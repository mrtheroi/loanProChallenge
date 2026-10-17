import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import type { Registration } from '../api/authApi'
import { applyServerErrors } from '../serverErrors'
import { FormField } from './FormField'

const schema = z
  .object({
    name: z.string().trim().min(1, 'Enter your name.').max(255, 'Use at most 255 characters.'),
    email: z.email('Enter a valid email address.'),
    password: z.string().min(8, 'Use at least 8 characters.'),
    password_confirmation: z.string(),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: 'The passwords do not match.',
  })

type RegisterFormProps = { onSubmit: (registration: Registration) => Promise<unknown> }

export function RegisterForm({ onSubmit }: RegisterFormProps) {
  const form = useForm<Registration>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', email: '', password: '', password_confirmation: '' },
  })
  const { errors, isSubmitting } = form.formState

  const submit = form.handleSubmit(async (values) => {
    try {
      await onSubmit(values)
    } catch (error) {
      const message = applyServerErrors(error, ['name', 'email', 'password', 'password_confirmation'], form.setError)
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
      <FormField id="name" label="Name" autoComplete="name" error={errors.name?.message} {...form.register('name')} />
      <FormField id="email" label="Email" type="email" autoComplete="email" error={errors.email?.message} {...form.register('email')} />
      <FormField id="password" label="Password" type="password" autoComplete="new-password" error={errors.password?.message} {...form.register('password')} />
      <FormField
        id="password_confirmation"
        label="Confirm password"
        type="password"
        autoComplete="new-password"
        error={errors.password_confirmation?.message}
        {...form.register('password_confirmation')}
      />
      <Button type="submit" disabled={isSubmitting}>
        Create account
      </Button>
    </form>
  )
}
