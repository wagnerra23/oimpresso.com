// @memcofre
//   tela: /tax-rates
//   module: Configuracoes
//   stories: thread sistema/playbook/05 (tela 1 de 3)
//   permissao: tax_rate.view ou tax_rate.create (ver) · tax_rate.create/.update/.delete (ações)
//
// Alíquotas de imposto e grupos de imposto do negócio. Atrás da flag `useV2ConfiguracoesImpostos`
// (TaxRateController::FLAG_V2); desligada, a rota segue na Blade `tax_rate/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-impostos.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Impostos()
//
// A alíquota entra no imposto da venda (regra mestre de valor). A tela só MOSTRA aqui; cadastrar e editar (F3-2)
// mandam texto pt-BR e o num_uf do store/update segue único parser. Grupos são do GroupTaxController (fora do
// prefixo da thread): a tela lista, não edita.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Checkbox } from '@/Components/ui/checkbox';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
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

export interface Aliquota { id: number; nome: string; aliquota: number; so_grupo: boolean; em_grupo: boolean }
export interface Grupo { id: number; nome: string; aliquota: number; sub_impostos: string[] }
interface Props {
  impostos?: { aliquotas: Aliquota[]; grupos: Grupo[] };
  pode: { criar: boolean; editar: boolean; excluir: boolean };
  nfe_ativo: boolean;
}

/** 18 → "18,00 %". Mesmo formato que o @num_format da Blade mostrava. */
const pct = (n: number) => `${paraTexto(n)} %`;
/** 1.65 → "1,65". É o TEXTO que o drawer manda: o num_uf do store/update lê vírgula como decimal (regra mestre de valor). */
const paraTexto = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4, useGrouping: false });

