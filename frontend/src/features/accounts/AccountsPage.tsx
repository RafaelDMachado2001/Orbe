import { format } from 'date-fns'
import { Plus } from 'lucide-react'
import { useMemo, useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { MonthNav } from '@/components/ui/MonthNav'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { apiErrorMessage } from '@/lib/api'
import type { AccountRow, BankGroup as BankGroupData } from '@/types/api'

import {
  useAccountOptions,
  useAccounts,
  useArchiveAccount,
  useDeleteAccount,
  useDeleteBank,
} from './api'
import { AccountFormModal } from './components/AccountFormModal'
import { AccountsSummary, AccountsSummarySkeleton } from './components/AccountsSummary'
import { AdjustBalanceModal } from './components/AdjustBalanceModal'
import { BalanceEvolution, BalanceEvolutionSkeleton } from './components/BalanceEvolution'
import { BankFormModal, type EditableBank } from './components/BankFormModal'
import { BankGroup, BankGroupSkeleton } from './components/BankGroup'
import { DeleteAccountDialog } from './components/DeleteAccountDialog'
import { DeleteBankDialog } from './components/DeleteBankDialog'

/**
 * Bancos e contas.
 *
 * A tela responde "quanto eu tenho e onde". O agrupamento é por instituição
 * porque é assim que a pessoa procura, e o saldo nunca é um número guardado:
 * é sempre saldo inicial mais os lançamentos confirmados. Quando o app e o
 * extrato do banco divergem, a correção é um lançamento de ajuste — não uma
 * edição do passado.
 *
 * Trocar o mês e mostrar arquivadas acontece em memória, sem recarregar: a
 * lista atual fica à vista até a nova chegar.
 */
export function AccountsPage() {
  const today = format(new Date(), 'yyyy-MM-dd')
  const [month, setMonth] = useState(() => format(new Date(), 'yyyy-MM'))
  const [showArchived, setShowArchived] = useState(false)

  const { data, isPending, isFetching, isError, error, refetch } = useAccounts(month, showArchived)
  const { data: options } = useAccountOptions()

  const archive = useArchiveAccount()
  const removeAccount = useDeleteAccount()
  const removeBank = useDeleteBank()

  const { toast, notify, dismiss } = useToast()

  const [isBankFormOpen, setIsBankFormOpen] = useState(false)
  const [editingBank, setEditingBank] = useState<EditableBank | null>(null)

  const [isAccountFormOpen, setIsAccountFormOpen] = useState(false)
  const [editingAccount, setEditingAccount] = useState<AccountRow | null>(null)
  const [defaultBankId, setDefaultBankId] = useState<number | null>(null)

  const [adjusting, setAdjusting] = useState<AccountRow | null>(null)

  const [pendingAccount, setPendingAccount] = useState<AccountRow | null>(null)
  const [accountError, setAccountError] = useState<string | null>(null)
  const [pendingBank, setPendingBank] = useState<BankGroupData | null>(null)
  const [bankError, setBankError] = useState<string | null>(null)

  const isRefreshing = isFetching && !isPending
  const banks = useMemo(() => data?.banks ?? [], [data])

  function openBankCreate() {
    setEditingBank(null)
    setIsBankFormOpen(true)
  }

  function openBankEdit(bank: BankGroupData) {
    setEditingBank({ id: bank.id, name: bank.name, color: bank.color, kind: bank.kind })
    setIsBankFormOpen(true)
  }

  function openAccountCreate(bankId: number | null) {
    setEditingAccount(null)
    setDefaultBankId(bankId)
    setIsAccountFormOpen(true)
  }

  function openAccountEdit(account: AccountRow) {
    setEditingAccount(account)
    setDefaultBankId(account.bank.id)
    setIsAccountFormOpen(true)
  }

  async function handleArchive(account: AccountRow, isActive: boolean) {
    try {
      await archive.mutateAsync({ id: account.id, isActive })
      notify(isActive ? 'Conta reativada.' : 'Conta arquivada.')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível arquivar a conta.'), 'erro')
    }
  }

  async function handleDeleteAccount() {
    if (pendingAccount === null) {
      return
    }

    setAccountError(null)

    try {
      await removeAccount.mutateAsync(pendingAccount.id)
      notify('Conta excluída.')
      setPendingAccount(null)
    } catch (mutationError) {
      setAccountError(apiErrorMessage(mutationError, 'Não foi possível excluir a conta.'))
    }
  }

  async function handleDeleteBank() {
    if (pendingBank === null) {
      return
    }

    setBankError(null)

    try {
      await removeBank.mutateAsync(pendingBank.id)
      notify('Banco excluído.')
      setPendingBank(null)
    } catch (mutationError) {
      setBankError(apiErrorMessage(mutationError, 'Não foi possível excluir o banco.'))
    }
  }

  return (
    <AppShell>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Bancos e contas</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">
            {data ? summaryCaption(data.summary.active_count, data.summary.banks_count) : 'Carregando suas contas…'}
          </p>
        </div>

        <div className="ml-auto flex flex-wrap items-center gap-2.5">
          <MonthNav month={month} onChange={setMonth} />

          <Button variant="ghost" onClick={() => setShowArchived((value) => !value)}>
            {showArchived ? 'Ocultar arquivadas' : 'Mostrar arquivadas'}
          </Button>

          <Button onClick={openBankCreate}>
            <Plus className="size-3.5" aria-hidden="true" />
            Novo banco
          </Button>
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar as contas.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? <AccountsSummarySkeleton /> : null}
      {data ? <AccountsSummary summary={data.summary} isRefreshing={isRefreshing} /> : null}

      <section className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(340px,420px)]">
        <div className="flex flex-col gap-4">
          {isPending ? (
            <>
              <BankGroupSkeleton />
              <BankGroupSkeleton />
            </>
          ) : banks.length === 0 ? (
            <EmptyState
              title="Nenhum banco cadastrado"
              description="Cadastre a instituição onde você tem conta — Nubank, Itaú, uma corretora ou até dinheiro em espécie. As contas vêm depois, dentro dela."
              action={
                <Button variant="ghost" onClick={openBankCreate}>
                  <Plus className="size-3.5" aria-hidden="true" />
                  Novo banco
                </Button>
              }
            />
          ) : (
            banks.map((bank) => (
              <BankGroup
                key={bank.id}
                bank={bank}
                isRefreshing={isRefreshing}
                onAddAccount={() => openAccountCreate(bank.id)}
                onEditBank={() => openBankEdit(bank)}
                onDeleteBank={() => {
                  setBankError(null)
                  setPendingBank(bank)
                }}
                onEditAccount={openAccountEdit}
                onAdjustAccount={setAdjusting}
                onArchiveAccount={(account, isActive) => void handleArchive(account, isActive)}
                onDeleteAccount={(account) => {
                  setAccountError(null)
                  setPendingAccount(account)
                }}
              />
            ))
          )}
        </div>

        {isPending ? (
          <BalanceEvolutionSkeleton />
        ) : (
          <BalanceEvolution data={data?.evolution ?? []} isRefreshing={isRefreshing} />
        )}
      </section>

      <BankFormModal
        isOpen={isBankFormOpen}
        onClose={() => setIsBankFormOpen(false)}
        bank={editingBank}
        options={options}
        onSaved={notify}
      />

      <AccountFormModal
        isOpen={isAccountFormOpen}
        onClose={() => setIsAccountFormOpen(false)}
        account={editingAccount}
        defaultBankId={defaultBankId}
        options={options}
        onSaved={notify}
      />

      <AdjustBalanceModal
        account={adjusting}
        today={today}
        onClose={() => setAdjusting(null)}
        onAdjusted={notify}
      />

      <DeleteAccountDialog
        account={pendingAccount}
        isDeleting={removeAccount.isPending}
        error={accountError}
        onCancel={() => setPendingAccount(null)}
        onConfirm={() => void handleDeleteAccount()}
      />

      <DeleteBankDialog
        bank={pendingBank}
        isDeleting={removeBank.isPending}
        error={bankError}
        onCancel={() => setPendingBank(null)}
        onConfirm={() => void handleDeleteBank()}
      />

      <Toast toast={toast} onDismiss={dismiss} />
    </AppShell>
  )
}

function summaryCaption(accounts: number, banks: number): string {
  if (banks === 0) {
    return 'comece cadastrando a instituição onde você tem conta'
  }

  const accountsLabel = `${accounts} ${accounts === 1 ? 'conta ativa' : 'contas ativas'}`
  const banksLabel = `${banks} ${banks === 1 ? 'instituição' : 'instituições'}`

  return `${accountsLabel} em ${banksLabel} · o saldo é sempre o inicial mais os lançamentos confirmados`
}
