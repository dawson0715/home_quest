<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Recipes, ingredient catalog, weekly meal plans. shopping_category is pulled
 * forward from M6 because ingredients belong to a store department.
 */
class m261116_170000_create_recipe_and_meal_tables extends Migration
{
    private const OPTIONS = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
    private const UNITS = "ENUM('g','kg','ml','l','pz','cucchiaio','cucchiaino','qb')";

    /** [code (i18n shopping_categories.<code>), icon] in default order */
    public const CATEGORIES = [
        ['frutta_verdura', '🥦'], ['pane_forno', '🥖'], ['latticini', '🧀'], ['carne_pesce', '🍗'],
        ['dispensa', '🥫'], ['surgelati', '🧊'], ['bevande', '🧃'], ['casa_igiene', '🧴'], ['altro', '🛒'],
    ];

    /** Global ingredient catalog (Italian data): [name, default unit, category code] */
    public const INGREDIENTS = [
        // frutta e verdura
        ['Mele', 'pz', 'frutta_verdura'], ['Pere', 'pz', 'frutta_verdura'], ['Banane', 'pz', 'frutta_verdura'],
        ['Arance', 'pz', 'frutta_verdura'], ['Mandarini', 'pz', 'frutta_verdura'], ['Limoni', 'pz', 'frutta_verdura'],
        ['Fragole', 'g', 'frutta_verdura'], ['Uva', 'g', 'frutta_verdura'], ['Kiwi', 'pz', 'frutta_verdura'],
        ['Pesche', 'pz', 'frutta_verdura'], ['Albicocche', 'pz', 'frutta_verdura'], ['Ananas', 'pz', 'frutta_verdura'],
        ['Mirtilli', 'g', 'frutta_verdura'], ['Melone', 'pz', 'frutta_verdura'], ['Anguria', 'pz', 'frutta_verdura'],
        ['Pomodori', 'g', 'frutta_verdura'], ['Pomodorini', 'g', 'frutta_verdura'], ['Patate', 'g', 'frutta_verdura'],
        ['Carote', 'g', 'frutta_verdura'], ['Cipolle', 'pz', 'frutta_verdura'], ['Cipolla rossa', 'pz', 'frutta_verdura'],
        ['Aglio', 'pz', 'frutta_verdura'], ['Zucchine', 'g', 'frutta_verdura'], ['Melanzane', 'g', 'frutta_verdura'],
        ['Peperoni', 'pz', 'frutta_verdura'], ['Insalata', 'pz', 'frutta_verdura'], ['Rucola', 'g', 'frutta_verdura'],
        ['Spinaci', 'g', 'frutta_verdura'], ['Broccoli', 'g', 'frutta_verdura'], ['Cavolfiore', 'pz', 'frutta_verdura'],
        ['Cavolo', 'pz', 'frutta_verdura'], ['Finocchi', 'pz', 'frutta_verdura'], ['Sedano', 'pz', 'frutta_verdura'],
        ['Zucca', 'g', 'frutta_verdura'], ['Funghi champignon', 'g', 'frutta_verdura'], ['Fagiolini', 'g', 'frutta_verdura'],
        ['Piselli freschi', 'g', 'frutta_verdura'], ['Asparagi', 'g', 'frutta_verdura'], ['Carciofi', 'pz', 'frutta_verdura'],
        ['Cetrioli', 'pz', 'frutta_verdura'], ['Porri', 'pz', 'frutta_verdura'], ['Basilico', 'qb', 'frutta_verdura'],
        ['Prezzemolo', 'qb', 'frutta_verdura'], ['Rosmarino', 'qb', 'frutta_verdura'], ['Salvia', 'qb', 'frutta_verdura'],
        ['Avocado', 'pz', 'frutta_verdura'], ['Mais dolce', 'g', 'dispensa'],
        // pane e forno
        ['Pane', 'g', 'pane_forno'], ['Pane in cassetta', 'pz', 'pane_forno'], ['Pane integrale', 'g', 'pane_forno'],
        ['Piadine', 'pz', 'pane_forno'], ['Grissini', 'pz', 'pane_forno'], ['Fette biscottate', 'pz', 'pane_forno'],
        ['Pangrattato', 'g', 'pane_forno'], ['Pasta sfoglia', 'pz', 'pane_forno'], ['Pasta brisée', 'pz', 'pane_forno'],
        ['Base per pizza', 'pz', 'pane_forno'], ['Cornetti', 'pz', 'pane_forno'],
        // latticini e uova
        ['Latte', 'ml', 'latticini'], ['Latte di avena', 'ml', 'latticini'], ['Yogurt', 'pz', 'latticini'],
        ['Yogurt greco', 'g', 'latticini'], ['Burro', 'g', 'latticini'], ['Panna da cucina', 'ml', 'latticini'],
        ['Mozzarella', 'g', 'latticini'], ['Parmigiano', 'g', 'latticini'], ['Grana Padano', 'g', 'latticini'],
        ['Pecorino', 'g', 'latticini'], ['Ricotta', 'g', 'latticini'], ['Stracchino', 'g', 'latticini'],
        ['Mascarpone', 'g', 'latticini'], ['Scamorza', 'g', 'latticini'], ['Fontina', 'g', 'latticini'],
        ['Uova', 'pz', 'latticini'],
        // carne e pesce
        ['Petto di pollo', 'g', 'carne_pesce'], ['Cosce di pollo', 'g', 'carne_pesce'], ['Fesa di tacchino', 'g', 'carne_pesce'],
        ['Carne macinata', 'g', 'carne_pesce'], ['Hamburger', 'pz', 'carne_pesce'], ['Fettine di vitello', 'g', 'carne_pesce'],
        ['Braciole di maiale', 'g', 'carne_pesce'], ['Salsiccia', 'g', 'carne_pesce'], ['Prosciutto cotto', 'g', 'carne_pesce'],
        ['Prosciutto crudo', 'g', 'carne_pesce'], ['Speck', 'g', 'carne_pesce'], ['Pancetta', 'g', 'carne_pesce'],
        ['Bresaola', 'g', 'carne_pesce'], ['Wurstel', 'pz', 'carne_pesce'], ['Salmone', 'g', 'carne_pesce'],
        ['Merluzzo', 'g', 'carne_pesce'], ['Orata', 'pz', 'carne_pesce'], ['Gamberi', 'g', 'carne_pesce'],
        ['Cozze', 'g', 'carne_pesce'], ['Vongole', 'g', 'carne_pesce'], ['Calamari', 'g', 'carne_pesce'],
        // dispensa
        ['Spaghetti', 'g', 'dispensa'], ['Penne', 'g', 'dispensa'], ['Fusilli', 'g', 'dispensa'],
        ['Farfalle', 'g', 'dispensa'], ['Rigatoni', 'g', 'dispensa'], ['Pastina', 'g', 'dispensa'],
        ['Lasagne', 'g', 'dispensa'], ['Gnocchi', 'g', 'dispensa'], ['Tortellini', 'g', 'dispensa'],
        ['Riso', 'g', 'dispensa'], ['Riso Carnaroli', 'g', 'dispensa'], ['Farro', 'g', 'dispensa'],
        ['Orzo', 'g', 'dispensa'], ['Cous cous', 'g', 'dispensa'], ['Polenta', 'g', 'dispensa'],
        ['Farina 00', 'g', 'dispensa'], ['Farina integrale', 'g', 'dispensa'], ['Lievito di birra', 'g', 'dispensa'],
        ['Lievito per dolci', 'pz', 'dispensa'], ['Zucchero', 'g', 'dispensa'], ['Sale', 'qb', 'dispensa'],
        ['Pepe', 'qb', 'dispensa'], ['Olio extravergine di oliva', 'ml', 'dispensa'], ['Olio di semi', 'ml', 'dispensa'],
        ['Aceto', 'ml', 'dispensa'], ['Aceto balsamico', 'ml', 'dispensa'], ['Passata di pomodoro', 'g', 'dispensa'],
        ['Pomodori pelati', 'g', 'dispensa'], ['Concentrato di pomodoro', 'g', 'dispensa'], ['Tonno in scatola', 'g', 'dispensa'],
        ['Ceci', 'g', 'dispensa'], ['Fagioli', 'g', 'dispensa'], ['Lenticchie', 'g', 'dispensa'],
        ['Piselli in scatola', 'g', 'dispensa'], ['Olive', 'g', 'dispensa'], ['Capperi', 'g', 'dispensa'],
        ['Brodo vegetale', 'ml', 'dispensa'], ['Dado', 'pz', 'dispensa'], ['Miele', 'g', 'dispensa'],
        ['Marmellata', 'g', 'dispensa'], ['Crema spalmabile', 'g', 'dispensa'], ['Biscotti', 'g', 'dispensa'],
        ['Cereali', 'g', 'dispensa'], ['Fiocchi d\'avena', 'g', 'dispensa'], ['Cacao amaro', 'g', 'dispensa'],
        ['Cioccolato fondente', 'g', 'dispensa'], ['Noci', 'g', 'dispensa'], ['Mandorle', 'g', 'dispensa'],
        ['Pinoli', 'g', 'dispensa'], ['Uvetta', 'g', 'dispensa'], ['Pesto', 'g', 'dispensa'],
        ['Maionese', 'g', 'dispensa'], ['Ketchup', 'g', 'dispensa'], ['Senape', 'g', 'dispensa'],
        ['Origano', 'qb', 'dispensa'], ['Curcuma', 'qb', 'dispensa'], ['Noce moscata', 'qb', 'dispensa'],
        ['Vanillina', 'pz', 'dispensa'], ['Zucchero a velo', 'g', 'dispensa'],
        // surgelati
        ['Piselli surgelati', 'g', 'surgelati'], ['Spinaci surgelati', 'g', 'surgelati'], ['Minestrone surgelato', 'g', 'surgelati'],
        ['Bastoncini di pesce', 'pz', 'surgelati'], ['Patatine surgelate', 'g', 'surgelati'], ['Gelato', 'g', 'surgelati'],
        ['Pizza surgelata', 'pz', 'surgelati'],
        // bevande
        ['Acqua naturale', 'l', 'bevande'], ['Acqua frizzante', 'l', 'bevande'], ['Succo di frutta', 'ml', 'bevande'],
        ['Tè', 'pz', 'bevande'], ['Caffè', 'g', 'bevande'], ['Vino bianco', 'ml', 'bevande'],
        // casa e igiene
        ['Detersivo piatti', 'pz', 'casa_igiene'], ['Detersivo lavatrice', 'pz', 'casa_igiene'], ['Carta igienica', 'pz', 'casa_igiene'],
        ['Carta da cucina', 'pz', 'casa_igiene'], ['Sacchetti spazzatura', 'pz', 'casa_igiene'], ['Dentifricio', 'pz', 'casa_igiene'],
        ['Shampoo', 'pz', 'casa_igiene'], ['Sapone mani', 'pz', 'casa_igiene'], ['Pellicola trasparente', 'pz', 'casa_igiene'],
        ['Carta forno', 'pz', 'casa_igiene'], ['Pastiglie lavastoviglie', 'pz', 'casa_igiene'],
    ];

