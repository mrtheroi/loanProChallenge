import { useState } from 'react'
import { Input } from '@/shared/ui/input'

type QuantityInputProps = { value: number; max: number; onChange: (quantity: number) => void }

export function QuantityInput({ value, max, onChange }: QuantityInputProps) {
  const [draft, setDraft] = useState(String(value))

  return (
    <Input
      type="number"
      inputMode="numeric"
      min={1}
      max={max}
      className="w-20"
      value={draft}
      onChange={(event) => {
        setDraft(event.target.value)
        const quantity = Number.parseInt(event.target.value, 10)
        if (Number.isFinite(quantity) && quantity > 0) onChange(quantity)
      }}
      onBlur={() => setDraft(String(value))}
    />
  )
}
