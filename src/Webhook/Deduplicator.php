<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Webhook;

use Psr\SimpleCache\CacheInterface;

/**
 * Drops duplicate webhook deliveries (Gando retries on non-2xx or timeout).
 *
 * Uses a SHA-256 hash of the raw body as idempotency key. Returns 200 without
 * re-dispatching when the same payload was already accepted.
 */
final class Deduplicator
{
    private const CACHE_KEY_PREFIX = 'gando_webhook_';

    public function __construct(
        private readonly ?CacheInterface $cache = null,
        private readonly int $ttlSeconds = 86_400,
    ) {
    }

    public function isDuplicate(string $rawBody): bool
    {
        if ($this->cache === null) {
            return false;
        }

        $key = self::CACHE_KEY_PREFIX.hash('sha256', $rawBody);

        if ($this->cache->has($key)) {
            return true;
        }

        $this->cache->set($key, true, $this->ttlSeconds);

        return false;
    }
}
