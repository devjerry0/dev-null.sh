<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Exception\ResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Uri\WhatWg\Url;

final readonly class LinkPreviewRequest
{
    public const int HTTP_PORT = 80;

    public const int HTTPS_PORT = 443;

    private const int CONNECT_TIMEOUT_SECONDS = 3;

    private const int TIMEOUT_SECONDS = 5;

    /**
     * @param  array<string, string>  $headers
     */
    public function get(Url $url, string $address, array $headers, int $maxBodyBytes): Response
    {
        $port = $url->getPort() ?? ($url->getScheme() === 'https' ? self::HTTPS_PORT : self::HTTP_PORT);
        $connection = sprintf('::%s:%d', str_contains($address, ':') ? "[{$address}]" : $address, $port);

        try {
            return Http::withUserAgent(sprintf('Mozilla/5.0 (compatible; devnull-linkpreview/1.0; +%s)', config()->string('app.url')))
                ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9', ...$headers])
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withoutRedirecting()
                ->withOptions([
                    'decode_content' => false,
                    'progress' => fn (int $expected, int $downloaded): bool => $downloaded > $maxBodyBytes,
                    'curl' => [CURLOPT_CONNECT_TO => [$connection]],
                ])
                ->get($url->toAsciiString());
        } catch (ConnectionException $connectionException) {
            $previous = $connectionException->getPrevious();

            throw_unless($previous instanceof ResponseException, $connectionException);

            return new Response($previous->getResponse());
        }
    }
}
