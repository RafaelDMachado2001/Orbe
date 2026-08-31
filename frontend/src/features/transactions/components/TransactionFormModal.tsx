import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { Textarea } from '@/components/ui/Textarea'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatBRL } from '@/lib/format'
import type { AmountMode, EntryKind, TransactionForm, TransactionOptions } from '@/types/api'

import {
  useCreateTransaction,
  useTransaction,
  useUpdateTransaction,
  type TransactionPayload,
} from '../api'
import { Chip } from './Chip'

/** Abas em que o lançamento pode ser dividido em N meses na conta. */
const planKinds: EntryKind[] = ['despesa', 'emprestimo']

const MAX_PLAN_INSTALLMENTS = 360

const schema = z.object({
  kind: z.enum(['receita', 'despesa', 'cartao', 'emprestimo', 'transferencia']),
  description: z.string().trim().min(2, 'Descreva o lançamento.').max(120, 'Máximo de 120 caracteres.'),
  // Texto, como todo campo do formulário: assim o input abre vazio em vez de
  // com um zero que o usuário precisa apagar antes de digitar.
  amount: z
    .string()
    .min(1, 'Informe o valor.')
    .refine((value) => Number(value) > 0, 'O valor precisa ser maior que zero.'),
  amount_mode: z.enum(['total', 'parcela']),
  competence_date: z.string().min(1, 'Informe a data.'),
  status: z.enum(['previsto', 'confirmado', 'cancelado']),
  category_id: z.string(),
  account_id: z.string(),
  method: z.string(),
  credit_card_id: z.string(),
  installments: z.string(),
  lender: z.string().max(80, 'Máximo de 80 caracteres.'),
  from_account_id: z.string(),
  to_account_id: z.string(),
  notes: z.string().max(500, 'Máximo de 500 caracteres.'),
}).superRefine((values, ctx) => {
  const required = (field: keyof typeof values, message: string) => {
    if (values[field] === '') {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: [field], message })
    }
  }

  if (values.kind === 'receita' || values.kind === 'despesa' || values.kind === 'emprestimo') {
    required('account_id', 'Escolha a conta do lançamento.')
  }

  if (values.kind === 'cartao') {
    required('credit_card_id', 'Escolha o cartão da compra.')
    required('installments', 'Informe o número de parcelas.')
  }

  if (values.kind === 'emprestimo') {
    required('lender', 'Informe quem concedeu o empréstimo.')
  }

  if (planKinds.includes(values.kind)) {
    const installments = Number(values.installments)

    if (
      values.installments === '' ||
      !Number.isInteger(installments) ||
      installments < 1 ||
      installments > MAX_PLAN_INSTALLMENTS
    ) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['installments'],
        message: `O parcelamento aceita de 1 a ${MAX_PLAN_INSTALLMENTS} parcelas.`,
      })
    }
  }

  if (values.kind === 'transferencia') {
    required('from_account_id', 'Escolha a conta de origem.')
    required('to_account_id', 'Escolha a conta de destino.')

    if (values.from_account_id !== '' && values.from_account_id === values.to_account_id) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['to_account_id'],
        message: 'A conta de destino precisa ser diferente da origem.',
      })
    }
  }
})

type FormValues = z.infer<typeof schema>

/** Campos que a tela sabe destacar quando a API devolve erro de validação. */
const formFields = [
  'description',
  'amount',
  'amount_mode',
  'competence_date',
  'status',
  'category_id',
  'account_id',
  'method',
  'credit_card_id',
  'installments',
  'lender',
  'from_account_id',
  'to_account_id',
  'notes',
] as const

const emptyValues = (kind: EntryKind, today: string): FormValues => ({
  kind,
  description: '',
  amount: '',
  amount_mode: 'total',
  competence_date: today,
  status: 'confirmado',
  category_id: '',
  account_id: '',
  method: '',
  credit_card_id: '',
  installments: '1',
  lender: '',
  from_account_id: '',
  to_account_id: '',
  notes: '',
})

interface Props {
  isOpen: boolean
  onClose: () => void
  /** Null cria; um id abre o lançamento correspondente para edição. */
  transactionId: number | null
  options?: TransactionOptions
  today: string
  onSaved: (message: string) => void
}

