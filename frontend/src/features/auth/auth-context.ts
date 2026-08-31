import { createContext } from 'react'

import type { User } from '@/types/api'

import type { LoginPayload, RegisterPayload } from './api'

export interface AuthContextValue {
  user: User | null
  isLoading: boolean
  isAuthenticated: boolean
  login: (payload: LoginPayload) => Promise<void>
  register: (payload: RegisterPayload) => Promise<void>
  logout: () => Promise<void>
}

/**
 * O contexto vive em um arquivo proprio para que AuthProvider.tsx exporte
 * apenas componentes — condicao para o fast refresh do Vite funcionar.
 */
export const AuthContext = createContext<AuthContextValue | null>(null)
