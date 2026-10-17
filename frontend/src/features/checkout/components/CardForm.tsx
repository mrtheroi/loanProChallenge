import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { FormField } from '@/features/auth/components/FormField'
import { Button } from '@/shared/ui/button'
import type { CheckoutPayload } from '@/features/orders/api/ordersApi'

const schema = z.object({
  number: z.string().refine((value) => /^[\d -]+$/.test(value) && /^\d{13,19}$/.test(value.replace(/[ -]/g, '')), 'Enter 13 to 19 digits.'),
  exp_month: z.coerce.number<string>().int('Enter a month from 1 to 12.').min(1, 'Enter a month from 1 to 12.').max(12, 'Enter a month from 1 to 12.'),
  exp_year: z.coerce.number<string>().int('Enter a year between 2000 and 2100.').min(2000, 'Enter a year between 2000 and 2100.').max(2100, 'Enter a year between 2000 and 2100.'),
  cvc: z.string().regex(/^\d{3,4}$/, 'Enter 3 or 4 digits.'),
}).refine(
  (card) => {
    // The backend checks expiry against the current UTC month.
    const today = new Date()
    return card.exp_year * 12 + card.exp_month >= today.getUTCFullYear() * 12 + today.getUTCMonth() + 1
  },
  { path: ['exp_year'], message: 'This card has expired.' },
)

type CardValues = z.input<typeof schema>
export type Card = CheckoutPayload['card']

type CardFormProps = { totalLabel: string; disabled: boolean; onSubmit: (card: Card) => void }

export function CardForm({ totalLabel, disabled, onSubmit }: CardFormProps) {
  const form = useForm<CardValues, unknown, Card>({ resolver: zodResolver(schema), defaultValues: { number: '', exp_month: '', exp_year: '', cvc: '' } })
  const { errors } = form.formState

  return (
    <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="grid gap-4">
      <FormField id="card-number" label="Card number" inputMode="numeric" autoComplete="cc-number" error={errors.number?.message} {...form.register('number')} />
      <div className="grid grid-cols-3 gap-4">
        <FormField id="exp-month" label="Expiry month" inputMode="numeric" autoComplete="cc-exp-month" error={errors.exp_month?.message} {...form.register('exp_month')} />
        <FormField id="exp-year" label="Expiry year" inputMode="numeric" autoComplete="cc-exp-year" error={errors.exp_year?.message} {...form.register('exp_year')} />
        <FormField id="cvc" label="CVC" inputMode="numeric" autoComplete="cc-csc" error={errors.cvc?.message} {...form.register('cvc')} />
      </div>
      <p className="text-xs text-muted-foreground">Test mode: 4242 4242 4242 4242 is approved, 4000 0000 0000 0002 is declined.</p>
      <Button type="submit" disabled={disabled}>
        Pay {totalLabel}
      </Button>
    </form>
  )
}
