import type { User } from './api/authApi'

const ORIGIN = 'http://same-site.invalid'

export function safeRedirect(target: string | null): string | null {
  if (target === null || !target.startsWith('/') || hasUnsafeCharacter(target)) return null

  const url = new URL(target, ORIGIN)
  if (url.origin !== ORIGIN) return null

  return url.pathname + url.search + url.hash
}

function hasUnsafeCharacter(value: string): boolean {
  return [...value].some((character) => character === '\\' || character.charCodeAt(0) < 0x20 || character.charCodeAt(0) === 0x7f)
}

export function homeFor(user: User): string {
  return user.role === 'admin' ? '/admin' : '/'
}