export function TransactionFormModal({
  isOpen,
  onClose,
  transactionId,
  options,
  today,
  onSaved,
}: Props) {
  const isEditing = transactionId !== null
  const { data: existing, isPending: isLoadingExisting } = useTransaction(isOpen ? transactionId : null)

  const create = useCreateTransaction()
  const update = useUpdateTransaction()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyValues('despesa', today),
  })

  const kind = watch('kind')
  const amount = watch('amount')
  const amountMode = watch('amount_mode')
  const installments = watch('installments')

  const installmentCount = Math.max(1, Math.trunc(Number(installments) || 1))
  const isPlan = planKinds.includes(kind)
  const isSplit = (isPlan || kind === 'cartao') && installmentCount > 1

  // Abrir para criar limpa o formulário; abrir para editar espera o registro.
  useEffect(() => {
    if (!isOpen) {
      return
    }

    setFormError(null)

    if (!isEditing) {
      reset(emptyValues('despesa', today))

      return
    }

    if (existing) {
      reset(toFormValues(existing))
    }
  }, [isOpen, isEditing, existing, reset, today])

  /**
   * A subcategoria aparece com o nome da mãe na frente: duas "Aluguel" em
   * árvores diferentes seriam indistinguíveis em uma lista plana.
   */
  const categories = useMemo(() => {
    const wanted = kind === 'receita' ? 'receita' : 'despesa'
    const all = options?.categories ?? []
    const nameOf = new Map(all.map((category) => [category.id, category.name]))

    return all
      .filter((category) => category.type === wanted)
      .map((category) => ({
        value: category.id.toString(),
        label:
          category.parent_id === null
            ? category.name
            : `${nameOf.get(category.parent_id) ?? '—'} › ${category.name}`,
      }))
      .sort((a, b) => a.label.localeCompare(b.label, 'pt-BR'))
  }, [options, kind])

  // Trocar de aba pode invalidar a categoria escolhida na aba anterior.
  useEffect(() => {
    const current = watch('category_id')

    if (current !== '' && !categories.some((option) => option.value === current)) {
      setValue('category_id', '')
    }
  }, [categories, setValue, watch])

  // Empréstimo já nasce classificado: a categoria dele é uma só, e obrigar a
  // escolhê-la toda vez seria uma pergunta com uma resposta possível.
  useEffect(() => {
    if (kind !== 'emprestimo' || isEditing) {
      return
    }

    const loanCategoryId = options?.loan_category_id

    if (loanCategoryId != null && watch('category_id') === '') {
      setValue('category_id', loanCategoryId.toString())
    }
  }, [kind, isEditing, options, setValue, watch])

  async function onSubmit(values: FormValues) {
    setFormError(null)

    try {
      if (isEditing && transactionId !== null) {
        await update.mutateAsync({ id: transactionId, payload: toPayload(values, false) })
        onSaved(isSplit ? 'Parcelamento atualizado.' : 'Lançamento atualizado.')
      } else {
        await create.mutateAsync(toPayload(values, true))
        onSaved(isSplit ? `Parcelamento registrado em ${installmentCount}x.` : 'Lançamento registrado.')
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
        setFormError(apiErrorMessage(error, 'Não foi possível salvar o lançamento.'))
      }
    }
  }

  const isLocked = isEditing && existing !== undefined && !existing.is_editable

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Editar lançamento' : 'Novo lançamento'}
      subtitle={subtitleFor(kind)}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form="transaction-form"
            isLoading={isSubmitting}
            disabled={isLocked}
          >
            {isEditing ? 'Salvar alterações' : 'Registrar'}
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
          id="transaction-form"
          onSubmit={handleSubmit(onSubmit)}
          className="flex flex-col gap-4"
          noValidate
        >
          {isLocked ? (
            <p role="alert" className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
              {existing?.lock_reason}
            </p>
          ) : null}

          {isEditing ? (
            <p className="rounded-[11px] border border-hairline bg-surface-alt px-3.5 py-2.5 text-[11.5px] text-ink-soft">
              {kindNote(kind)}
            </p>
          ) : (
            <div role="group" aria-label="Tipo de lançamento" className="flex flex-wrap gap-1.5">
              {(options?.kinds ?? []).map((option) => (
                <Chip
                  key={option.value}
                  isActive={kind === option.value}
                  onClick={() => setValue('kind', option.value as EntryKind)}
                >
                  {option.label}
                </Chip>
              ))}
            </div>
          )}

          <Field
            label="Descrição"
            placeholder={kind === 'emprestimo' ? 'Empréstimo pessoal' : 'Feira da semana'}
            autoFocus
            error={errors.description?.message}
            {...register('description')}
          />

          <div className="grid grid-cols-2 gap-3.5">
            <Field
              label={amountMode === 'parcela' ? 'Valor da parcela (R$)' : 'Valor (R$)'}
              type="number"
              step="0.01"
              min="0.01"
              inputMode="decimal"
              placeholder="0,00"
              error={errors.amount?.message}
              {...register('amount')}
            />

            <Field
              label={dateLabel(kind, isSplit)}
              type="date"
              error={errors.competence_date?.message}
              {...register('competence_date')}
            />
          </div>

          {kind === 'emprestimo' ? (
            <Field
              label="Credor"
              placeholder="Banco, financeira ou pessoa"
              error={errors.lender?.message}
              {...register('lender')}
            />
          ) : null}

          {kind === 'transferencia' ? (
            <div className="grid grid-cols-2 gap-3.5">
              <Select
                label="De"
                placeholder="Conta de origem"
                options={accountOptions(options)}
                error={errors.from_account_id?.message}
                {...register('from_account_id')}
              />
              <Select
                label="Para"
                placeholder="Conta de destino"
                options={accountOptions(options)}
                error={errors.to_account_id?.message}
                {...register('to_account_id')}
              />
            </div>
          ) : null}

          {kind === 'receita' || isPlan ? (
            <div className="grid grid-cols-2 gap-3.5">
              <Select
                label="Conta"
                placeholder="Selecione a conta"
                options={accountOptions(options)}
                error={errors.account_id?.message}
                {...register('account_id')}
              />
              <Select
                label="Forma de pagamento"
                placeholder="Não informar"
                options={(options?.methods ?? []).filter((method) => method.value !== 'credito')}
                error={errors.method?.message}
                {...register('method')}
              />
            </div>
          ) : null}

          {kind === 'cartao' ? (
            <div className="grid grid-cols-2 gap-3.5">
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
              <Select
                label="Parcelas"
                options={Array.from({ length: 36 }, (_, index) => ({
                  value: (index + 1).toString(),
                  label: index === 0 ? 'À vista' : `${index + 1}x`,
                }))}
                error={errors.installments?.message}
                {...register('installments')}
              />
            </div>
          ) : null}

          {isPlan ? (
            <Field
              label="Parcelas"
              type="number"
              min="1"
              max={MAX_PLAN_INSTALLMENTS}
              step="1"
              inputMode="numeric"
              error={errors.installments?.message}
              {...register('installments')}
            />
          ) : null}

          {isSplit ? (
            <div className="flex flex-col gap-2 rounded-[11px] border border-hairline bg-surface-alt px-3.5 py-3">
              <span className="text-[11.5px] font-semibold text-ink-soft">O valor informado é</span>

              <div role="group" aria-label="Como ler o valor" className="flex flex-wrap gap-1.5">
                <Chip
                  isActive={amountMode === 'total'}
                  onClick={() => setValue('amount_mode', 'total')}
                >
                  Valor total
                </Chip>
                <Chip
                  isActive={amountMode === 'parcela'}
                  onClick={() => setValue('amount_mode', 'parcela')}
                >
                  Valor da parcela
                </Chip>
              </div>

              <p className="text-[11.5px] leading-relaxed text-ink-muted">
                {planPreview(amount, amountMode, installmentCount)}
              </p>
            </div>
          ) : null}

          <div className={cn('grid gap-3.5', kind === 'transferencia' ? 'grid-cols-1' : 'grid-cols-2')}>
            {kind !== 'transferencia' ? (
              <Select
                label="Categoria"
                placeholder="Sem categoria"
                options={categories}
                error={errors.category_id?.message}
                {...register('category_id')}
              />
            ) : null}

            <Select
              label="Situação"
              options={(options?.statuses ?? []).filter(
                // Cancelar é uma ação da lista, não um estado inicial.
                (status) => isEditing || status.value !== 'cancelado',
              )}
              error={errors.status?.message}
              {...register('status')}
            />
          </div>

          {isSplit && isPlan ? (
            <p className="text-[11px] leading-relaxed text-ink-muted">
              A situação escolhida vale para as parcelas já vencidas. As futuras entram como
              previstas — confirmar as {installmentCount} de uma vez tiraria da conta, hoje, dinheiro
              que só sai ao longo dos próximos meses.
            </p>
          ) : null}

          <Textarea
            label="Observação"
            rows={2}
            placeholder="Opcional"
            error={errors.notes?.message}
            {...register('notes')}
          />

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

