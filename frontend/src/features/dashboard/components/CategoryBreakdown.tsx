import { Card, CardHeader } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/States'
import { Money } from '@/components/ui/Money'
import { capitalize, formatCompetence, formatPercent } from '@/lib/format'
import type { CategorySlice } from '@/types/api'

interface Props {
  categories: CategorySlice[]
  month: string
}

export function CategoryBreakdown({ categories, month }: Props) {
  return (
    <Card>
      <CardHeader
        title="Gastos por categoria"
        action={
          <span className="text-[11px] font-semibold text-ink-muted">
            {formatCompetence(month)}
          </span>
        }
        className="mb-[18px]"
      />

      {categories.length === 0 ? (
        <EmptyState
          title="Nenhum gasto neste mês"
          description="Assim que houver despesas lançadas, elas aparecem aqui agrupadas por categoria."
        />
      ) : (
        <div className="flex flex-col gap-[15px]">
          {categories.map((slice) => (
            <div key={slice.category_id ?? slice.name}>
              <div className="mb-[7px] flex items-baseline gap-2.5">
                <span className="text-[12.5px] font-semibold">{capitalize(slice.name)}</span>
                <Money
                  value={slice.total}
                  className="ml-auto text-[12px] text-[#C3C9D2]"
                />
                <span className="w-8 text-right text-[11px] font-bold text-ink-muted">
                  {formatPercent(slice.percentage, 0)}
                </span>
              </div>
              <div className="h-[7px] overflow-hidden rounded-[5px] bg-white/[0.06]">
                <div
                  className="h-full rounded-[5px] transition-[width] duration-500"
                  style={{ width: `${slice.percentage}%`, background: slice.color }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}
