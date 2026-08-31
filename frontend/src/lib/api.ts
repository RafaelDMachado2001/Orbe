import axios, { AxiosError, type AxiosInstance } from 'axios'

import type { ApiValidationError } from '@/types/api'

const TOKEN_KEY = 'verso.token'

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function storeToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearStoredToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

/**
 * Cliente unico da API. Todo dado da tela vem daqui: o frontend nao calcula
 * saldo, projecao nem agregacao — apenas recebe e desenha.
 */
export const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = getStoredToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

/** Evento emitido quando a API recusa o token, para o app voltar ao login. */
export const UNAUTHORIZED_EVENT = 'verso:unauthorized'

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      clearStoredToken()
      window.dispatchEvent(new CustomEvent(UNAUTHORIZED_EVENT))
    }

    return Promise.reject(error)
  },
)

/** Extrai a mensagem util de um erro da API para exibir na interface. */
export function apiErrorMessage(error: unknown, fallback = 'Não foi possível concluir a ação.'): string {
  if (!axios.isAxiosError(error)) {
    return fallback
  }

  const data = error.response?.data as ApiValidationError | undefined

  if (data?.errors) {
    const first = Object.values(data.errors)[0]

    if (first && first.length > 0) {
      return first[0] as string
    }
  }

  if (error.code === 'ERR_NETWORK') {
    return 'Não conseguimos falar com o servidor. Verifique se a API está no ar.'
  }

  return data?.message ?? fallback
}

/** Erros de validacao por campo, para marcar os inputs do formulario. */
export function apiFieldErrors(error: unknown): Record<string, string> {
  if (!axios.isAxiosError(error)) {
    return {}
  }

  const data = error.response?.data as ApiValidationError | undefined

  if (!data?.errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(data.errors).map(([field, messages]) => [field, messages[0] ?? '']),
  )
}
