// Linhas de produto dos formulários de estoque (transferência e ajuste).
//
// Protótipo: `prototipo-ui/cowork/Wagner/estoque-forms.jsx` — BuscaProduto + LinhasItens.
// A busca é a mesma da venda (`/products/list` no local escolhido, igual à Blade
// `stock_transfer.js`); escolhida a variação, a linha vem de `/stock-transfers/produto-linha`
// (saldo naquele local, custo, unidades e lotes). Regras e envio em `linhas-estoque-dominio.ts`.

import { useState } from 'react';
import { Trash2, AlertCircle } from 'lucide-react';
import ProductSearchAutocomplete from '@/Pages/Sells/_components/ProductSearchAutocomplete';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import {
  type LinhaEstoque,
  CASAS_CUSTO_ESTOQUE,
  CASAS_QTD_ESTOQUE,
  buscarLinha,
  erroDaLinha,
  jaTem,
  linhaNova,
  subtotal,
  trocarUnidade,
} from './linhas-estoque-dominio';

// Sentinelas: o Select do DS (Radix) não aceita `value=""`; os ids reais são numéricos.
const BASE = 'base';
const SEM_LOTE = 'sem-lote';

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

interface Props {
  /** Local de onde o material sai. Sem ele a busca fica desligada. */
  localId: number | null;
  linhas: LinhaEstoque[];
  onChange: (linhas: LinhaEstoque[]) => void;
  verCusto: boolean;
  editarCusto: boolean;
  /** Mostra a escolha de unidade (a transferência tem; o ajuste não tinha na Blade). */
  comUnidade?: boolean;
}

