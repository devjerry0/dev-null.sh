<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\DnsResolver;
use Symfony\Component\HttpFoundation\IpUtils;
use Uri\WhatWg\Url;

final readonly class LinkPreviewAddress
{
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

    public function __construct(private DnsResolver $dns)
    {
        //
    }

    /**
     * Returns the address to connect to, or null when the URL must not be fetched: anything but plain http(s) on a
     * default port without credentials, or a host that resolves to nothing or to any non-public address.
     */
    public function get(Url $url): ?string
    {
        if (! in_array($url->getScheme(), ['http', 'https'], true)) {
            return null;
        }

        if (($url->getUsername() ?? '') !== '') {
            return null;
        }

        if (($url->getPassword() ?? '') !== '') {
            return null;
        }

        if ($url->getPort() !== null) {
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
}
