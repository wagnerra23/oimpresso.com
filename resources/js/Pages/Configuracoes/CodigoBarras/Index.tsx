// @memcofre
//   tela: /barcodes
//   module: Configuracoes
//   stories: thread sistema/playbook/04 (tela 2 de 3)
//   permissao: barcode_settings.access (o controller exige em todas as ações)
//
// Configurações de etiqueta (folha ou rolo) usadas na impressão de código de barras. Atrás da flag
// `useV2ConfiguracoesCodigoBarras` (BarcodeController::FLAG_V2); desligada, a rota segue na Blade `barcode/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-codigo-barras.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Barras()
//
// O protótipo pede medidas em mm; o banco e a impressão são em polegada — a tela segue em polegada até decisão [W]
// (RUNBOOK §10). "Imprimir prova" fica fora: não existe endpoint.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Textarea } from '@/Components/ui/textarea';
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
import { Grid, Inline, Stack } from '@/Components/layout';
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';

export interface Etiqueta {
  id: number; nome: string; descricao: string; padrao: boolean; continuo: boolean;
  por_linha: number | null; por_folha: number | null; medidas: Record<string, number | null>;
}
interface Props { etiquetas?: Etiqueta[] }

/** Campos de medida da Blade, em polegada (`barcode.in_in`). Os da folha somem no rolo contínuo. */
const MEDIDAS: { k: string; rotulo: string; soFolha?: boolean }[] = [
  { k: 'top_margin', rotulo: 'Margem superior' }, { k: 'left_margin', rotulo: 'Margem esquerda' },
  { k: 'width', rotulo: 'Largura da etiqueta' }, { k: 'height', rotulo: 'Altura da etiqueta' },
  { k: 'paper_width', rotulo: 'Largura do papel' }, { k: 'paper_height', rotulo: 'Altura do papel', soFolha: true },
  { k: 'row_distance', rotulo: 'Distância entre linhas' }, { k: 'col_distance', rotulo: 'Distância entre colunas' },
];
interface Edicao {
  id?: number; nome: string; descricao: string; continuo: boolean; padrao: boolean;
  por_linha: string; por_folha: string; medidas: Record<string, string>;
}
/** Defaults do `barcode/create.blade.php`: margens e distâncias 0. */
const NOVA: Edicao = {
  nome: '', descricao: '', continuo: false, padrao: false, por_linha: '', por_folha: '',
  medidas: { top_margin: '0', left_margin: '0', row_distance: '0', col_distance: '0' },
};
const texto = (n: number | null | undefined) => (n == null ? '' : String(n));
/** O `Form::number` da Blade manda ponto; vírgula digitada vira ponto (medida, não dinheiro). */
const numero = (v: string) => v.replace(',', '.').replace(/[^0-9.]/g, '');

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** destroy() e setDefault() só respondem a ajax() e devolvem `{success, msg}` — igual à Blade. */
async function chamar(metodo: 'DELETE' | 'GET', url: string): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(url, {
    method: metodo,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível concluir (HTTP ${r.status}).` };
}

function CodigoBarrasIndex({ etiquetas: etiquetasProp }: Props) {
  const etiquetas = useMemo(() => etiquetasProp ?? [], [etiquetasProp]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<Etiqueta | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [edicao, setEdicao] = useState<Edicao | null>(null);
  const [salvando, setSalvando] = useState(false);
  const busca = useRef<HTMLInputElement>(null);
  // store()/update() redirecionam com ->with('status'): a mensagem chega como flash (sucesso ou erro).
  const { flash } = usePage<{ flash?: { success?: unknown; error?: unknown } }>().props;
  const flashMsg = [flash?.error, flash?.success].find((m): m is string => typeof m === 'string' && m !== '');

  const nova = useCallback(() => setEdicao({ ...NOVA, medidas: { ...NOVA.medidas } }), []);
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
      if (e.key === 'n') { e.preventDefault(); nova(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [nova]);

  const editar = (e: Etiqueta) => setEdicao({
    id: e.id, nome: e.nome, descricao: e.descricao, continuo: e.continuo, padrao: e.padrao,
    por_linha: texto(e.por_linha), por_folha: texto(e.por_folha),
    medidas: Object.fromEntries(MEDIDAS.map((m) => [m.k, texto(e.medidas[m.k])])),
  });
  const visiveis = MEDIDAS.filter((m) => !(edicao?.continuo && m.soFolha));
  const podeSalvar = !!edicao && edicao.nome.trim() !== '' && edicao.por_linha !== ''
    && visiveis.every((m) => (edicao.medidas[m.k] ?? '') !== '') && (edicao.continuo || edicao.por_folha !== '');
  /** Mesmo corpo do formulário da Blade; no rolo contínuo o servidor força 28 por folha e ignora a altura. */
  const salvar = () => {
    if (!edicao || !podeSalvar) return;
    const corpo: Record<string, string> = {
      name: edicao.nome.trim(), description: edicao.descricao.trim(), stickers_in_one_row: edicao.por_linha,
      stickers_in_one_sheet: edicao.por_folha, ...Object.fromEntries(MEDIDAS.map((m) => [m.k, edicao.medidas[m.k] ?? ''])),
    };
    if (edicao.continuo) corpo.is_continuous = '1';
    if (!edicao.id && edicao.padrao) corpo.is_default = '1';
    const fim = { onStart: () => setSalvando(true), onFinish: () => { setSalvando(false); setEdicao(null); } };
    if (edicao.id) router.put(`/barcodes/${edicao.id}`, corpo, fim);
    else router.post('/barcodes', corpo, fim);
  };
  const muda = (k: 'nome' | 'descricao' | 'por_linha' | 'por_folha', v: string) => setEdicao((e) => (e ? { ...e, [k]: v } : e));
  const mudaMedida = (k: string, v: string) => setEdicao((e) => (e ? { ...e, medidas: { ...e.medidas, [k]: numero(v) } } : e));

  const termo = q.trim().toLowerCase();
  const lista = useMemo(
    () => etiquetas.filter((e) => !termo || [e.nome, e.descricao].some((v) => v.toLowerCase().includes(termo))),
    [etiquetas, termo],
  );

  const depois = (r: { success: boolean; msg?: string }) => {
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['etiquetas'] });
  };
  const tornarPadrao = async (e: Etiqueta) => depois(await chamar('GET', `/barcodes/set_default/${e.id}`));
  const confirmarExclusao = async () => {
    if (!excluir) return;
    const alvo = excluir;
    setExcluir(null);
    depois(await chamar('DELETE', `/barcodes/${alvo.id}`));
  };

  const colunas: ColumnDef<Etiqueta, unknown>[] = [
    {
      id: 'nome', header: 'Configuração',
      cell: ({ row: { original: e } }) => (
        <Inline gap={2}><span className="font-medium">{e.nome}</span>{e.padrao && <Badge variant="secondary">padrão</Badge>}</Inline>
      ),
    },
    { id: 'descricao', header: 'Descrição', cell: ({ row: { original: e } }) => <span className="text-muted-foreground">{e.descricao || '—'}</span> },
    { id: 'papel', header: 'Papel', cell: ({ row: { original: e } }) => (e.continuo ? 'Rolo contínuo' : 'Folha') },
    { id: 'por_folha', header: 'Etiquetas por folha', meta: { align: 'right', mono: true }, cell: ({ row: { original: e } }) => e.por_folha ?? '—' },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: e } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${e.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onSelect={() => editar(e)}>Editar configuração</DropdownMenuItem>
            {!e.padrao && <DropdownMenuItem onSelect={() => tornarPadrao(e)}>Tornar padrão</DropdownMenuItem>}
            {/* A padrão não pode ser excluída: o destroy() recusa, e a Blade desabilita o botão. */}
            {!e.padrao && <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(e)}>Excluir configuração</DropdownMenuItem>}
            {e.padrao && <DropdownMenuItem disabled>É a padrão — escolha outra antes de excluir</DropdownMenuItem>}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Código de barras"
          subtitle={etiquetasProp ? <><strong>{etiquetas.length}</strong> {etiquetas.length === 1 ? 'configuração de etiqueta' : 'configurações de etiqueta'}</> : 'configurações de etiqueta'}
          subnav={<ConfiguracoesSubNav />}
          actions={<Button onClick={nova}><Plus className="size-4" /> Nova configuração</Button>} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar configuração de etiqueta…" aria-label="Buscar configuração de etiqueta" />
          </div>
        </Inline>
        {(aviso ?? flashMsg) && <p role="status" className={flash?.error && !aviso ? 'text-sm text-destructive' : 'text-sm text-muted-foreground'}>{aviso ?? flashMsg}</p>}

        <Deferred data="etiquetas" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando configurações…</p>}>
          {etiquetas.length > 0 ? (
            <div data-contract="etiquetas-table">
              <DataTable columns={colunas} data={lista} caption="Configurações de etiqueta do negócio" rowKey={(e) => e.id}
                emptyMessage="Nenhuma configuração com esse termo. Limpe a busca para ver todas." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma configuração de etiqueta" description="Cada folha ou rolo que a loja compra vira uma configuração aqui."
                action={<Button onClick={nova}>Cadastrar a primeira</Button>} />
            </div>
          )}
        </Deferred>
      </Stack>

      <Sheet open={!!edicao} onOpenChange={(v) => !v && setEdicao(null)}>
        <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-lg">
          <header className="os-drawer-head">
            <div className="os-drawer-head-l">
              <SheetTitle asChild><h2 className="m-0">{edicao?.id ? `Editar ${edicao.nome}` : 'Nova configuração de etiqueta'}</h2></SheetTitle>
              <SheetDescription asChild><p>Medidas em polegada, como a impressão usa.</p></SheetDescription>
            </div>
          </header>
          {edicao && (
            <Stack data-contract="etiqueta-form" gap={3} className="p-5 text-sm">
              <Stack gap={1}>
                <label htmlFor="etq-nome">Nome *</label>
                <Input id="etq-nome" value={edicao.nome} placeholder="Ex.: Etiqueta 3 colunas" onChange={(e) => muda('nome', e.target.value)} />
              </Stack>
              <Stack gap={1}>
                <label htmlFor="etq-desc">Descrição</label>
                <Textarea id="etq-desc" rows={2} value={edicao.descricao} onChange={(e) => muda('descricao', e.target.value)} />
              </Stack>
              <Inline gap={2}>
                <Checkbox id="etq-continuo" checked={edicao.continuo} onCheckedChange={(v) => setEdicao((x) => (x ? { ...x, continuo: v === true } : x))} />
                <label htmlFor="etq-continuo">Rolo contínuo (uma etiqueta por vez)</label>
              </Inline>
              <Grid cols={2} gap={3}>
                {visiveis.map((m) => (
                  <Stack key={m.k} gap={1}>
                    <label htmlFor={`etq-${m.k}`}>{m.rotulo} (pol) *</label>
                    <Input id={`etq-${m.k}`} inputMode="decimal" value={edicao.medidas[m.k] ?? ''} onChange={(e) => mudaMedida(m.k, e.target.value)} />
                  </Stack>
                ))}
                <Stack gap={1}>
                  <label htmlFor="etq-linha">Etiquetas por linha *</label>
                  <Input id="etq-linha" inputMode="numeric" value={edicao.por_linha} onChange={(e) => muda('por_linha', e.target.value.replace(/\D/g, ''))} />
                </Stack>
                {!edicao.continuo && (
                  <Stack gap={1}>
                    <label htmlFor="etq-folha">Etiquetas por folha *</label>
                    <Input id="etq-folha" inputMode="numeric" value={edicao.por_folha} onChange={(e) => muda('por_folha', e.target.value.replace(/\D/g, ''))} />
                  </Stack>
                )}
              </Grid>
              {!edicao.id && (
                <Inline gap={2}>
                  <Checkbox id="etq-padrao" checked={edicao.padrao} onCheckedChange={(v) => setEdicao((x) => (x ? { ...x, padrao: v === true } : x))} />
                  <label htmlFor="etq-padrao">Usar como padrão (a padrão atual deixa de ser)</label>
                </Inline>
              )}
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
          <p className="text-sm">Só a configuração sai; nenhuma etiqueta já impressa muda.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

CodigoBarrasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default CodigoBarrasIndex;
