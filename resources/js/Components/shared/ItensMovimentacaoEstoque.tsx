// Itens de uma movimentação de estoque (transferência e ajuste) — busca de produto + linhas, com as
// MESMAS regras das telas Blade (`public/js/stock_transfer.js`, `stock_adjustment.js` e os
// `partials/product_table_row.blade.php` das duas):
//
//   - a busca só abre com a filial escolhida, e procura em `/products/list` (a mesma rota da
//     Blade) com o local;
//   - produto sem controle de estoque não aparece; produto sem saldo no local aparece
//     desabilitado ("sem estoque");
//   - ao escolher, a linha vem de `/stock-transfers/produto-linha` (os dados do `get_product_row`
//     que a Blade usa nas duas telas): saldo no local, unidade, subunidades, lotes e o último
//     custo de compra;
//   - a quantidade não passa do saldo do local (ou do lote), na unidade escolhida.
//
// O que muda entre as duas telas vem por prop: a transferência escolhe subunidade e aceita uma
// linha por produto (o banco tem chave única por transferência + variação); o ajuste não tem
// subunidade e aceita o mesmo produto em mais de uma linha (lotes diferentes).
//
// O que cada tela manda ao salvar está em `linhaParaTransferencia` e `linhaParaAjuste`: os campos
// da linha da Blade, com os números em `paraNumUf` (vírgula decimal, sem milhar) — é o formato que
// o `num_uf` do servidor lê sem ambiguidade (REGRA MESTRE de valor/estoque, memory/proibicoes.md).
//
// Mora em `Components/shared` porque duas telas consomem (regra-de-2): StockTransfer/Create e
// StockAdjustment/Create.

import { useEffect, useRef, useState } from 'react';
import { Loader2, Package, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Inline, Stack } from '@/Components/layout';
import { paraNumUf } from '@/Lib/numberPtBR';

interface SubUnidade {
  id: number;
  nome: string;
  multiplicador: number;
  permite_decimal: boolean;
}

interface Lote {
  purchase_line_id: number;
  lot_number: string | null;
  exp_date: string | null;
  qty_available: number;
}

/** Resposta de GET /stock-transfers/produto-linha. */
interface DadosDaLinha {
  product_id: number;
  variation_id: number;
  nome: string;
  sub_sku: string;
  enable_stock: number;
  qty_available: number;
  unidade: string;
  unit_id: number;
  permite_decimal: boolean;
  custo: number;
  sub_unidades: SubUnidade[];
  usa_lote: boolean;
  usa_validade: boolean;
  lotes: Lote[];
}

export interface LinhaDeEstoque extends DadosDaLinha {
  chave: string;
  sub_unit_id: number | null;
  lot_no_line_id: number | null;
  quantity: number;
  unit_price: number;
}

/** Item de /products/list (ProductUtil::filterProduct). */
interface ResultadoBusca {
  product_id: number;
  name: string;
  type: string;
  enable_stock: number;
  variation_id: number;
  variation: string;
  qty_available: number | string | null;
  sub_sku: string;
}

const CASAS_PRECO = 2;
const arredondar = (n: number, casas: number) => Number(n.toFixed(casas));

const subUnidadeDe = (l: LinhaDeEstoque) => l.sub_unidades.find((s) => s.id === l.sub_unit_id) ?? null;
export const multiplicadorDe = (l: LinhaDeEstoque) => subUnidadeDe(l)?.multiplicador ?? 1;
const permiteDecimal = (l: LinhaDeEstoque) => subUnidadeDe(l)?.permite_decimal ?? l.permite_decimal;
export const casasDaQuantidade = (l: LinhaDeEstoque) => (permiteDecimal(l) ? 2 : 0);

/** Quanto dá para transferir desta linha, na unidade escolhida (saldo do lote, se houver lote). */
export function maximoDaLinha(l: LinhaDeEstoque): number | null {
  if (l.enable_stock !== 1) return null;
  const lote = l.lotes.find((x) => x.purchase_line_id === l.lot_no_line_id);
  return (lote ? lote.qty_available : l.qty_available) / multiplicadorDe(l);
}

export const linhaInvalida = (l: LinhaDeEstoque): boolean => {
  const max = maximoDaLinha(l);
  return !(l.quantity > 0) || (max !== null && l.quantity > max + 1e-9);
};

