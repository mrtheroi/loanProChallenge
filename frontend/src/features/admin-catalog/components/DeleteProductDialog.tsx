import type { Product } from '@/features/catalog/api/catalogApi'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/shared/ui/alert-dialog'

type DeleteProductDialogProps = { product: Product | null; deleting: boolean; onConfirm: (product: Product) => void; onClose: () => void }

export function DeleteProductDialog({ product, deleting, onConfirm, onClose }: DeleteProductDialogProps) {
  return (
    <AlertDialog open={product !== null} onOpenChange={(open) => !open && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete {product?.name}?</AlertDialogTitle>
          <AlertDialogDescription>It disappears from the store. Its SKU, {product?.sku}, stays reserved and cannot be reused.</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction variant="destructive" disabled={deleting} onClick={() => product && onConfirm(product)}>
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
