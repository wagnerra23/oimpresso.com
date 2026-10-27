// @memcofre
//   tela: /vestuario/remarcacao
//   stories: US-VEST-032 (Remarcação por coleção, com prévia e reversão)
//   protótipo: prototipo-ui/cowork/Wagner/vestuario-remarcacao-page.jsx (ADR 0438)
//   runbook: memory/requisitos/Vestuario/RUNBOOK-remarcacao.md
//   permissao: product.update · liga por empresa: setting remarcacao.enabled

import { useState } from 'react';
import { Deferred, Head } from '@inertiajs/react';
import { Printer, Undo2 } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Segmented } from '@/Components/ui/segmented';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Inline } from '@/Components/layout';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

interface Opcao { id: number; name: string; parent_id?: number }
interface Lote { id: number; nome: string; modo: string; valor: string; qtd_itens: number; status: string; created_at: string }
interface Linha { variation_id: number; produto: string; variacao: string; sku: string; preco_antes: number; preco_depois: number }
interface Previa { linhas: Linha[]; ignoradas: number; assinatura: string }
interface Props { categorias: Opcao[]; marcas: Opcao[]; lotes?: Lote[] }

const TODAS = '__todas__';
const brl = (n: number) => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
// Valor digitado em pt-BR ("39,90"); vai ao servidor como número com 2 casas (regra mestre de valor).
const num = (s: string) => Math.round((parseFloat(s.replace(/\./g, '').replace(',', '.')) || 0) * 100) / 100;
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

async function api(url: string, method: 'GET' | 'POST', body?: unknown) {
  const res = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(json.message ?? `Erro ${res.status}`);
  return json;
}

