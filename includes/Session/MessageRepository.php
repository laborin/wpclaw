<?php

declare(strict_types=1);

namespace WPNativeAgent\Session;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Repository for messages persisted in wpna_messages table.
 */
final class MessageRepository
{
    private const ALLOWED_ROLES = ['user', 'assistant', 'tool', 'system'];

    private object $wpdb;

    private string $table;

    public function __construct(object $wpdb, ?Schema $schema = null)
    {
        $this->wpdb = $wpdb;
        $this->table = $schema?->messages_table_name() ?? $this->resolve_default_table_name($wpdb);
    }

    /**
     * @param array<int, array<string, mixed>>|null $toolCalls
     * @param array<string, mixed>|null $iterationLog
     */
    public function add_message(
        int $sessionId,
        string $role,
        string $content,
        ?array $toolCalls = null,
        ?string $toolCallId = null,
        ?string $toolName = null,
        int $tokenEstimate = 0,
        ?array $iterationLog = null,
        ?string $createdAt = null
    ): int {
        $this->assert_positive($sessionId, 'sessionId');
        $this->assert_valid_role($role);

        if ($tokenEstimate < 0) {
            throw new InvalidArgumentException('tokenEstimate must be greater or equal to zero.');
        }

        $toolCallsJson = $toolCalls === null ? null : $this->encode_json($toolCalls, 'toolCalls');
        $iterationLogJson = $iterationLog === null ? null : $this->encode_json($iterationLog, 'iterationLog');

        $result = $this->wpdb->insert(
            $this->table,
            [
                'session_id' => $sessionId,
                'role' => $role,
                'content' => $content,
                'tool_calls' => $toolCallsJson,
                'tool_call_id' => $toolCallId,
                'tool_name' => $toolName,
                'token_estimate' => $tokenEstimate,
                'created_at' => $createdAt ?? $this->current_time(),
                'iteration_log' => $iterationLogJson,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            throw new RuntimeException('Could not insert message: ' . $this->last_error());
        }

        $insertId = (int) ($this->wpdb->insert_id ?? 0);
        if ($insertId < 1) {
            throw new RuntimeException('Could not insert message: missing insert id.');
        }

        return $insertId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_by_id(int $messageId): ?array
    {
        $this->assert_positive($messageId, 'messageId');

        $sql = $this->wpdb->prepare(
            "SELECT id, session_id, role, content, tool_calls, tool_call_id, tool_name, token_estimate, created_at, iteration_log FROM {$this->table} WHERE id = %d LIMIT 1",
            $messageId
        );

        $row = $this->normalize_row($this->wpdb->get_row($sql));
        if ($row === null) {
            return null;
        }

        return $this->hydrate_message($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_by_session_id(int $sessionId, int $limit = 100, int $offset = 0, bool $ascending = true): array
    {
        $this->assert_positive($sessionId, 'sessionId');

        if ($limit < 1) {
            throw new InvalidArgumentException('limit must be greater than zero.');
        }

        if ($offset < 0) {
            throw new InvalidArgumentException('offset must be zero or positive.');
        }

        $order = $ascending ? 'ASC' : 'DESC';
        $sql = $this->wpdb->prepare(
            "SELECT id, session_id, role, content, tool_calls, tool_call_id, tool_name, token_estimate, created_at, iteration_log FROM {$this->table} WHERE session_id = %d ORDER BY created_at {$order}, id {$order} LIMIT %d OFFSET %d",
            $sessionId,
            $limit,
            $offset
        );

        $rows = $this->wpdb->get_results($sql);
        if (! is_array($rows)) {
            return [];
        }

        $messages = [];
        foreach ($rows as $row) {
            $normalized = $this->normalize_row($row);
            if ($normalized === null) {
                continue;
            }

            $messages[] = $this->hydrate_message($normalized);
        }

        return $messages;
    }

    public function count_by_session_id(int $sessionId): int
    {
        $this->assert_positive($sessionId, 'sessionId');

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE session_id = %d",
            $sessionId
        );

        return (int) $this->wpdb->get_var($sql);
    }

    public function delete_by_session_id(int $sessionId): int
    {
        $this->assert_positive($sessionId, 'sessionId');

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table} WHERE session_id = %d",
            $sessionId
        );

        $result = $this->wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Could not delete messages by session: ' . $this->last_error());
        }

        return (int) $result;
    }

    public function delete_all(): int
    {
        $result = $this->wpdb->query("DELETE FROM {$this->table}");
        if ($result === false) {
            throw new RuntimeException('Could not delete all messages: ' . $this->last_error());
        }

        return (int) $result;
    }

    private function resolve_default_table_name(object $wpdb): string
    {
        $prefix = (string) ($wpdb->prefix ?? '');
        if ($prefix === '') {
            throw new InvalidArgumentException('wpdb prefix is required.');
        }

        return $prefix . 'wpna_messages';
    }

    private function assert_positive(int $value, string $name): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException("{$name} must be greater than zero.");
        }
    }

    private function assert_valid_role(string $role): void
    {
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException('Invalid message role.');
        }
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
     * @return array<string, mixed>
     */
    private function hydrate_message(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'session_id' => (int) ($row['session_id'] ?? 0),
            'role' => (string) ($row['role'] ?? ''),
            'content' => (string) ($row['content'] ?? ''),
            'tool_calls' => $this->decode_json_field($row['tool_calls'] ?? null),
            'tool_call_id' => $this->nullable_string($row['tool_call_id'] ?? null),
            'tool_name' => $this->nullable_string($row['tool_name'] ?? null),
            'token_estimate' => (int) ($row['token_estimate'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'iteration_log' => $this->decode_json_field($row['iteration_log'] ?? null),
        ];
    }

    private function nullable_string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;

        return $string !== '' ? $string : null;
    }

    private function decode_json_field(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    private function encode_json(array $payload, string $fieldName): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("{$fieldName} could not be encoded as json.", 0, $exception);
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
