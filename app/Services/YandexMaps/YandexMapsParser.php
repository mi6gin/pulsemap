<?php

namespace App\Services\YandexMaps;

use App\Exceptions\YandexMapsParsingException;
use Carbon\CarbonImmutable;
use Closure;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Sleep;
use Throwable;

class YandexMapsParser
{
    private const RATE_LIMIT_KEY = 'yandex-maps:http-requests';

    /** @var list<string> */
    private const ALLOWED_HOSTS = [
        'yandex.ru',
        'yandex.com',
        'yandex.kz',
        'yandex.by',
        'yandex.uz',
        'yandex.com.tr',
        'yandex.com.ge',
    ];

    public function parse(string $sourceUrl, ?Closure $onProgress = null): ParsedOrganization
    {
        $this->assertAllowedUrl($sourceUrl);

        $cookieJar = new CookieJar;
        [$html, $canonicalUrl] = $this->fetchOrganizationPage($sourceUrl, $cookieJar);
        $externalId = $this->extractBusinessId($canonicalUrl, $html);
        $pageMetadata = $this->extractMetadata($html, $externalId);
        $reviewsUrl = $this->reviewsUrl($canonicalUrl);

        $reviews = [];
        $seenReviewIds = [];
        $pageSize = (int) config('services.yandex_maps.page_size', 50);
        $maxPages = (int) config('services.yandex_maps.max_pages', 20);

        for ($page = 1; $page <= $maxPages; $page++) {
            $pageHtml = $page === 1 && $this->isFirstReviewsPage($canonicalUrl)
                ? $html
                : $this->fetchReviewsPage($reviewsUrl, $page, $cookieJar);
            $rawReviews = $this->extractReviews($pageHtml);

            if ($rawReviews === []) {
                if ($page === 1 && ($pageMetadata['reviews_count'] ?? 0) > 0) {
                    throw new YandexMapsParsingException(
                        'Формат страницы Яндекс.Карт изменился: не найден список отзывов.',
                        ['business_id' => $externalId, 'page' => $page],
                    );
                }

                break;
            }

            $newReviewsCount = 0;
            foreach ($rawReviews as $rawReview) {
                if (! is_array($rawReview)) {
                    throw new YandexMapsParsingException(
                        'Формат отзыва в ответе Яндекс.Карт изменился.',
                        ['business_id' => $externalId, 'page' => $page],
                    );
                }

                $review = $this->mapReview($rawReview, $externalId);
                if (isset($seenReviewIds[$review->id])) {
                    continue;
                }

                $seenReviewIds[$review->id] = true;
                $reviews[] = $review;
                $newReviewsCount++;
            }

            $pageMetadata = $this->mergeMetadata($pageMetadata, $this->extractMetadata($pageHtml, $externalId));
            $metadata = $pageMetadata;
            $expectedReviews = $metadata['reviews_count'];
            $estimatedPages = max(1, (int) ceil(($expectedReviews ?? count($reviews)) / $pageSize));
            if ($onProgress !== null) {
                $onProgress(min(94, 10 + (int) floor(($page / max(1, $estimatedPages)) * 84)));
            }

            if (($expectedReviews !== null && count($reviews) >= $expectedReviews)
                || count($rawReviews) < $pageSize
                || $newReviewsCount === 0) {
                break;
            }

            if ($page === $maxPages) {
                throw new YandexMapsParsingException(
                    'Достигнут лимит страниц парсера. Увеличьте YANDEX_MAPS_MAX_PAGES.',
                    ['business_id' => $externalId, 'max_pages' => $maxPages],
                );
            }

            $this->throttle();
        }

        $metadata = $pageMetadata;

        $name = $metadata['name'];
        $ratingsCount = $metadata['ratings_count'];
        $reviewsCount = $metadata['reviews_count'] ?? count($reviews);

        if ($name === null || $ratingsCount === null) {
            throw new YandexMapsParsingException(
                'Не удалось надёжно извлечь название и счётчики. Вероятно, формат Яндекс.Карт изменился.',
                ['business_id' => $externalId],
            );
        }

        if ($reviewsCount > 0 && $reviews === []) {
            throw new YandexMapsParsingException(
                'Яндекс сообщил о наличии отзывов, но не вернул их. Возможна блокировка или смена формата.',
                ['business_id' => $externalId, 'reviews_count' => $reviewsCount],
            );
        }

        return new ParsedOrganization(
            externalId: $externalId,
            canonicalUrl: $canonicalUrl,
            name: $name,
            rating: $metadata['rating'],
            ratingsCount: $ratingsCount,
            reviewsCount: $reviewsCount,
            reviews: $reviews,
        );
    }

