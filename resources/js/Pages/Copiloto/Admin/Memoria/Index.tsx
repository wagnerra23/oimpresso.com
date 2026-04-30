// @memcofre
//   tela: /copiloto/admin/memoria
//   module: Copiloto
//   stories: MEM-KB-1 (ADR 0053) — KB browser dos docs servidos via MCP server
//   adrs: 0053, 0057
//   permissao: copiloto.mcp.memory.manage
//
// V3 — markdown enriquecido (syntax highlight + anchors + external links em nova
// aba) + UX (keyboard j/k/Enter/Esc/`/`, debounce search 350ms, scroll-to-top no
// doc novo, copy slug button, contador resultados, breadcrumb anchors).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Label } from '@/Components/ui/label';
import { ScrollArea } from '@/Components/ui/scroll-area';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { toast } from 'sonner';

interface DocRow {
  id: number;
  slug: string;
  type: 'adr' | 'session' | 'reference' | 'spec' | string;
  module: string | null;
  title: string;
  scope_required: string | null;
  admin_only: boolean;
  git_sha: string | null;
  git_path: string | null;
  pii_redactions_count: number;
  size_chars: number;
  indexed_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
}

interface Paginator<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  from: number;
  to: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  docs: Paginator<DocRow>;
  filters: { type?: string; module?: string; q?: string; with_pii?: boolean };
  kpis: {
    total: number;
    soft_deleted: number;
    com_pii: number;
    tipos: Record<string, number>;
    modulos: Record<string, number>;
    ultimo_sync: string | null;
  };
  github_repo: string;
}

interface DocDetail {
  slug: string;
  type: string;
  module: string | null;
  title: string;
  content_md: string;
  scope_required: string | null;
  admin_only: boolean;
  metadata: Record<string, unknown> | null;
  git_sha: string | null;
  git_path: string | null;
  pii_redactions_count: number;
  indexed_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
  history_count: number;
  github_url: string | null;
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
  });
}

function fmtRelative(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso).getTime();
  const diffSec = (Date.now() - d) / 1000;
  if (diffSec < 60) return 'agora';
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)}min atrás`;
  if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h atrás`;
  if (diffSec < 86400 * 7) return `${Math.floor(diffSec / 86400)}d atrás`;
  return fmtDate(iso);
}

function fmtSize(chars: number): string {
  if (chars < 1024) return `${chars}c`;
  return `${(chars / 1024).toFixed(1)}k`;
}

function typeBadge(type: string): { className: string; label: string } {
  const map: Record<string, { className: string; label: string }> = {
    adr:       { className: 'bg-blue-100 text-blue-800',     label: 'ADR' },
    session:   { className: 'bg-purple-100 text-purple-800', label: 'session' },
    reference: { className: 'bg-amber-100 text-amber-800',   label: 'ref' },
    spec:      { className: 'bg-emerald-100 text-emerald-800', label: 'spec' },
  };
  return map[type] ?? { className: 'bg-gray-100 text-gray-800', label: type };
}

const PANEL_STORAGE_KEY = 'oimpresso-kb-panel';

