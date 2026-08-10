import { Link } from '@inertiajs/react'
import { MessageSquare } from 'lucide-react'

interface Props {
  contextRoute?: string
}

export default function FabJana({ contextRoute }: Props) {
  // Onda 3 da fusao (US-COPI-148): o FAB leva a CONVERSA. A raiz `/ia` virou o
  // Painel, entao sem isto o botao de chat cairia no Painel — de onde ele e
  // renderizado. Loop silencioso: nada quebra, o chat so fica inalcancavel.
  const href = contextRoute
    ? `/ia/conversa?context=${encodeURIComponent(contextRoute)}`
    : '/ia/conversa'

  return (
    <Link
      href={href}
      aria-label="Conversar com a Jana"
      className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
    >
      <MessageSquare className="h-6 w-6" />
    </Link>
  )
}
