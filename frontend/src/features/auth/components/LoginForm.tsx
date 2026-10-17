import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import type { Credentials } from '../api/authApi'
import { applyServerErrors } from '../serverErrors'
import { FormField } from './FormField'

const schema = z.object({
  email: z.email('Enter a valid email address.'),
  password: z.string().min(1, 'Enter your password.'),
})

type LoginFormProps = { onSubmit: (credentials: Credentials) => Promise<unknown> }

export function LoginForm({ onSubmit }: LoginFormProps) {
  const form = useForm<Credentials>({ resolver: zodResolver(schema), defaultValues: { email: '', password: '' } })
  const { errors, isSubmitting } = form.formState

  const submit = form.handleSubmit(async (values) => {
    try {
      await onSubmit(values)
    } catch (error) {
      const message = applyServerErrors(error, ['email', 'password'], form.setError)
      if (message !== null) form.setError('root', { message })
    }
  })

  return (
    <form noValidate onSubmit={submit} className="grid gap-4">
      {errors.root?.message && (
        <Alert variant="destructive">
          <AlertDescription>{errors.root.message}</AlertDescription>
        </Alert>
      )}
      <FormField id="email" label="Email" type="email" autoComplete="email" error={errors.email?.message} {...form.register('email')} />
      <FormField id="password" label="Password" type="password" autoComplete="current-password" error={errors.password?.message} {...form.register('password')} />
      <Button type="submit" disabled={isSubmitting}>
        Log in
      </Button>
    </form>
  )
}
