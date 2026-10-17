const GENERIC_ERROR = 'Something went wrong. Please try again.'
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS'])
const CSRF_MISMATCH = 419

export type FieldErrors = Record<string, string[]>

export class ApiError extends Error {
  readonly status: number
  readonly errors: FieldErrors
  readonly body: unknown

  constructor(status: number, message: string, errors: FieldErrors = {}, body: unknown = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.body = body
  }
}

type RequestOptions = {
  method?: string
  body?: unknown
  headers?: Record<string, string>
}

function readCookie(name: string): string | null {
  const match = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${name}=`))

  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}

async function ensureCsrfCookie(refresh = false): Promise<string | null> {
  if (refresh || readCookie('XSRF-TOKEN') === null) {
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  }

  return readCookie('XSRF-TOKEN')
}

async function parseBody(response: Response): Promise<unknown> {
  const text = await response.text()
  if (text === '') return undefined

  try {
    return JSON.parse(text) as unknown
  } catch {
    return null
  }
}

function toApiError(status: number, body: unknown): ApiError {
  if (body !== null && typeof body === 'object') {
    const { message, errors } = body as { message?: unknown; errors?: unknown }

    return new ApiError(
      status,
      typeof message === 'string' && message !== '' ? message : GENERIC_ERROR,
      errors !== null && typeof errors === 'object' ? (errors as FieldErrors) : {},
      body,
    )
  }

  return new ApiError(status, GENERIC_ERROR)
}

export async function apiFetch<T = unknown>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase()
  const mutation = !SAFE_METHODS.has(method)

  let body: BodyInit | undefined
  const baseHeaders: Record<string, string> = { Accept: 'application/json', ...options.headers }
  if (options.body instanceof FormData) {
    body = options.body
  } else if (options.body !== undefined) {
    baseHeaders['Content-Type'] = 'application/json'
    body = JSON.stringify(options.body)
  }

  const send = async (refreshCsrf: boolean): Promise<Response> => {
    const headers = { ...baseHeaders }
    if (mutation) {
      const token = await ensureCsrfCookie(refreshCsrf)
      if (token !== null) headers['X-XSRF-TOKEN'] = token
    }

    return fetch(path, { method, headers, body, credentials: 'same-origin' })
  }

  let response = await send(false)
  if (mutation && response.status === CSRF_MISMATCH) {
    response = await send(true)
  }

  const parsed = await parseBody(response)

  if (!response.ok) throw toApiError(response.status, parsed)

  return parsed as T
}
