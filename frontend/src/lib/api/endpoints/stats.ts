import { api } from '../client';
import type { Stats } from '../types';

export async function getStats(
  params: { from?: string; to?: string; childId?: number } = {}
): Promise<Stats> {
  const q = new URLSearchParams();
  if (params.from) q.set('from', params.from);
  if (params.to) q.set('to', params.to);
  if (params.childId !== undefined) q.set('child_id', String(params.childId));
  const qs = q.toString();
  return (await api.get<Stats>(qs ? `stats?${qs}` : 'stats')).data;
}
