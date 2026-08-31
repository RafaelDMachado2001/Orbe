import { Card, CardHeader } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { EmptyState } from '@/components/ui/States'
import { formatBRLCompact, formatDate } from '@/lib/format'
import type { CardSummary } from '@/types/api'

export function CardsPanel({ cards }: { cards: CardSummary[] }) {
  const [highlight, ...rest] = cards

  return (
    <Card>
      <CardHeader
        title="Meus cartões"
        action={<span className="text-[11.5px] font-semibold text-purple">Gerenciar</span>}
        className="mb-4"
      />

      {highlight === undefined ? (
        <EmptyState
          title="Nenhum cartão cadastrado"
          description="Cadastre um cartão de crédito para acompanhar faturas, parcelas e uso do limite."
        />
      ) : (
        <>
          <HighlightCard card={highlight} />

          <div className="mt-[11px] flex flex-col gap-[9px]">
            {rest.map((card) => (
              <CompactCard key={card.id} card={card} />
            ))}
          </div>
        </>
      )}
    </Card>
  )
}

function HighlightCard({ card }: { card: CardSummary }) {
  const invoice = card.current_invoice

  return (
    <article className="relative overflow-hidden rounded-[15px] bg-[linear-gradient(135deg,#2B1B4D_0%,#4A2A7A_55%,#6B3AA8_100%)] p-[17px]">
      <div
        aria-hidden="true"
        className="pointer-events-none absolute -bottom-[50px] -right-[30px] size-[150px] rounded-full bg-[radial-gradient(circle,rgba(255,138,61,0.35),transparent_70%)]"
      />

      <div className="relative flex items-center gap-2.5">
        <span className="text-[12px] font-bold text-[#E4D9FF]">{card.nickname}</span>
        <span className="ml-auto font-mono text-[10.5px] font-bold tracking-[0.08em] text-[#CBB8FF]">
          •••• {card.last_four}
        </span>
      </div>

      <Money
        value={invoice.total}
        className="relative my-3.5 mb-1 block text-[22px] font-semibold tracking-[-0.6px] text-white"
      />

      <p className="relative text-[11px] font-medium text-[#C3B2E8]">
        {invoiceCaption(card)} · limite {formatBRLCompact(card.limit_amount)}
      </p>

      <div className="relative mt-3 h-1 overflow-hidden rounded-[3px] bg-white/[0.16]">
        <div
          className="h-full rounded-[3px] bg-[#FFB072]"
          style={{ width: `${card.usage_percent}%` }}
        />
      </div>
    </article>
  )
}

function CompactCard({ card }: { card: CardSummary }) {
  const initials = card.nickname
    .split(' ')
    .slice(0, 1)
    .map((word) => word.slice(0, 2).toUpperCase())
    .join('')

  return (
    <article className="flex items-center gap-3 rounded-[13px] border border-hairline bg-surface-alt px-3.5 py-3">
      <span
        className="grid size-[30px] shrink-0 place-items-center rounded-[9px] text-[12px] font-extrabold"
        style={{ background: `${card.color}22`, color: card.color }}
      >
        {initials}
      </span>

      <div className="flex min-w-0 flex-col gap-0.5">
        <span className="truncate text-[12.5px] font-semibold">{card.nickname}</span>
        <span className="text-[10.5px] font-medium text-ink-muted">
          •••• {card.last_four} · {compactCaption(card)}
        </span>
      </div>

      <Money
        value={card.current_invoice.total}
        className="ml-auto text-[13.5px] font-semibold text-[#C3C9D2]"
      />
    </article>
  )
}

/** Fatura fechada ja tem data de vencimento; aberta ainda vai fechar. */
function compactCaption(card: CardSummary): string {
  const invoice = card.current_invoice

  return invoice.status === 'aberta'
    ? `fecha ${shortDate(invoice.closing_date)}`
    : `vence ${shortDate(invoice.due_date)}`
}

function shortDate(isoDate: string): string {
  return formatDate(isoDate).slice(0, 5)
}

function invoiceCaption(card: CardSummary): string {
  const invoice = card.current_invoice

  if (invoice.status === 'fechada') {
    return invoice.days_to_due === 0
      ? 'Fatura vence hoje'
      : `Fatura vence em ${invoice.days_to_due} ${plural(invoice.days_to_due)}`
  }

  return invoice.days_to_close === 0
    ? 'Fatura fecha hoje'
    : `Fatura fecha em ${invoice.days_to_close} ${plural(invoice.days_to_close)}`
}

function plural(days: number): string {
  return days === 1 ? 'dia' : 'dias'
}
