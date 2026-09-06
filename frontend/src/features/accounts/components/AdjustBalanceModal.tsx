import { zodResolver } from '@hookform/resolvers/zod'
import { Scale } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Textarea } from '@/components/ui/Textarea'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatBRL } from '@/lib/format'
import type { AccountRow } from '@/types/api'

import { useAdjustBalance, type AdjustBalancePayload } from '../api'

const schema = z.object({
  balance: z
    .string()
    .min(1, 'Informe o saldo que o banco mostra.')
    .refine((value) => Number.isFinite(Number(value)), 'Informe um valor.'),
  date: z.string().min(1, 'Informe a data.'),
  notes: z.string().max(255, 'Máximo de 255 caracteres.'),
})

type FormValues = z.infer<typeof schema>

const formFields = ['balance', 'date', 'notes'] as const

interface Props {
  /** Null mantém o diálogo fechado; uma conta abre a conciliação dela. */
  account: AccountRow | null
  today: string
  onClose: () => void
  onAdjusted: (message: string) => void
}

/**
 * Conciliação com o extrato do banco.
 *
 * A pessoa informa o saldo que o banco mostra, não a diferença — a diferença é
 * o que a API calcula e registra como lançamento. Isso mantém o histórico
 * explicável: a correção aparece no extrato, na data em que foi feita, e pode
 * ser desfeita como qualquer outro lançamento.
 *
 * A data importa: um ajuste datado no mês passado é comparado com o saldo
 * daquele dia, não com o de hoje.
 */
export function AdjustBalanceModal({ account, today, onClose, onAdjusted }: Props) {
  const adjust = useAdjustBalance()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { balance: '', date: today, notes: '' },
  })

  const typed = watch('balance')

  useEffect(() => {
    if (account === null) {
      return
    }

    setFormError(null)
    reset({ balance: '', date: today, notes: '' })
  }, [account, today, reset])

  // A prévia usa o saldo de hoje. Com data no passado o número final pode
  // diferir, e o texto avisa em vez de prometer o que não vai acontecer.
  const difference =
    account === null || typed.trim() === '' || !Number.isFinite(Number(typed))
      ? null
      : Number((Number(typed) - account.balance).toFixed(2))

  async function onSubmit(values: FormValues) {
    if (account === null) {
      return
    }

    setFormError(null)

    const notes = values.notes.trim()

    const payload: AdjustBalancePayload = {
      balance: Number(values.balance),
      date: values.date,
      notes: notes === '' ? null : notes,
    }

    try {
      await adjust.mutateAsync({ id: account.id, payload })
      onAdjusted('Saldo ajustado.')
      onClose()
    } catch (error) {
      const fields = apiFieldErrors(error)

      Object.entries(fields).forEach(([field, message]) => {
        if ((formFields as readonly string[]).includes(field)) {
          setError(field as keyof FormValues, { message })
        }
      })

      if (Object.keys(fields).length === 0) {
        setFormError(apiErrorMessage(error, 'Não foi possível ajustar o saldo.'))
      }
    }
  }

  return (
    <Modal
      isOpen={account !== null}
      onClose={onClose}
      title="Ajustar saldo"
      subtitle="A diferença entra como um lançamento, para o histórico continuar explicável."
      className="max-w-[460px]"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="adjust-form" isLoading={isSubmitting}>
            Lançar ajuste
          </Button>
        </>
      }
    >
      {account ? (
        <form
          id="adjust-form"
          onSubmit={handleSubmit(onSubmit)}
          className="flex flex-col gap-4"
          noValidate
        >
          <div className="flex gap-3.5 rounded-[12px] border border-hairline bg-surface-alt px-3.5 py-3">
            <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-purple/[0.14] text-purple-light">
              <Scale className="size-4" aria-hidden="true" />
            </span>
            <div className="flex min-w-0 flex-col gap-0.5">
              <span className="truncate text-[12.5px] font-semibold text-ink">
                {account.nickname} · {account.bank.name}
              </span>
              <span className="text-[11.5px] font-medium text-ink-muted">
                O app calcula {formatBRL(account.balance)} hoje
              </span>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3.5">
            <Field
              label="Saldo no banco (R$)"
              type="number"
              step="0.01"
              inputMode="decimal"
              placeholder="0,00"
              autoFocus
              error={errors.balance?.message}
              {...register('balance')}
            />

            <Field
              label="Data do ajuste"
              type="date"
              max={today}
              error={errors.date?.message}
              {...register('date')}
            />
          </div>

          {difference !== null ? (
            <p
              className={cn(
                'rounded-[11px] border px-3.5 py-2.5 text-[11.5px] leading-relaxed',
                difference === 0
                  ? 'border-hairline bg-surface-alt text-ink-soft'
                  : difference > 0
                    ? 'border-green/25 bg-green/[0.07] text-green-bright'
                    : 'border-orange/25 bg-orange/[0.07] text-orange-light',
              )}
            >
              {difference === 0
                ? 'Esse é o saldo que a conta já tem — não há diferença a lançar.'
                : `Vai lançar ${difference > 0 ? 'uma entrada' : 'uma saída'} de ${formatBRL(Math.abs(difference))} na categoria Ajuste de saldo.`}
            </p>
          ) : null}

          <Textarea
            label="Observação (opcional)"
            placeholder="Conferido no app do banco"
            rows={2}
            error={errors.notes?.message}
            {...register('notes')}
          />

          {formError ? (
            <p
              role="alert"
              className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light"
            >
              {formError}
            </p>
          ) : null}
        </form>
      ) : null}
    </Modal>
  )
}
