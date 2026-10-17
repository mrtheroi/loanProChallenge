import { Link } from 'react-router'
import type { Product } from '@/features/catalog/api/catalogApi'
import { formatCents, toCents } from '@/shared/lib/money'
import { Button } from '@/shared/ui/button'

type ProductTableProps = { products: Product[]; onDelete: (product: Product) => void }

export function ProductTable({ products, onDelete }: ProductTableProps) {
  return (
    <table className="w-full text-sm">
      <thead className="text-left text-muted-foreground">
        <tr>
          <th className="py-2">SKU</th>
          <th className="py-2">Name</th>
          <th className="py-2">Category</th>
          <th className="py-2 text-right">Price</th>
          <th className="py-2 text-right">Stock</th>
          <th className="py-2 text-right">Actions</th>
        </tr>
      </thead>
      <tbody className="divide-y">
        {products.map((product) => (
          <tr key={product.sku}>
            <td className="py-2 font-mono">{product.sku}</td>
            <td className="py-2">{product.name}</td>
            <td className="py-2">{product.category}</td>
            <td className="py-2 text-right">{formatCents(toCents(product.price))}</td>
            <td className="py-2 text-right">{product.stock}</td>
            <td className="py-2">
              <div className="flex justify-end gap-2">
                <Button asChild size="sm" variant="outline">
                  <Link to={`/admin/products/${encodeURIComponent(product.sku)}/edit`} aria-label={`Edit ${product.name}`}>
                    Edit
                  </Link>
                </Button>
                <Button size="sm" variant="destructive" aria-label={`Delete ${product.name}`} onClick={() => onDelete(product)}>
                  Delete
                </Button>
              </div>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
