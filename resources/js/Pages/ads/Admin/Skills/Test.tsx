// @ads
//   tela: /ads/admin/skills/{slug}/test
//   adrs: 0076 (Fase 3) — roda skill contra prompt do user, salva em mcp_skill_test_runs

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, useForm, usePage } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import PageHeader from '@/Components/shared/PageHeader'
import { ArrowLeft, Play, Clock, Hash, ShieldAlert } from 'lucide-react'

interface Skill {
  slug: string
  frontmatter: Record<string, any>
  body: string
  git_path: string
  source: 'db' | 'filesystem'
}

interface TestRun {
  id: number
  version_id: number
  prompt_preview: string
  output_preview: string
  latency_ms: number | null
  output_tokens: number | null
  pii_count: number
  executed_at: string | null
}

interface Props {
  skill: Skill
  currentVersion: number | null
  currentVersionId: number | null
  recentRuns: TestRun[]
  dryRun: boolean
}

const Test: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ skill, currentVersion, recentRuns, dryRun }) => {
  const flash = (usePage().props as any)?.flash?.status
  const { data, setData, post, processing } = useForm({
    prompt: '',
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post(`/ads/admin/skills/${skill.slug}/test`, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setData('prompt', ''),
    })
  }

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-4">
      <Link href={`/ads/admin/skills/${skill.slug}`} className="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
        <ArrowLeft className="w-4 h-4" /> Voltar pro detalhe
      </Link>

      <PageHeader
        icon="zap"
        title={`Testar: ${skill.frontmatter?.name || skill.slug}`}
        description={`Roda a versão production (v${currentVersion ?? '?'}) contra o prompt fornecido. PII redactor obrigatório (CPF/CNPJ mascarados antes da chamada). Resultado salvo em mcp_skill_test_runs.`}
        action={dryRun ? <Badge variant="outline">DRY RUN (sem chamar API)</Badge> : <Badge>LIVE</Badge>}
      />

      {flash && (
        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">
          {flash}
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Prompt de teste</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-3">
            <Textarea
              value={data.prompt}
              onChange={e => setData('prompt', e.target.value)}
              rows={8}
              placeholder="Escreva o prompt que você quer testar contra esta skill (ex: 'Crie um módulo Laravel chamado Foo')"
              spellCheck={false}
            />
            <div className="flex items-center justify-between text-xs text-muted-foreground">
              <span>{data.prompt.length} chars · 3 ≤ N ≤ 8000</span>
              <Button type="submit" disabled={processing || data.prompt.length < 3}>
                <Play className="w-4 h-4 mr-1" />
                {processing ? 'Rodando…' : 'Rodar teste'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-sm flex items-center gap-2">
            Últimas execuções ({recentRuns.length})
          </CardTitle>
        </CardHeader>
        <CardContent>
          {recentRuns.length === 0 ? (
            <p className="text-sm text-muted-foreground">Nenhum teste rodado ainda. Use o form acima.</p>
          ) : (
            <div className="space-y-3">
              {recentRuns.map(r => (
                <div key={r.id} className="rounded-md border p-3 space-y-2">
                  <div className="flex items-center gap-3 text-xs text-muted-foreground tabular-nums">
                    <span>#{r.id}</span>
                    <span>{r.executed_at}</span>
                    <span className="inline-flex items-center gap-1"><Clock className="w-3 h-3" /> {r.latency_ms ?? '?'}ms</span>
                    <span className="inline-flex items-center gap-1"><Hash className="w-3 h-3" /> {r.output_tokens ?? '?'} tokens</span>
                    {r.pii_count > 0 && (
                      <span className="inline-flex items-center gap-1 text-amber-600">
                        <ShieldAlert className="w-3 h-3" /> {r.pii_count} PII redactions
                      </span>
                    )}
                  </div>
                  <div>
                    <div className="text-xs font-medium text-muted-foreground mb-0.5">Prompt:</div>
                    <p className="text-xs font-mono bg-muted/30 rounded px-2 py-1">{r.prompt_preview}…</p>
                  </div>
                  <div>
                    <div className="text-xs font-medium text-muted-foreground mb-0.5">Output (200 chars):</div>
                    <p className="text-xs font-mono whitespace-pre-wrap leading-relaxed">{r.output_preview}…</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground">
        Em DRY_RUN não chama Anthropic API — retorna fixture. Pra rodar de verdade, configure ANTHROPIC_API_KEY no .env.
        Approval queue (Fase 4) vai exigir ≥1 test run aprovado pra mover label production.
      </p>
    </div>
  )
}

Test.layout = (page: ReactNode) => (
  <AppShellV2 title="Testar Skill" breadcrumbItems={[{ label: 'ADS' }, { label: 'Skills', href: '/ads/admin/skills' }, { label: 'Testar' }]}>
    {page}
  </AppShellV2>
)

export default Test
