import { api } from '../client';
import type { BulkApproveResult, Occurrence, Today, TransitionResult } from '../types';

// Parent side
export async function listOccurrences(
  params: {
    date?: string;
    childId?: number;
    status?: string;
    sort?: 'recent';
    limit?: number;
  } = {}
): Promise<Occurrence[]> {
  const q = new URLSearchParams();
  if (params.date) q.set('date', params.date);
  if (params.childId !== undefined) q.set('child_id', String(params.childId));
  if (params.status) q.set('status', params.status);
  if (params.sort) q.set('sort', params.sort);
  if (params.limit !== undefined) q.set('limit', String(params.limit));
  const qs = q.toString();
  return (await api.get<Occurrence[]>(qs ? `occurrences?${qs}` : 'occurrences')).data;
}

export async function approveOccurrence(id: number, note?: string): Promise<TransitionResult> {
  return (await api.post<TransitionResult>(`occurrences/${id}/approve`, { note })).data;
}

export async function rejectOccurrence(id: number, note?: string): Promise<Occurrence> {
  return (await api.post<Occurrence>(`occurrences/${id}/reject`, { note })).data;
}

export async function bulkApprove(ids: number[]): Promise<BulkApproveResult> {
  return (await api.post<BulkApproveResult>('occurrences/bulk-approve', { ids })).data;
}

// Player side: kids pass their child token; parents (playing too) pass undefined
const as = (token?: string) => (token ? { token } : {});

export async function getToday(childToken?: string): Promise<Today> {
  return (await api.get<Today>('me/today', as(childToken))).data;
}

export async function setChecklistItem(
  childToken: string | undefined,
  id: number,
  itemId: number,
  done: boolean
): Promise<Occurrence> {
  return (
    await api.patch<Occurrence>(
      `occurrences/${id}/checklist`,
      { item_id: itemId, done },
      as(childToken)
    )
  ).data;
}

export async function submitOccurrence(
  childToken: string | undefined,
  id: number
): Promise<TransitionResult> {
  return (await api.post<TransitionResult>(`occurrences/${id}/submit`, {}, as(childToken))).data;
}
