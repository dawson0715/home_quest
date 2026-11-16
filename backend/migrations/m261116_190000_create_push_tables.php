<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Web push subscriptions, notification dedup log and optional parent email
 * (weekly summary, M7.2).
 */
class m261116_190000_create_push_tables extends Migration
{
    private const OPTIONS = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

    public function safeUp(): void
    {
        $this->createTable('{{%push_subscription}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'user_id' => $this->integer()->null(),
            'child_id' => $this->integer()->unsigned()->null(),
            'endpoint' => $this->text()->notNull(),
            'endpoint_hash' => $this->char(64)->notNull(),
            'p256dh' => $this->string(255)->notNull(),
            'auth' => $this->string(255)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_push_subscription_endpoint', '{{%push_subscription}}', 'endpoint_hash', true);
        $this->addForeignKey('fk_push_subscription_family', '{{%push_subscription}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_push_subscription_user', '{{%push_subscription}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_push_subscription_child', '{{%push_subscription}}', 'child_id', '{{%child_profile}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%notification_log}}', [
            'id' => $this->primaryKey()->unsigned(),
            'kind' => $this->string(40)->notNull(),
            'target' => $this->string(60)->notNull(),
            'day' => $this->date()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_notification_log', '{{%notification_log}}', ['kind', 'target', 'day'], true);

        $this->addColumn('{{%user}}', 'email', (string) $this->string(190)->null()->after('name'));
        $this->createIndex('ux_user_email', '{{%user}}', 'email', true);
    }

    public function safeDown(): void
    {
        $this->dropIndex('ux_user_email', '{{%user}}');
        $this->dropColumn('{{%user}}', 'email');
        $this->dropTable('{{%notification_log}}');
        $this->dropTable('{{%push_subscription}}');
    }
}
