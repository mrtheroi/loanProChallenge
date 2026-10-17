import { Link } from 'react-router'
import { Button } from '@/shared/ui/button'
import { cartItemCount, useCartStore } from '../cartStore'

export function CartLink() {
  const count = useCartStore((state) => cartItemCount(state.items))

  return (
    <Button asChild variant="ghost">
      <Link to="/cart">Cart ({count})</Link>
    </Button>
  )
}