/** A linha como o `store()` da transferência lê — os campos do `<tr>` da Blade. */
export function linhaParaTransferencia(l: LinhaDeEstoque): Record<string, string | number> {
  const envio: Record<string, string | number> = {
    product_id: l.product_id,
    variation_id: l.variation_id,
    enable_stock: l.enable_stock,
    quantity: paraNumUf(l.quantity, casasDaQuantidade(l)),
    base_unit_multiplier: multiplicadorDe(l),
    product_unit_id: l.unit_id,
    unit_price: paraNumUf(l.unit_price, CASAS_PRECO),
  };
  if (l.sub_unit_id !== null) envio.sub_unit_id = l.sub_unit_id;
  if (l.lot_no_line_id !== null) envio.lot_no_line_id = l.lot_no_line_id;
  return envio;
}

/** A linha como o `store()` do ajuste lê: sem subunidade, quantidade na unidade do produto. */
export function linhaParaAjuste(l: LinhaDeEstoque): Record<string, string | number> {
  const envio: Record<string, string | number> = {
    product_id: l.product_id,
    variation_id: l.variation_id,
    enable_stock: l.enable_stock,
    quantity: paraNumUf(l.quantity, casasDaQuantidade(l)),
    unit_price: paraNumUf(l.unit_price, CASAS_PRECO),
  };
  if (l.lot_no_line_id !== null) envio.lot_no_line_id = l.lot_no_line_id;
  return envio;
}

const rotuloDoResultado = (r: ResultadoBusca) =>
  `${r.name}${r.type === 'variable' ? `-${r.variation}` : ''} (${r.sub_sku})`;

const rotuloDoLote = (lote: Lote, l: LinhaDeEstoque) => {
  const partes: string[] = [];
  if (l.usa_lote && lote.lot_number) partes.push(lote.lot_number);
  if (l.usa_validade && lote.exp_date) {
    const [ano, mes, dia] = lote.exp_date.split('-');
    partes.push(`validade ${dia}/${mes}/${ano}`);
  }
  return partes.join(' · ') || `lote ${lote.purchase_line_id}`;
};

const qtd = (n: number) => n.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const cabecalhos = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

interface Props {
  /** Filial de onde o estoque sai — sem ela a busca fica fechada (o saldo é por local). */
  origemId: string;
  linhas: LinhaDeEstoque[];
  aoMudar: (linhas: LinhaDeEstoque[]) => void;
  verCusto: boolean;
  editarPreco: boolean;
  desabilitado?: boolean;
  /** Texto do campo de busca enquanto a filial não foi escolhida. */
  rotuloSemLocal?: string;
  /** Transferência: escolhe a subunidade (caixa, dúzia…). O ajuste da Blade não tem. */
  comSubUnidade?: boolean;
  /** Transferência: o banco só aceita uma linha por produto. O ajuste aceita repetir (lotes). */
  umaLinhaPorProduto?: boolean;
}

