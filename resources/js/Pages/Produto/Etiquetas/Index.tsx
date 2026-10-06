// @memcofre
//   tela: /labels/show (Imprimir etiquetas)
//   module: Produto
//   stories: playbook Produto thread 04 (LabelsController@show · Blade → Inertia)
//   permissao: print_labels.access
//
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/produto-acoes.jsx → Etiquetas()
// Contrato: governance/design/contracts/produto-etiquetas.contract.json
//
// ⛔ Regra mestre de valor: esta tela NÃO calcula preço. Cada linha chega do servidor com o texto do
// preço que preview() imprimiria, por grupo e por tipo (LabelsController::linhaEtiqueta). "Imprimir"
// abre /labels/preview com os MESMOS parâmetros que o formulário da Blade enviava.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useMemo, useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import { Grid, Inline, Stack } from '@/Components/layout';

type Tipo = 'inclusive' | 'exclusive';
interface Linha {
  product_id: number; variation_id: number; nome: string; variacao: string | null; sku: string;
  precos: Record<string, Record<Tipo, string | null>>;
  qtd?: number; lote?: string; validade?: string;
}
interface Item extends Linha { qtdTxt: string; loteTxt: string; validadeTxt: string; embalagem: string; grupo: string }
interface Modelo { id: number; nome: string; colunas: number; por_folha: number; largura: number; altura: number; padrao: boolean }
interface Props {
  linhas: Linha[]; grupos: { id: number; nome: string }[]; modelos: Modelo[];
  negocio: string; usa_lote: boolean; usa_validade: boolean;
}
interface Achado { id: number; text: string; product_id: number; variation_id: number }

const CAMPOS = [
  { id: 'name', l: 'Nome do produto', size: 15 },
  { id: 'variations', l: 'Variação', size: 17 },
  { id: 'price', l: 'Preço de venda', size: 17 },
  { id: 'business_name', l: 'Nome do negócio', size: 20 },
  { id: 'packing_date', l: 'Data de embalagem', size: 12 },
  { id: 'lot_number', l: 'Lote', size: 12 },
  { id: 'exp_date', l: 'Validade', size: 12 },
] as const;
type CampoId = (typeof CAMPOS)[number]['id'];
const XHR = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const num = (n: number) => n.toLocaleString('pt-BR', { maximumFractionDigits: 2 });
const item = (l: Linha): Item => ({
  ...l, qtdTxt: String(l.qtd ?? 1), loteTxt: l.lote ?? '', validadeTxt: l.validade ?? '', embalagem: '', grupo: '0',
});

