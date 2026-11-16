import { api } from '../client';
import type { Child, ChildInput } from '../types';

export async function listChildren(archived = false): Promise<Child[]> {
  return (await api.get<Child[]>(archived ? 'children?archived=1' : 'children')).data;
}

/** Every player: children + the parents' game profiles (task assignees, stats). */
export async function listPlayers(): Promise<Child[]> {
  return (await api.get<Child[]>('children?players=1')).data;
}

export async function getChild(id: number): Promise<Child> {
  return (await api.get<Child>(`children/${id}`)).data;
}

export async function createChild(input: ChildInput): Promise<Child> {
  return (await api.post<Child>('children', input)).data;
}

export async function updateChild(id: number, input: Partial<ChildInput>): Promise<Child> {
  return (await api.patch<Child>(`children/${id}`, input)).data;
}

export async function archiveChild(id: number): Promise<void> {
  await api.delete(`children/${id}`);
}

export async function setChildPin(id: number, pin: string | null): Promise<Child> {
  return (await api.put<Child>(`children/${id}/pin`, { pin })).data;
}
