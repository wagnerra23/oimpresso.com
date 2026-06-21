// Forja Triagem — dossiê do Analista (drawer) da aba /forja (Triagem).
//   Endpoint: GET /forja/{taskId}/dossier (read-only, SÓ dados reais).
//   Ações [W] confirma: aprovar (→backlog), rejeitar (→cancelled), fundir (duplicata).
//   "Agente propõe, [W] aprova" — nada vira oficial sem confirmação.
//
// Espelha resources/js/Pages/ProjectMgmt/Triage/_components/TriageDossier.tsx
// (PR-5a) — só muda o prefixo de rota /project-mgmt/triage → /forja. DS v6:
// tokens semânticos, layout via inline-flex/inline-grid, data-testid locators.

import { useEffect, useState } from 'react';
import {
  Activity as ActivityIcon, AlertTriangle, CheckCircle2, ExternalLink, FileText,
  GitMerge, Loader2, ShieldCheck, XCircle,
} from 'lucide-react';
import {
  Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle,
} from '@/Components/ui/sheet';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { cn } from '@/Lib/utils';

const GH = 'https://github.com/wagnerra23/oimpresso.com/blob/main/';

interface DossierTask {
  task_id: string;
  display_id: string;
  title: string;
  module: string | null;
  owner: string | null;
  priority_raw: string | null;
  priority: string;
  status: string;
  type: string | null;
  forja_tipo: string | null;
  forja_papel: string | null;
}
interface Dup { task_id: string; display_id: string; title: string; status: string; owner: string | null }
interface Ev { event_type: string; from_value: string | null; to_value: string | null; author: string | null; note: string | null; occurred_at: string | null }
interface Doc { slug: string; type: string; title: string; path: string | null }
interface Sess { session_uuid: string; summary: string | null; started_at: string | null }

interface Dossier {
  task: DossierTask;
  description: string | null;
  duplicatas: Dup[];
  atividade: Ev[];
  docs: Doc[];
  sessoes: Sess[];
  valor_esforco: { valor: string; esforco: string };
  risco_tier0: { tier0: boolean; sinais: string[] };
  charter_ref: string | null;
  pode_aprovar: boolean;
}

type ConfirmAction = { title: string; description: string; confirmLabel: string; destructive: boolean; run: () => void } | null;

