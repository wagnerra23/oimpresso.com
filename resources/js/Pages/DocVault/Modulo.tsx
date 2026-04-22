import AppShell from '@/Layouts/AppShell';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import {
  ArrowLeft,
  Boxes,
  CheckCircle2,
  Circle,
  ClipboardList,
  Code,
  ExternalLink,
  FileText,
  History,
  Lightbulb,
  Scale,
  Target,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

interface Story {
  id: string;
  title: string;
  dod_total: number;
  dod_done: number;
  implementado_em: string | null;
}

interface Rule {
  id: string;
  title: string;
  testado_em: string | null;
}

interface Frontmatter {
  module?: string;
  alias?: string;
  status?: string;
  migration_target?: string;
  migration_priority?: string;
  risk?: string;
  areas?: string[];
  last_generated?: string;
  [k: string]: any;
}

interface Adr {
  number: string;
  slug: string;
  title: string;
  status: string;
  date: string | null;
  raw: string;
}

interface Props {
  module: string;
  format?: 'folder' | 'flat';
  frontmatter: Frontmatter;
  stories: Story[];
  rules: Rule[];
  raw: string;
  readme?: string | null;
  architecture?: string | null;
  changelog?: string | null;
  adrs?: Adr[];
  size_kb: number;
  mtime: string;
}

type Tab = 'overview' | 'architecture' | 'stories' | 'rules' | 'adrs' | 'changelog' | 'raw';

export default function DocVaultModulo({
  module,
  format = 'flat',
  frontmatter,
  stories,
  rules,
  raw,
  readme,
  architecture,
  changelog,
  adrs = [],
  size_kb,
  mtime,
}: Props) {
  const [tab, setTab] = useState<Tab>('overview');
  const [selectedAdr, setSelectedAdr] = useState<Adr | null>(null);
  const hasFolder = format === 'folder';

  const totalDod = stories.reduce((acc, s) => acc + s.dod_total, 0);
  const doneDod = stories.reduce((acc, s) => acc + s.dod_done, 0);
  const dodPct = totalDod > 0 ? Math.round((doneDod / totalDod) * 100) : 0;

  const storiesImplementadas = stories.filter((s) => s.implementado_em).length;
  const rulesTestadas = rules.filter((r) => r.testado_em).length;

  return (
    <AppShell
      title={`DocVault — ${module}`}
      breadcrumb={[
        { label: 'DocVault', href: '/docs' },
        { label: module },
      ]}
    >
      <div className="mx-auto max-w-6xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2 flex-wrap">
              <FileText size={22} />
              {module}
              {frontmatter.status && (
                <Badge variant={frontmatter.status === 'ativo' ? 'default' : 'outline'} className="text-[10px]">
                  {frontmatter.status}
                </Badge>
              )}
              {frontmatter.risk && (
                <Badge variant="secondary" className="text-[10px]">
                  risco: {frontmatter.risk}
                </Badge>
              )}
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Requisitos funcionais consolidados · {size_kb} KB · atualizado {mtime}
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" asChild>
              <Link href="/docs">
                <ArrowLeft size={14} className="mr-1.5" /> Voltar
              </Link>
            </Button>
          </div>
        </header>

        {/* Frontmatter compacto */}
        {frontmatter.areas && frontmatter.areas.length > 0 && (
          <Card>
            <CardContent className="pt-4">
              <div className="text-xs text-muted-foreground mb-1">Áreas funcionais</div>
              <div className="flex flex-wrap gap-1">
                {frontmatter.areas.map((area, i) => (
                  <Badge key={i} variant="outline" className="text-[10px]">
                    {area}
                  </Badge>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {/* KPIs */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Kpi icon={<FileText size={14} />} label="User stories" value={stories.length} hint={`${storiesImplementadas} implementadas`} />
          <Kpi icon={<Scale size={14} />} label="Regras" value={rules.length} hint={`${rulesTestadas} testadas`} />
          <Kpi icon={<Target size={14} />} label="DoD progresso" value={`${dodPct}%`} hint={`${doneDod}/${totalDod}`} />
          <Kpi icon={<Code size={14} />} label="Tela React" value={storiesImplementadas > 0 ? 'sim' : 'não'} />
        </div>

        {/* Tabs */}
        <div className="border-b border-border flex gap-1 flex-wrap">
          <TabBtn active={tab === 'overview'} onClick={() => setTab('overview')}>
            <FileText size={13} className="mr-1" /> Overview
          </TabBtn>
          {architecture && (
            <TabBtn active={tab === 'architecture'} onClick={() => setTab('architecture')}>
              <Boxes size={13} className="mr-1" /> Arquitetura
            </TabBtn>
          )}
          <TabBtn active={tab === 'stories'} onClick={() => setTab('stories')}>
            <ClipboardList size={13} className="mr-1" /> User stories
            <Badge variant="secondary" className="ml-1 text-[10px]">{stories.length}</Badge>
          </TabBtn>
          <TabBtn active={tab === 'rules'} onClick={() => setTab('rules')}>
            <Scale size={13} className="mr-1" /> Regras
            <Badge variant="secondary" className="ml-1 text-[10px]">{rules.length}</Badge>
          </TabBtn>
          {adrs.length > 0 && (
            <TabBtn active={tab === 'adrs'} onClick={() => setTab('adrs')}>
              <Lightbulb size={13} className="mr-1" /> Decisões
              <Badge variant="secondary" className="ml-1 text-[10px]">{adrs.length}</Badge>
            </TabBtn>
          )}
          {changelog && (
            <TabBtn active={tab === 'changelog'} onClick={() => setTab('changelog')}>
              <History size={13} className="mr-1" /> Changelog
            </TabBtn>
          )}
          <TabBtn active={tab === 'raw'} onClick={() => setTab('raw')}>
            <Code size={13} className="mr-1" /> Markdown
          </TabBtn>
        </div>

        {tab === 'overview' && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center justify-between">
                <span>{hasFolder ? 'Visão geral' : 'Resumo'}</span>
                <Badge variant="outline" className="text-[10px]">
                  {hasFolder ? 'pasta' : 'arquivo plano'}
                </Badge>
              </CardTitle>
            </CardHeader>
            <CardContent className="text-sm space-y-3">
              {readme && (
                <pre className="text-xs whitespace-pre-wrap font-mono bg-muted/30 rounded p-3 overflow-x-auto">
                  {readme}
                </pre>
              )}
              <div>
                <strong>Fonte:</strong>{' '}
                <code className="text-xs">
                  memory/requisitos/{module}{hasFolder ? '/' : '.md'}
                </code>
              </div>
              <div className="grid grid-cols-2 gap-2">
                {Object.entries(frontmatter).filter(([k]) => !['areas'].includes(k)).map(([k, v]) => (
                  <div key={k} className="text-xs">
                    <span className="text-muted-foreground">{k}:</span>{' '}
                    <span className="font-mono">{Array.isArray(v) ? v.join(', ') : String(v)}</span>
                  </div>
                ))}
              </div>
              {!hasFolder && (
                <div className="pt-3 border-t border-border">
                  <p className="text-xs text-muted-foreground">
                    Este módulo ainda está no formato plano (arquivo único). Para migrar para a estrutura nova com
                    arquitetura/spec/changelog separados, crie a pasta{' '}
                    <code className="text-xs">memory/requisitos/{module}/</code> com README.md, ARCHITECTURE.md,
                    SPEC.md e CHANGELOG.md.
                  </p>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {tab === 'architecture' && architecture && (
          <Card>
            <CardContent>
              <pre className="text-xs overflow-x-auto whitespace-pre-wrap font-mono">{architecture}</pre>
            </CardContent>
          </Card>
        )}

        {tab === 'changelog' && changelog && (
          <Card>
            <CardContent>
              <pre className="text-xs overflow-x-auto whitespace-pre-wrap font-mono">{changelog}</pre>
            </CardContent>
          </Card>
        )}

        {tab === 'adrs' && adrs.length > 0 && (
          <div className="grid md:grid-cols-[280px_1fr] gap-3">
            <Card>
              <CardContent className="p-0">
                <ul className="divide-y divide-border">
                  {adrs.map((a) => {
                    const toneCls = a.status === 'accepted'
                      ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                      : a.status === 'proposed'
                      ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                      : a.status === 'deprecated' || a.status.startsWith('superseded')
                      ? 'bg-muted text-muted-foreground line-through'
                      : 'bg-muted text-muted-foreground';
                    const active = selectedAdr?.slug === a.slug;
                    return (
                      <li
                        key={a.slug}
                        onClick={() => setSelectedAdr(a)}
                        className={`p-3 cursor-pointer hover:bg-accent/30 ${active ? 'bg-accent/50' : ''}`}
                      >
                        <div className="flex items-center gap-2">
                          <code className="text-[10px] font-mono bg-muted px-1.5 py-0.5 rounded">
                            {a.number}
                          </code>
                          <span className={`text-[10px] px-1.5 py-0.5 rounded ${toneCls}`}>
                            {a.status}
                          </span>
                        </div>
                        <div className="text-sm font-medium mt-1 line-clamp-2">{a.title}</div>
                        {a.date && (
                          <div className="text-[10px] text-muted-foreground mt-0.5 font-mono">
                            {a.date}
                          </div>
                        )}
                      </li>
                    );
                  })}
                </ul>
              </CardContent>
            </Card>
            <Card>
              <CardContent>
                {selectedAdr ? (
                  <pre className="text-xs overflow-x-auto whitespace-pre-wrap font-mono">
                    {selectedAdr.raw}
                  </pre>
                ) : (
                  <div className="p-8 text-center text-sm text-muted-foreground">
                    Selecione uma decisão à esquerda.
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        )}

        {tab === 'stories' && (
          <Card>
            <CardContent className="p-0">
              {stories.length === 0 ? (
                <div className="p-12 text-center text-sm text-muted-foreground">
                  Nenhuma user story escrita ainda para este módulo.
                </div>
              ) : (
                <ul className="divide-y divide-border">
                  {stories.map((s) => {
                    const pct = s.dod_total > 0 ? Math.round((s.dod_done / s.dod_total) * 100) : 0;
                    return (
                      <li key={s.id} className="p-4 hover:bg-accent/30">
                        <div className="flex items-start gap-3">
                          {s.implementado_em ? (
                            <CheckCircle2 size={16} className="text-emerald-600 flex-shrink-0 mt-0.5" />
                          ) : (
                            <Circle size={16} className="text-muted-foreground flex-shrink-0 mt-0.5" />
                          )}
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                              <code className="text-xs bg-muted px-1.5 py-0.5 rounded">{s.id}</code>
                              <span className="font-medium text-sm">{s.title}</span>
                            </div>
                            {s.implementado_em && (
                              <div className="text-[10px] text-emerald-700 dark:text-emerald-400 mt-1 font-mono">
                                → {s.implementado_em}
                              </div>
                            )}
                            {s.dod_total > 0 && (
                              <div className="flex items-center gap-2 mt-2">
                                <div className="flex-1 h-1 bg-muted rounded overflow-hidden max-w-xs">
                                  <div
                                    className={`h-full ${pct >= 80 ? 'bg-emerald-500' : pct >= 40 ? 'bg-amber-500' : 'bg-red-500'}`}
                                    style={{ width: `${pct}%` }}
                                  />
                                </div>
                                <span className="text-[10px] text-muted-foreground font-mono">
                                  {s.dod_done}/{s.dod_total} DoD
                                </span>
                              </div>
                            )}
                          </div>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              )}
            </CardContent>
          </Card>
        )}

        {tab === 'rules' && (
          <Card>
            <CardContent className="p-0">
              {rules.length === 0 ? (
                <div className="p-12 text-center text-sm text-muted-foreground">
                  Nenhuma regra Gherkin escrita ainda.
                </div>
              ) : (
                <ul className="divide-y divide-border">
                  {rules.map((r) => (
                    <li key={r.id} className="p-4 hover:bg-accent/30">
                      <div className="flex items-start gap-3">
                        {r.testado_em ? (
                          <CheckCircle2 size={16} className="text-emerald-600 flex-shrink-0 mt-0.5" />
                        ) : (
                          <Circle size={16} className="text-muted-foreground flex-shrink-0 mt-0.5" />
                        )}
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 flex-wrap">
                            <code className="text-xs bg-muted px-1.5 py-0.5 rounded">{r.id}</code>
                            <span className="font-medium text-sm">{r.title}</span>
                          </div>
                          {r.testado_em && (
                            <div className="text-[10px] text-emerald-700 dark:text-emerald-400 mt-1 font-mono">
                              testado em: {r.testado_em}
                            </div>
                          )}
                        </div>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        )}

        {tab === 'raw' && (
          <Card>
            <CardContent>
              <pre className="text-xs overflow-x-auto whitespace-pre-wrap font-mono">{raw}</pre>
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  );
}

function Kpi({ icon, label, value, hint }: { icon: React.ReactNode; label: string; value: string | number; hint?: string }) {
  return (
    <Card>
      <CardContent className="pt-4">
        <div className="text-xs text-muted-foreground flex items-center gap-1 mb-1">
          {icon} {label}
        </div>
        <div className="text-xl font-bold">{value}</div>
        {hint && <div className="text-[10px] text-muted-foreground mt-0.5">{hint}</div>}
      </CardContent>
    </Card>
  );
}

function TabBtn({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`px-3 py-2 text-sm flex items-center border-b-2 -mb-px transition ${
        active
          ? 'border-primary text-foreground font-medium'
          : 'border-transparent text-muted-foreground hover:text-foreground'
      }`}
    >
      {children}
    </button>
  );
}