export default function RemarcacaoIndex({ categorias, marcas, lotes }: Props) {
  const [categoria, setCategoria] = useState(TODAS);
  const [subcategoria, setSubcategoria] = useState(TODAS);
  const [marca, setMarca] = useState(TODAS);
  const [nomeFiltro, setNomeFiltro] = useState('');
  const [modo, setModo] = useState<'percentual' | 'valor'>('percentual');
  const [valor, setValor] = useState('-30');
  const [nome, setNome] = useState('');
  const [previa, setPrevia] = useState<Previa | null>(null);
  const [confirmar, setConfirmar] = useState(false);
  const [ocupado, setOcupado] = useState(false);
  const [aviso, setAviso] = useState<string | null>(null);
  const [feito, setFeito] = useState<{ lote: number; n: number } | null>(null);
  const [lista, setLista] = useState<Lote[] | null>(null);

  const pais = categorias.filter((c) => !c.parent_id);
  const filhas = categorias.filter((c) => c.parent_id && String(c.parent_id) === categoria);
  const id = (v: string) => (v === TODAS ? null : Number(v));
  const corpo = () => ({
    modo, valor: num(valor), nome,
    filtro: { category_id: id(categoria), sub_category_id: id(subcategoria), brand_id: id(marca), nome: nomeFiltro.trim() || null },
  });
  const linhasLote = lista ?? lotes ?? [];

  async function rodar(f: () => Promise<void>) {
    setOcupado(true); setAviso(null);
    try { await f(); } catch (e) { setAviso(e instanceof Error ? e.message : String(e)); } finally { setOcupado(false); }
  }
  const verPrevia = () => rodar(async () => { setFeito(null); setPrevia(await api('/vestuario/remarcacao/previa', 'POST', corpo())); });
  const gravar = () => rodar(async () => {
    const r = await api('/vestuario/remarcacao', 'POST', { ...corpo(), assinatura: previa?.assinatura });
    setFeito({ lote: r.lote, n: previa?.linhas.length ?? 0 });
    setLista([{ id: r.lote, nome, modo, valor: String(num(valor)), qtd_itens: previa?.linhas.length ?? 0, status: 'aplicada', created_at: new Date().toISOString() }, ...linhasLote]);
    setPrevia(null); setConfirmar(false);
  });
  const reverter = (l: Lote) => rodar(async () => {
    if (!window.confirm(`Reverter "${l.nome}"? Volta o preço de antes; peça mexida depois fica como está.`)) return;
    const r = await api(`/vestuario/remarcacao/${l.id}/reverter`, 'POST');
    setAviso(`${r.revertidos} revertidas${r.conflitos ? ` · ${r.conflitos} ficaram como estavam (preço mexido depois)` : ''}.`);
    setLista(linhasLote.map((x) => (x.id === l.id ? { ...x, status: 'revertida' } : x)));
  });
  const etiquetas = (lote: number) => rodar(async () => {
    const { items } = await api(`/vestuario/remarcacao/${lote}/etiquetas`, 'GET');
    const res = await fetch('/vestuario/etiquetas/lote/pdf', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/pdf', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ items: items.slice(0, 500) }),
    });
    if (!res.ok) throw new Error(`Etiquetas: erro ${res.status}`);
    const url = URL.createObjectURL(await res.blob());
    const a = Object.assign(document.createElement('a'), { href: url, download: `etiquetas-remarcacao-${lote}.pdf` });
    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  });

  const selectDe = (rotulo: string, v: string, set: (v: string) => void, ops: Opcao[]) => (
    <div className="space-y-1">
      <Label className="text-xs">{rotulo}</Label>
      <Select value={v} onValueChange={set}>
        <SelectTrigger aria-label={rotulo}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODAS}>Todas</SelectItem>
          {ops.map((o) => <SafeSelectItem key={o.id} value={String(o.id)}>{o.name}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );

  return (
    <>
      <Head title="Remarcação — Vestuário" />
      <div data-contract="cabecalho">
        <PageHeader title="Remarcação por coleção" subtitle="Muda o preço de etiqueta de várias peças de uma vez, com prévia antes de gravar" />
      </div>
      <div className="space-y-4 p-4">
        {feito && (
          <Inline justify="between" gap={0} className="rounded-md bg-success-soft p-3 text-sm" role="status">
            <span><b>Remarcação gravada.</b> {feito.n} variações remarcadas.</span>
            <Button size="sm" variant="outline" disabled={ocupado} onClick={() => etiquetas(feito.lote)}>
              <Printer className="mr-1 h-4 w-4" /> Imprimir etiquetas
            </Button>
          </Inline>
        )}
        {aviso && <div className="rounded-md border p-3 text-sm" role="alert">{aviso}</div>}

        <Card data-contract="filtro">
          <CardHeader><CardTitle className="text-sm">1 · Quais peças</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <Grid cols={4} gap={3}>
              {selectDe('Categoria', categoria, (v) => { setCategoria(v); setSubcategoria(TODAS); }, pais)}
              {selectDe('Subcategoria', subcategoria, setSubcategoria, filhas)}
              {selectDe('Marca', marca, setMarca, marcas)}
              <div className="space-y-1">
                <Label htmlFor="rem-nome" className="text-xs">Coleção (nome contém)</Label>
                <Input id="rem-nome" value={nomeFiltro} placeholder="ex.: Verão24" onChange={(e) => setNomeFiltro(e.target.value)} />
              </div>
            </Grid>
            <Inline wrap align="end" gap={3}>
              <div className="space-y-1">
                <Label className="text-xs">Novo preço</Label>
                <Segmented
                  value={modo}
                  onValueChange={(v) => { setModo(v as 'percentual' | 'valor'); setValor(v === 'valor' ? '' : '-30'); }}
                  options={[{ value: 'percentual', label: 'Percentual' }, { value: 'valor', label: 'Valor fixo' }]}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="rem-valor" className="text-xs">{modo === 'percentual' ? 'Percentual (negativo baixa)' : 'Novo preço (R$)'}</Label>
                <Input id="rem-valor" className="w-36" inputMode="decimal" value={valor} onChange={(e) => setValor(e.target.value)} />
              </div>
              <Button disabled={ocupado} onClick={verPrevia}>Ver prévia</Button>
            </Inline>
          </CardContent>
        </Card>

        {previa && (
          <Card data-contract="previa">
            <CardHeader><CardTitle className="text-sm">2 · Prévia — nada foi gravado ainda</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <Inline wrap align="stretch" gap={2} className="text-xs">
                <Badge variant="outline">{previa.linhas.length} variações mudam de preço</Badge>
                {previa.ignoradas > 0 && <Badge variant="outline">{previa.ignoradas} sem mudança, fora da lista</Badge>}
                <Badge variant="outline">Tabelas de preço por grupo não mudam</Badge>
              </Inline>
              <table className="w-full text-sm tabular-nums">
                <thead><tr className="border-b text-left text-xs text-muted-foreground">
                  <th className="p-2">Produto</th><th className="p-2">Variação</th><th className="p-2">SKU</th>
                  <th className="p-2 text-right">Antes</th><th className="p-2 text-right">Depois</th><th className="p-2 text-right">Diferença</th>
                </tr></thead>
                <tbody>{previa.linhas.map((l) => (
                  <tr key={l.variation_id} className="border-b">
                    <td className="p-2">{l.produto}</td><td className="p-2">{l.variacao}</td><td className="p-2">{l.sku}</td>
                    <td className="p-2 text-right text-muted-foreground line-through">{brl(l.preco_antes)}</td>
                    <td className="p-2 text-right font-semibold">{brl(l.preco_depois)}</td>
                    <td className="p-2 text-right">{brl(l.preco_depois - l.preco_antes)}</td>
                  </tr>))}
                </tbody>
              </table>
              <Inline justify="between" gap={3}>
                <p className="text-xs text-muted-foreground">Se algum preço mudar antes de gravar, a gravação é recusada e a prévia precisa ser refeita.</p>
                <Button disabled={ocupado || previa.linhas.length === 0} onClick={() => setConfirmar(true)}>Gravar remarcação</Button>
              </Inline>
            </CardContent>
          </Card>
        )}

        <Card data-contract="historico">
          <CardHeader><CardTitle className="text-sm">Remarcações anteriores</CardTitle></CardHeader>
          <CardContent>
            <Deferred data="lotes" fallback={<p className="text-sm text-muted-foreground">Carregando…</p>}>
              {linhasLote.length === 0
                ? <p className="text-sm text-muted-foreground">Nenhuma remarcação ainda.</p>
                : (
                  <table className="w-full text-sm tabular-nums">
                    <thead><tr className="border-b text-left text-xs text-muted-foreground">
                      <th className="p-2">Quando</th><th className="p-2">Nome</th><th className="p-2">Operação</th>
                      <th className="p-2 text-right">Peças</th><th className="p-2">Situação</th><th className="p-2" />
                    </tr></thead>
                    <tbody>{linhasLote.map((l) => (
                      <tr key={l.id} className="border-b">
                        <td className="p-2">{new Date(l.created_at.replace(' ', 'T')).toLocaleString('pt-BR')}</td>
                        <td className="p-2">{l.nome}</td>
                        <td className="p-2">{l.modo === 'percentual' ? `${Number(l.valor).toLocaleString('pt-BR')}%` : brl(Number(l.valor))}</td>
                        <td className="p-2 text-right">{l.qtd_itens}</td>
                        <td className="p-2"><Badge variant={l.status === 'aplicada' ? 'default' : 'outline'}>{l.status}</Badge></td>
                        <td className="p-2 text-right">
                          {l.status === 'aplicada' && (
                            <span className="inline-flex gap-2">
                              <Button size="sm" variant="outline" disabled={ocupado} onClick={() => etiquetas(l.id)}><Printer className="mr-1 h-4 w-4" /> Etiquetas</Button>
                              <Button size="sm" variant="outline" disabled={ocupado} onClick={() => reverter(l)}><Undo2 className="mr-1 h-4 w-4" /> Reverter</Button>
                            </span>
                          )}
                        </td>
                      </tr>))}
                    </tbody>
                  </table>
                )}
            </Deferred>
          </CardContent>
        </Card>
      </div>

      <AlertDialog open={confirmar} onOpenChange={setConfirmar}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Gravar a remarcação?</AlertDialogTitle>
            <AlertDialogDescription>
              {previa?.linhas.length ?? 0} variações vão mudar de preço. O preço de antes fica guardado e pode ser revertido.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="space-y-1">
            <Label htmlFor="rem-lote" className="text-xs">Nome da remarcação</Label>
            <Input id="rem-lote" value={nome} placeholder="ex.: Liquidação Verão24" onChange={(e) => setNome(e.target.value)} />
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction disabled={ocupado || !nome.trim()} onClick={(e) => { e.preventDefault(); gravar(); }}>Gravar</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

RemarcacaoIndex.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>;
