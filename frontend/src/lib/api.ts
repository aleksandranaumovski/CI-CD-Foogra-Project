import axios, { AxiosError, type AxiosInstance } from 'axios'
import type { ApiError } from './types'

const TOKEN_KEY = 'foogra.token'

export const tokenStore = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

/**
 * In dev the Vite proxy forwards /api to the Laravel server, so a relative
 * baseURL keeps everything same-origin. Set VITE_API_URL for a deployed build.
 */
export const api: AxiosInstance = axios.create({
  baseURL: `${import.meta.env.VITE_API_URL ?? ''}/api/v1`,
  headers: { Accept: 'application/json' },
  withCredentials: true,
})

api.interceptors.request.use((config) => {
  const token = tokenStore.get()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    /*
     * A 401 means the stored token is gone or expired. Drop it so the UI falls
     * back to the signed-out state instead of retrying with a dead credential.
     * The redirect is left to the router, not forced here.
     */
    if (error.response?.status === 401) {
      tokenStore.clear()
    }

    return Promise.reject(error)
  },
)

/** Normalises any thrown value into the API's error envelope. */
export function toApiError(error: unknown): ApiError {
  if (axios.isAxiosError(error)) {
    const err = error as AxiosError<ApiError>

    return {
      message: err.response?.data?.message ?? err.message ?? 'Something went wrong.',
      errors: err.response?.data?.errors,
      status: err.response?.status,
    }
  }

  return { message: error instanceof Error ? error.message : 'Something went wrong.' }
}

/**
 * Shown when a user has no avatar of their own. The template's `avatar*.jpg`
 * files are grey "IMAGE PLACEHOLDER" panels; `avatar_*.svg` are its actual
 * illustrated portraits, so the fallback uses one of those.
 */
export const AVATAR_FALLBACK = '/img/avatar_1.svg'

/** First validation message for a field, if the API returned one. */
export function fieldError(error: ApiError | null, field: string): string | undefined {
  return error?.errors?.[field]?.[0]
}

/**
 * Serialises filters into a query string Laravel understands, dropping empty
 * values and expanding arrays into `categories[]=a&categories[]=b`.
 */
export function toQuery(params: object): string {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '' || value === false) continue

    if (Array.isArray(value)) {
      if (value.length === 0) continue
      value.forEach((item) => search.append(`${key}[]`, String(item)))
    } else if (value === true) {
      search.append(key, '1')
    } else {
      search.append(key, String(value))
    }
  }

  return search.toString()
}
