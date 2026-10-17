import type { ReactNode } from 'react'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/shared/ui/card'

type AuthCardProps = { title: string; description: string; footer: ReactNode; children: ReactNode }

export function AuthCard({ title, description, footer, children }: AuthCardProps) {
  return (
    <Card className="mx-auto w-full max-w-sm">
      <CardHeader>
        <CardTitle>
          <h1>{title}</h1>
        </CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent>{children}</CardContent>
      <CardFooter className="text-sm text-muted-foreground">{footer}</CardFooter>
    </Card>
  )
}
