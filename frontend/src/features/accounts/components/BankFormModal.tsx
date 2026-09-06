import { zodResolver } from '@hookform/resolvers/zod'
import { Check } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import type { AccountOptions, BankKind } from '@/types/api'

import { useCreateBank, useUpdateBank, type BankPayload } from '../api'

/** Cores do tema, para o banco que não vem do catálogo. */
const palette = [
  '#A05BE0',
  '#7C3FBF',
  '#8E7BFF',
  '#C9B4FF',
  '#35D68A',
  '#3AA76D',
  '#00A868',
  '#12A19A',
  '#FF7A00',
  '#EC7000',
  '#E8B93B',
  '#D6294A',
  '#4C7BC0',
  '#6E7681',
]

const schema = z.object({
  name: z.string().trim().min(2, 'Dê um nome ao banco.').max(60, 'Máximo de 60 caracteres.'),
  color: z.string().regex(/^#[0-9A-Fa-f]{6}$/, 'Escolha uma cor.'),
  kind: z.enum(['digital', 'tradicional', 'corretora', 'carteira']),
})

type FormValues = z.infer<typeof schema>

const formFields = ['name', 'color', 'kind'] as const

/** O mínimo que a edição precisa — o slug é derivado do nome pela API. */
export interface EditableBank {
  id: number
  name: string
  color: string
  kind: BankKind
}

interface Props {
  isOpen: boolean
  onClose: () => void
  /** Null cadastra; um banco abre a instituição para edição. */
  bank: EditableBank | null
  options: AccountOptions | undefined
  onSaved: (message: string) => void
}

/**
 * Cadastro de instituição.
 *
 * Na criação a tela oferece primeiro o catálogo: escolher "Nubank" preenche
 * nome, cor e tipo de uma vez, e a mesma instituição fica com a mesma cor em
 * todas as telas. Os campos continuam abertos embaixo — quem usa um banco fora
 * da lista digita, e o resultado é exatamente o mesmo registro.
 */
export function BankFormModal({ isOpen, onClose, bank, options, onSaved }: Props) {
  const isEditing = bank !== null

  const create = useCreateBank()
  const update = useUpdateBank()
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
    defaultValues: { name: '', color: '#A05BE0', kind: 'digital' },
  })

  const color = watch('color')
  const name = watch('name')

  useEffect(() => {
    if (!isOpen) {
      return
    }

    setFormError(null)
    reset(
      bank === null
        ? { name: '', color: '#A05BE0', kind: 'digital' }
        : { name: bank.name, color: bank.color, kind: bank.kind },
    )
  }, [isOpen, bank, reset])

  // O catálogo só aparece no cadastro: na edição a instituição já existe, e
  // oferecer a lista convidaria a trocar um banco por outro sem querer.
  const catalog = useMemo(() => (isEditing ? [] : (options?.catalog ?? [])), [isEditing, options])

  async function onSubmit(values: FormValues) {
    setFormError(null)

    const payload: BankPayload = {
      name: values.name.trim(),
      color: values.color.toUpperCase(),
      kind: values.kind,
    }

    try {
      if (isEditing && bank !== null) {
        await update.mutateAsync({ id: bank.id, payload })
        onSaved('Banco atualizado.')
      } else {
        await create.mutateAsync(payload)
        onSaved('Banco cadastrado.')
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
        setFormError(apiErrorMessage(error, 'Não foi possível salvar o banco.'))
      }
    }
  }

  function pickFromCatalog(slug: string) {
    const entry = catalog.find((option) => option.slug === slug)

    if (entry === undefined) {
      return
    }

    setValue('name', entry.name, { shouldValidate: true })
    setValue('color', entry.color, { shouldValidate: true })
    setValue('kind', entry.kind as BankKind, { shouldValidate: true })
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Editar banco' : 'Novo banco'}
      subtitle={
        isEditing
          ? 'A cor identifica a instituição em contas, cartões e gráficos.'
          : 'Escolha da lista ou cadastre qualquer instituição — inclusive dinheiro em espécie.'
      }
      className="max-w-[520px]"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="bank-form" isLoading={isSubmitting}>
            {isEditing ? 'Salvar alterações' : 'Cadastrar banco'}
          </Button>
        </>
      }
    >
      <form id="bank-form" onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
        {catalog.length > 0 ? (
          <div className="flex flex-col gap-2">
            <span className="text-[11.5px] font-semibold text-ink-soft">Bancos comuns</span>
            <div role="group" aria-label="Escolher banco da lista" className="flex flex-wrap gap-1.5">
              {catalog.map((entry) => {
                const isPicked = !entry.is_registered && name.trim() === entry.name

                return (
                  <button
                    key={entry.slug}
                    type="button"
                    disabled={entry.is_registered}
                    onClick={() => pickFromCatalog(entry.slug)}
                    title={entry.is_registered ? 'Você já cadastrou este banco' : entry.kind_label}
                    className={cn(
                      'flex items-center gap-1.5 rounded-[10px] border px-2.5 py-1.5 text-[11.5px] font-semibold transition-colors',
                      entry.is_registered
                        ? 'cursor-not-allowed border-hairline text-ink-faint'
                        : isPicked
                          ? 'border-green/45 bg-green/[0.11] text-green-bright'
                          : 'border-hairline bg-surface-alt text-ink-soft hover:border-hairline-strong hover:text-ink',
                    )}
                  >
                    <span
                      className="size-2.5 rounded-full"
                      style={{ background: entry.color }}
                      aria-hidden="true"
                    />
                    {entry.name}
                    {entry.is_registered ? <Check className="size-3" aria-hidden="true" /> : null}
                  </button>
                )
              })}
            </div>
          </div>
        ) : null}

        <div className="grid grid-cols-2 gap-3.5">
          <Field
            label="Nome"
            placeholder="Nubank"
            autoFocus
            error={errors.name?.message}
            {...register('name')}
          />

          <Select
            label="Tipo"
            options={options?.bank_kinds ?? []}
            error={errors.kind?.message}
            {...register('kind')}
          />
        </div>

        <div className="flex flex-col gap-2">
          <span className="text-[11.5px] font-semibold text-ink-soft">Cor</span>
          <div role="group" aria-label="Cor do banco" className="flex flex-wrap gap-2">
            {palette.map((option) => (
              <button
                key={option}
                type="button"
                aria-label={`Cor ${option}`}
                aria-pressed={color?.toUpperCase() === option.toUpperCase()}
                onClick={() => setValue('color', option, { shouldValidate: true })}
                style={{ backgroundColor: option }}
                className={cn(
                  'size-7 rounded-[9px] transition-transform',
                  color?.toUpperCase() === option.toUpperCase()
                    ? 'ring-2 ring-white/80 ring-offset-2 ring-offset-surface'
                    : 'hover:scale-110',
                )}
              />
            ))}
          </div>
          {errors.color?.message ? (
            <p role="alert" className="text-[11px] font-medium text-orange-light">
              {errors.color.message}
            </p>
          ) : null}
        </div>

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
