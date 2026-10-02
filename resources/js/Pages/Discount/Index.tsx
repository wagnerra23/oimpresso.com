// Discount/Index — Descontos (/discount). Thread 04 do playbook venda-menu.
// PT-01 Lista (UI-0013) + drawer PT-02 de cadastro. Golden: Sells/Drafts (lista dual) e
// SalesOrder/Index (drawer). Protótipo: TelaDescontos em prototipo-ui/cowork/Wagner/venda-blade.jsx.
// Decisão D1 de [W] (2026-10-02): ver (`discount.view`) × gravar (`discount.manage`) — sem
// `manage` os botões de gravação aparecem DESABILITADOS com o motivo, não somem.
// Desconto é VALOR: esta tela só cadastra; grava pelos endpoints de sempre, no mesmo formato
// que o Blade manda (checkbox desmarcado não vai no corpo; datas no formato do negócio).
// Refs: ADR 0104 (MWART), ADR 0093 (o controller escopa business_id).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Pencil, Power, Search, Trash2, X } from 'lucide-react';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import EmptyState from '@/Components/shared/EmptyState';
import { Grid, Inline, Stack } from '@/Components/layout';
import { formatDecimalPtBR, parseDecimalPtBR } from '@/Lib/numberPtBR';

interface Produto { id: number; nome: string }

interface Desconto {
  id: number;
  nome: string;
  inicio: string | null;
  fim: string | null;
  prioridade: number | null;
  tipo: 'fixed' | 'percentage' | string | null;
  valor: string;
  marcaId: number | null;
  marca: string | null;
  categoriaId: number | null;
  categoria: string | null;
  localId: number | null;
  local: string | null;
  grupoPreco: string | null;
  aplicaEmGrupoCliente: boolean;
  ativo: boolean;
  produtos: Produto[];
}

type Mapa = Record<string, string>;

export interface DiscountIndexProps {
  descontos?: Desconto[]; // deferred
  opcoes?: { categorias: Mapa; marcas: Mapa; locais: Mapa; gruposPreco: Mapa }; // deferred
  formatoData: { data: string; hora: number };
  permissoes: { editar: boolean };
  urls: {
    salvar: string; atualizar: string; excluir: string; reativar: string;
    desativarEmMassa: string; buscarProdutos: string;
  };
}

interface Form {
  id: number | null;
  nome: string;
  produtos: Produto[];
  marcaId: string;
  categoriaId: string;
  localId: string;
  prioridade: string;
  tipo: string;
  valor: string;
  inicio: string; // YYYY-MM-DDTHH:mm (datetime-local)
  fim: string;
  grupoPreco: string;
  aplicaEmGrupoCliente: boolean;
  ativo: boolean;
}

const NENHUM = 'NENHUM';
const MOTIVO_SEM_EDITAR = 'Seu papel só tem “Ver descontos”. Criar, editar, desativar e excluir pedem “Adicionar, editar, desativar e excluir descontos”.';

/** Opções data-driven sem chave/rótulo vazio — Radix Select quebra com value="" (§5 2026-06-29). */
function opcoes(mapa: Mapa | undefined) {
  return Object.entries(mapa ?? {}).filter(([k, v]) => k !== '' && v);
}

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

/** 'Y-m-d H:i:s' do banco → 'dd/mm/aaaa'. */
function dataCurta(v: string | null): string {
  const m = v ? /^(\d{4})-(\d{2})-(\d{2})/.exec(v) : null;
  return m ? `${m[3]}/${m[2]}/${m[1]}` : '—';
}

/** 'Y-m-d H:i:s' → valor de <input type="datetime-local">. */
function paraInput(v: string | null): string {
  const m = v ? /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/.exec(v) : null;
  return m ? `${m[1]}T${m[2]}` : '';
}

/**
 * datetime-local → string no formato de data/hora do NEGÓCIO, que é o que o `uf_date()` do
 * servidor espera (o datetimepicker do Blade manda a mesma coisa). Formatos do UltimatePOS
 * usam só d, m e Y; hora 12 → 'h:i A', 24 → 'H:i'.
 */
