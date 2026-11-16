import { describe, expect, it, vi } from 'vitest';
import { configureClient, request } from './client';
import { ApiError } from './errors';

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' }
  });
}

describe('request', () => {
  it('unwraps the envelope and sends the bearer token', async () => {
    configureClient({ getToken: () => 'tok' });
    const fetchFn = vi.fn(async () => jsonResponse(200, { data: { status: 'ok' }, meta: {} }));

    const res = await request<{ status: string }>('GET', '/health', undefined, { fetchFn });

    expect(res.data.status).toBe('ok');
    const [url, init] = fetchFn.mock.calls[0] as unknown as [string, RequestInit];
    expect(url).toBe('/api/v1/health');
    expect((init.headers as Record<string, string>).Authorization).toBe('Bearer tok');
  });

  it('throws ApiError with backend codes and field errors', async () => {
    const fetchFn = vi.fn(async () =>
      jsonResponse(422, {
        data: null,
        meta: {
          errors: [
            { code: 'validation_failed', field: 'text', message: 'Text cannot be blank.' },
            { code: 'validation_failed', field: 'list', message: 'List is invalid.' }
          ]
        }
      })
    );

    const err = await request('POST', 'checklist-items', {}, { fetchFn }).catch((e: unknown) => e);

    expect(err).toBeInstanceOf(ApiError);
    const apiErr = err as ApiError;
    expect(apiErr.status).toBe(422);
    expect(apiErr.code).toBe('validation_failed');
    expect(apiErr.fieldErrors).toEqual({
      text: ['validation_failed'],
      list: ['validation_failed']
    });
  });

  it('calls the unauthorized handler on 401', async () => {
    const onUnauthorized = vi.fn();
    configureClient({ onUnauthorized });
    const fetchFn = vi.fn(async () =>
      jsonResponse(401, { data: null, meta: { errors: [{ code: 'unauthorized', message: 'x' }] } })
    );

    await expect(request('GET', 'me', undefined, { fetchFn })).rejects.toMatchObject({
      code: 'unauthorized'
    });
    expect(onUnauthorized).toHaveBeenCalledOnce();
  });

  it('uses an explicit token and does not clear the session on its 401', async () => {
    const onUnauthorized = vi.fn();
    configureClient({ getToken: () => 'session', onUnauthorized });
    const fetchFn = vi.fn(async () =>
      jsonResponse(401, { data: null, meta: { errors: [{ code: 'unauthorized', message: 'x' }] } })
    );

    await expect(
      request('GET', 'devices/profiles', undefined, { token: 'd_x', fetchFn })
    ).rejects.toMatchObject({ status: 401 });
    const init = fetchFn.mock.calls[0] as unknown as [string, RequestInit];
    expect((init[1].headers as Record<string, string>).Authorization).toBe('Bearer d_x');
    expect(onUnauthorized).not.toHaveBeenCalled();
  });

  it('sends If-None-Match and maps 304 to not_modified', async () => {
    const fetchFn = vi.fn(async () => new Response(null, { status: 304 }));
    const res = await request('GET', 'shopping-lists/1', undefined, {
      fetchFn,
      ifNoneMatch: '"v3"'
    });
    expect(res).toEqual({ data: null, meta: { not_modified: true } });
    const init = fetchFn.mock.calls[0] as unknown as [string, RequestInit];
    expect((init[1].headers as Record<string, string>)['If-None-Match']).toBe('"v3"');
  });

  it('maps network failures to network_error', async () => {
    const fetchFn = vi.fn(async () => {
      throw new TypeError('Failed to fetch');
    });

    await expect(request('GET', 'health', undefined, { fetchFn })).rejects.toMatchObject({
      status: 0,
      code: 'network_error'
    });
  });
});
