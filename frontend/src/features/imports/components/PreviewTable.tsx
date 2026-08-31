import { AlertTriangle, Ban } from 'lucide-react'
import { useState } from 'react'

import { Money } from '@/components/ui/Money'
import { cn } from '@/lib/cn'
import { formatDate } from '@/lib/format'
import type { ImportPreviewRow, TransactionOptions } from '@/types/api'

/**
 * Quantas linhas o navegador desenha de cara. Um extrato de anos passa de mil
 * linhas e montar todas de uma vez trava a rolagem — quem confere olha o
 * começo e usa os totais para o resto, então o restante fica atrás de um
 * clique. Marcar, desmarcar e importar continuam valendo para o arquivo todo.
 */
const VISIBLE_ROWS = 150

interface Props {
  rows: ImportPreviewRow[]
  selected: Set<number>
  categories: Record<number, number | null>
  categoryOptions: TransactionOptions['categories']
  onToggle: (index: number) => void
  onToggleAll: (shouldSelect: boolean) => void
  onCategoryChange: (index: number, categoryId: number | null) => void
}

export function PreviewTable({
  rows,
  selected,
  categories,
  categoryOptions,
  onToggle,
  onToggleAll,
  onCategoryChange,
}: Props) {
  const [showAll, setShowAll] = useState(false)

  const importable = rows.filter((row) => row.is_importable)
  const allSelected = importable.length > 0 && importable.every((row) => selected.has(row.index))
  const visible = showAll ? rows : rows.slice(0, VISIBLE_ROWS)
  const hidden = rows.length - visible.length

  return (
    <div className="flex flex-col gap-3">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[680px] border-collapse text-left">
          <thead>
            <tr className="border-b border-hairline">
              <Th className="w-9 pl-1">
                <input
                  type="checkbox"
                  checked={allSelected}
                  onChange={() => onToggleAll(!allSelected)}
                  aria-label={allSelected ? 'Desmarcar todos' : 'Marcar todos'}
                  className="size-3.5 cursor-pointer accent-[#35D68A]"
                />
              </Th>
              <Th className="w-[92px]">Data</Th>
              <Th>Descrição</Th>
              <Th className="w-[184px]">Categoria</Th>
              <Th className="w-[132px] text-right">Valor</Th>
            </tr>
          </thead>

          <tbody>
            {visible.map((row) => (
              <Row
                key={row.index}
                row={row}
                isSelected={selected.has(row.index)}
                categoryId={categories[row.index] ?? null}
                categoryOptions={categoryOptions}
                onToggle={() => onToggle(row.index)}
                onCategoryChange={(value) => onCategoryChange(row.index, value)}
              />
            ))}
          </tbody>
        </table>
      </div>

      {hidden > 0 ? (
        <button
          type="button"
          onClick={() => setShowAll(true)}
          className="self-center rounded-[10px] border border-hairline px-3.5 py-2 text-[11.5px] font-semibold text-ink-soft transition-colors hover:border-hairline-strong hover:text-ink"
        >
          Mostrar os outros {hidden} lançamentos
        </button>
      ) : null}
    </div>
  )
}

interface RowProps {
  row: ImportPreviewRow
  isSelected: boolean
  categoryId: number | null
  categoryOptions: TransactionOptions['categories']
  onToggle: () => void
  onCategoryChange: (categoryId: number | null) => void
}

function Row({ row, isSelected, categoryId, categoryOptions, onToggle, onCategoryChange }: RowProps) {
  const isIncome = row.direction === 'entrada'

  // Categoria de receita não serve para despesa: oferecer as duas listas
  // deixaria gravar um salário em "Alimentação".
  const options = categoryOptions.filter((category) =>
    isIncome ? category.type === 'receita' : category.type === 'despesa',
  )

  return (
    <tr
      className={cn(
        'border-b border-hairline-soft transition-colors',
        !row.is_importable && 'opacity-45',
        row.is_duplicate && row.is_importable && !isSelected && 'opacity-70',
      )}
    >
      <td className="py-2.5 pl-1 align-middle">
        <input
          type="checkbox"
          checked={isSelected}
          disabled={!row.is_importable}
          onChange={onToggle}
          aria-label={`Importar ${row.description}`}
          className="size-3.5 cursor-pointer accent-[#35D68A] disabled:cursor-not-allowed"
        />
      </td>

      <td className="py-2.5 align-middle font-mono text-[11.5px] tabular-nums text-ink-soft">
        {formatDate(row.date).slice(0, 5)}
      </td>

      <td className="py-2.5 pr-3 align-middle">
        <span className="line-clamp-1 text-[12.5px] font-medium text-ink">{row.description}</span>

        {row.is_duplicate ? (
          <Badge tone="warn" icon={AlertTriangle}>
            Já existe um lançamento igual nesta data
          </Badge>
        ) : null}

        {row.skip_reason ? (
          <Badge tone="blocked" icon={Ban}>
            {row.skip_reason}
          </Badge>
        ) : null}
      </td>

      <td className="py-2.5 pr-3 align-middle">
        <select
          value={categoryId === null ? '' : String(categoryId)}
          disabled={!row.is_importable}
          onChange={(event) =>
            onCategoryChange(event.target.value === '' ? null : Number(event.target.value))
          }
          aria-label={`Categoria de ${row.description}`}
          className="w-full appearance-none rounded-[9px] border border-hairline bg-surface-alt px-2.5 py-1.5 text-[11.5px] text-ink transition-colors focus:border-purple/60 disabled:cursor-not-allowed"
        >
          <option value="">Sem categoria</option>
          {options.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </td>

      <td className="py-2.5 pr-1 text-right align-middle">
        <Money
          value={row.amount}
          className={cn('text-[12.5px] font-semibold', isIncome ? 'text-green-bright' : 'text-ink')}
        />
      </td>
    </tr>
  )
}

function Th({ children, className }: { children: React.ReactNode; className?: string }) {
  return (
    <th
      scope="col"
      className={cn(
        'pb-2 text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint',
        className,
      )}
    >
      {children}
    </th>
  )
}

function Badge({
  children,
  tone,
  icon: Icon,
}: {
  children: React.ReactNode
  tone: 'warn' | 'blocked'
  icon: typeof AlertTriangle
}) {
  return (
    <span
      className={cn(
        'mt-1 flex items-center gap-1.5 text-[10.5px] font-semibold',
        tone === 'warn' ? 'text-orange-light' : 'text-ink-muted',
      )}
    >
      <Icon className="size-3 shrink-0" aria-hidden="true" />
      {children}
    </span>
  )
}
