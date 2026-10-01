// Moldura das páginas públicas do app (ponto e Site/Privacidade) — sem AppShellV2/Sidebar do ERP,
// legível no celular (é aberta a partir do app e da ficha da loja).

import type { ReactNode } from 'react'
import { Head } from '@inertiajs/react'

type Props = { titulo: string; atualizadoEm: string; rotulo?: string; children: ReactNode }

export function DocumentoPublico({ titulo, atualizadoEm, rotulo = 'App de ponto', children }: Props) {
  return (
    <main className="min-h-screen bg-background text-foreground">
      <Head title={titulo} />
      <article className="mx-auto max-w-2xl px-4 py-10 text-sm leading-relaxed [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-base [&_h2]:font-semibold [&_li]:ml-5 [&_li]:list-disc [&_p]:mb-3">
        <p className="mb-1 text-xs text-muted-foreground">oimpresso · {rotulo}</p>
        <h1 className="mb-1 text-xl font-semibold">{titulo}</h1>
        <p className="mb-6 text-xs text-muted-foreground">Atualizado em {atualizadoEm}</p>
        {children}
      </article>
    </main>
  )
}
