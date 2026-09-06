import { Card, CardHeader } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/States'
import { Money } from '@/components/ui/Money'
import { formatPercent } from '@/lib/format'
import type { AnnualCategorySlice } from '@/types/api'

export function CategoryRanking({ categories }: { categories: AnnualCategorySlice[] }) {
  return (
    <Card>
      <CardHeader title="Categorias que mais pesaram no ano" className="mb-[18px]" />

      {categories.length === 0 ? (
        <EmptyState
          title="Nenhuma despesa categorizada"
          description="Assim que houver despesas lançadas no ano, o ranking aparece aqui."
        />
      ) : (
        <div className="flex flex-col gap-[15px]">
          {categories.map((category, index) => (
            <div key={category.category_id ?? category.name}>
              <div className="mb-[7px] flex items-baseline gap-2.5">
                <span className="w-4 text-[11px] font-bold text-ink-faint">{index + 1}</span>
                <span className="text-[12.5px] font-semibold">{category.name}</span>
                <Money value={category.total} className="ml-auto text-[12px] text-[#C3C9D2]" />
                <span className="w-8 text-right text-[11px] font-bold text-ink-muted">
                  {formatPercent(category.percentage, 0)}
                </span>
              </div>
              <div className="ml-[26px] h-[7px] overflow-hidden rounded-[5px] bg-white/[0.06]">
                <div
                  className="h-full rounded-[5px] transition-[width] duration-500"
                  style={{ width: `${category.percentage}%`, background: category.color }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

export function CategoryRankingSkeleton() {
  return (
    <Card>
      <div className="skeleton h-4 w-56" />
      <div className="mt-[18px] flex flex-col gap-[15px]">
        {Array.from({ length: 5 }).map((_, index) => (
          <div key={index} className="flex flex-col gap-[7px]">
            <div className="skeleton h-3 w-full" />
            <div className="skeleton h-[7px] w-full" />
          </div>
        ))}
      </div>
    </Card>
  )
}
