import { Plus } from 'lucide-react'

import { Card, CardHeader } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import type { AccountSummary } from '@/types/api'

export function AccountsPanel({ accounts }: { accounts: AccountSummary[] }) {
  return (
    <Card>
      <CardHeader title="Contas bancárias" className="mb-4" />

      <div className="flex flex-col gap-[13px]">
        {accounts.map((account) => (
          <div key={account.id} className="flex items-center gap-[11px]">
            <span
              className="h-[30px] w-2 shrink-0 rounded"
              style={{ background: account.bank.color }}
              aria-hidden="true"
            />
            <div className="flex min-w-0 flex-col gap-0.5">
              <span className="truncate text-[12.5px] font-semibold">{account.bank.name}</span>
              <span className="text-[10.5px] font-medium text-ink-muted">{account.nickname}</span>
            </div>
            <Money value={account.balance} className="ml-auto text-[13px] font-semibold" />
          </div>
        ))}

        <div className="flex items-center gap-[11px] opacity-75">
          <span className="h-[30px] w-2 shrink-0 rounded bg-[#3C4450]" aria-hidden="true" />
          <div className="flex flex-col gap-0.5">
            <span className="flex items-center gap-1 text-[12.5px] font-semibold">
              <Plus className="size-3" aria-hidden="true" />
              Cadastrar banco
            </span>
            <span className="text-[10.5px] font-medium text-ink-muted">
              {accounts.length === 0 ? 'Comece pela sua conta principal' : 'Cadastro manual'}
            </span>
          </div>
        </div>
      </div>
    </Card>
  )
}
