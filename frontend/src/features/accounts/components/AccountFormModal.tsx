import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { formatBRL } from '@/lib/format'
import type { AccountOptions, AccountRow, AccountType } from '@/types/api'

import { useCreateAccount, useUpdateAccount, type AccountPayload } from '../api'

const schema = z.object({
  bank_id: z.string().min(1, 'Escolha o banco da conta.'),
  nickname: z.string().trim().min(2, 'Dê um apelido à conta.').max(60, 'Máximo de 60 caracteres.'),
  type: z.enum(['corrente', 'poupanca', 'investimento', 'carteira']),
  // Texto, como nos outros formulários: o input abre vazio em vez de com um
  // zero para apagar. Aceita negativo — conta no vermelho tem saldo negativo.
  initial_balance: z
    .string()
    .refine((value) => value.trim() === '' || Number.isFinite(Number(value)), 'Informe um valor.'),
})

type FormValues = z.infer<typeof schema>

const formFields = ['bank_id', 'nickname', 'type', 'initial_balance'] as const

interface Props {
  isOpen: boolean
  onClose: () => void
  /** Null cadastra; uma conta abre o registro para edição. */
  account: AccountRow | null
  /** Banco pré-selecionado quando o cadastro sai do botão de um banco. */
  defaultBankId: number | null
  options: AccountOptions | undefined
  onSaved: (message: string) => void
}

/**
 * Cadastro e edição de conta.
 *
 * O saldo inicial só aparece na criação: ele é o ponto de partida do histórico,
 * e reescrevê-lo mudaria todos os saldos já conferidos sem deixar rastro. Na
 * edição a tela diz isso e aponta para o ajuste de saldo, que registra a
 * diferença como lançamento.
 */
export function AccountFormModal({
  isOpen,
  onClose,
  account,
  defaultBankId,
  options,
  onSaved,
}: Props) {
  const isEditing = account !== null

  const create = useCreateAccount()
  const update = useUpdateAccount()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyValues(defaultBankId),
  })

  useEffect(() => {
    if (!isOpen) {
      return
    }

    setFormError(null)
    reset(account === null ? emptyValues(defaultBankId) : toFormValues(account))
  }, [isOpen, account, defaultBankId, reset])

  const bankOptions = useMemo(
    () => (options?.banks ?? []).map((bank) => ({ value: bank.id.toString(), label: bank.name })),
    [options],
  )

  async function onSubmit(values: FormValues) {
    setFormError(null)

    const payload: AccountPayload = {
      bank_id: Number(values.bank_id),
      nickname: values.nickname.trim(),
      type: values.type as AccountType,
      ...(isEditing ? {} : { initial_balance: Number(values.initial_balance || 0) }),
    }

    try {
      if (isEditing && account !== null) {
        await update.mutateAsync({ id: account.id, payload })
        onSaved('Conta atualizada.')
      } else {
        await create.mutateAsync(payload)
        onSaved('Conta cadastrada.')
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
        setFormError(apiErrorMessage(error, 'Não foi possível salvar a conta.'))
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Editar conta' : 'Nova conta'}
      subtitle={
        isEditing
          ? 'Apelido, tipo e banco. O saldo se acerta pelo ajuste de saldo.'
          : 'O saldo informado é o ponto de partida: daí em diante ele é calculado pelos lançamentos.'
      }
      className="max-w-[480px]"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="account-form" isLoading={isSubmitting}>
            {isEditing ? 'Salvar alterações' : 'Cadastrar conta'}
          </Button>
        </>
      }
    >
      <form
        id="account-form"
        onSubmit={handleSubmit(onSubmit)}
        className="flex flex-col gap-4"
        noValidate
      >
        {bankOptions.length === 0 ? (
          <p className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
            Cadastre um banco antes: toda conta pertence a uma instituição.
          </p>
        ) : null}

        <Field
          label="Apelido"
          placeholder="Conta corrente"
          autoFocus
          error={errors.nickname?.message}
          {...register('nickname')}
        />

        <div className="grid grid-cols-2 gap-3.5">
          <Select
            label="Banco"
            placeholder="Escolha o banco"
            options={bankOptions}
            error={errors.bank_id?.message}
            {...register('bank_id')}
          />

          <Select
            label="Tipo"
            options={options?.account_types ?? []}
            error={errors.type?.message}
            {...register('type')}
          />
        </div>

        {isEditing ? (
          <p className="rounded-[11px] border border-hairline bg-surface-alt px-3.5 py-2.5 text-[11.5px] leading-relaxed text-ink-soft">
            Saldo de partida: <strong className="font-semibold text-ink">{formatBRL(account.initial_balance)}</strong>.
            Ele não muda depois da criação — mudá-lo reescreveria os saldos de todos os meses
            anteriores. Para bater com o extrato do banco, use <em>Ajustar saldo</em>: a diferença
            entra como um lançamento datado.
          </p>
        ) : (
          <Field
            label="Saldo hoje (R$)"
            type="number"
            step="0.01"
            inputMode="decimal"
            placeholder="0,00"
            error={errors.initial_balance?.message}
            {...register('initial_balance')}
          />
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

function emptyValues(defaultBankId: number | null): FormValues {
  return {
    bank_id: defaultBankId?.toString() ?? '',
    nickname: '',
    type: 'corrente',
    initial_balance: '',
  }
}

function toFormValues(account: AccountRow): FormValues {
  return {
    bank_id: account.bank.id.toString(),
    nickname: account.nickname,
    type: account.type,
    initial_balance: account.initial_balance.toString(),
  }
}