function accountOptions(options?: TransactionOptions) {
  return (options?.accounts ?? []).map((account) => ({
    value: account.id.toString(),
    label: `${account.nickname} · ${account.bank}`,
  }))
}

function dateLabel(kind: EntryKind, isSplit: boolean): string {
  if (kind === 'cartao') {
    return 'Data da compra'
  }

  return isSplit ? 'Data da 1ª parcela' : 'Data'
}

function subtitleFor(kind: EntryKind): string {
  switch (kind) {
    case 'cartao':
      return 'A compra é registrada por inteiro e distribuída nas faturas.'
    case 'emprestimo':
      return 'As prestações já entram nos próximos meses, uma por mês.'
    case 'transferencia':
      return 'Move saldo entre suas contas, sem virar receita nem despesa.'
    default:
      return 'Só o que está confirmado entra no saldo atual.'
  }
}

function kindNote(kind: EntryKind): string {
  switch (kind) {
    case 'cartao':
      return 'Compra no cartão. Alterar valor, data ou parcelas redistribui as parcelas nas faturas.'
    case 'emprestimo':
      return 'Empréstimo. Alterar valor, data ou parcelas redistribui as prestações inteiras.'
    case 'transferencia':
      return 'Transferência entre contas. Os dois lados são atualizados juntos.'
    case 'receita':
      return 'Receita lançada em conta.'
    default:
      return 'Despesa lançada em conta. Se estiver parcelada, editar redistribui todas as parcelas.'
  }
}

