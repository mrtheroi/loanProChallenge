import { ApiError, apiFetch } from '@/shared/api/http'

export type Role = 'admin' | 'buyer'

export type User = {
  id: number
  name: string
  email: string
  role: Role
}

export type Credentials = { email: string; password: string }

export type Registration = {
  name: string
  email: string
  password: string
  password_confirmation: string
}

type UserEnvelope = { data: User }

export async function fetchCurrentUser(): Promise<User | null> {
  try {
    return (await apiFetch<UserEnvelope>('/api/auth/me')).data
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return null
    throw error
  }
}

export async function login(credentials: Credentials): Promise<User> {
  return (await apiFetch<UserEnvelope>('/api/auth/login', { method: 'POST', body: credentials })).data
}

export async function register(registration: Registration): Promise<User> {
  return (await apiFetch<UserEnvelope>('/api/auth/register', { method: 'POST', body: registration })).data
}

export async function logout(): Promise<void> {
  await apiFetch('/api/auth/logout', { method: 'POST' })
}
