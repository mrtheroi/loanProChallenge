import { useState } from 'react'
import { Input } from '@/shared/ui/input'

type QuantityInputProps = { value: number; max: number; onCommit: (quantity: number) => number }

export function QuantityInput({ value, max, onCommit }: QuantityInputProps) {
  const [draft, setDraft] = useState<string | null>(null)

  const commit = () => {
    if (draft === null) return
    const quantity = Number.parseInt(draft, 10)
    setDraft(null)
    if (Number.isFinite(quantity)) onCommit(quantity)
  }

  return (
    <Input
      type="number"
      inputMode="numeric"
      min={1}
      max={max}
      className="w-20"
      value={draft ?? String(value)}
      onChange={(event) => setDraft(event.target.value)}
      onBlur={commit}
      onKeyDown={(event) => {
        if (event.key === 'Enter') commit()
      }}
    />
  )
}
