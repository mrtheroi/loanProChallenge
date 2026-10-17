import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { ApiError } from '@/shared/api/http'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import { Input } from '@/shared/ui/input'
import { Label } from '@/shared/ui/label'
import { importProducts } from '../api/importApi'
import { ImportReportView } from '../components/ImportReportView'

export function ImportContainer() {
  const [file, setFile] = useState<File | null>(null)
  const queryClient = useQueryClient()
  const upload = useMutation({
    mutationFn: importProducts,
    // Rows are written one by one, so even a failed import may have changed the catalog.
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: ['products'] })
      await queryClient.invalidateQueries({ queryKey: ['product'] })
      await queryClient.invalidateQueries({ queryKey: ['categories'] })
    },
  })
  const fileError = upload.error instanceof ApiError ? upload.error.errors.file?.[0] : undefined
  const problem = upload.isError && fileError === undefined ? 'The import could not be completed. Please try again.' : null

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (file !== null) upload.mutate(file)
  }

  return (
    <section className="flex flex-col gap-6">
      <h1 className="font-heading text-2xl font-semibold">Import products</h1>
      <form onSubmit={submit} className="flex max-w-xl flex-col gap-3">
        <Label htmlFor="csv-file">CSV file</Label>
        <Input
          id="csv-file"
          type="file"
          accept=".csv,text/csv"
          disabled={upload.isPending}
          aria-invalid={fileError !== undefined}
          aria-describedby={fileError ? 'csv-file-error' : undefined}
          onChange={(event) => {
            setFile(event.target.files?.[0] ?? null)
            upload.reset()
          }}
        />
        {fileError && (
          <p id="csv-file-error" className="text-sm text-destructive">
            {fileError}
          </p>
        )}
        <Button type="submit" disabled={file === null || upload.isPending} className="self-start">
          Import
        </Button>
      </form>
      {problem && (
        <Alert variant="destructive">
          <AlertDescription>{problem}</AlertDescription>
        </Alert>
      )}
      {upload.data && <ImportReportView report={upload.data} />}
    </section>
  )
}
