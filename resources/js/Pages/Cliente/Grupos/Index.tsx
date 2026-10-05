// @memcofre
//   tela: /customer-group
//   module: Cliente
//   stories: thread cliente/playbook/03 (D2 = tela própria) · US-CRM-CLIENTE
//   permissao: customer.view (ver) · customer.create/update/delete (ações)
//
// Grupos de cliente: o grupo carrega o ajuste de preço aplicado na venda de quem está nele.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · Contrato: governance/design/contracts/cliente-grupos.contract.json
// Âncora de design: prototipo-ui/cowork/Wagner/cliente-grupos.jsx → ClienteGruposPage()
//
// O percentual é um AJUSTE com sinal (positivo aumenta, negativo diminui o preço) — é o que o
// sistema faz e o que a dica da Blade dizia. O protótipo o chama de "Desconto" e só aceita dígitos;
// aqui o sinal e os decimais ficam (RUNBOOK-grupos §10).
// O valor viaja como TEXTO pt-BR ("10,50"), igual ao input_number da Blade: o `num_uf` do
// store/update continua sendo o único parser (regra mestre de valor).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Inline, Stack } from '@/Components/layout';

type Calculo = 'percentage' | 'selling_price_group';
interface Grupo {
  id: number; nome: string; calculo: Calculo; percentual: number;
  tabela_id: number | null; tabela_nome: string | null; cadastros: number;
}
interface Tabela { id: number; nome: string }
interface Props { grupos?: Grupo[]; tabelas: Tabela[]; pode: { criar: boolean; editar: boolean; excluir: boolean } }
interface Edicao { id?: number; nome: string; calculo: Calculo; percentual: string; tabela: string }

/** 10.5 → "10,50"; -5.25 → "-5,25". Mesmo formato que o num_format da Blade punha no campo. */
const paraTexto = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const ajusteLegivel = (n: number) => (n > 0 ? `+${paraTexto(n)}%` : `${paraTexto(n).replace('-', '−')}%`);

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** Mesmo corpo que o `$(form).serialize()` da Blade mandava; `X-Requested-With` porque update/destroy exigem ajax(). */
async function enviar(url: string, campos: Record<string, string>): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
    body: new URLSearchParams(campos),
  });
  if (!r.ok) return { success: false, msg: `Não foi possível salvar (HTTP ${r.status}).` };
  return r.json();
}

