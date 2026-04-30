// @memcofre
//   tela: /copiloto/admin/memoria
//   module: Copiloto
//   stories: MEM-KB-1 (ADR 0053) — KB browser dos docs servidos via MCP server
//   adrs: 0053, 0057
//   permissao: copiloto.mcp.memory.manage
//
// Lista paginada de mcp_memory_documents (ADRs, sessions, references, specs)
// sincronizados de memory/* via webhook GitHub. Click linha → Sheet preview
// markdown + git_sha → GitHub. Soft-delete double-confirm pra LGPD.

import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
  Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription,
} from '@/Components/ui/sheet';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Label } from '@/Components/ui/label';
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

function MemoriaIndex(props: Props) {
  const { docs, filters, kpis, github_repo } = props;
  const [search, setSearch] = useState(filters.q ?? '');
  const [openSlug, setOpenSlug] = useState<string | null>(null);
  const [detail, setDetail] = useState<DocDetail | null>(null);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState<DocDetail | null>(null);
  const [confirmText, setConfirmText] = useState('');

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
    setOpenSlug(slug);
    setDetail(null);
    setLoadingDetail(true);
    fetch(`/copiloto/admin/memoria/${slug}/show`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
      .then((r) => r.json())
      .then((d: DocDetail) => setDetail(d))
      .catch(() => toast.error('Erro ao carregar doc'))
      .finally(() => setLoadingDetail(false));
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
        setOpenSlug(null);
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
        if (detail && detail.slug === slug) openDoc(slug); // refresh sheet
      }
    } catch {
      toast.error('Erro ao restaurar');
    }
  }

  return (
    <>
      <Head title="KB MCP — Memória" />

      <PageHeader
        icon="book-open"
        title="KB MCP — Memória"
        description={`Documentos servidos via mcp.oimpresso.com — ADRs, sessions, references e specs sincronizados de memory/* via webhook GitHub.`}
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
          label="Docs com PII redacted"
          value={num(kpis.com_pii)}
          description="CPF/CNPJ/email mascarados no sync"
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
          value={kpis.ultimo_sync ? fmtDate(kpis.ultimo_sync) : '—'}
          description="webhook GitHub → IndexarMemoryGitParaDb"
        />
      </KpiGrid>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Filtros</CardTitle>
        </CardHeader>
        <CardContent className="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div>
            <Label className="text-xs">Busca</Label>
            <Input
              placeholder="título ou conteúdo..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') applyFilter({ q: search }); }}
            />
          </div>
          <div>
            <Label className="text-xs">Tipo</Label>
            <Select
              value={filters.type ?? '__all__'}
              onValueChange={(v) => applyFilter({ type: v === '__all__' ? '' : v })}
            >
              <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {Object.keys(kpis.tipos).map((t) => (
                  <SelectItem key={t} value={t}>{t} ({kpis.tipos[t]})</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label className="text-xs">Módulo</Label>
            <Select
              value={filters.module ?? '__all__'}
              onValueChange={(v) => applyFilter({ module: v === '__all__' ? '' : v })}
            >
              <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {Object.keys(kpis.modulos).map((m) => (
                  <SelectItem key={m} value={m}>{m} ({kpis.modulos[m]})</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex items-end gap-2">
            <Button
              variant={filters.with_pii ? 'default' : 'outline'}
              onClick={() => applyFilter({ with_pii: !filters.with_pii })}
              className="text-xs"
            >
              {filters.with_pii ? '✓ ' : ''}só com PII
            </Button>
            <Button variant="outline" onClick={() => { setSearch(''); applyFilter({ q: '', type: '', module: '', with_pii: false }); }}>
              Limpar
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Docs ({num(docs.total)})</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="text-left py-2 px-2 font-medium w-20">Tipo</th>
                  <th className="text-left py-2 px-2 font-medium">Título</th>
                  <th className="text-left py-2 px-2 font-medium w-32">Módulo</th>
                  <th className="text-right py-2 px-2 font-medium w-20">Tamanho</th>
                  <th className="text-center py-2 px-2 font-medium w-16">PII</th>
                  <th className="text-left py-2 px-2 font-medium w-32">Indexado</th>
                  <th className="text-center py-2 px-2 font-medium w-20">Status</th>
                </tr>
              </thead>
              <tbody>
                {docs.data.map((d) => {
                  const tb = typeBadge(d.type);
                  return (
                    <tr
                      key={d.id}
                      className={`border-b hover:bg-muted/40 cursor-pointer ${d.deleted_at ? 'opacity-50' : ''}`}
                      onClick={() => openDoc(d.slug)}
                    >
                      <td className="py-2 px-2">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${tb.className}`}>
                          {tb.label}
                        </span>
                      </td>
                      <td className="py-2 px-2">
                        <div className="font-medium">{d.title}</div>
                        <div className="text-xs text-muted-foreground font-mono">{d.slug}</div>
                      </td>
                      <td className="py-2 px-2 text-xs">{d.module ?? '—'}</td>
                      <td className="text-right py-2 px-2 font-mono text-xs">{fmtSize(d.size_chars)}</td>
                      <td className="text-center py-2 px-2">
                        {d.pii_redactions_count > 0 ? (
                          <Badge variant="outline" className="bg-orange-50 text-orange-800 border-orange-200">
                            {d.pii_redactions_count}
                          </Badge>
                        ) : (
                          <span className="text-xs text-muted-foreground">—</span>
                        )}
                      </td>
                      <td className="py-2 px-2 text-xs text-muted-foreground">{fmtDate(d.indexed_at)}</td>
                      <td className="text-center py-2 px-2">
                        {d.deleted_at ? (
                          <Badge variant="outline" className="bg-red-50 text-red-700 border-red-200">deletado</Badge>
                        ) : (
                          <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">ativo</Badge>
                        )}
                      </td>
                    </tr>
                  );
                })}
                {docs.data.length === 0 && (
                  <tr><td colSpan={7} className="text-center py-8 text-muted-foreground">Nenhum doc encontrado.</td></tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Paginação simples */}
          {docs.last_page > 1 && (
            <div className="flex justify-center gap-1 mt-4">
              {docs.links.map((l, i) => (
                <Button
                  key={i}
                  variant={l.active ? 'default' : 'outline'}
                  size="sm"
                  disabled={!l.url}
                  onClick={() => l.url && router.get(l.url, {}, { preserveScroll: true, preserveState: true, only: ['docs'] })}
                  dangerouslySetInnerHTML={{ __html: l.label }}
                />
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Sheet preview */}
      <Sheet open={openSlug !== null} onOpenChange={(o) => { if (!o) { setOpenSlug(null); setDetail(null); } }}>
        <SheetContent className="w-full sm:max-w-2xl overflow-y-auto">
          {loadingDetail && <div className="p-8 text-center text-muted-foreground">Carregando...</div>}
          {detail && !loadingDetail && (
            <>
              <SheetHeader>
                <div className="flex items-start gap-2 flex-wrap">
                  <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${typeBadge(detail.type).className}`}>
                    {typeBadge(detail.type).label}
                  </span>
                  {detail.module && <Badge variant="outline">{detail.module}</Badge>}
                  {detail.scope_required && (
                    <Badge variant="outline" className="bg-purple-50 text-purple-700 border-purple-200" title="Spatie permission requerida">
                      🔒 {detail.scope_required}
                    </Badge>
                  )}
                  {detail.admin_only && <Badge variant="outline" className="bg-red-50 text-red-700 border-red-200">admin only</Badge>}
                  {detail.pii_redactions_count > 0 && (
                    <Badge variant="outline" className="bg-orange-50 text-orange-800 border-orange-200">
                      ⚠️ {detail.pii_redactions_count} PII redacted
                    </Badge>
                  )}
                  {detail.deleted_at && <Badge variant="outline" className="bg-red-50 text-red-700 border-red-200">deletado</Badge>}
                </div>
                <SheetTitle className="text-xl mt-2">{detail.title}</SheetTitle>
                <SheetDescription>
                  <span className="font-mono text-xs">{detail.slug}</span>
                  {detail.git_sha && (
                    <> · <span className="font-mono text-xs">git {detail.git_sha.slice(0, 7)}</span></>
                  )}
                  {detail.indexed_at && (
                    <> · indexado {fmtDate(detail.indexed_at)}</>
                  )}
                </SheetDescription>
              </SheetHeader>

              <div className="mt-4 flex gap-2 flex-wrap">
                {detail.github_url && (
                  <a href={detail.github_url} target="_blank" rel="noopener noreferrer">
                    <Button variant="outline" size="sm">📂 Ver no GitHub</Button>
                  </a>
                )}
                {detail.history_count > 0 && (
                  <Button variant="outline" size="sm" disabled title="Em breve">
                    📜 {detail.history_count} versões
                  </Button>
                )}
                {!detail.deleted_at ? (
                  <Button
                    variant="destructive" size="sm"
                    onClick={() => setConfirmDelete(detail)}
                  >
                    🗑️ Soft-delete LGPD
                  </Button>
                ) : (
                  <Button
                    variant="default" size="sm"
                    onClick={() => doRestore(detail.slug)}
                  >
                    ♻️ Restaurar
                  </Button>
                )}
              </div>

              <div className="mt-6 prose prose-sm dark:prose-invert max-w-none">
                <ReactMarkdown remarkPlugins={[remarkGfm]}>
                  {detail.content_md || '*conteúdo vazio*'}
                </ReactMarkdown>
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>

      {/* Confirm soft-delete */}
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

MemoriaIndex.layout = (page: ReactNode) => <AppShell>{page}</AppShell>;

export default MemoriaIndex;
