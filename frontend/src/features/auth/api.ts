import { api, clearStoredToken, storeToken } from '@/lib/api'
import type { AuthResponse, ResourceEnvelope, User } from '@/types/api'

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export async function login(payload: LoginPayload): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/auth/login', {
    ...payload,
    device_name: 'web',
  })

  storeToken(data.token)

  return data
}

export async function register(payload: RegisterPayload): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/auth/register', {
    ...payload,
    device_name: 'web',
  })

  storeToken(data.token)

  return data
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await api.get<ResourceEnvelope<User>>('/auth/me')

  return data.data
}

export async function logout(): Promise<void> {
  try {
    await api.post('/auth/logout')
  } finally {
    clearStoredToken()
  }
}
