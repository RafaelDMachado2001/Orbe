import { CheckCircle2, Lock, Undo2 } from 'lucide-react'

import { usePrivacy } from '@/app/usePrivacy'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/ui/States'
import { cn } from '@/lib/cn'
import { capitalize, formatCompetence, formatDate, formatDayMonth } from '@/lib/format'
import type { InvoiceDetail, InvoiceStatus } from '@/types/api'

const statusTone: Record<InvoiceStatus, string> = {
  aberta: 'bg-purple/[0.14] text-purple-light',
  fechada: 'bg-orange/[0.13] text-orange-light',
  paga: 'bg-green/[0.12] text-green-bright',
}

interface Props {
  invoice: InvoiceDetail
  isRefreshing: boolean
  isBusy: boolean
  onPay: () => void
  onClose: () => void
  onUndo: () => void
}

/**
 * A fatura aberta: cabeçalho, o que a compõe e o que já foi pago.
 *
 * O que pode ser feito vem da API (`can_pay`, `can_close`, `can_undo`), e não
 * de uma cópia da regra aqui — do contrário a tela ofereceria um botão que o
 * domínio recusaria.
 *
 * As faturas de meses passados chegam aqui já fechadas: o fechamento
 * retroativo é convenção, não clique. O botão só aparece na fatura aberta, e
 * quando a data de corte ainda não chegou ele diz que vai antecipá-la.
 */
