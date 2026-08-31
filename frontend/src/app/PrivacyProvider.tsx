import { useEffect, useMemo, useState, type ReactNode } from 'react'

import { PrivacyContext, type PrivacyContextValue } from './privacy-context'

const STORAGE_KEY = 'orbe.privacy'

/**
 * Modo privacidade: borra todos os valores da tela. Atalho Shift+H, para
 * esconder a tela rapidamente sem sair do app.
 */
export function PrivacyProvider({ children }: { children: ReactNode }) {
  const [isPrivate, setIsPrivate] = useState(() => localStorage.getItem(STORAGE_KEY) === '1')

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, isPrivate ? '1' : '0')
  }, [isPrivate])

  useEffect(() => {
    function handleKey(event: KeyboardEvent) {
      const target = event.target as HTMLElement | null
      const isTyping =
        target?.tagName === 'INPUT' || target?.tagName === 'TEXTAREA' || target?.isContentEditable

      if (isTyping || !event.shiftKey) {
        return
      }

      if (event.key === 'H' || event.key === 'h') {
        event.preventDefault()
        setIsPrivate((current) => !current)
      }
    }

    window.addEventListener('keydown', handleKey)

    return () => window.removeEventListener('keydown', handleKey)
  }, [])

  const value = useMemo<PrivacyContextValue>(
    () => ({ isPrivate, toggle: () => setIsPrivate((current) => !current) }),
    [isPrivate],
  )

  return <PrivacyContext.Provider value={value}>{children}</PrivacyContext.Provider>
}
