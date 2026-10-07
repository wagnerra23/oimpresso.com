// @memcofre
//   tela: /business-location
//   module: Configuracoes
//   stories: thread sistema/playbook/04 (tela 3 de 3)
//   permissao: business_settings.access (a mesma da Configuração da empresa — o legado não separa)
//
// Locais comerciais (filiais) do negócio. Atrás da flag `useV2ConfiguracoesLocais` (BusinessLocationController::FLAG_V2);
// desligada, a rota segue na Blade `business_location/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-locais.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Locais()
//
// Não existe excluir local (o destroy() é vazio): só ativar e desativar. "Configurações de recibo" do protótipo é outra
// tela (location_settings), fora desta thread.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';

export interface Local {
  id: number; nome: string; referencia: string; cidade: string; cnpj: string; ativo: boolean;
  tabela: string | null; esquema: string | null; layout_pdv: string | null; layout_venda: string | null;
}
interface Props { locais?: Local[] }

/** activateDeactivateLocation() é GET e devolve `{success, msg}` — igual à Blade. */
async function alternar(id: number): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(`/business-location/activate-deactivate/${id}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível concluir (HTTP ${r.status}).` };
}

function LocaisIndex({ locais: locaisProp }: Props) {
  const locais = useMemo(() => locaisProp ?? [], [locaisProp]);
  const [q, setQ] = useState('');
  const [aviso, setAviso] = useState<string | null>(null);
  const busca = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, []);

  const termo = q.trim().toLowerCase();
  const lista = useMemo(
    () => locais.filter((l) => !termo || [l.nome, l.referencia, l.cidade, l.cnpj].some((v) => v.toLowerCase().includes(termo))),
    [locais, termo],
  );
  const ativos = locais.filter((l) => l.ativo).length;

  const ativarOuDesativar = async (l: Local) => {
    const r = await alternar(l.id);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['locais'] });
  };

  const colunas: ColumnDef<Local, unknown>[] = [
    {
      id: 'nome', header: 'Local',
      cell: ({ row: { original: l } }) => (
        <Stack gap={0}>
          <Inline gap={2}><span className="font-medium">{l.nome}</span>{!l.ativo && <Badge variant="outline">inativo</Badge>}</Inline>
          <span className="text-xs text-muted-foreground">{l.referencia || '—'}{l.cidade ? ` · ${l.cidade}` : ''}</span>
        </Stack>
      ),
    },
    { id: 'cnpj', header: 'CNPJ', meta: { mono: true }, cell: ({ row: { original: l } }) => l.cnpj || '—' },
    { id: 'tabela', header: 'Tabela de preço', cell: ({ row: { original: l } }) => l.tabela ?? 'Padrão' },
    { id: 'esquema', header: 'Esquema de fatura', cell: ({ row: { original: l } }) => l.esquema ?? '—' },
    {
      id: 'layouts', header: 'Layouts (PDV · venda)',
      cell: ({ row: { original: l } }) => <span className="text-muted-foreground">{l.layout_pdv ?? '—'} · {l.layout_venda ?? l.layout_pdv ?? '—'}</span>,
    },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: l } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${l.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem className={l.ativo ? 'text-destructive' : undefined} onSelect={() => ativarOuDesativar(l)}>
              {l.ativo ? 'Desativar local' : 'Ativar local'}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Locais comerciais"
          subtitle={locaisProp ? <><strong>{ativos}</strong> {ativos === 1 ? 'local ativo' : 'locais ativos'} de {locais.length}</> : 'filiais do negócio'} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar local, referência, cidade ou CNPJ…" aria-label="Buscar local" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="locais" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando locais…</p>}>
          {locais.length > 0 ? (
            <div data-contract="locais-table">
              <DataTable columns={colunas} data={lista} caption="Locais comerciais do negócio" rowKey={(l) => l.id}
                emptyMessage="Nenhum local com esse termo. Limpe a busca para ver todos." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhum local que você possa ver" description="Sem acesso a todos os locais, a lista mostra só os liberados para você." />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

LocaisIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default LocaisIndex;
