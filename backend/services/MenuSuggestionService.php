<?php

declare(strict_types=1);

namespace app\services;

use app\components\AiClient;
use app\components\AiException;
use app\components\ApiException;
use app\components\ErrorCode;
use app\models\Family;
use app\models\MealEntry;
use app\models\MealPlanWeek;
use app\models\MealSlot;
use app\models\Recipe;
use app\models\RecipeIngredient;
use Yii;
use yii\caching\CacheInterface;
use yii\db\Query;

/**
 * Suggests dishes for the empty slots of a week, from the family cookbook,
 * its history (last 8 weeks) and the votes. Nothing is saved: the planner
 * shows a preview and writes the accepted dishes through PUT slot.
 *
 * With `settings.ai_suggestions` and a configured AiClient the choice is made
 * by the model, which receives ANONYMOUS data only: recipes are numbered 1..N
 * (no database ids), no names of people, no family details, no per-person
 * votes. Otherwise (or on any failure) a local scoring algorithm is used.
 */
class MenuSuggestionService
{
    public const NOTE_MAX = 200;
    public const HISTORY_WEEKS = 8;

    public const SOURCE_AI = 'ai';
    public const SOURCE_LOCAL = 'local';

    public const FALLBACK_DISABLED = 'disabled';
    public const FALLBACK_NOT_CONFIGURED = 'not_configured';
    public const FALLBACK_RATE_LIMITED = 'rate_limited';
    public const FALLBACK_ERROR = 'error';

