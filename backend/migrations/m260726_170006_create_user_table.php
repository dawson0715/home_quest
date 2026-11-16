<?php

use yii\db\Migration;

class m260726_170006_create_user_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(64)->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'access_token' => $this->string(64)->notNull(),
            'status' => $this->smallInteger()->notNull()->defaultValue(10),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('ux_user_username', '{{%user}}', 'username', true);
        $this->createIndex('ux_user_access_token', '{{%user}}', 'access_token', true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%user}}');
    }
}
