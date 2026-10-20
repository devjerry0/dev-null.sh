<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

final readonly class Giphy implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if (! in_array($url->getScheme(), ['http', 'https'], true)
            || ! in_array($url->getAsciiHost(), ['giphy.com', 'www.giphy.com'], true)) {
            return null;
        }

        if (($url->getUsername() ?? '') !== '' || ($url->getPassword() ?? '') !== '' || $url->getPort() !== null) {
            return null;
        }

        if (preg_match('#^/(?:gifs/(?:[a-z0-9-]+-)?|embed/)([a-z0-9]+)/?$#i', $url->getPath(), $matches) !== 1) {
            return null;
        }

        return Embed::gif('GIPHY', "https://media.giphy.com/media/{$matches[1]}/giphy.gif", "https://media.giphy.com/media/{$matches[1]}/giphy_s.gif");
    }
}
