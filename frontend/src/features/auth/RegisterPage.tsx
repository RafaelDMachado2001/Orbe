import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { Button } from '@/components/ui/Button'
import { Field } from '@/components/ui/Field'
import { apiErrorMessage, apiFieldErrors } from '@/lib/api'

import { useAuth } from './useAuth'
import { AuthLayout } from './components/AuthLayout'

const schema = z
  .object({
    name: z.string().min(2, 'Informe seu nome.'),
    email: z.string().min(1, 'Informe seu e-mail.').email('Informe um e-mail válido.'),
    password: z
      .string()
      .min(8, 'A senha precisa ter ao menos 8 caracteres.')
      .regex(/[a-zA-Z]/, 'A senha precisa ter letras.')
      .regex(/\d/, 'A senha precisa ter números.'),
    password_confirmation: z.string(),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: 'A confirmação não confere.',
  })

type FormValues = z.infer<typeof schema>

/** Campos que a API pode marcar como invalidos, para direcionar o erro. */
const formFields = ['name', 'email', 'password', 'password_confirmation'] as const

export function RegisterPage() {
  const { register: createAccount, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', email: '', password: '', password_confirmation: '' },
  })

  if (isAuthenticated) {
    return <Navigate to="/" replace />
  }

  async function onSubmit(values: FormValues) {
    setFormError(null)

    try {
      await createAccount(values)
      navigate('/', { replace: true })
    } catch (error) {
      const fields = apiFieldErrors(error)

      if (Object.keys(fields).length > 0) {
        Object.entries(fields).forEach(([field, message]) => {
          if ((formFields as readonly string[]).includes(field)) {
            setError(field as keyof FormValues, { message })
          }
        })
      } else {
        setFormError(apiErrorMessage(error, 'Não foi possível criar a conta.'))
      }
    }
  }

  return (
    <AuthLayout
      title="Criar conta"
      subtitle="Sua conta já nasce com o plano de categorias pronto — é só cadastrar bancos e lançar."
      footer={
        <>
          Já tem conta?{' '}
          <Link to="/entrar" className="font-semibold text-purple hover:text-purple-light">
            Entrar
          </Link>
        </>
      }
    >
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
        <Field
          label="Nome"
          autoComplete="name"
          placeholder="Como podemos te chamar"
          error={errors.name?.message}
          {...register('name')}
        />

        <Field
          label="E-mail"
          type="email"
          autoComplete="email"
          placeholder="voce@exemplo.com"
          error={errors.email?.message}
          {...register('email')}
        />

        <Field
          label="Senha"
          type="password"
          autoComplete="new-password"
          placeholder="Ao menos 8 caracteres, com letras e números"
          error={errors.password?.message}
          {...register('password')}
        />

        <Field
          label="Confirmar senha"
          type="password"
          autoComplete="new-password"
          placeholder="Repita a senha"
          error={errors.password_confirmation?.message}
          {...register('password_confirmation')}
        />

        {formError ? (
          <p role="alert" className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
            {formError}
          </p>
        ) : null}

        <Button type="submit" isLoading={isSubmitting} className="mt-1 w-full py-3">
          Criar conta
        </Button>
      </form>
    </AuthLayout>
  )
}
