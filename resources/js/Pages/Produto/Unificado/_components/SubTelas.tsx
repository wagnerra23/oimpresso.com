/**
 * Sub-telas secundárias do catálogo: Categorias, Insumos·BOM, Tabelas de preço e Histórico
 * de uso.
 *
 * Elas eram as ABAS da tela até 2026-08-18. O handoff "Consulta de Produtos" dá a barra de
 * abas pro recorte por TIPO do item, então estas quatro saíram de lá e passaram pro menu de
 * ações do cabeçalho — continuam servidas pelo mesmo controller, com os mesmos gates. Nada
 * que funcionava deixou de funcionar; mudou por onde se chega.
 *
 * O conteúdo é o que já estava em produção, movido sem alteração de regra.
 */

import { useState } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Grid } from '@/Components/layout';
import { brl, pct, type Permissoes, type ProdutoRow } from './catalogo';

export type CategoriaRow = { id: number; slug: string; label: string; count: number };
export type InsumoRow = { id: number; name: string; unit: string; cost?: number; stock: number; fornecedor: string | null };
export type TabelaRow = { id: string; label: string; desc: string; mult: number };
export type HistoricoRow = {
  os: string; date: string; prodId: string; prodName: string;
  cat: string | null; unit: string; client: string | null; qty: number; value?: number;
};

/**
 * Sub-tela que o usuário não pode ver. Diz QUE não pode e POR QUÊ — tabela vazia sem
 * explicação faz o operador achar que o cadastro está vazio e abrir chamado.
 */
export function SubTelaSemPermissao({ texto }: { texto: string }) {
  return (
    <div className="rounded-md bg-card border border-border p-6">
      <div className="text-[13px] font-medium text-foreground">Você não tem acesso a esta informação</div>
      <p className="mt-1.5 text-[12.5px] text-muted-foreground max-w-2xl">{texto}</p>
      <p className="mt-2 text-[11.5px] text-muted-foreground">Peça ao administrador do negócio pra revisar as permissões do seu papel.</p>
    </div>
  );
}

export function ListaCategorias({ rows }: { rows: CategoriaRow[] }) {
  return (
    <Grid min="sm" gap={3}>
      {rows.map((c) => (
        <div key={c.id} className="p-4 rounded-md bg-card border border-border">
          <div className="text-[14px] font-semibold">{c.label}</div>
          <div className="mt-1 text-[12px] text-muted-foreground">
            {c.count} {c.count === 1 ? 'produto' : 'produtos'}
          </div>
        </div>
      ))}
    </Grid>
  );
}

