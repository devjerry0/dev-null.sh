<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\DnsResolver;
use App\Models\LinkPreview;
use App\Services\GifLinks;
use App\Services\LinkPreviewMetadata;
use App\Services\LinkPreviewRequest;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Uri\WhatWg\Url;

final readonly class UpdateLinkPreview
{
    private const int MAX_REDIRECTS = 3;

    private const int MAX_BODY_BYTES = 1_048_576;

    /**
     * Non-public ranges that filter_var's private and reserved range flags let through: carrier-grade NAT, IETF protocol
     * assignments, 6to4 relays, benchmarking, documentation and multicast for IPv4; NAT64, discard, Teredo,
     * documentation, 6to4, site-local, multicast and IPv4-translated addresses for IPv6.
     *
     * @var list<string>
     */
    private const array BLOCKED_RANGES = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/32',
        '2001:db8::/32',
        '2002::/16',
        'fec0::/10',
        'ff00::/8',
        '::ffff:0:0:0/96',
    ];

    private const array NO_METADATA = [
        'title' => null,
        'description' => null,
        'image_url' => null,
        'site_name' => null,
    ];

    public function __construct(
        private DnsResolver $dns,
        private GifLinks $gifLinks,
        private LinkPreviewRequest $request,
        private LinkPreviewMetadata $metadata,
    ) {
        //
    }

    /**
     * Fetches the page behind the link and stores its Open Graph metadata. A page that can't or mustn't be fetched is
     * marked as fetched without metadata, so it is not tried again. Failures that may pass, such as connection errors,
     * timeouts, rate limits and server errors, are thrown instead, so the caller can try again later.
     */
    public function handle(LinkPreview $linkPreview): void
    {
        $linkPreview->update(['gif_url' => null, ...$this->fetchMetadata($linkPreview->url), 'fetched_at' => now()]);
    }

    /**
     * Follows up to three redirects by hand, so that every hop goes through the same checks as the original URL.
     *
     * @return array{title: string|null, description: string|null, image_url: string|null, site_name: string|null, gif_url?: string}
     */
    private function fetchMetadata(string $url): array
    {
        $page = Url::parse($url);
        $isGif = $this->gifLinks->isGifUrl($page);
        $headers = $this->requestHeaders($isGif);
        $maxBodyBytes = $isGif ? GifLinks::HEADER_BYTES : self::MAX_BODY_BYTES;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS && $page instanceof Url; $hop++) {
            $address = $this->publicAddress($page);

            if ($address === null || ($isGif && $page->getScheme() !== 'https')) {
                return self::NO_METADATA;
            }

            $response = $this->request->get($page, $address, $headers, $maxBodyBytes);
            $response->throwIf($response->serverError() || $response->tooManyRequests());

            if ($response->redirect()) {
                $page = Url::parse($response->header('Location'), $page);

                continue;
            }

            return $this->responseMetadata($response, $page, $isGif);
        }

        return self::NO_METADATA;
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(bool $isGif): array
    {
        if (! $isGif) {
            return ['Accept' => 'text/html'];
        }

        return ['Accept' => GifLinks::CONTENT_TYPE, 'Range' => sprintf('bytes=0-%d', GifLinks::HEADER_BYTES - 1)];
    }

    /**
     * @return array{title: string|null, description: string|null, image_url: string|null, site_name: string|null, gif_url?: string}
     */
    private function responseMetadata(Response $response, Url $page, bool $isGif): array
    {
        if (! $response->successful()) {
            return self::NO_METADATA;
        }

        if ($isGif) {
            return $this->getGifMetadata($response, $page);
        }

        if (! $this->isHtml($response)) {
            return self::NO_METADATA;
        }

        return $this->metadata->parse($this->body($response), $page);
    }

    /**
     * @return array{title: string|null, description: string|null, image_url: string|null, site_name: string|null, gif_url?: string}
     */
    private function getGifMetadata(Response $response, Url $page): array
    {
        $url = $this->gifLinks->getVerifiedUrl($page, $response->header('Content-Type'), $this->body($response));

        if ($url === null) {
            return self::NO_METADATA;
        }

        return [...self::NO_METADATA, 'gif_url' => $url];
    }

    /**
     * Returns the address to connect to, or null when the URL must not be fetched: anything but plain http(s) on a
     * default port without credentials, or a host that resolves to nothing or to any non-public address.
     */
    private function publicAddress(Url $url): ?string
    {
        if (! in_array($url->getScheme(), ['http', 'https'], true)
            || ($url->getUsername() ?? '') !== ''
            || ($url->getPassword() ?? '') !== ''
            || ! in_array($url->getPort(), [null, LinkPreviewRequest::HTTP_PORT, LinkPreviewRequest::HTTPS_PORT], true)) {
            return null;
        }

        $addresses = $this->dns->resolve(mb_trim((string) $url->getAsciiHost(), '[]'));

        return $addresses !== [] && array_all($addresses, $this->isPublic(...)) ? $addresses[0] : null;
    }

    private function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! IpUtils::checkIp($address, self::BLOCKED_RANGES);
    }

    private function isHtml(Response $response): bool
    {
        $mediaType = mb_strtolower(mb_trim(Str::before($response->header('Content-Type'), ';')));

        return in_array($mediaType, ['text/html', 'application/xhtml+xml'], true);
    }

    private function body(Response $response): string
    {
        $body = $response->toPsrResponse()->getBody();
        $body->rewind();

        return Utils::copyToString($body, self::MAX_BODY_BYTES);
    }
}