interface Edicao { id?: number; nome: string; aliquota: string; so_grupo: boolean }

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** store/update/destroy respondem a ajax() com `{success, msg}` — igual aos modais da Blade. */
async function enviar(url: string, campos: Record<string, string>): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    body: new URLSearchParams(campos),
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível concluir (HTTP ${r.status}).` };
}

function ImpostosIndex({ impostos, pode, nfe_ativo }: Props) {
  const aliquotas = useMemo(() => impostos?.aliquotas ?? [], [impostos]);
  const grupos = useMemo(() => impostos?.grupos ?? [], [impostos]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<Aliquota | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [edicao, setEdicao] = useState<Edicao | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [salvando, setSalvando] = useState(false);
  const busca = useRef<HTMLInputElement>(null);

  const nova = useCallback(() => { setErro(null); setEdicao({ nome: '', aliquota: '', so_grupo: false }); }, []);
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
      if (e.key === 'n' && pode.criar) { e.preventDefault(); nova(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [nova, pode.criar]);

  const podeSalvar = !!edicao && edicao.nome.trim() !== '' && edicao.aliquota.trim() !== '';
  const salvar = async () => {
    if (!edicao || !podeSalvar) return;
    setSalvando(true); setErro(null);
    // Texto pt-BR como o input_number da Blade: o num_uf do controller é o único parser.
    const campos: Record<string, string> = { name: edicao.nome.trim(), amount: edicao.aliquota.trim() };
    if (edicao.so_grupo) campos.for_tax_group = '1';
    if (edicao.id) campos._method = 'PUT';
    const r = await enviar(edicao.id ? `/tax-rates/${edicao.id}` : '/tax-rates', campos);
    setSalvando(false);
    if (!r.success) { setErro(r.msg || 'Não foi possível salvar a alíquota.'); return; }
    setEdicao(null);
    setAviso(r.msg ?? null);
    router.reload({ only: ['impostos'] });
  };

  const termo = q.trim().toLowerCase();
  const filtrar = <T extends { nome: string }>(lista: T[]) => lista.filter((x) => !termo || x.nome.toLowerCase().includes(termo));

  const confirmarExclusao = async () => {
    if (!excluir) return;
    const r = await enviar(`/tax-rates/${excluir.id}`, { _method: 'DELETE' });
    setExcluir(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['impostos'] });
  };

  const colAliquotas: ColumnDef<Aliquota, unknown>[] = [
    {
      id: 'nome', header: 'Alíquota',
      cell: ({ row: { original: a } }) => (
        <Inline gap={2}><span className="font-medium">{a.nome}</span>{a.so_grupo && <Badge variant="outline">só em grupo</Badge>}</Inline>
      ),
    },
    { id: 'aliquota', header: 'Percentual', meta: { align: 'right', mono: true }, cell: ({ row: { original: a } }) => pct(a.aliquota) },
    { id: 'uso', header: 'Uso', cell: ({ row: { original: a } }) => <span className="text-muted-foreground">{a.em_grupo ? 'Compõe um grupo' : '—'}</span> },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: a } }) => (pode.editar || pode.excluir) && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${a.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {pode.editar && (
              <DropdownMenuItem onSelect={() => { setErro(null); setEdicao({ id: a.id, nome: a.nome, aliquota: paraTexto(a.aliquota), so_grupo: a.so_grupo }); }}>
                Editar alíquota
              </DropdownMenuItem>
            )}
            {/* O destroy() recusa alíquota que compõe grupo: a tela avisa antes, pela mesma relação. */}
            {pode.excluir && (a.em_grupo
              ? <DropdownMenuItem disabled>Compõe um grupo — tire do grupo antes de excluir</DropdownMenuItem>
              : <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(a)}>Excluir alíquota</DropdownMenuItem>)}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];
  const colGrupos: ColumnDef<Grupo, unknown>[] = [
    { id: 'nome', header: 'Grupo', cell: ({ row: { original: g } }) => <span className="font-medium">{g.nome}</span> },
    { id: 'aliquota', header: 'Percentual', meta: { align: 'right', mono: true }, cell: ({ row: { original: g } }) => pct(g.aliquota) },
    { id: 'sub', header: 'Composição', cell: ({ row: { original: g } }) => <span className="text-muted-foreground">{g.sub_impostos.join(' + ') || '—'}</span> },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Impostos"
          subtitle={impostos ? <><strong>{aliquotas.length}</strong> {aliquotas.length === 1 ? 'alíquota' : 'alíquotas'} · <strong>{grupos.length}</strong> {grupos.length === 1 ? 'grupo' : 'grupos'}</> : 'alíquotas e grupos de imposto'}
          subnav={<ConfiguracoesSubNav />}
          actions={pode.criar ? <Button onClick={nova}><Plus className="size-4" /> Nova alíquota</Button> : undefined} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        {nfe_ativo && (
          <Stack data-contract="aviso-nfe" gap={1} className="rounded-md border p-4 text-sm">
            <strong>Configuração fiscal avançada disponível</strong>
            <span className="text-muted-foreground">Para NF-e/NFC-e, use a tributação por NCM, UF e CST/CSOSN em vez de alíquotas avulsas.</span>
            <a className="text-sm font-medium underline" href="/nfe-brasil/tributacao">Abrir a Tributação NF-e</a>
          </Stack>
        )}
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar alíquota ou grupo…" aria-label="Buscar alíquota ou grupo" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="impostos" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando impostos…</p>}>
          <Stack gap={6}>
            {aliquotas.length > 0 ? (
              <div data-contract="aliquotas-table">
                <DataTable columns={colAliquotas} data={filtrar(aliquotas)} caption="Alíquotas do negócio" rowKey={(a) => a.id}
                  emptyMessage="Nenhuma alíquota com esse termo." />
              </div>
            ) : (
              <div data-contract="vazio">
                <EmptyState title="Nenhuma alíquota cadastrada" description="Cadastre as alíquotas que a venda aplica (ICMS, ISS, PIS, COFINS…)."
                  action={pode.criar ? <Button onClick={nova}>Cadastrar a primeira</Button> : undefined} />
              </div>
            )}
            <Stack gap={2} data-contract="grupos-table">
              <h2 className="m-0 text-sm font-semibold">Grupos de imposto</h2>
              {grupos.length > 0
                ? <DataTable columns={colGrupos} data={filtrar(grupos)} caption="Grupos de imposto do negócio" rowKey={(g) => g.id} emptyMessage="Nenhum grupo com esse termo." />
                : <p className="text-sm text-muted-foreground">Nenhum grupo. Um grupo soma alíquotas (ex.: PIS + COFINS).</p>}
            </Stack>
          </Stack>
        </Deferred>
      </Stack>

      <Sheet open={!!edicao} onOpenChange={(v) => !v && setEdicao(null)}>
        <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-md">
          <header className="os-drawer-head">
            <div className="os-drawer-head-l">
              <SheetTitle asChild><h2 className="m-0">{edicao?.id ? `Editar ${edicao.nome}` : 'Nova alíquota'}</h2></SheetTitle>
              <SheetDescription asChild><p>Percentual que a venda aplica.{edicao?.id ? ' Os grupos que usam esta alíquota são recalculados ao salvar.' : ''}</p></SheetDescription>
            </div>
          </header>
          {edicao && (
            <Stack data-contract="aliquota-form" gap={3} className="p-5 text-sm">
              <Stack gap={1}>
                <label htmlFor="imp-nome">Nome *</label>
                <Input id="imp-nome" value={edicao.nome} placeholder="Ex.: ICMS 18%" onChange={(e) => setEdicao({ ...edicao, nome: e.target.value })} />
              </Stack>
              <Stack gap={1}>
                <label htmlFor="imp-aliquota">Percentual (%) *</label>
                <Input id="imp-aliquota" inputMode="decimal" value={edicao.aliquota} placeholder="Ex.: 18,00"
                  onChange={(e) => setEdicao({ ...edicao, aliquota: e.target.value.replace(/[^0-9,]/g, '') })} />
                <span className="text-xs text-muted-foreground">Use vírgula para os decimais.</span>
              </Stack>
              <Inline gap={2}>
                <Checkbox id="imp-so-grupo" checked={edicao.so_grupo} onCheckedChange={(v) => setEdicao({ ...edicao, so_grupo: v === true })} />
                <label htmlFor="imp-so-grupo">Só para compor grupo de imposto</label>
              </Inline>
              {erro && <p role="alert" className="text-destructive">{erro}</p>}
              <Inline gap={2} justify="end" className="border-t pt-4">
                <Button variant="ghost" onClick={() => setEdicao(null)}>Cancelar</Button>
                <Button disabled={!podeSalvar || salvando} onClick={salvar}>{edicao.id ? 'Salvar' : 'Cadastrar'}</Button>
              </Inline>
            </Stack>
          )}
        </SheetContent>
      </Sheet>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">A alíquota sai da lista de impostos do negócio.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

ImpostosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ImpostosIndex;
