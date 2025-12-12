<?php

declare(strict_types=1);

namespace WPClaw\Session;

use InvalidArgumentException;
use RuntimeException;

/**
 * Repository for user chat sessions persisted in wpclaw_sessions table.
 */
final class SessionRepository
{
    private object $wpdb;

    private string $table;

    public function __construct(object $wpdb, ?Schema $schema = null)
    {
        $this->wpdb = $wpdb;
        $this->table = $schema?->sessions_table_name() ?? $this->resolve_default_table_name($wpdb);
    }

    /**
     * @return array<string, int|string>|null
     */
    public function get_by_user_id(int $userId): ?array
    {
        $this->assert_positive($userId, 'userId');

        $sql = $this->wpdb->prepare(
            "SELECT id, user_id, created_at, updated_at, message_count FROM {$this->table} WHERE user_id = %d LIMIT 1",
            $userId
        );

        $row = $this->normalize_row($this->wpdb->get_row($sql));
        if ($row === null) {
            return null;
        }

        return $this->hydrate_session($row);
    }

    /**
     * @return array<string, int|string>|null
     */
    public function get_by_id(int $sessionId): ?array
    {
        $this->assert_positive($sessionId, 'sessionId');

        $sql = $this->wpdb->prepare(
            "SELECT id, user_id, created_at, updated_at, message_count FROM {$this->table} WHERE id = %d LIMIT 1",
            $sessionId
        );

        $row = $this->normalize_row($this->wpdb->get_row($sql));
        if ($row === null) {
            return null;
        }

        return $this->hydrate_session($row);
    }

    /**
     * @return array<string, int|string>
     */
    public function get_or_create_by_user_id(int $userId): array
    {
        $this->assert_positive($userId, 'userId');

        $existing = $this->get_by_user_id($userId);
        if ($existing !== null) {
            return $existing;
        }

        try {
            $sessionId = $this->create_for_user($userId);
        } catch (RuntimeException $exception) {
            $raceWinner = $this->get_by_user_id($userId);
            if ($raceWinner !== null) {
                return $raceWinner;
            }

            throw $exception;
        }

        $created = $this->get_by_id($sessionId);
        if ($created === null) {
            throw new RuntimeException('Created session was not found after insert.');
        }

        return $created;
    }

    public function create_for_user(int $userId): int
    {
        $this->assert_positive($userId, 'userId');

        $timestamp = $this->current_time();
        $result = $this->wpdb->insert(
            $this->table,
            [
                'user_id' => $userId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'message_count' => 0,
            ],
            ['%d', '%s', '%s', '%d']
        );

        if ($result === false) {
            throw new RuntimeException('Could not create session: ' . $this->last_error());
        }

        $insertId = (int) ($this->wpdb->insert_id ?? 0);
        if ($insertId < 1) {
            throw new RuntimeException('Could not create session: missing insert id.');
        }

        return $insertId;
    }

    public function touch(int $sessionId): bool
    {
        $this->assert_positive($sessionId, 'sessionId');

        $result = $this->wpdb->update(
            $this->table,
            [
                'updated_at' => $this->current_time(),
            ],
            [
                'id' => $sessionId,
            ],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException('Could not update session timestamp: ' . $this->last_error());
        }

        return $result > 0;
    }

    public function increment_message_count(int $sessionId, int $delta = 1): bool
    {
        $this->assert_positive($sessionId, 'sessionId');
        $this->assert_positive($delta, 'delta');

        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table} SET message_count = message_count + %d, updated_at = %s WHERE id = %d",
            $delta,
            $this->current_time(),
            $sessionId
        );

        $result = $this->wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Could not increment message count: ' . $this->last_error());
        }

        return $result > 0;
    }

    public function delete_by_user_id(int $userId): int
    {
        $this->assert_positive($userId, 'userId');

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table} WHERE user_id = %d",
            $userId
        );

        $result = $this->wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Could not delete session: ' . $this->last_error());
        }

        return (int) $result;
    }

    private function resolve_default_table_name(object $wpdb): string
    {
        $prefix = (string) ($wpdb->prefix ?? '');
        if ($prefix === '') {
            throw new InvalidArgumentException('wpdb prefix is required.');
        }

        return $prefix . 'wpclaw_sessions';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalize_row(mixed $row): ?array
    {
        if ($row === null) {
            return null;
        }

        if (is_array($row)) {
            return $row;
        }

        if (is_object($row)) {
            return get_object_vars($row);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int|string>
     */
    private function hydrate_session(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'message_count' => (int) ($row['message_count'] ?? 0),
        ];
    }

    private function assert_positive(int $value, string $name): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException("{$name} must be greater than zero.");
        }
    }

    private function current_time(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function last_error(): string
    {
        $error = (string) ($this->wpdb->last_error ?? 'unknown error');

        return $error !== '' ? $error : 'unknown error';
    }
}
