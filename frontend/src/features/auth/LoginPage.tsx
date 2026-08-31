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

const schema = z.object({
  email: z.string().min(1, 'Informe seu e-mail.').email('Informe um e-mail válido.'),
  password: z.string().min(1, 'Informe sua senha.'),
})

type FormValues = z.infer<typeof schema>

export function LoginPage() {
  const { login, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  })

  if (isAuthenticated) {
    return <Navigate to="/" replace />
  }

  async function onSubmit(values: FormValues) {
    setFormError(null)

    try {
      await login(values)
      navigate('/', { replace: true })
    } catch (error) {
      const fields = apiFieldErrors(error)

      if (Object.keys(fields).length > 0) {
        Object.entries(fields).forEach(([field, message]) => {
          if (field === 'email' || field === 'password') {
            setError(field, { message })
          }
        })
      } else {
        setFormError(apiErrorMessage(error, 'Não foi possível entrar.'))
      }
    }
  }

  return (
    <AuthLayout
      title="Entrar"
      subtitle="Acesse sua conta para ver o balanço do mês, as faturas e a projeção."
      footer={
        <>
          Ainda não tem conta?{' '}
          <Link to="/registrar" className="font-semibold text-purple hover:text-purple-light">
            Criar conta
          </Link>
        </>
      }
    >
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
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
          autoComplete="current-password"
          placeholder="••••••••"
          error={errors.password?.message}
          {...register('password')}
        />

        {formError ? (
          <p role="alert" className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light">
            {formError}
          </p>
        ) : null}

        <Button type="submit" isLoading={isSubmitting} className="mt-1 w-full py-3">
          Entrar
        </Button>
      </form>
    </AuthLayout>
  )
}
