// Painel de detalhe do produto — `DetalheDrawer` do protótipo (produto-blade.jsx) com o conteúdo da
// janela "Ver" da lista Blade. Os dados chegam prontos de ProductController::buildProdutoDetalhe, com os
// gates da Blade: chave de preço ausente = sem permissão (nunca zero).
//
// O painel monta DENTRO do `.cockpit` (prop `container` do Sheet). Medido em produção em
// 2026-10-11: no <body>, fora do `.cockpit`, `--text`, `--text-mute`, `--border`, `--accent`,
// `--surface` e a fonte chegavam vazios — fonte do sistema, rótulos sem esmaecer, selo sem cor,
// tabelas sem linha e borda branca no rodapé. Dentro dele os tokens da fundação chegam sozinhos,
// sem redefinir nenhum (ADR UI-0013). As medidas da moldura são as do `DetalheDrawer` do protótipo.

import { useEffect, useState, type ReactNode } from 'react';
import { Archive, Pencil, Printer, X } from 'lucide-react';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Grid, Inline } from '@/Components/layout';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import { brl, qtdComUnidade } from '../../Unificado/_components/catalogo';
import { TIPO_ROTULO, type AbaDoDetalhe, type DetalheProdutoDados } from './tipos';

type Aba = AbaDoDetalhe;

const qtd = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const dinheiro = (n: number | undefined | null) => (n === undefined || n === null ? '—' : brl(n));
// Produto único tem uma variação interna chamada DUMMY; o protótipo a mostra como "Padrão".
const nomeDaVariacao = (n: string | null | undefined) => (!n || n === 'DUMMY' ? 'Padrão' : n);

/** Fechar do protótipo: 28×28, raio 6, esmaecido. */
function Fechar() {
  return (
    <SheetClose asChild>
      <Button
        variant="ghost"
        size="icon"
        aria-label="Fechar painel"
        className="ml-auto size-7 rounded-md text-[var(--text-mute)] hover:bg-[var(--bg-2)] hover:text-[var(--text)]"
      >
        <X size={16} aria-hidden />
      </Button>
    </SheetClose>
  );
}

/** Título de seção do protótipo (`.os-drawer-section h3`): 11px, 700, maiúsculas, esmaecido. */
function TituloDaSecao({ children }: { children: ReactNode }) {
  return <h3 className="mb-3 text-[11px] font-bold uppercase tracking-[0.06em] text-[var(--text-mute)]">{children}</h3>;
}

const TOPO = 'pt-[14px] pr-[18px] pb-[15px] pl-[19px]';

/**
 * Os 3 indicadores da aba Estoque do protótipo (`.pb-kpi`, 3 colunas). Nada é calculado aqui: os
 * totais chegam somados do servidor (`estoque_totais`, a soma das linhas da tabela logo abaixo, pela
 * mesma função do rodapé do relatório de estoque). Valor null = sem permissão de ver valores do
 * estoque — aparece "—", nunca zero. A legenda do custo diz o que o campo é (preço pago nas
 * compras); o protótipo dizia "preço de compra padrão", que não é a conta do relatório.
 */
function IndicadoresDoEstoque({ d }: { d: DetalheProdutoDados }) {
  const t = d.estoque_totais;
  if (!t) return null;
  const unidade = d.dados.unidade_curta || d.dados.unidade;
  const legendaDoEstoque = [unidade, d.dados.alerta === null ? '' : `alerta em ${qtd(d.dados.alerta)}`].filter(Boolean).join(' · ');
  const semPermissao = 'sem permissão para ver valores do estoque';
  return (
    <Grid cols={3} className="gap-[10px]" data-contract="produto-detalhe-estoque-indicadores">
      <div className="pb-kpi">
        <small>Estoque atual</small>
        <b>{qtd(t.estoque)}</b>
        <div className="ln">{legendaDoEstoque || '—'}</div>
      </div>
      <div className="pb-kpi">
        <small>Valor pelo custo</small>
        <b>{dinheiro(t.valor_compra)}</b>
        <div className="ln">{t.valor_compra === null ? semPermissao : 'pelo preço pago nas compras'}</div>
      </div>
      <div className="pb-kpi">
        <small>Valor pela venda</small>
        <b>{dinheiro(t.valor_venda)}</b>
        <div className="ln">{t.lucro_potencial === null ? semPermissao : `lucro potencial ${brl(t.lucro_potencial)}`}</div>
      </div>
    </Grid>
  );
}

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
  /**
   * Aba ativa — quem abre o painel é o dono dela. "Ver" abre em Dados, "Dados fiscais" abre em
   * Fiscal, e cada abertura recomeça na aba pedida (o painel fica montado entre uma e outra).
   */
  aba: AbaDoDetalhe;
  aoMudarAba: (aba: AbaDoDetalhe) => void;
  aoFechar: () => void;
  podeEditar: boolean;
  podePrecos: boolean;
  /** `purchase.create` — o "Comprar" abre a nova compra com este produto. */
  podeComprar: boolean;
  irPara: (url: string) => void;
}

