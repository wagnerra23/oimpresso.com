// Painel de detalhe do produto — `DetalheDrawer` do protótipo (produto-blade.jsx) com o conteúdo da
// janela "Ver" da lista Blade. Os dados chegam prontos de ProductController::buildProdutoDetalhe, com os
// gates da Blade: chave de preço ausente = sem permissão (nunca zero).

import { useState } from 'react';
import { Pencil, Printer } from 'lucide-react';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import { brl, qtdComUnidade } from '../../Unificado/_components/catalogo';
import { TIPO_ROTULO, type DetalheProdutoDados } from './tipos';

type Aba = 'dados' | 'variacoes' | 'composicao' | 'estoque' | 'fiscal' | 'prateleira';

const qtd = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const dinheiro = (n: number | undefined | null) => (n === undefined || n === null ? '—' : brl(n));

function Dt({ rotulo, valor }: { rotulo: string; valor: string | null | undefined }) {
  return (
    <div className="pb-dt">
      <span>{rotulo}</span>
      <b>{valor ? valor : '—'}</b>
    </div>
  );
}

interface Props {
  aberto: boolean;
  detalhe: DetalheProdutoDados | null | undefined;
  aoFechar: () => void;
  podeEditar: boolean;
  podePrecos: boolean;
  irPara: (url: string) => void;
}

