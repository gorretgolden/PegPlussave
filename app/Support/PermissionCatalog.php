<?php

namespace App\Support;

/**
 * Who may open which desk and which button. The list is the reviewed prototype's own gates written
 * down: a page is one ability, a written action is another, and nothing is allowed by accident.
 *
 * The four "own" abilities are different. Everyone holds them, including the committee, because a
 * Chairperson is first a member with savings of their own; what decides whether they see anything is
 * whether their account is tied to a member, which the views ask directly.
 */
class PermissionCatalog
{
    public const ABILITIES = [
        'view-dashboard',
        'view-own-savings',
        'view-own-loans',
        'make-member-entry',
        'view-calendar',
        'view-directory',
        'view-notices',
        'view-minutes',
        'view-approvals',
        'check-as-chair',
        'check-as-patron',
        'view-treasury',
        'view-reports',
        'view-ledger',
        'make-treasury-entry',
        'view-mobilisation',
        'view-audit',
        'add-event',
        'post-notice',
        'record-minutes',
        'register-member',
        'edit-member',
        'flag-exit',
        'add-prospect',
        'manage-system',
    ];

    /**
     * The desks a member and the System Owner both open: what the group does, not what it holds.
     */
    public const SHARED = [
        'view-dashboard', 'view-calendar', 'view-directory', 'view-notices', 'view-minutes',
    ];

    /**
     * What every signed-in member sees, whatever their committee role.
     */
    public const EVERYONE = [
        ...self::SHARED,
        'view-own-savings', 'view-own-loans', 'make-member-entry',
    ];

    public const ROLES = [
        'member' => [],

        'treasurer' => [
            'view-treasury', 'view-reports', 'view-ledger', 'make-treasury-entry',
        ],

        'chair' => [
            'view-approvals', 'check-as-chair',
            'view-treasury', 'view-reports', 'view-ledger',
            'view-mobilisation', 'view-audit',
            'add-event', 'post-notice', 'record-minutes', 'add-prospect',
        ],

        'secretary' => [
            'view-mobilisation',
            'add-event', 'post-notice', 'record-minutes', 'register-member', 'edit-member', 'flag-exit',
        ],

        'spokesman' => [
            'add-event', 'post-notice',
        ],

        'mobiliser' => [
            'view-mobilisation', 'register-member', 'add-prospect',
        ],

        'patron' => [
            'view-approvals', 'check-as-patron',
            'view-treasury', 'view-reports', 'view-ledger', 'view-audit',
        ],

        'accountant' => [
            'view-treasury', 'view-reports', 'view-ledger', 'view-audit',
        ],

        'admin' => [
            'register-member', 'edit-member', 'view-audit', 'manage-system',
        ],
    ];

    public const ROLE_LABELS = [
        'member' => 'Member',
        'chair' => 'Chairperson',
        'treasurer' => 'Treasurer',
        'secretary' => 'Secretary',
        'spokesman' => 'Spokesman',
        'mobiliser' => 'Mobiliser',
        'patron' => 'Patron',
        'accountant' => 'Accountant',
        'admin' => 'System Owner',
    ];

    /**
     * Committee roles a member can be given. The System Owner is not on the list: that account is
     * not a member of the group at all.
     */
    public const COMMITTEE = ['chair', 'treasurer', 'secretary', 'spokesman', 'mobiliser', 'patron', 'accountant'];

    /**
     * @return array<int, string>
     */
    public static function abilitiesFor(string $role): array
    {
        if ($role === 'admin') {
            // The System Owner reads the group's notices, minutes and register, because that is where
            // their own register-member and edit-member abilities are used. What they hold is the
            // shared set, never the money desks.
            return array_values(array_unique(array_merge(self::SHARED, self::ROLES['admin'])));
        }

        return array_values(array_unique(array_merge(self::EVERYONE, self::ROLES[$role] ?? [])));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function matrix(): array
    {
        return array_map(fn (string $role) => self::abilitiesFor($role), array_keys(self::ROLES));
    }

    public static function label(?string $role): string
    {
        return self::ROLE_LABELS[$role ?? 'member'] ?? 'Member';
    }
}