export default function ProdutoEtiquetasIndex({ linhas, grupos, modelos, negocio, usa_lote, usa_validade }: Props) {
  const [itens, setItens] = useState<Item[]>(() => linhas.map(item));
  const [busca, setBusca] = useState('');
  const [achados, setAchados] = useState<Achado[]>([]);
  const [campos, setCampos] = useState(() => Object.fromEntries(CAMPOS.map((c) => [c.id,
    { on: ['name', 'variations', 'price', 'business_name'].includes(c.id), size: String(c.size) }])) as Record<CampoId, { on: boolean; size: string }>);
  const [tipo, setTipo] = useState<Tipo>('inclusive');
  const [modeloId, setModeloId] = useState(String((modelos.find((m) => m.padrao) ?? modelos[0])?.id ?? ''));
  const [folhaAtual, setFolhaAtual] = useState(1);

  const visiveis = CAMPOS.filter((c) => (c.id !== 'lot_number' || usa_lote) && (c.id !== 'exp_date' || usa_validade));
  const M = modelos.find((m) => String(m.id) === modeloId);
  const porFolha = M?.por_folha ?? 1;
  const setItem = (i: number, k: keyof Item, v: string) => setItens((s) => s.map((x, j) => (j === i ? { ...x, [k]: v } : x)));

  const buscar = async (termo: string) => {
    setBusca(termo);
    if (termo.trim().length < 2) { setAchados([]); return; }
    const r = await fetch(`/purchases/get_products?check_enable_stock=false&term=${encodeURIComponent(termo)}`, { headers: XHR })
      .then((x) => x.json() as Promise<Achado[]>).catch(() => []);
    setAchados(r.slice(0, 8));
  };
  const adicionar = async (a: Achado) => {
    setBusca(''); setAchados([]);
    const q = new URLSearchParams({ product_id: String(a.product_id), variation_id: String(a.variation_id), row_count: '0' });
    const r = await fetch(`/labels/add-product-row?${q}`, { headers: XHR })
      .then((x) => x.json() as Promise<{ linhas: Linha[] }>).catch(() => ({ linhas: [] }));
    setItens((s) => [...s, ...r.linhas.filter((l) => !s.some((x) => x.variation_id === l.variation_id)).map(item)]);
  };

  const folha = useMemo(() => itens.flatMap((it) => Array.from({ length: Math.min(Math.max(parseInt(it.qtdTxt, 10) || 0, 0), 500) }, () => it)), [itens]);
  const folhas = Math.max(1, Math.ceil(folha.length / porFolha));
  const pagina = Math.min(folhaAtual, folhas);
  const naFolha = folha.slice((pagina - 1) * porFolha, pagina * porFolha);

  // Mesmos nomes que o formulário da Blade serializava para /labels/preview.
  const imprimir = () => {
    const q = new URLSearchParams();
    itens.forEach((it, i) => {
      const p = `products[${i}]`;
      q.append(`${p}[product_id]`, String(it.product_id));
      q.append(`${p}[variation_id]`, String(it.variation_id));
      q.append(`${p}[quantity]`, it.qtdTxt);
      if (usa_lote) q.append(`${p}[lot_number]`, it.loteTxt);
      if (usa_validade) q.append(`${p}[exp_date]`, it.validadeTxt);
      q.append(`${p}[packing_date]`, it.embalagem);
      q.append(`${p}[price_group_id]`, it.grupo === '0' ? '' : it.grupo);
    });
    visiveis.forEach((c) => {
      if (campos[c.id].on) q.append(`print[${c.id}]`, '1');
      q.append(`print[${c.id}_size]`, campos[c.id].size);
    });
    q.append('print[price_type]', tipo);
    q.append('barcode_setting', modeloId);
    window.open(`/labels/preview?${q}`, 'newwindow');
  };

  const th = 'border-b border-border py-2 pr-3 text-left text-xs font-medium text-muted-foreground';
  return (
    <Stack gap={4}>
      <div data-contract="produto-etiquetas-header">
        <PageHeader title="Imprimir etiquetas" subtitle="Monte a folha, confira a prévia e imprima." />
      </div>

      <Card data-contract="produto-etiquetas-produtos">
        <CardContent className="p-4">
          <Stack gap={3}>
            <Inline gap={2} className="justify-between">
              <h2 className="text-sm font-medium">Produtos para etiquetar</h2>
              <span className="text-xs text-muted-foreground">{folha.length} etiqueta(s) · {folhas} folha(s)</span>
            </Inline>
            <Stack gap={1} className="text-sm">
              <Label htmlFor="etq-busca">Buscar produto</Label>
              <Input id="etq-busca" value={busca} placeholder="Digite o nome do produto para imprimir etiquetas" onChange={(e) => void buscar(e.target.value)} />
              <span className="text-xs text-muted-foreground">Nome ou SKU — o produto entra na lista abaixo.</span>
            </Stack>
            {achados.length > 0 && (
              <Inline gap={2} wrap>
                {achados.map((a) => <Button key={a.id} size="sm" variant="outline" onClick={() => void adicionar(a)}>+ {a.text}</Button>)}
              </Inline>
            )}
            {itens.length === 0 ? (
              <div className="rounded-md border border-dashed border-border p-4 text-sm">
                <b>Nenhum produto na folha</b>
                <p className="text-muted-foreground">Busque acima pelo nome ou SKU. Da compra ou do produto, a ação Etiquetas já traz a linha montada.</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[900px] text-sm">
                  <thead><tr>
                    {['Produto', 'Nº de etiquetas', ...(usa_lote ? ['Lote'] : []), ...(usa_validade ? ['Validade'] : []), 'Data de embalagem', 'Grupo de preço', '']
                      .map((c) => <th key={c} className={th}>{c}</th>)}
                  </tr></thead>
                  <tbody>
                    {itens.map((it, i) => (
                      <tr key={it.variation_id} className="border-b border-border">
                        <td className="py-2 pr-3">{it.nome}{it.variacao && <b> {it.variacao}</b>}<div className="font-mono text-xs text-muted-foreground">{it.sku}</div></td>
                        <td className="py-2 pr-3"><Input type="number" min={1} value={it.qtdTxt} aria-label="Nº de etiquetas" onChange={(e) => setItem(i, 'qtdTxt', e.target.value)} /></td>
                        {usa_lote && <td className="py-2 pr-3"><Input value={it.loteTxt} placeholder="—" aria-label="Lote" onChange={(e) => setItem(i, 'loteTxt', e.target.value)} /></td>}
                        {usa_validade && <td className="py-2 pr-3"><Input value={it.validadeTxt} placeholder="dd/mm/aaaa" aria-label="Validade" onChange={(e) => setItem(i, 'validadeTxt', e.target.value)} /></td>}
                        <td className="py-2 pr-3"><Input value={it.embalagem} placeholder="dd/mm/aaaa" aria-label="Data de embalagem" onChange={(e) => setItem(i, 'embalagem', e.target.value)} /></td>
                        <td className="py-2 pr-3">
                          <Select value={it.grupo} onValueChange={(v) => setItem(i, 'grupo', v)}>
                            <SelectTrigger aria-label="Grupo de preço"><SelectValue /></SelectTrigger>
                            <SelectContent>
                              <SelectItem value="0">Nenhum</SelectItem>
                              {grupos.map((g) => <SelectItem key={g.id} value={String(g.id)}>{g.nome}</SelectItem>)}
                            </SelectContent>
                          </Select>
                        </td>
                        <td className="py-2"><Button size="sm" variant="ghost" aria-label={`Remover ${it.nome}`} onClick={() => setItens((s) => s.filter((_, j) => j !== i))}>✕</Button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Stack>
        </CardContent>
      </Card>

      <Card data-contract="produto-etiquetas-campos">
        <CardContent className="p-4">
          <Stack gap={3}>
            <Inline gap={2} className="justify-between">
              <h2 className="text-sm font-medium">Informações na etiqueta</h2>
              <span className="text-xs text-muted-foreground">{visiveis.filter((c) => campos[c.id].on).length} de {visiveis.length}</span>
            </Inline>
            <Grid fit="sm" gap={3}>
              {visiveis.map((c) => (
                <Stack key={c.id} gap={1}>
                  <Inline gap={2} align="center" className="text-sm">
                    <Checkbox id={`etq-${c.id}`} checked={campos[c.id].on} onCheckedChange={(v) => setCampos((s) => ({ ...s, [c.id]: { ...s[c.id], on: v === true } }))} />
                    <Label htmlFor={`etq-${c.id}`}>{c.l}</Label>
                  </Inline>
                  <Stack gap={1} className="text-xs">
                    <Label htmlFor={`etq-${c.id}-corpo`}>Corpo (pt)</Label>
                    <Input id={`etq-${c.id}-corpo`} value={campos[c.id].size} disabled={!campos[c.id].on} aria-label={`Corpo de ${c.l}`}
                      onChange={(e) => setCampos((s) => ({ ...s, [c.id]: { ...s[c.id], size: e.target.value } }))} />
                  </Stack>
                </Stack>
              ))}
            </Grid>
            <Grid fit="md" gap={3}>
              <Stack gap={1} className="text-sm">
                <Label htmlFor="etq-tipo">Preço a imprimir</Label>
                <Select value={tipo} onValueChange={(v) => setTipo(v as Tipo)}>
                  <SelectTrigger id="etq-tipo"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="inclusive">Com imposto</SelectItem>
                    <SelectItem value="exclusive">Sem imposto</SelectItem>
                  </SelectContent>
                </Select>
                <span className="text-xs text-muted-foreground">Como o preço sai na etiqueta do balcão.</span>
              </Stack>
              <Stack gap={1} className="text-sm">
                <Label htmlFor="etq-modelo">Modelo de etiqueta</Label>
                <Select value={modeloId} onValueChange={(v) => { setModeloId(v); setFolhaAtual(1); }}>
                  <SelectTrigger id="etq-modelo"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {modelos.map((m) => <SelectItem key={m.id} value={String(m.id)}>{m.nome}</SelectItem>)}
                  </SelectContent>
                </Select>
              </Stack>
            </Grid>
            <div><Button disabled={!folha.length || !M} onClick={imprimir}>Imprimir {folhas} folha(s)</Button></div>
          </Stack>
        </CardContent>
      </Card>

      <Card data-contract="produto-etiquetas-previa">
        <CardContent className="p-4">
          <Stack gap={3}>
            <Inline gap={2} className="justify-between">
              <h2 className="text-sm font-medium">Prévia da folha</h2>
              {M && <span className="text-xs text-muted-foreground">{num(M.largura)} × {num(M.altura)} cm · folha {pagina} de {folhas}</span>}
            </Inline>
            {folha.length === 0 ? (
              <p className="text-sm text-muted-foreground">Sem produtos na lista — nada a imprimir.</p>
            ) : (
              <>
                <Grid gap={2} style={{ gridTemplateColumns: `repeat(${M?.colunas ?? 1}, minmax(0, 1fr))` }}>
                  {naFolha.map((it, k) => (
                    <Stack key={`${it.variation_id}-${k}`} gap={0} align="center" className="rounded border border-border bg-white p-2 text-center text-[11px] text-black">
                      {campos.business_name.on && <b className="text-[9px] uppercase tracking-wide">{negocio}</b>}
                      {campos.name.on && it.nome.length <= 46 && <span>{it.nome}</span>}
                      {campos.variations.on && it.variacao && <b>{it.variacao}</b>}
                      {campos.price.on && <span>{it.precos[it.grupo]?.[tipo] ?? 'sem preço no grupo'}</span>}
                      <Inline gap={0} align="stretch" aria-hidden="true" className="h-6">{Array.from({ length: 28 }, (_, b) => <i key={b} className="mr-px bg-black" style={{ width: (b * 7 + it.variation_id) % 3 === 0 ? 3 : 1 }} />)}</Inline>
                      <span className="font-mono">{it.sku}</span>
                    </Stack>
                  ))}
                </Grid>
                {folhas > 1 && (
                  <Inline gap={2}>
                    <Button size="sm" variant="outline" disabled={pagina <= 1} onClick={() => setFolhaAtual(pagina - 1)}>Folha anterior</Button>
                    <Button size="sm" variant="outline" disabled={pagina >= folhas} onClick={() => setFolhaAtual(pagina + 1)}>Próxima folha</Button>
                    <span className="text-xs text-muted-foreground">{naFolha.length} nesta folha · {folha.length} no total</span>
                  </Inline>
                )}
              </>
            )}
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}

ProdutoEtiquetasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
