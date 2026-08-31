import { AlertTriangle, Inbox, RefreshCw } from 'lucide-react'
import type { ReactNode } from 'react'

import { Button } from './Button'

interface EmptyStateProps {
  title: string
  description: string
  action?: ReactNode
}

export function EmptyState({ title, description, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-[14px] border border-dashed border-hairline px-6 py-10 text-center">
      <Inbox className="size-6 text-ink-faint" aria-hidden="true" />
      <div>
        <p className="text-[13px] font-semibold text-ink">{title}</p>
        <p className="mt-1 max-w-[36ch] text-[11.5px] leading-relaxed text-ink-muted">{description}</p>
      </div>
      {action}
    </div>
  )
}

interface ErrorStateProps {
  title?: string
  description: string
  onRetry?: () => void
}

export function ErrorState({ title = 'Algo deu errado', description, onRetry }: ErrorStateProps) {
  return (
    <div
      role="alert"
      className="flex flex-col items-center gap-3 rounded-[14px] border border-orange/25 bg-orange/[0.06] px-6 py-8 text-center"
    >
      <AlertTriangle className="size-6 text-orange" aria-hidden="true" />
      <div>
        <p className="text-[13px] font-semibold text-ink">{title}</p>
        <p className="mt-1 max-w-[42ch] text-[11.5px] leading-relaxed text-ink-soft">{description}</p>
      </div>
      {onRetry ? (
        <Button variant="ghost" onClick={onRetry}>
          <RefreshCw className="size-3.5" aria-hidden="true" />
          Tentar de novo
        </Button>
      ) : null}
    </div>
  )
}
