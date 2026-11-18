import { api, request } from '../client';
import type { Family, FamilyMember, FamilyUpdate, Me, ParentMe, ProfileUpdate } from '../types';

export async function getMe(): Promise<Me> {
  return (await api.get<Me>('me')).data;
}

/** Parent's own personal data and game profile (avatar, color). */
export async function updateMe(changes: ProfileUpdate): Promise<ParentMe> {
  return (await api.patch<ParentMe>('me', changes)).data;
}

export async function getFamily(): Promise<Family> {
  return (await api.get<Family>('family')).data;
}

export async function updateFamily(changes: FamilyUpdate): Promise<Family> {
  return (await api.patch<Family>('family', changes)).data;
}

export async function getFamilyMembers(): Promise<FamilyMember[]> {
  return (await api.get<FamilyMember[]>('family/members')).data;
}

/** Full data export (admin) as a JSON object. */
export async function exportFamily(): Promise<Record<string, unknown>> {
  return (await api.get<Record<string, unknown>>('family/export')).data;
}

/** Definitive deletion (admin): `confirmName` must equal the family name. */
export async function deleteFamily(confirmName: string): Promise<void> {
  await request('DELETE', 'family', { confirm_name: confirmName });
}
