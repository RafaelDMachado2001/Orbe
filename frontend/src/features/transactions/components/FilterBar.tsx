import { Search, X } from 'lucide-react'

import { Card } from '@/components/ui/Card'
import { Select } from '@/components/ui/Select'
import { cn } from '@/lib/cn'
import type { TransactionOptions } from '@/types/api'

import { periodShortcuts, type FiltersController } from '../useTransactionFilters'
import { Chip } from './Chip'

interface Props {
  controller: FiltersController
  options?: TransactionOptions
}

/**
 * Todo controle daqui muda o estado em memória e a lista se refaz sozinha.
 * Não há botão "aplicar" nem recarga de página: filtrar é ver o mesmo extrato
 * por outro recorte, e a espera de um clique extra só atrapalharia.
 */
export function FilterBar({ controller, options }: Props) {
  const { filters } = controller

  return (
    <Card className="flex flex-col gap-3.5 p-[16px_18px]">
      <div className="flex flex-wrap items-center gap-2.5">
        <div className="relative min-w-[200px] flex-1">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-ink-faint"
            aria-hidden="true"
          />
          <input
            type="search"
            value={controller.searchInput}
            onChange={(event) => controller.setSearchInput(event.target.value)}
            placeholder="Buscar por descrição…"
            aria-label="Buscar lançamento por descrição"
            className="w-full rounded-[11px] border border-hairline bg-surface-alt py-2.5 pl-9 pr-3.5 text-[13px] text-ink transition-colors placeholder:text-ink-faint focus:border-purple/60"
          />
        </div>

        <div
          role="group"
          aria-label="Período rápido"
          className="flex items-center gap-0.5 rounded-[11px] border border-hairline bg-surface-raised p-1"
        >
          {periodShortcuts.map((shortcut) => (
            <button
              key={shortcut.key}
              type="button"
              onClick={() => controller.applyShortcut(shortcut.key)}
              aria-current={controller.activeShortcut === shortcut.key}
              className={cn(
                'rounded-lg px-[11px] py-1.5 text-[12px] transition-colors',
                controller.activeShortcut === shortcut.key
                  ? 'bg-white/[0.08] font-bold text-ink'
                  : 'font-semibold text-ink-muted hover:text-ink',
              )}
            >
              {shortcut.label}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-1.5 rounded-[11px] border border-hairline bg-surface-raised px-2.5 py-1.5">
          <input
            type="date"
            value={filters.from}
            max={filters.to}
            onChange={(event) => controller.setPeriod(event.target.value, filters.to)}
            aria-label="Data inicial"
            className="bg-transparent text-[12px] font-semibold text-ink outline-none"
          />
          <span className="text-[11px] text-ink-faint">até</span>
          <input
            type="date"
            value={filters.to}
            min={filters.from}
            onChange={(event) => controller.setPeriod(filters.from, event.target.value)}
            aria-label="Data final"
            className="bg-transparent text-[12px] font-semibold text-ink outline-none"
          />
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-x-4 gap-y-2.5 border-t border-hairline-soft pt-3.5">
        <ChipGroup
          label="Origem"
          options={options?.origins ?? []}
          selected={filters.origins}
          onToggle={(value) => controller.toggle('origins', value)}
        />

        <ChipGroup
          label="Tipo"
          options={options?.types ?? []}
          selected={filters.types}
          onToggle={(value) => controller.toggle('types', value)}
        />

        <ChipGroup
          label="Situação"
          options={options?.statuses ?? []}
          selected={filters.statuses}
          onToggle={(value) => controller.toggle('statuses', value)}
        />

        <div className="ml-auto flex flex-wrap items-center gap-2">
          <Select
            aria-label="Filtrar por categoria"
            placeholder="Todas as categorias"
            value={filters.categories[0]?.toString() ?? ''}
            onChange={(event) =>
              controller.setCategories(event.target.value === '' ? [] : [Number(event.target.value)])
            }
            options={(options?.categories ?? []).map((category) => ({
              value: category.id.toString(),
              label: category.name,
            }))}
            className="w-[168px] py-2 text-[12px]"
          />

          <Select
            aria-label="Filtrar por conta"
            placeholder="Todas as contas"
            value={filters.accountId?.toString() ?? ''}
            onChange={(event) =>
              controller.setAccount(event.target.value === '' ? null : Number(event.target.value))
            }
            options={(options?.accounts ?? []).map((account) => ({
              value: account.id.toString(),
              label: account.nickname,
            }))}
            className="w-[150px] py-2 text-[12px]"
          />

          <Select
            aria-label="Filtrar por cartão"
            placeholder="Todos os cartões"
            value={filters.creditCardId?.toString() ?? ''}
            onChange={(event) =>
              controller.setCard(event.target.value === '' ? null : Number(event.target.value))
            }
            options={(options?.cards ?? []).map((card) => ({
              value: card.id.toString(),
              label: card.nickname,
            }))}
            className="w-[168px] py-2 text-[12px]"
          />

          {controller.isDirty ? (
            <button
              type="button"
              onClick={controller.clear}
              className="flex items-center gap-1.5 rounded-[9px] px-2.5 py-2 text-[11.5px] font-semibold text-ink-muted transition-colors hover:text-orange"
            >
              <X className="size-3.5" aria-hidden="true" />
              Limpar
            </button>
          ) : null}
        </div>
      </div>
    </Card>
  )
}

interface ChipGroupProps {
  label: string
  options: { value: string; label: string }[]
  selected: string[]
  onToggle: (value: string) => void
}

function ChipGroup({ label, options, selected, onToggle }: ChipGroupProps) {
  return (
    <div role="group" aria-label={label} className="flex items-center gap-1.5">
      <span className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
        {label}
      </span>
      {options.map((option) => (
        <Chip
          key={option.value}
          isActive={selected.includes(option.value)}
          onClick={() => onToggle(option.value)}
        >
          {option.label}
        </Chip>
      ))}
    </div>
  )
}
