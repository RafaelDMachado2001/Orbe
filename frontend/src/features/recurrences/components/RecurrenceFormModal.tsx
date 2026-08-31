import { zodResolver } from '@hookform/resolvers/zod'
import { Info } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import type { RecurrenceOptions, RecurrenceRecord } from '@/types/api'

import { useCreateRecurrence, useRecurrence, useUpdateRecurrence, type RecurrencePayload } from '../api'
import { Chip } from '@/features/transactions/components/Chip'

const schema = z
  .object({
    source_kind: z.enum(['conta', 'cartao']),
    type: z.enum(['despesa', 'receita']),
    description: z
      .string()
      .trim()
      .min(2, 'Descreva a despesa fixa.')
      .max(120, 'Máximo de 120 caracteres.'),
    amount: z
      .string()
      .min(1, 'Informe o valor.')
      .refine((value) => Number(value) > 0, 'O valor precisa ser maior que zero.'),
    frequency: z.enum(['diaria', 'semanal', 'mensal', 'anual']),
    interval: z.string().min(1, 'Informe o intervalo.'),
    day_of_month: z.string(),
    starts_on: z.string().min(1, 'Informe a partir de quando vale.'),
    ends_on: z.string(),
    category_id: z.string(),
    account_id: z.string(),
    credit_card_id: z.string(),
    method: z.string(),
  })
  .superRefine((values, ctx) => {
    if (values.frequency === 'mensal' && values.day_of_month === '') {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['day_of_month'],
        message: 'Escolha o dia do mês.',
      })
    }

    if (values.source_kind === 'conta' && values.account_id === '') {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['account_id'], message: 'Escolha a conta.' })
    }

    if (values.source_kind === 'cartao' && values.credit_card_id === '') {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['credit_card_id'],
        message: 'Escolha o cartão.',
      })
    }

    if (values.ends_on !== '' && values.ends_on < values.starts_on) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['ends_on'],
        message: 'O fim não pode ser anterior ao início.',
      })
    }
  })

type FormValues = z.infer<typeof schema>

const formFields = [
  'description',
  'amount',
  'type',
  'frequency',
  'interval',
  'day_of_month',
  'starts_on',
  'ends_on',
  'category_id',
  'account_id',
  'credit_card_id',
  'method',
] as const

const emptyValues = (today: string): FormValues => ({
  source_kind: 'conta',
  type: 'despesa',
  description: '',
  amount: '',
  frequency: 'mensal',
  interval: '1',
  day_of_month: '',
  starts_on: today,
  ends_on: '',
  category_id: '',
  account_id: '',
  credit_card_id: '',
  method: '',
})

/** Dias 29 a 31 não existem em todo mês; a regra pularia os meses curtos. */
const monthDays = Array.from({ length: 28 }, (_, index) => ({
  value: (index + 1).toString(),
  label: `Dia ${index + 1}`,
}))

interface Props {
  isOpen: boolean
  onClose: () => void
  recurrenceId: number | null
  options?: RecurrenceOptions
  today: string
  onSaved: (message: string) => void
}

