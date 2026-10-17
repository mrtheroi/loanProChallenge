import { Link, useNavigate, useSearchParams } from 'react-router'
import { AuthCard } from '../components/AuthCard'
import { LoginForm } from '../components/LoginForm'
import { useLogin } from '../hooks/useAuth'
import { homeFor, safeRedirect } from '../redirect'

export function LoginContainer() {
  const login = useLogin()
  const navigate = useNavigate()
  const [params] = useSearchParams()

  return (
    <AuthCard
      title="Log in"
      description="Use your account to buy or to manage the catalog."
      footer={
        <span>
          New here? <Link to="/register" className="underline">Create an account</Link>
        </span>
      }
    >
      <LoginForm
        onSubmit={async (credentials) => {
          const user = await login.mutateAsync(credentials)
          await navigate(safeRedirect(params.get('redirect')) ?? homeFor(user), { replace: true })
        }}
      />
    </AuthCard>
  )
}
