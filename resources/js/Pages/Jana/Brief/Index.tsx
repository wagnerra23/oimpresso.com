// Stub /jana/brief — ghost canon ADR 0182 + GUIA-SIDEBAR-V3 (Wagner 2026-05-21).
// Brief executive diário é gerado pelo BriefDiarioAgent + exposto via MCP tool
// `brief-fetch`. UI dedicada vem em onda futura; este stub mantém o ghost
// clicável e explica onde achar o brief enquanto isso.

import React from 'react'
import { Head, Link } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { JanaAreaHeader } from '@/Pages/Jana/components/JanaAreaHeader'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Sparkles, MessageSquare, FileText } from 'lucide-react'

interface Props {
  businessId: number
}

export default function BriefIndex({ businessId }: Props) {
  return (
    <>
      <Head title="Jana · Brief" />
      <JanaAreaHeader active="brief" />

      <div className="mx-auto max-w-3xl space-y-6 p-6">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <span
              aria-hidden
              className="inline-block size-2 rounded-full"
              style={{ background: 'oklch(0.62 0.13 220)' }}
            />
            <span className="text-xs uppercase tracking-wide text-muted-foreground">
              brief diário · business {businessId}
            </span>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight">Brief da Jana</h1>
          <p className="text-sm text-muted-foreground">
            Sumário diário executivo do seu negócio — vendas, inadimplência, tickets, NF-e e oportunidades.
          </p>
        </div>

        <Card className="border-dashed">
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-base">
              <Sparkles className="h-4 w-4 text-violet-500" />
              UI em construção
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-muted-foreground">
            <p>
              O Brief diário é gerado pelo agente <code className="rounded bg-muted px-1 py-0.5">BriefDiarioAgent</code>
              {' '}com base nas últimas 24h do business e fica disponível via MCP tool{' '}
              <code className="rounded bg-muted px-1 py-0.5">brief-fetch</code>.
            </p>
            <p>
              Enquanto a tela dedicada não estiver pronta, peça o brief direto à Jana — ela carrega o snapshot
              e te explica em linguagem natural.
            </p>
            <div className="flex flex-wrap gap-2 pt-2">
              <Link href="/jana">
                <Button size="sm" className="gap-2">
                  <MessageSquare className="h-4 w-4" />
                  Pedir brief à Jana
                </Button>
              </Link>
              <Link href="/jana/painel">
                <Button size="sm" variant="outline" className="gap-2">
                  <FileText className="h-4 w-4" />
                  Ver painel V2
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  )
}

BriefIndex.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Jana — Brief" breadcrumbItems={[{ label: 'Jana' }, { label: 'Brief' }]}>
    {page}
  </AppShellV2>
)
