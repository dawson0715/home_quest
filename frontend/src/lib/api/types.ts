// API contract types — keep aligned with docs/04-api.md.

export interface ApiErrorItem {
  /** Stable machine-readable code (backend app\components\ErrorCode). */
  code: string;
  /** Developer-facing message: never show it to users, translate `code` instead. */
  message: string;
  field?: string;
}

export interface Pagination {
  totalCount: number;
  pageCount: number;
  currentPage: number;
  perPage: number;
}

export interface EnvelopeMeta {
  errors?: ApiErrorItem[];
  pagination?: Pagination;
  [key: string]: unknown;
}

/** Every API response: {"data": ..., "meta": {...}}. */
export interface Envelope<T> {
  data: T;
  meta: EnvelopeMeta;
}

export type FamilyRole = 'admin' | 'coparent';
export type MealType = 'breakfast' | 'lunch' | 'snack' | 'dinner';

export interface FamilySettings {
  allow_penalties: boolean;
  weekly_email: boolean;
  meal_types: MealType[];
  week_starts_on: 1 | 7;
  shopping_poll_seconds: number;
}

export interface Family {
  id: number;
  name: string;
  settings: FamilySettings;
}

export interface FamilyUpdate {
  name?: string;
  settings?: Partial<FamilySettings>;
}

export interface User {
  id: number;
  username: string;
  name: string | null;
}

export interface ParentMe {
  user: User;
  family: Family | null;
  role: FamilyRole | null;
  /** the parent's own game profile */
  player: Child | null;
}

export interface DeviceMe {
  device: { id: number; name: string };
  family: Family;
  role: 'device';
}

export interface ChildMe {
  child: Child;
  family: Family;
  role: 'child';
}

export type Me = ParentMe | DeviceMe | ChildMe;

export interface PairingCode {
  code: string;
  expires_at: string;
}

export interface Device {
  id: number;
  name: string;
  last_seen_at: string | null;
  created_at: string;
  revoked: boolean;
}

export interface PairResult {
  device_token: string;
  device: Device;
}

export interface ChildProfileCard {
  id: number;
  name: string;
  avatar: Avatar;
  color: string;
  has_pin: boolean;
  is_adult?: boolean;
}

export interface SelectChildResult {
  child_token: string;
  child: Child;
}

export interface FamilyMember {
  id: number;
  user_id: number;
  username: string;
  name: string | null;
  role: FamilyRole;
}

export const AVATARS = [
  'fox',
  'bear',
  'cat',
  'dog',
  'owl',
  'panda',
  'lion',
  'rabbit',
  'unicorn',
  'dragon',
  'robot',
  'astronaut',
  'dino',
  'penguin',
  'koala',
  'frog'
] as const;
export type Avatar = (typeof AVATARS)[number];

export interface Child {
  id: number;
  name: string;
  avatar: Avatar;
  color: string;
  birth_year_month: string | null;
  has_pin: boolean;
  points_balance: number;
  xp_total: number;
  level: number;
  current_streak: number;
  best_streak: number;
  can_check_shopping: boolean;
  sort_order: number;
  archived: boolean;
  /** game profile of a parent */
  is_adult: boolean;
}

export interface ChildInput {
  name: string;
  avatar: Avatar;
  color: string;
  birth_year_month?: string | null;
  can_check_shopping?: boolean;
  sort_order?: number;
}

export type RecurrenceType = 'none' | 'daily' | 'weekly' | 'interval';
export type TimeOfDay = 'morning' | 'afternoon' | 'evening' | 'any';
export const TIMES_OF_DAY: TimeOfDay[] = ['morning', 'afternoon', 'evening', 'any'];

export interface ChecklistItem {
  id: number;
  label: string;
  icon: string | null;
}

export interface Task {
  id: number;
  title: string;
  description: string | null;
  icon: string;
  points: number;
  xp: number;
  requires_approval: boolean;
  /** first player who completes it wins; no assignees = every player */
  is_open: boolean;
  recurrence_type: RecurrenceType;
  recurrence_days: number | null;
  interval_days: number | null;
  time_of_day: TimeOfDay;
  due_date: string | null;
  start_date: string;
  end_date: string | null;
  is_active: boolean;
  linked_meal_slot_id: number | null;
  assignee_ids: number[];
  checklist: ChecklistItem[];
}

