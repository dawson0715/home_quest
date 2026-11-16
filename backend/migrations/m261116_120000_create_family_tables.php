<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Family (tenant) and its adult members; adds the display name to user.
 * A user belongs to exactly one family (UNIQUE user_id): the current family
 * is derived from the authenticated user.
 */
class m261116_120000_create_family_tables extends Migration
{
    private const OPTIONS = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

    public function safeUp(): void
    {
        $this->addColumn('{{%user}}', 'name', (string) $this->string(80)->null()->after('username'));

        $this->createTable('{{%family}}', [
            'id' => $this->primaryKey()->unsigned(),
            'name' => $this->string(80)->notNull(),
            'settings' => $this->json()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);

        $this->createTable('{{%family_member}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            // user.id is a signed INT (legacy primaryKey())
            'user_id' => $this->integer()->notNull(),
            'role' => "ENUM('admin','coparent') NOT NULL",
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);

        $this->createIndex('ux_family_member_user', '{{%family_member}}', 'user_id', true);
        $this->createIndex('ix_family_member_family', '{{%family_member}}', 'family_id');
        $this->addForeignKey('fk_family_member_family', '{{%family_member}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_family_member_user', '{{%family_member}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%family_member}}');
        $this->dropTable('{{%family}}');
        $this->dropColumn('{{%user}}', 'name');
    }
}
