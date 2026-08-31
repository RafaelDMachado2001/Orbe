import { createContext } from 'react'

export interface PrivacyContextValue {
  isPrivate: boolean
  toggle: () => void
}

export const PrivacyContext = createContext<PrivacyContextValue | null>(null)
