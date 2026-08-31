import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import type { CategoryNode, CategoryType, EnumOption } from '@/types/api'

import { useCreateCategory, useUpdateCategory, type CategoryPayload } from '../api'

/** Paleta do tema: a cor da categoria pinta gráfico, chip e árvore. */
const palette = [
  '#A07CFF',
  '#8E7BFF',
  '#7C5CE0',
  '#C9B4FF',
  '#35D68A',
  '#48E39A',
  '#1D9260',
  '#4FD1C5',
  '#FF8A3D',
  '#FFA76B',
  '#C97B2E',
  '#F4515F',
  '#6E7681',
  '#4A525E',
]

const schema = z.object({
  name: z.string().trim().min(2, 'Dê um nome à categoria.').max(60, 'Máximo de 60 caracteres.'),
  type: z.enum(['receita', 'despesa']),
  color: z.string().regex(/^#[0-9A-Fa-f]{6}$/, 'Escolha uma cor.'),
  icon: z.string().max(40, 'Máximo de 40 caracteres.'),
  parent_id: z.string(),
})

type FormValues = z.infer<typeof schema>

const formFields = ['name', 'type', 'color', 'icon', 'parent_id'] as const

interface Props {
  isOpen: boolean
  onClose: () => void
  /** Null cria; um nó abre a categoria correspondente para edição. */
  category: CategoryNode | null
  /** Preenchido ao criar a partir do botão "+" de uma categoria mãe. */
  defaultParent: CategoryNode | null
  defaultType: CategoryType
  roots: CategoryNode[]
  types: EnumOption[]
  onSaved: (message: string) => void
}

export function CategoryFormModal({
  isOpen,
  onClose,
  category,
  defaultParent,
  defaultType,
  roots,
  types,
  onSaved,
}: Props) {
  const isEditing = category !== null

  const create = useCreateCategory()
  const update = useUpdateCategory()
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
    defaultValues: emptyValues(defaultType, null),
  })

  const type = watch('type')
  const color = watch('color')
  const parentId = watch('parent_id')

  useEffect(() => {
    if (!isOpen) {
      return
    }

    setFormError(null)
    reset(
      category === null
        ? emptyValues(defaultParent?.type ?? defaultType, defaultParent)
        : toFormValues(category),
    )
  }, [isOpen, category, defaultParent, defaultType, reset])

  /**
   * Só categorias principais do mesmo tipo podem ser mãe — a árvore tem dois
   * níveis, e uma subcategoria de receita dentro de uma mãe de despesa faria o
   * seletor de despesas oferecer uma categoria de receita.
   */
  const parentOptions = useMemo(
    () =>
      roots
        .filter((root) => root.type === type && root.id !== category?.id)
        .map((root) => ({ value: root.id.toString(), label: root.name })),
    [roots, type, category],
  )

  useEffect(() => {
    if (parentId !== '' && !parentOptions.some((option) => option.value === parentId)) {
      setValue('parent_id', '')
    }
  }, [parentOptions, parentId, setValue])

  // Uma categoria com filhas não pode virar filha: sobrariam três níveis.
  const hasChildren = (category?.children.length ?? 0) > 0
  const isSystem = category?.is_system ?? false

  async function onSubmit(values: FormValues) {
    setFormError(null)

    const payload: CategoryPayload = {
      name: values.name.trim(),
      type: values.type,
      color: values.color.toUpperCase(),
      icon: values.icon.trim() === '' ? null : values.icon.trim(),
      parent_id: values.parent_id === '' ? null : Number(values.parent_id),
    }

    try {
      if (isEditing && category !== null) {
        await update.mutateAsync({ id: category.id, payload })
        onSaved('Categoria atualizada.')
      } else {
        await create.mutateAsync(payload)
        onSaved('Categoria criada.')
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
        setFormError(apiErrorMessage(error, 'Não foi possível salvar a categoria.'))
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Editar categoria' : 'Nova categoria'}
      subtitle={
        isEditing
          ? 'Renomear altera o extrato, os relatórios e a Visão geral na mesma hora.'
          : 'Categorias classificam lançamentos e alimentam os relatórios por categoria.'
      }
      className="max-w-[480px]"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="category-form" isLoading={isSubmitting}>
            {isEditing ? 'Salvar alterações' : 'Criar categoria'}
          </Button>
        </>
      }
    >
      <form
        id="category-form"
        onSubmit={handleSubmit(onSubmit)}
        className="flex flex-col gap-4"
        noValidate
      >
        {isSystem ? (
          <p className="rounded-[11px] border border-hairline bg-surface-alt px-3.5 py-2.5 text-[11.5px] text-ink-soft">
            Categoria do sistema. O nome, a cor e o lugar na árvore são seus; o tipo não muda,
            porque o app aponta para ela em outros lugares.
          </p>
        ) : null}

        <Field
          label="Nome"
          placeholder="Alimentação"
          autoFocus
          error={errors.name?.message}
          {...register('name')}
        />

        <div className="grid grid-cols-2 gap-3.5">
          <Select
            label="Tipo"
            options={types}
            disabled={isSystem}
            error={errors.type?.message}
            {...register('type')}
          />

          <Select
            label="Dentro de"
            placeholder="Categoria principal"
            options={parentOptions}
            disabled={hasChildren}
            error={
              errors.parent_id?.message ??
              (hasChildren ? 'Tem subcategorias e por isso é sempre principal.' : undefined)
            }
            {...register('parent_id')}
          />
        </div>

        <div className="flex flex-col gap-2">
          <span className="text-[11.5px] font-semibold text-ink-soft">Cor</span>
          <div role="group" aria-label="Cor da categoria" className="flex flex-wrap gap-2">
            {palette.map((option) => (
              <button
                key={option}
                type="button"
                aria-label={`Cor ${option}`}
                aria-pressed={color?.toUpperCase() === option}
                onClick={() => setValue('color', option, { shouldValidate: true })}
                style={{ backgroundColor: option }}
                className={cn(
                  'size-7 rounded-[9px] transition-transform',
                  color?.toUpperCase() === option
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

        <Field
          label="Ícone (opcional)"
          placeholder="utensils"
          error={errors.icon?.message}
          {...register('icon')}
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

function emptyValues(type: CategoryType, parent: CategoryNode | null): FormValues {
  return {
    name: '',
    type,
    color: type === 'receita' ? '#35D68A' : '#A07CFF',
    icon: '',
    parent_id: parent?.id.toString() ?? '',
  }
}

function toFormValues(category: CategoryNode): FormValues {
  return {
    name: category.name,
    type: category.type,
    color: category.color,
    icon: category.icon ?? '',
    parent_id: category.parent_id?.toString() ?? '',
  }
}
