// @memcofre
//   tela: /types-of-service
//   module: Configuracoes
//   stories: thread sistema/playbook/05 (tela 2 de 3)
//   permissao: access_types_of_service (o controller exige em todas as ações)
//
// Tipos de serviço da venda (balcão, entrega, montagem…), com taxa de embalagem e tabela de preço por local.
// Atrás da flag `useV2ConfiguracoesTiposServico` (TypesOfServiceController::FLAG_V2); desligada, a Blade segue.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-tipos-servico.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Servicos()
//
// A taxa entra no total da venda (regra mestre de valor). A tela só MOSTRA aqui; cadastrar/editar (F3-2) mandam texto
// pt-BR e o num_uf do store/update segue único parser.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Checkbox } from '@/Components/ui/checkbox';
import { Textarea } from '@/Components/ui/textarea';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';

export interface TipoServico {
  id: number; nome: string; descricao: string; taxa: number; tipo_taxa: 'fixed' | 'percent';
  campos_personalizados: boolean; precos_por_local: { local: string; tabela: string }[];
  tabela_por_local: Record<string, string>;
}
interface Props { tipos?: TipoServico[]; opcoes?: { locais: Record<string, string>; tabelas: Record<string, string> } }
interface Edicao {
  id?: number; nome: string; descricao: string; tipo_taxa: 'fixed' | 'percent'; taxa: string;
  campos_personalizados: boolean; tabela_por_local: Record<string, string>;
}
/** 8.5 → "8,50". É o TEXTO que o drawer manda: o num_uf do store/update lê vírgula como decimal (regra mestre de valor). */
const paraTexto = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4, useGrouping: false });

