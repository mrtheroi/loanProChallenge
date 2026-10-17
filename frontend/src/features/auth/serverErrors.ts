import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { ApiError } from '@/shared/api/http'

export function applyServerErrors<T extends FieldValues>(error: unknown, fields: ReadonlyArray<Path<T>>, setError: UseFormSetError<T>): string | null {
  if (!(error instanceof ApiError)) return 'Something went wrong. Please try again.'

  let mapped = false
  for (const field of fields) {
    const message = error.errors[field]?.[0]
    if (message !== undefined) {
      setError(field, { type: 'server', message })
      mapped = true
    }
  }

  return mapped ? null : error.message
}
