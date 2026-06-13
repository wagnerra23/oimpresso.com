// @ads
//   tela: /ads/admin/skills/{slug}/edit
//   adrs: 0076 (Fase 2) — editor inline cria nova version status=draft

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, useForm } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import PageHeader from '@/Components/shared/PageHeader'
import { ArrowLeft, Save, AlertCircle } from 'lucide-react'

interface Skill {
  slug: string
  frontmatter: Record<string, any>
  body: string
  git_path: string
  source: 'db' | 'filesystem'
}

interface Props {
  skill: Skill
  frontmatterYaml: string
  currentVersion: number | null
}

const Edit: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ skill, frontmatterYaml, currentVersion }) => {
  const { data, setData, post, processing, errors } = useForm({
    frontmatter_yaml:         frontmatterYaml,
    body_markdown:            skill.body,
    rationale_problem:        '',
    rationale_hypothesis:     '',
    rationale_success_metric: '',
    rationale_rollback:       '',
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post(`/ads/admin/skills/${skill.slug}`, {
      preserveScroll: true,
    })
  }

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-4">
      <Link href={`/ads/admin/skills/${skill.slug}`} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
        <ArrowLeft className="w-4 h-4" /> Voltar pro detalhe
      </Link>

      <PageHeader
        icon="zap"
        title={`Editar: ${skill.frontmatter?.name || skill.slug}`}
        description={`Cria nova version draft em DB. Approval queue vai promover pra production. Versão atual: v${currentVersion ?? '?'}.`}
      />

      <form onSubmit={submit} className="space-y-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Frontmatter (YAML)</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {data.frontmatter_yaml !== frontmatterYaml && (
              <div className="rounded-md border border-warning/30 bg-warning-soft px-3 py-2 text-xs text-warning-fg">
                ⚠️ <strong>Frontmatter modificado.</strong> Mudanças aqui são <em>alto-impacto</em>:
                <code className="px-1">description</code> afeta auto-activation;
                <code className="px-1">name</code> é a chave do matching. Confira antes de salvar.
              </div>
            )}
            <Textarea
              value={data.frontmatter_yaml}
              onChange={e => setData('frontmatter_yaml', e.target.value)}
              rows={8}
              className={`font-mono text-xs ${data.frontmatter_yaml !== frontmatterYaml ? 'border-warning bg-warning-soft/50' : ''}`}
              spellCheck={false}
            />
            {errors.frontmatter_yaml && (
              <div className="flex items-start gap-1 text-xs text-destructive">
                <AlertCircle className="w-3.5 h-3.5 mt-0.5" />
                <span>{errors.frontmatter_yaml}</span>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Body (markdown)</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <Textarea
              value={data.body_markdown}
              onChange={e => setData('body_markdown', e.target.value)}
              rows={24}
              className="font-mono text-xs"
              spellCheck={false}
            />
            <p className="text-xs text-muted-foreground tabular-nums">
              {data.body_markdown.length.toLocaleString('pt-BR')} chars · {data.body_markdown.split('\n').length} linhas
            </p>
            {errors.body_markdown && (
              <div className="flex items-start gap-1 text-xs text-destructive">
                <AlertCircle className="w-3.5 h-3.5 mt-0.5" />
                <span>{errors.body_markdown}</span>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Rationale (4 campos obrigatórios)</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-xs text-muted-foreground">
              Forçar pensamento estruturado antes de mudar. Estado-da-arte 2026 — ninguém faz isso (Langfuse/LangSmith têm só commit message livre).
            </p>

            <div className="space-y-1">
              <Label htmlFor="rp">Problema observado <Badge variant="outline" className="ml-1 text-xs">obrigatório</Badge></Label>
              <Textarea
                id="rp"
                value={data.rationale_problem}
                onChange={e => setData('rationale_problem', e.target.value)}
                rows={2}
                placeholder="Ex: skill não está matchando em queries que mencionam 'faturamento'"
              />
              {errors.rationale_problem && <p className="text-xs text-destructive">{errors.rationale_problem}</p>}
            </div>

            <div className="space-y-1">
              <Label htmlFor="rh">Hipótese de fix <Badge variant="outline" className="ml-1 text-xs">obrigatório</Badge></Label>
              <Textarea
                id="rh"
                value={data.rationale_hypothesis}
                onChange={e => setData('rationale_hypothesis', e.target.value)}
                rows={2}
                placeholder="Ex: ajustar description pra incluir 'faturamento' e 'vendas'"
              />
              {errors.rationale_hypothesis && <p className="text-xs text-destructive">{errors.rationale_hypothesis}</p>}
            </div>

            <div className="space-y-1">
              <Label htmlFor="rm">Métrica de sucesso <Badge variant="outline" className="ml-1 text-xs">obrigatório</Badge></Label>
              <Textarea
                id="rm"
                value={data.rationale_success_metric}
                onChange={e => setData('rationale_success_metric', e.target.value)}
                rows={2}
                placeholder="Ex: hits 7d sobe de 8 → 15 nas próximas 2 semanas"
              />
              {errors.rationale_success_metric && <p className="text-xs text-destructive">{errors.rationale_success_metric}</p>}
            </div>

            <div className="space-y-1">
              <Label htmlFor="rr">Plano de rollback <Badge variant="outline" className="ml-1 text-xs">obrigatório</Badge></Label>
              <Textarea
                id="rr"
                value={data.rationale_rollback}
                onChange={e => setData('rationale_rollback', e.target.value)}
                rows={2}
                placeholder={`Ex: mover label production de volta pra v${currentVersion ?? 1}`}
              />
              {errors.rationale_rollback && <p className="text-xs text-destructive">{errors.rationale_rollback}</p>}
            </div>
          </CardContent>
        </Card>

        <div className="flex items-center justify-end gap-2">
          <Link href={`/ads/admin/skills/${skill.slug}`}>
            <Button type="button" variant="outline">Cancelar</Button>
          </Link>
          <Button type="submit" disabled={processing}>
            <Save className="w-4 h-4 mr-1" />
            {processing ? 'Salvando…' : 'Salvar como draft'}
          </Button>
        </div>
      </form>
    </div>
  )
}

Edit.layout = (page: ReactNode) => (
  <AppShellV2 title="Editar Skill" breadcrumbItems={[{ label: 'ADS' }, { label: 'Skills', href: '/ads/admin/skills' }, { label: 'Editar' }]}>
    {page}
  </AppShellV2>
)

export default Edit
