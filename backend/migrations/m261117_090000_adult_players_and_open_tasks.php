<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Parents play too: a child_profile row with user_id is the game profile of an
 * adult member. Open tasks (is_open): first player who completes them wins,
 * the other players' occurrences become `taken`.
 */
class m261117_090000_adult_players_and_open_tasks extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%child_profile}}', 'user_id', (string) $this->integer()->null()->after('family_id'));
        $this->createIndex('ux_child_profile_user', '{{%child_profile}}', 'user_id', true);
        $this->addForeignKey('fk_child_profile_user', '{{%child_profile}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');

        $this->addColumn('{{%task}}', 'is_open', (string) $this->boolean()->notNull()->defaultValue(false)->after('requires_approval'));
        $this->alterColumn('{{%task_occurrence}}', 'status', "ENUM('pending','submitted','approved','rejected','missed','taken') NOT NULL DEFAULT 'pending'");

        // existing parents get their game profile
        $now = time();
        $members = (new \yii\db\Query())->select(['m.family_id', 'm.user_id', 'u.name', 'u.username'])
            ->from('{{%family_member}} m')->innerJoin('{{%user}} u', 'u.id = m.user_id')->all($this->db);
        foreach ($members as $i => $m) {
            $this->insert('{{%child_profile}}', [
                'family_id' => $m['family_id'],
                'user_id' => $m['user_id'],
                'name' => mb_substr((string) ($m['name'] ?: $m['username']), 0, 40),
                'avatar' => 'owl',
                'color' => '#6B6B1F',
                'sort_order' => 1000 + $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->update('{{%task_occurrence}}', ['status' => 'missed'], ['status' => 'taken']);
        $this->alterColumn('{{%task_occurrence}}', 'status', "ENUM('pending','submitted','approved','rejected','missed') NOT NULL DEFAULT 'pending'");
        $this->dropColumn('{{%task}}', 'is_open');
        $this->delete('{{%child_profile}}', ['not', ['user_id' => null]]);
        $this->dropForeignKey('fk_child_profile_user', '{{%child_profile}}');
        $this->dropIndex('ux_child_profile_user', '{{%child_profile}}');
        $this->dropColumn('{{%child_profile}}', 'user_id');
    }
}
