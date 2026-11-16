import { api } from '../client';
import type { Task, TaskInput, TaskTemplate } from '../types';

export async function listTasks(
  params: { childId?: number; active?: boolean } = {}
): Promise<Task[]> {
  const q = new URLSearchParams();
  if (params.childId !== undefined) q.set('child_id', String(params.childId));
  if (params.active !== undefined) q.set('active', params.active ? '1' : '0');
  const qs = q.toString();
  return (await api.get<Task[]>(qs ? `tasks?${qs}` : 'tasks')).data;
}

export async function getTask(id: number): Promise<Task> {
  return (await api.get<Task>(`tasks/${id}`)).data;
}

export async function createTask(input: TaskInput): Promise<Task> {
  return (await api.post<Task>('tasks', input)).data;
}

export async function updateTask(id: number, input: TaskInput): Promise<Task> {
  return (await api.patch<Task>(`tasks/${id}`, input)).data;
}

export async function deleteTask(id: number): Promise<void> {
  await api.delete(`tasks/${id}`);
}

export async function listTaskTemplates(age?: number): Promise<TaskTemplate[]> {
  return (
    await api.get<TaskTemplate[]>(
      age === undefined ? 'task-templates' : `task-templates?age=${age}`
    )
  ).data;
}