/** Taxa como a Blade mostrava: valor em pt-BR, com "%" no percentual. */
const taxaTexto = (t: TipoServico) => {
  const n = t.taxa.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return t.tipo_taxa === 'percent' ? `${n} %` : `R$ ${n}`;
};

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** store/update/destroy devolvem `{success, msg}` — igual ao modal da Blade. */
async function enviar(url: string, corpo: URLSearchParams): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    body: corpo,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível concluir (HTTP ${r.status}).` };
}

function TiposServicoIndex({ tipos: tiposProp, opcoes }: Props) {
  const tipos = useMemo(() => tiposProp ?? [], [tiposProp]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<TipoServico | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [edicao, setEdicao] = useState<Edicao | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [salvando, setSalvando] = useState(false);
  const busca = useRef<HTMLInputElement>(null);
  const locais = useMemo(() => Object.entries(opcoes?.locais ?? {}).filter(([k]) => k !== ''), [opcoes]);
  const tabelas = useMemo(() => Object.entries(opcoes?.tabelas ?? {}).filter(([k]) => k !== ''), [opcoes]);

  /** Cadastro novo: cada local na 1ª tabela da lista, como o select da Blade sem valor escolhido. */
  const novo = useCallback(() => {
    const primeira = tabelas[0]?.[0] ?? '0';
    setErro(null);
    setEdicao({ nome: '', descricao: '', tipo_taxa: 'fixed', taxa: '', campos_personalizados: false,
      tabela_por_local: Object.fromEntries(locais.map(([id]) => [id, primeira])) });
  }, [locais, tabelas]);
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
      if (e.key === 'n' && opcoes) { e.preventDefault(); novo(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [novo, opcoes]);

  const editar = (t: TipoServico) => {
    setErro(null);
    setEdicao({ id: t.id, nome: t.nome, descricao: t.descricao, tipo_taxa: t.tipo_taxa, taxa: paraTexto(t.taxa),
      campos_personalizados: t.campos_personalizados, tabela_por_local: { ...t.tabela_por_local } });
  };
  const salvar = async () => {
    if (!edicao || edicao.nome.trim() === '') return;
    setSalvando(true); setErro(null);
    // Taxa em texto pt-BR (o num_uf do controller é o único parser); tabela por local como a Blade manda.
    const corpo = new URLSearchParams({ name: edicao.nome.trim(), description: edicao.descricao.trim(),
      packing_charge_type: edicao.tipo_taxa, packing_charge: edicao.taxa.trim() });
    for (const [local, tabela] of Object.entries(edicao.tabela_por_local)) corpo.append(`location_price_group[${local}]`, tabela);
    if (edicao.campos_personalizados) corpo.append('enable_custom_fields', '1');
    if (edicao.id) corpo.append('_method', 'PUT');
    const r = await enviar(edicao.id ? `/types-of-service/${edicao.id}` : '/types-of-service', corpo);
    setSalvando(false);
    if (!r.success) { setErro(r.msg || 'Não foi possível salvar o tipo de serviço.'); return; }
    setEdicao(null);
    setAviso(r.msg ?? null);
    router.reload({ only: ['tipos'] });
  };

  const termo = q.trim().toLowerCase();
  const lista = useMemo(
    () => tipos.filter((t) => !termo || [t.nome, t.descricao].some((v) => v.toLowerCase().includes(termo))),
    [tipos, termo],
  );

  const confirmarExclusao = async () => {
    if (!excluir) return;
    const r = await enviar(`/types-of-service/${excluir.id}`, new URLSearchParams({ _method: 'DELETE' }));
    setExcluir(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['tipos'] });
  };

  const colunas: ColumnDef<TipoServico, unknown>[] = [
    {
      id: 'nome', header: 'Tipo de serviço',
      cell: ({ row: { original: t } }) => (
        <Stack gap={0}>
          <Inline gap={2}><span className="font-medium">{t.nome}</span>{t.campos_personalizados && <Badge variant="outline">campos extras</Badge>}</Inline>
          <span className="text-xs text-muted-foreground">{t.descricao || '—'}</span>
        </Stack>
      ),
    },
    { id: 'taxa', header: 'Taxa de embalagem', meta: { align: 'right', mono: true }, cell: ({ row: { original: t } }) => taxaTexto(t) },
    {
      id: 'precos', header: 'Tabela de preço por local',
      cell: ({ row: { original: t } }) => (
        <span className="text-muted-foreground">{t.precos_por_local.map((p) => `${p.local}: ${p.tabela}`).join(' · ') || 'Padrão'}</span>
      ),
    },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: t } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${t.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem disabled={!opcoes} onSelect={() => editar(t)}>Editar tipo de serviço</DropdownMenuItem>
            <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(t)}>Excluir tipo de serviço</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Tipos de serviço"
          subtitle={tiposProp ? <><strong>{tipos.length}</strong> {tipos.length === 1 ? 'tipo de serviço' : 'tipos de serviço'}</> : 'balcão, entrega, montagem…'}
          subnav={<ConfiguracoesSubNav />}
          actions={<Button disabled={!opcoes} onClick={novo}><Plus className="size-4" /> Novo tipo de serviço</Button>} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar tipo de serviço…" aria-label="Buscar tipo de serviço" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="tipos" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando tipos de serviço…</p>}>
          {tipos.length > 0 ? (
            <div data-contract="tipos-table">
              <DataTable columns={colunas} data={lista} caption="Tipos de serviço do negócio" rowKey={(t) => t.id}
                emptyMessage="Nenhum tipo de serviço com esse termo." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhum tipo de serviço" description="Cada forma de atender (balcão, entrega, montagem) vira um tipo aqui, com taxa e tabela de preço próprias." />
            </div>
          )}
        </Deferred>
      </Stack>

      <Sheet open={!!edicao} onOpenChange={(v) => !v && setEdicao(null)}>
        <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-md">
          <header className="os-drawer-head">
            <div className="os-drawer-head-l">
              <SheetTitle asChild><h2 className="m-0">{edicao?.id ? `Editar ${edicao.nome}` : 'Novo tipo de serviço'}</h2></SheetTitle>
              <SheetDescription asChild><p>Como a loja atende a venda, com taxa e tabela de preço próprias.</p></SheetDescription>
            </div>
          </header>
          {edicao && (
            <Stack data-contract="tipo-form" gap={3} className="p-5 text-sm">
              <Stack gap={1}>
                <label htmlFor="ts-nome">Nome *</label>
                <Input id="ts-nome" value={edicao.nome} placeholder="Ex.: Entrega na cidade" onChange={(e) => setEdicao({ ...edicao, nome: e.target.value })} />
              </Stack>
              <Stack gap={1}>
                <label htmlFor="ts-desc">Descrição</label>
                <Textarea id="ts-desc" rows={2} value={edicao.descricao} onChange={(e) => setEdicao({ ...edicao, descricao: e.target.value })} />
              </Stack>
              <Inline gap={3}>
                <Stack gap={1} className="flex-1">
                  <label htmlFor="ts-tipo-taxa">Taxa de embalagem</label>
                  <Select value={edicao.tipo_taxa} onValueChange={(v) => setEdicao({ ...edicao, tipo_taxa: v === 'percent' ? 'percent' : 'fixed' })}>
                    <SelectTrigger id="ts-tipo-taxa"><SelectValue /></SelectTrigger>
                    <SelectContent><SelectItem value="fixed">Valor fixo (R$)</SelectItem><SelectItem value="percent">Percentual (%)</SelectItem></SelectContent>
                  </Select>
                </Stack>
                <Stack gap={1} className="w-32">
                  <label htmlFor="ts-taxa">{edicao.tipo_taxa === 'percent' ? 'Percentual' : 'Valor'}</label>
                  <Input id="ts-taxa" inputMode="decimal" value={edicao.taxa} placeholder="0,00"
                    onChange={(e) => setEdicao({ ...edicao, taxa: e.target.value.replace(/[^0-9,]/g, '') })} />
                </Stack>
              </Inline>
              {locais.length > 0 && (
                <Stack gap={2}>
                  <span className="text-xs font-semibold uppercase text-muted-foreground">Tabela de preço por local</span>
                  {locais.map(([id, nome]) => (
                    <Inline key={id} gap={3}>
                      <label htmlFor={`ts-local-${id}`} className="flex-1">{nome}</label>
                      <Select value={edicao.tabela_por_local[id] ?? tabelas[0]?.[0] ?? '0'}
                        onValueChange={(v) => setEdicao({ ...edicao, tabela_por_local: { ...edicao.tabela_por_local, [id]: v } })}>
                        <SelectTrigger id={`ts-local-${id}`} className="w-48"><SelectValue /></SelectTrigger>
                        <SelectContent>{tabelas.map(([k, l]) => <SelectItem key={k} value={k}>{l}</SelectItem>)}</SelectContent>
                      </Select>
                    </Inline>
                  ))}
                </Stack>
              )}
              <Inline gap={2}>
                <Checkbox id="ts-campos" checked={edicao.campos_personalizados} onCheckedChange={(v) => setEdicao({ ...edicao, campos_personalizados: v === true })} />
                <label htmlFor="ts-campos">Pedir campos extras na venda</label>
              </Inline>
              {erro && <p role="alert" className="text-destructive">{erro}</p>}
              <Inline gap={2} justify="end" className="border-t pt-4">
                <Button variant="ghost" onClick={() => setEdicao(null)}>Cancelar</Button>
                <Button disabled={edicao.nome.trim() === '' || salvando} onClick={salvar}>{edicao.id ? 'Salvar' : 'Cadastrar'}</Button>
              </Inline>
            </Stack>
          )}
        </SheetContent>
      </Sheet>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">O tipo de serviço sai da lista do negócio.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

TiposServicoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default TiposServicoIndex;