function paraNegocio(local: string, fmt: { data: string; hora: number }): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(local);
  if (!m) return '';
  const [, ano, mes, dia, hh, mi] = m;
  const data = (fmt.data || 'd/m/Y').replace(/[dmY]/g, (t) => ({ d: dia, m: mes, Y: ano }[t] ?? t));
  if (fmt.hora === 12) {
    const h = Number(hh);
    return `${data} ${String(h % 12 || 12).padStart(2, '0')}:${mi} ${h >= 12 ? 'PM' : 'AM'}`;
  }
  return `${data} ${hh}:${mi}`;
}

/**
 * Número do banco (decimal 22,4) em pt-BR SEM perder casas: '12.5000' → '12,5', '0.1234' → '0,1234'.
 * Editar e salvar sem mexer precisa devolver o MESMO número (REGRA MESTRE de valor).
 */
function decimalPtBR(n: number): string {
  return formatDecimalPtBR(n, 4).replace(/(,\d*?)0+$/, '$1').replace(/,$/, '');
}

function valorNaLista(d: Desconto): string {
  const n = Number(d.valor);
  if (Number.isNaN(n)) return d.valor;
  return d.tipo === 'percentage' ? `${decimalPtBR(n)}%` : `R$ ${decimalPtBR(n)}`;
}

function formVazio(): Form {
  return {
    id: null, nome: '', produtos: [], marcaId: NENHUM, categoriaId: NENHUM, localId: NENHUM,
    prioridade: '1', tipo: 'percentage', valor: '', inicio: '', fim: '', grupoPreco: NENHUM,
    aplicaEmGrupoCliente: false, ativo: true,
  };
}

function formDe(d: Desconto): Form {
  return {
    id: d.id, nome: d.nome, produtos: d.produtos,
    marcaId: d.marcaId ? String(d.marcaId) : NENHUM,
    categoriaId: d.categoriaId ? String(d.categoriaId) : NENHUM,
    localId: d.localId ? String(d.localId) : NENHUM,
    prioridade: d.prioridade === null ? '' : String(d.prioridade),
    tipo: d.tipo ?? 'percentage',
    valor: Number.isNaN(Number(d.valor)) ? d.valor : decimalPtBR(Number(d.valor)),
    inicio: paraInput(d.inicio), fim: paraInput(d.fim),
    grupoPreco: d.grupoPreco ?? NENHUM,
    aplicaEmGrupoCliente: d.aplicaEmGrupoCliente, ativo: d.ativo,
  };
}

