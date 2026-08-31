import { useContext } from 'react'

import { PrivacyContext, type PrivacyContextValue } from './privacy-context'

export function usePrivacy(): PrivacyContextValue {
  const context = useContext(PrivacyContext)

  if (context === null) {
    throw new Error('usePrivacy precisa estar dentro de um PrivacyProvider.')
  }

  return context
}
