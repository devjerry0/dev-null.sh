<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class GifLinks
{
    public const string CONTENT_TYPE = 'image/gif';

    public const int HEADER_BYTES = 32;

    private const int MIN_HEADER_BYTES = 13;

    private const int MAX_URL_LENGTH = 2048;

    private const int SIGNATURE_BYTES = 6;

    private const int WIDTH_OFFSET = 6;

    private const int HEIGHT_OFFSET = 8;

    private const int DIMENSION_BYTES = 2;

    public function isGifUrl(?Url $url): bool
    {
        if ($url?->getScheme() !== 'https') {
            return false;
        }

        return str_ends_with(mb_strtolower($url->getPath()), '.gif');
    }

    public function getVerifiedUrl(Url $url, string $contentType, string $header): ?string
    {
        if (mb_strtolower(mb_trim(Str::before($contentType, ';'))) !== self::CONTENT_TYPE
            || mb_strlen($url->toAsciiString()) > self::MAX_URL_LENGTH) {
            return null;
        }

        if (strlen($header) < self::MIN_HEADER_BYTES) {
            return null;
        }

        if (! in_array(substr($header, 0, self::SIGNATURE_BYTES), ['GIF87a', 'GIF89a'], true)) {
            return null;
        }

        if (substr($header, self::WIDTH_OFFSET, self::DIMENSION_BYTES) === "\0\0"
            || substr($header, self::HEIGHT_OFFSET, self::DIMENSION_BYTES) === "\0\0") {
            return null;
        }

        return $url->toAsciiString();
    }
}
