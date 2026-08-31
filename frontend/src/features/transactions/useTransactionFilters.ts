import {
  endOfMonth,
  endOfYear,
  format,
  startOfMonth,
  startOfYear,
  subDays,
  subMonths,
} from 'date-fns'
import { useEffect, useMemo, useReducer, useState } from 'react'

import type { TransactionFilters } from './api'

export type PeriodShortcut = 'mes-atual' | 'mes-passado' | 'ultimos-30' | 'ano'

export const periodShortcuts: { key: PeriodShortcut; label: string }[] = [
  { key: 'mes-atual', label: 'Este mês' },
  { key: 'mes-passado', label: 'Mês passado' },
  { key: 'ultimos-30', label: '30 dias' },
  { key: 'ano', label: 'Ano' },
]

const iso = (date: Date): string => format(date, 'yyyy-MM-dd')

export function rangeFor(shortcut: PeriodShortcut, today = new Date()): { from: string; to: string } {
  switch (shortcut) {
    case 'mes-passado': {
      const previous = subMonths(today, 1)

      return { from: iso(startOfMonth(previous)), to: iso(endOfMonth(previous)) }
    }
    case 'ultimos-30':
      return { from: iso(subDays(today, 29)), to: iso(today) }
    case 'ano':
      return { from: iso(startOfYear(today)), to: iso(endOfYear(today)) }
    case 'mes-atual':
    default:
      return { from: iso(startOfMonth(today)), to: iso(endOfMonth(today)) }
  }
}

export const defaultFilters = (): TransactionFilters => ({
  ...rangeFor('mes-atual'),
  origins: [],
  types: [],
  statuses: [],
  categories: [],
  accountId: null,
  creditCardId: null,
  search: '',
  page: 1,
  perPage: 25,
})

type Action =
  | { type: 'periodo'; from: string; to: string }
  | { type: 'alterna'; campo: 'origins' | 'types' | 'statuses'; valor: string }
  | { type: 'categorias'; valor: number[] }
  | { type: 'conta'; valor: number | null }
  | { type: 'cartao'; valor: number | null }
  | { type: 'busca'; valor: string }
  | { type: 'pagina'; valor: number }
  | { type: 'limpar' }

/**
 * Qualquer recorte novo volta para a primeira página: manter a página 7 depois
 * de filtrar por "receita" mostraria uma lista vazia com dados atrás.
 */
function reducer(state: TransactionFilters, action: Action): TransactionFilters {
  switch (action.type) {
    case 'periodo':
      return { ...state, from: action.from, to: action.to, page: 1 }
    case 'alterna': {
      const atual = state[action.campo] as string[]
      const proximo = atual.includes(action.valor)
        ? atual.filter((item) => item !== action.valor)
        : [...atual, action.valor]

      return { ...state, [action.campo]: proximo, page: 1 }
    }
    case 'categorias':
      return { ...state, categories: action.valor, page: 1 }
    case 'conta':
      // Conta e cartão se excluem: uma linha ou é de conta, ou é parcela.
      return { ...state, accountId: action.valor, creditCardId: null, page: 1 }
    case 'cartao':
      return { ...state, creditCardId: action.valor, accountId: null, page: 1 }
    case 'busca':
      return { ...state, search: action.valor, page: 1 }
    case 'pagina':
      return { ...state, page: action.valor }
    case 'limpar':
      return defaultFilters()
    default:
      return state
  }
}

export interface FiltersController {
  filters: TransactionFilters
  searchInput: string
  isDirty: boolean
  setSearchInput: (value: string) => void
  setPeriod: (from: string, to: string) => void
  applyShortcut: (shortcut: PeriodShortcut) => void
  activeShortcut: PeriodShortcut | null
  toggle: (campo: 'origins' | 'types' | 'statuses', valor: string) => void
  setCategories: (ids: number[]) => void
  setAccount: (id: number | null) => void
  setCard: (id: number | null) => void
  setPage: (page: number) => void
  clear: () => void
}

/**
 * O estado dos filtros vive em memória, não na URL.
 *
 * Marcar um filtro é uma mudança de tela, não de endereço: navegar a cada
 * clique encheria o histórico do navegador e faria o botão "voltar" desfazer
 * filtros um a um em vez de sair da tela.
 */
export function useTransactionFilters(): FiltersController {
  const [filters, dispatch] = useReducer(reducer, undefined, defaultFilters)
  const [searchInput, setSearchInput] = useState('')

  // Digitar não pode disparar uma requisição por tecla.
  useEffect(() => {
    if (searchInput === filters.search) {
      return
    }

    const timer = window.setTimeout(() => {
      dispatch({ type: 'busca', valor: searchInput })
    }, 350)

    return () => window.clearTimeout(timer)
  }, [searchInput, filters.search])

  const activeShortcut = useMemo(() => {
    const match = periodShortcuts.find((shortcut) => {
      const range = rangeFor(shortcut.key)

      return range.from === filters.from && range.to === filters.to
    })

    return match?.key ?? null
  }, [filters.from, filters.to])

  const isDirty = useMemo(() => {
    const base = defaultFilters()

    return (
      filters.from !== base.from ||
      filters.to !== base.to ||
      filters.origins.length > 0 ||
      filters.types.length > 0 ||
      filters.statuses.length > 0 ||
      filters.categories.length > 0 ||
      filters.accountId !== null ||
      filters.creditCardId !== null ||
      filters.search !== ''
    )
  }, [filters])

  return {
    filters,
    searchInput,
    isDirty,
    activeShortcut,
    setSearchInput,
    setPeriod: (from, to) => dispatch({ type: 'periodo', from, to }),
    applyShortcut: (shortcut) => {
      const range = rangeFor(shortcut)

      dispatch({ type: 'periodo', from: range.from, to: range.to })
    },
    toggle: (campo, valor) => dispatch({ type: 'alterna', campo, valor }),
    setCategories: (ids) => dispatch({ type: 'categorias', valor: ids }),
    setAccount: (id) => dispatch({ type: 'conta', valor: id }),
    setCard: (id) => dispatch({ type: 'cartao', valor: id }),
    setPage: (page) => dispatch({ type: 'pagina', valor: page }),
    clear: () => {
      setSearchInput('')
      dispatch({ type: 'limpar' })
    },
  }
}
