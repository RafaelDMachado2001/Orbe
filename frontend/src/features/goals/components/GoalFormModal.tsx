import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { useTransactionOptions } from '@/features/transactions/api'
import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { formatBRL } from '@/lib/format'
import type { GoalRecord } from '@/types/api'

import { useCreateGoal, useUpdateGoal, type GoalPayload } from '../api'

const schema = z.object({
  name: z.string().trim().min(2, 'Dê um nome à meta.').max(80, 'Máximo de 80 caracteres.'),
  target_amount: z.string().refine((value) => Number(value) > 0, 'Informe um valor alvo maior que zero.'),
  initial_amount: z
    .string()
    .refine((value) => value.trim() === '' || Number.isFinite(Number(value)), 'Informe um valor.'),
  deadline: z.string(),
  account_id: z.string(),
})

type FormValues = z.infer<typeof schema>

const formFields = ['name', 'target_amount', 'initial_amount', 'deadline', 'account_id'] as const

interface Props {
  isOpen: boolean
  onClose: () => void
  /** Null cadastra; uma meta abre o registro para edicao. */
  goal: GoalRecord | null
  onSaved: (message: string) => void
}

/**
 * Cadastro e edicao de meta.
 *
 * O valor inicial so aparece na criacao: dali em diante o progresso anda por
 * aporte (tela da meta), nunca por edicao direta — mesmo raciocinio do saldo
 * inicial de uma conta.
 */
export function GoalFormModal({ isOpen, onClose, goal, onSaved }: Props) {
  const isEditing = goal !== null

  const { data: options } = useTransactionOptions()
  const create = useCreateGoal()
  const update = useUpdateGoal()
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
    reset(goal === null ? emptyValues() : toFormValues(goal))
  }, [isOpen, goal, reset])

  const accountOptions = useMemo(
    () => (options?.accounts ?? []).map((account) => ({
      value: account.id.toString(),
      label: `${account.nickname} · ${account.bank}`,
    })),
    [options],
  )

  async function onSubmit(values: FormValues) {
    setFormError(null)

    const payload: GoalPayload = {
      name: values.name.trim(),
      target_amount: Number(values.target_amount),
      deadline: values.deadline || null,
      account_id: values.account_id ? Number(values.account_id) : null,
    }

    try {
      if (isEditing && goal !== null) {
        await update.mutateAsync({ id: goal.id, payload })
        onSaved('Meta atualizada.')
      } else {
        await create.mutateAsync({ ...payload, initial_amount: Number(values.initial_amount || 0) })
        onSaved('Meta cadastrada.')
      }

      onClose()
    } catch (error) {
      const fields = apiFieldErrors(error)

      Object.entries(fields).forEach(([field, message]) => {
        if ((formFields as readonly string[]).includes(field)) {
          setError(field as keyof FormValues, { message })
        }
      })

      if (Object.keys(fields).length === 0) {
        setFormError(apiErrorMessage(error, 'Não foi possível salvar a meta.'))
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Editar meta' : 'Nova meta'}
      subtitle={
        isEditing
          ? 'Nome, valor alvo, prazo e conta. O valor inicial não muda depois da criação.'
          : 'O valor inicial é o quanto você já tem guardado — dali em diante o progresso anda por aporte.'
      }
      className="max-w-[480px]"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="goal-form" isLoading={isSubmitting}>
            {isEditing ? 'Salvar alterações' : 'Cadastrar meta'}
          </Button>
        </>
      }
    >
      <form id="goal-form" onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
        <Field label="Nome" placeholder="Reserva de emergência" autoFocus error={errors.name?.message} {...register('name')} />

        <div className="grid grid-cols-2 gap-3.5">
          <Field
            label="Valor alvo (R$)"
            type="number"
            step="0.01"
            inputMode="decimal"
            placeholder="0,00"
            error={errors.target_amount?.message}
            {...register('target_amount')}
          />

          <Field label="Prazo" type="date" error={errors.deadline?.message} {...register('deadline')} />
        </div>

        {isEditing ? (
          <p className="rounded-[11px] border border-hairline bg-surface-alt px-3.5 py-2.5 text-[11.5px] leading-relaxed text-ink-soft">
            Valor inicial: <strong className="font-semibold text-ink">{formatBRL(goal.initial_amount)}</strong>.
            Ele não muda depois da criação — o progresso anda pela tela de aportes.
          </p>
        ) : (
          <Field
            label="Valor inicial (R$)"
            type="number"
            step="0.01"
            inputMode="decimal"
            placeholder="0,00"
            error={errors.initial_amount?.message}
            {...register('initial_amount')}
          />
        )}

        <Select
          label="Conta vinculada"
          placeholder="Nenhuma"
          options={accountOptions}
          error={errors.account_id?.message}
          {...register('account_id')}
        />

        <p className="text-[11px] leading-relaxed text-ink-muted">
          Vinculando uma conta, cada aporte vira uma transferência de verdade para ela. Sem conta
          vinculada, o aporte é só um registro de progresso.
        </p>

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
  return { name: '', target_amount: '', initial_amount: '', deadline: '', account_id: '' }
}

function toFormValues(goal: GoalRecord): FormValues {
  return {
    name: goal.name,
    target_amount: goal.target_amount.toString(),
    initial_amount: goal.initial_amount.toString(),
    deadline: goal.deadline ?? '',
    account_id: goal.account_id?.toString() ?? '',
  }
}
