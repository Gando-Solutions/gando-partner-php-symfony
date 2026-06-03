<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\Webhook;

use Gando\Partner\Symfony\Webhook\Deduplicator;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class DeduplicatorTest extends TestCase
{
    public function testWithoutCacheNeverDedupes(): void
    {
        $deduplicator = new Deduplicator();

        self::assertFalse($deduplicator->isDuplicate('{"a":1}'));
        self::assertFalse($deduplicator->isDuplicate('{"a":1}'));
    }

    public function testWithCacheMarksDuplicates(): void
    {
        $cache = new InMemoryCache();
        $deduplicator = new Deduplicator($cache, ttlSeconds: 60);

        self::assertFalse($deduplicator->isDuplicate('{"a":1}'));
        self::assertTrue($deduplicator->isDuplicate('{"a":1}'));
        self::assertFalse($deduplicator->isDuplicate('{"a":2}'));
    }
}

final class InMemoryCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }
}
