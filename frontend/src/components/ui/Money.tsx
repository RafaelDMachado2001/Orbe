import { usePrivacy } from '@/app/usePrivacy'
import { cn } from '@/lib/cn'
import { formatBRL, splitBRL } from '@/lib/format'

interface MoneyProps {
  value: number | null | undefined
  className?: string
  /** Exibe os centavos em corpo menor, como nos KPIs da visao geral. */
  emphasizeCents?: boolean
  centsClassName?: string
}

/**
 * Todo valor monetario da interface passa por aqui: fonte mono, sem quebra de
 * linha e sujeito ao modo privacidade.
 */
export function Money({ value, className, emphasizeCents = false, centsClassName }: MoneyProps) {
  const { isPrivate } = usePrivacy()

  const base = cn('font-mono whitespace-nowrap tabular-nums', isPrivate && 'privacy-blur', className)

  if (!emphasizeCents) {
    return <span className={base}>{formatBRL(value)}</span>
  }

  const { integer, cents } = splitBRL(value)

  return (
    <span className={base}>
      {integer}
      <span className={cn('text-[17px]', centsClassName ?? 'text-ink-muted')}>,{cents}</span>
    </span>
  )
}
