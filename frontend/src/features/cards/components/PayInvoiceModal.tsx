import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Money } from '@/components/ui/Money'
import { Select } from '@/components/ui/Select'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { capitalize, formatCompetence, formatDate } from '@/lib/format'
import type { CardOptions, InvoiceDetail } from '@/types/api'

import { usePayInvoice } from '../api'

const schema = z.object({
  account_id: z.string().min(1, 'Escolha a conta que vai pagar.'),
  amount: z
    .string()
    .min(1, 'Informe o valor.')
    .refine((value) => Number(value) > 0, 'O valor precisa ser maior que zero.'),
  paid_at: z.string().min(1, 'Informe a data do pagamento.'),
})

type FormValues = z.infer<typeof schema>

const formFields = ['account_id', 'amount', 'paid_at'] as const

interface Props {
  invoice: InvoiceDetail | null
  options?: CardOptions
  today: string
  defaultAccountId: number | null
  onClose: () => void
  onPaid: (message: string) => void
}

/**
 * Pagar a fatura. O valor abre com o saldo devedor inteiro, que é o caso
 * comum; diminuí-lo registra pagamento parcial e a fatura segue devendo a
 * diferença.
 */
export function PayInvoiceModal({
  invoice,
  options,
  today,
  defaultAccountId,
  onClose,
  onPaid,
}: Props) {
  const pay = usePayInvoice()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    setError,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { account_id: '', amount: '', paid_at: today },
  })

  useEffect(() => {
    if (invoice === null) {
      return
    }

    setFormError(null)
    reset({
      account_id: defaultAccountId?.toString() ?? '',
      amount: invoice.remaining.toFixed(2),
      paid_at: today,
    })
  }, [invoice, defaultAccountId, today, reset])

  const amount = Number(watch('amount'))
  const isPartial = invoice !== null && amount > 0 && amount < invoice.remaining

  async function onSubmit(values: FormValues) {
    if (invoice === null) {
      return
    }

    setFormError(null)

    try {
      await pay.mutateAsync({
        id: invoice.id,
        payload: {
          account_id: Number(values.account_id),
          amount: Number(values.amount),
          paid_at: values.paid_at,
        },
      })

      onPaid(isPartial ? 'Pagamento parcial registrado.' : 'Fatura paga.')
      onClose()
    } catch (error) {
      const fields = apiFieldErrors(error)

      Object.entries(fields).forEach(([field, message]) => {
        if ((formFields as readonly string[]).includes(field)) {
          setError(field as keyof FormValues, { message })
        }
      })

      if (Object.keys(fields).length === 0) {
        setFormError(apiErrorMessage(error, 'Não foi possível registrar o pagamento.'))
      }
    }
  }

  return (
    <Modal
      isOpen={invoice !== null}
      onClose={onClose}
      title="Pagar fatura"
      subtitle={
        invoice
          ? `${invoice.card} · ${capitalize(formatCompetence(invoice.reference_month))} · vence ${formatDate(invoice.due_date)}`
          : undefined
      }
      className="max-w-[480px]"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="pay-invoice-form" isLoading={isSubmitting}>
            {isPartial ? 'Registrar pagamento parcial' : 'Pagar'}
          </Button>
        </>
      }
    >
      {invoice ? (
        <form
          id="pay-invoice-form"
          onSubmit={handleSubmit(onSubmit)}
          className="flex flex-col gap-4"
          noValidate
        >
          <div className="flex items-center gap-3 rounded-[11px] border border-hairline bg-surface-alt px-3.5 py-3">
            <span className="text-[11.5px] font-semibold text-ink-soft">Saldo devedor</span>
            <Money value={invoice.remaining} className="ml-auto text-[16px] font-bold text-ink" />
          </div>

          <Select
            label="Conta"
            placeholder="Selecione a conta"
            options={(options?.accounts ?? []).map((account) => ({
              value: account.id.toString(),
              label: `${account.nickname} · ${account.bank}`,
            }))}
            error={errors.account_id?.message}
            {...register('account_id')}
          />

          <div className="grid grid-cols-2 gap-3.5">
            <Field
              label="Valor (R$)"
              type="number"
              step="0.01"
              min="0.01"
              inputMode="decimal"
              error={errors.amount?.message}
              {...register('amount')}
            />
            <Field
              label="Data do pagamento"
              type="date"
              error={errors.paid_at?.message}
              {...register('paid_at')}
            />
          </div>

          {isPartial ? (
            <p className="rounded-[11px] border border-purple/25 bg-purple/[0.07] px-3.5 py-2.5 text-[11.5px] text-purple-light">
              Pagamento parcial: a fatura continua devendo{' '}
              {(invoice.remaining - amount).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL',
              })}{' '}
              e o limite correspondente segue ocupado.
            </p>
          ) : (
            <p className="rounded-[11px] border border-hairline bg-surface-alt px-3.5 py-2.5 text-[11.5px] text-ink-soft">
              O pagamento debita a conta escolhida, mas não conta como despesa do mês — as compras
              já foram lançadas na competência de cada parcela.
            </p>
          )}

          {formError ? (
            <p role="alert" className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
              {formError}
            </p>
          ) : null}
        </form>
      ) : null}
    </Modal>
  )
}
