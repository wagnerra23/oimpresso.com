// Charters — interface do Charter Governance (ADR 0243).
//   tela: /kb/charters
//   module: KB
//   Lista os *.charter.md (filesystem, read-only) reusando o tri-pane do kb/Index.
//   F1 (US-CHTR-002/003): aba Governança — propor sugestão → owner aprova/rejeita.
//   Núcleo imutável (git); a evolução acontece por sugestão supervisionada.
//   Spec: memory/requisitos/KB/INTERFACE-CHARTER-KB.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import {
  FileText, Search, Lock, ExternalLink, MessageSquarePlus, Loader2, X, Check, Ban, Send,
} from 'lucide-react';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import { ScrollArea } from '@/Components/ui/scroll-area';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { toast } from 'sonner';

interface CharterRow {
  path: string;
  title: string;
  module: string | null;
  screen: string | null;
  level: 'page' | 'module';
  status: string | null;
  tier: string | null;
  owner: string | null;
  size_chars: number;
}

interface Props {
  charters: CharterRow[];
  filters: { module?: string; q?: string };
  kpis: { total: number; modulos: Record<string, number>; modulos_total: number };
  github_repo: string;
}

interface CharterDetail {
  path: string;
  title: string;
  content_md: string;
  github_url: string | null;
}

interface Suggestion {
  id: number;
  anchor: string | null;
  kind: string;
  text: string;
  status: string;
  author_user_id: number | null;
  resolved_by_user_id: number | null;
  resolution_note: string | null;
  created_at: string | null;
}

function fmtSize(chars: number): string {
  if (!chars) return '—';
  if (chars < 1024) return `${chars}c`;
  return `${(chars / 1024).toFixed(1)}k`;
}

const STATUS_TONE: Record<string, string> = {
  live: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
  wip: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
  sunsetting: 'border-muted bg-muted text-muted-foreground',
};

const SUG_TONE: Record<string, string> = {
  proposed: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
  under_review: 'border-primary/30 bg-primary/10 text-primary',
  accepted: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
  rejected: 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-400',
  merged: 'border-primary/40 bg-primary/15 text-primary',
};

const KIND_LABEL: Record<string, string> = {
  suggestion: 'Sugestão', question: 'Dúvida', erratum: 'Errata', comment: 'Comentário',
};

function csrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function ChartersIndex({ charters, kpis, github_repo }: Props) {
  const [moduleFilter, setModuleFilter] = useState<string>('__all__');
  const [search, setSearch] = useState('');
  const [selectedPath, setSelectedPath] = useState<string | null>(null);
  const [detail, setDetail] = useState<CharterDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [tab, setTab] = useState<'contract' | 'governance'>('contract');
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [formOpen, setFormOpen] = useState(false);
  const [formKind, setFormKind] = useState('suggestion');
  const [formText, setFormText] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [resolving, setResolving] = useState<{ id: number; status: 'accepted' | 'rejected' } | null>(null);
  const [resolveNote, setResolveNote] = useState('');
  const searchRef = useRef<HTMLInputElement | null>(null);

  const modules = useMemo(
    () => Object.keys(kpis?.modulos ?? {}).sort((a, b) => a.localeCompare(b)),
    [kpis],
  );

  const visible = useMemo(() => {
    const term = search.trim().toLowerCase();
    return charters.filter((c) => {
      if (moduleFilter !== '__all__' && c.module !== moduleFilter) return false;
      if (term && !`${c.title} ${c.path}`.toLowerCase().includes(term)) return false;
      return true;
    });
  }, [charters, moduleFilter, search]);

  const openCount = suggestions.filter((s) => s.status === 'proposed' || s.status === 'under_review').length;

  function loadSuggestions(path: string) {
    fetch(`/kb/charters/suggestions?path=${encodeURIComponent(path)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((d: { suggestions: Suggestion[] }) => setSuggestions(d.suggestions ?? []))
      .catch(() => setSuggestions([]));
  }

  function openCharter(path: string) {
    setSelectedPath(path);
    setDetail(null);
    setLoading(true);
    setTab('contract');
    setFormOpen(false);
    setFormText('');
    setResolving(null);
    setSuggestions([]);
    fetch(`/kb/charters/show?path=${encodeURIComponent(path)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((d: CharterDetail) => setDetail(d))
      .catch(() => toast.error('Erro ao carregar o charter'))
      .finally(() => setLoading(false));
    loadSuggestions(path);
  }

  async function submitSuggestion() {
    if (!selectedPath || formText.trim().length < 3) {
      toast.error('Escreva ao menos 3 caracteres.');
      return;
    }
    setSubmitting(true);
    try {
      const r = await fetch('/kb/charters/suggestions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ path: selectedPath, kind: formKind, text: formText }),
      });
      if (!r.ok) throw r;
      toast.success('Sugestão enviada pra revisão.');
      setFormText('');
      setFormOpen(false);
      loadSuggestions(selectedPath);
    } catch {
      toast.error('Falha ao enviar sugestão.');
    } finally {
      setSubmitting(false);
    }
  }

  async function confirmResolve() {
    if (!resolving || resolveNote.trim().length < 3) {
      toast.error('Escreva um motivo (mín. 3 caracteres).');
      return;
    }
    try {
      const r = await fetch(`/kb/charters/suggestions/${resolving.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ status: resolving.status, resolution_note: resolveNote }),
      });
      if (!r.ok) throw r;
      toast.success(resolving.status === 'accepted' ? 'Sugestão aprovada.' : 'Sugestão rejeitada.');
      setResolving(null);
      setResolveNote('');
      if (selectedPath) loadSuggestions(selectedPath);
    } catch {
      toast.error('Falha ao resolver.');
    }
  }

  // Atalhos j/k/Enter/Esc//
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const tg = (e.target as HTMLElement)?.tagName;
      const typing = tg === 'INPUT' || tg === 'TEXTAREA' || (e.target as HTMLElement)?.isContentEditable;
      if (e.key === '/' && !typing) { e.preventDefault(); searchRef.current?.focus(); return; }
      if (e.key === 'Escape' && selectedPath && !typing) { setSelectedPath(null); setDetail(null); return; }
      if (typing || visible.length === 0) return;
      if (e.key === 'j' || e.key === 'k') {
        e.preventDefault();
        const idx = selectedPath ? visible.findIndex((c) => c.path === selectedPath) : -1;
        let next = idx;
        if (e.key === 'j') next = Math.min(visible.length - 1, idx + 1);
        if (e.key === 'k') next = Math.max(0, idx === -1 ? 0 : idx - 1);
        if (next !== idx) openCharter(visible[next].path);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, selectedPath]);

  return (
    <AppShellV2>
      <Head title="Charters — KB" />

      <PageHeader title="Charters" subtitle="Contratos vivos de telas e módulos · governados no KB (ADR 0243)" />

      <div className="px-4 py-4 space-y-4">
        <KpiGrid>
          <KpiCard label="Charters" value={String(kpis?.total ?? charters.length)} icon={<FileText size={16} />} />
          <KpiCard label="Módulos cobertos" value={String(kpis?.modulos_total ?? modules.length)} />
          <KpiCard label="Exibindo" value={String(visible.length)} />
        </KpiGrid>

        <div className="flex flex-wrap items-center gap-2">
          <div className="relative flex-1 min-w-[220px]">
            <Search size={15} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input ref={searchRef} value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar charter…  ( / )" className="pl-8" />
          </div>
          <Select value={moduleFilter} onValueChange={setModuleFilter}>
            <SelectTrigger className="w-[200px]"><SelectValue placeholder="Todos os módulos" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">Todos os módulos</SelectItem>
              {modules.map((m) => (<SelectItem key={m} value={m}>{m} ({kpis.modulos[m]})</SelectItem>))}
            </SelectContent>
          </Select>
        </div>

        <div className="grid gap-4 lg:grid-cols-[minmax(280px,2fr)_3fr]">
          {/* Lista master */}
          <ScrollArea className="h-[calc(100vh-320px)] rounded-lg border bg-card">
            <div className="divide-y">
              {visible.length === 0 && (<div className="p-8 text-center text-sm text-muted-foreground">Nenhum charter encontrado.</div>)}
              {visible.map((c) => {
                const active = c.path === selectedPath;
                return (
                  <button key={c.path} onClick={() => openCharter(c.path)}
                    className={`flex w-full flex-col items-start gap-1 px-3 py-2.5 text-left transition-colors ${active ? 'bg-primary/10' : 'hover:bg-muted/50'}`}>
                    <div className="flex w-full items-center gap-2">
                      <Badge variant="outline" className={c.level === 'module' ? 'border-primary/40 bg-primary/15 text-primary' : 'border-primary/25 bg-primary/5 text-primary'}>
                        {c.level === 'module' ? 'Módulo' : 'Tela'}
                      </Badge>
                      <span className="truncate text-sm font-medium">{c.module ?? '—'}</span>
                      {c.status && (<Badge variant="outline" className={`text-[10px] ${STATUS_TONE[c.status] ?? 'text-muted-foreground'}`}>{c.status}</Badge>)}
                      <span className="ml-auto shrink-0 text-[11px] text-muted-foreground">{fmtSize(c.size_chars)}</span>
                    </div>
                    <span className="truncate text-xs text-muted-foreground">{c.screen}</span>
                  </button>
                );
              })}
            </div>
          </ScrollArea>

          {/* Preview + Governança */}
          <Card className="h-[calc(100vh-320px)] overflow-hidden">
            {!selectedPath ? (
              <CardContent className="flex h-full flex-col items-center justify-center gap-2 text-center text-sm text-muted-foreground">
                <FileText size={28} className="text-muted-foreground/50" />
                Selecione um charter para ver o contrato.
                <span className="text-xs">Atalhos: <kbd>j</kbd>/<kbd>k</kbd> navega · <kbd>Enter</kbd> abre · <kbd>/</kbd> busca</span>
              </CardContent>
            ) : (
              <div className="flex h-full flex-col">
                <div className="flex items-center gap-2 border-b bg-primary/5 px-4 py-2 text-xs text-primary">
                  <Lock size={13} />
                  <span>Núcleo vem do git — para mudar, proponha uma sugestão. Edição livre desabilitada.</span>
                  <button onClick={() => { setSelectedPath(null); setDetail(null); }} className="ml-auto rounded p-0.5 hover:bg-primary/10" aria-label="Fechar preview"><X size={14} /></button>
                </div>

                {/* Abas */}
                <div className="flex items-center gap-1 border-b px-3">
                  <button onClick={() => setTab('contract')} className={`px-3 py-2 text-sm border-b-2 -mb-px ${tab === 'contract' ? 'border-primary text-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground'}`}>Contrato</button>
                  <button onClick={() => setTab('governance')} className={`px-3 py-2 text-sm border-b-2 -mb-px ${tab === 'governance' ? 'border-primary text-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground'}`}>
                    Governança{suggestions.length > 0 && <span className="ml-1 text-xs">({suggestions.length}{openCount > 0 ? `·${openCount} abertas` : ''})</span>}
                  </button>
                  {detail?.github_url && (
                    <a href={detail.github_url} target="_blank" rel="noopener noreferrer" className="ml-auto inline-flex items-center gap-1 text-xs text-primary hover:underline"><ExternalLink size={13} /> GitHub</a>
                  )}
                </div>

                {tab === 'contract' ? (
                  <ScrollArea className="flex-1">
                    {loading ? (
                      <div className="flex items-center justify-center gap-2 p-8 text-sm text-muted-foreground"><Loader2 size={16} className="animate-spin" /> Carregando contrato…</div>
                    ) : detail ? (
                      <div className="prose prose-sm max-w-none p-4 dark:prose-invert"><ReactMarkdown remarkPlugins={[remarkGfm]}>{detail.content_md}</ReactMarkdown></div>
                    ) : (<div className="p-8 text-center text-sm text-muted-foreground">Sem conteúdo.</div>)}
                  </ScrollArea>
                ) : (
                  <ScrollArea className="flex-1">
                    <div className="space-y-3 p-4">
                      {/* Propor */}
                      {!formOpen ? (
                        <Button size="sm" variant="outline" onClick={() => setFormOpen(true)}><MessageSquarePlus size={14} /> Propor sugestão</Button>
                      ) : (
                        <div className="space-y-2 rounded-lg border bg-muted/30 p-3">
                          <Select value={formKind} onValueChange={setFormKind}>
                            <SelectTrigger className="h-8 w-[180px]"><SelectValue /></SelectTrigger>
                            <SelectContent>
                              {Object.entries(KIND_LABEL).map(([k, l]) => (<SelectItem key={k} value={k}>{l}</SelectItem>))}
                            </SelectContent>
                          </Select>
                          <Textarea value={formText} onChange={(e) => setFormText(e.target.value)} rows={3} placeholder="Descreva a sugestão / dúvida / errata (não edita o núcleo — vira proposta pra aprovação)…" />
                          <div className="flex gap-2">
                            <Button size="sm" disabled={submitting} onClick={submitSuggestion}>{submitting ? <Loader2 size={14} className="animate-spin" /> : <Send size={14} />} Enviar</Button>
                            <Button size="sm" variant="ghost" onClick={() => { setFormOpen(false); setFormText(''); }}>Cancelar</Button>
                          </div>
                        </div>
                      )}

                      {/* Lista de sugestões */}
                      {suggestions.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">Nenhuma sugestão ainda. Seja o primeiro a propor.</p>
                      ) : (
                        suggestions.map((s) => (
                          <div key={s.id} className="rounded-lg border p-3 text-sm">
                            <div className="mb-1 flex items-center gap-2">
                              <Badge variant="outline" className="text-[10px]">{KIND_LABEL[s.kind] ?? s.kind}</Badge>
                              <Badge variant="outline" className={`text-[10px] ${SUG_TONE[s.status] ?? ''}`}>{s.status}</Badge>
                              <span className="ml-auto text-[11px] text-muted-foreground">#{s.id}</span>
                            </div>
                            <p className="whitespace-pre-wrap">{s.text}</p>
                            {s.resolution_note && (<p className="mt-2 rounded bg-muted/50 px-2 py-1 text-xs text-muted-foreground"><b>Resolução:</b> {s.resolution_note}</p>)}
                            {(s.status === 'proposed' || s.status === 'under_review') && (
                              resolving?.id === s.id ? (
                                <div className="mt-2 space-y-2">
                                  <Input autoFocus value={resolveNote} onChange={(e) => setResolveNote(e.target.value)} placeholder={resolving.status === 'accepted' ? 'Motivo da aprovação…' : 'Motivo da rejeição…'} className="h-8" />
                                  <div className="flex gap-2">
                                    <Button size="sm" onClick={confirmResolve}>Confirmar {resolving.status === 'accepted' ? 'aprovação' : 'rejeição'}</Button>
                                    <Button size="sm" variant="ghost" onClick={() => { setResolving(null); setResolveNote(''); }}>Cancelar</Button>
                                  </div>
                                </div>
                              ) : (
                                <div className="mt-2 flex gap-2">
                                  <Button size="sm" variant="outline" onClick={() => { setResolving({ id: s.id, status: 'accepted' }); setResolveNote(''); }}><Check size={13} /> Aprovar</Button>
                                  <Button size="sm" variant="ghost" onClick={() => { setResolving({ id: s.id, status: 'rejected' }); setResolveNote(''); }}><Ban size={13} /> Rejeitar</Button>
                                </div>
                              )
                            )}
                          </div>
                        ))
                      )}
                    </div>
                  </ScrollArea>
                )}
              </div>
            )}
          </Card>
        </div>

        <p className="text-center text-[11px] text-muted-foreground">
          {github_repo} · Charters read-only (ADR 0243) · sugestão aceita vira PR no .charter.md (Fase 3)
        </p>
      </div>
    </AppShellV2>
  );
}
