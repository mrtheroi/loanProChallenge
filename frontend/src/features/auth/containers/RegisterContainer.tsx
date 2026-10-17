import { Link, useNavigate } from 'react-router'
import { AuthCard } from '../components/AuthCard'
import { RegisterForm } from '../components/RegisterForm'
import { useRegister } from '../hooks/useAuth'

export function RegisterContainer() {
  const register = useRegister()
  const navigate = useNavigate()

  return (
    <AuthCard
      title="Create an account"
      description="Register as a buyer to place orders."
      footer={
        <span>
          Already have an account? <Link to="/login" className="underline">Log in</Link>
        </span>
      }
    >
      <RegisterForm
        onSubmit={async (registration) => {
          await register.mutateAsync(registration)
          await navigate('/', { replace: true })
        }}
      />
    </AuthCard>
  )
}
