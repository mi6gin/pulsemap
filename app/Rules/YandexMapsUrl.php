<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class YandexMapsUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('Вставьте корректную ссылку на карточку в Яндекс.Картах.');

            return;
        }

        $parts = parse_url($value);
        $host = mb_strtolower($parts['host'] ?? '');
        $host = str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;
        $path = $parts['path'] ?? '';

        $allowedHosts = [
            'yandex.ru', 'yandex.com', 'yandex.kz', 'yandex.by',
            'yandex.uz', 'yandex.com.tr', 'yandex.com.ge',
        ];

        if (($parts['scheme'] ?? '') !== 'https'
            || ! in_array($host, $allowedHosts, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || ! str_starts_with($path, '/maps/')
        ) {
            $fail('Поддерживаются только HTTPS-ссылки на карточки в Яндекс.Картах.');
        }
    }
}