export interface TaskInput {
  title?: string;
  description?: string | null;
  icon?: string;
  points?: number;
  xp?: number;
  requires_approval?: boolean;
  is_open?: boolean;
  recurrence_type?: RecurrenceType;
  recurrence_days?: number | null;
  interval_days?: number | null;
  time_of_day?: TimeOfDay;
  due_date?: string | null;
  start_date?: string;
  end_date?: string | null;
  is_active?: boolean;
  linked_meal_slot_id?: number | null;
  assignee_ids?: number[];
  checklist?: { id?: number; label: string; icon?: string | null }[];
}

export interface TaskTemplate {
  id: number;
  title_key: string;
  icon: string;
  min_age: number;
  max_age: number;
  default_points: number;
  category: string;
  time_of_day: TimeOfDay;
  recurrence_type: RecurrenceType;
}

export type OccurrenceStatus =
  'pending' | 'submitted' | 'approved' | 'rejected' | 'missed' | 'taken';

export interface Occurrence {
  id: number;
  task_id: number;
  child_id: number;
  due_date: string;
  status: OccurrenceStatus;
  title: string;
  icon: string;
  points: number;
  time_of_day: TimeOfDay;
  requires_approval: boolean;
  is_open: boolean;
  checklist: (ChecklistItem & { done: boolean })[];
  submitted_at: string | null;
  reviewed_at: string | null;
  review_note: string | null;
  points_awarded: number | null;
  child?: ChildProfileCard;
}

export interface TransitionResult {
  occurrence: Occurrence;
  points_awarded: number;
  level_up: boolean;
  balance: number | null;
  new_badges: EarnedBadge[];
}

export interface Today {
  date: string;
  child: Child & { xp_level_start: number; xp_next_level: number };
  groups: Record<TimeOfDay, Occurrence[]>;
  savings_goal: SavingsGoal | null;
}

export interface BulkApproveResult {
  approved: number[];
  skipped: number[];
}

export type RewardPeriod = 'day' | 'week' | 'month';

export interface Reward {
  id: number;
  title: string;
  icon: string;
  cost: number;
  limit_per_period: number | null;
  limit_period: RewardPeriod | null;
  stock: number | null;
  is_active: boolean;
  sort_order: number;
}

export interface RewardInput {
  title?: string;
  icon?: string;
  cost?: number;
  limit_per_period?: number | null;
  limit_period?: RewardPeriod | null;
  stock?: number | null;
  is_active?: boolean;
  sort_order?: number;
}

/** Child view of the shop. */
export interface ShopReward extends Reward {
  affordable: boolean;
  available: boolean;
  is_goal: boolean;
}

export type RedemptionStatus = 'requested' | 'approved' | 'rejected' | 'fulfilled';

export interface Redemption {
  id: number;
  reward_id: number;
  child_id: number;
  title: string;
  icon: string;
  cost: number;
  status: RedemptionStatus;
  requested_at: string;
  reviewed_at: string | null;
  note: string | null;
  child: ChildProfileCard;
}

export interface EarnedBadge {
  code: string;
  icon: string;
}

export interface BadgeStatus extends EarnedBadge {
  earned_at: string | null;
}

export type PointTransactionType =
  'task' | 'bonus' | 'penalty' | 'reward' | 'streak_bonus' | 'badge' | 'adjustment';

export interface PointTransaction {
  id: number;
  child_id: number;
  points: number;
  xp: number;
  type: PointTransactionType;
  source_type: string | null;
  source_id: number | null;
  note: string | null;
  created_at: string;
}

export interface SavingsGoal {
  reward_id: number;
  title: string;
  icon: string;
  cost: number;
  progress: number;
}

