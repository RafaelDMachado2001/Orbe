import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { CardFace } from '@/components/ui/CardFace'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import type { CardBrand, CardOptions, CreditCardRecord } from '@/types/api'

import { useCard, useCreateCard, useUpdateCard, type CreditCardPayload } from '../api'

const palette = ['#A07CFF', '#35D68A', '#FF8A3D', '#F4515F', '#4A9CFF', '#E9ECF1']

const schema = z.object({
  nickname: z.string().trim().min(2, 'Dê um apelido ao cartão.').max(60, 'Máximo de 60 caracteres.'),
  bank_id: z.string().min(1, 'Escolha o banco.'),
  brand: z.string().min(1, 'Escolha a bandeira.'),
  last_four: z.string().regex(/^\d{4}$/, 'Informe os quatro últimos dígitos.'),
  limit_amount: z
    .string()
    .min(1, 'Informe o limite.')
    .refine((value) => Number(value) >= 0, 'O limite não pode ser negativo.'),
  closing_day: z.string().min(1, 'Informe o dia de fechamento.'),
  due_day: z.string().min(1, 'Informe o dia de vencimento.'),
  payment_account_id: z.string(),
  color: z.string(),
})

type FormValues = z.infer<typeof schema>

const formFields = [
  'nickname',
  'bank_id',
  'brand',
  'last_four',
  'limit_amount',
  'closing_day',
  'due_day',
  'payment_account_id',
  'color',
] as const

const emptyValues = (): FormValues => ({
  nickname: '',
  bank_id: '',
  brand: 'mastercard',
  last_four: '',
  limit_amount: '',
  closing_day: '',
  due_day: '',
  payment_account_id: '',
  color: palette[0] as string,
})

/** Dias 29 a 31 não existem em todo mês; o ciclo do cartão ficaria irregular. */
const cycleDays = Array.from({ length: 28 }, (_, index) => ({
  value: (index + 1).toString(),
  label: `Dia ${index + 1}`,
}))

interface Props {
  isOpen: boolean
  onClose: () => void
  cardId: number | null
  options?: CardOptions
  onSaved: (message: string) => void
}

