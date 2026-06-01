// Charters — interface do Charter Governance (ADR 0243).
//   tela: /kb/charters
//   module: KB
//   Reusa o tri-pane do kb/Index.tsx (AppShellV2 + PageHeader + KpiGrid + lista
//   master + preview markdown + atalhos j/k/Enter/Esc//). Read-only: o núcleo do
//   charter vem do git (ADR 0061). Governança (sugestão→aprovação) = F1 (US-CHTR-001..003).
//   Spec: memory/requisitos/KB/INTERFACE-CHARTER-KB.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import {
  FileText, Search, Lock, ExternalLink, MessageSquarePlus, Loader2, X,
} from 'lucide-react';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
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
  slug: string;
  title: string;
  module: string | null;
  screen: string | null;
  level: 'page' | 'module';
  git_path: string;
  git_sha: string | null;
  size_chars: number;
  updated_at: string | null;
}

interface Props {
  charters: CharterRow[];
  filters: { module?: string; q?: string };
  kpis: { total: number; modulos: Record<string, number>; modulos_total: number };
  github_repo: string;
}

interface CharterDetail {
  slug: string;
  title: string;
  content_md: string;
  git_path: string | null;
  github_url: string | null;
  updated_at: string | null;
}

function fmtSize(chars: number): string {
  if (!chars) return '—';
  if (chars < 1024) return `${chars}c`;
  return `${(chars / 1024).toFixed(1)}k`;
}

