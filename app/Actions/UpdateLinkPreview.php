<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\PreviewLink;
use App\Dto\LinkPreviewMetadata;
use App\Dto\LinkPreviewRequest;
use App\Models\LinkPreview;
use App\Services\LinkPreviews\LinkPreviewAddress;
use App\Services\LinkPreviews\LinkPreviewBudget;
use App\Services\LinkPreviews\LinkPreviewHttpClient;
use App\Services\PreviewLinkProviders\GifPreviewLink;
use App\Services\PreviewLinkProviders\HtmlPreviewLink;
use Illuminate\Http\Client\Response;
use Uri\WhatWg\Url;

final readonly class UpdateLinkPreview
{
    private const int MAX_REDIRECTS = 3;

    private const string HEADER_ACCEPT = 'Accept';

    private const string HEADER_RANGE = 'Range';

    public function __construct(
        private LinkPreviewAddress $addresses,
        private LinkPreviewBudget $budget,
        private GifPreviewLink $gifLinks,
        private LinkPreviewHttpClient $http,
        private HtmlPreviewLink $html,
    ) {
        //
    }

    public function handle(LinkPreview $linkPreview): void
    {
        $metadata = $this->fetchMetadata($linkPreview->url);

        $linkPreview->update([...$metadata->toArray(), 'fetched_at' => now()]);
    }

    private function fetchMetadata(string $url): LinkPreviewMetadata
    {
        $page = Url::parse($url);
        $isGif = $this->gifLinks->isGifUrl($page);
        $previewLink = $isGif ? $this->gifLinks : $this->html;
        $deadline = $this->budget->start();
        $visited = [];

        for ($hop = 0; $hop <= self::MAX_REDIRECTS && $page instanceof Url; $hop++) {
            $source = $page->withFragment(null)->toAsciiString();

            if (isset($visited[$source])) {
                return new LinkPreviewMetadata;
            }

            $visited[$source] = true;
            $request = $this->prepareRequest($page, $isGif, $deadline);

            if (! $request instanceof LinkPreviewRequest) {
                return new LinkPreviewMetadata;
            }

            $response = $this->http->get($request);
            $response->throwIf($response->serverError() || $response->tooManyRequests());

            if (! $response->redirect()) {
                return $this->responseMetadata($response, $page, $previewLink);
            }

            $page = $this->redirectPage($response, $page);
        }

        return new LinkPreviewMetadata;
    }

    private function prepareRequest(Url $page, bool $isGif, float $deadline): ?LinkPreviewRequest
    {
        if ($isGif && $page->getScheme() !== 'https') {
            return null;
        }

        $address = $this->addresses->get($page);
        $remaining = $this->budget->remaining($deadline);

        if ($address === null || $remaining <= 0) {
            return null;
        }

        return new LinkPreviewRequest(
            url: $page,
            address: $address,
            headers: $this->requestHeaders($isGif),
            maxBodyBytes: $isGif ? GifPreviewLink::HEADER_BYTES : HtmlPreviewLink::MAX_BODY_BYTES,
            timeoutSeconds: $remaining,
        );
    }

    private function redirectPage(Response $response, Url $page): ?Url
    {
        $location = mb_trim($response->header('Location'));

        if ($location === '') {
            return null;
        }

        return Url::parse($location, $page);
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(bool $isGif): array
    {
        if (! $isGif) {
            return [self::HEADER_ACCEPT => HtmlPreviewLink::CONTENT_TYPE];
        }

        return [self::HEADER_ACCEPT => GifPreviewLink::CONTENT_TYPE, self::HEADER_RANGE => sprintf('bytes=0-%d', GifPreviewLink::HEADER_BYTES - 1)];
    }

    private function responseMetadata(Response $response, Url $page, PreviewLink $previewLink): LinkPreviewMetadata
    {
        if (! $response->successful()) {
            return new LinkPreviewMetadata;
        }

        return $previewLink->metadata($response, $page);
    }
}
