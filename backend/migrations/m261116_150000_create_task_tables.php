<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Tasks/routines, their daily occurrences and the immutable points ledger
 * (point_transaction is pulled forward from M4: approvals award points in M3).
 */
class m261116_150000_create_task_tables extends Migration
{
    private const OPTIONS = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

    public function safeUp(): void
    {
        $this->createTable('{{%task}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'title' => $this->string(120)->notNull(),
            'description' => $this->text()->null(),
            'icon' => $this->string(40)->notNull(),
            'points' => $this->smallInteger()->unsigned()->notNull(),
            'xp' => $this->smallInteger()->unsigned()->notNull(),
            'requires_approval' => $this->boolean()->notNull()->defaultValue(true),
            'recurrence_type' => "ENUM('none','daily','weekly','interval') NOT NULL",
            'recurrence_days' => $this->tinyInteger()->unsigned()->null(),
            'interval_days' => $this->smallInteger()->unsigned()->null(),
            'time_of_day' => "ENUM('morning','afternoon','evening','any') NOT NULL DEFAULT 'any'",
            'due_date' => $this->date()->null(),
            'start_date' => $this->date()->notNull(),
            'end_date' => $this->date()->null(),
            // FK to meal_slot added in M5.7
            'linked_meal_slot_id' => $this->integer()->unsigned()->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ix_task_family_active', '{{%task}}', ['family_id', 'is_active']);
        $this->addForeignKey('fk_task_family', '{{%task}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_task_created_by', '{{%task}}', 'created_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%task_checklist_item}}', [
            'id' => $this->primaryKey()->unsigned(),
            'task_id' => $this->integer()->unsigned()->notNull(),
            'label' => $this->string(120)->notNull(),
            'icon' => $this->string(40)->null(),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
        ], self::OPTIONS);
        $this->addForeignKey('fk_task_checklist_item_task', '{{%task_checklist_item}}', 'task_id', '{{%task}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%task_assignee}}', [
            'task_id' => $this->integer()->unsigned()->notNull(),
            'child_id' => $this->integer()->unsigned()->notNull(),
            'PRIMARY KEY (task_id, child_id)',
        ], self::OPTIONS);
        $this->addForeignKey('fk_task_assignee_task', '{{%task_assignee}}', 'task_id', '{{%task}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_task_assignee_child', '{{%task_assignee}}', 'child_id', '{{%child_profile}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%task_occurrence}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'task_id' => $this->integer()->unsigned()->notNull(),
            'child_id' => $this->integer()->unsigned()->notNull(),
            'due_date' => $this->date()->notNull(),
            'status' => "ENUM('pending','submitted','approved','rejected','missed') NOT NULL DEFAULT 'pending'",
            'checklist_state' => $this->json()->null(),
            'submitted_at' => $this->dateTime()->null(),
            'reviewed_at' => $this->dateTime()->null(),
            'reviewed_by' => $this->integer()->null(),
            'review_note' => $this->string(255)->null(),
            'points_awarded' => $this->smallInteger()->unsigned()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_task_occurrence_task_child_date', '{{%task_occurrence}}', ['task_id', 'child_id', 'due_date'], true);
        $this->createIndex('ix_task_occurrence_family_date_status', '{{%task_occurrence}}', ['family_id', 'due_date', 'status']);
        $this->createIndex('ix_task_occurrence_child_date', '{{%task_occurrence}}', ['child_id', 'due_date']);
        $this->addForeignKey('fk_task_occurrence_family', '{{%task_occurrence}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_task_occurrence_task', '{{%task_occurrence}}', 'task_id', '{{%task}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_task_occurrence_child', '{{%task_occurrence}}', 'child_id', '{{%child_profile}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_task_occurrence_reviewed_by', '{{%task_occurrence}}', 'reviewed_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%point_transaction}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'child_id' => $this->integer()->unsigned()->notNull(),
            'points' => $this->integer()->notNull(),
            'xp' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'type' => "ENUM('task','bonus','penalty','reward','streak_bonus','badge','adjustment') NOT NULL",
            'source_type' => $this->string(30)->null(),
            'source_id' => $this->integer()->unsigned()->null(),
            'note' => $this->string(255)->null(),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ix_point_transaction_child_created', '{{%point_transaction}}', ['child_id', 'created_at']);
        $this->addForeignKey('fk_point_transaction_family', '{{%point_transaction}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_point_transaction_child', '{{%point_transaction}}', 'child_id', '{{%child_profile}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_point_transaction_created_by', '{{%point_transaction}}', 'created_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%task_template}}', [
            'id' => $this->primaryKey()->unsigned(),
            'title_key' => $this->string(60)->notNull()->unique(),
            'icon' => $this->string(40)->notNull(),
            'min_age' => $this->tinyInteger()->unsigned()->notNull(),
            'max_age' => $this->tinyInteger()->unsigned()->notNull(),
            'default_points' => $this->smallInteger()->unsigned()->notNull(),
            'category' => $this->string(30)->notNull(),
            'time_of_day' => "ENUM('morning','afternoon','evening','any') NOT NULL DEFAULT 'any'",
            'recurrence_type' => "ENUM('none','daily','weekly','interval') NOT NULL DEFAULT 'daily'",
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
        ], self::OPTIONS);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%task_template}}');
        $this->dropTable('{{%point_transaction}}');
        $this->dropTable('{{%task_occurrence}}');
        $this->dropTable('{{%task_assignee}}');
        $this->dropTable('{{%task_checklist_item}}');
        $this->dropTable('{{%task}}');
    }
}