export default function PainelDetalheProduto({ aberto, detalhe: d, aba, aoMudarAba, aoFechar, podeEditar, podePrecos, podeComprar, irPara }: Props) {
  // O `.cockpit` é o contêiner que entrega os tokens e a fonte do Design System.
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
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
      {/* `pb-root` de novo: o conteúdo fica fora do `.pb-root` da lista e perderia o escopo do bundle
          (lápide §5 2026-07-10 — re-aplicar o wrapper no conteúdo do portal). */}
      <SheetContent
        side="right"
        container={cockpit}
        showCloseButton={false}
        className="pb-root w-[820px] max-w-[95vw] gap-0 border-[var(--border)] bg-[var(--surface)] p-0 text-[var(--text)] sm:max-w-[820px]"
      >
        {!d ? (
          <div aria-busy="true">
            <Inline className={TOPO}>
              <Fechar />
            </Inline>
            <SheetTitle className="sr-only">Carregando produto</SheetTitle>
            <div className="space-y-3 px-5 pb-6">
              <Skeleton className="h-6 w-2/3" />
              <Skeleton className="h-4 w-1/2" />
              <Skeleton className="h-40 w-full" />
            </div>
          </div>
        ) : (
          <>
            <Inline className={TOPO}>
              <Inline wrap gap={2}>
                <span className="font-mono text-[11px] uppercase tracking-[0.06em] text-[var(--text-dim)]">{d.sku}</span>
                <span className={`pb-pill ${d.tipo}`}>{TIPO_ROTULO[d.tipo] ?? d.tipo}</span>
                {d.inativo && <span className="pb-pill off">Inativo</span>}
              </Inline>
              <Fechar />
            </Inline>
            <SheetHeader className="gap-1 pt-4 pr-[18px] pb-4 pl-[19px]">
              <SheetTitle className="text-[17px] leading-[1.3] font-semibold tracking-[-0.01em] text-[var(--text)]">{d.nome}</SheetTitle>
              <SheetDescription className="text-[12.5px] leading-[1.45] text-[var(--text-dim)]">
                {[d.dados.categoria, d.dados.subcategoria, d.dados.marca, d.dados.unidade && `unidade ${d.dados.unidade_curta || d.dados.unidade}`]
                  .filter(Boolean)
                  .join(' · ')}
              </SheetDescription>
            </SheetHeader>

            <div className="border-b border-[var(--border)] bg-[var(--bg)] px-3">
              <PageHeaderTabs
                density="compact"
                activeGhostKey={abaAtiva}
                onGhostChange={(k) => aoMudarAba(k as Aba)}
                ghosts={abas}
              />
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-4" data-contract="produto-detalhe">
              {abaAtiva === 'dados' && (
                <Grid gap={4} className="grid-cols-[1fr_168px]">
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
                  {d.imagem ? (
                    <img
                      src={d.imagem}
                      alt={"Imagem de " + d.nome}
                      className="size-[168px] rounded-[10px] border border-[var(--border)] object-cover"
                    />
                  ) : (
                    <div className="pb-img">Sem imagem</div>
                  )}
                  {d.dados.descricao && (
                    <p className="text-[12.5px] leading-relaxed text-[var(--text-dim)]" style={{ gridColumn: '1 / -1' }}>
                      {d.dados.descricao}
                    </p>
                  )}
                </Grid>
              )}

              {abaAtiva === 'variacoes' && (
                <div>
                  <TituloDaSecao>Variações</TituloDaSecao>
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
                          <td>{nomeDaVariacao(v.nome)}</td>
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
                    <div className="mt-[18px]">
                      <TituloDaSecao>Preços por grupo</TituloDaSecao>
                    </div>
                  )}
                  {grupos.length > 0 && (
                    <table className="pb-tbl">
                      <caption className="sr-only">Preços por grupo</caption>
                      <thead>
                        <tr>
                          <th>Variação</th>
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
                            <td>{nomeDaVariacao(v.nome)}</td>
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

              {abaAtiva === 'composicao' && <TituloDaSecao>Composição</TituloDaSecao>}
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
                  <TituloDaSecao>Detalhes de estoque</TituloDaSecao>
                  <IndicadoresDoEstoque d={d} />
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

              {abaAtiva === 'fiscal' && <TituloDaSecao>Dados fiscais</TituloDaSecao>}
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

              {abaAtiva === 'prateleira' && <TituloDaSecao>Detalhes de prateleira</TituloDaSecao>}
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

            <SheetFooter className="mt-0 flex-row flex-wrap justify-end gap-2 border-t border-[var(--border)] px-[18px] py-3 [&_button]:h-[30px] [&_button]:px-3 [&_button]:text-[12.5px]">
              {podeEditar && (
                <Button size="sm" onClick={() => irPara(`/products/${d.id}/edit`)}>
                  <Pencil size={13} aria-hidden /> Editar produto
                </Button>
              )}
              {podeComprar && d.dados.controla_estoque && !d.inativo && (
                <Button variant="outline" size="sm" onClick={() => irPara(`/purchases/create?produto=${d.id}`)}>
                  <Archive size={13} aria-hidden /> Comprar
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