export function ListaInsumos({ rows, perm }: { rows: InsumoRow[]; perm: Permissoes }) {
  // UC-PUNI-04 — sem módulo Manufacturing no pacote + `manufacturing.access_recipe`, o backend
  // devolve `[]`. Sem esta mensagem a sub-tela pareceria um catálogo de insumos vazio.
  if (!perm.composicao) {
    return <SubTelaSemPermissao texto="A composição (insumos e BOM) depende do módulo de Produção estar no plano do negócio e da permissão de acessar receitas." />;
  }

  return (
    <div className="rounded-md bg-card border border-border shadow-sm overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">
          <tr className="border-b border-border bg-muted/40">
            <th scope="col" className="pl-6 py-2">Insumo</th>
            <th scope="col" className="py-2 w-20">Unid.</th>
            {perm.custo && <th scope="col" className="py-2 w-28 text-right">Custo</th>}
            <th scope="col" className="py-2 w-24 text-right">Estoque</th>
            <th scope="col" className="pr-6 py-2 w-44">Fornecedor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((i) => (
            <tr key={i.id} className="border-b border-border/60" style={{ height: 40 }}>
              <td className="pl-6 text-[13px] font-medium">{i.name}</td>
              <td className="text-[12px] text-muted-foreground">{i.unit}</td>
              {perm.custo && (
                <td className="text-[12.5px] text-right tabular-nums">
                  {i.cost !== undefined ? brl(i.cost) : null}
                </td>
              )}
              <td className="text-[12.5px] text-right tabular-nums">{i.stock}</td>
              <td className="pr-6 text-[12px] text-muted-foreground truncate">{i.fornecedor ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function ListaTabelas({ rows, produtos, perm }: { rows: TabelaRow[]; produtos: ProdutoRow[]; perm: Permissoes }) {
  // Hooks antes de qualquer return condicional (Rules of Hooks).
  const [tableId, setTableId] = useState(rows[0]?.id ?? '');
  const cur = rows.find((t) => t.id === tableId);

  // UC-PUNI-03 — tabela de preço É preço de venda agrupado: mesmo dado, mesmo gate. O backend
  // devolve `[]`; sem esta mensagem a sub-tela pareceria "nenhuma tabela cadastrada".
  if (!perm.preco) {
    return <SubTelaSemPermissao texto="As tabelas de preço mostram o preço de venda agrupado por perfil de cliente — elas seguem a mesma permissão de ver preço de venda." />;
  }

  return (
    <div className="space-y-4">
      <Grid min="sm" gap={3}>
        {rows.map((t) => {
          const active = t.id === tableId;
          return (
            <button
              key={t.id}
              type="button"
              aria-pressed={active}
              onClick={() => setTableId(t.id)}
              className={`text-left p-4 rounded-md border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${active ? 'bg-primary text-primary-foreground border-primary' : 'bg-card border-border hover:bg-muted/60'}`}
            >
              <div className={`text-[10px] uppercase tracking-widest ${active ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>Tabela</div>
              <div className="mt-1 text-[16px] font-semibold">{t.label}</div>
              <div className={`mt-1.5 text-[12px] ${active ? 'text-primary-foreground/80' : 'text-muted-foreground'}`}>{t.desc}</div>
              <div className="mt-3 text-[20px] font-semibold tabular-nums">{Math.round(t.mult * 100)}%</div>
            </button>
          );
        })}
      </Grid>
      {cur && (
        <div className="rounded-md bg-card border border-border overflow-hidden">
          <table className="w-full text-left">
            <thead className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">
              <tr className="border-b border-border bg-muted/40">
                <th scope="col" className="pl-6 py-2 w-24">Referência</th>
                <th scope="col" className="py-2">Produto</th>
                <th scope="col" className="py-2 w-28 text-right">Balcão</th>
                <th scope="col" className="py-2 w-28 text-right">Esta tabela</th>
                {/* Margem = (preço da tabela − custo) / preço da tabela. Sem direito ao custo,
                    a coluna some — calcular com `?? 0` imprimiria 100% e afirmaria custo zero. */}
                {perm.custo && <th scope="col" className="pr-6 py-2 w-24 text-right">Margem</th>}
              </tr>
            </thead>
            <tbody>
              {produtos.filter((p) => p.active).map((p) => {
                const base = p.price ?? 0;
                const tab = base * cur.mult;
                const m = p.cost !== undefined && tab > 0 ? (tab - p.cost) / tab : undefined;
                return (
                  <tr key={p.id} className="border-b border-border/60" style={{ height: 40 }}>
                    <td className="pl-6 font-mono text-[11.5px] text-muted-foreground">{p.referencia ?? '—'}</td>
                    <td className="text-[13px] font-medium">{p.name}</td>
                    <td className="text-[12.5px] text-right text-muted-foreground tabular-nums">{p.price !== undefined ? brl(p.price) : null}</td>
                    <td className="text-[13px] text-right font-semibold tabular-nums">{p.price !== undefined ? brl(tab) : null}</td>
                    {perm.custo && (
                      <td className={`pr-6 text-[12.5px] text-right tabular-nums ${m === undefined ? '' : m >= 0.4 ? 'text-success-fg' : m >= 0.15 ? 'text-foreground' : 'text-destructive-fg'}`}>
                        {m !== undefined ? pct(m) : null}
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export function ListaHistorico({ rows, perm }: { rows: HistoricoRow[]; perm: Permissoes }) {
  return (
    <div className="rounded-md bg-card border border-border overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">
          <tr className="border-b border-border bg-muted/40">
            <th scope="col" className="pl-6 py-2 w-24">Data</th>
            <th scope="col" className="py-2 w-24">OS</th>
            <th scope="col" className="py-2">Produto</th>
            <th scope="col" className="py-2 w-44">Cliente</th>
            <th scope="col" className="py-2 w-16 text-right">Qtd</th>
            {/* UC-PUNI-02b — `value` é qty × preço unitário: a porta lateral do preço de venda. */}
            {perm.preco && <th scope="col" className="pr-6 py-2 w-28 text-right">Valor</th>}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, idx) => (
            <tr key={r.os + r.prodId + idx} className="border-b border-border/60" style={{ height: 36 }}>
              <td className="pl-6 text-[12px] tabular-nums">{r.date}</td>
              <td>
                <Badge variant="secondary" className="font-mono text-[11px] font-normal">{r.os}</Badge>
              </td>
              <td className="text-[12.5px] font-medium">{r.prodName}</td>
              <td className="text-[12px] text-muted-foreground">{r.client ?? '—'}</td>
              <td className="text-[12.5px] text-right tabular-nums">{r.qty}</td>
              {perm.preco && (
                <td className="pr-6 text-[12.5px] text-right font-medium tabular-nums">
                  {r.value !== undefined ? brl(r.value) : null}
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
