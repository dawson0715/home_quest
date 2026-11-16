<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Drops the legacy Chicco checklist (not family-scoped); shopping lists come in M6.
 */
class m261116_130000_drop_checklist_item_table extends Migration
{
    public function safeUp(): void
    {
        $this->dropTable('{{%checklist_item}}');
    }

    public function safeDown(): void
    {
        $this->createTable('{{%checklist_item}}', [
            'id' => $this->primaryKey(),
            'list' => $this->string(20)->notNull(),
            'text' => $this->string(255)->notNull(),
            'is_checked' => $this->boolean()->notNull()->defaultValue(false),
            'position' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ix_checklist_item_list', '{{%checklist_item}}', ['list', 'position']);
        $this->addCheck('ck_checklist_item_list', '{{%checklist_item}}', "[[list]] IN ('bag','shopping','todo')");
    }
}
