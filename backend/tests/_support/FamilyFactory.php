<?php

declare(strict_types=1);

namespace tests\_support;

use app\components\TokenHasher;
use app\models\ChildProfile;
use app\models\ChildSession;
use app\models\Device;
use app\models\Family;
use app\models\Task;
use app\models\FamilyMember;
use app\models\User;

/**
 * Builds users/families directly in the DB for tests.
 */
final class FamilyFactory
{
    private static int $seq = 0;

    public static function user(?string $username = null, ?string $name = null): User
    {
        $user = new User([
            'username' => $username ?? 'user' . ++self::$seq . '_' . bin2hex(random_bytes(3)),
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->auth_key = User::generateAuthKey();
        $user->access_token = User::generateAccessToken();
        $user->save(false);
        return $user;
    }

    public static function family(string $name = 'Rossi'): Family
    {
        $family = new Family(['name' => $name]);
        $family->save(false);
        return $family;
    }

    public static function member(Family $family, ?User $user = null, string $role = FamilyMember::ROLE_ADMIN): FamilyMember
    {
        $member = new FamilyMember([
            'family_id' => $family->id,
            'user_id' => ($user ?? self::user())->id,
            'role' => $role,
        ]);
        $member->save(false);
        return $member;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function child(Family $family, string $name = 'Luca', array $attributes = []): ChildProfile
    {
        $child = new ChildProfile(array_merge([
            'name' => $name,
            'avatar' => 'fox',
            'color' => '#FF8A00',
        ], $attributes));
        $child->family_id = $family->id;
        $child->save(false);
        return $child;
    }

    /**
     * @param list<ChildProfile> $assignees
     * @param array<string, mixed> $attributes
     * @param list<string> $checklist
     */
    public static function task(Family $family, array $assignees, array $attributes = [], array $checklist = []): Task
    {
        $task = new Task(array_merge([
            'title' => 'Routine',
            'icon' => '🌅',
            'points' => 10,
            'xp' => 10,
            'requires_approval' => false,
            'recurrence_type' => Task::RECURRENCE_DAILY,
            'time_of_day' => 'morning',
            'start_date' => '2026-01-01',
            'is_active' => true,
        ], $attributes));
        $task->family_id = $family->id;
        $task->save(false);
        foreach ($assignees as $child) {
            Task::getDb()->createCommand()->insert('{{%task_assignee}}', ['task_id' => $task->id, 'child_id' => $child->id])->execute();
        }
        foreach ($checklist as $i => $label) {
            Task::getDb()->createCommand()->insert('{{%task_checklist_item}}', ['task_id' => $task->id, 'label' => $label, 'sort_order' => $i])->execute();
        }
        return $task;
    }

    /** Pairs a device and opens a session for $child: returns the child bearer token. */
    public static function childToken(ChildProfile $child): string
    {
        $device = new Device(['name' => 'Tablet']);
        $device->family_id = $child->family_id;
        $device->token_hash = TokenHasher::hash(TokenHasher::generate(TokenHasher::DEVICE_PREFIX));
        $device->save(false);

        $token = TokenHasher::generate(TokenHasher::CHILD_PREFIX);
        $session = new ChildSession();
        $session->family_id = $child->family_id;
        $session->device_id = $device->id;
        $session->child_id = $child->id;
        $session->token_hash = TokenHasher::hash($token);
        $session->save(false);
        return $token;
    }
}