export function RecurrenceFormModal({
  isOpen,
  onClose,
  recurrenceId,
  options,
  today,
  onSaved,
}: Props) {
  const isEditing = recurrenceId !== null
  const { data: existing, isPending: isLoadingExisting } = useRecurrence(isOpen ? recurrenceId : null)

  const create = useCreateRecurrence()
  const update = useUpdateRecurrence()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyValues(today),
  })

  const sourceKind = watch('source_kind')
  const type = watch('type')
  const frequency = watch('frequency')

  useEffect(() => {
    if (!isOpen) {
      return
    }

    setFormError(null)

    if (!isEditing) {
      reset(emptyValues(today))

      return
    }

    if (existing) {
      reset(toFormValues(existing))
    }
  }, [isOpen, isEditing, existing, reset, today])

  // Cartão de crédito não recebe receita: trocar para cartão força despesa.
  useEffect(() => {
    if (sourceKind === 'cartao' && type === 'receita') {
      setValue('type', 'despesa')
    }
  }, [sourceKind, type, setValue])

  const categories = useMemo(
    () =>
      (options?.categories ?? [])
        .filter((category) => category.type === type)
        .map((category) => ({ value: category.id.toString(), label: category.name })),
    [options, type],
  )

  // Trocar entre receita e despesa invalida a categoria da aba anterior.
  useEffect(() => {
    const current = watch('category_id')

    if (current !== '' && !categories.some((option) => option.value === current)) {
      setValue('category_id', '')
    }
  }, [categories, setValue, watch])

  async function onSubmit(values: FormValues) {
    setFormError(null)

    try {
      if (isEditing && recurrenceId !== null) {
        await update.mutateAsync({ id: recurrenceId, payload: toPayload(values) })
        onSaved('Regra atualizada.')
      } else {
        await create.mutateAsync(toPayload(values))
        onSaved('Regra cadastrada.')
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
        setFormError(apiErrorMessage(error, 'Não foi possível salvar a regra.'))
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Editar regra fixa' : 'Nova despesa fixa'}
      subtitle="A regra descreve o que se repete. O lançamento no extrato é um passo à parte."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="recurrence-form" isLoading={isSubmitting}>
            {isEditing ? 'Salvar alterações' : 'Cadastrar'}
          </Button>
        </>
      }
    >
      {isEditing && isLoadingExisting ? (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 5 }).map((_, index) => (
            <Skeleton key={index} className="h-[52px] w-full" />
          ))}
        </div>
      ) : (
        <form
          id="recurrence-form"
          onSubmit={handleSubmit(onSubmit)}
          className="flex flex-col gap-4"
          noValidate
        >
          <div className="flex flex-wrap items-center gap-4">
            <div role="group" aria-label="Natureza" className="flex gap-1.5">
              <Chip isActive={type === 'despesa'} onClick={() => setValue('type', 'despesa')}>
                Despesa
              </Chip>
              <Chip
                isActive={type === 'receita'}
                onClick={() => setValue('type', 'receita')}
                title={
                  sourceKind === 'cartao' ? 'Cartão de crédito não recebe receita' : undefined
                }
              >
                Receita
              </Chip>
            </div>

            <div role="group" aria-label="Origem" className="flex gap-1.5">
              <Chip
                isActive={sourceKind === 'conta'}
                onClick={() => setValue('source_kind', 'conta')}
              >
                Em conta
              </Chip>
              <Chip
                isActive={sourceKind === 'cartao'}
                onClick={() => setValue('source_kind', 'cartao')}
              >
                No cartão
              </Chip>
            </div>
          </div>

          <Field
            label="Descrição"
            placeholder="Aluguel do apartamento"
            autoFocus
            error={errors.description?.message}
            {...register('description')}
          />

          <div className="grid grid-cols-2 gap-3.5">
            <Field
              label="Valor (R$)"
              type="number"
              step="0.01"
              min="0.01"
              inputMode="decimal"
              placeholder="0,00"
              error={errors.amount?.message}
              {...register('amount')}
            />

            {sourceKind === 'conta' ? (
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
            ) : (
              <Select
                label="Cartão"
                placeholder="Selecione o cartão"
                options={(options?.cards ?? []).map((card) => ({
                  value: card.id.toString(),
                  label: `${card.nickname} ···· ${card.last_four}`,
                }))}
                error={errors.credit_card_id?.message}
                {...register('credit_card_id')}
              />
            )}
          </div>

          <div className="grid grid-cols-3 gap-3.5">
            <Select
              label="Frequência"
              options={options?.frequencies ?? []}
              error={errors.frequency?.message}
              {...register('frequency')}
            />
            <Select
              label="A cada"
              options={Array.from({ length: 24 }, (_, index) => ({
                value: (index + 1).toString(),
                label: (index + 1).toString(),
              }))}
              error={errors.interval?.message}
              {...register('interval')}
            />
            {frequency === 'mensal' ? (
              <Select
                label="Dia do mês"
                placeholder="Dia"
                options={monthDays}
                error={errors.day_of_month?.message}
                {...register('day_of_month')}
              />
            ) : (
              <Select
                label="Categoria"
                placeholder="Sem categoria"
                options={categories}
                error={errors.category_id?.message}
                {...register('category_id')}
              />
            )}
          </div>

          <div className="grid grid-cols-2 gap-3.5">
            <Field
              label="Vale a partir de"
              type="date"
              error={errors.starts_on?.message}
              {...register('starts_on')}
            />
            <Field
              label="Até (opcional)"
              type="date"
              error={errors.ends_on?.message}
              {...register('ends_on')}
            />
          </div>

          <div
            className={cn(
              'grid gap-3.5',
              frequency === 'mensal' && sourceKind === 'conta' ? 'grid-cols-2' : 'grid-cols-1',
            )}
          >
            {frequency === 'mensal' ? (
              <Select
                label="Categoria"
                placeholder="Sem categoria"
                options={categories}
                error={errors.category_id?.message}
                {...register('category_id')}
              />
            ) : null}

            {sourceKind === 'conta' ? (
              <Select
                label="Forma de pagamento"
                placeholder="Não informar"
                options={(options?.methods ?? []).filter((method) => method.value !== 'credito')}
                error={errors.method?.message}
                {...register('method')}
              />
            ) : null}
          </div>

          {sourceKind === 'cartao' ? (
            <p className="flex items-start gap-2 rounded-[11px] border border-purple/25 bg-purple/[0.07] px-3.5 py-2.5 text-[11.5px] leading-relaxed text-purple-light">
              <Info className="mt-px size-3.5 shrink-0" aria-hidden="true" />
              Cada mês vira uma compra de 1x na fatura do cartão. O dinheiro só sai da conta quando
              a fatura for paga.
            </p>
          ) : null}

          {formError ? (
            <p role="alert" className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
              {formError}
            </p>
          ) : null}
        </form>
      )}
    </Modal>
  )
}

function toFormValues(record: RecurrenceRecord): FormValues {
  return {
    source_kind: record.source_kind,
    type: record.type,
    description: record.description,
    amount: record.amount.toString(),
    frequency: record.frequency,
    interval: record.interval.toString(),
    day_of_month: record.day_of_month?.toString() ?? '',
    starts_on: record.starts_on,
    ends_on: record.ends_on ?? '',
    category_id: record.category_id?.toString() ?? '',
    account_id: record.account_id?.toString() ?? '',
    credit_card_id: record.credit_card_id?.toString() ?? '',
    method: record.method ?? '',
  }
}

/**
 * A API recusa campo que não pertence à origem escolhida, então o payload leva
 * só o lado que se aplica.
 */
function toPayload(values: FormValues): RecurrencePayload {
  const isOnCard = values.source_kind === 'cartao'

  return {
    source_kind: values.source_kind,
    description: values.description.trim(),
    amount: Number(values.amount),
    type: values.type,
    frequency: values.frequency,
    interval: Number(values.interval),
    day_of_month: values.frequency === 'mensal' ? Number(values.day_of_month) : null,
    starts_on: values.starts_on,
    ends_on: values.ends_on === '' ? null : values.ends_on,
    category_id: values.category_id === '' ? null : Number(values.category_id),
    account_id: isOnCard ? null : Number(values.account_id),
    credit_card_id: isOnCard ? Number(values.credit_card_id) : null,
    method: isOnCard || values.method === '' ? null : values.method,
  }
}
