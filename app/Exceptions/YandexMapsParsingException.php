<?php

namespace App\Exceptions;

use RuntimeException;

class YandexMapsParsingException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message, private readonly array $details = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['source' => 'yandex-maps', ...$this->details];
    }
}
