import { Button } from '@/shared/ui/button'

type PaginationProps = { page: number; lastPage: number; onChange: (page: number) => void }

export function Pagination({ page, lastPage, onChange }: PaginationProps) {
  return (
    <nav aria-label="Pagination" className="flex items-center justify-center gap-4">
      <Button variant="outline" disabled={page <= 1} onClick={() => onChange(page - 1)} aria-label="Previous page">
        Previous
      </Button>
      <span className="text-sm">
        Page {page} of {lastPage}
      </span>
      <Button variant="outline" disabled={page >= lastPage} onClick={() => onChange(page + 1)} aria-label="Next page">
        Next
      </Button>
    </nav>
  )
}
