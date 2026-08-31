import { format } from 'date-fns'
import { Plus } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import type { CardDetail } from '@/types/api'

import {
  useArchiveCard,
  useCardInvoices,
  useCardOptions,
  useCards,
  useCloseInvoice,
  useDeleteCard,
  useInvoice,
  useUndoInvoicePayment,
} from './api'
import { CardFormModal } from './components/CardFormModal'
import { CardsSummary, CardsSummarySkeleton } from './components/CardsSummary'
import { CreditCardTile } from './components/CreditCardTile'
import { DeleteCardDialog } from './components/DeleteCardDialog'
import { InvoicePanel, InvoicePanelSkeleton } from './components/InvoicePanel'
import { InvoiceTimeline, InvoiceTimelineSkeleton } from './components/InvoiceTimeline'
import { PayInvoiceModal } from './components/PayInvoiceModal'

/**
 * Cartões e faturas.
 *
 * A tela é uma cascata de três níveis: cartão → fatura → compras que a compõem.
 * A seleção vive em memória e cada nível repõe o seguinte sem navegar: escolher
 * outro cartão troca a linha do tempo, escolher outro mês troca o detalhe, e
 * nada disso recarrega a página.
 */
export function CardsPage() {
  const today = format(new Date(), 'yyyy-MM-dd')
  const month = format(new Date(), 'yyyy-MM')

  const [showArchived, setShowArchived] = useState(false)
  const [selectedCardId, setSelectedCardId] = useState<number | null>(null)
  const [selectedInvoiceId, setSelectedInvoiceId] = useState<number | null>(null)

  const { data, isPending, isFetching, isError, error, refetch } = useCards(month, showArchived)
  const { data: options } = useCardOptions()

  const cards = useMemo(() => data?.cards ?? [], [data])

  // Sem escolha explícita, abre no primeiro cartão. Se o cartão selecionado
  // sumiu (arquivado, excluído), a seleção volta para o primeiro disponível.
  const activeCardId = useMemo(() => {
    if (selectedCardId !== null && cards.some((card) => card.id === selectedCardId)) {
      return selectedCardId
    }

    return cards[0]?.id ?? null
  }, [selectedCardId, cards])

  const { data: invoices, isPending: isLoadingInvoices } = useCardInvoices(activeCardId)

  // Abre na fatura em aberto mais antiga — a que precisa ser paga.
  const activeInvoiceId = useMemo(() => {
    if (selectedInvoiceId !== null && invoices?.some((row) => row.id === selectedInvoiceId)) {
      return selectedInvoiceId
    }

    const outstanding = [...(invoices ?? [])]
      .reverse()
      .find((row) => row.status !== 'paga' && row.total > 0)

    return outstanding?.id ?? invoices?.[0]?.id ?? null
  }, [selectedInvoiceId, invoices])

  const { data: invoice, isPending: isLoadingInvoice, isFetching: isFetchingInvoice } =
    useInvoice(activeInvoiceId)

  // Trocar de cartão zera a fatura escolhida no cartão anterior.
  useEffect(() => {
    setSelectedInvoiceId(null)
  }, [activeCardId])

  const archive = useArchiveCard()
  const remove = useDeleteCard()
  const undoPayment = useUndoInvoicePayment()
  const closeInvoice = useCloseInvoice()

  const { toast, notify, dismiss } = useToast()

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingCardId, setEditingCardId] = useState<number | null>(null)
  const [pendingDeletion, setPendingDeletion] = useState<CardDetail | null>(null)
  const [isPayOpen, setIsPayOpen] = useState(false)

  const isRefreshing = isFetching && !isPending
  const selectedCard = cards.find((card) => card.id === activeCardId) ?? null
  const isInvoiceBusy = undoPayment.isPending || closeInvoice.isPending

  function openCreate() {
    setEditingCardId(null)
    setIsFormOpen(true)
  }

  async function handleArchive(card: CardDetail, isActive: boolean) {
    try {
      await archive.mutateAsync({ id: card.id, isActive })
      notify(isActive ? 'Cartão reativado.' : 'Cartão arquivado.')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível arquivar o cartão.'), 'erro')
    }
  }

  async function handleDelete() {
    if (pendingDeletion === null) {
      return
    }

    try {
      await remove.mutateAsync(pendingDeletion.id)
      notify('Cartão excluído.')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível excluir o cartão.'), 'erro')
    } finally {
      setPendingDeletion(null)
    }
  }

  async function handleUndo() {
    if (invoice === undefined) {
      return
    }

    try {
      await undoPayment.mutateAsync(invoice.id)
      notify('Pagamento desfeito.')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível desfazer o pagamento.'), 'erro')
    }
  }

  async function handleClose() {
    if (invoice === undefined) {
      return
    }

    try {
      await closeInvoice.mutateAsync(invoice.id)
      notify('Fatura fechada.')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível fechar a fatura.'), 'erro')
    }
  }

  return (
    <AppShell cardsCount={data?.summary.active_count}>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Cartões</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">
            {data
              ? `${data.summary.active_count} ${data.summary.active_count === 1 ? 'cartão ativo' : 'cartões ativos'} · o limite volta a ficar livre conforme as faturas são pagas`
              : 'Carregando seus cartões…'}
          </p>
        </div>

        <div className="ml-auto flex items-center gap-2.5">
          <Button variant="ghost" onClick={() => setShowArchived((value) => !value)}>
            {showArchived ? 'Ocultar arquivados' : 'Mostrar arquivados'}
          </Button>
          <Button onClick={openCreate}>
            <Plus className="size-3.5" aria-hidden="true" />
            Novo cartão
          </Button>
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar os cartões.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? <CardsSummarySkeleton /> : null}
      {data ? <CardsSummary summary={data.summary} isRefreshing={isRefreshing} /> : null}

      <section className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[368px_minmax(0,1fr)]">
        <Card className={cn('flex flex-col gap-3 transition-opacity', isRefreshing && 'opacity-60')}>
          <CardHeader
            title="Meus cartões"
            subtitle={cards.length > 0 ? 'Clique para ver as faturas' : undefined}
          />

          {isPending ? (
            <div className="flex flex-col gap-3">
              {Array.from({ length: 3 }).map((_, index) => (
                <Skeleton key={index} className="h-[128px] w-full rounded-[15px]" />
              ))}
            </div>
          ) : cards.length === 0 ? (
            <EmptyState
              title="Nenhum cartão cadastrado"
              description="Cadastre um cartão para acompanhar faturas, parcelas e uso do limite."
              action={
                <Button variant="ghost" onClick={openCreate}>
                  <Plus className="size-3.5" aria-hidden="true" />
                  Novo cartão
                </Button>
              }
            />
          ) : (
            cards.map((card) => (
              <CreditCardTile
                key={card.id}
                card={card}
                isSelected={card.id === activeCardId}
                onSelect={() => setSelectedCardId(card.id)}
                onEdit={() => {
                  setEditingCardId(card.id)
                  setIsFormOpen(true)
                }}
                onArchive={(isActive) => void handleArchive(card, isActive)}
                onDelete={() => setPendingDeletion(card)}
              />
            ))
          )}
        </Card>

        <div className="flex flex-col gap-4">
          {selectedCard ? (
            <Card className="flex flex-col gap-3 p-[18px_20px]">
              <CardHeader
                title={`Faturas · ${selectedCard.nickname}`}
                subtitle={cycleCaption(selectedCard)}
              />

              {isLoadingInvoices ? (
                <InvoiceTimelineSkeleton />
              ) : (
                <InvoiceTimeline
                  invoices={invoices ?? []}
                  selectedId={activeInvoiceId}
                  onSelect={setSelectedInvoiceId}
                />
              )}
            </Card>
          ) : null}

          {activeInvoiceId !== null && isLoadingInvoice ? <InvoicePanelSkeleton /> : null}

          {invoice ? (
            <InvoicePanel
              invoice={invoice}
              isRefreshing={isFetchingInvoice && !isLoadingInvoice}
              isBusy={isInvoiceBusy}
              onPay={() => setIsPayOpen(true)}
              onClose={() => void handleClose()}
              onUndo={() => void handleUndo()}
            />
          ) : null}
        </div>
      </section>

      <CardFormModal
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        cardId={editingCardId}
        options={options}
        onSaved={notify}
      />

      <PayInvoiceModal
        invoice={isPayOpen ? (invoice ?? null) : null}
        options={options}
        today={today}
        defaultAccountId={selectedCard?.payment_account?.id ?? null}
        onClose={() => setIsPayOpen(false)}
        onPaid={notify}
      />

      <DeleteCardDialog
        card={pendingDeletion}
        isDeleting={remove.isPending}
        onCancel={() => setPendingDeletion(null)}
        onConfirm={() => void handleDelete()}
      />

      <Toast toast={toast} onDismiss={dismiss} />
    </AppShell>
  )
}

/** Ciclo do cartão e, quando definida, a conta que costuma pagar a fatura. */
function cycleCaption(card: CardDetail): string {
  const cycle = `Fecha dia ${card.closing_day} · vence dia ${card.due_day}`

  return card.payment_account === null
    ? cycle
    : `${cycle} · paga com ${card.payment_account.nickname}`
}
