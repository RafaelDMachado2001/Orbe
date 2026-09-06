import { LogOut, Menu } from 'lucide-react'
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
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)

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
      {isSidebarOpen ? (
        <div
          className="fixed inset-0 z-30 bg-black/60 lg:hidden"
          onClick={() => setIsSidebarOpen(false)}
          aria-hidden="true"
        />
      ) : null}

      <Sidebar
        cardsCount={cardsCount}
        commitmentRate={commitmentRate}
        nextMonthLabel={nextMonthLabel}
        isOpen={isSidebarOpen}
        onClose={() => setIsSidebarOpen(false)}
      />

      <div className="flex min-w-0 flex-1 flex-col">
        <div className="flex items-center justify-between gap-2 px-4 pt-5 sm:px-6 lg:justify-end lg:px-[30px]">
          <button
            type="button"
            onClick={() => setIsSidebarOpen(true)}
            aria-label="Abrir menu"
            className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-surface-raised text-ink-soft transition-colors hover:text-ink lg:hidden"
          >
            <Menu className="size-4.5" aria-hidden="true" />
          </button>

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

        <main className="flex min-w-0 flex-1 flex-col gap-5 px-4 pb-10 pt-[18px] sm:px-6 lg:px-[30px]">
          {children}
        </main>
      </div>
    </div>
  )
}
