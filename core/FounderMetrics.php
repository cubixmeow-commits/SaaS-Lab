<?php

declare(strict_types=1);

final class FounderMetrics
{
    /**
     * @return array{members: int, active_projects: int, events_7d: int}
     */
    public static function summary(): array
    {
        $db = platform_db();
        $members = (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM users WHERE role = 'member'")['c'] ?? 0);
        $activeProjects = (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM projects WHERE access_mode = 'lab'")['c'] ?? 0);
        $since = self::sevenDaysAgoUtc();
        $events7d = (int) ($db->fetchOne(
            'SELECT COUNT(*) AS c FROM project_events WHERE occurred_at >= :since',
            ['since' => $since]
        )['c'] ?? 0);

        return [
            'members' => $members,
            'active_projects' => $activeProjects,
            'events_7d' => $events7d,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function projectRows(): array
    {
        $projects = platform_db()->fetchAll(
            'SELECT * FROM projects ORDER BY created_at DESC'
        );
        $since = self::sevenDaysAgoUtc();
        $rows = [];

        foreach ($projects as $project) {
            $projectId = (int) $project['id'];
            $coreAction = (string) $project['core_action_name'];

            $users = (int) (platform_db()->fetchOne(
                'SELECT COUNT(DISTINCT user_id) AS c
                 FROM project_events
                 WHERE project_id = :project_id
                   AND user_id IS NOT NULL',
                ['project_id' => $projectId]
            )['c'] ?? 0);

            $active7d = (int) (platform_db()->fetchOne(
                'SELECT COUNT(DISTINCT user_id) AS c
                 FROM project_events
                 WHERE project_id = :project_id
                   AND user_id IS NOT NULL
                   AND occurred_at >= :since',
                ['project_id' => $projectId, 'since' => $since]
            )['c'] ?? 0);

            $coreActions7d = (int) (platform_db()->fetchOne(
                'SELECT COUNT(*) AS c
                 FROM project_events
                 WHERE project_id = :project_id
                   AND event_name = :event_name
                   AND occurred_at >= :since',
                [
                    'project_id' => $projectId,
                    'event_name' => $coreAction,
                    'since' => $since,
                ]
            )['c'] ?? 0);

            $last = platform_db()->fetchOne(
                'SELECT MAX(occurred_at) AS last_activity
                 FROM project_events
                 WHERE project_id = :project_id',
                ['project_id' => $projectId]
            );

            $rows[] = [
                'project' => $project,
                'users' => $users,
                'active_7d' => $active7d,
                'core_actions_7d' => $coreActions7d,
                'last_activity' => $last['last_activity'] ?? null,
            ];
        }

        return $rows;
    }

    private static function sevenDaysAgoUtc(): string
    {
        return gmdate('c', time() - (7 * 24 * 60 * 60));
    }
}
