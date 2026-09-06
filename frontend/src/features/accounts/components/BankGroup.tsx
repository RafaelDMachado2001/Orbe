import {
  Archive,
  ArchiveRestore,
  Pencil,
  Plus,
  Scale,
  Trash2,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

import { Card } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatBRLCompact, formatDate } from '@/lib/format'
import type { AccountRow, BankGroup as BankGroupData } from '@/types/api'

interface Props {
  bank: BankGroupData
  isRefreshing: boolean
  onAddAccount: () => void
  onEditBank: () => void
  onDeleteBank: () => void
  onEditAccount: (account: AccountRow) => void
  onAdjustAccount: (account: AccountRow) => void
  onArchiveAccount: (account: AccountRow, isActive: boolean) => void
  onDeleteAccount: (account: AccountRow) => void
}

/**
 * Uma instituição e as contas dela.
 *
 * O agrupamento é por banco porque é assim que a pessoa procura: "quanto tenho
 * no Nubank" vem antes de "quanto tenho na conta corrente". O saldo no
 * cabeçalho soma só as contas ativas do banco — a arquivada aparece na lista,
 * apagada, mas não entra em nenhum total.
 */
export function BankGroup({
  bank,
  isRefreshing,
  onAddAccount,
  onEditBank,
  onDeleteBank,
  onEditAccount,
  onAdjustAccount,
  onArchiveAccount,
  onDeleteAccount,
}: Props) {
  const isEmpty = bank.accounts.length === 0

  return (
    <Card className={cn('flex flex-col gap-3.5 p-[18px_20px]', isRefreshing && 'opacity-60')}>
      <header className="flex items-center gap-3">
        <span
          className="h-[34px] w-2.5 shrink-0 rounded"
          style={{ background: bank.color }}
          aria-hidden="true"
        />

        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="truncate text-[14px] font-bold tracking-[-0.2px] text-ink">
            {bank.name}
          </span>
          <span className="truncate text-[11px] font-medium text-ink-muted">
            {bank.kind_label} · {countLabel(bank)}
          </span>
        </div>

        <div className="ml-auto flex items-center gap-3">
          <Money value={bank.balance} className="text-[15px] font-bold" />

          <div className="flex items-center gap-0.5">
            <IconButton icon={Plus} label={`Nova conta no ${bank.name}`} onClick={onAddAccount} />
            <IconButton icon={Pencil} label={`Editar ${bank.name}`} onClick={onEditBank} />
            <IconButton
              icon={Trash2}
              label={`Excluir ${bank.name}`}
              onClick={onDeleteBank}
              tone="danger"
            />
          </div>
        </div>
      </header>

      {isEmpty ? (
        <button
          type="button"
          onClick={onAddAccount}
          className="flex items-center gap-2 rounded-[12px] border border-dashed border-hairline px-3.5 py-3 text-left text-[12px] font-medium text-ink-muted transition-colors hover:border-hairline-strong hover:text-ink"
        >
          <Plus className="size-3.5" aria-hidden="true" />
          Nenhuma conta neste banco ainda — cadastre a primeira
        </button>
      ) : (
        <ul className="flex flex-col gap-1.5">
          {bank.accounts.map((account) => (
            <li key={account.id}>
              <AccountLine
                account={account}
                onEdit={() => onEditAccount(account)}
                onAdjust={() => onAdjustAccount(account)}
                onArchive={() => onArchiveAccount(account, !account.is_active)}
                onDelete={() => onDeleteAccount(account)}
              />
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}

interface AccountLineProps {
  account: AccountRow
  onEdit: () => void
  onAdjust: () => void
  onArchive: () => void
  onDelete: () => void
}

function AccountLine({ account, onEdit, onAdjust, onArchive, onDelete }: AccountLineProps) {
  return (
    <div
      className={cn(
        'flex flex-wrap items-center gap-3 rounded-[12px] border border-hairline bg-surface-alt px-3.5 py-2.5',
        !account.is_active && 'opacity-55',
      )}
    >
      <div className="flex min-w-0 flex-col gap-0.5">
        <span className="flex items-center gap-2 truncate text-[12.5px] font-semibold text-ink">
          {account.nickname}
          {account.is_active ? null : (
            <span className="rounded-full bg-white/[0.07] px-1.5 py-px text-[9.5px] font-bold uppercase tracking-[0.06em] text-ink-muted">
              arquivada
            </span>
          )}
        </span>
        <span className="truncate text-[10.5px] font-medium text-ink-muted">
          {account.type_label} · {movementCaption(account)}
        </span>
      </div>

      <div className="ml-auto flex items-center gap-4">
        <div className="flex flex-col items-end gap-0.5">
          <Money
            value={account.balance}
            className={cn(
              'text-[13.5px] font-bold',
              account.balance < 0 ? 'text-danger' : 'text-ink',
            )}
          />
          <span className="whitespace-nowrap text-[10.5px] font-medium text-ink-muted">
            <span className="text-green-bright">+{formatBRLCompact(account.month_in).replace('R$ ', '')}</span>
            {' / '}
            <span className="text-orange-light">−{formatBRLCompact(account.month_out).replace('R$ ', '')}</span>
            {' no mês'}
          </span>
        </div>

        <div className="flex items-center gap-0.5">
          {account.is_active ? (
            <IconButton icon={Scale} label={`Ajustar saldo de ${account.nickname}`} onClick={onAdjust} />
          ) : null}
          <IconButton icon={Pencil} label={`Editar ${account.nickname}`} onClick={onEdit} />
          <IconButton
            icon={account.is_active ? Archive : ArchiveRestore}
            label={
              account.is_active
                ? `Arquivar ${account.nickname}`
                : `Reativar ${account.nickname}`
            }
            onClick={onArchive}
          />
          <IconButton
            icon={Trash2}
            label={`Excluir ${account.nickname}`}
            onClick={onDelete}
            tone="danger"
          />
        </div>
      </div>
    </div>
  )
}

interface IconButtonProps {
  icon: LucideIcon
  label: string
  onClick: () => void
  tone?: 'default' | 'danger'
}

function IconButton({ icon: Icon, label, onClick, tone = 'default' }: IconButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={label}
      aria-label={label}
      className={cn(
        'grid size-7 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-white/[0.06]',
        tone === 'danger' ? 'hover:text-danger' : 'hover:text-ink',
      )}
    >
      <Icon className="size-3.5" aria-hidden="true" />
    </button>
  )
}

function countLabel(bank: BankGroupData): string {
  const accounts = `${bank.accounts_count} ${bank.accounts_count === 1 ? 'conta' : 'contas'}`

  if (bank.cards_count === 0) {
    return accounts
  }

  return `${accounts} · ${bank.cards_count} ${bank.cards_count === 1 ? 'cartão' : 'cartões'}`
}

function movementCaption(account: AccountRow): string {
  if (account.movements_count === 0) {
    return 'sem lançamentos'
  }

  const total = `${account.movements_count} ${account.movements_count === 1 ? 'lançamento' : 'lançamentos'}`

  return account.last_movement_on === null
    ? total
    : `${total} · último em ${formatDate(account.last_movement_on)}`
}

export function BankGroupSkeleton() {
  return (
    <Card className="flex flex-col gap-3.5 p-[18px_20px]">
      <div className="flex items-center gap-3">
        <Skeleton className="h-[34px] w-2.5 rounded" />
        <div className="flex flex-col gap-2">
          <Skeleton className="h-3 w-28" />
          <Skeleton className="h-2.5 w-40" />
        </div>
        <Skeleton className="ml-auto h-4 w-24" />
      </div>
      <Skeleton className="h-[54px] w-full rounded-[12px]" />
      <Skeleton className="h-[54px] w-full rounded-[12px]" />
    </Card>
  )
}
