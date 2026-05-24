<?php
/**
 * functions.php — Shared business-logic helpers for Kea Buddy.
 *
 * Written to satisfy the failing unit tests in tests/SchedulePrivacyTest.php
 * (TDD GREEN phase). Functions are designed for dependency injection so they
 * can be tested without a real database connection.
 */

/**
 * Return true if $value is one of the three allowed visibility options.
 *
 * Valid values: 'public' | 'friends' | 'private'
 *
 * @param string $value The value to validate.
 */
function isValidVisibility(string $value): bool {
    return in_array($value, ['public', 'friends', 'private'], true);
}

/**
 * Determine whether a viewer may see a user's schedule.
 *
 * Rules:
 *   - The owner always sees their own schedule.
 *   - 'public'  → any viewer (including unauthenticated) may see it.
 *   - 'friends' → only viewers for whom $checkFriendship returns true.
 *   - 'private' → only the owner (all others are denied).
 *
 * The friendship check is injected as a callable so this function can be
 * unit-tested without a live database (pass a mock closure in tests;
 * pass a real DB-querying closure in production code).
 *
 * @param string   $visibility      'public' | 'friends' | 'private'
 * @param int|null $viewerId        ID of the viewing user (null = unauthenticated)
 * @param int      $ownerId         ID of the profile owner
 * @param callable $checkFriendship fn(?int $viewerId, int $ownerId): bool
 */
function canViewSchedule(
    string $visibility,
    ?int $viewerId,
    int $ownerId,
    callable $checkFriendship
): bool {
    // Owner always sees their own schedule
    if ($viewerId !== null && $viewerId === $ownerId) {
        return true;
    }

    switch ($visibility) {
        case 'public':
            return true;

        case 'friends':
            if ($viewerId === null) return false;
            return $checkFriendship($viewerId, $ownerId);

        case 'private':
        default:
            return false;
    }
}
