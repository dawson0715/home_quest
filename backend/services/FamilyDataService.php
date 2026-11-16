<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\ErrorCode;
use app\models\Family;
use app\models\FamilyMember;
use app\models\Recipe;
use app\models\User;
use Yii;
use yii\db\Query;

/**
 * GDPR: full export of a family's data and definitive deletion (docs/02-architettura.md §7).
 */
class FamilyDataService
{
    public function __construct(private readonly Family $family)
    {
    }

    /**
     * Everything the family stored, without credentials or technical secrets.
     *
     * @return array<string, mixed>
     */
    public function export(): array
    {
        $f = (int) $this->family->id;
        $rows = fn (string $table, array $where, array $columns = ['*']): array => (new Query())->select($columns)->from($table)->where($where)->all();
        $ids = fn (string $table): array => (new Query())->select('id')->from($table)->where(['family_id' => $f])->column();

        $taskIds = $ids('{{%task}}');
        $recipeIds = $ids('{{%recipe}}');
        $weekIds = $ids('{{%meal_plan_week}}');
        $slotIds = (new Query())->select('id')->from('{{%meal_slot}}')->where(['week_id' => $weekIds ?: [0]])->column();
        $listIds = $ids('{{%shopping_list}}');

        return [
            'exported_at' => Yii::$app->get('clock')->now()->format(DATE_ATOM),
            'family' => ['id' => $f, 'name' => $this->family->name, 'settings' => $this->family->getEffectiveSettings()],
            'members' => (new Query())->select(['u.username', 'u.name', 'u.email', 'm.role'])->from('{{%family_member}} m')
                ->innerJoin('{{%user}} u', 'u.id = m.user_id')->where(['m.family_id' => $f])->all(),
            'children' => $rows('{{%child_profile}}', ['family_id' => $f], ['id', 'name', 'avatar', 'color', 'birth_year_month', 'points_balance', 'xp_total', 'level', 'current_streak', 'best_streak', 'can_check_shopping', 'archived_at']),
            'devices' => $rows('{{%device}}', ['family_id' => $f], ['id', 'name', 'last_seen_at', 'revoked_at']),
            'tasks' => $rows('{{%task}}', ['family_id' => $f]),
            'task_checklist_items' => $rows('{{%task_checklist_item}}', ['task_id' => $taskIds ?: [0]]),
            'task_assignees' => $rows('{{%task_assignee}}', ['task_id' => $taskIds ?: [0]]),
            'task_occurrences' => $rows('{{%task_occurrence}}', ['family_id' => $f]),
            'point_transactions' => $rows('{{%point_transaction}}', ['family_id' => $f]),
            'rewards' => $rows('{{%reward}}', ['family_id' => $f]),
            'reward_redemptions' => $rows('{{%reward_redemption}}', ['family_id' => $f]),
            'savings_goals' => $rows('{{%savings_goal}}', ['family_id' => $f]),
            'badges' => (new Query())->select(['cb.child_id', 'b.code', 'cb.earned_at'])->from('{{%child_badge}} cb')
                ->innerJoin('{{%badge}} b', 'b.id = cb.badge_id')->innerJoin('{{%child_profile}} c', 'c.id = cb.child_id')
                ->where(['c.family_id' => $f])->all(),
            'ingredients' => $rows('{{%ingredient}}', ['family_id' => $f]),
            'recipes' => $rows('{{%recipe}}', ['family_id' => $f]),
            'recipe_ingredients' => $rows('{{%recipe_ingredient}}', ['recipe_id' => $recipeIds ?: [0]]),
            'meal_plan_weeks' => $rows('{{%meal_plan_week}}', ['family_id' => $f]),
            'meal_slots' => $rows('{{%meal_slot}}', ['week_id' => $weekIds ?: [0]]),
            'meal_entries' => $rows('{{%meal_entry}}', ['slot_id' => $slotIds ?: [0]]),
            'meal_votes' => $rows('{{%meal_vote}}', ['entry_id' => (new Query())->select('id')->from('{{%meal_entry}}')->where(['slot_id' => $slotIds ?: [0]])]),
            'shopping_lists' => $rows('{{%shopping_list}}', ['family_id' => $f]),
            'shopping_items' => $rows('{{%shopping_item}}', ['list_id' => $listIds ?: [0]]),
            'favorite_items' => $rows('{{%favorite_item}}', ['family_id' => $f]),
        ];
    }

    /**
     * Deletes the family and everything in it, including the parents' accounts
     * (a user belongs to exactly one family) and the recipe photos.
     */
    public function delete(string $confirmName): void
    {
        if (trim($confirmName) !== $this->family->name) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Type the family name to confirm.', 422, 'confirm_name');
        }
        $userIds = FamilyMember::find()->forFamily($this->family->id)->select('user_id')->column();
        $images = Recipe::find()->forFamily($this->family->id)->andWhere(['not', ['image_path' => null]])->select('image_path')->column();

        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            // meal_entry.recipe_id is RESTRICT: clear the menu before recipes cascade
            $weekIds = (new Query())->select('id')->from('{{%meal_plan_week}}')->where(['family_id' => $this->family->id])->column();
            $db->createCommand()->delete('{{%meal_plan_week}}', ['id' => $weekIds ?: [0]])->execute();
            $db->createCommand()->delete('{{%family}}', ['id' => $this->family->id])->execute();
            User::deleteAll(['id' => $userIds ?: [0]]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        foreach ($images as $path) {
            $file = Yii::getAlias('@webroot') . '/' . $path;
            if (str_starts_with((string) $path, RecipeService::IMAGE_DIR . '/') && is_file($file)) {
                unlink($file);
            }
        }
    }
}