    public function safeUp(): void
    {
        $this->createTable('{{%shopping_category}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->null(),
            'code' => $this->string(40)->null(),
            'name' => $this->string(60)->null(),
            'icon' => $this->string(40)->notNull(),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
        ], self::OPTIONS);
        $this->addForeignKey('fk_shopping_category_family', '{{%shopping_category}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->batchInsert('{{%shopping_category}}', ['code', 'icon', 'sort_order'],
            array_map(fn (array $c, int $i): array => [$c[0], $c[1], $i], self::CATEGORIES, array_keys(self::CATEGORIES)));

        $this->createTable('{{%ingredient}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->null(),
            'name' => $this->string(80)->notNull(),
            'default_unit' => self::UNITS . " NOT NULL DEFAULT 'pz'",
            'shopping_category_id' => $this->integer()->unsigned()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_ingredient_family_name', '{{%ingredient}}', ['family_id', 'name'], true);
        $this->addForeignKey('fk_ingredient_family', '{{%ingredient}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_ingredient_category', '{{%ingredient}}', 'shopping_category_id', '{{%shopping_category}}', 'id', 'RESTRICT', 'CASCADE');
        $categoryIds = (new \yii\db\Query())->select('id')->from('{{%shopping_category}}')->indexBy('code')->column($this->db);
        $this->batchInsert('{{%ingredient}}', ['name', 'default_unit', 'shopping_category_id'],
            array_map(fn (array $i): array => [$i[0], $i[1], $categoryIds[$i[2]]], self::INGREDIENTS));

        $this->createTable('{{%recipe}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            'title' => $this->string(120)->notNull(),
            'image_path' => $this->string(255)->null(),
            'servings' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(4),
            'prep_minutes' => $this->smallInteger()->unsigned()->null(),
            'instructions' => $this->text()->null(),
            'tags' => $this->json()->null(),
            'is_favorite' => $this->boolean()->notNull()->defaultValue(false),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->addForeignKey('fk_recipe_family', '{{%recipe}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_recipe_created_by', '{{%recipe}}', 'created_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%recipe_ingredient}}', [
            'id' => $this->primaryKey()->unsigned(),
            'recipe_id' => $this->integer()->unsigned()->notNull(),
            'ingredient_id' => $this->integer()->unsigned()->notNull(),
            'quantity' => $this->decimal(10, 2)->null(),
            'unit' => self::UNITS . ' NOT NULL',
            'note' => $this->string(80)->null(),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
        ], self::OPTIONS);
        $this->addForeignKey('fk_recipe_ingredient_recipe', '{{%recipe_ingredient}}', 'recipe_id', '{{%recipe}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_recipe_ingredient_ingredient', '{{%recipe_ingredient}}', 'ingredient_id', '{{%ingredient}}', 'id', 'RESTRICT', 'CASCADE');

        $this->createTable('{{%meal_plan_week}}', [
            'id' => $this->primaryKey()->unsigned(),
            'family_id' => $this->integer()->unsigned()->notNull(),
            // NULL for templates
            'week_start' => $this->date()->null(),
            'notes' => $this->text()->null(),
            'is_template' => $this->boolean()->notNull()->defaultValue(false),
            'template_name' => $this->string(60)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], self::OPTIONS);
        $this->createIndex('ux_meal_plan_week_family_start', '{{%meal_plan_week}}', ['family_id', 'week_start'], true);
        $this->addForeignKey('fk_meal_plan_week_family', '{{%meal_plan_week}}', 'family_id', '{{%family}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%meal_slot}}', [
            'id' => $this->primaryKey()->unsigned(),
            'week_id' => $this->integer()->unsigned()->notNull(),
            'day_index' => $this->tinyInteger()->unsigned()->notNull(),
            'meal_type' => "ENUM('breakfast','lunch','snack','dinner') NOT NULL",
            'servings' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(4),
        ], self::OPTIONS);
        $this->createIndex('ux_meal_slot_week_day_meal', '{{%meal_slot}}', ['week_id', 'day_index', 'meal_type'], true);
        $this->addForeignKey('fk_meal_slot_week', '{{%meal_slot}}', 'week_id', '{{%meal_plan_week}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%meal_entry}}', [
            'id' => $this->primaryKey()->unsigned(),
            'slot_id' => $this->integer()->unsigned()->notNull(),
            'recipe_id' => $this->integer()->unsigned()->null(),
            'free_text' => $this->string(120)->null(),
            'status' => "ENUM('planned','proposed','rejected') NOT NULL DEFAULT 'planned'",
            'proposed_by_child_id' => $this->integer()->unsigned()->null(),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
        ], self::OPTIONS);
        $this->addForeignKey('fk_meal_entry_slot', '{{%meal_entry}}', 'slot_id', '{{%meal_slot}}', 'id', 'CASCADE', 'CASCADE');
        // RESTRICT (not SET NULL): MySQL forbids CHECKs on columns with FK actions.
        // RecipeService turns entries into free text before deleting a recipe.
        $this->addForeignKey('fk_meal_entry_recipe', '{{%meal_entry}}', 'recipe_id', '{{%recipe}}', 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_meal_entry_child', '{{%meal_entry}}', 'proposed_by_child_id', '{{%child_profile}}', 'id', 'SET NULL', 'CASCADE');
        $this->addCheck('ck_meal_entry_content', '{{%meal_entry}}', '[[recipe_id]] IS NOT NULL OR [[free_text]] IS NOT NULL');

        $this->createTable('{{%meal_vote}}', [
            'entry_id' => $this->integer()->unsigned()->notNull(),
            'voter_type' => "ENUM('user','child') NOT NULL",
            'voter_id' => $this->integer()->notNull(),
            'value' => $this->tinyInteger()->notNull(),
            'PRIMARY KEY (entry_id, voter_type, voter_id)',
        ], self::OPTIONS);
        $this->addForeignKey('fk_meal_vote_entry', '{{%meal_vote}}', 'entry_id', '{{%meal_entry}}', 'id', 'CASCADE', 'CASCADE');

        // M5.7: tasks linked to a meal slot ("help cooking dinner")
        $this->addForeignKey('fk_task_meal_slot', '{{%task}}', 'linked_meal_slot_id', '{{%meal_slot}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_task_meal_slot', '{{%task}}');
        $this->dropTable('{{%meal_vote}}');
        $this->dropTable('{{%meal_entry}}');
        $this->dropTable('{{%meal_slot}}');
        $this->dropTable('{{%meal_plan_week}}');
        $this->dropTable('{{%recipe_ingredient}}');
        $this->dropTable('{{%recipe}}');
        $this->dropTable('{{%ingredient}}');
        $this->dropTable('{{%shopping_category}}');
    }
}
