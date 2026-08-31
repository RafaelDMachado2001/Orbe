import { FileSpreadsheet, FileUp, X } from 'lucide-react'
import { useRef, useState, type DragEvent } from 'react'

import { Card, CardHeader } from '@/components/ui/Card'
import { Select } from '@/components/ui/Select'
import { cn } from '@/lib/cn'
import type { TransactionOptions } from '@/types/api'

/** Extensões que a API sabe ler. `.txt` existe porque banco exporta OFX assim. */
const ACCEPTED = '.ofx,.qfx,.csv,.txt'

interface Props {
  options: TransactionOptions | undefined
  /** Destino codificado como `conta:12` ou `cartao:3`. */
  destination: string
  onDestinationChange: (value: string) => void
  file: File | null
  onFileChange: (file: File | null) => void
  isReading: boolean
}

/**
 * Primeiro passo: para onde vai e qual arquivo.
 *
 * O destino vem antes do arquivo porque é ele que define a leitura — a mesma
 * linha de crédito é receita numa conta e estorno numa fatura, e a checagem de
 * repetidos só faz sentido contra um destino conhecido.
 */
export function UploadPanel({
  options,
  destination,
  onDestinationChange,
  file,
  onFileChange,
  isReading,
}: Props) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [isDragging, setIsDragging] = useState(false)

  const destinations = [
    ...(options?.accounts ?? []).map((account) => ({
      value: `conta:${account.id}`,
      label: `${account.nickname} · ${account.bank}`,
    })),
    ...(options?.cards ?? []).map((card) => ({
      value: `cartao:${card.id}`,
      label: `${card.nickname} · •••• ${card.last_four}`,
    })),
  ]

  function handleDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault()
    setIsDragging(false)

    const dropped = event.dataTransfer.files[0]

    if (dropped) {
      onFileChange(dropped)
    }
  }

  return (
    <Card className="flex flex-col gap-4">
      <CardHeader
        title="Escolha o destino e o arquivo"
        subtitle="Exporte o extrato em OFX ou CSV no internet banking. Nada é gravado antes de você conferir."
      />

      <Select
        label="Conta ou cartão que vai receber os lançamentos"
        options={destinations}
        placeholder={destinations.length === 0 ? 'Nenhuma conta ou cartão cadastrado' : 'Selecione…'}
        value={destination}
        disabled={destinations.length === 0}
        onChange={(event) => onDestinationChange(event.target.value)}
      />

      <div
        onDragOver={(event) => {
          event.preventDefault()
          setIsDragging(true)
        }}
        onDragLeave={() => setIsDragging(false)}
        onDrop={handleDrop}
        className={cn(
          'flex flex-col items-center gap-3 rounded-[14px] border border-dashed px-6 py-8 text-center transition-colors',
          isDragging ? 'border-purple/60 bg-purple/[0.06]' : 'border-hairline',
        )}
      >
        <input
          ref={inputRef}
          type="file"
          accept={ACCEPTED}
          className="sr-only"
          onChange={(event) => onFileChange(event.target.files?.[0] ?? null)}
        />

        {file ? (
          <div className="flex w-full items-center gap-3 rounded-[12px] border border-hairline bg-surface-alt px-3.5 py-2.5 text-left">
            <span className="grid size-8 shrink-0 place-items-center rounded-[10px] bg-purple/[0.14] text-purple-light">
              <FileSpreadsheet className="size-4" aria-hidden="true" />
            </span>
            <div className="flex min-w-0 flex-col">
              <span className="truncate text-[12.5px] font-semibold text-ink">{file.name}</span>
              <span className="text-[11px] font-medium text-ink-muted">
                {isReading ? 'Lendo o arquivo…' : `${Math.max(1, Math.round(file.size / 1024))} KB`}
              </span>
            </div>
            <button
              type="button"
              onClick={() => {
                onFileChange(null)

                if (inputRef.current) {
                  inputRef.current.value = ''
                }
              }}
              aria-label="Remover arquivo"
              className="ml-auto grid size-7 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-white/[0.06] hover:text-ink"
            >
              <X className="size-3.5" aria-hidden="true" />
            </button>
          </div>
        ) : (
          <>
            <FileUp className="size-6 text-ink-faint" aria-hidden="true" />
            <div>
              <p className="text-[13px] font-semibold text-ink">Arraste o extrato aqui</p>
              <p className="mt-1 text-[11.5px] leading-relaxed text-ink-muted">
                OFX, QFX ou CSV, até 4 MB. O OFX dispensa configuração; o CSV pede
                que você confirme de qual coluna sai cada campo.
              </p>
            </div>
            <button
              type="button"
              onClick={() => inputRef.current?.click()}
              className="rounded-[11px] border border-hairline bg-surface-raised px-4 py-[9px] text-[12.5px] font-semibold text-[#C3C9D2] transition-colors hover:border-hairline-strong"
            >
              Escolher arquivo
            </button>
          </>
        )}
      </div>
    </Card>
  )
}
