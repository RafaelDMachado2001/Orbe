import { NavLink } from 'react-router-dom'

import { cn } from '@/lib/cn'
import { formatPercent } from '@/lib/format'

interface NavItem {
  label: string
  to: string
  badge?: number
  /** Telas ainda nao entregues aparecem desabilitadas, sem link morto. */
  disabled?: boolean
}

interface NavGroup {
  title: string
  items: NavItem[]
}

const groups: NavGroup[] = [
  {
    title: 'Geral',
    items: [
      { label: 'Visão geral', to: '/' },
      { label: 'Lançamentos', to: '/lancamentos' },
      { label: 'Cartões', to: '/cartoes' },
      { label: 'Bancos e contas', to: '/bancos', disabled: true },
      { label: 'Despesas fixas', to: '/recorrencias' },
      { label: 'Categorias', to: '/categorias' },
      { label: 'Importar histórico', to: '/importar' },
    ],
  },
  {
    title: 'Inteligência',
    items: [
      { label: 'Previsão financeira', to: '/previsao', disabled: true },
      { label: 'Relatórios', to: '/relatorios', disabled: true },
      { label: 'Metas e orçamento', to: '/metas', disabled: true },
    ],
  },
]

interface SidebarProps {
  cardsCount?: number
  commitmentRate?: number | null
  nextMonthLabel?: string
}

export function Sidebar({ cardsCount, commitmentRate, nextMonthLabel }: SidebarProps) {
  return (
    <aside className="flex w-[248px] shrink-0 flex-col gap-7 border-r border-hairline bg-[linear-gradient(180deg,#0C0E12,#08090C)] px-[18px] py-[26px]">
      <div className="flex items-center gap-[11px] px-1.5">
        <div className="grid size-[34px] place-items-center rounded-[11px] bg-[linear-gradient(140deg,#35D68A,#1E9E63)] text-[15px] font-extrabold text-[#04140C]">
          V
        </div>
        <div className="flex flex-col gap-px">
          <span className="text-[14.5px] font-bold tracking-[-0.2px]">Verso Finanças</span>
          <span className="text-[11px] font-medium text-ink-muted">Conta pessoal</span>
        </div>
      </div>

      <nav className="flex flex-col gap-[3px]">
        {groups.map((group, groupIndex) => (
          <div key={group.title} className="flex flex-col gap-[3px]">
            <span
              className={cn(
                'px-2 pb-2 text-[10.5px] font-bold uppercase tracking-[0.1em] text-ink-faint',
                groupIndex > 0 && 'pt-[22px]',
              )}
            >
              {group.title}
            </span>

            {group.items.map((item) => (
              <SidebarLink
                key={item.to}
                item={item}
                badge={item.label === 'Cartões' ? cardsCount : undefined}
              />
            ))}
          </div>
        ))}
      </nav>

      {commitmentRate !== null && commitmentRate !== undefined ? (
        <div className="mt-auto rounded-[14px] border border-purple/[0.22] bg-[linear-gradient(160deg,rgba(160,124,255,0.14),rgba(160,124,255,0.03))] p-[15px]">
          <p className="mb-[5px] text-[12.5px] font-bold text-purple-light">
            Fatura de {nextMonthLabel ?? 'próximo mês'}
          </p>
          <p className="mb-[11px] text-[11.5px] leading-[1.5] text-ink-soft">
            Você já comprometeu {formatPercent(commitmentRate, 0)} da renda prevista do próximo mês.
          </p>
          <div className="h-[5px] overflow-hidden rounded bg-white/[0.08]">
            <div
              className="h-full rounded bg-[linear-gradient(90deg,#A07CFF,#FF8A3D)]"
              style={{ width: `${Math.min(commitmentRate, 100)}%` }}
            />
          </div>
        </div>
      ) : null}
    </aside>
  )
}

function SidebarLink({ item, badge }: { item: NavItem; badge?: number }) {
  const content = (isActive: boolean) => (
    <>
      <span
        className={cn(
          'size-[7px] shrink-0 rounded-full',
          isActive
            ? 'bg-green shadow-[0_0_10px_#35D68A]'
            : 'border-[1.5px] border-[#3C4450]',
        )}
      />
      {item.label}
      {badge !== undefined && badge > 0 ? (
        <span className="ml-auto rounded-full bg-orange/[0.13] px-[7px] py-0.5 text-[11px] font-bold text-orange">
          {badge}
        </span>
      ) : null}
    </>
  )

  if (item.disabled) {
    return (
      <span
        aria-disabled="true"
        title="Em breve"
        className="flex cursor-not-allowed items-center gap-[11px] rounded-[10px] px-3 py-2.5 text-[13.5px] font-medium text-ink-soft/45"
      >
        {content(false)}
      </span>
    )
  }

  return (
    <NavLink
      to={item.to}
      end
      className={({ isActive }) =>
        cn(
          'flex items-center gap-[11px] rounded-[10px] px-3 py-2.5 text-[13.5px] transition-colors',
          isActive
            ? 'bg-green/[0.11] font-semibold text-green-bright shadow-[inset_0_0_0_1px_rgba(53,214,138,0.16)]'
            : 'font-medium text-ink-soft hover:bg-white/[0.045] hover:text-ink',
        )
      }
    >
      {({ isActive }) => content(isActive)}
    </NavLink>
  )
}