export default function ChartersIndex({ charters, kpis, github_repo }: Props) {
  const [moduleFilter, setModuleFilter] = useState<string>('__all__');
  const [search, setSearch] = useState('');
  const [selectedSlug, setSelectedSlug] = useState<string | null>(null);
  const [detail, setDetail] = useState<CharterDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const searchRef = useRef<HTMLInputElement | null>(null);

  const modules = useMemo(
    () => Object.keys(kpis?.modulos ?? {}).sort((a, b) => a.localeCompare(b)),
    [kpis],
  );

  const visible = useMemo(() => {
    const term = search.trim().toLowerCase();
    return charters.filter((c) => {
      if (moduleFilter !== '__all__' && c.module !== moduleFilter) return false;
      if (term && !`${c.title} ${c.git_path}`.toLowerCase().includes(term)) return false;
      return true;
    });
  }, [charters, moduleFilter, search]);

  function openCharter(slug: string) {
    setSelectedSlug(slug);
    setDetail(null);
    setLoading(true);
    fetch(`/kb/${slug}/show`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
      .then((r) => r.json())
      .then((d: CharterDetail) => setDetail(d))
      .catch(() => toast.error('Erro ao carregar o charter'))
      .finally(() => setLoading(false));
  }

  // Atalhos j/k/Enter/Esc// (mesma convenção do kb/Index)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const tag = (e.target as HTMLElement)?.tagName;
      const typing = tag === 'INPUT' || tag === 'TEXTAREA' || (e.target as HTMLElement)?.isContentEditable;
      if (e.key === '/' && !typing) { e.preventDefault(); searchRef.current?.focus(); return; }
      if (e.key === 'Escape' && selectedSlug) { setSelectedSlug(null); setDetail(null); return; }
      if (typing || visible.length === 0) return;
      if (e.key === 'j' || e.key === 'k') {
        e.preventDefault();
        const idx = selectedSlug ? visible.findIndex((c) => c.slug === selectedSlug) : -1;
        let next = idx;
        if (e.key === 'j') next = Math.min(visible.length - 1, idx + 1);
        if (e.key === 'k') next = Math.max(0, idx === -1 ? 0 : idx - 1);
        if (next !== idx) openCharter(visible[next].slug);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, selectedSlug]);

  return (
    <AppShellV2>
      <Head title="Charters — KB" />

      <PageHeader
        title="Charters"
        subtitle="Contratos vivos de telas e módulos · governados no KB (ADR 0243)"
      />

      <div className="px-4 py-4 space-y-4">
        <KpiGrid>
          <KpiCard label="Charters" value={String(kpis?.total ?? charters.length)} icon={<FileText size={16} />} />
          <KpiCard label="Módulos cobertos" value={String(kpis?.modulos_total ?? modules.length)} />
          <KpiCard label="Exibindo" value={String(visible.length)} />
        </KpiGrid>

        {/* Toolbar */}
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative flex-1 min-w-[220px]">
            <Search size={15} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input
              ref={searchRef}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Buscar charter…  ( / )"
              className="pl-8"
            />
          </div>
          <Select value={moduleFilter} onValueChange={setModuleFilter}>
            <SelectTrigger className="w-[200px]">
              <SelectValue placeholder="Todos os módulos" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">Todos os módulos</SelectItem>
              {modules.map((m) => (
                <SelectItem key={m} value={m}>{m} ({kpis.modulos[m]})</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Tri-pane: lista + preview */}
        <div className="grid gap-4 lg:grid-cols-[minmax(280px,2fr)_3fr]">
          {/* Lista master */}
          <ScrollArea className="h-[calc(100vh-320px)] rounded-lg border bg-card">
            <div className="divide-y">
              {visible.length === 0 && (
                <div className="p-8 text-center text-sm text-muted-foreground">
                  Nenhum charter encontrado.
                </div>
              )}
              {visible.map((c) => {
                const active = c.slug === selectedSlug;
                return (
                  <button
                    key={c.slug}
                    onClick={() => openCharter(c.slug)}
                    className={`flex w-full flex-col items-start gap-1 px-3 py-2.5 text-left transition-colors ${
                      active ? 'bg-primary/10' : 'hover:bg-muted/50'
                    }`}
                  >
                    <div className="flex w-full items-center gap-2">
                      <Badge
                        variant="outline"
                        className={c.level === 'module'
                          ? 'border-primary/40 bg-primary/15 text-primary'
                          : 'border-primary/25 bg-primary/5 text-primary'}
                      >
                        {c.level === 'module' ? 'Módulo' : 'Tela'}
                      </Badge>
                      <span className="truncate text-sm font-medium">{c.module ?? '—'}</span>
                      <span className="ml-auto shrink-0 text-[11px] text-muted-foreground">{fmtSize(c.size_chars)}</span>
                    </div>
                    <span className="truncate text-xs text-muted-foreground">{c.screen}</span>
                  </button>
                );
              })}
            </div>
          </ScrollArea>

          {/* Preview */}
          <Card className="h-[calc(100vh-320px)] overflow-hidden">
            {!selectedSlug ? (
              <CardContent className="flex h-full flex-col items-center justify-center gap-2 text-center text-sm text-muted-foreground">
                <FileText size={28} className="text-muted-foreground/50" />
                Selecione um charter para ver o contrato.
                <span className="text-xs">Atalhos: <kbd>j</kbd>/<kbd>k</kbd> navega · <kbd>Enter</kbd> abre · <kbd>/</kbd> busca</span>
              </CardContent>
            ) : (
              <div className="flex h-full flex-col">
                {/* Banner núcleo imutável */}
                <div className="flex items-center gap-2 border-b bg-primary/5 px-4 py-2 text-xs text-primary">
                  <Lock size={13} />
                  <span>Núcleo vem do git — para mudar, proponha uma sugestão (vira PR). Edição livre desabilitada.</span>
                  <button
                    onClick={() => { setSelectedSlug(null); setDetail(null); }}
                    className="ml-auto rounded p-0.5 hover:bg-primary/10"
                    aria-label="Fechar preview"
                  >
                    <X size={14} />
                  </button>
                </div>

                {/* Ações (governança = F1) */}
                <div className="flex items-center gap-2 border-b px-4 py-2">
                  <Button size="sm" variant="outline" disabled title="Disponível na Fase 1 (US-CHTR-002)">
                    <MessageSquarePlus size={14} /> Propor sugestão
                    <Badge variant="secondary" className="ml-1 text-[10px]">em breve</Badge>
                  </Button>
                  {detail?.github_url && (
                    <a
                      href={detail.github_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="ml-auto inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                      <ExternalLink size={13} /> Ver no GitHub
                    </a>
                  )}
                </div>

                {/* Conteúdo */}
                <ScrollArea className="flex-1">
                  {loading ? (
                    <div className="flex items-center justify-center gap-2 p-8 text-sm text-muted-foreground">
                      <Loader2 size={16} className="animate-spin" /> Carregando contrato…
                    </div>
                  ) : detail ? (
                    <div className="prose prose-sm max-w-none p-4 dark:prose-invert">
                      <ReactMarkdown remarkPlugins={[remarkGfm]}>{detail.content_md}</ReactMarkdown>
                    </div>
                  ) : (
                    <div className="p-8 text-center text-sm text-muted-foreground">Sem conteúdo.</div>
                  )}
                </ScrollArea>
              </div>
            )}
          </Card>
        </div>

        <p className="text-center text-[11px] text-muted-foreground">
          {github_repo} · Charters são contratos read-only (ADR 0243) · governança de evolução chega na Fase 1
        </p>
      </div>
    </AppShellV2>
  );
}
