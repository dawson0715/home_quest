<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Global task template catalog. Titles are i18n keys: frontend it.json `task_templates.<key>`.
 */
class m261116_150100_seed_task_templates extends Migration
{
    /** [key, icon, min_age, max_age, points, category, time_of_day, recurrence] */
    public const TEMPLATES = [
        ['brush_teeth_morning', '🪥', 3, 14, 5, 'hygiene', 'morning', 'daily'],
        ['brush_teeth_evening', '🪥', 3, 14, 5, 'hygiene', 'evening', 'daily'],
        ['get_dressed', '👕', 3, 10, 5, 'routine', 'morning', 'daily'],
        ['make_bed', '🛏️', 4, 14, 5, 'home', 'morning', 'daily'],
        ['pack_school_bag', '🎒', 6, 14, 5, 'school', 'evening', 'weekly'],
        ['breakfast_dishes', '🥣', 5, 14, 5, 'kitchen', 'morning', 'daily'],
        ['wash_hands', '🧼', 3, 8, 3, 'hygiene', 'any', 'daily'],
        ['shower', '🚿', 6, 14, 5, 'hygiene', 'evening', 'daily'],
        ['bath_time', '🛁', 3, 8, 5, 'hygiene', 'evening', 'weekly'],
        ['pajamas', '🩳', 3, 8, 3, 'routine', 'evening', 'daily'],
        ['tidy_toys', '🧸', 3, 9, 5, 'home', 'evening', 'daily'],
        ['tidy_room', '🧹', 6, 14, 10, 'home', 'any', 'weekly'],
        ['dirty_clothes_basket', '🧺', 4, 14, 3, 'home', 'evening', 'daily'],
        ['set_table', '🍽️', 5, 14, 5, 'kitchen', 'evening', 'daily'],
        ['clear_table', '🧽', 5, 14, 5, 'kitchen', 'evening', 'daily'],
        ['load_dishwasher', '🫧', 8, 14, 10, 'kitchen', 'evening', 'daily'],
        ['empty_dishwasher', '🥄', 8, 14, 10, 'kitchen', 'morning', 'daily'],
        ['help_cook', '👩‍🍳', 6, 14, 15, 'kitchen', 'evening', 'weekly'],
        ['water_plants', '🪴', 5, 14, 5, 'home', 'any', 'weekly'],
        ['feed_pet', '🐾', 5, 14, 5, 'pets', 'morning', 'daily'],
        ['walk_dog', '🐕', 9, 14, 15, 'pets', 'afternoon', 'daily'],
        ['clean_litter', '🐈', 10, 14, 10, 'pets', 'any', 'weekly'],
        ['homework', '📚', 6, 14, 15, 'school', 'afternoon', 'weekly'],
        ['read_book', '📖', 5, 14, 10, 'school', 'evening', 'daily'],
        ['practice_instrument', '🎹', 7, 14, 10, 'school', 'afternoon', 'weekly'],
        ['sign_school_diary', '📝', 6, 14, 3, 'school', 'evening', 'weekly'],
        ['take_out_trash', '🗑️', 8, 14, 10, 'home', 'evening', 'weekly'],
        ['sort_recycling', '♻️', 6, 14, 5, 'home', 'any', 'weekly'],
        ['fold_laundry', '👚', 8, 14, 10, 'home', 'any', 'weekly'],
        ['match_socks', '🧦', 4, 10, 5, 'home', 'any', 'weekly'],
        ['vacuum_room', '🧹', 10, 14, 15, 'home', 'any', 'weekly'],
        ['dust_shelves', '🪶', 8, 14, 10, 'home', 'any', 'weekly'],
        ['clean_bathroom_sink', '🚰', 10, 14, 10, 'home', 'any', 'weekly'],
        ['help_groceries', '🛒', 6, 14, 10, 'kitchen', 'any', 'weekly'],
        ['put_away_groceries', '🥫', 6, 14, 5, 'kitchen', 'any', 'weekly'],
        ['shoes_in_place', '👟', 3, 10, 3, 'home', 'afternoon', 'daily'],
        ['outdoor_play', '🌳', 3, 14, 5, 'health', 'afternoon', 'daily'],
        ['drink_water', '💧', 3, 10, 3, 'health', 'any', 'daily'],
        ['screen_off_on_time', '📵', 7, 14, 10, 'health', 'evening', 'daily'],
        ['be_kind_sibling', '🤝', 4, 14, 5, 'family', 'any', 'daily'],
        ['call_grandparents', '☎️', 5, 14, 5, 'family', 'any', 'weekly'],
        ['prepare_clothes_tomorrow', '🧥', 6, 14, 5, 'routine', 'evening', 'daily'],
    ];

    public function safeUp(): void
    {
        $rows = [];
        foreach (self::TEMPLATES as $i => [$key, $icon, $min, $max, $points, $category, $tod, $recurrence]) {
            $rows[] = [$key, $icon, $min, $max, $points, $category, $tod, $recurrence, $i];
        }
        $this->batchInsert(
            '{{%task_template}}',
            ['title_key', 'icon', 'min_age', 'max_age', 'default_points', 'category', 'time_of_day', 'recurrence_type', 'sort_order'],
            $rows
        );
    }

    public function safeDown(): void
    {
        $this->delete('{{%task_template}}');
    }
}