export default function LinhasDeEstoque({
  localId,
  linhas,
  onChange,
  verCusto,
  editarCusto,
  comUnidade = false,
}: Props) {
  const [aviso, setAviso] = useState<string | null>(null);
  const [buscando, setBuscando] = useState(false);

  const adicionar = async (variationId: number) => {
    setAviso(null);
    if (!localId) return;
    if (jaTem(linhas, variationId)) {
      setAviso('Este produto já está na lista — ajuste a quantidade da linha.');
      return;
    }
    setBuscando(true);
    const dados = await buscarLinha(variationId, localId).catch(() => null);
    setBuscando(false);
    if (!dados) {
      setAviso('Não foi possível trazer o produto deste local.');
      return;
    }
    if (dados.enable_stock === 1 && dados.qty_available <= 0) {
      setAviso(`${dados.nome} está sem saldo neste local.`);
      return;
    }
    onChange([...linhas, linhaNova(dados)]);
  };

  const alterar = (idx: number, nova: LinhaEstoque) => onChange(linhas.map((l, i) => (i === idx ? nova : l)));
  const remover = (idx: number) => onChange(linhas.filter((_, i) => i !== idx));

  const th = 'px-2 py-2 font-medium text-[11px] uppercase tracking-wide text-muted-foreground';

  return (
    <div>
      <ProductSearchAutocomplete
        locationId={localId}
        disabled={!localId || buscando}
        onSelect={(p) => void adicionar(p.variation_id)}
        placeholder={localId ? 'Buscar produto por nome, SKU ou código de barras…' : 'Escolha primeiro o local de origem'}
      />
      {aviso && (
        <Inline gap={1} role="status" className="mt-2 text-[12px] text-warning-fg">
          <AlertCircle className="h-3.5 w-3.5" aria-hidden="true" /> {aviso}
        </Inline>
      )}

      {linhas.length === 0 ? (
        <p className="py-8 text-center text-sm text-muted-foreground">Nenhum produto adicionado.</p>
      ) : (
        <table className="mt-3 w-full border-collapse">
          <thead>
            <tr className="border-b border-border">
              <th className={`${th} text-left`}>Produto</th>
              <th className={`${th} w-28 text-right`}>Quantidade</th>
              {verCusto && <th className={`${th} w-28 text-right`}>Custo unit.</th>}
              {verCusto && <th className={`${th} w-28 text-right`}>Subtotal</th>}
              <th className={`${th} w-10`}><span className="sr-only">Remover</span></th>
            </tr>
          </thead>
          <tbody>
            {linhas.map((l, idx) => {
              const erro = erroDaLinha(l);
              const usaLote = (l.dados.usa_lote || l.dados.usa_validade) && l.dados.lotes.length > 0;
              return (
                <tr key={l.dados.variation_id} className="border-b border-border align-top text-[13px]">
                  <td className="px-2 py-2">
                    <div className="font-medium">{l.dados.nome}</div>
                    <div className="text-[12px] text-muted-foreground">
                      {l.dados.sub_sku}
                      {l.dados.enable_stock === 1 && <> · saldo {l.dados.qty_available.toLocaleString('pt-BR')} {l.dados.unidade}</>}
                    </div>
                    {comUnidade && l.dados.sub_unidades.length > 1 && (
                      <Select
                        value={l.sub_unit_id === null ? BASE : String(l.sub_unit_id)}
                        onValueChange={(v) => alterar(idx, trocarUnidade(l, v === BASE ? null : Number(v)))}
                      >
                        <SelectTrigger aria-label={`Unidade de ${l.dados.nome}`} className="mt-1 h-8 w-auto min-w-28 text-[12.5px]">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value={BASE}>{l.dados.unidade}</SelectItem>
                          {l.dados.sub_unidades
                            .filter((u) => u.id !== l.dados.unit_id)
                            .map((u) => <SelectItem key={u.id} value={String(u.id)}>{u.nome}</SelectItem>)}
                        </SelectContent>
                      </Select>
                    )}
                    {usaLote && (
                      <Select
                        value={l.lot_no_line_id === null ? SEM_LOTE : String(l.lot_no_line_id)}
                        onValueChange={(v) => alterar(idx, { ...l, lot_no_line_id: v === SEM_LOTE ? null : Number(v) })}
                      >
                        <SelectTrigger aria-label={`Lote de ${l.dados.nome}`} className="mt-1 h-8 w-auto min-w-40 text-[12.5px]">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value={SEM_LOTE}>Lote / validade</SelectItem>
                          {l.dados.lotes.map((lt) => (
                            <SelectItem key={lt.purchase_line_id} value={String(lt.purchase_line_id)}>
                              {[lt.lot_number, lt.exp_date && `val. ${lt.exp_date}`].filter(Boolean).join(' · ') || `#${lt.purchase_line_id}`}
                              {` (${lt.qty_available.toLocaleString('pt-BR')})`}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    )}
                    {erro && <p className="mt-1 text-[12px] text-destructive-fg">{erro}</p>}
                  </td>
                  <td className="px-2 py-2 text-right">
                    <NumericInputPtBR
                      aria-label={`Quantidade de ${l.dados.nome}`}
                      aria-invalid={erro ? true : undefined}
                      className="h-8 text-right text-[12.5px] tabular-nums"
                      precision={l.dados.permite_decimal ? CASAS_QTD_ESTOQUE : 0}
                      value={l.quantidade}
                      onChange={(n) => alterar(idx, { ...l, quantidade: n })}
                    />
                  </td>
                  {verCusto && (
                    <td className="px-2 py-2 text-right">
                      <NumericInputPtBR
                        aria-label={`Custo unitário de ${l.dados.nome}`}
                        className="h-8 text-right text-[12.5px] tabular-nums"
                        precision={CASAS_CUSTO_ESTOQUE}
                        readOnly={!editarCusto}
                        value={l.custo}
                        onChange={(n) => alterar(idx, { ...l, custo: n })}
                      />
                    </td>
                  )}
                  {verCusto && <td className="px-2 py-2 text-right tabular-nums">{brl(subtotal(l))}</td>}
                  <td className="px-2 py-2 text-right">
                    <Button type="button" size="sm" variant="ghost" className="h-8 w-8 p-0 text-destructive" onClick={() => remover(idx)} aria-label={`Remover ${l.dados.nome}`}>
                      <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                    </Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}
    </div>
  );
}