export type Unit = 'g' | 'kg' | 'ml' | 'l' | 'pz' | 'cucchiaio' | 'cucchiaino' | 'qb';
export const UNITS: Unit[] = ['g', 'kg', 'ml', 'l', 'pz', 'cucchiaio', 'cucchiaino', 'qb'];
export type RecipeTag = 'veloce' | 'vegetariano' | 'preferito_bimbi';
export const RECIPE_TAGS: RecipeTag[] = ['veloce', 'vegetariano', 'preferito_bimbi'];
export interface ShoppingCategory {
  id: number;
  /** i18n `shopping_categories.<code>` for global categories */
  code: string | null;
  name: string | null;
  icon: string;
  sort_order: number;
}

export interface Ingredient {
  id: number;
  name: string;
  default_unit: Unit;
  shopping_category_id: number;
  is_custom: boolean;
}

export interface RecipeIngredient {
  id: number;
  ingredient_id: number;
  name: string;
  quantity: number | null;
  unit: Unit;
  note: string | null;
}

export interface RecipeSummary {
  id: number;
  title: string;
  image_url: string | null;
  tags: RecipeTag[];
  is_favorite: boolean;
  prep_minutes: number | null;
}

export interface Recipe extends RecipeSummary {
  servings: number;
  instructions: string | null;
  ingredients: RecipeIngredient[];
}

export interface RecipeInput {
  title?: string;
  servings?: number;
  prep_minutes?: number | null;
  instructions?: string | null;
  tags?: RecipeTag[];
  is_favorite?: boolean;
  ingredients?: {
    ingredient_id?: number;
    name?: string;
    quantity?: number | null;
    unit: Unit;
    note?: string | null;
  }[];
}

export type MealEntryStatus = 'planned' | 'proposed' | 'rejected';

export interface MealEntry {
  id: number;
  recipe_id: number | null;
  recipe: RecipeSummary | null;
  free_text: string | null;
  title: string;
  status: MealEntryStatus;
  proposed_by: ChildProfileCard | null;
  votes: { up: number; down: number; mine: -1 | 0 | 1 };
}

export interface MealSlot {
  id: number | null;
  day_index: number;
  date: string;
  meal_type: MealType;
  servings: number;
  entries: MealEntry[];
}

export interface MealPlan {
  id: number;
  week_start: string;
  notes: string | null;
  meal_types: MealType[];
  slots: MealSlot[];
}

export interface MealTemplate {
  id: number;
  name: string;
  dishes: number;
}

export interface ShoppingItem {
  id: number;
  ingredient_id: number | null;
  label: string;
  quantity: number | null;
  unit: string | null;
  category_id: number;
  source: 'manual' | 'generated';
  checked: boolean;
  checked_at: string | null;
  client_uuid: string | null;
}

export interface ShoppingListSummary {
  id: number;
  name: string;
  is_default: boolean;
  version: number;
  items_count: number;
  open_count: number;
}

export interface ShoppingList {
  id: number;
  name: string;
  is_default: boolean;
  version: number;
  linked_week_id: number | null;
  items: ShoppingItem[];
  pantry: number[];
}

export interface ShoppingItemInput {
  label?: string;
  ingredient_id?: number;
  quantity?: number | null;
  unit?: string | null;
  category_id?: number;
  client_uuid?: string;
}

export type SyncOp =
  | { op: 'add'; client_uuid: string; data: ShoppingItemInput; at: string }
  | { op: 'check' | 'uncheck' | 'delete'; client_uuid?: string; item_id?: number; at: string };

export interface FavoriteItem {
  id: number;
  label: string;
  ingredient_id: number | null;
  category_id: number;
  use_count: number;
}

export interface ChildStats {
  child: ChildProfileCard;
  tasks: { approved: number; missed: number; rejected: number; submitted: number; pending: number };
  completion_rate: number | null;
  points_earned: number;
  points_spent: number;
  current_streak: number;
  best_streak: number;
  per_day: { date: string; approved: number; missed: number }[];
}

export interface Stats {
  from: string;
  to: string;
  children: ChildStats[];
}

export interface Health {
  status: 'ok' | 'degraded';
  db: boolean;
  time: string;
}
