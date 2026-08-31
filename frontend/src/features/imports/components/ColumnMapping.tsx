import { Columns3 } from 'lucide-react'

import { Select } from '@/components/ui/Select'
import type { CsvMapping as Mapping, ImportPreview } from '@/types/api'

interface Props {
  preview: ImportPreview
  onChange: (mapping: Mapping) => void
  isReading: boolean
}

const DELIMITERS = [
  { value: ';', label: 'Ponto e vírgula ( ; )' },
  { value: ',', label: 'Vírgula ( , )' },
  { value: '\t', label: 'Tabulação' },
  { value: '|', label: 'Barra vertical ( | )' },
]

/**
 * De qual coluna do CSV saiu cada campo.
 *
 * CSV não tem padrão: muda o separador, a ordem das colunas e até se o valor
 * vem com sinal ou dividido em débito e crédito. O parser adivinha e mostra o
 * palpite aqui — adivinhar em silêncio importaria o extrato trocado, e pedir o
 * mapeamento antes de ler exigiria que o usuário abrisse a planilha.
 */
export function ColumnMapping({ preview, onChange, isReading }: Props) {
  const mapping = preview.mapping

  if (mapping === null) {
    return null
  }

  const columns = Array.from({ length: Math.max(preview.column_count, 1) }, (_, index) => ({
    value: String(index),
    label: preview.headers[index]?.trim() || `Coluna ${index + 1}`,
  }))

  function update(patch: Partial<Mapping>) {
    onChange({ ...(mapping as Mapping), ...patch })
  }

  return (
    <div className="flex flex-col gap-3 rounded-[14px] border border-hairline bg-surface-alt p-4">
      <div className="flex items-center gap-2">
        <Columns3 className="size-3.5 text-ink-muted" aria-hidden="true" />
        <span className="text-[12px] font-bold text-ink">Colunas do arquivo</span>
        <span className="text-[11px] font-medium text-ink-muted">
          {isReading ? 'relendo…' : 'confira o que reconhecemos'}
        </span>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Select
          label="Data"
          options={columns}
          value={String(mapping.date_column)}
          onChange={(event) => update({ date_column: Number(event.target.value) })}
        />
        <Select
          label="Descrição"
          options={columns}
          value={String(mapping.description_column)}
          onChange={(event) => update({ description_column: Number(event.target.value) })}
        />
        <Select
          label={mapping.inflow_column === null ? 'Valor' : 'Saída (débito)'}
          options={columns}
          value={String(mapping.amount_column)}
          onChange={(event) => update({ amount_column: Number(event.target.value) })}
        />
        <Select
          label="Entrada (crédito)"
          options={columns}
          placeholder="O valor já tem sinal"
          value={mapping.inflow_column === null ? '' : String(mapping.inflow_column)}
          onChange={(event) =>
            update({ inflow_column: event.target.value === '' ? null : Number(event.target.value) })
          }
        />
        <Select
          label="Separador"
          options={DELIMITERS}
          value={mapping.delimiter}
          onChange={(event) => update({ delimiter: event.target.value })}
        />
        <Select
          label="Primeira linha"
          options={[
            { value: '1', label: 'É o cabeçalho' },
            { value: '0', label: 'Já é um lançamento' },
          ]}
          value={mapping.has_header ? '1' : '0'}
          onChange={(event) => update({ has_header: event.target.value === '1' })}
        />
      </div>

      <p className="text-[11px] leading-relaxed text-ink-muted">
        Deixe <strong className="font-semibold text-ink-soft">Entrada (crédito)</strong> em branco
        quando o arquivo trouxer um único valor com sinal. Preencha quando ele separar débito e
        crédito em duas colunas.
      </p>
    </div>
  )
}
