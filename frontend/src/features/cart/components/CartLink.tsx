import { Link } from 'react-router'
import { Button } from '@/shared/ui/button'
import { cartLineCount, useCartStore } from '../cartStore'

export function CartLink() {
  const count = useCartStore((state) => cartLineCount(state.items))

  return (
    <Button asChild variant="ghost">
      <Link to="/cart">Cart ({count})</Link>
    </Button>
  )
}
