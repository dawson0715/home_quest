<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Rewards shop, redemptions, savings goals, badges (+ catalog) and the streak
 * bookkeeping column on child_profile.
 */
class m261116_160000_create_reward_and_badge_tables extends Migration
{
    private const OPTIONS = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

    /** [code, icon, rule] — name: frontend it.json `badges.<code>` */
    public const BADGES = [
        ['first_task', '🌱', ['type' => 'tasks_completed', 'threshold' => 1]],
        ['tasks_10', '⭐', ['type' => 'tasks_completed', 'threshold' => 10]],
        ['tasks_50', '🌟', ['type' => 'tasks_completed', 'threshold' => 50]],
        ['tasks_100', '💫', ['type' => 'tasks_completed', 'threshold' => 100]],
        ['tasks_250', '🏅', ['type' => 'tasks_completed', 'threshold' => 250]],
        ['streak_3', '🔥', ['type' => 'streak', 'threshold' => 3]],
        ['streak_7', '📅', ['type' => 'streak', 'threshold' => 7]],
        ['streak_14', '🚀', ['type' => 'streak', 'threshold' => 14]],
        ['streak_30', '👑', ['type' => 'streak', 'threshold' => 30]],
        ['level_5', '🎖️', ['type' => 'level', 'threshold' => 5]],
        ['level_10', '🏆', ['type' => 'level', 'threshold' => 10]],
        ['first_reward', '🎁', ['type' => 'rewards_redeemed', 'threshold' => 1]],
        ['rewards_10', '🛍️', ['type' => 'rewards_redeemed', 'threshold' => 10]],
        ['early_bird', '🐦', ['type' => 'time_of_day_completed', 'time_of_day' => 'morning', 'threshold' => 20]],
        ['night_owl', '🦉', ['type' => 'time_of_day_completed', 'time_of_day' => 'evening', 'threshold' => 20]],
        ['chef_helper', '👩‍🍳', ['type' => 'meal_tasks_completed', 'threshold' => 1]],
    ];

    public function safeUp(): void
    {
        $this->addColumn('{{%child_profile}}', 'streak_date', (string) $this->date()->null()->after('best_streak'));

        $this->createTable('{{%reward}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'title' => $this->string(120)->notNull(),
            'icon' => $this->string(40)->notNull(),
            'cost' => $this->smallInteger()->unsigned()->notNull(),
            'limit_per_period' => $this->smallInteger()->unsigned()->null(),
            'limit_period' => "ENUM('day','week','month') NULL",
            'stock' => $this->smallInteger()->unsigned()->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->addForeignKey('fk_reward_family', '{{%reward}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%reward_redemption}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'reward_id' => $this->integer()->unsigned()->notNull(),
            'child_id' => $this->integer()->unsigned()->notNull(),
            'cost_snapshot' => $this->smallInteger()->unsigned()->notNull(),
            'status' => "ENUM('requested','approved','rejected','fulfilled') NOT NULL DEFAULT 'requested'",
            'requested_at' => $this->dateTime()->notNull(),
            'reviewed_at' => $this->dateTime()->null(),
            'reviewed_by' => $this->integer()->null(),
            'note' => $this->string(255)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ix_reward_redemption_family_status', '{{%reward_redemption}}', ['family_id', 'status']);
        $this->createIndex('ix_reward_redemption_reward_child', '{{%reward_redemption}}', ['reward_id', 'child_id', 'requested_at']);
        $this->addForeignKey('fk_reward_redemption_family', '{{%reward_redemption}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_reward_redemption_reward', '{{%reward_redemption}}', 'reward_id', '{{%reward}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_reward_redemption_child', '{{%reward_redemption}}', 'child_id', '{{%child_profile}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_reward_redemption_reviewed_by', '{{%reward_redemption}}', 'reviewed_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%savings_goal}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'child_id' => $this->integer()->unsigned()->notNull()->unique(),
            'reward_id' => $this->integer()->unsigned()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->addForeignKey('fk_savings_goal_family', '{{%savings_goal}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_savings_goal_child', '{{%savings_goal}}', 'child_id', '{{%child_profile}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_savings_goal_reward', '{{%savings_goal}}', 'reward_id', '{{%reward}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%badge}}', [
            'id' => $this->primaryKey()->unsigned(),
            'code' => $this->string(40)->notNull()->unique(),
            'icon' => $this->string(40)->notNull(),
            'rule' => $this->json()->notNull(),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
        ], self::OPTIONS);

        $this->createTable('{{%child_badge}}', [
            'child_id' => $this->integer()->unsigned()->notNull(),
            'badge_id' => $this->integer()->unsigned()->notNull(),
            'earned_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY (child_id, badge_id)',
        ], self::OPTIONS);
        $this->addForeignKey('fk_child_badge_child', '{{%child_badge}}', 'child_id', '{{%child_profile}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_child_badge_badge', '{{%child_badge}}', 'badge_id', '{{%badge}}', 'id', 'CASCADE', 'CASCADE');

        $rows = [];
        foreach (self::BADGES as $i => [$code, $icon, $rule]) {
            $rows[] = [$code, $icon, $rule, $i];
        }
        $this->batchInsert('{{%badge}}', ['code', 'icon', 'rule', 'sort_order'], $rows);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%child_badge}}');
        $this->dropTable('{{%badge}}');
        $this->dropTable('{{%savings_goal}}');
        $this->dropTable('{{%reward_redemption}}');
        $this->dropTable('{{%reward}}');
        $this->dropColumn('{{%child_profile}}', 'streak_date');
    }
}
