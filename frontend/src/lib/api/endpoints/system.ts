import { api } from '../client';
import type { Health } from '../types';

export async function getHealth(): Promise<Health> {
  return (await api.get<Health>('health')).data;
}
