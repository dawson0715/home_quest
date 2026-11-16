import { api } from '../client';
import type {
  BadgeStatus,
  Child,
  EarnedBadge,
  PointTransaction,
  Redemption,
  Reward,
  RewardInput,
  SavingsGoal,
  ShopReward
} from '../types';
import type { Envelope } from '../types';

// Parent side
export async function listRewards(): Promise<Reward[]> {
  return (await api.get<Reward[]>('rewards')).data;
}

export async function createReward(input: RewardInput): Promise<Reward> {
  return (await api.post<Reward>('rewards', input)).data;
}

export async function updateReward(id: number, input: RewardInput): Promise<Reward> {
  return (await api.patch<Reward>(`rewards/${id}`, input)).data;
}

export async function deleteReward(id: number): Promise<void> {
  await api.delete(`rewards/${id}`);
}

export async function listRedemptions(status?: string): Promise<Redemption[]> {
  return (await api.get<Redemption[]>(status ? `redemptions?status=${status}` : 'redemptions'))
    .data;
}

export async function approveRedemption(
  id: number,
  note?: string
): Promise<{ redemption: Redemption; new_badges: EarnedBadge[] }> {
  return (
    await api.post<{ redemption: Redemption; new_badges: EarnedBadge[] }>(
      `redemptions/${id}/approve`,
      { note }
    )
  ).data;
}

export async function rejectRedemption(id: number, note?: string): Promise<Redemption> {
  return (await api.post<Redemption>(`redemptions/${id}/reject`, { note })).data;
}

export async function fulfillRedemption(id: number): Promise<Redemption> {
  return (await api.post<Redemption>(`redemptions/${id}/fulfill`)).data;
}

export async function listChildBadges(childId: number): Promise<BadgeStatus[]> {
  return (await api.get<BadgeStatus[]>(`badges?child_id=${childId}`)).data;
}

export async function listTransactions(
  childId: number,
  page = 1
): Promise<Envelope<PointTransaction[]>> {
  return api.get<PointTransaction[]>(`children/${childId}/transactions?page=${page}`);
}

export async function adjustPoints(
  childId: number,
  input: { type: 'bonus' | 'penalty'; points: number; xp?: number; note: string }
): Promise<{ child: Child; transaction: PointTransaction; level_up: boolean }> {
  return (
    await api.post<{ child: Child; transaction: PointTransaction; level_up: boolean }>(
      `children/${childId}/adjust`,
      input
    )
  ).data;
}

// Player side: kids pass their child token; parents (playing too) omit it
export async function listShop(childToken?: string): Promise<ShopReward[]> {
  return (await api.get<ShopReward[]>('rewards?view=shop', childToken ? { token: childToken } : {}))
    .data;
}

export async function redeemReward(
  childToken: string | undefined,
  rewardId: number
): Promise<Redemption> {
  return (
    await api.post<Redemption>(
      `rewards/${rewardId}/redeem`,
      {},
      childToken ? { token: childToken } : {}
    )
  ).data;
}

export async function listMyRedemptions(childToken: string): Promise<Redemption[]> {
  return (await api.get<Redemption[]>('redemptions', { token: childToken })).data;
}

export async function setSavingsGoal(
  childToken: string,
  rewardId: number | null
): Promise<SavingsGoal | null> {
  return (
    await api.put<{ savings_goal: SavingsGoal | null }>(
      'me/savings-goal',
      { reward_id: rewardId },
      { token: childToken }
    )
  ).data.savings_goal;
}

export async function listMyBadges(childToken: string): Promise<BadgeStatus[]> {
  return (await api.get<BadgeStatus[]>('badges', { token: childToken })).data;
}
