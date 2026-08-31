import { format, parseISO } from 'date-fns'
import { ptBR } from 'date-fns/locale'

/**
 * Toda formatacao visivel passa por aqui. Nenhum componente chama
 * Intl.NumberFormat direto — assim moeda, data e percentual mudam em um lugar.
 */

const brl = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
  minimumFractionDigits: 2,
})

const brlCompact = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
  maximumFractionDigits: 0,
})

export function formatBRL(value: number | null | undefined): string {
  return brl.format(value ?? 0)
}

/** R$ 3.850 — usado onde os centavos so poluiriam a leitura. */
export function formatBRLCompact(value: number | null | undefined): string {
  return brlCompact.format(value ?? 0)
}

/**
 * Separa a parte inteira dos centavos para o KPI, que exibe os centavos em
 * corpo menor e com cor de apoio.
 */
export function splitBRL(value: number | null | undefined): {
  integer: string
  cents: string
} {
  const parts = formatBRL(value).split(',')

  return { integer: parts[0] ?? 'R$ 0', cents: parts[1] ?? '00' }
}

export function formatSigned(value: number, direction: 'entrada' | 'saida'): string {
  const symbol = direction === 'entrada' ? '+' : '−'

  return `${symbol} ${new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(value))}`
}

export function formatPercent(value: number | null | undefined, digits = 1): string {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: digits,
  }).format(value)}%`
}

/** 2026-08-24 -> 24 ago */
export function formatDayMonth(isoDate: string): string {
  return format(parseISO(isoDate), "dd MMM", { locale: ptBR })
}

/** 2026-08-24 -> 24/08/2026 */
export function formatDate(isoDate: string): string {
  return format(parseISO(isoDate), 'dd/MM/yyyy', { locale: ptBR })
}

/** 2026-08 -> agosto */
export function formatCompetence(month: string): string {
  return format(parseISO(`${month}-01`), 'MMMM', { locale: ptBR })
}

/** 2026-08 -> Ago */
export function formatCompetenceShort(month: string): string {
  const label = format(parseISO(`${month}-01`), 'MMM', { locale: ptBR })

  return label.charAt(0).toUpperCase() + label.slice(1).replace('.', '')
}

export function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1)
}