export default function ItensMovimentacaoEstoque({
  origemId,
  linhas,
  aoMudar,
  verCusto,
  editarPreco,
  desabilitado = false,
  rotuloSemLocal = 'Escolha a filial primeiro',
  comSubUnidade = true,
  umaLinhaPorProduto = true,
}: Props) {
  const [busca, setBusca] = useState('');
  const [resultados, setResultados] = useState<ResultadoBusca[]>([]);
  const [aberto, setAberto] = useState(false);
  const [carregando, setCarregando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const caixa = useRef<HTMLDivElement>(null);
  const linhasRef = useRef(linhas);
  linhasRef.current = linhas;

  const adicionar = async (variationId: number) => {
    setBusca('');
    setResultados([]);
    setAberto(false);
    setErro(null);
    // Na transferência o banco não aceita a mesma variação duas vezes
    // (`transaction_sell_lines.uk_tsl_dup_prevent`): em vez de falhar ao salvar, avisa aqui.
    if (umaLinhaPorProduto && linhasRef.current.some((l) => l.variation_id === variationId)) {
      setErro('Este produto já está na lista. Ajuste a quantidade na linha dele.');
      return;
    }
    setCarregando(true);
    try {
      const params = new URLSearchParams({ variation_id: String(variationId), location_id: origemId });
      const res = await fetch(`/stock-transfers/produto-linha?${params.toString()}`, { headers: cabecalhos, credentials: 'same-origin' });
      if (!res.ok) {
        setErro('Não foi possível carregar este produto nesta filial.');
        return;
      }
      const d = (await res.json()) as DadosDaLinha;
      const sub = comSubUnidade ? (d.sub_unidades[0] ?? null) : null;
      const multiplicador = sub?.multiplicador ?? 1;
      aoMudar([
        ...linhasRef.current,
        {
          ...d,
          chave: `${d.variation_id}-${Date.now()}`,
          sub_unit_id: sub?.id ?? null,
          lot_no_line_id: null,
          quantity: 1,
          // O campo de preço da Blade nasce com o último custo de compra × a unidade, em 2 casas.
          unit_price: arredondar(d.custo * multiplicador, CASAS_PRECO),
        },
      ]);
    } catch {
      setErro('Erro ao carregar o produto.');
    } finally {
      setCarregando(false);
    }
  };

  // Busca com espera de 250ms e cancelamento do pedido anterior.
  useEffect(() => {
    const termo = busca.trim();
    if (termo.length < 2 || !origemId) {
      setResultados([]);
      setAberto(false);
      return;
    }
    const controle = new AbortController();
    const espera = setTimeout(async () => {
      setCarregando(true);
      try {
        const params = new URLSearchParams({ term: termo, location_id: origemId });
        const res = await fetch(`/products/list?${params.toString()}`, { headers: cabecalhos, credentials: 'same-origin', signal: controle.signal });
        const dados = res.ok ? ((await res.json()) as ResultadoBusca[]) : [];
        // Como na Blade: produto sem controle de estoque não entra na lista da transferência.
        const lista = (Array.isArray(dados) ? dados : []).filter((r) => Number(r.enable_stock) === 1);
        // Um único resultado com saldo entra direto (leitor de código de barras).
        const unico = lista.length === 1 ? lista[0] : undefined;
        if (unico && Number(unico.qty_available) > 0) {
          void adicionar(unico.variation_id);
          return;
        }
        setResultados(lista.slice(0, 20));
        setAberto(true);
      } catch (e) {
        if ((e as Error).name !== 'AbortError') setResultados([]);
      } finally {
        if (!controle.signal.aborted) setCarregando(false);
      }
    }, 250);
    return () => {
      clearTimeout(espera);
      controle.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- `adicionar` lê as linhas por ref
  }, [busca, origemId]);

  useEffect(() => {
    const fora = (e: MouseEvent) => {
      if (caixa.current && !caixa.current.contains(e.target as Node)) setAberto(false);
    };
    document.addEventListener('mousedown', fora);
    return () => document.removeEventListener('mousedown', fora);
  }, []);

  const mudar = (chave: string, parte: Partial<LinhaDeEstoque>) =>
    aoMudar(linhas.map((l) => (l.chave === chave ? { ...l, ...parte } : l)));

  const trocarUnidade = (l: LinhaDeEstoque, id: number) => {
    const sub = l.sub_unidades.find((s) => s.id === id);
    if (!sub) return;
    // Como na Blade: trocar a unidade recalcula o preço pela unidade base × o multiplicador.
    mudar(l.chave, { sub_unit_id: id, unit_price: arredondar(l.custo * sub.multiplicador, CASAS_PRECO) });
  };

  return (
    <Stack gap={3}>
      <div ref={caixa} className="relative w-full">
        <Command shouldFilter={false} className="overflow-visible rounded-md border border-input bg-background">
          <div className="relative">
            <CommandInput
              value={busca}
              onValueChange={setBusca}
              onFocus={() => resultados.length > 0 && setAberto(true)}
              onKeyDown={(e) => {
                if (e.key === 'Escape') setAberto(false);
              }}
              placeholder={origemId ? 'Buscar produto por nome ou código…' : rotuloSemLocal}
              disabled={desabilitado || !origemId}
              aria-label="Buscar produto"
              className="pr-8 text-[13px]"
            />
            {carregando && <Loader2 className="absolute right-2 top-1/2 size-3.5 -translate-y-1/2 animate-spin text-muted-foreground" />}
          </div>
          {aberto && busca.trim().length >= 2 && (
            <CommandList className="absolute left-0 right-0 top-full z-50 mt-1 max-h-64 rounded-md border bg-popover shadow-md">
              {!carregando && <CommandEmpty>Nenhum produto encontrado para "{busca}".</CommandEmpty>}
              {resultados.map((r) => {
                const semSaldo = !(Number(r.qty_available) > 0);
                return (
                  <CommandItem
                    key={r.variation_id}
                    value={String(r.variation_id)}
                    disabled={semSaldo}
                    onSelect={() => void adicionar(r.variation_id)}
                    className="text-[13px]"
                  >
                    <span className="truncate">{rotuloDoResultado(r)}</span>
                    {semSaldo && <span className="ml-auto text-[11.5px] text-muted-foreground">sem estoque</span>}
                  </CommandItem>
                );
              })}
            </CommandList>
          )}
        </Command>
      </div>

      {erro && (
        <p role="alert" className="text-[12px] text-destructive">
          {erro}
        </p>
      )}

      {linhas.length === 0 ? (
        <div className="py-10 text-center">
          <Package className="mx-auto mb-2 h-10 w-10 text-muted-foreground/40" aria-hidden />
          <p className="text-sm text-muted-foreground">Nenhum item adicionado.</p>
        </div>
      ) : (
        <table className="w-full border-collapse" data-contract="estoque-itens">
          <thead>
            <tr className="border-b border-border text-[10px] uppercase tracking-widest text-muted-foreground">
              <th className="px-2 py-2 text-left font-medium">Produto</th>
              <th className="w-44 px-2 py-2 text-right font-medium">Quantidade</th>
              {verCusto && <th className="w-28 px-2 py-2 text-right font-medium">Custo unit.</th>}
              {verCusto && <th className="w-28 px-2 py-2 text-right font-medium">Subtotal</th>}
              <th className="w-10 px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {linhas.map((l) => {
              const max = maximoDaLinha(l);
              const acima = max !== null && l.quantity > max + 1e-9;
              const unidade = subUnidadeDe(l)?.nome ?? l.unidade;
              return (
                <tr key={l.chave} className="border-b border-border/60 align-top text-[13px]">
                  <td className="px-2 py-2">
                    <div className="font-medium">{l.nome}</div>
                    <div className="text-[11.5px] text-muted-foreground">
                      {l.sub_sku}
                      {l.enable_stock === 1 && (
                        <>
                          {' · '}Estoque atual: {qtd(l.qty_available)} {l.unidade}
                        </>
                      )}
                    </div>
                    {l.lotes.length > 0 && (
                      <Select
                        value={l.lot_no_line_id === null ? '__nenhum__' : String(l.lot_no_line_id)}
                        onValueChange={(v) => mudar(l.chave, { lot_no_line_id: v === '__nenhum__' ? null : Number(v) })}
                      >
                        <SelectTrigger aria-label={`Lote de ${l.nome}`} className="mt-1 h-8 w-full text-[12.5px]">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="__nenhum__">Lote e validade</SelectItem>
                          {l.lotes.map((lote) => (
                            <SelectItem key={lote.purchase_line_id} value={String(lote.purchase_line_id)}>
                              {rotuloDoLote(lote, l)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    )}
                  </td>
                  <td className="px-2 py-2">
                    <Inline gap={2} justify="end">
                      <NumericInputPtBR
                        value={l.quantity}
                        onChange={(n) => mudar(l.chave, { quantity: n })}
                        precision={casasDaQuantidade(l)}
                        aria-label={`Quantidade de ${l.nome}`}
                        aria-invalid={acima || !(l.quantity > 0)}
                        className="h-8 w-20 text-right text-[12.5px] tabular-nums"
                      />
                      {comSubUnidade && l.sub_unidades.length > 1 ? (
                        <Select value={String(l.sub_unit_id ?? '')} onValueChange={(v) => trocarUnidade(l, Number(v))}>
                          <SelectTrigger aria-label={`Unidade de ${l.nome}`} className="h-8 w-20 text-[12.5px]">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {l.sub_unidades.map((s) => (
                              <SelectItem key={s.id} value={String(s.id)}>
                                {s.nome}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      ) : (
                        <span className="text-[12px] text-muted-foreground">{unidade}</span>
                      )}
                    </Inline>
                    {acima && max !== null && (
                      <p role="alert" className="mt-1 text-right text-[11.5px] text-destructive">
                        Só há {qtd(max)} {unidade} disponíveis.
                      </p>
                    )}
                  </td>
                  {verCusto && (
                    <td className="px-2 py-2">
                      <NumericInputPtBR
                        value={l.unit_price}
                        onChange={(n) => mudar(l.chave, { unit_price: n })}
                        precision={CASAS_PRECO}
                        readOnly={!editarPreco}
                        aria-label={`Custo unitário de ${l.nome}`}
                        className="h-8 text-right text-[12.5px] tabular-nums"
                      />
                    </td>
                  )}
                  {verCusto && <td className="px-2 py-2 text-right tabular-nums">{brl(l.quantity * l.unit_price)}</td>}
                  <td className="px-2 py-2 text-right">
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      className="h-7 w-7 p-0 text-destructive"
                      onClick={() => aoMudar(linhas.filter((x) => x.chave !== l.chave))}
                      aria-label={`Remover ${l.nome}`}
                    >
                      <Trash2 className="h-3.5 w-3.5" aria-hidden />
                    </Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}
    </Stack>
  );
}
