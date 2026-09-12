<?php

namespace Tests\Unit\Rules;

use App\Rules\YandexMapsUrl;
use PHPUnit\Framework\TestCase;

class YandexMapsUrlTest extends TestCase
{
    public function test_accepts_full_and_short_yandex_maps_links(): void
    {
        $this->assertSame([], $this->failuresFor('https://yandex.ru/maps/org/name/1234567890/'));
        $this->assertSame([], $this->failuresFor('https://yandex.ru/maps/org/1234567890/'));
        $this->assertSame([], $this->failuresFor('https://yandex.kz/maps/-/CDabc123'));
        $this->assertSame([], $this->failuresFor('https://yandex.ru/maps/213/moscow/?ll=37.6%2C55.7&oid=1234567890'));
    }

    public function test_rejects_foreign_hosts_and_unsafe_url_components(): void
    {
        $this->assertNotEmpty($this->failuresFor('https://example.com/maps/org/name/1234567890/'));
        $this->assertNotEmpty($this->failuresFor('https://user:secret@yandex.ru/maps/org/name/1234567890/'));
        $this->assertNotEmpty($this->failuresFor('http://yandex.ru/maps/org/name/1234567890/'));
        $this->assertNotEmpty($this->failuresFor('https://yandex.ru/profile/1234567890'));
        $this->assertNotEmpty($this->failuresFor('https://yandex.ru/maps/213/moscow/search/cafe'));
        $this->assertNotEmpty($this->failuresFor('https://yandex.ru/maps/213/moscow/?oid=not-an-id'));
        $this->assertNotEmpty($this->failuresFor('https://yandex.ru/maps/org/name/'));
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