export function InvoicePanel({ invoice, isRefreshing, isBusy, onPay, onClose, onUndo }: Props) {
  const { isPrivate } = usePrivacy()

  return (
    <Card className={cn('flex flex-col gap-4 transition-opacity', isRefreshing && 'opacity-60')}>
      <header className="flex flex-wrap items-start gap-4">
        <div className="flex flex-col gap-1.5">
          <div className="flex items-center gap-2.5">
            <h2 className="text-[15px] font-bold tracking-[-0.2px]">
              Fatura de {capitalize(formatCompetence(invoice.reference_month))}
            </h2>
            <span
              className={cn(
                'rounded-full px-[9px] py-[3px] text-[10.5px] font-bold',
                statusTone[invoice.status],
              )}
            >
              {invoice.status_label}
            </span>
          </div>
          <p className="text-[11.5px] font-medium text-ink-dim">
            {invoice.card} · fecha {formatDate(invoice.closing_date)} · vence{' '}
            {formatDate(invoice.due_date)}
          </p>
        </div>

        <div className="ml-auto flex items-center gap-2">
          {invoice.can_close ? (
            <Button
              variant="ghost"
              onClick={onClose}
              disabled={isBusy}
              title={
                invoice.closes_early
                  ? `Antecipa o corte: esta fatura só fecharia em ${formatDate(invoice.closing_date)}, e as compras a partir de agora caem na próxima.`
                  : 'Congela o total da fatura; as compras novas passam para a próxima.'
              }
            >
              <Lock className="size-3.5" aria-hidden="true" />
              {invoice.closes_early ? 'Fechar agora' : 'Fechar fatura'}
            </Button>
          ) : null}

          {invoice.can_undo ? (
            <Button variant="ghost" onClick={onUndo} disabled={isBusy}>
              <Undo2 className="size-3.5" aria-hidden="true" />
              Desfazer pagamento
            </Button>
          ) : null}

          {invoice.can_pay ? (
            <Button onClick={onPay} disabled={isBusy}>
              <CheckCircle2 className="size-3.5" aria-hidden="true" />
              Pagar fatura
            </Button>
          ) : null}
        </div>
      </header>

      <div className="grid grid-cols-3 gap-3 rounded-[13px] border border-hairline bg-surface-alt p-3.5">
        <Figure label="Total" value={invoice.total} />
        <Figure label="Pago" value={invoice.paid_amount} accent="text-green-bright" />
        <Figure
          label="Falta pagar"
          value={invoice.remaining}
          accent={invoice.remaining > 0 ? 'text-[#FF9E5C]' : 'text-ink-muted'}
        />
      </div>

      {invoice.items.length === 0 ? (
        <EmptyState
          title="Fatura sem lançamentos"
          description="Nenhuma compra caiu nesta fatura. Compras a partir do dia de fechamento entram na fatura seguinte."
        />
      ) : (
        <>
          <div className="flex flex-col md:hidden">
            {invoice.items.map((item, index) => (
              <InvoiceItemCard
                key={item.id}
                item={item}
                isPrivate={isPrivate}
                isLast={index === invoice.items.length - 1}
              />
            ))}
          </div>

          <div
            role="table"
            aria-label="Compras da fatura"
            className="hidden text-[12.5px] md:block"
          >
            <div
              role="row"
              className="grid grid-cols-[74px_minmax(0,1.8fr)_130px_70px_110px] gap-3 border-b border-hairline pb-2.5 text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint"
            >
              <span role="columnheader">Compra</span>
              <span role="columnheader">Descrição</span>
              <span role="columnheader">Categoria</span>
              <span role="columnheader">Parcela</span>
              <span role="columnheader" className="text-right">
                Valor
              </span>
            </div>

            {invoice.items.map((item, index) => (
              <div
                key={item.id}
                role="row"
                className={cn(
                  'grid grid-cols-[74px_minmax(0,1.8fr)_130px_70px_110px] items-center gap-3 py-[11px]',
                  index < invoice.items.length - 1 && 'border-b border-hairline-soft',
                  item.is_cancelled && 'opacity-50',
                )}
              >
                <span role="cell" className="font-mono text-[11.5px] tabular-nums text-ink-soft">
                  {formatDayMonth(item.purchase_date)}
                </span>

                <span role="cell" className="min-w-0 truncate font-semibold text-ink">
                  {item.description}
                  {item.is_cancelled ? (
                    <span className="ml-2 rounded bg-white/[0.06] px-1.5 py-px text-[10px] font-bold text-ink-muted">
                      cancelada
                    </span>
                  ) : null}
                </span>

                <span role="cell" className="min-w-0">
                  {item.category ? (
                    <span
                      className="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px] font-semibold"
                      style={{ color: item.category.color, background: `${item.category.color}1F` }}
                    >
                      {item.category.name}
                    </span>
                  ) : (
                    <span className="text-[11px] text-ink-muted">Sem categoria</span>
                  )}
                </span>

                <span
                  role="cell"
                  className="font-mono text-[11.5px] font-semibold tabular-nums text-ink-soft"
                >
                  {item.installment}
                </span>

                <span
                  role="cell"
                  className={cn(
                    'whitespace-nowrap text-right font-mono text-[13px] font-semibold tabular-nums text-[#FF9E5C]',
                    item.is_cancelled && 'line-through',
                    isPrivate && 'privacy-blur',
                  )}
                >
                  <Money value={item.amount} className="text-[13px]" />
                </span>
              </div>
            ))}
          </div>
        </>
      )}

      {invoice.payments.length > 0 ? (
        <section className="rounded-[13px] border border-green/20 bg-green/[0.04] p-3.5">
          <h3 className="mb-2 text-[11px] font-bold uppercase tracking-[0.08em] text-green-bright">
            Pagamentos lançados
          </h3>
          <ul className="flex flex-col gap-1.5">
            {invoice.payments.map((payment) => (
              <li key={payment.id} className="flex items-center gap-3 text-[11.5px]">
                <span className="font-mono tabular-nums text-ink-soft">
                  {formatDate(payment.paid_date)}
                </span>
                <span className="truncate font-medium text-ink-soft">{payment.account}</span>
                <Money
                  value={payment.amount}
                  className="ml-auto text-[12.5px] font-semibold text-green-bright"
                />
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </Card>
  )
}

function InvoiceItemCard({
  item,
  isPrivate,
  isLast,
}: {
  item: InvoiceDetail['items'][number]
  isPrivate: boolean
  isLast: boolean
}) {
  return (
    <div
      className={cn(
        'flex flex-col gap-1.5 py-[11px] text-[12.5px]',
        !isLast && 'border-b border-hairline-soft',
        item.is_cancelled && 'opacity-50',
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <span className="min-w-0 truncate font-semibold text-ink">
          {item.description}
          {item.is_cancelled ? (
            <span className="ml-2 rounded bg-white/[0.06] px-1.5 py-px text-[10px] font-bold text-ink-muted">
              cancelada
            </span>
          ) : null}
        </span>

        <span
          className={cn(
            'shrink-0 whitespace-nowrap font-mono text-[13px] font-semibold tabular-nums text-[#FF9E5C]',
            item.is_cancelled && 'line-through',
            isPrivate && 'privacy-blur',
          )}
        >
          <Money value={item.amount} className="text-[13px]" />
        </span>
      </div>

      <div className="flex flex-wrap items-center gap-1.5 text-[11px]">
        <span className="font-mono tabular-nums text-ink-muted">
          {formatDayMonth(item.purchase_date)}
        </span>

        {item.category ? (
          <span
            className="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] font-semibold"
            style={{ color: item.category.color, background: `${item.category.color}1F` }}
          >
            {item.category.name}
          </span>
        ) : (
          <span className="text-ink-muted">Sem categoria</span>
        )}

        <span className="font-mono font-semibold tabular-nums text-ink-muted">
          {item.installment}
        </span>
      </div>
    </div>
  )
}

function Figure({ label, value, accent }: { label: string; value: number; accent?: string }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
        {label}
      </span>
      <Money value={value} className={cn('text-[16px] font-bold', accent ?? 'text-ink')} />
    </div>
  )
}

export function InvoicePanelSkeleton() {
  return (
    <Card className="flex flex-col gap-4">
      <Skeleton className="h-4 w-52" />
      <Skeleton className="h-[74px] w-full rounded-[13px]" />
      {Array.from({ length: 5 }).map((_, index) => (
        <Skeleton key={index} className="h-[34px] w-full" />
      ))}
    </Card>
  )
}
