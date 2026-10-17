import type { User } from './api/authApi'

export function safeRedirect(target: string | null): string | null {
  if (target === null || !target.startsWith('/') || target.startsWith('//') || target.startsWith('/\\')) return null

  return target
}

export function homeFor(user: User): string {
  return user.role === 'admin' ? '/admin' : '/'
}
