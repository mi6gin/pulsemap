<?php

namespace Tests\Unit\Rules;

use App\Rules\YandexMapsUrl;
use PHPUnit\Framework\TestCase;

class YandexMapsUrlTest extends TestCase
{
    public function test_accepts_full_and_short_yandex_maps_links(): void
    {
        $this->assertSame([], $this->failuresFor('https://yandex.ru/maps/org/name/1234567890/'));
        $this->assertSame([], $this->failuresFor('https://yandex.kz/maps/-/CDabc123'));
    }

    public function test_rejects_foreign_hosts_and_unsafe_url_components(): void
    {
        $this->assertNotEmpty($this->failuresFor('https://example.com/maps/org/name/1234567890/'));
        $this->assertNotEmpty($this->failuresFor('https://user:secret@yandex.ru/maps/org/name/1234567890/'));
        $this->assertNotEmpty($this->failuresFor('http://yandex.ru/maps/org/name/1234567890/'));
    }

    /** @return list<string> */
    private function failuresFor(string $url): array
    {
        $failures = [];
        (new YandexMapsUrl)->validate('url', $url, function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        return $failures;
    }
}