export default function DetalheProduto({ aberto, detalhe: d, aoFechar, podeEditar, podePrecos, irPara }: Props) {
  const [aba, setAba] = useState<Aba>('dados');
  const combo = d?.tipo === 'combo';
  const custo = (d?.variacoes ?? []).some((v) => v.compra_exc !== undefined);
  const venda = (d?.variacoes ?? []).some((v) => v.venda_exc !== undefined);
  const lucro = (d?.variacoes ?? []).some((v) => v.lucro_percentual !== undefined);
  const grupos = d?.variacoes[0]?.grupos ?? [];

  const abas = d
    ? [
        { key: 'dados', label: 'Dados', href: '#' },
        combo
          ? { key: 'composicao', label: 'Composição', href: '#', badge: d.composicao.length }
          : { key: 'variacoes', label: 'Variações', href: '#', badge: d.variacoes.length },
        ...(d.estoque !== null ? [{ key: 'estoque', label: 'Estoque', href: '#' }] : []),
        { key: 'fiscal', label: 'Fiscal', href: '#' },
        { key: 'prateleira', label: 'Prateleira', href: '#' },
      ]
    : [];
  const abaAtiva: Aba = abas.some((a) => a.key === aba) ? aba : 'dados';

  return (
    <Sheet open={aberto} onOpenChange={(v) => !v && aoFechar()}>
      {/* `pb-root` de novo: o conteúdo vai para um portal fora da tela e perderia o escopo do bundle
          (lápide §5 2026-07-10 — re-aplicar o wrapper no conteúdo do portal). */}
      <SheetContent side="right" className="pb-root flex w-[820px] max-w-[95vw] flex-col gap-0 p-0 sm:max-w-[820px]">
        {!d ? (
          <div className="space-y-3 p-6" aria-busy="true">
            <SheetTitle className="sr-only">Carregando produto</SheetTitle>
            <Skeleton className="h-6 w-2/3" />
            <Skeleton className="h-4 w-1/2" />
            <Skeleton className="h-40 w-full" />
          </div>
        ) : (
          <>
            <SheetHeader className="border-b border-[var(--border)] px-5 py-4">
              <div className="flex flex-wrap items-center gap-2">
                <span className="m font-mono text-[11.5px] text-[var(--text-mute)]">{d.sku}</span>
                <span className={`pb-pill ${d.tipo}`}>{TIPO_ROTULO[d.tipo] ?? d.tipo}</span>
                {d.inativo && <span className="pb-pill off">Inativo</span>}
              </div>
              <SheetTitle className="text-[17px]">{d.nome}</SheetTitle>
              <SheetDescription>
                {[d.dados.categoria, d.dados.subcategoria, d.dados.marca, d.dados.unidade && `unidade ${d.dados.unidade_curta || d.dados.unidade}`]
                  .filter(Boolean)
                  .join(' · ')}
              </SheetDescription>
            </SheetHeader>

            <div className="px-3">
              <PageHeaderTabs
                density="compact"
                activeGhostKey={abaAtiva}
                onGhostChange={(k) => setAba(k as Aba)}
                ghosts={abas}
              />
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-4" data-contract="produto-detalhe">
              {abaAtiva === 'dados' && (
                <div className="grid gap-4" style={{ gridTemplateColumns: d.imagem ? '1fr 168px' : '1fr' }}>
                  <div className="pb-drawer-grid">
                    <Dt rotulo="SKU" valor={d.sku} />
                    <Dt rotulo="Tipo de código de barras" valor={d.dados.codigo_de_barras} />
                    <Dt rotulo="Unidade" valor={d.dados.unidade} />
                    <Dt rotulo="Marca" valor={d.dados.marca} />
                    <Dt rotulo="Categoria" valor={d.dados.categoria} />
                    <Dt rotulo="Subcategoria" valor={d.dados.subcategoria} />
                    <Dt rotulo="Imposto aplicável" valor={d.dados.imposto || 'Nenhum'} />
                    <Dt
                      rotulo="Tipo de imposto na venda"
                      valor={d.dados.tipo_imposto === 'inclusive' ? 'Inclusivo' : d.dados.tipo_imposto === 'exclusive' ? 'Exclusivo' : ''}
                    />
                    <Dt rotulo="Peso" valor={d.dados.peso} />
                    <Dt rotulo="Gerenciar estoque" valor={d.dados.controla_estoque ? 'Sim' : 'Não'} />
                    <Dt rotulo="Quantidade de alerta" valor={d.dados.alerta === null ? '' : qtd(d.dados.alerta)} />
                    <Dt
                      rotulo="Expira em"
                      valor={
                        d.dados.validade
                          ? `${qtd(d.dados.validade.periodo)} ${d.dados.validade.tipo === 'days' ? 'dias' : 'meses'}`
                          : 'Não aplicável'
                      }
                    />
                    <Dt rotulo="Disponível nos locais" valor={d.dados.locais.join(', ') || 'Nenhum'} />
                    <Dt rotulo="Não para venda" valor={d.dados.nao_para_venda ? 'Sim' : 'Não'} />
                    <Dt rotulo="Garantia" valor={d.dados.garantia} />
                    {d.dados.tempo_preparo !== null && <Dt rotulo="Tempo de preparo" valor={`${d.dados.tempo_preparo} min`} />}
                    {d.dados.campos.map((c, i) => (
                      <Dt key={i} rotulo={c.rotulo || 'Campo personalizado'} valor={c.valor} />
                    ))}
                  </div>
                  {d.imagem && <img src={d.imagem} alt={`Imagem de ${d.nome}`} className="h-[168px] w-[168px] rounded-lg object-cover" />}
                  {d.dados.descricao && (
                    <p className="text-[12.5px] leading-relaxed text-[var(--text-dim)]" style={{ gridColumn: '1 / -1' }}>
                      {d.dados.descricao}
                    </p>
                  )}
                </div>
              )}

              {abaAtiva === 'variacoes' && (
                <div className="space-y-4">
                  <table className="pb-tbl">
                    <caption className="sr-only">Variações e preços</caption>
                    <thead>
                      <tr>
                        <th>Variação</th>
                        <th>SKU</th>
                        {custo && <th className="r">Compra (excl.)</th>}
                        {custo && <th className="r">Compra (incl.)</th>}
                        {lucro && <th className="r">% lucro</th>}
                        {venda && <th className="r">Venda (excl.)</th>}
                        {venda && <th className="r">Venda (incl.)</th>}
                      </tr>
                    </thead>
                    <tbody>
                      {d.variacoes.map((v) => (
                        <tr key={v.sku}>
                          <td>{v.nome || '—'}</td>
                          <td className="m">{v.sku}</td>
                          {custo && <td className="r">{dinheiro(v.compra_exc)}</td>}
                          {custo && <td className="r">{dinheiro(v.compra_inc)}</td>}
                          {lucro && <td className="r">{v.lucro_percentual === undefined ? '—' : `${qtd(v.lucro_percentual)} %`}</td>}
                          {venda && <td className="r">{dinheiro(v.venda_exc)}</td>}
                          {venda && <td className="r">{dinheiro(v.venda_inc)}</td>}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  {grupos.length > 0 && (
                    <table className="pb-tbl">
                      <caption className="sr-only">Preços por grupo</caption>
                      <thead>
                        <tr>
                          <th>Preço por grupo</th>
                          {grupos.map((g) => (
                            <th key={g.grupo} className="r">
                              {g.grupo}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {d.variacoes.map((v) => (
                          <tr key={v.sku}>
                            <td>{v.nome || v.sku}</td>
                            {v.grupos.map((g) => (
                              <td key={g.grupo} className="r">
                                {dinheiro(g.preco)}
                              </td>
                            ))}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}
                  {!custo && !venda && <p className="pb-help">Os preços aparecem para quem tem permissão de vê-los.</p>}
                </div>
              )}

              {abaAtiva === 'composicao' && (
                <table className="pb-tbl">
                  <caption className="sr-only">Composição</caption>
                  <thead>
                    <tr>
                      <th>Produto</th>
                      <th>SKU</th>
                      <th className="r">Qtd</th>
                      {custo && <th className="r">Compra (excl.)</th>}
                      {custo && <th className="r">Total (excl.)</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {d.composicao.map((c) => (
                      <tr key={c.sku}>
                        <td>
                          <b>{c.nome}</b>
                        </td>
                        <td className="m">{c.sku}</td>
                        <td className="r">{qtdComUnidade(c.quantidade, c.unidade)}</td>
                        {custo && <td className="r">{dinheiro(c.compra_exc)}</td>}
                        {custo && <td className="r">{dinheiro(c.total_exc)}</td>}
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}

              {abaAtiva === 'estoque' && d.estoque !== null && (
                <div className="space-y-3">
                  <table className="pb-tbl">
                    <caption className="sr-only">Estoque por local</caption>
                    <thead>
                      <tr>
                        <th>Local</th>
                        {d.tipo === 'variable' && <th>Variação</th>}
                        <th className="r">Saldo</th>
                        <th className="r">Alerta</th>
                        <th>Situação</th>
                      </tr>
                    </thead>
                    <tbody>
                      {d.estoque.map((e, i) => (
                        <tr key={`${e.sku}-${e.local}-${i}`}>
                          <td>{e.local || '—'}</td>
                          {d.tipo === 'variable' && <td>{e.variacao}</td>}
                          <td className="r">
                            <span className={`pb-stock${e.alerta ? ' low' : ''}`}>
                              {e.estoque === null ? '--' : qtdComUnidade(e.estoque, e.unidade)}
                            </span>
                          </td>
                          <td className="r">{d.dados.alerta === null ? '—' : qtd(d.dados.alerta)}</td>
                          <td>{e.alerta ? <span className="pb-pill off">Repor</span> : <span className="pb-pill combo">Suficiente</span>}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  <p className="pb-help">Saldo por local, o mesmo de Relatórios → Estoque. O histórico completo fica em Histórico de estoque.</p>
                  <Button variant="outline" size="sm" onClick={() => irPara(`/products/stock-history/${d.id}`)}>
                    Abrir histórico de estoque
                  </Button>
                </div>
              )}

              {abaAtiva === 'fiscal' && (
                <div className="pb-drawer-grid">
                  <Dt rotulo="NCM" valor={d.fiscal.ncm && d.fiscal.ncm !== '0' ? d.fiscal.ncm : ''} />
                  <Dt rotulo="CEST" valor={d.fiscal.cest} />
                  <Dt rotulo="CFOP dentro do estado" valor={d.fiscal.cfop_interno} />
                  <Dt rotulo="CFOP fora do estado" valor={d.fiscal.cfop_externo} />
                  <Dt rotulo="CST / CSOSN" valor={d.fiscal.cst_csosn} />
                  <Dt rotulo="CST PIS" valor={d.fiscal.cst_pis} />
                  <Dt rotulo="CST COFINS" valor={d.fiscal.cst_cofins} />
                  <Dt rotulo="CST IPI" valor={d.fiscal.cst_ipi} />
                  <Dt rotulo="Imposto aplicável" valor={d.dados.imposto || 'Nenhum'} />
                </div>
              )}

              {abaAtiva === 'prateleira' &&
                (d.prateleiras.linhas.length === 0 ? (
                  <p className="pb-help">Nenhuma prateleira cadastrada para este produto.</p>
                ) : (
                  <table className="pb-tbl">
                    <caption className="sr-only">Prateleiras por local</caption>
                    <thead>
                      <tr>
                        <th>Local</th>
                        {d.prateleiras.colunas.prateleira && <th>Prateleira</th>}
                        {d.prateleiras.colunas.fileira && <th>Fileira</th>}
                        {d.prateleiras.colunas.posicao && <th>Posição</th>}
                      </tr>
                    </thead>
                    <tbody>
                      {d.prateleiras.linhas.map((r, i) => (
                        <tr key={`${r.local}-${i}`}>
                          <td>{r.local}</td>
                          {d.prateleiras.colunas.prateleira && <td className="m">{r.prateleira || '—'}</td>}
                          {d.prateleiras.colunas.fileira && <td className="m">{r.fileira || '—'}</td>}
                          {d.prateleiras.colunas.posicao && <td className="m">{r.posicao || '—'}</td>}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                ))}
            </div>

            <SheetFooter className="flex-row flex-wrap gap-2 border-t border-[var(--border)] px-5 py-3">
              {podeEditar && (
                <Button size="sm" onClick={() => irPara(`/products/${d.id}/edit`)}>
                  <Pencil size={13} aria-hidden /> Editar produto
                </Button>
              )}
              {podePrecos && (
                <Button variant="outline" size="sm" onClick={() => irPara(`/products/add-selling-prices/${d.id}`)}>
                  Preços por grupo
                </Button>
              )}
              <Button variant="outline" size="sm" onClick={() => window.print()}>
                <Printer size={13} aria-hidden /> Imprimir
              </Button>
              <div className="flex-1" />
              <Button variant="ghost" size="sm" onClick={aoFechar}>
                Fechar
              </Button>
            </SheetFooter>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}
