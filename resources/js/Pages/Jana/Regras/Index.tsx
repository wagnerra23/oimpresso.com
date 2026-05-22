// Stub /jana/regras — ghost canon ADR 0182 + GUIA-SIDEBAR-V3 (Wagner 2026-05-21).
// "Regras" cobre policies do PolicyEngine ADS (ALLOW_BRAIN_A / REQUIRE_HUMAN_REVIEW
// etc.) + governance MCP cross-team. UI dedicada vem em onda futura; este stub
// mantém o ghost clicável e aponta pra /jana/admin/governanca enquanto isso.

import React from 'react'
import { Head, Link } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { JanaAreaHeader } from '@/Pages/Jana/components/JanaAreaHeader'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { ShieldCheck, ScrollText } from 'lucide-react'

interface Props {
  businessId: number
}

export default function RegrasIndex({ businessId }: Props) {
  return (
    <>
      <Head title="Jana · Regras" />
      <JanaAreaHeader active="regras" />

      <div className="mx-auto max-w-3xl space-y-6 p-6">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <span
              aria-hidden
              className="inline-block size-2 rounded-full"
              style={{ background: 'oklch(0.62 0.13 220)' }}
            />
            <span className="text-xs uppercase tracking-wide text-muted-foreground">
              regras &amp; políticas · business {businessId}
            </span>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight">Regras da Jana</h1>
          <p className="text-sm text-muted-foreground">
            Políticas que regem quando a Jana decide sozinha, quando pede revisão humana e quais ações ficam bloqueadas.
          </p>
        </div>

        <Card className="border-dashed">
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-base">
              <ShieldCheck className="h-4 w-4 text-violet-500" />
              UI em construção
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-muted-foreground">
            <p>
              As regras são governadas pelo <code className="rounded bg-muted px-1 py-0.5">PolicyEngine</code> do
              módulo ADS (4 outcomes: <em>ALLOW_BRAIN_A</em>, <em>REQUIRE_BRAIN_B</em>, <em>REQUIRE_HUMAN_REVIEW</em>,
              <em>BLOCK_ALWAYS</em>) e pelo governance MCP cross-team.
            </p>
            <p>
              Enquanto a tela dedicada não está pronta, consulte o painel de Governança da Jana.
            </p>
            <div className="flex flex-wrap gap-2 pt-2">
              <Link href="/jana/admin/governanca">
                <Button size="sm" className="gap-2">
                  <ScrollText className="h-4 w-4" />
                  Ver governança
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  )
}

RegrasIndex.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Jana — Regras" breadcrumbItems={[{ label: 'Jana' }, { label: 'Regras' }]}>
    {page}
  </AppShellV2>
)