/** A conta que a pessoa faria de cabeça, feita na tela antes de ela salvar. */
function planPreview(amount: string, mode: AmountMode, installments: number): string {
  const value = Number(amount)

  if (!Number.isFinite(value) || value <= 0) {
    return `Dividido em ${installments} parcelas mensais.`
  }

  const total = mode === 'total' ? value : value * installments
  const each = mode === 'total' ? value / installments : value

  return `${installments}x de ${formatBRL(each)} · total de ${formatBRL(total)}`
}

function toFormValues(record: TransactionForm): FormValues {
  return {
    kind: record.kind,
    description: record.description,
    amount: record.amount.toString(),
    amount_mode: record.amount_mode,
    competence_date: record.competence_date,
    status: record.status,
    category_id: record.category_id?.toString() ?? '',
    account_id: record.account_id?.toString() ?? '',
    method: record.method ?? '',
    credit_card_id: record.credit_card_id?.toString() ?? '',
    installments: (record.installments ?? 1).toString(),
    lender: record.lender ?? '',
    from_account_id: record.from_account_id?.toString() ?? '',
    to_account_id: record.to_account_id?.toString() ?? '',
    notes: record.notes ?? '',
  }
}

/**
 * A API recusa campo que não pertence à aba escolhida, então o payload leva
 * só o que aquela natureza de lançamento aceita.
 */
function toPayload(values: FormValues, includeKind: boolean): TransactionPayload {
  const base: TransactionPayload = {
    ...(includeKind && { kind: values.kind }),
    description: values.description.trim(),
    amount: Number(values.amount),
    competence_date: values.competence_date,
    status: values.status,
    notes: values.notes.trim() === '' ? null : values.notes.trim(),
  }

  if (values.kind === 'transferencia') {
    return {
      ...base,
      from_account_id: Number(values.from_account_id),
      to_account_id: Number(values.to_account_id),
    }
  }

  const withCategory: TransactionPayload = {
    ...base,
    category_id: values.category_id === '' ? null : Number(values.category_id),
  }

  if (values.kind === 'cartao') {
    return {
      ...withCategory,
      credit_card_id: Number(values.credit_card_id),
      installments: Number(values.installments),
      amount_mode: values.amount_mode,
    }
  }

  const inAccount: TransactionPayload = {
    ...withCategory,
    account_id: Number(values.account_id),
    method: values.method === '' ? null : values.method,
  }

  if (!planKinds.includes(values.kind)) {
    return inAccount
  }

  return {
    ...inAccount,
    installments: Number(values.installments),
    amount_mode: values.amount_mode,
    ...(values.kind === 'emprestimo' && { lender: values.lender.trim() }),
  }
}
