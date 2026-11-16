// @memcofre
//   tela: /nfse
//   module: NFSe
//   stories: US-NFSE-008
//   adrs: tech/0001-service-adapter-dto-pattern, tech/0002-erros-nfse-tratamento
//   permissao: nfse.view

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { useState, useEffect, useMemo, useRef } from 'react';
import { ColumnDef } from '@tanstack/react-table';
import { FileText, Plus, Eye, XCircle, Download } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';
import { Skeleton } from '@/Components/ui/skeleton';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { PaginatorShape } from '@/Components/shared/DataTable';
import StatusBadge from '@/Components/shared/StatusBadge';
import EmptyState from '@/Components/shared/EmptyState';
import PageFilters from '@/Components/shared/PageFilters';

const LS_KEY = 'oimpresso.nfse.filters';

interface NfseRow {
  id: number;
  numero: string | null;
  status: string;
  tomador_nome: string;
  valor_servicos: number;
  valor_iss: number | null;
  competencia: string | null;
  pdf_url: string | null;
  erro_mensagem: string | null;
  created_at: string;
}

interface Filters {
  status?: string;
  de?: string;
  ate?: string;
  q?: string;
}

interface Props {
  // Deferida (Inertia::defer): undefined no 1º render, chega no 2º pedido.
  notas?: PaginatorShape<NfseRow>;
  filters: Filters;
}

// Os 5 status que a coluna aceita — mesmo vocabulário do IndexNfseRequest::STATUS e do
// StatusBadge kind="nfse". Rótulos iguais aos do badge da tabela.
const STATUS_OPCOES: { value: string; label: string }[] = [
  { value: 'rascunho', label: 'Rascunho' },
  { value: 'processando', label: 'Processando' },
  { value: 'emitida', label: 'Emitida' },
  { value: 'cancelada', label: 'Cancelada' },
  { value: 'erro', label: 'Erro' },
];
const statusLabel = (v: string) => STATUS_OPCOES.find((o) => o.value === v)?.label ?? v;

function TabelaSkeleton() {
  return (
    <div className="space-y-2" aria-busy="true" aria-label="Carregando notas">
      {Array.from({ length: 6 }).map((_, i) => (
        <Skeleton key={i} className="h-12 w-full" />
      ))}
    </div>
  );
}

const brl = (v: number | null) =>
  v != null
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)
    : '—';

// Sentinela NÃO-vazio pro item "Todos". O Radix Select CRASHA a tela inteira
// (branco em prod) se um <SelectItem> tiver value="" — o item literal aqui era
// um crash latente vivo. O estado `status` segue '' pra "sem filtro"; só o
// Select mapeia '' <-> STATUS_ALL. Ref: memory/proibicoes.md §5 (2026-06-29).
const STATUS_ALL = '__all__';

function savedFilters(): Filters {
  try {
    return JSON.parse(localStorage.getItem(LS_KEY) ?? '{}');
  } catch {
    return {};
  }
}

