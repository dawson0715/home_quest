<?php

use yii\db\Migration;

class m260730_140000_create_checklist_item_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%checklist_item}}', [
            'id' => $this->primaryKey(),
            'list' => $this->string(20)->notNull(),
            'text' => $this->string(255)->notNull(),
            'is_checked' => $this->boolean()->notNull()->defaultValue(false),
            'position' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('ix_checklist_item_list', '{{%checklist_item}}', ['list', 'position']);

        $this->addCheck('ck_checklist_item_list', '{{%checklist_item}}', "[[list]] IN ('bag','shopping','todo')");
    }

    public function safeDown()
    {
        $this->dropTable('{{%checklist_item}}');
    }
}
