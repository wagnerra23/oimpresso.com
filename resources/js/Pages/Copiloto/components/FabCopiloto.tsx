import { Link } from '@inertiajs/react'
import { MessageSquare } from 'lucide-react'

interface Props {
  contextRoute?: string
}

export default function FabCopiloto({ contextRoute }: Props) {
  const href = contextRoute
    ? `/copiloto?context=${encodeURIComponent(contextRoute)}`
    : '/copiloto'

  return (
    <Link
      href={href}
      aria-label="Conversar com Copiloto"
      className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
    >
      <MessageSquare className="h-6 w-6" />
    </Link>
  )
}