export default function NfseIndex({ notas, filters }: Props) {
  const rows = useMemo(() => notas?.data ?? [], [notas?.data]);
  const merged = { ...savedFilters(), ...filters };

  const [status, setStatus] = useState(merged.status ?? '');
  const [de, setDe]         = useState(merged.de ?? '');
  const [ate, setAte]       = useState(merged.ate ?? '');
  const [q, setQ]           = useState(merged.q ?? '');
  const [focusedIdx, setFocusedIdx] = useState<number>(-1);
  const searchRef = useRef<HTMLInputElement>(null);

  // Persiste filtros no localStorage
  useEffect(() => {
    localStorage.setItem(LS_KEY, JSON.stringify({ status, de, ate, q }));
  }, [status, de, ate, q]);

  // Atalhos de teclado: N → emitir, J/K → navegar, / → foco na busca
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const tag = (e.target as HTMLElement).tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

      if (e.key === 'n' || e.key === 'N') {
        router.visit('/nfse/emitir');
      } else if (e.key === 'j' || e.key === 'J') {
        setFocusedIdx(i => Math.min(i + 1, rows.length - 1));
      } else if (e.key === 'k' || e.key === 'K') {
        setFocusedIdx(i => Math.max(i - 1, 0));
      } else if (e.key === '/') {
        e.preventDefault();
        searchRef.current?.focus();
      } else if (e.key === 'Enter' && focusedIdx >= 0) {
        const row = rows[focusedIdx];
        if (row) router.visit(`/nfse/${row.id}`);
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [focusedIdx, rows]);

  function applyFilters() {
    // D-14: partial reload — só re-busca o que muda com filtro.
    router.get('/nfse', { status: status || undefined, de: de || undefined, ate: ate || undefined, q: q || undefined }, {
      preserveScroll: true, preserveState: true, replace: true, only: ['notas', 'filters'],
    });
  }

  function resetFilters() {
    setStatus(''); setDe(''); setAte(''); setQ('');
    localStorage.removeItem(LS_KEY);
    // D-14: partial reload — só re-busca o que muda com filtro.
    router.get('/nfse', {}, { preserveScroll: true, preserveState: true, replace: true, only: ['notas', 'filters'] });
  }

  const activeChips = [
    status && { label: `Status: ${statusLabel(status)}`, onRemove: () => { setStatus(''); applyFilters(); } },
    de && { label: `De: ${de}`, onRemove: () => { setDe(''); applyFilters(); } },
    ate && { label: `Até: ${ate}`, onRemove: () => { setAte(''); applyFilters(); } },
    q && { label: `Busca: ${q}`, onRemove: () => { setQ(''); applyFilters(); } },
  ].filter(Boolean) as { label: string; onRemove: () => void }[];

  const columns: ColumnDef<NfseRow, any>[] = [
    {
      accessorKey: 'competencia',
      header: 'Competência',
      cell: ({ row }) => row.original.competencia ?? '—',
    },
    {
      accessorKey: 'numero',
      header: 'Nº Nota',
      cell: ({ row }) => row.original.numero ?? <span className="text-[color:var(--text-mute)] text-xs">pendente</span>,
    },
    {
      accessorKey: 'tomador_nome',
      header: 'Tomador',
      cell: ({ row }) => (
        <span className="max-w-[200px] block truncate" title={row.original.tomador_nome}>
          {row.original.tomador_nome}
        </span>
      ),
    },
    {
      accessorKey: 'valor_servicos',
      header: 'Valor',
      cell: ({ row }) => (
        <div className="text-right tabular-nums">
          <div>{brl(row.original.valor_servicos)}</div>
          {row.original.valor_iss != null && (
            <div className="text-xs text-[color:var(--text-mute)]">ISS: {brl(row.original.valor_iss)}</div>
          )}
        </div>
      ),
    },
    {
      accessorKey: 'status',
      header: 'Status',
      cell: ({ row }) => (
        <div>
          <StatusBadge kind="nfse" value={row.original.status} />
          {row.original.status === 'erro' && row.original.erro_mensagem && (
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <p className="text-xs text-destructive mt-0.5 max-w-[180px] truncate cursor-help">
                    {row.original.erro_mensagem}
                  </p>
                </TooltipTrigger>
                <TooltipContent className="max-w-xs">{row.original.erro_mensagem}</TooltipContent>
              </Tooltip>
            </TooltipProvider>
          )}
        </div>
      ),
    },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) => {
        const nota = row.original;
        return (
          <div className="flex items-center justify-end gap-1">
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost" size="icon"
                    onClick={() => router.visit(`/nfse/${nota.id}`)}
                    aria-label="Ver detalhe"
                  >
                    <Eye size={15} />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Ver detalhe</TooltipContent>
              </Tooltip>

              {nota.pdf_url && (
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost" size="icon"
                      onClick={() => window.open(`/nfse/${nota.id}/pdf`, '_blank')}
                      aria-label="Baixar DANFSE"
                    >
                      <Download size={15} />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>Baixar DANFSE</TooltipContent>
                </Tooltip>
              )}

              {nota.status === 'emitida' && (
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost" size="icon"
                      className="text-destructive hover:text-destructive"
                      onClick={() => router.visit(`/nfse/${nota.id}`, { data: { cancelar: true } })}
                      aria-label="Cancelar nota"
                    >
                      <XCircle size={15} />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>Cancelar nota</TooltipContent>
                </Tooltip>
              )}

              {nota.status === 'rascunho' && (
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost" size="icon"
                      className="text-primary hover:text-primary"
                      onClick={() => router.visit(`/nfse/${nota.id}`)}
                      aria-label="Emitir rascunho"
                    >
                      <FileText size={15} />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>Emitir rascunho</TooltipContent>
                </Tooltip>
              )}
            </TooltipProvider>
          </div>
        );
      },
    },
  ];

  return (
    <AppShellV2 title="NFSe">
      <Head title="Notas Fiscais de Serviço" />
      <div className="p-6 space-y-5 max-w-6xl mx-auto">
        <PageHeader
          leading={<FileText size={18} aria-hidden="true" />}
          title="Notas Fiscais de Serviço"
          subtitle={notas ? `${notas.total} nota(s) no filtro atual` : 'Carregando notas…'}
          actions={
            <Button onClick={() => router.visit('/nfse/emitir')}>
              <Plus size={16} className="mr-1" />
              <span>Emitir NFSe</span>
              <kbd className="ml-2 hidden sm:inline-flex h-5 items-center rounded border border-border bg-muted px-1 text-[10px] font-mono opacity-60">N</kbd>
            </Button>
          }
        />

        <PageFilters
          cols={4}
          activeChips={activeChips}
          onReset={resetFilters}
        >
          <div className="flex flex-col gap-1">
            <label className="text-xs text-[color:var(--text-mute)]">Status</label>
            <Select value={status || STATUS_ALL} onValueChange={(v) => setStatus(v === STATUS_ALL ? '' : v)}>
              <SelectTrigger className="h-9">
                <SelectValue placeholder="Todos" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={STATUS_ALL}>Todos</SelectItem>
                {STATUS_OPCOES.map((o) => (
                  <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-xs text-[color:var(--text-mute)]">Competência de</label>
            <Input type="month" value={de} onChange={(e) => setDe(e.target.value)} className="h-9" />
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-xs text-[color:var(--text-mute)]">Competência até</label>
            <Input type="month" value={ate} onChange={(e) => setAte(e.target.value)} className="h-9" />
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-xs text-[color:var(--text-mute)]">Tomador / Nº</label>
            <Input
              ref={searchRef}
              placeholder="Buscar... (/)"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
              className="h-9"
            />
          </div>

          <div className="flex items-end sm:col-span-4">
            <Button size="sm" onClick={applyFilters} className="ml-auto">
              Filtrar
            </Button>
          </div>
        </PageFilters>

        <Deferred data="notas" fallback={<TabelaSkeleton />}>
          {!notas || rows.length === 0 ? (
            <EmptyState
              icon="file-text"
              title="Nenhuma nota encontrada"
              description="Emita sua primeira NFSe ou ajuste os filtros acima."
              action={
                <Button onClick={() => router.visit('/nfse/emitir')}>
                  <Plus size={16} className="mr-1" />
                  Emitir NFSe
                </Button>
              }
            />
          ) : (
            <DataTable
              columns={columns}
              data={rows}
              pagination={notas}
              endpoint="/nfse"
              // Copy do PageHeader da própria tela (Index.tsx:274).
              caption="Notas Fiscais de Serviço"
              filters={{ status, de, ate, q }}
              showSearch={false}
              rowKey={(row) => row.id}
              emptyMessage="Nenhuma nota encontrada."
            />
          )}
        </Deferred>

        {rows.length > 0 && (
          <p className="text-xs text-[color:var(--text-mute)] text-right">
            Atalhos: <kbd className="font-mono">J</kbd>/<kbd className="font-mono">K</kbd> navegar · <kbd className="font-mono">Enter</kbd> abrir · <kbd className="font-mono">N</kbd> nova nota · <kbd className="font-mono">/</kbd> busca
          </p>
        )}
      </div>
    </AppShellV2>
  );
}
