<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Shopping lists, items, pantry exclusions, favorites and the per-family
 * department order (shopping_category itself was created in M5).
 */
class m261116_180000_create_shopping_tables extends Migration
{
    private const OPTIONS = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

    public function safeUp(): void
    {
        $this->createTable('{{%shopping_list}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(60)->notNull(),
            'is_default' => $this->boolean()->notNull()->defaultValue(false),
            'linked_week_id' => $this->integer()->unsigned()->null(),
            'version' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->addForeignKey('fk_shopping_list_family', '{{%shopping_list}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_shopping_list_week', '{{%shopping_list}}', 'linked_week_id', '{{%meal_plan_week}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%shopping_item}}', [
            'id' => $this->primaryKey()->unsigned(),
            'list_id' => $this->integer()->unsigned()->notNull(),
            'ingredient_id' => $this->integer()->unsigned()->null(),
            'label' => $this->string(120)->notNull(),
            'quantity' => $this->decimal(10, 2)->null(),
            'unit' => $this->string(12)->null(),
            'category_id' => $this->integer()->unsigned()->notNull(),
            'source' => "ENUM('manual','generated') NOT NULL DEFAULT 'manual'",
            'source_week_id' => $this->integer()->unsigned()->null(),
            'checked_at' => $this->dateTime()->null(),
            'checked_by_type' => "ENUM('user','child') NULL",
            'checked_by_id' => $this->integer()->null(),
            'client_uuid' => $this->char(36)->null(),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_shopping_item_client_uuid', '{{%shopping_item}}', 'client_uuid', true);
        $this->createIndex('ix_shopping_item_list', '{{%shopping_item}}', ['list_id', 'checked_at']);
        $this->addForeignKey('fk_shopping_item_list', '{{%shopping_item}}', 'list_id', '{{%shopping_list}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_shopping_item_ingredient', '{{%shopping_item}}', 'ingredient_id', '{{%ingredient}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_shopping_item_category', '{{%shopping_item}}', 'category_id', '{{%shopping_category}}', 'id', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('fk_shopping_item_week', '{{%shopping_item}}', 'source_week_id', '{{%meal_plan_week}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%pantry_exclusion}}', [
            'list_id' => $this->integer()->unsigned()->notNull(),
            'ingredient_id' => $this->integer()->unsigned()->notNull(),
            'PRIMARY KEY (list_id, ingredient_id)',
        ], self::OPTIONS);
        $this->addForeignKey('fk_pantry_exclusion_list', '{{%pantry_exclusion}}', 'list_id', '{{%shopping_list}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_pantry_exclusion_ingredient', '{{%pantry_exclusion}}', 'ingredient_id', '{{%ingredient}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%favorite_item}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'label' => $this->string(120)->notNull(),
            'ingredient_id' => $this->integer()->unsigned()->null(),
            'category_id' => $this->integer()->unsigned()->notNull(),
            'use_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
        ], self::OPTIONS);
        $this->createIndex('ux_favorite_item_family_label', '{{%favorite_item}}', ['family_id', 'label'], true);
        $this->addForeignKey('fk_favorite_item_family', '{{%favorite_item}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_favorite_item_ingredient', '{{%favorite_item}}', 'ingredient_id', '{{%ingredient}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_favorite_item_category', '{{%favorite_item}}', 'category_id', '{{%shopping_category}}', 'id', 'CASCADE', 'CASCADE');

        // the family's own department order (PUT /shopping-categories/order)
        $this->createTable('{{%family_category_order}}', [
            'family_id' => $this->integer()->unsigned()->notNull(),
            'category_id' => $this->integer()->unsigned()->notNull(),
            'sort_order' => $this->smallInteger()->notNull(),
            'PRIMARY KEY (family_id, category_id)',
        ], self::OPTIONS);
        $this->addForeignKey('fk_family_category_order_family', '{{%family_category_order}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_family_category_order_category', '{{%family_category_order}}', 'category_id', '{{%shopping_category}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%family_category_order}}');
        $this->dropTable('{{%favorite_item}}');
        $this->dropTable('{{%pantry_exclusion}}');
        $this->dropTable('{{%shopping_item}}');
        $this->dropTable('{{%shopping_list}}');
    }
}
