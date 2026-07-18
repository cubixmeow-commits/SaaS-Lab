<?php

declare(strict_types=1);

final class EventLogger
{
    private const MAX_PAYLOAD_BYTES = 16384;

    public static function log(string $eventName, array $data = []): void
    {
        try {
            if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $eventName)) {
                lab_log('warning', 'Rejected invalid event name.', ['event_name' => $eventName]);
                return;
            }

            if (!ProjectContext::has()) {
                lab_log('warning', 'Event emitted without project context.', ['event_name' => $eventName]);
                return;
            }

            $project = project();
            $payload = self::encodePayload($data);

            platform_db()->run(
                'INSERT INTO project_events
                    (project_id, user_id, session_id, event_name, event_data, project_version, occurred_at)
                 VALUES
                    (:project_id, :user_id, :session_id, :event_name, :event_data, :project_version, :occurred_at)',
                [
                    'project_id' => $project->id(),
                    'user_id' => auth()->id(),
                    'session_id' => auth()->visitToken(),
                    'event_name' => $eventName,
                    'event_data' => $payload,
                    'project_version' => $project->version(),
                    'occurred_at' => utc_now(),
                ]
            );
        } catch (Throwable $exception) {
            lab_log('error', 'Event logging failed.', [
                'event_name' => $eventName,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Emit project_opened at most once per authenticated visit token per project.
     */
    public static function maybeProjectOpened(): void
    {
        if (!auth()->check() || !ProjectContext::has()) {
            return;
        }

        $slug = project()->slug();
        if (!auth()->markProjectOpened($slug)) {
            return;
        }

        self::log('project_opened', [
            'slug' => $slug,
        ]);
    }

    private static function encodePayload(array $data): string
    {
        try {
            $encoded = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            lab_log('warning', 'Event payload JSON encoding failed.', [
                'error' => $exception->getMessage(),
            ]);

            return json_encode([
                '_payload_omitted' => true,
                '_reason' => 'json_encoding_failed',
            ], JSON_UNESCAPED_SLASHES) ?: '{"_payload_omitted":true}';
        }

        if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            lab_log('warning', 'Event payload exceeded 16KB and was omitted.', [
                'bytes' => strlen($encoded),
            ]);

            return json_encode([
                '_payload_omitted' => true,
                '_reason' => 'payload_exceeded_16kb',
            ], JSON_UNESCAPED_SLASHES) ?: '{"_payload_omitted":true}';
        }

        return $encoded;
    }
}
