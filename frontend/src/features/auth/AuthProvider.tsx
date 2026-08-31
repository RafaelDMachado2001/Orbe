import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'

import { clearStoredToken, getStoredToken, UNAUTHORIZED_EVENT } from '@/lib/api'
import type { User } from '@/types/api'

import * as authApi from './api'
import { AuthContext, type AuthContextValue } from './auth-context'

/**
 * Sessao do usuario. O token fica no localStorage e a identidade e sempre
 * reconfirmada com a API no boot — o frontend nunca decide sozinho quem esta
 * logado.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true

    async function restoreSession() {
      if (!getStoredToken()) {
        setIsLoading(false)

        return
      }

      try {
        const current = await authApi.fetchCurrentUser()

        if (active) {
          setUser(current)
        }
      } catch {
        clearStoredToken()
      } finally {
        if (active) {
          setIsLoading(false)
        }
      }
    }

    void restoreSession()

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    function handleUnauthorized() {
      setUser(null)
    }

    window.addEventListener(UNAUTHORIZED_EVENT, handleUnauthorized)

    return () => window.removeEventListener(UNAUTHORIZED_EVENT, handleUnauthorized)
  }, [])

  const login = useCallback(async (payload: authApi.LoginPayload) => {
    const response = await authApi.login(payload)
    setUser(response.user)
  }, [])

  const register = useCallback(async (payload: authApi.RegisterPayload) => {
    const response = await authApi.register(payload)
    setUser(response.user)
  }, [])

  const logout = useCallback(async () => {
    await authApi.logout()
    setUser(null)
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      isLoading,
      isAuthenticated: user !== null,
      login,
      register,
      logout,
    }),
    [user, isLoading, login, register, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