    private const LIGHT_MEALS = ['breakfast', 'snack'];
    private const WEEKDAYS_IT = [1 => 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
    private const MEALS_IT = ['breakfast' => 'colazione', 'lunch' => 'pranzo', 'snack' => 'merenda', 'dinner' => 'cena'];

    /** @var array<int, Recipe> recipes by database id */
    private array $recipes = [];
    /** @var array<int, array{times: int, days_since: int|null, meals: list<string>, up: int, down: int}> */
    private array $stats = [];
    /** @var list<array{text: string, meal_type: string, times: int, days_since: int}> */
    private array $habits = [];
    /** @var list<array{day_index: int, meal_type: string, recipe_id: int|null, title: string}> */
    private array $planned = [];

    public function __construct(
        private readonly Family $family,
        private readonly AiClient $ai,
        private readonly CacheInterface $cache,
        private readonly int $aiDailyLimit = 20,
    ) {
    }

    public static function create(Family $family): self
    {
        /** @var AiClient $ai */
        $ai = Yii::$container->get(AiClient::class);
        return new self($family, $ai, Yii::$app->cache, (int) Yii::$app->params['aiDailyLimit']);
    }

    /**
     * @return array{source: string, fallback_reason: string|null, suggestions: list<array<string, mixed>>}
     */
    public function suggest(MealPlanWeek $week, string $note = ''): array
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));
        if (mb_strlen($note) > self::NOTE_MAX) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'note max ' . self::NOTE_MAX . ' characters.', 422, 'note');
        }
        $this->load($week);
        $slots = $this->emptySlots();
        if ($slots === []) {
            return ['source' => self::SOURCE_LOCAL, 'fallback_reason' => null, 'suggestions' => []];
        }

        $fallback = $this->aiUnavailableReason();
        $picks = null;
        if ($fallback === null) {
            try {
                $picks = $this->askAi($week, $slots, $note);
            } catch (AiException $e) {
                Yii::warning('AI menu suggestion failed: ' . $e->getMessage(), __METHOD__);
                $fallback = self::FALLBACK_ERROR;
            }
        }
        $source = $picks === null ? self::SOURCE_LOCAL : self::SOURCE_AI;
        // slots the model left out (or all of them, without AI) are filled locally
        $picks = $this->localPicks($week, $slots, $picks ?? []);

        $suggestions = [];
        $start = new \DateTimeImmutable((string) $week->week_start, new \DateTimeZone('UTC'));
        foreach ($slots as $key => $slot) {
            $pick = $picks[$key] ?? null;
            if ($pick === null) {
                continue;
            }
            $recipe = $pick['recipe_id'] === null ? null : $this->recipes[$pick['recipe_id']];
            $suggestions[] = [
                'day_index' => $slot['day_index'],
                'date' => $start->modify("+{$slot['day_index']} days")->format('Y-m-d'),
                'meal_type' => $slot['meal_type'],
                'recipe_id' => $recipe === null ? null : (int) $recipe->id,
                'recipe' => $recipe?->toSummary(),
                'free_text' => $recipe === null ? $pick['free_text'] : null,
                'title' => $recipe === null ? (string) $pick['free_text'] : $recipe->title,
                'reason' => $pick['reason'],
                'reason_code' => $pick['reason_code'],
            ];
        }
        return ['source' => $source, 'fallback_reason' => $source === self::SOURCE_AI ? null : $fallback, 'suggestions' => $suggestions];
    }

    // ---- data -------------------------------------------------------------

    private function load(MealPlanWeek $week): void
    {
        $familyId = (int) $this->family->id;
        /** @var Recipe[] $recipes */
        $recipes = Recipe::find()->forFamily($familyId)->with('recipeIngredients')->orderBy(['id' => SORT_ASC])->all();
        $this->recipes = [];
        $this->stats = [];
        foreach ($recipes as $recipe) {
            $this->recipes[(int) $recipe->id] = $recipe;
            $this->stats[(int) $recipe->id] = ['times' => 0, 'days_since' => null, 'meals' => [], 'up' => 0, 'down' => 0];
        }

        $start = new \DateTimeImmutable((string) $week->week_start, new \DateTimeZone('UTC'));
        $from = $start->modify('-' . (self::HISTORY_WEEKS * 7) . ' days')->format('Y-m-d');
        $rows = (new Query())
            ->select(['e.recipe_id', 'e.free_text', 's.meal_type', 's.day_index', 'w.week_start'])
            ->from(['e' => MealEntry::tableName()])
            ->innerJoin(['s' => MealSlot::tableName()], 's.id = e.slot_id')
            ->innerJoin(['w' => MealPlanWeek::tableName()], 'w.id = s.week_id')
            ->where(['w.family_id' => $familyId, 'w.is_template' => false, 'e.status' => MealEntry::STATUS_PLANNED])
            ->andWhere(['>=', 'w.week_start', $from])
            ->andWhere(['<', 'w.week_start', $start->format('Y-m-d')])
            ->all();
        $habits = [];
        foreach ($rows as $row) {
            $date = (new \DateTimeImmutable((string) $row['week_start'], new \DateTimeZone('UTC')))->modify('+' . (int) $row['day_index'] . ' days');
            $daysSince = max(1, (int) $date->diff($start)->days);
            $meal = (string) $row['meal_type'];
            $id = $row['recipe_id'] === null ? null : (int) $row['recipe_id'];
            if ($id !== null && isset($this->stats[$id])) {
                $s = $this->stats[$id];
                $s['times']++;
                $s['days_since'] = $s['days_since'] === null ? $daysSince : min($s['days_since'], $daysSince);
                if (!in_array($meal, $s['meals'], true)) {
                    $s['meals'][] = $meal;
                }
                $this->stats[$id] = $s;
            } elseif ($id === null && $row['free_text'] !== null) {
                $text = (string) $row['free_text'];
                $k = $meal . "\0" . mb_strtolower($text);
                $habits[$k] ??= ['text' => $text, 'meal_type' => $meal, 'times' => 0, 'days_since' => $daysSince];
                $habits[$k]['times']++;
                $habits[$k]['days_since'] = min($habits[$k]['days_since'], $daysSince);
            }
        }
        usort($habits, fn (array $a, array $b): int => [$b['times'], $a['text']] <=> [$a['times'], $b['text']]);
        $this->habits = array_slice($habits, 0, 20);

        // votes of every family member, summed per recipe (never per person)
        $votes = (new Query())
            ->select(['e.recipe_id', 'up' => 'SUM(v.value > 0)', 'down' => 'SUM(v.value < 0)'])
            ->from(['v' => '{{%meal_vote}}'])
            ->innerJoin(['e' => MealEntry::tableName()], 'e.id = v.entry_id')
            ->innerJoin(['s' => MealSlot::tableName()], 's.id = e.slot_id')
            ->innerJoin(['w' => MealPlanWeek::tableName()], 'w.id = s.week_id')
            ->where(['w.family_id' => $familyId])
            ->andWhere(['not', ['e.recipe_id' => null]])
            ->groupBy('e.recipe_id')
            ->all();
        foreach ($votes as $row) {
            $id = (int) $row['recipe_id'];
            if (isset($this->stats[$id])) {
                $this->stats[$id]['up'] = (int) $row['up'];
                $this->stats[$id]['down'] = (int) $row['down'];
            }
        }

        // dishes already planned in the target week
        $this->planned = [];
        /** @var MealSlot $slot */
        foreach (MealSlot::find()->where(['week_id' => $week->id])->with('entries.recipe')->all() as $slot) {
            foreach ($slot->entries as $entry) {
                if ($entry->status === MealEntry::STATUS_PLANNED) {
                    $this->planned[] = [
                        'day_index' => (int) $slot->day_index,
                        'meal_type' => $slot->meal_type,
                        'recipe_id' => $entry->recipe_id === null ? null : (int) $entry->recipe_id,
                        'title' => $entry->getTitle(),
                    ];
                }
            }
        }
    }

    /**
     * Enabled meal slots without planned dishes, keyed "day:meal".
     *
     * @return array<string, array{day_index: int, meal_type: string}>
     */
    private function emptySlots(): array
    {
        $mealTypes = (array) ($this->family->getEffectiveSettings()['meal_types'] ?? MealSlot::MEAL_TYPES);
        $taken = [];
        foreach ($this->planned as $p) {
            $taken["{$p['day_index']}:{$p['meal_type']}"] = true;
        }
        $out = [];
        for ($day = 0; $day < 7; $day++) {
            foreach (MealSlot::MEAL_TYPES as $meal) {
                if (in_array($meal, $mealTypes, true) && !isset($taken["$day:$meal"])) {
                    $out["$day:$meal"] = ['day_index' => $day, 'meal_type' => $meal];
                }
            }
        }
        return $out;
    }

    private function aiUnavailableReason(): ?string
    {
        if (!($this->family->getEffectiveSettings()['ai_suggestions'] ?? false)) {
            return self::FALLBACK_DISABLED;
        }
        if (!$this->ai->isEnabled()) {
            return self::FALLBACK_NOT_CONFIGURED;
        }
        $key = 'ai-menu:' . $this->family->id . ':' . date('Y-m-d');
        $count = (int) $this->cache->get($key);
        if ($count >= $this->aiDailyLimit) {
            return self::FALLBACK_RATE_LIMITED;
        }
        $this->cache->set($key, $count + 1, 86400);
        return null;
    }

    // ---- AI ---------------------------------------------------------------

    /**
     * @param array<string, array{day_index: int, meal_type: string}> $slots
     * @return array<string, array{recipe_id: int|null, free_text: string|null, reason: string|null, reason_code: string|null}>
     */
    private function askAi(MealPlanWeek $week, array $slots, string $note): array
    {
        $prompt = $this->buildPrompt($week, $slots, $note);
        $answer = $this->ai->completeJson($prompt['system'], (string) json_encode($prompt['input'], JSON_UNESCAPED_UNICODE), self::answerSchema());
        return $this->parseAnswer($answer, $prompt['slot_keys'], $prompt['recipe_ids']);
    }

    /**
     * The anonymous request sent to the model.
     *
     * @param array<string, array{day_index: int, meal_type: string}> $slots
     * @return array{system: string, input: array<string, mixed>, slot_keys: array<int, string>, recipe_ids: array<int, int>}
     */
    public function buildPrompt(MealPlanWeek $week, array $slots, string $note): array
    {
        $start = new \DateTimeImmutable((string) $week->week_start, new \DateTimeZone('UTC'));
        $dayName = fn (int $day): string => self::WEEKDAYS_IT[(int) $start->modify("+$day days")->format('N')];

        $recipeIds = [];
        $numberOf = [];
        $recipes = [];
        foreach ($this->recipes as $id => $recipe) {
            $n = count($recipeIds) + 1;
            $recipeIds[$n] = $id;
            $numberOf[$id] = $n;
            $s = $this->stats[$id];
            $recipes[] = array_filter([
                'n' => $n,
                'titolo' => $recipe->title,
                'tag' => $recipe->tags ?: null,
                'minuti' => $recipe->prep_minutes === null ? null : (int) $recipe->prep_minutes,
                'preferita' => (bool) $recipe->is_favorite ?: null,
                'ingredienti' => array_slice(array_map(fn (RecipeIngredient $ri): string => $ri->ingredient->name, $recipe->recipeIngredients), 0, 8) ?: null,
                'volte_ultime_8_settimane' => $s['times'],
                'giorni_dall_ultima_volta' => $s['days_since'],
                'pasti_abituali' => array_map(fn (string $m): string => self::MEALS_IT[$m], $s['meals']) ?: null,
                'voti_positivi' => $s['up'] ?: null,
                'voti_negativi' => $s['down'] ?: null,
            ], fn ($v): bool => $v !== null);
        }

        $slotKeys = [];
        $toFill = [];
        foreach ($slots as $key => $slot) {
            $n = count($slotKeys) + 1;
            $slotKeys[$n] = $key;
            $toFill[] = ['slot' => $n, 'giorno' => $dayName($slot['day_index']), 'pasto' => self::MEALS_IT[$slot['meal_type']]];
        }

        $input = [
            'mese' => self::monthName((int) $start->format('n')),
            'slot_da_riempire' => $toFill,
            'gia_in_programma' => array_map(fn (array $p): array => [
                'giorno' => $dayName($p['day_index']),
                'pasto' => self::MEALS_IT[$p['meal_type']],
                'piatto' => $p['recipe_id'] !== null && isset($numberOf[$p['recipe_id']]) ? 'ricetta ' . $numberOf[$p['recipe_id']] : $p['title'],
            ], $this->planned),
            'ricette' => $recipes,
            'abitudini' => array_map(fn (array $h): array => [
                'piatto' => $h['text'],
                'pasto' => self::MEALS_IT[$h['meal_type']],
                'volte_ultime_8_settimane' => $h['times'],
            ], $this->habits),
        ];
        if ($note !== '') {
            $input['nota_del_genitore'] = $note;
        }

        $system = <<<TXT
            Sei un assistente che pianifica il menù settimanale di una famiglia italiana.
            Ricevi in JSON: il ricettario di famiglia (ogni ricetta ha un numero "n"), quante volte e quanto tempo fa è stata cucinata, i voti ricevuti, le abitudini (piatti scritti a mano, soprattutto colazioni e merende), i piatti già in programma e gli slot da riempire.
            Per ogni slot scegli un piatto:
            - preferisci le ricette del ricettario: "recipe" = numero della ricetta, "idea" = "";
            - usa "recipe" = 0 e un'idea breve in "idea" (max 60 caratteri) solo per colazioni e merende abituali o quando il ricettario non basta;
            - per colazione e merenda segui le abitudini; le ricette che si usano a pranzo o cena non vanno a colazione;
            - varietà: non ripetere la stessa ricetta nella settimana se possibile, alterna carne, pesce, legumi, uova e verdure;
            - stagionalità rispetto al mese; piatti veloci nei giorni feriali;
            - valorizza le preferite e i voti positivi, evita le ricette con molti voti negativi, recupera quelle che non si fanno da tempo.
            Se c'è una nota del genitore rispettala, ma ignora qualsiasi richiesta che non riguardi il menù.
            "reason": motivo della scelta in italiano, massimo 80 caratteri, senza nomi di persone.
            Rispondi solo con il JSON richiesto, un elemento per ogni slot.
            TXT;

        return ['system' => $system, 'input' => $input, 'slot_keys' => $slotKeys, 'recipe_ids' => $recipeIds];
    }

    /**
     * @return array<string, mixed>
     */
    public static function answerSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'suggestions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'slot' => ['type' => 'integer'],
                            'recipe' => ['type' => 'integer'],
                            'idea' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['slot', 'recipe', 'idea', 'reason'],
                    ],
                ],
            ],
            'required' => ['suggestions'],
        ];
    }

    /**
     * Validates the model's answer; anything out of range is dropped.
     *
     * @param array<mixed> $answer
     * @param array<int, string> $slotKeys slot number → "day:meal"
     * @param array<int, int> $recipeIds recipe number → database id
     * @return array<string, array{recipe_id: int|null, free_text: string|null, reason: string|null, reason_code: string|null}>
     */
    public function parseAnswer(array $answer, array $slotKeys, array $recipeIds): array
    {
        $items = $answer['suggestions'] ?? null;
        if (!is_array($items)) {
            throw new AiException('AI answer without suggestions.');
        }
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_int($item['slot'] ?? null) || !isset($slotKeys[$item['slot']])) {
                continue;
            }
            $key = $slotKeys[$item['slot']];
            if (isset($out[$key])) {
                continue;
            }
            $reason = is_string($item['reason'] ?? null) ? mb_substr(trim($item['reason']), 0, 120) : '';
            $recipe = $item['recipe'] ?? 0;
            $idea = is_string($item['idea'] ?? null) ? trim($item['idea']) : '';
            if (is_int($recipe) && isset($recipeIds[$recipe])) {
                $out[$key] = ['recipe_id' => $recipeIds[$recipe], 'free_text' => null, 'reason' => $reason ?: null, 'reason_code' => null];
            } elseif ($idea !== '' && mb_strlen($idea) <= 120) {
                $out[$key] = ['recipe_id' => null, 'free_text' => $idea, 'reason' => $reason ?: null, 'reason_code' => null];
            }
        }
        if ($out === []) {
            throw new AiException('AI answer without valid suggestions.');
        }
        return $out;
    }

    // ---- local algorithm ---------------------------------------------------

    /**
     * Greedy scoring: favourites, liked and long-unseen recipes first, quick
     * ones on weekdays, no repeats in the week while alternatives exist.
     * Breakfast/snack use recipes or habits seen at that meal only.
     *
     * @param array<string, array{day_index: int, meal_type: string}> $slots
     * @param array<string, array{recipe_id: int|null, free_text: string|null, reason: string|null, reason_code: string|null}> $picks already chosen (kept)
     * @return array<string, array{recipe_id: int|null, free_text: string|null, reason: string|null, reason_code: string|null}>
     */
    public function localPicks(MealPlanWeek $week, array $slots, array $picks = []): array
    {
        $start = new \DateTimeImmutable((string) $week->week_start, new \DateTimeZone('UTC'));
        $uses = [];
        $usesByDay = [];
        $count = function (int $day, ?int $recipeId, ?string $text) use (&$uses, &$usesByDay): void {
            $k = $recipeId !== null ? "r$recipeId" : 't' . mb_strtolower((string) $text);
            $uses[$k] = ($uses[$k] ?? 0) + 1;
            $usesByDay["$day:$k"] = true;
        };
        foreach ($this->planned as $p) {
            $count($p['day_index'], $p['recipe_id'], $p['title']);
        }
        foreach ($picks as $key => $pick) {
            $count((int) explode(':', $key)[0], $pick['recipe_id'], $pick['free_text']);
        }

        foreach ($slots as $key => $slot) {
            if (isset($picks[$key])) {
                continue;
            }
            $day = $slot['day_index'];
            $meal = $slot['meal_type'];
            $light = in_array($meal, self::LIGHT_MEALS, true);
            $weekday = (int) $start->modify("+$day days")->format('N') <= 5;
            $best = null;

            foreach ($this->recipes as $id => $recipe) {
                $s = $this->stats[$id];
                $onlyLight = $s['meals'] !== [] && array_diff($s['meals'], self::LIGHT_MEALS) === [];
                if ($light ? !in_array($meal, $s['meals'], true) : $onlyLight) {
                    continue;
                }
                $tags = $recipe->tags ?? [];
                $parts = [
                    'favorite' => $recipe->is_favorite ? 2.0 : 0.0,
                    'kids_favorite' => in_array('preferito_bimbi', $tags, true) ? 1.5 : 0.0,
                    'liked' => max(-3.0, min(3.0, ($s['up'] - $s['down']) * 0.5)),
                    'not_recent' => $s['days_since'] === null ? 1.0 : min($s['days_since'], 35) / 7,
                    'quick' => !$light && $weekday && in_array('veloce', $tags, true) ? 1.0 : 0.0,
                ];
                $k = "r$id";
                $score = array_sum($parts) - 4 * ($uses[$k] ?? 0) - (isset($usesByDay["$day:$k"]) ? 3 : 0) + $this->jitter($week, $k);
                if ($best === null || $score > $best['score']) {
                    $best = ['score' => $score, 'recipe_id' => $id, 'free_text' => null, 'parts' => $parts];
                }
            }
            foreach ($this->habits as $h) {
                if ($h['meal_type'] !== $meal || (!$light && $h['times'] < 2)) {
                    continue;
                }
                $k = 't' . mb_strtolower($h['text']);
                // a habit repeats happily (same breakfast every day), but less than a new one
                $score = 1.0 + $h['times'] * 0.5 - ($light ? 0.5 : 4) * ($uses[$k] ?? 0) + $this->jitter($week, $k);
                if ($best === null || $score > $best['score']) {
                    $best = ['score' => $score, 'recipe_id' => null, 'free_text' => $h['text'], 'parts' => ['habit' => 1.0]];
                }
            }
            if ($best === null) {
                continue;
            }
            $parts = array_filter($best['parts'], fn (float $v): bool => $v > 0);
            arsort($parts);
            $reasonCode = $parts === [] ? null : (string) array_key_first($parts);
            $picks[$key] = ['recipe_id' => $best['recipe_id'], 'free_text' => $best['free_text'], 'reason' => null, 'reason_code' => $reasonCode];
            $count($day, $best['recipe_id'], $best['free_text']);
        }
        return $picks;
    }

    /** Small stable tie-breaker, different every week. */
    private function jitter(MealPlanWeek $week, string $key): float
    {
        return (crc32($week->week_start . '/' . $key) % 1000) / 10000;
    }

    private static function monthName(int $month): string
    {
        return ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'][$month - 1];
    }
}
