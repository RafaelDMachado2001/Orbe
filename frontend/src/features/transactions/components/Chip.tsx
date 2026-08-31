import type { ReactNode } from 'react'

import { cn } from '@/lib/cn'

interface ChipProps {
  isActive: boolean
  onClick: () => void
  children: ReactNode
  /** Cor de destaque quando ativo; sem ela, o chip usa o cinza padrão. */
  accent?: string
  title?: string
}

/** Filtro de um clique. Usa aria-pressed porque alterna, não navega. */
export function Chip({ isActive, onClick, children, accent, title }: ChipProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={isActive}
      title={title}
      style={
        isActive && accent
          ? { color: accent, background: `${accent}1F`, borderColor: `${accent}45` }
          : undefined
      }
      className={cn(
        'rounded-[9px] border px-[11px] py-[5px] text-[11.5px] transition-colors',
        isActive
          ? 'border-hairline-strong bg-white/[0.08] font-bold text-ink'
          : 'border-hairline bg-transparent font-semibold text-ink-dim hover:border-hairline-strong hover:text-ink',
      )}
    >
      {children}
    </button>
  )
}
