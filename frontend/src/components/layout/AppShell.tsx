import { LogOut } from 'lucide-react'
import { useState, type ReactNode } from 'react'

import { usePrivacy } from '@/app/usePrivacy'
import { useAuth } from '@/features/auth/useAuth'

import { Sidebar } from './Sidebar'

interface AppShellProps {
  children: ReactNode
  cardsCount?: number
  commitmentRate?: number | null
  nextMonthLabel?: string
}

export function AppShell({ children, cardsCount, commitmentRate, nextMonthLabel }: AppShellProps) {
  const { user, logout } = useAuth()
  usePrivacy()
  const [isLeaving, setIsLeaving] = useState(false)

  async function handleLogout() {
    setIsLeaving(true)

    try {
      await logout()
    } finally {
      setIsLeaving(false)
    }
  }

  return (
    <div className="flex min-h-screen bg-app text-ink">
      <Sidebar
        cardsCount={cardsCount}
        commitmentRate={commitmentRate}
        nextMonthLabel={nextMonthLabel}
      />

      <div className="flex min-w-0 flex-1 flex-col">
        <div className="flex items-center justify-end gap-2 px-[30px] pt-5">
          <div className="flex items-center gap-2 rounded-[11px] bg-surface-raised px-3 py-1.5">
            <span className="grid size-7 place-items-center rounded-lg bg-purple/[0.16] text-[11px] font-bold text-purple-light">
              {user?.initials ?? '–'}
            </span>
            <button
              type="button"
              onClick={() => void handleLogout()}
              disabled={isLeaving}
              title="Sair"
              aria-label="Sair da conta"
              className="ml-1 text-ink-muted transition-colors hover:text-orange disabled:opacity-50"
            >
              <LogOut className="size-3.5" aria-hidden="true" />
            </button>
          </div>
        </div>

        <main className="flex min-w-0 flex-1 flex-col gap-5 px-[30px] pb-10 pt-[18px]">
          {children}
        </main>
      </div>
    </div>
  )
}
