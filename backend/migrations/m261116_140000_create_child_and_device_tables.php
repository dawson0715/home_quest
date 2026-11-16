<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Children profiles and the device pairing / child session model
 * (docs/02-architettura.md §5, docs/03-database.md §1).
 */
class m261116_140000_create_child_and_device_tables extends Migration
{
    private const OPTIONS = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

    public function safeUp(): void
    {
        $this->createTable('{{%child_profile}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(40)->notNull(),
            'avatar' => $this->string(40)->notNull(),
            'color' => $this->char(7)->notNull(),
            'birth_year_month' => $this->char(7)->null(),
            'pin_hash' => $this->string(255)->null(),
            'points_balance' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'xp_total' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'level' => $this->smallInteger()->unsigned()->notNull()->defaultValue(1),
            'current_streak' => $this->smallInteger()->unsigned()->notNull()->defaultValue(0),
            'best_streak' => $this->smallInteger()->unsigned()->notNull()->defaultValue(0),
            'can_check_shopping' => $this->boolean()->notNull()->defaultValue(false),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
            'archived_at' => $this->dateTime()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ix_child_profile_family', '{{%child_profile}}', ['family_id', 'archived_at', 'sort_order']);
        $this->addForeignKey('fk_child_profile_family', '{{%child_profile}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%device}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(60)->notNull(),
            'token_hash' => $this->char(64)->notNull(),
            'last_seen_at' => $this->dateTime()->null(),
            'revoked_at' => $this->dateTime()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_device_token_hash', '{{%device}}', 'token_hash', true);
        $this->createIndex('ix_device_family', '{{%device}}', 'family_id');
        $this->addForeignKey('fk_device_family', '{{%device}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%pairing_code}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'code' => $this->char(6)->notNull(),
            'expires_at' => $this->dateTime()->notNull(),
            'used_at' => $this->dateTime()->null(),
            'created_by' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_pairing_code_code', '{{%pairing_code}}', 'code', true);
        $this->addForeignKey('fk_pairing_code_family', '{{%pairing_code}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_pairing_code_user', '{{%pairing_code}}', 'created_by', '{{%user}}', 'id', 'CASCADE', 'CASCADE');

        // A child "logged in" on a paired device after choosing the profile (+ PIN)
        $this->createTable('{{%child_session}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'device_id' => $this->integer()->unsigned()->notNull(),
            'child_id' => $this->integer()->unsigned()->notNull(),
            'token_hash' => $this->char(64)->notNull(),
            'last_seen_at' => $this->dateTime()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_child_session_token_hash', '{{%child_session}}', 'token_hash', true);
        $this->addForeignKey('fk_child_session_family', '{{%child_session}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_child_session_device', '{{%child_session}}', 'device_id', '{{%device}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_child_session_child', '{{%child_session}}', 'child_id', '{{%child_profile}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%child_session}}');
        $this->dropTable('{{%pairing_code}}');
        $this->dropTable('{{%device}}');
        $this->dropTable('{{%child_profile}}');
    }
}
