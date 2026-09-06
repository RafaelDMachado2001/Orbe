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
import { formatCompetence } from '@/lib/format'
import type { BudgetStatus } from '@/types/api'

import { useCreateBudget, useUpdateBudget, type BudgetPayload } from '../api'

const schema = z.object({
  category_id: z.string().min(1, 'Escolha a categoria.'),
  // Texto, como nos outros formularios de valor: abre vazio em vez de com zero.
  limit_amount: z
    .string()
    .refine((value) => Number(value) > 0, 'Informe um limite maior que zero.'),
})

type FormValues = z.infer<typeof schema>

const formFields = ['category_id', 'limit_amount'] as const

interface Props {
  isOpen: boolean
  onClose: () => void
  /** Null cadastra; um orcamento abre o registro para edicao. */
  budget: BudgetStatus | null
  /** Mes de referencia vindo da pagina — o orcamento e sempre do mes que esta sendo navegado. */
  month: string
  onSaved: (message: string) => void
}

/**
 * Cadastro e edicao de orcamento.
 *
 * So categorias de despesa entram na lista: a API recusa categoria de
 * receita. A subcategoria aparece com o nome da mae na frente, igual ao
 * seletor do formulario de lancamentos — sem isso, duas "Aluguel" em arvores
 * diferentes seriam indistinguiveis.
 */
export function BudgetFormModal({ isOpen, onClose, budget, month, onSaved }: Props) {
  const isEditing = budget !== null

  const { data: options } = useTransactionOptions()
  const create = useCreateBudget()
  const update = useUpdateBudget()
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
    reset(budget === null ? emptyValues() : toFormValues(budget))
  }, [isOpen, budget, reset])

  const categoryOptions = useMemo(() => {
    const all = options?.categories ?? []
    const nameOf = new Map(all.map((category) => [category.id, category.name]))

    return all
      .filter((category) => category.type === 'despesa')
      .map((category) => ({
        value: category.id.toString(),
        label:
          category.parent_id === null
            ? category.name
            : `${nameOf.get(category.parent_id) ?? '—'} › ${category.name}`,
      }))
      .sort((a, b) => a.label.localeCompare(b.label, 'pt-BR'))
  }, [options])

  async function onSubmit(values: FormValues) {
    setFormError(null)

    const payload: BudgetPayload = {
      category_id: Number(values.category_id),
      reference_month: month,
      limit_amount: Number(values.limit_amount),
    }

    try {
      if (isEditing && budget !== null) {
        await update.mutateAsync({ id: budget.id, payload })
        onSaved('Orçamento atualizado.')
      } else {
        await create.mutateAsync(payload)
        onSaved('Orçamento cadastrado.')
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
        setFormError(apiErrorMessage(error, 'Não foi possível salvar o orçamento.'))
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Editar orçamento' : 'Novo orçamento'}
      subtitle={`Limite de gasto para ${formatCompetence(month)}`}
      className="max-w-[440px]"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="budget-form" isLoading={isSubmitting}>
            {isEditing ? 'Salvar alterações' : 'Cadastrar orçamento'}
          </Button>
        </>
      }
    >
      <form id="budget-form" onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
        {categoryOptions.length === 0 ? (
          <p className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
            Cadastre uma categoria de despesa antes de criar um orçamento.
          </p>
        ) : null}

        <Select
          label="Categoria"
          placeholder="Escolha a categoria"
          options={categoryOptions}
          error={errors.category_id?.message}
          {...register('category_id')}
        />

        <Field
          label="Limite (R$)"
          type="number"
          step="0.01"
          inputMode="decimal"
          placeholder="0,00"
          autoFocus
          error={errors.limit_amount?.message}
          {...register('limit_amount')}
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
    </Modal>
  )
}

function emptyValues(): FormValues {
  return { category_id: '', limit_amount: '' }
}

function toFormValues(budget: BudgetStatus): FormValues {
  return {
    category_id: budget.category_id.toString(),
    limit_amount: budget.limit_amount.toString(),
  }
}
