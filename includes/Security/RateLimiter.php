<?php

declare(strict_types=1);

namespace WPNativeAgent\Security;

/**
 * Rate limiter for per-user and per-ip request limits.
 */
final class RateLimiter
{
    /**
     * @var callable
     */
    private $getValue;

    /**
     * @var callable
     */
    private $setValue;

    /**
     * @var callable
     */
    private $time;

    /**
     * @var array<string, array{count:int,reset_at:int}>
     */
    private static array $memoryStore = [];

    public function __construct(?callable $getValue = null, ?callable $setValue = null, ?callable $time = null)
    {
        $this->getValue = $getValue ?? static fn (string $key): mixed => self::default_get($key);
        $this->setValue = $setValue ?? static function (string $key, array $value, int $ttl): void {
            self::default_set($key, $value, $ttl);
        };
        $this->time = $time ?? static fn (): int => time();
    }

    /**
     * @return array{allowed: bool, remaining: int, reset_at: int}
     */
    public function hit(string $key, int $limit, int $windowSeconds): array
    {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);

        $now = (int) call_user_func($this->time);
        $state = call_user_func($this->getValue, $key);

        if (! is_array($state) || ! isset($state['count'], $state['reset_at']) || (int) $state['reset_at'] <= $now) {
            $state = [
                'count' => 0,
                'reset_at' => $now + $windowSeconds,
            ];
        }

        if ((int) $state['count'] >= $limit) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => (int) $state['reset_at'],
            ];
        }

        $state['count'] = (int) $state['count'] + 1;
        call_user_func($this->setValue, $key, $state, $windowSeconds);

        return [
            'allowed' => true,
            'remaining' => max(0, $limit - (int) $state['count']),
            'reset_at' => (int) $state['reset_at'],
        ];
    }

    /**
     * @return array{allowed: bool, minute: array<string,mixed>, day: array<string,mixed>}
     */
    public function check_user_limits(int $userId, int $perMinute, int $perDay): array
    {
        $minute = $this->hit('wpna_rate_user_minute_' . $userId, $perMinute, 60);
        $day = $this->hit('wpna_rate_user_day_' . $userId, $perDay, 86400);

        return [
            'allowed' => $minute['allowed'] && $day['allowed'],
            'minute' => $minute,
            'day' => $day,
        ];
    }

    /**
     * @return array{allowed: bool, remaining: int, reset_at: int}
     */
    public function check_ip_limit(string $ip, int $perMinute): array
    {
        return $this->hit('wpna_rate_ip_minute_' . $ip, $perMinute, 60);
    }

    private static function default_get(string $key): mixed
    {
        if (function_exists('get_transient')) {
            return get_transient($key);
        }

        return self::$memoryStore[$key] ?? null;
    }

    /**
     * @param array{count:int,reset_at:int} $value
     */
    private static function default_set(string $key, array $value, int $ttl): void
    {
        if (function_exists('set_transient')) {
            set_transient($key, $value, $ttl);
            return;
        }

        self::$memoryStore[$key] = $value;
    }
}