function MemoriaIndex(props: Props) {
  const { docs, filters, kpis } = props;
  const [search, setSearch] = useState(filters.q ?? '');
  const [selectedSlug, setSelectedSlug] = useState<string | null>(null);
  const [detail, setDetail] = useState<DocDetail | null>(null);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState<DocDetail | null>(null);
  const [confirmText, setConfirmText] = useState('');
  const [previewOpen, setPreviewOpen] = useState(false);
  const previewScrollRef = useRef<HTMLDivElement | null>(null);
  const searchInputRef = useRef<HTMLInputElement | null>(null);
  const listRef = useRef<HTMLDivElement | null>(null);

  // Restaura preview state
  useEffect(() => {
    try {
      const saved = localStorage.getItem(PANEL_STORAGE_KEY);
      if (saved === 'open') setPreviewOpen(true);
    } catch {}
  }, []);

  // Debounce search — aplica filtro 350ms após user parar de digitar
  useEffect(() => {
    if (search === (filters.q ?? '')) return;
    const t = setTimeout(() => { applyFilter({ q: search }); }, 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  // Scroll-to-top do preview quando muda doc
  useEffect(() => {
    if (detail && previewScrollRef.current) {
      previewScrollRef.current.scrollTop = 0;
    }
  }, [detail?.slug]);

  // Keyboard shortcuts: j/k navegação, Enter abre, Esc fecha, / foca busca
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const tag = (e.target as HTMLElement)?.tagName;
      const isTyping = tag === 'INPUT' || tag === 'TEXTAREA' || (e.target as HTMLElement)?.isContentEditable;

      // / foca busca de qualquer lugar (exceto digitando em input)
      if (e.key === '/' && !isTyping) {
        e.preventDefault();
        searchInputRef.current?.focus();
        return;
      }

      // Esc fecha preview se aberto
      if (e.key === 'Escape' && previewOpen && !isTyping) {
        e.preventDefault();
        closePreview();
        return;
      }

      if (isTyping) return;

      // j/k navegam lista
      if (e.key === 'j' || e.key === 'k') {
        e.preventDefault();
        if (docs.data.length === 0) return;
        const currentIdx = selectedSlug
          ? docs.data.findIndex((d) => d.slug === selectedSlug)
          : -1;
        let nextIdx = currentIdx;
        if (e.key === 'j') nextIdx = Math.min(docs.data.length - 1, currentIdx + 1);
        if (e.key === 'k') nextIdx = Math.max(0, currentIdx === -1 ? 0 : currentIdx - 1);
        if (nextIdx !== currentIdx) {
          openDoc(docs.data[nextIdx].slug);
        }
        return;
      }

      // Enter abre o selecionado se preview fechado
      if (e.key === 'Enter' && !previewOpen && selectedSlug) {
        e.preventDefault();
        openDoc(selectedSlug);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [docs.data, selectedSlug, previewOpen]);

  const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

  function applyFilter(patch: Partial<Props['filters']>) {
    const params = { ...filters, ...patch, page: 1 };
    Object.keys(params).forEach((k) => {
      const v = (params as Record<string, unknown>)[k];
      if (v === '' || v === false || v === null || v === undefined) {
        delete (params as Record<string, unknown>)[k];
      }
    });
    router.get('/copiloto/admin/memoria', params, {
      preserveScroll: true,
      preserveState: true,
      only: ['docs', 'filters'],
    });
  }

  function openDoc(slug: string) {
    setSelectedSlug(slug);
    setDetail(null);
    setLoadingDetail(true);
    setPreviewOpen(true);
    try { localStorage.setItem(PANEL_STORAGE_KEY, 'open'); } catch {}
    fetch(`/copiloto/admin/memoria/${slug}/show`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
      .then((r) => r.json())
      .then((d: DocDetail) => setDetail(d))
      .catch(() => toast.error('Erro ao carregar doc'))
      .finally(() => setLoadingDetail(false));
  }

  function closePreview() {
    setPreviewOpen(false);
    setSelectedSlug(null);
    setDetail(null);
    try { localStorage.setItem(PANEL_STORAGE_KEY, 'closed'); } catch {}
  }

  function copySlug(slug: string) {
    navigator.clipboard.writeText(slug);
    toast.success(`Slug copiado: ${slug}`);
  }

  async function doSoftDelete() {
    if (!confirmDelete) return;
    if (confirmText !== 'CONFIRMO') {
      toast.error('Digite CONFIRMO pra confirmar');
      return;
    }
    try {
      const res = await fetch(`/copiloto/admin/memoria/${confirmDelete.slug}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ confirm: confirmText }),
      });
      const data = await res.json();
      if (data.ok) {
        toast.success(data.message);
        setConfirmDelete(null);
        setConfirmText('');
        if (selectedSlug) openDoc(selectedSlug);
        router.reload({ only: ['docs', 'kpis'] });
      } else {
        toast.error(data.message ?? 'Erro');
      }
    } catch {
      toast.error('Erro de rede');
    }
  }

  async function doRestore(slug: string) {
    try {
      const res = await fetch(`/copiloto/admin/memoria/${slug}/restore`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const data = await res.json();
      if (data.ok) {
        toast.success(data.message);
        router.reload({ only: ['docs', 'kpis'] });
        if (detail && detail.slug === slug) openDoc(slug);
      }
    } catch {
      toast.error('Erro ao restaurar');
    }
  }

  const hasActiveFilters = !!(filters.q || filters.type || filters.module || filters.with_pii);

  // ─── Lista ────────────────────────────────────────────────────────────
  const ListPanel = (
    <Card className="flex flex-col h-full">
      <CardHeader className="py-3 border-b flex-row items-center justify-between space-y-0 gap-2">
        <CardTitle className="text-sm flex items-center gap-2">
          <span>{num(docs.total)} docs</span>
          {hasActiveFilters && <Badge variant="outline" className="text-[10px]">filtrado</Badge>}
          <span className="text-xs text-muted-foreground font-normal">
            {docs.from}-{docs.to} · pág {docs.current_page}/{docs.last_page}
          </span>
        </CardTitle>
        <div className="text-[10px] text-muted-foreground flex items-center gap-2 hidden md:flex">
          <kbd className="px-1.5 py-0.5 rounded bg-muted border">j/k</kbd>
          <kbd className="px-1.5 py-0.5 rounded bg-muted border">/</kbd>
          <kbd className="px-1.5 py-0.5 rounded bg-muted border">Esc</kbd>
        </div>
      </CardHeader>
      <CardContent className="p-0 flex-1 overflow-hidden" ref={listRef}>
        <ScrollArea className="h-full">
          <table className="w-full text-xs">
            <thead className="sticky top-0 bg-background z-10">
              <tr className="border-b">
                <th className="text-left py-2 px-2 font-medium w-16">Tipo</th>
                <th className="text-left py-2 px-2 font-medium">Título</th>
                {!previewOpen && (
                  <>
                    <th className="text-left py-2 px-2 font-medium w-32">Módulo</th>
                    <th className="text-left py-2 px-2 font-medium w-28">Indexado</th>
                  </>
                )}
                <th className="text-right py-2 px-2 font-medium w-12">PII</th>
                <th className="text-right py-2 px-2 font-medium w-14">Tam.</th>
              </tr>
            </thead>
            <tbody>
              {docs.data.map((d) => {
                const tb = typeBadge(d.type);
                const isSel = d.slug === selectedSlug;
                return (
                  <tr
                    key={d.id}
                    className={`border-b cursor-pointer hover:bg-muted/40 ${isSel ? 'bg-blue-50 dark:bg-blue-950/30 border-l-2 border-l-blue-500' : ''} ${d.deleted_at ? 'opacity-50' : ''}`}
                    onClick={() => openDoc(d.slug)}
                  >
                    <td className="py-1.5 px-2 align-top">
                      <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${tb.className}`}>
                        {tb.label}
                      </span>
                    </td>
                    <td className="py-1.5 px-2">
                      <div className="font-medium text-xs leading-tight">{d.title}</div>
                      <div className="text-[10px] text-muted-foreground font-mono truncate" title={d.slug}>
                        {previewOpen && d.module && <span className="mr-1">[{d.module}]</span>}
                        {d.slug}
                      </div>
                    </td>
                    {!previewOpen && (
                      <>
                        <td className="py-1.5 px-2 text-xs align-top">{d.module ?? '—'}</td>
                        <td className="py-1.5 px-2 text-[10px] text-muted-foreground align-top" title={d.indexed_at ?? ''}>
                          {fmtRelative(d.indexed_at)}
                        </td>
                      </>
                    )}
                    <td className="text-right py-1.5 px-2 align-top">
                      {d.pii_redactions_count > 0 ? (
                        <span className="text-[10px] text-orange-700 font-mono" title={`${d.pii_redactions_count} PII redacted`}>
                          {d.pii_redactions_count}
                        </span>
                      ) : (
                        <span className="text-[10px] text-muted-foreground">—</span>
                      )}
                    </td>
                    <td className="text-right py-1.5 px-2 font-mono text-[10px] text-muted-foreground align-top">{fmtSize(d.size_chars)}</td>
                  </tr>
                );
              })}
              {docs.data.length === 0 && (
                <tr>
                  <td colSpan={previewOpen ? 4 : 6} className="text-center py-8 text-muted-foreground">
                    {hasActiveFilters ? 'Nenhum doc bate com os filtros.' : 'Nenhum doc.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </ScrollArea>
      </CardContent>

      {docs.last_page > 1 && (
        <div className="border-t p-2 flex justify-center gap-1 flex-wrap">
          {docs.links.map((l, i) => (
            <Button
              key={i}
              variant={l.active ? 'default' : 'outline'}
              size="sm"
              className="h-7 px-2 text-xs"
              disabled={!l.url}
              onClick={() => l.url && router.get(l.url, {}, { preserveScroll: true, preserveState: true, only: ['docs'] })}
              dangerouslySetInnerHTML={{ __html: l.label }}
            />
          ))}
        </div>
      )}
    </Card>
  );

  // ─── Preview ──────────────────────────────────────────────────────────
  const PreviewPanel = (
    <Card className="flex flex-col h-full">
      <CardHeader className="py-3 border-b flex-row items-center justify-between space-y-0 gap-2">
        <CardTitle className="text-sm truncate flex-1 min-w-0">
          {detail ? detail.title : selectedSlug ? `Carregando ${selectedSlug}...` : 'Preview'}
        </CardTitle>
        <div className="flex items-center gap-1 shrink-0">
          {detail && (
            <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => copySlug(detail.slug)} title="Copiar slug">
              📋
            </Button>
          )}
          <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={closePreview} title="Fechar (Esc)">
            ✕
          </Button>
        </div>
      </CardHeader>

      {!selectedSlug && (
        <div className="flex-1 flex items-center justify-center text-muted-foreground p-12 text-sm text-center">
          ← Selecione um doc na lista pra ver o conteúdo aqui.<br />
          <span className="text-[10px] mt-2 block">
            (j/k navega · Enter abre · Esc fecha · / foca busca)
          </span>
        </div>
      )}

      {selectedSlug && loadingDetail && (
        <div className="flex-1 flex items-center justify-center text-muted-foreground p-12 text-sm">
          Carregando...
        </div>
      )}

      {selectedSlug && detail && !loadingDetail && (
        <>
          <div className="px-4 pt-3 pb-2 border-b">
            <div className="flex items-start gap-2 flex-wrap">
              <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${typeBadge(detail.type).className}`}>
                {typeBadge(detail.type).label}
              </span>
              {detail.module && <Badge variant="outline" className="text-xs">{detail.module}</Badge>}
              {detail.scope_required && (
                <Badge variant="outline" className="bg-purple-50 text-purple-700 border-purple-200 text-xs" title="Spatie permission requerida">
                  🔒 {detail.scope_required}
                </Badge>
              )}
              {detail.admin_only && <Badge variant="outline" className="bg-red-50 text-red-700 border-red-200 text-xs">admin only</Badge>}
              {detail.pii_redactions_count > 0 && (
                <Badge variant="outline" className="bg-orange-50 text-orange-800 border-orange-200 text-xs">
                  ⚠️ {detail.pii_redactions_count} PII redacted
                </Badge>
              )}
              {detail.deleted_at && <Badge variant="outline" className="bg-red-50 text-red-700 border-red-200 text-xs">deletado</Badge>}
            </div>

            <div className="text-xs text-muted-foreground font-mono mt-2">
              {detail.slug}
              {detail.git_sha && <> · git {detail.git_sha.slice(0, 7)}</>}
              {detail.indexed_at && <> · {fmtRelative(detail.indexed_at)}</>}
            </div>

            <div className="flex gap-2 flex-wrap mt-2">
              {detail.github_url && (
                <a href={detail.github_url} target="_blank" rel="noopener noreferrer">
                  <Button variant="outline" size="sm" className="h-7 text-xs">📂 GitHub</Button>
                </a>
              )}
              {detail.history_count > 0 && (
                <Button variant="outline" size="sm" className="h-7 text-xs" disabled title="Em breve (O11)">
                  📜 {detail.history_count} versões
                </Button>
              )}
              {!detail.deleted_at ? (
                <Button
                  variant="destructive" size="sm"
                  className="h-7 text-xs"
                  onClick={() => setConfirmDelete(detail)}
                >
                  🗑️ Soft-delete LGPD
                </Button>
              ) : (
                <Button
                  variant="default" size="sm"
                  className="h-7 text-xs"
                  onClick={() => doRestore(detail.slug)}
                >
                  ♻️ Restaurar
                </Button>
              )}
            </div>
          </div>

          <div className="flex-1 overflow-auto" ref={previewScrollRef}>
            <article className="p-6 prose prose-sm dark:prose-invert max-w-none
              prose-headings:scroll-mt-4 prose-headings:font-semibold
              prose-h1:text-2xl prose-h1:border-b prose-h1:pb-2 prose-h1:mb-4
              prose-h2:text-xl prose-h2:mt-8 prose-h2:mb-3
              prose-h3:text-base prose-h3:mt-6 prose-h3:mb-2
              prose-pre:bg-zinc-900 prose-pre:text-zinc-100 prose-pre:rounded-md prose-pre:p-4 prose-pre:text-xs
              prose-code:before:content-none prose-code:after:content-none
              prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-xs prose-code:font-mono prose-code:font-normal
              prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-a:no-underline hover:prose-a:underline
              prose-table:text-xs prose-th:text-xs prose-td:py-1 prose-td:px-2
              prose-blockquote:border-l-4 prose-blockquote:border-blue-500 prose-blockquote:pl-4 prose-blockquote:italic prose-blockquote:text-muted-foreground
              prose-hr:my-6 prose-hr:border-border
              prose-strong:text-foreground
              prose-li:my-0.5
              prose-img:rounded-md">
              <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                  a: ({ href, children, ...rest }) => {
                    const isExternal = href && /^(https?:|mailto:)/.test(href);
                    return isExternal
                      ? <a href={href} target="_blank" rel="noopener noreferrer" {...rest}>{children}</a>
                      : <a href={href} {...rest}>{children}</a>;
                  },
                }}
              >
                {detail.content_md || '*conteúdo vazio*'}
              </ReactMarkdown>
            </article>
          </div>
        </>
      )}
    </Card>
  );

  return (
    <>
      <PageHeader
        icon="book-open"
        title="KB MCP — Memória"
        description={`${num(kpis.total)} docs servidos via mcp.oimpresso.com — ADRs, sessions, references e specs sincronizados de memory/* via webhook GitHub.`}
      />

      <KpiGrid cols={4} className="mt-6">
        <KpiCard
          icon="files"
          tone="info"
          label="Docs ativos"
          value={num(kpis.total - kpis.soft_deleted)}
          description={`${num(kpis.total)} total · ${num(kpis.soft_deleted)} soft-deleted`}
        />
        <KpiCard
          icon="shield-check"
          tone={kpis.com_pii > 0 ? 'warning' : 'success'}
          label="Docs com PII"
          value={num(kpis.com_pii)}
          description="CPF/CNPJ/email mascarados"
        />
        <KpiCard
          icon="layers"
          tone="default"
          label="Tipos"
          value={Object.keys(kpis.tipos).length.toString()}
          description={Object.entries(kpis.tipos).map(([k, v]) => `${k}: ${v}`).join(' · ')}
        />
        <KpiCard
          icon="clock"
          tone="default"
          label="Último sync"
          value={kpis.ultimo_sync ? fmtRelative(kpis.ultimo_sync) : '—'}
          description="webhook GitHub"
        />
      </KpiGrid>

      <Card className="mt-4">
        <CardContent className="py-3 flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[200px] relative">
            <Label className="text-xs">Busca <span className="text-[10px] text-muted-foreground">(/, debounce 350ms)</span></Label>
            <Input
              ref={searchInputRef}
              placeholder="título ou conteúdo..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-8"
            />
            {search && (
              <button
                onClick={() => { setSearch(''); applyFilter({ q: '' }); }}
                className="absolute right-2 top-7 text-xs text-muted-foreground hover:text-foreground"
                title="Limpar busca"
              >
                ✕
              </button>
            )}
          </div>
          <div className="w-32">
            <Label className="text-xs">Tipo</Label>
            <Select
              value={filters.type ?? '__all__'}
              onValueChange={(v) => applyFilter({ type: v === '__all__' ? '' : v })}
            >
              <SelectTrigger className="h-8"><SelectValue placeholder="Todos" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {Object.keys(kpis.tipos).map((t) => (
                  <SelectItem key={t} value={t}>{t} ({kpis.tipos[t]})</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="w-40">
            <Label className="text-xs">Módulo</Label>
            <Select
              value={filters.module ?? '__all__'}
              onValueChange={(v) => applyFilter({ module: v === '__all__' ? '' : v })}
            >
              <SelectTrigger className="h-8"><SelectValue placeholder="Todos" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {Object.keys(kpis.modulos).map((m) => (
                  <SelectItem key={m} value={m}>{m} ({kpis.modulos[m]})</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button
            variant={filters.with_pii ? 'default' : 'outline'}
            onClick={() => applyFilter({ with_pii: !filters.with_pii })}
            className="text-xs h-8"
          >
            {filters.with_pii ? '✓ ' : ''}só com PII
          </Button>
          {hasActiveFilters && (
            <Button variant="outline" className="h-8 text-xs" onClick={() => { setSearch(''); applyFilter({ q: '', type: '', module: '', with_pii: false }); }}>
              Limpar todos
            </Button>
          )}
          {!previewOpen && selectedSlug && (
            <Button variant="default" size="sm" className="h-8 text-xs" onClick={() => openDoc(selectedSlug)}>
              📖 Abrir preview
            </Button>
          )}
        </CardContent>
      </Card>

      <div className="mt-4" style={{ height: '78vh' }}>
        {!previewOpen ? (
          <div className="h-full">{ListPanel}</div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-2 h-full">
            <div className="lg:col-span-5 h-full">{ListPanel}</div>
            <div className="lg:col-span-7 h-full">{PreviewPanel}</div>
          </div>
        )}
      </div>

      <AlertDialog open={confirmDelete !== null} onOpenChange={(o) => { if (!o) { setConfirmDelete(null); setConfirmText(''); } }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Soft-delete LGPD</AlertDialogTitle>
            <AlertDialogDescription>
              Vai marcar <code className="font-mono">{confirmDelete?.slug}</code> como deletado.
              <br /><br />
              Mantém auditoria em <code>mcp_audit_log</code> e history. Pode ser restaurado em até 30 dias.
              <br /><br />
              Próximo sync do GitHub vai re-criar se o arquivo ainda estiver no repo. Pra remover permanentemente, apague do git também.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="my-4">
            <Label>Digite <strong>CONFIRMO</strong> pra prosseguir</Label>
            <Input
              value={confirmText}
              onChange={(e) => setConfirmText(e.target.value)}
              placeholder="CONFIRMO"
              className="mt-2"
            />
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => setConfirmText('')}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={doSoftDelete}
              className="bg-red-600 hover:bg-red-700"
              disabled={confirmText !== 'CONFIRMO'}
            >
              Soft-delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

MemoriaIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="KB MCP — Memória" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'KB MCP' }]}>
    {page}
  </AppShellV2>
);

export default MemoriaIndex;
