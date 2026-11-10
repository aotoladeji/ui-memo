import { AxiosAdapter, AxiosError, AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { dispatch, HttpError } from './handlers';

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

/** Replaces the network: every API call is answered in-browser by the mock handlers. */
export const mockAdapter: AxiosAdapter = async (config: InternalAxiosRequestConfig) => {
  await sleep(80);
  const method = (config.method ?? 'get').toLowerCase();
  let body: any = config.data;
  if (typeof body === 'string') { try { body = JSON.parse(body); } catch { /* keep string */ } }
  const auth = String(config.headers?.Authorization ?? config.headers?.get?.('Authorization') ?? '');
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : null;
  const build = (status: number, data: unknown): AxiosResponse => ({ data, status, statusText: String(status), headers: {}, config, request: {} });

  try {
    const out = await dispatch(method, config.url ?? '', config.params ?? {}, body, token);
    return build(out.status, out.data);
  } catch (e) {
    const err = e instanceof HttpError ? e : new HttpError(500, e instanceof Error ? e.message : 'Mock error');
    const res = build(err.status, { message: err.message, errors: err.errors });
    throw new AxiosError(err.message, String(err.status), config, {}, res);
  }
};