export default function DiscountIndex({ descontos, opcoes: op, formatoData, permissoes, urls }: DiscountIndexProps) {
  const [busca, setBusca] = useState('');
  const [sel, setSel] = useState<number[]>([]);
  const [form, setForm] = useState<Form | null>(null);
  const [erro, setErro] = useState('');
  const [salvando, setSalvando] = useState(false);
  const [aviso, setAviso] = useState('');
  const [excluir, setExcluir] = useState<Desconto | null>(null);
  const [termo, setTermo] = useState('');
  const [achados, setAchados] = useState<Produto[]>([]);
  const pode = permissoes.editar;

  const lista = useMemo(() => descontos ?? [], [descontos]);
  const q = busca.trim().toLowerCase();
  const visiveis = useMemo(() => (q ? lista.filter((d) => d.nome.toLowerCase().includes(q)) : lista), [lista, q]);

  // Busca de produtos: o mesmo endpoint que o select2 do Blade usa.
  useEffect(() => {
    if (!form || termo.trim().length < 1) { setAchados([]); return; }
    const t = setTimeout(async () => {
      try {
        const res = await fetch(`${urls.buscarProdutos}&term=${encodeURIComponent(termo.trim())}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
        });
        const json = res.ok ? await res.json() : [];
        setAchados((Array.isArray(json) ? json : Object.values(json)).map((r: { variation_id: number; text: string }) => ({ id: Number(r.variation_id), nome: String(r.text) })));
      } catch { setAchados([]); }
    }, 250);
    return () => clearTimeout(t);
  }, [termo, form, urls.buscarProdutos]);

  async function chamar(url: string, metodo: 'POST' | 'GET' | 'DELETE', corpo?: URLSearchParams): Promise<boolean> {
    const res = await fetch(url, {
      method: metodo,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: corpo,
    });
    if (!res.ok) return false;
    const tipo = res.headers.get('content-type') ?? '';
    if (!tipo.includes('json')) return true;
    const json = await res.json().catch(() => null);
    // store/update/destroy/activate devolvem {success}; o DataTable (redirect da massa) não tem.
    return json === null || json.success === undefined || json.success === true || json.success === 1;
  }

  function recarregar(msg: string) {
    setAviso(msg);
    setSel([]);
    router.reload({ only: ['descontos'] });
  }

  async function salvar() {
    if (!form) return;
    if (!form.nome.trim()) { setErro('Informe o nome do desconto.'); return; }
    const valor = parseDecimalPtBR(form.valor);
    if (form.valor.trim() === '' || Number.isNaN(valor)) { setErro('Informe o valor do desconto.'); return; }
    if (form.localId === NENHUM) { setErro('Escolha o local.'); return; }
    if (!form.inicio || !form.fim) { setErro('Informe o começo e o fim do período.'); return; }

    // Mesmo corpo do formulário Blade (serialize): checkbox desmarcado NÃO vai.
    const c = new URLSearchParams();
    if (form.id) c.set('_method', 'PUT');
    c.set('name', form.nome.trim());
    form.produtos.forEach((p) => c.append('variation_ids[]', String(p.id)));
    c.set('brand_id', form.marcaId === NENHUM ? '' : form.marcaId);
    c.set('category_id', form.categoriaId === NENHUM ? '' : form.categoriaId);
    c.set('location_id', form.localId);
    c.set('priority', form.prioridade.trim());
    c.set('discount_type', form.tipo);
    c.set('discount_amount', String(valor)); // ponto decimal, nunca texto pt-BR ambíguo
    c.set('starts_at', paraNegocio(form.inicio, formatoData));
    c.set('ends_at', paraNegocio(form.fim, formatoData));
    c.set('spg', form.grupoPreco === NENHUM ? '' : form.grupoPreco);
    if (form.aplicaEmGrupoCliente) c.set('applicable_in_cg', '1');
    if (form.ativo) c.set('is_active', '1');

    setSalvando(true);
    setErro('');
    try {
      const url = form.id ? urls.atualizar.replace('{id}', String(form.id)) : urls.salvar;
      if (!(await chamar(url, 'POST', c))) throw new Error('falhou');
      setForm(null);
      recarregar('Desconto salvo — vale no PDV a partir da data de início.');
    } catch {
      setErro('Não foi possível salvar o desconto. Tente de novo.');
    } finally {
      setSalvando(false);
    }
  }

  async function reativar(d: Desconto) {
    const ok = await chamar(urls.reativar.replace('{id}', String(d.id)), 'GET');
    if (ok) recarregar(`“${d.nome}” reativado.`);
    else setAviso('Não foi possível reativar.');
  }

  async function confirmarExclusao() {
    if (!excluir) return;
    const ok = await chamar(urls.excluir.replace('{id}', String(excluir.id)), 'DELETE');
    const nome = excluir.nome;
    setExcluir(null);
    if (ok) recarregar(`Desconto “${nome}” excluído.`);
    else setAviso('Não foi possível excluir.');
  }

  async function desativarSelecionados() {
    const c = new URLSearchParams({ selected_discounts: sel.join(',') });
    const n = sel.length;
    const ok = await chamar(urls.desativarEmMassa, 'POST', c);
    if (ok) recarregar(`${n} desconto(s) desativado(s).`);
    else setAviso('Não foi possível desativar.');
  }

  const todosMarcados = visiveis.length > 0 && visiveis.every((d) => sel.includes(d.id));
  const trava = pode ? undefined : MOTIVO_SEM_EDITAR;

  const campo = (rotulo: string, filho: ReactNode, obrig = false) => (
    <Stack gap={1} align="stretch" className="text-sm">
      <span>{rotulo}{obrig && <span className="text-destructive"> *</span>}</span>
      {filho}
    </Stack>
  );

  const seletor = (rotulo: string, valor: string, set: (v: string) => void, mapa: Mapa | undefined, vazio: string) => (
    <Select value={valor} onValueChange={set}>
      <SelectTrigger aria-label={rotulo}><SelectValue /></SelectTrigger>
      <SelectContent>
        <SelectItem value={NENHUM}>{vazio}</SelectItem>
        {opcoes(mapa).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
      </SelectContent>
    </Select>
  );

  return (
    <AppShellV2>
      <Head title="Descontos" />
      <div className="container mx-auto px-6 py-6 space-y-4">
        <div data-contract="cabecalho">
          <PageHeader
            title="Descontos"
            subtitle="Regras de desconto que o PDV aplica sozinho enquanto valem."
            actions={(
              <span title={trava}>
                <PageHeaderPrimary label="Adicionar desconto" onClick={() => { setForm(formVazio()); setErro(''); setTermo(''); }} disabled={!pode} />
              </span>
            )}
          />
        </div>

        <Alert data-contract="aviso">
          <AlertTitle>Ver × editar</AlertTitle>
          <AlertDescription>
            Ver a lista pede “Ver descontos”; criar, editar, desativar e excluir pedem “Adicionar, editar, desativar e excluir descontos”.
            {!pode && <> Seu papel só vê — os botões de gravação ficam desabilitados.</>}
          </AlertDescription>
        </Alert>

        <section data-contract="lista" className="rounded-lg border border-border bg-card overflow-hidden">
          <Inline gap={3} justify="between" className="p-3 border-b border-border">
            <div className="relative max-w-sm flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input variant="shadcn" value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar desconto…" className="pl-9" aria-label="Buscar desconto" />
            </div>
            <span className="text-xs text-muted-foreground tabular-nums">{visiveis.length} de {lista.length}</span>
          </Inline>
          <Deferred data="descontos" fallback={<div className="p-8 text-center text-sm text-muted-foreground">Carregando descontos…</div>}>
            {visiveis.length === 0 ? (
              <EmptyState icon="file-text" title={q ? 'Nenhum desconto encontrado' : 'Nenhum desconto'}
                description={q ? 'Tente outro termo de busca.' : 'Cadastre um desconto para o PDV aplicar no período.'} />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-xs text-muted-foreground uppercase tracking-wide">
                    <tr className="border-b border-border">
                      <th className="px-3 py-2 w-8">
                        <Checkbox aria-label="Selecionar todos" checked={todosMarcados}
                          onCheckedChange={(v) => setSel(v ? visiveis.map((d) => d.id) : [])} />
                      </th>
                      <th className="text-left px-3 py-2 font-medium">Nome</th>
                      <th className="text-left px-3 py-2 font-medium">Começa em</th>
                      <th className="text-left px-3 py-2 font-medium">Termina em</th>
                      <th className="text-right px-3 py-2 font-medium">Valor do desconto</th>
                      <th className="text-right px-3 py-2 font-medium">Prioridade</th>
                      <th className="text-left px-3 py-2 font-medium">Marca</th>
                      <th className="text-left px-3 py-2 font-medium">Categoria</th>
                      <th className="text-right px-3 py-2 font-medium">Produtos</th>
                      <th className="text-left px-3 py-2 font-medium">Local</th>
                      <th className="text-left px-3 py-2 font-medium">Situação</th>
                      <th className="text-right px-3 py-2 font-medium">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {visiveis.map((d) => (
                      <tr key={d.id} className={`border-b border-border last:border-0 ${d.ativo ? '' : 'text-muted-foreground'}`}>
                        <td className="px-3 py-2">
                          <Checkbox aria-label={`Selecionar ${d.nome}`} checked={sel.includes(d.id)}
                            onCheckedChange={(v) => setSel((s) => (v ? [...s, d.id] : s.filter((x) => x !== d.id)))} />
                        </td>
                        <td className="px-3 py-2 font-medium">{d.nome}</td>
                        <td className="px-3 py-2 tabular-nums">{dataCurta(d.inicio)}</td>
                        <td className="px-3 py-2 tabular-nums">{dataCurta(d.fim)}</td>
                        <td className="px-3 py-2 text-right tabular-nums">{valorNaLista(d)}</td>
                        <td className="px-3 py-2 text-right tabular-nums">{d.prioridade ?? '—'}</td>
                        <td className="px-3 py-2">{d.marca ?? '—'}</td>
                        <td className="px-3 py-2">{d.categoria ?? '—'}</td>
                        <td className="px-3 py-2 text-right tabular-nums" title={d.produtos.map((p) => p.nome).join(', ')}>{d.produtos.length}</td>
                        <td className="px-3 py-2">{d.local ?? 'Todos'}</td>
                        <td className="px-3 py-2"><Badge variant={d.ativo ? 'success' : 'neutral'}>{d.ativo ? 'Ativo' : 'Inativo'}</Badge></td>
                        <td className="px-3 py-2 text-right whitespace-nowrap" title={trava}>
                          <Button variant="ghost" size="sm" disabled={!pode} aria-label={`Editar ${d.nome}`}
                            onClick={() => { setForm(formDe(d)); setErro(''); setTermo(''); }}><Pencil className="h-3.5 w-3.5" /></Button>
                          {!d.ativo && (
                            <Button variant="ghost" size="sm" disabled={!pode} aria-label={`Reativar ${d.nome}`} onClick={() => reativar(d)}>
                              <Power className="h-3.5 w-3.5 mr-1" />Reativar
                            </Button>
                          )}
                          <Button variant="ghost" size="sm" disabled={!pode} aria-label={`Excluir ${d.nome}`} onClick={() => setExcluir(d)}>
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Deferred>
          <Inline gap={3} justify="between" wrap className="px-4 py-2 border-t border-border">
            <p className="text-xs text-muted-foreground max-w-3xl">
              O desconto vale no PDV e na venda direta enquanto estiver no período — prioridade menor ganha da maior.
              Escolher produtos apaga marca e categoria: o servidor guarda um ou outro, nunca os dois.
            </p>
            <span title={trava}>
              <Button variant="outline" size="sm" disabled={!pode || sel.length === 0} onClick={desativarSelecionados}>Desativar selecionados</Button>
            </span>
          </Inline>
          {aviso && <p className="px-4 pb-3 text-sm" role="status" aria-live="polite">{aviso}</p>}
        </section>
      </div>

      <Sheet open={form !== null} onOpenChange={(aberto) => { if (!aberto) setForm(null); }}>
        <SheetContent side="right" className="w-[640px] sm:max-w-[640px] overflow-y-auto">
          <SheetHeader>
            <SheetTitle>{form?.id ? 'Editar desconto' : 'Adicionar desconto'}</SheetTitle>
            <SheetDescription>Desconto inativo não aparece no PDV.</SheetDescription>
          </SheetHeader>
          {form && (
            <Stack gap={3} align="stretch" className="px-4">
              {campo('Nome', <Input value={form.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} placeholder="Semana da comunicação visual" aria-label="Nome" />, true)}
              {campo('Produtos', (
                <Stack gap={2} align="stretch">
                  <Input value={termo} onChange={(e) => setTermo(e.target.value)} placeholder="Buscar produtos (deixe vazio para valer por marca/categoria)" aria-label="Buscar produtos" />
                  {achados.length > 0 && (
                    <ul className="max-h-40 overflow-y-auto rounded-md border border-border text-sm">
                      {achados.filter((a) => !form.produtos.some((p) => p.id === a.id)).map((a) => (
                        <li key={a.id}>
                          <button type="button" className="w-full text-left px-3 py-1.5 hover:bg-accent"
                            onClick={() => { setForm({ ...form, produtos: [...form.produtos, a] }); setTermo(''); }}>{a.nome}</button>
                        </li>
                      ))}
                    </ul>
                  )}
                  {form.produtos.length > 0 && (
                    <Inline gap={2} wrap>
                      {form.produtos.map((p) => (
                        <Badge key={p.id} variant="info">
                          {p.nome}
                          <button type="button" aria-label={`Remover ${p.nome}`} className="ml-1"
                            onClick={() => setForm({ ...form, produtos: form.produtos.filter((x) => x.id !== p.id) })}><X className="h-3 w-3" /></button>
                        </Badge>
                      ))}
                    </Inline>
                  )}
                  {form.produtos.length > 0 && (form.marcaId !== NENHUM || form.categoriaId !== NENHUM) && (
                    <p className="text-xs text-warning-fg">Com produtos escolhidos, marca e categoria são ignoradas ao salvar.</p>
                  )}
                </Stack>
              ))}
              <Grid cols={2} gap={3}>
                {campo('Marca', seletor('Marca', form.marcaId, (v) => setForm({ ...form, marcaId: v }), op?.marcas, 'Selecione'))}
                {campo('Categoria', seletor('Categoria', form.categoriaId, (v) => setForm({ ...form, categoriaId: v }), op?.categorias, 'Selecione'))}
                {campo('Local', seletor('Local', form.localId, (v) => setForm({ ...form, localId: v }), op?.locais, 'Selecione'), true)}
                {campo('Prioridade', <Input value={form.prioridade} onChange={(e) => setForm({ ...form, prioridade: e.target.value })} inputMode="numeric" aria-label="Prioridade" />, true)}
                {campo('Tipo de desconto', (
                  <Select value={form.tipo} onValueChange={(v) => setForm({ ...form, tipo: v })}>
                    <SelectTrigger aria-label="Tipo de desconto"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="fixed">Fixo</SelectItem>
                      <SelectItem value="percentage">Percentual</SelectItem>
                    </SelectContent>
                  </Select>
                ), true)}
                {campo('Valor do desconto', <Input value={form.valor} onChange={(e) => setForm({ ...form, valor: e.target.value })} inputMode="decimal" placeholder={form.tipo === 'fixed' ? 'R$' : '%'} aria-label="Valor do desconto" />, true)}
                {campo('Começa em', <Input type="datetime-local" value={form.inicio} onChange={(e) => setForm({ ...form, inicio: e.target.value })} aria-label="Começa em" />, true)}
                {campo('Termina em', <Input type="datetime-local" value={form.fim} onChange={(e) => setForm({ ...form, fim: e.target.value })} aria-label="Termina em" />, true)}
                {campo('Grupo de preço de venda', seletor('Grupo de preço de venda', form.grupoPreco, (v) => setForm({ ...form, grupoPreco: v }), op?.gruposPreco, 'Todos'))}
              </Grid>
              <Inline gap={2} align="center" className="text-sm">
                <Checkbox id="dsc-cg" checked={form.aplicaEmGrupoCliente} onCheckedChange={(v) => setForm({ ...form, aplicaEmGrupoCliente: v === true })} />
                <label htmlFor="dsc-cg">Aplicar no grupo de clientes</label>
              </Inline>
              <Inline gap={2} align="center" className="text-sm">
                <Checkbox id="dsc-ativo" checked={form.ativo} onCheckedChange={(v) => setForm({ ...form, ativo: v === true })} />
                <label htmlFor="dsc-ativo">Ativo</label>
              </Inline>
              {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
            </Stack>
          )}
          <SheetFooter>
            <Button variant="outline" onClick={() => setForm(null)}>Cancelar</Button>
            <Button onClick={salvar} disabled={salvando || !pode}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      <AlertDialog open={excluir !== null} onOpenChange={(aberto) => { if (!aberto) setExcluir(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir “{excluir?.nome}”?</AlertDialogTitle>
            <AlertDialogDescription>O desconto sai do PDV e da lista. Para só tirar do PDV, desative.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AppShellV2>
  );
}
