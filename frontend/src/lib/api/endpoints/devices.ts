import { api } from '../client';
import type {
  ChildMe,
  ChildProfileCard,
  Device,
  DeviceMe,
  PairResult,
  PairingCode,
  SelectChildResult
} from '../types';

// Parent side
export async function createPairingCode(): Promise<PairingCode> {
  return (await api.post<PairingCode>('devices/pairing-code')).data;
}

export async function listDevices(): Promise<Device[]> {
  return (await api.get<Device[]>('devices')).data;
}

export async function revokeDevice(id: number): Promise<void> {
  await api.delete(`devices/${id}`);
}

// Device side: explicit tokens (the device has no parent session)
export async function pairDevice(code: string, deviceName: string): Promise<PairResult> {
  return (
    await api.post<PairResult>('devices/pair', { code, device_name: deviceName }, { token: null })
  ).data;
}

export async function getDeviceMe(deviceToken: string): Promise<DeviceMe> {
  return (await api.get<DeviceMe>('me', { token: deviceToken })).data;
}

export async function listProfiles(deviceToken: string): Promise<ChildProfileCard[]> {
  return (await api.get<ChildProfileCard[]>('devices/profiles', { token: deviceToken })).data;
}

export async function selectChild(
  deviceToken: string,
  childId: number,
  pin?: string
): Promise<SelectChildResult> {
  return (
    await api.post<SelectChildResult>(
      'devices/select-child',
      { child_id: childId, pin },
      { token: deviceToken }
    )
  ).data;
}

export async function getChildMe(childToken: string): Promise<ChildMe> {
  return (await api.get<ChildMe>('me', { token: childToken })).data;
}