export function CardFormModal({ isOpen, onClose, cardId, options, onSaved }: Props) {
  const isEditing = cardId !== null
  const { data: existing, isPending: isLoadingExisting } = useCard(isOpen ? cardId : null)

  const create = useCreateCard()
  const update = useUpdateCard()
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
    defaultValues: emptyValues(),
  })

  const [color, nickname, lastFour, brand] = watch(['color', 'nickname', 'last_four', 'brand'])

  useEffect(() => {
    if (!isOpen) {
      return
    }

    setFormError(null)

    if (!isEditing) {
      reset(emptyValues())

      return
    }

    if (existing) {
      reset(toFormValues(existing))
    }
  }, [isOpen, isEditing, existing, reset])

  async function onSubmit(values: FormValues) {
    setFormError(null)

    try {
      if (isEditing && cardId !== null) {
        await update.mutateAsync({ id: cardId, payload: toPayload(values) })
        onSaved('Cartão atualizado.')
      } else {
        await create.mutateAsync(toPayload(values))
        onSaved('Cartão cadastrado.')
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
        setFormError(apiErrorMessage(error, 'Não foi possível salvar o cartão.'))
      }
    }
  }

  const hasBanks = (options?.banks ?? []).length > 0

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Editar cartão' : 'Novo cartão'}
      subtitle="O fechamento define em qual fatura cada compra cai."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form="card-form"
            isLoading={isSubmitting}
            disabled={!hasBanks}
          >
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
        <form id="card-form" onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
          {!hasBanks ? (
            <p role="alert" className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
              Você ainda não tem um banco cadastrado. O cadastro de bancos chega na tela de Bancos e
              contas.
            </p>
          ) : null}

          {/* O cartão se monta enquanto o formulário é preenchido: cor e
              bandeira são escolhas visuais, e escolher às cegas para só ver o
              resultado depois de salvar dá uma ida e volta a cada tentativa. */}
          <div className="flex justify-center rounded-[14px] border border-hairline bg-surface-alt py-[18px]">
            <CardFace
              nickname={nickname.trim() === '' ? 'Seu cartão' : nickname}
              lastFour={/^\d{4}$/.test(lastFour) ? lastFour : '••••'}
              brand={toBrand(brand)}
              color={color}
              width={196}
            />
          </div>

          <Field
            label="Apelido"
            placeholder="Nubank Ultravioleta"
            autoFocus
            error={errors.nickname?.message}
            {...register('nickname')}
          />

          <div className="grid grid-cols-2 gap-3.5">
            <Select
              label="Banco"
              placeholder="Selecione o banco"
              options={(options?.banks ?? []).map((bank) => ({
                value: bank.id.toString(),
                label: bank.name,
              }))}
              error={errors.bank_id?.message}
              {...register('bank_id')}
            />
            <Select
              label="Bandeira"
              options={options?.brands ?? []}
              error={errors.brand?.message}
              {...register('brand')}
            />
          </div>

          <div className="grid grid-cols-2 gap-3.5">
            <Field
              label="Últimos 4 dígitos"
              inputMode="numeric"
              maxLength={4}
              placeholder="4417"
              error={errors.last_four?.message}
              {...register('last_four')}
            />
            <Field
              label="Limite (R$)"
              type="number"
              step="0.01"
              min="0"
              inputMode="decimal"
              placeholder="0,00"
              error={errors.limit_amount?.message}
              {...register('limit_amount')}
            />
          </div>

          <div className="grid grid-cols-2 gap-3.5">
            <Select
              label="Fechamento"
              placeholder="Dia"
              options={cycleDays}
              error={errors.closing_day?.message}
              {...register('closing_day')}
            />
            <Select
              label="Vencimento"
              placeholder="Dia"
              options={cycleDays}
              error={errors.due_day?.message}
              {...register('due_day')}
            />
          </div>

          <Select
            label="Conta que paga a fatura"
            placeholder="Escolher na hora de pagar"
            options={(options?.accounts ?? []).map((account) => ({
              value: account.id.toString(),
              label: `${account.nickname} · ${account.bank}`,
            }))}
            error={errors.payment_account_id?.message}
            {...register('payment_account_id')}
          />

          <div className="flex flex-col gap-2">
            <span className="text-[11.5px] font-semibold text-ink-soft">Cor do cartão</span>
            <div role="radiogroup" aria-label="Cor do cartão" className="flex gap-2">
              {palette.map((option) => (
                <button
                  key={option}
                  type="button"
                  role="radio"
                  aria-checked={color === option}
                  aria-label={`Cor ${option}`}
                  onClick={() => setValue('color', option)}
                  style={{ background: option }}
                  className={cn(
                    'size-7 rounded-[9px] transition-transform',
                    color === option
                      ? 'ring-2 ring-white/70 ring-offset-2 ring-offset-surface'
                      : 'opacity-70 hover:opacity-100',
                  )}
                />
              ))}
            </div>
          </div>

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

const brands: CardBrand[] = ['visa', 'mastercard', 'elo', 'amex', 'hipercard']

/** O campo do formulário é texto livre; a miniatura precisa de uma bandeira. */
function toBrand(value: string): CardBrand {
  return (brands as string[]).includes(value) ? (value as CardBrand) : 'mastercard'
}

function toFormValues(card: CreditCardRecord): FormValues {
  return {
    nickname: card.nickname,
    bank_id: card.bank_id.toString(),
    brand: card.brand,
    last_four: card.last_four,
    limit_amount: card.limit_amount.toString(),
    closing_day: card.closing_day.toString(),
    due_day: card.due_day.toString(),
    payment_account_id: card.payment_account_id?.toString() ?? '',
    color: card.color,
  }
}

function toPayload(values: FormValues): CreditCardPayload {
  return {
    bank_id: Number(values.bank_id),
    payment_account_id: values.payment_account_id === '' ? null : Number(values.payment_account_id),
    nickname: values.nickname.trim(),
    brand: values.brand,
    last_four: values.last_four,
    limit_amount: Number(values.limit_amount),
    closing_day: Number(values.closing_day),
    due_day: Number(values.due_day),
    color: values.color,
  }
}
