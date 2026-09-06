import { zodResolver } from '@hookform/resolvers/zod'
import { format } from 'date-fns'
import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { useTransactionOptions } from '@/features/transactions/api'
import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import type { GoalRecord } from '@/types/api'

import { useAddContribution } from '../api'

const schema = z.object({
  amount: z.string().refine((value) => Number(value) > 0, 'Informe um valor maior que zero.'),
  contributed_at: z.string().min(1, 'Informe a data.'),
  source_account_id: z.string(),
})

type FormValues = z.infer<typeof schema>

const formFields = ['amount', 'contributed_at', 'source_account_id'] as const

interface Props {
  isOpen: boolean
  onClose: () => void
  goal: GoalRecord | null
  onSaved: (message: string) => void
}

/**
 * Novo aporte. Meta com conta vinculada exige de onde o dinheiro sai — vira
 * uma transferencia real. Sem conta vinculada, e so um registro de progresso.
 */
export function ContributionModal({ isOpen, onClose, goal, onSaved }: Props) {
  const { data: options } = useTransactionOptions()
  const add = useAddContribution()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyValues(),
  })

  useEffect(() => {
    if (!isOpen) {
      return
    }

    setFormError(null)
    reset(emptyValues())
  }, [isOpen, reset])

  const needsSourceAccount = goal?.account_id !== null && goal !== null

  const sourceOptions = useMemo(
    () => (options?.accounts ?? [])
      .filter((account) => account.id !== goal?.account_id)
      .map((account) => ({ value: account.id.toString(), label: `${account.nickname} · ${account.bank}` })),
    [options, goal],
  )

  async function onSubmit(values: FormValues) {
    if (goal === null) {
      return
    }

    setFormError(null)

    try {
      await add.mutateAsync({
        goalId: goal.id,
        payload: {
          amount: Number(values.amount),
          contributed_at: values.contributed_at,
          source_account_id: values.source_account_id ? Number(values.source_account_id) : null,
        },
      })
      onSaved('Aporte registrado.')
      onClose()
    } catch (error) {
      const fields = apiFieldErrors(error)

      Object.entries(fields).forEach(([field, message]) => {
        if ((formFields as readonly string[]).includes(field)) {
          setError(field as keyof FormValues, { message })
        }
      })

      if (Object.keys(fields).length === 0) {
        setFormError(apiErrorMessage(error, 'Não foi possível registrar o aporte.'))
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Novo aporte"
      subtitle={goal ? `Meta: ${goal.name}` : undefined}
      className="max-w-[420px]"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="contribution-form" isLoading={isSubmitting}>
            Registrar aporte
          </Button>
        </>
      }
    >
      <form id="contribution-form" onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
        {needsSourceAccount && sourceOptions.length === 0 ? (
          <p className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
            Cadastre outra conta antes: o aporte precisa de uma origem diferente da conta da meta.
          </p>
        ) : null}

        <div className="grid grid-cols-2 gap-3.5">
          <Field
            label="Valor (R$)"
            type="number"
            step="0.01"
            inputMode="decimal"
            placeholder="0,00"
            autoFocus
            error={errors.amount?.message}
            {...register('amount')}
          />

          <Field label="Data" type="date" error={errors.contributed_at?.message} {...register('contributed_at')} />
        </div>

        {needsSourceAccount ? (
          <Select
            label="De onde sai o dinheiro"
            placeholder="Escolha a conta de origem"
            options={sourceOptions}
            error={errors.source_account_id?.message}
            {...register('source_account_id')}
          />
        ) : (
          <p className="text-[11px] leading-relaxed text-ink-muted">
            Esta meta não tem conta vinculada — o aporte só atualiza o progresso, sem mover
            dinheiro entre contas.
          </p>
        )}

        {formError ? (
          <p
            role="alert"
            className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light"
          >
            {formError}
          </p>
        ) : null}
      </form>
    </Modal>
  )
}

function emptyValues(): FormValues {
  return { amount: '', contributed_at: format(new Date(), 'yyyy-MM-dd'), source_account_id: '' }
}