function GruposIndex({ grupos: gruposProp, tabelas, pode }: Props) {
  const grupos = useMemo(() => gruposProp ?? [], [gruposProp]);
  const [q, setQ] = useState('');
  const [edicao, setEdicao] = useState<Edicao | null>(null);
  const [excluir, setExcluir] = useState<Grupo | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [salvando, setSalvando] = useState(false);
  const busca = useRef<HTMLInputElement>(null);

  const novo = useCallback(() => { setErro(null); setEdicao({ nome: '', calculo: 'percentage', percentual: '', tabela: '' }); }, []);
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
      if (e.key === 'n' && pode.criar) { e.preventDefault(); novo(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [pode.criar, novo]);

  const termo = q.trim().toLowerCase();
  const lista = useMemo(() => grupos.filter((g) => !termo || g.nome.toLowerCase().includes(termo)), [grupos, termo]);
  const tabelasValidas = tabelas.filter((t) => t.id > 0 && t.nome);

  const podeSalvar = !!edicao && edicao.nome.trim() !== '' && (edicao.calculo === 'percentage' || edicao.tabela !== '');
  const salvar = async () => {
    if (!edicao || !podeSalvar) return;
    setSalvando(true); setErro(null);
    const campos: Record<string, string> = {
      name: edicao.nome.trim(),
      price_calculation_type: edicao.calculo,
      amount: edicao.calculo === 'percentage' ? edicao.percentual.trim() : '',
      selling_price_group_id: edicao.calculo === 'selling_price_group' ? edicao.tabela : '',
    };
    if (edicao.id) campos._method = 'PUT';
    const r = await enviar(edicao.id ? `/customer-group/${edicao.id}` : '/customer-group', campos);
    setSalvando(false);
    if (!r.success) { setErro(r.msg || 'Não foi possível salvar o grupo.'); return; }
    setEdicao(null);
    router.reload({ only: ['grupos'] });
  };
  const confirmarExclusao = async () => {
    if (!excluir) return;
    const r = await enviar(`/customer-group/${excluir.id}`, { _method: 'DELETE' });
    setExcluir(null);
    if (r.success) router.reload({ only: ['grupos'] });
  };

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Grupos de cliente"
          subtitle={gruposProp ? <><strong>{grupos.length}</strong> {grupos.length === 1 ? 'grupo' : 'grupos'} · definem o preço aplicado na venda</> : 'definem o preço aplicado na venda'}
          actions={pode.criar ? <Button onClick={novo}><Plus className="size-4" /> Novo grupo</Button> : undefined} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <a href="/cliente" className="text-sm text-muted-foreground hover:underline">← Clientes</a>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar grupo pelo nome…" aria-label="Buscar grupo" />
          </div>
        </Inline>

        <Deferred data="grupos" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando grupos…</p>}>
        {lista.length > 0 ? (
          <div className="overflow-x-auto">
            <table data-contract="grupos-table" className="w-full text-sm [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-0 [&_th:first-child]:pl-0 [&_td:last-child]:pr-0 [&_th:last-child]:pr-0">
              <thead><tr className="border-b text-left text-xs text-muted-foreground">
                <th className="py-2">Grupo</th><th>Cálculo do preço</th><th className="text-right">Ajuste</th>
                <th>Tabela de preço</th><th className="text-right">Cadastros</th><th />
              </tr></thead>
              <tbody>
                {lista.map((g) => (
                  <tr key={g.id} className="border-b">
                    <td className="py-2 font-medium">{g.nome}</td>
                    <td className="text-muted-foreground">{g.calculo === 'percentage' ? 'Percentual sobre o preço' : 'Tabela de preço própria'}</td>
                    <td className="text-right tabular-nums">
                      {g.calculo !== 'percentage' ? <span className="text-muted-foreground">—</span>
                        : g.percentual === 0 ? <span className="text-muted-foreground">sem ajuste</span>
                          : ajusteLegivel(g.percentual)}
                    </td>
                    <td>{g.calculo === 'selling_price_group' && g.tabela_nome ? g.tabela_nome : <span className="text-muted-foreground">—</span>}</td>
                    <td className="text-right tabular-nums">{g.cadastros}</td>
                    <td className="text-right">
                      {(pode.editar || pode.excluir) && (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações do grupo ${g.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            {pode.editar && (
                              <DropdownMenuItem onSelect={() => { setErro(null); setEdicao({ id: g.id, nome: g.nome, calculo: g.calculo, percentual: paraTexto(g.percentual), tabela: g.tabela_id ? String(g.tabela_id) : '' }); }}>
                                Editar grupo
                              </DropdownMenuItem>
                            )}
                            {pode.excluir && <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(g)}>Excluir grupo</DropdownMenuItem>}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div data-contract="vazio">
            {grupos.length === 0
              ? <EmptyState title="Nenhum grupo de cliente ainda" description="Sem grupo, o cliente paga o preço de tabela." action={pode.criar ? <Button onClick={novo}>Criar o primeiro grupo</Button> : undefined} />
              : <EmptyState title="Nenhum grupo com esse nome" description="Tente outro termo na busca." />}
          </div>
        )}
        </Deferred>
      </Stack>

      <Dialog open={!!edicao} onOpenChange={(v) => !v && setEdicao(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{edicao?.id ? 'Editar grupo' : 'Novo grupo'}</DialogTitle>
            <DialogDescription>O ajuste vale para toda venda de quem estiver no grupo.</DialogDescription>
          </DialogHeader>
          {edicao && (
            <Stack data-contract="grupo-form" gap={3} className="text-sm">
              <Stack gap={1}>
                <label htmlFor="grupo-nome">Nome</label>
                <Input id="grupo-nome" autoFocus value={edicao.nome} maxLength={191} placeholder="Ex.: Atacado"
                  onChange={(e) => setEdicao({ ...edicao, nome: e.target.value })} />
              </Stack>
              <Stack gap={1}>
                <label htmlFor="grupo-calculo">Cálculo do preço</label>
                <Select value={edicao.calculo} onValueChange={(v) => setEdicao({ ...edicao, calculo: v as Calculo })}>
                  <SelectTrigger id="grupo-calculo"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="percentage">Percentual sobre o preço</SelectItem>
                    <SelectItem value="selling_price_group">Tabela de preço própria</SelectItem>
                  </SelectContent>
                </Select>
              </Stack>
              {edicao.calculo === 'percentage' ? (
                <Stack gap={1}>
                  <label htmlFor="grupo-ajuste">Ajuste (%)</label>
                  <Input id="grupo-ajuste" inputMode="decimal" value={edicao.percentual} placeholder="Ex.: -10 ou 5,5"
                    onChange={(e) => setEdicao({ ...edicao, percentual: e.target.value.replace(/[^0-9,.-]/g, '') })} />
                  <span className="text-xs text-muted-foreground">Positivo aumenta o preço de venda; negativo diminui.</span>
                </Stack>
              ) : (
                <Stack gap={1}>
                  <label htmlFor="grupo-tabela">Tabela de preço</label>
                  <Select value={edicao.tabela} onValueChange={(v) => setEdicao({ ...edicao, tabela: v })}>
                    <SelectTrigger id="grupo-tabela"><SelectValue placeholder="Selecione" /></SelectTrigger>
                    <SelectContent>
                      {tabelasValidas.map((t) => <SelectItem key={t.id} value={String(t.id)}>{t.nome}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Stack>
              )}
              <p className="text-xs text-muted-foreground">Ou o grupo aplica um percentual sobre o preço, ou usa uma tabela de preço própria — nunca os dois. Quem não tem grupo paga o preço de tabela.</p>
              {erro && <p role="alert" className="text-destructive">{erro}</p>}
            </Stack>
          )}
          <DialogFooter>
            <Button variant="ghost" onClick={() => setEdicao(null)}>Cancelar</Button>
            <Button disabled={!podeSalvar || salvando} onClick={salvar}>{edicao?.id ? 'Salvar grupo' : 'Criar grupo'}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir este grupo?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">
            {excluir && excluir.cadastros > 0
              ? `${excluir.cadastros} ${excluir.cadastros === 1 ? 'cadastro usa' : 'cadastros usam'} o grupo “${excluir.nome}”.`
              : `Nenhum cadastro usa o grupo “${excluir?.nome}”.`}
          </p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir grupo</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

GruposIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default GruposIndex;