    /** @return array{0: string, 1: string} */
    private function fetchOrganizationPage(string $sourceUrl, CookieJar $cookieJar): array
    {
        $currentUrl = $sourceUrl;

        for ($redirect = 0; $redirect <= 5; $redirect++) {
            $response = $this->request($cookieJar)
                ->withOptions(['allow_redirects' => false])
                ->get($currentUrl);

            $this->guardResponse($response, $currentUrl);

            if (! $response->redirect()) {
                $html = $response->body();
                if (mb_strlen($html) < 1000) {
                    throw new YandexMapsParsingException(
                        'Яндекс.Карты вернули неожиданно короткую страницу.',
                        ['url' => $currentUrl, 'bytes' => mb_strlen($html)],
                    );
                }

                return [$html, $currentUrl];
            }

            $location = $response->header('Location');
            if ($location === '') {
                throw new YandexMapsParsingException('Яндекс.Карты вернули редирект без адреса.');
            }

            $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);
            $this->assertAllowedUrl($currentUrl);
        }

        throw new YandexMapsParsingException('Слишком много редиректов при открытии карточки.');
    }

    private function fetchReviewsPage(string $reviewsUrl, int $page, CookieJar $cookieJar): string
    {
        $url = $reviewsUrl.'?'.http_build_query(['page' => $page]);
        $response = $this->request($cookieJar)
            ->withHeaders(['Referer' => $reviewsUrl])
            ->get($url);

        $this->guardResponse($response, $url);

        return $response->body();
    }

    private function reviewsUrl(string $canonicalUrl): string
    {
        $parts = parse_url($canonicalUrl);
        $path = $parts['path'] ?? '';

        if (preg_match('~^(/maps/(?:org|business)/[^/]+/[0-9]+)~u', $path, $matches) !== 1) {
            throw new YandexMapsParsingException(
                'Не удалось построить адрес раздела с отзывами.',
                ['url' => $canonicalUrl],
            );
        }

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$matches[1].'/reviews/';
    }

    private function isFirstReviewsPage(string $url): bool
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return str_contains((string) parse_url($url, PHP_URL_PATH), '/reviews')
            && (! isset($query['page']) || (int) $query['page'] === 1);
    }

    private function request(CookieJar $cookieJar): PendingRequest
    {
        $userAgents = config('services.yandex_maps.user_agents', []);
        $userAgent = is_array($userAgents) && $userAgents !== []
            ? $userAgents[array_rand($userAgents)]
            : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/128 Safari/537.36';

        $options = ['cookies' => $cookieJar];
        $proxy = config('services.yandex_maps.proxy');
        if (is_string($proxy) && $proxy !== '') {
            $options['proxy'] = $proxy;
        }

        return Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.7',
            'User-Agent' => $userAgent,
        ])->withOptions($options)
            ->connectTimeout((int) config('services.yandex_maps.connect_timeout', 10))
            ->timeout((int) config('services.yandex_maps.timeout', 25))
            ->beforeSending(function (): void {
                $this->acquireRequestSlot();
            })
            ->retry(
                [500, 1500, 3000],
                0,
                static fn (?Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429)),
                false,
            );
    }

    private function guardResponse(Response $response, string $url): void
    {
        if (in_array($response->status(), [403, 429], true)) {
            throw new YandexMapsParsingException(
                'Яндекс.Карты временно ограничили запросы. Повторим позже.',
                ['status' => $response->status(), 'url' => $url],
                true,
            );
        }

        if ($response->notFound()) {
            throw new YandexMapsParsingException('Карточка организации не найдена или удалена.', ['url' => $url]);
        }

        if ($response->failed()) {
            throw new YandexMapsParsingException(
                'Яндекс.Карты вернули ошибку.',
                ['status' => $response->status(), 'url' => $url],
                $response->serverError(),
            );
        }
    }

    private function extractBusinessId(string $canonicalUrl, string $html): string
    {
        $patterns = [
            '~/(?:org|business)/[^/?#]+/(\\d{6,})~u',
            '~[?&](?:oid|orgpage)=([0-9]{6,})~u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $canonicalUrl, $matches) === 1) {
                return $matches[1];
            }
        }

        foreach (['businessId', 'orgId', 'oid'] as $key) {
            if (preg_match('/\\?"'.preg_quote($key, '/').'\\?"\\s*:\\s*\\?"?([0-9]{6,})/u', $html, $matches) === 1) {
                return $matches[1];
            }
        }

        throw new YandexMapsParsingException(
            'Не удалось определить ID организации. Проверьте ссылку или формат источника.',
            ['url' => $canonicalUrl],
        );
    }

    /**
     * @return array{name: ?string, rating: ?float, ratings_count: ?int, reviews_count: ?int}
     */
    private function extractMetadata(string $html, string $businessId): array
    {
        $metadata = ['name' => null, 'rating' => null, 'ratings_count' => null, 'reviews_count' => null];

        foreach ($this->jsonBlocks($html) as $block) {
            $candidate = $this->findMetadataCandidate($block, $businessId);
            if ($candidate !== null) {
                $metadata = $this->mergeMetadata($metadata, $candidate);
            }
        }

        $metadata = $this->mergeMetadata($metadata, [
            'name' => $this->extractPageTitle($html),
            'rating' => $this->matchMetaItemProp($html, 'ratingValue')
                ?? $this->matchNumber($html, ['rating', 'ratingValue']),
            'ratings_count' => ($value = $this->matchMetaItemProp($html, 'ratingCount')) !== null
                ? (int) $value
                : $this->matchInteger($html, ['ratingCount', 'ratingsCount']),
            'reviews_count' => ($value = $this->matchMetaItemProp($html, 'reviewCount')) !== null
                ? (int) $value
                : $this->matchInteger($html, ['reviewCount', 'reviewsCount']),
        ]);

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{name: ?string, rating: ?float, ratings_count: ?int, reviews_count: ?int}|null
     */
    private function findMetadataCandidate(array $value, string $businessId): ?array
    {
        $best = null;
        $bestScore = 0;
        $stack = [$value];

        while ($stack !== []) {
            $current = array_pop($stack);
            if (! is_array($current)) {
                continue;
            }

            foreach ($current as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }

            $candidateId = Arr::first([
                $current['businessId'] ?? null,
                $current['orgId'] ?? null,
                $current['oid'] ?? null,
                $current['id'] ?? null,
            ], static fn (mixed $id): bool => is_scalar($id) && (string) $id === $businessId);

            $name = $this->firstString($current, ['title', 'name', 'businessName']);
            $rating = $this->firstFloat($current, ['rating', 'ratingValue', 'businessRating']);
            $ratingsCount = $this->firstInteger($current, ['ratingCount', 'ratingsCount', 'rating_count']);
            $reviewsCount = $this->firstInteger($current, ['reviewCount', 'reviewsCount', 'review_count']);

            $score = ($candidateId !== null ? 5 : 0)
                + ($name !== null ? 1 : 0)
                + ($rating !== null ? 1 : 0)
                + ($ratingsCount !== null ? 2 : 0)
                + ($reviewsCount !== null ? 2 : 0);

            if ($score > $bestScore && ($candidateId !== null || $score >= 4)) {
                $bestScore = $score;
                $best = compact('name', 'rating', 'ratingsCount', 'reviewsCount');
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'name' => $best['name'],
            'rating' => $best['rating'],
            'ratings_count' => $best['ratingsCount'],
            'reviews_count' => $best['reviewsCount'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function jsonBlocks(string $html): array
    {
        preg_match_all('~<script[^>]*>(.*?)</script>~siu', $html, $matches);
        $blocks = [];

        foreach ($matches[1] ?? [] as $rawBlock) {
            $rawBlock = html_entity_decode(trim($rawBlock), ENT_QUOTES | ENT_HTML5);
            if ($rawBlock === '' || (! str_starts_with($rawBlock, '{') && ! str_starts_with($rawBlock, '['))) {
                continue;
            }

            $decoded = json_decode($rawBlock, true);
            if (is_array($decoded)) {
                $blocks[] = $decoded;
            }
        }

        return $blocks;
    }

    /** @return list<array<string, mixed>> */
    private function extractReviews(string $html): array
    {
        $reviews = [];

        foreach ($this->jsonBlocks($html) as $block) {
            $stack = [$block];

            while ($stack !== []) {
                $current = array_pop($stack);
                if (! is_array($current)) {
                    continue;
                }

                if ($this->firstString($current, ['reviewId']) !== null
                    && $this->firstInteger($current, ['rating', 'stars']) !== null) {
                    $reviews[] = $current;

                    continue;
                }

                foreach ($current as $child) {
                    if (is_array($child)) {
                        $stack[] = $child;
                    }
                }
            }
        }

        return array_reverse($reviews);
    }

    private function mapReview(array $rawReview, string $businessId): ParsedReview
    {
        $id = $this->firstString($rawReview, ['reviewId', 'id']);
        $rating = $this->firstInteger($rawReview, ['rating', 'stars']);

        if ($id === null || $rating === null || $rating < 1 || $rating > 5) {
            throw new YandexMapsParsingException(
                'Отзыв не содержит ожидаемые ID или оценку.',
                ['business_id' => $businessId],
            );
        }

        $author = is_array($rawReview['author'] ?? null) ? $rawReview['author'] : [];
        $authorName = $this->firstString($author, ['name', 'displayName']) ?? 'Анонимный автор';

        return new ParsedReview(
            id: $id,
            authorName: $authorName,
            authorAvatarUrl: $this->firstString($author, ['avatarHref', 'avatarUrl', 'avatar']),
            publishedAt: $this->parseDate($this->firstScalar($rawReview, ['updatedTime', 'createdTime', 'date'])),
            text: $this->firstString($rawReview, ['text', 'comment']),
            rating: $rating,
            updatedAt: $this->parseDate($this->firstScalar($rawReview, ['updatedTime'])),
        );
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (int) $value;
                if ($timestamp > 9999999999) {
                    $timestamp = (int) floor($timestamp / 1000);
                }

                return CarbonImmutable::createFromTimestampUTC($timestamp);
            }

            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array{name: ?string, rating: ?float, ratings_count: ?int, reviews_count: ?int}  $base
     * @param  array{name?: ?string, rating?: ?float, ratings_count?: ?int, reviews_count?: ?int}  $additional
     * @return array{name: ?string, rating: ?float, ratings_count: ?int, reviews_count: ?int}
     */
    private function mergeMetadata(array $base, array $additional): array
    {
        foreach (array_keys($base) as $key) {
            if (($base[$key] ?? null) === null && array_key_exists($key, $additional) && $additional[$key] !== null) {
                $base[$key] = $additional[$key];
            }
        }

        return $base;
    }

    /** @param list<string> $keys */
    private function firstString(array $value, array $keys): ?string
    {
        $found = $this->firstScalar($value, $keys);

        return is_scalar($found) && trim((string) $found) !== '' ? trim((string) $found) : null;
    }

    /** @param list<string> $keys */
    private function firstInteger(array $value, array $keys): ?int
    {
        $found = $this->firstScalar($value, $keys);

        return is_numeric($found) ? (int) $found : null;
    }

    /** @param list<string> $keys */
    private function firstFloat(array $value, array $keys): ?float
    {
        $found = $this->firstScalar($value, $keys);

        return is_numeric($found) ? (float) $found : null;
    }

    /** @param list<string> $keys */
    private function firstScalar(array $value, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $value) && is_scalar($value[$key])) {
                return $value[$key];
            }
        }

        return null;
    }

    /** @param list<string> $keys */
    private function matchInteger(string $html, array $keys): ?int
    {
        $value = $this->matchNumber($html, $keys);

        return $value === null ? null : (int) $value;
    }

    /** @param list<string> $keys */
    private function matchNumber(string $html, array $keys): ?float
    {
        foreach ($keys as $key) {
            $pattern = '/\\?"'.preg_quote($key, '/').'\\?"\\s*:\\s*\\?"?([0-9]+(?:[.,][0-9]+)?)/u';
            if (preg_match($pattern, $html, $matches) === 1) {
                return (float) str_replace(',', '.', $matches[1]);
            }
        }

        return null;
    }

    private function matchMetaItemProp(string $html, string $property): ?float
    {
        $quotedProperty = preg_quote($property, '~');
        $patterns = [
            '~<meta[^>]+itemprop=["\']'.$quotedProperty.'["\'][^>]+content=["\']([0-9]+(?:[.,][0-9]+)?)["\']~iu',
            '~<meta[^>]+content=["\']([0-9]+(?:[.,][0-9]+)?)["\'][^>]+itemprop=["\']'.$quotedProperty.'["\']~iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return (float) str_replace(',', '.', $matches[1]);
            }
        }

        return null;
    }

    private function extractPageTitle(string $html): ?string
    {
        if (preg_match('~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']~iu', $html, $matches) !== 1
            && preg_match('~<title>([^<]+)</title>~iu', $html, $matches) !== 1) {
            return null;
        }

        return trim((string) preg_replace('/\\s*[\x{2014}-]\\s*(?:Яндекс\\.?Карты|Yandex Maps).*$/iu', '', html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5)));
    }

    private function throttle(): void
    {
        $minimum = (int) config('services.yandex_maps.delay_min_ms', 250);
        $maximum = max($minimum, (int) config('services.yandex_maps.delay_max_ms', 650));

        if ($maximum > 0) {
            Sleep::for(random_int($minimum, $maximum))->milliseconds();
        }
    }

    private function acquireRequestSlot(): void
    {
        $requestsPerMinute = (int) config('services.yandex_maps.requests_per_minute', 12);
        if ($requestsPerMinute <= 0) {
            return;
        }

        while (! RateLimiter::attempt(
            self::RATE_LIMIT_KEY,
            $requestsPerMinute,
            static fn (): bool => true,
            60,
        )) {
            Sleep::for(max(1, RateLimiter::availableIn(self::RATE_LIMIT_KEY)))->seconds();
        }
    }

    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = mb_strtolower($parts['host'] ?? '');
        $host = str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;

        if (($parts['scheme'] ?? null) !== 'https'
            || ! in_array($host, self::ALLOWED_HOSTS, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            throw new YandexMapsParsingException('Недопустимый адрес источника.', ['url' => $url]);
        }
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (str_starts_with($location, 'https://')) {
            return $location;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($location, '//')) {
            return 'https:'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';

        return $origin.rtrim(dirname($path), '/').'/'.$location;
    }
}
