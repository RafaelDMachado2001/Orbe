import { useContext } from 'react'

import { AuthContext, type AuthContextValue } from './auth-context'

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (context === null) {
    throw new Error('useAuth precisa estar dentro de um AuthProvider.')
  }

  return context
}
