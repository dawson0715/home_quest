<?php

declare(strict_types=1);

namespace app\commands;

use app\models\ChildProfile;
use app\models\Family;
use app\models\FamilyMember;
use app\models\Reward;
use app\models\User;
use app\services\MealPlanService;
use app\services\PointsService;
use app\services\RecipeService;
use app\services\ShoppingListService;
use app\services\TaskService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Demo data for manual testing: family "Rossi" with two parents, two children,
 * routines, rewards, recipes, this week's menu and a generated shopping list.
 */
class SeedController extends Controller
{
    public function actionDemo(): int
    {
        if (User::find()->where(['username' => ['demo-anna', 'demo-marco']])->exists()) {
            $this->stderr("Demo data already present (users demo-anna / demo-marco).\n");
            return ExitCode::DATAERR;
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            $tokens = $this->seed();
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        $this->stdout("Demo family \"Rossi\" created.\n");
        foreach ($tokens as $username => $token) {
            $this->stdout("  $username: $token\n");
        }
        $this->stdout("Children: Luca (PIN 1234), Sofia (no PIN). Pair a device from Impostazioni → Dispositivi.\n");
        return ExitCode::OK;
    }

    /**
     * @return array<string, string> username => access token
     */
    private function seed(): array
    {
        $family = new Family(['name' => 'Rossi', 'settings' => ['weekly_email' => false]]);
        $family->save(false);

        $tokens = [];
        $users = [];
        foreach ([['demo-anna', 'Anna', FamilyMember::ROLE_ADMIN], ['demo-marco', 'Marco', FamilyMember::ROLE_COPARENT]] as [$username, $name, $role]) {
            $user = new User(['username' => $username, 'name' => $name, 'status' => User::STATUS_ACTIVE]);
            $user->auth_key = User::generateAuthKey();
            $user->access_token = User::generateAccessToken();
            $user->save(false);
            (new FamilyMember(['family_id' => $family->id, 'user_id' => $user->id, 'role' => $role]))->save(false);
            $tokens[$username] = $user->access_token;
            $users[] = $user;
        }
        // act as the admin so family-scoped records get the right family
        Yii::$app->set('user', ['class' => \yii\web\User::class, 'identityClass' => User::class, 'enableSession' => false]);
        Yii::$app->user->setIdentity($users[0]);
        Yii::$app->get('familyContext')->reset();

        $luca = $this->child($family, 'Luca', 'dragon', '#2E5BBA', '2017-04', 0, '1234');
        $sofia = $this->child($family, 'Sofia', 'unicorn', '#B03A7A', '2020-09', 1, null);

        $tasks = TaskService::create();
        foreach ([
            ['Routine del mattino', '🌅', 10, false, 'weekly', 31, 'morning', [$luca, $sofia], ['Lavarsi i denti', 'Vestirsi', 'Fare colazione']],
            ['Mettere a posto i giochi', '🧸', 5, true, 'daily', null, 'evening', [$sofia], []],
            ['Compiti di scuola', '📚', 15, true, 'weekly', 31, 'afternoon', [$luca], []],
            ['Apparecchiare la tavola', '🍽️', 5, false, 'daily', null, 'evening', [$luca], []],
            ['Riordinare la camera', '🧹', 20, true, 'weekly', 32, 'any', [$luca, $sofia], []],
        ] as [$title, $icon, $points, $approval, $type, $days, $tod, $children, $checklist]) {
            $form = $tasks->newForm();
            $form->load([
                'title' => $title, 'icon' => $icon, 'points' => $points, 'requires_approval' => $approval,
                'recurrence_type' => $type, 'recurrence_days' => $days, 'time_of_day' => $tod,
                'assignee_ids' => array_map(fn (ChildProfile $c): int => (int) $c->id, $children),
                'checklist' => array_map(fn (string $label): array => ['label' => $label], $checklist),
            ]);
            $tasks->save($form, (int) $users[0]->id);
        }

        foreach ([['30 minuti di tablet', '📱', 50, 1, 'day'], ['Scegli la cena', '🍕', 100, 1, 'week'], ['Gita al parco giochi', '🎠', 200, null, null], ['Gelato', '🍦', 40, 2, 'week']] as $i => [$title, $icon, $cost, $limit, $period]) {
            $reward = new Reward(['title' => $title, 'icon' => $icon, 'cost' => $cost, 'limit_per_period' => $limit, 'limit_period' => $period, 'sort_order' => $i]);
            $reward->family_id = $family->id;
            $reward->save(false);
        }
        $points = new PointsService();
        $points->award($luca, 85, 85, 'bonus', null, null, 'Benvenuto!', (int) $users[0]->id);
        $points->award($sofia, 40, 40, 'bonus', null, null, 'Benvenuta!', (int) $users[0]->id);

        $recipes = new RecipeService();
        $recipeIds = [];
        foreach ([
            ['Pasta al pomodoro', 4, 20, ['veloce', 'preferito_bimbi'], [['Spaghetti', 320, 'g'], ['Passata di pomodoro', 400, 'g'], ['Basilico', null, 'qb'], ['Parmigiano', 40, 'g']]],
            ['Frittata di zucchine', 4, 25, ['vegetariano'], [['Uova', 6, 'pz'], ['Zucchine', 400, 'g'], ['Parmigiano', 30, 'g']]],
            ['Pollo al forno con patate', 4, 60, ['preferito_bimbi'], [['Cosce di pollo', 800, 'g'], ['Patate', 1, 'kg'], ['Rosmarino', null, 'qb']]],
            ['Minestrone', 4, 45, ['vegetariano'], [['Minestrone surgelato', 750, 'g'], ['Pastina', 160, 'g']]],
        ] as [$title, $servings, $minutes, $tags, $lines]) {
            $recipe = new \app\models\Recipe();
            $recipe->family_id = $family->id;
            $saved = $recipes->save($recipe, [
                'title' => $title, 'servings' => $servings, 'prep_minutes' => $minutes, 'tags' => $tags,
                'ingredients' => array_map(fn (array $l): array => ['name' => $l[0], 'quantity' => $l[1], 'unit' => $l[2]], $lines),
            ], (int) $users[0]->id);
            if (!$saved instanceof \app\models\Recipe) {
                throw new \RuntimeException('Demo recipe invalid: ' . json_encode($saved->getErrors()));
            }
            $recipeIds[] = (int) $saved->id;
        }

        $menu = new MealPlanService($family);
        $today = Yii::$app->get('clock')->today();
        $week = $menu->getOrCreateWeek((new \DateTimeImmutable($today))->modify('monday this week')->format('Y-m-d'));
        foreach ([[0, 'dinner', 0], [1, 'dinner', 1], [2, 'lunch', 3], [3, 'dinner', 2], [4, 'dinner', 0], [6, 'lunch', 2]] as [$day, $meal, $r]) {
            $menu->putSlot($week, $day, $meal, ['entries' => [['recipe_id' => $recipeIds[$r]]]]);
        }
        $menu->putSlot($week, 5, 'dinner', ['entries' => [['free_text' => 'Pizza a domicilio']]]);

        $shopping = ShoppingListService::for($family);
        $list = $shopping->lists()[0];
        $shopping->generateFromWeek($list, $week);
        foreach (['Latte', 'Pane', 'Detersivo piatti'] as $label) {
            $shopping->addItem($list, ['label' => $label]);
        }

        Yii::$app->user->setIdentity(null);
        return $tokens;
    }

    private function child(Family $family, string $name, string $avatar, string $color, string $born, int $order, ?string $pin): ChildProfile
    {
        $child = new ChildProfile(['name' => $name, 'avatar' => $avatar, 'color' => $color, 'birth_year_month' => $born, 'sort_order' => $order, 'can_check_shopping' => $name === 'Luca']);
        $child->family_id = $family->id;
        $child->setPin($pin);
        $child->save(false);
        $child->refresh();
        return $child;
    }
}