interface Props {
  taskId: string | null;
  onClose: () => void;
  onResolved: (taskId: string) => void;
}

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export default function ForjaDossier({ taskId, onClose, onResolved }: Props) {
  const [data, setData] = useState<Dossier | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [confirm, setConfirm] = useState<ConfirmAction>(null);

  useEffect(() => {
    if (!taskId) return;
    setLoading(true); setError(null); setData(null);
    const ctrl = new AbortController();
    fetch(`/forja/${encodeURIComponent(taskId)}/dossier`, {
      headers: { Accept: 'application/json' }, signal: ctrl.signal,
    })
      .then((r) => {
        if (r.status === 403) throw new Error('Sem permissão.');
        if (r.status === 404) throw new Error('Task não encontrada.');
        if (!r.ok) throw new Error(`Erro ${r.status}`);
        return r.json();
      })
      .then((d: Dossier) => { setData(d); setLoading(false); })
      .catch((e: Error) => { if (e.name === 'AbortError') return; setError(e.message); setLoading(false); });
    return () => ctrl.abort();
  }, [taskId]);

  function act(path: string, body?: Record<string, unknown>) {
    if (!taskId) return;
    setBusy(true);
    fetch(`/forja/${encodeURIComponent(taskId)}/${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(body ?? {}),
    })
      .then(async (r) => {
        const d = await r.json().catch(() => ({}));
        setBusy(false);
        if (!r.ok) { setError(d?.error ?? `Erro ${r.status}`); return; }
        onResolved(taskId);
        onClose();
      })
      .catch(() => { setBusy(false); setError('Erro de rede.'); });
  }

  const open = !!taskId;
  const t = data?.task;
  const ve = data?.valor_esforco;
  const risk = data?.risco_tier0;

  return (
    <>
      <Sheet open={open} onOpenChange={(v) => { if (!v && !busy) onClose(); }}>
        <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-[600px]" data-testid="forja-dossier">
          <SheetHeader className="border-b">
            <div className="inline-flex w-full items-center gap-2">
              <FileText size={14} className="text-muted-foreground" aria-hidden />
              <span className="font-mono text-xs text-muted-foreground" data-testid="dossier-id">{t?.display_id ?? taskId}</span>
              {t?.module && <span className="rounded bg-muted px-1.5 py-0.5 text-[10px]">{t.module}</span>}
              {t?.forja_papel && <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px]">[{t.forja_papel}]</span>}
            </div>
            <SheetTitle className="mt-1 text-base leading-snug" data-testid="dossier-title">
              {t?.title ?? (loading ? 'Carregando…' : 'Dossiê')}
            </SheetTitle>
            <SheetDescription className="mt-1 inline-flex w-full flex-wrap items-center gap-2 text-xs">
              <span>dono: <strong className="text-foreground">{t?.owner ?? '— sem dono —'}</strong></span>
              <span>prio: <strong className="text-foreground">{t?.priority_raw ? t.priority_raw.toUpperCase() : '— sem prio —'}</strong></span>
              <span>situação: {t?.status}</span>
            </SheetDescription>
          </SheetHeader>

          <div className="flex-1 space-y-5 overflow-y-auto px-4 py-4">
            {loading && (
              <div className="inline-flex w-full items-center justify-center py-12 text-sm text-muted-foreground">
                <Loader2 className="mr-2 h-4 w-4 animate-spin" /> montando dossiê…
              </div>
            )}
            {error && (
              <div className="rounded-md border border-destructive/20 bg-destructive-soft px-3 py-2 text-sm text-destructive-fg" data-testid="dossier-error">
                {error}
              </div>
            )}

            {!loading && data && t && (
              <>
                {/* Valor × esforço + Risco */}
                <div className="inline-grid w-full grid-cols-2 gap-3">
                  <div className="rounded-lg border bg-card p-3">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Valor × esforço <span className="normal-case">(sugerido)</span></div>
                    <div className="mt-1 inline-flex gap-2 text-xs">
                      <span className="rounded bg-muted px-1.5 py-0.5">valor: <strong>{ve?.valor}</strong></span>
                      <span className="rounded bg-muted px-1.5 py-0.5">esforço: <strong>{ve?.esforco}</strong></span>
                    </div>
                  </div>
                  <div className={cn('rounded-lg border p-3', risk?.tier0 ? 'border-destructive/30 bg-destructive-soft' : 'border-success/30 bg-success/10')}>
                    <div className="inline-flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                      {risk?.tier0 ? <AlertTriangle size={12} className="text-destructive" /> : <ShieldCheck size={12} className="text-success" />}
                      Risco Tier-0 <span className="normal-case">(heurística)</span>
                    </div>
                    <div className="mt-1 text-xs">
                      {risk?.tier0
                        ? <span className="text-destructive-fg">sinais: {risk.sinais.join(', ')}</span>
                        : <span className="text-success-fg">sem sinal de Tier-0</span>}
                    </div>
                  </div>
                </div>

                {data.description && (
                  <div>
                    <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Descrição</h4>
                    <p className="whitespace-pre-wrap text-sm leading-relaxed">{data.description}</p>
                  </div>
                )}

                {/* Requisitos / charter */}
                {data.charter_ref && (
                  <div>
                    <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Requisitos do módulo</h4>
                    <a href={GH + data.charter_ref} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                      <FileText size={12} /> {data.charter_ref} <ExternalLink size={10} />
                    </a>
                  </div>
                )}

                {/* Duplicatas (com Fundir) */}
                <div data-testid="dossier-duplicatas">
                  <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Possíveis duplicatas <span className="normal-case">(mesmo módulo)</span></h4>
                  {data.duplicatas.length === 0 ? (
                    <p className="text-xs italic text-muted-foreground">Nenhuma no módulo {t.module}.</p>
                  ) : (
                    <ul className="space-y-1">
                      {data.duplicatas.map((d) => (
                        <li key={d.task_id} className="inline-flex w-full items-center gap-2 rounded px-2 py-1 text-xs hover:bg-muted/50">
                          <span className="font-mono text-muted-foreground">{d.display_id}</span>
                          <span className="min-w-0 flex-1 truncate">{d.title}</span>
                          <span className="shrink-0 text-[10px] text-muted-foreground">{d.status}</span>
                          <Button
                            size="sm" variant="ghost" className="h-6 shrink-0 px-1.5 text-[11px]"
                            disabled={busy}
                            onClick={() => setConfirm({
                              title: `Fundir ${t.display_id} em ${d.display_id}?`,
                              description: `${t.display_id} será cancelada e marcada como duplicata de ${d.display_id} (${d.title}). Registra evento em mcp_task_events. Não pode ser desfeito automaticamente.`,
                              confirmLabel: 'Fundir',
                              destructive: true,
                              run: () => act('fundir', { target_task_id: d.task_id }),
                            })}
                          >
                            <GitMerge size={12} className="mr-1" /> fundir aqui
                          </Button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>

                {/* Histórico de decisão: docs/ADRs */}
                <div data-testid="dossier-docs">
                  <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Histórico de decisão <span className="normal-case">(docs/ADRs do módulo)</span></h4>
                  {data.docs.length === 0 ? (
                    <p className="text-xs italic text-muted-foreground">Sem docs indexados pro módulo.</p>
                  ) : (
                    <ul className="space-y-1">
                      {data.docs.map((d) => (
                        <li key={d.slug} className="inline-flex w-full items-center gap-2 text-xs">
                          <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] uppercase text-muted-foreground">{d.type === 'decision' ? 'ADR' : d.type}</span>
                          {d.path
                            ? <a href={GH + d.path} target="_blank" rel="noopener noreferrer" className="min-w-0 flex-1 truncate text-primary hover:underline">{d.title}</a>
                            : <span className="min-w-0 flex-1 truncate">{d.title}</span>}
                        </li>
                      ))}
                    </ul>
                  )}
                </div>

                {/* Sessões CC relacionadas */}
                {data.sessoes.length > 0 && (
                  <div>
                    <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Sessões CC que citam o módulo</h4>
                    <ul className="space-y-1">
                      {data.sessoes.map((s) => (
                        <li key={s.session_uuid} className="inline-flex w-full items-start gap-2 text-xs">
                          <span className="font-mono text-[10px] text-muted-foreground">{s.session_uuid.slice(0, 8)}</span>
                          <span className="min-w-0 flex-1 line-clamp-2 text-muted-foreground">{s.summary ?? '(sem summary)'}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* Atividade */}
                {data.atividade.length > 0 && (
                  <div data-testid="dossier-atividade">
                    <h4 className="mb-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground"><ActivityIcon size={12} /> Atividade</h4>
                    <ul className="space-y-1">
                      {data.atividade.slice(0, 12).map((e, i) => (
                        <li key={i} className="text-xs text-muted-foreground">
                          <span className="font-semibold text-foreground">@{e.author ?? 'system'}</span> {e.event_type}
                          {e.from_value && e.to_value && <> ({e.from_value}→{e.to_value})</>}
                          {e.note && <> · {e.note}</>}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </>
            )}
          </div>

          {/* Ações [W] aprova */}
          {!loading && data && (
            <div className="inline-flex w-full items-center gap-2 border-t px-4 py-3">
              <Button
                className="flex-1"
                disabled={busy || !data.pode_aprovar}
                title={data.pode_aprovar ? 'Promove pro backlog ativo' : 'Defina dono + prioridade antes de aprovar'}
                onClick={() => setConfirm({
                  title: `Aprovar ${t?.display_id}?`,
                  description: 'Promove a proposta pro backlog ativo (status → todo). Vira task oficial.',
                  confirmLabel: 'Aprovar',
                  destructive: false,
                  run: () => act('aprovar'),
                })}
                data-testid="dossier-aprovar"
              >
                <CheckCircle2 size={14} className="mr-1" /> Aprovar → backlog
              </Button>
              <Button
                variant="outline"
                className="text-destructive"
                disabled={busy}
                onClick={() => setConfirm({
                  title: `Rejeitar ${t?.display_id}?`,
                  description: 'Cancela a proposta (status → cancelled). O audit log é preservado.',
                  confirmLabel: 'Rejeitar',
                  destructive: true,
                  run: () => act('rejeitar'),
                })}
                data-testid="dossier-rejeitar"
              >
                <XCircle size={14} className="mr-1" /> Rejeitar
              </Button>
            </div>
          )}
        </SheetContent>
      </Sheet>

      <AlertDialog open={confirm !== null} onOpenChange={(o) => { if (!o) setConfirm(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{confirm?.title}</AlertDialogTitle>
            <AlertDialogDescription>{confirm?.description}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              variant={confirm?.destructive ? 'destructive' : 'default'}
              onClick={() => { confirm?.run(); setConfirm(null); }}
            >
              {confirm?.confirmLabel ?? 'Confirmar'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
