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
import { Pencil, Printer, X } from 'lucide-react';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Grid, Inline } from '@/Components/layout';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import { brl, qtdComUnidade } from '../../Unificado/_components/catalogo';
import { TIPO_ROTULO, dataBr, type AbaDoDetalhe, type DetalheProdutoDados, type PrecoPropostoDoProduto, type RoteiroDoProduto } from './tipos';
import { ORIGENS_MERCADORIA } from '../origem-mercadoria';

type Aba = AbaDoDetalhe;

const qtd = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const dinheiro = (n: number | undefined | null) => (n === undefined || n === null ? '—' : brl(n));
/** `tax_type` do produto como a tela o chama; vazio quando o cadastro não diz. */
const tipoDeImposto = (t: string) => (t === 'inclusive' ? 'inclusivo' : t === 'exclusive' ? 'exclusivo' : '');

/** Menor e maior valor, como a faixa de preço da lista ("A – B"); um valor só quando são iguais. */
function faixa(valores: Array<number | undefined>): string {
  const v = valores.filter((n): n is number => n !== undefined);
  if (v.length === 0) return '';
  const min = Math.min(...v);
  const max = Math.max(...v);
  return min === max ? brl(min) : `${brl(min)} – ${brl(max)}`;
}

/**
 * A linha tem preço com imposto diferente do sem imposto? Sem imposto os dois são iguais e a tabela
 * mostra uma coluna só (decisão [F] 2026-10-26). Decide pelos valores que vieram: sem a permissão a
 * chave não vem e a coluna já some.
 */
type ParDePreco = { compra_exc?: number; compra_inc?: number; venda_exc?: number; venda_inc?: number };
const difere = (a: number | undefined, b: number | undefined) => a !== undefined && b !== undefined && Math.abs(a - b) >= 0.005;
const temImposto = (linhas: ParDePreco[]) => linhas.some((l) => difere(l.compra_exc, l.compra_inc) || difere(l.venda_exc, l.venda_inc));

// Tabela A da NF-e (origem da mercadoria): os mesmos nomes do campo do cadastro.
const ORIGENS = new Map(ORIGENS_MERCADORIA.map(([codigo, nome]) => [String(codigo), nome]));
const rotuloOrigem = (o: string) => (ORIGENS.has(o) ? `${o} — ${ORIGENS.get(o)}` : '');

const temNcm = (f: { ncm: string }) => !!f.ncm && f.ncm !== '0';

/**
 * A aba Fiscal começa dizendo se o produto está pronto para nota (decisão [F] 2026-10-26). Sem NCM a
 * nota não trava: sai com o NCM padrão da empresa.
 */
function ProntoParaNota({ f }: { f: { ncm: string } }) {
  return (
    <p className="pb-help mb-2.5" data-contract="produto-fiscal-pronto">
      {temNcm(f) ? (
        <>
          <span className="pb-pill combo">Pronto para nota</span> NCM preenchido.
        </>
      ) : (
        <>
          <span className="pb-pill off">Sem NCM</span> A nota sai com o NCM padrão da empresa. Se for serviço, a nota é a NFS-e, que
          não usa NCM.
        </>
      )}
    </p>
  );
}

/**
 * Texto de ajuda da aba Fiscal quando há NCM (UC-PIDX-25). Parte do protótipo, corrigido onde ele
 * afirma o que a emissão não faz: o CEST NÃO troca o CFOP da nota (a `NfeService` não lê o CEST). A
 * ordem da regra é a do simulador da Tributação: exceção do produto → regra do NCM → padrão da empresa.
 * Sem NCM, quem fala é a linha "Sem NCM" do topo da aba.
 */
function ajudaFiscal(f: { cest: string }): string {
  const base = 'O NCM escolhe a tributação da nota: primeiro a exceção do produto, depois a regra do NCM e, por fim, o padrão da empresa. Confira antes de emitir.';
  return f.cest
    ? `${base} CEST preenchido indica produto com substituição tributária; a nota não troca o CFOP sozinha — confira a regra do NCM.`
    : base;
}
// Produto único tem uma variação interna chamada DUMMY; o protótipo a mostra como "Padrão".
const nomeDaVariacao = (n: string | null | undefined) => (!n || n === 'DUMMY' ? 'Padrão' : n);

// Cores do rodapé no protótipo (`.os-btn` em styles.css), só neste painel — o `Button` do DS fica
// como está. O par `dark:` é obrigatório: no tema escuro o `outline` traz `dark:bg-input/30`, que
// venceria um `bg-…` sem prefixo. Medido em produção (biz 1, tema escuro) em 2026-10-14.
/** Principal: texto branco (`.os-btn.primary`). */
const BOTAO_PRINCIPAL = 'text-white hover:bg-[var(--accent-2)]';
/** Contorno: fundo sólido da superfície (`.os-btn`), não translúcido. */
const BOTAO_CONTORNO =
  'bg-[var(--surface)] text-[var(--text)] hover:bg-[var(--border-2)] hover:text-[var(--text)] dark:bg-[var(--surface)] dark:hover:bg-[var(--border-2)]';
/** "Fechar": texto esmaecido (`.os-btn.ghost`). */
const BOTAO_FECHAR = 'text-[var(--text-dim)] hover:bg-[var(--border-2)] hover:text-[var(--text)] dark:hover:bg-[var(--border-2)]';

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

/** Título de seção do protótipo (`.os-drawer-section h3`): 11px, 700, maiúsculas, esmaecido, altura de linha 1,2. */
function TituloDaSecao({ children }: { children: ReactNode }) {
  return <h3 className="mb-3 text-[11px] leading-[1.2] font-bold uppercase tracking-[0.06em] text-[var(--text-mute)]">{children}</h3>;
}

// Faixa do código + selo + fechar: a do `Drawer` do DS no protótipo (padding 14px 18px e
// `border-bottom: 1px solid var(--border)`). A borda é a linha entre a faixa e o título; sem ela a
// faixa compensava com 15px embaixo. Altura igual nos dois: 14 + 28 + 14 + 1 = 57px.
const TOPO = 'border-b border-[var(--border)] pt-[14px] pr-[18px] pb-[14px] pl-[19px]';

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

/**
 * Imagens das variações / dos itens — `variation_images` da janela "Ver" da Blade, que as punha numa
 * coluna da tabela. Aqui ficam numa seção à parte: medido em produção em 2026-10-14, a tabela da
 * composição com todas as colunas da Blade chegaria a 973 px num painel de 779 px.
 */
function ImagensDasVariacoes({ titulo, itens }: { titulo: string; itens: Array<{ nome: string; imagens: string[] }> }) {
  const comImagem = itens.filter((i) => i.imagens.length > 0);
  if (comImagem.length === 0) return null;
  return (
    <section aria-label={titulo} className="mt-[18px]" data-contract="produto-detalhe-imagens">
      <TituloDaSecao>{titulo}</TituloDaSecao>
      <Inline gap={3} wrap align="start">
        {comImagem.flatMap((i) =>
          i.imagens.map((url, n) => (
            <figure key={`${i.nome}-${n}-${url}`} className="m-0 w-[88px]">
              <img
                src={url}
                alt={`Imagem ${n + 1} de ${i.nome}`}
                loading="lazy"
                className="size-[88px] rounded-[8px] border border-[var(--border)] object-cover"
              />
              <figcaption className="mt-1 truncate text-[11px]" style={{ color: 'var(--text-mute)' }} title={i.nome}>
                {i.nome}
              </figcaption>
            </figure>
          )),
        )}
      </Inline>
    </section>
  );
}

/**
 * Preço da própria composição. A Blade mostrava o "Preço de venda padrão" (com imposto) abaixo da
 * tabela; o protótipo, o preço sem imposto e o % de lucro. Vêm os dois preços e o %, com os gates
 * das variações (sem permissão a chave não vem e a linha some).
 */
function PrecoDaComposicao({ v }: { v: DetalheProdutoDados['variacoes'][number] | undefined }) {
  if (!v || (v.venda_exc === undefined && v.venda_inc === undefined)) return null;
  // Primeiro o preço que a venda cobra (com imposto); o sem imposto só quando é diferente.
  return (
    <p className="pb-help" data-contract="produto-detalhe-preco-composicao">
      Preço de venda padrão da composição: <b>{dinheiro(v.venda_inc)}</b>
      {difere(v.venda_exc, v.venda_inc) && <> (sem imposto {dinheiro(v.venda_exc)})</>}
      {v.lucro_percentual !== undefined && <> · % de lucro {qtd(v.lucro_percentual)} %</>}
    </p>
  );
}

/**
 * Preços propostos pela compra recebida que aguardam aprovação (UC-PIDX-28 · UC-FLX-17). Só aviso:
 * aprovar e recusar ficam no detalhe da compra (US-COM-028), porque a consulta não altera preço
 * (charter, Automation Anti-hooks). O link só vem para quem pode abrir a compra.
 */
function PrecosPropostos({ itens, irPara }: { itens: PrecoPropostoDoProduto[]; irPara: (url: string) => void }) {
  if (itens.length === 0) return null;
  return (
    <section aria-label="Preço proposto pela compra" style={{ gridColumn: '1 / -1' }} data-contract="produto-detalhe-precos-propostos">
      <TituloDaSecao>Preço proposto pela compra</TituloDaSecao>
      <table className="pb-tbl">
        <caption className="sr-only">Preços propostos aguardando aprovação</caption>
        <thead>
          <tr>
            <th>Variação</th>
            <th className="r">Preço atual</th>
            <th className="r">Proposto</th>
            <th>Compra</th>
            <th aria-label="Abrir a compra" />
          </tr>
        </thead>
        <tbody>
          {itens.map((p) => (
            <tr key={p.id}>
              <td>{nomeDaVariacao(p.variacao)}</td>
              <td className="r">{brl(p.preco_atual)}</td>
              <td className="r">
                <b>{brl(p.preco_proposto)}</b>
              </td>
              <td>
                {[p.compra_ref, p.compra_data && p.compra_data.split('-').reverse().join('/')].filter(Boolean).join(' · ') || '—'}
              </td>
              <td className="r">
                {p.compra_url && (
                  <Button variant="outline" size="sm" className={BOTAO_CONTORNO} onClick={() => irPara(p.compra_url as string)}>
                    Abrir a compra
                  </Button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      <p className="pb-help">
        Aguardando aprovação. Aprove ou recuse no detalhe da compra.
        {itens.some((p) => p.preco_mudou) && ' O preço de alguma variação mudou depois da proposta: essa só pode ser recusada.'}
      </p>
    </section>
  );
}

/**
 * Roteiro de produção do produto, na ordem das etapas, com o posto e o setor de cada uma (UC-PIDX-29 ·
 * UC-FLX-07 · UC-FLX-08). O responsável não aparece: ele é escolhido na OS, entre os operadores do
 * posto (US-PCP-030).
 */
function RoteiroDoProdutoSecao({ r }: { r: RoteiroDoProduto }) {
  return (
    <div data-contract="produto-detalhe-roteiro">
      <TituloDaSecao>{`Roteiro: ${r.nome}`}</TituloDaSecao>
      {!r.ativo && <p className="pb-help">Roteiro inativo.</p>}
      {r.etapas.length === 0 ? (
        <p className="pb-help">O roteiro não tem etapas cadastradas.</p>
      ) : (
        <table className="pb-tbl">
          <caption className="sr-only">Etapas do roteiro</caption>
          <thead>
            <tr>
              <th className="r">#</th>
              <th>Etapa</th>
              <th>Posto</th>
              <th>Setor</th>
              <th className="r">Horas</th>
            </tr>
          </thead>
          <tbody>
            {r.etapas.map((e, i) => (
              <tr key={`${i}-${e.nome}`}>
                <td className="r">{i + 1}</td>
                <td>
                  {e.nome}
                  {e.opcional && <span className="text-[var(--text-mute)]"> · opcional</span>}
                  {e.paralela && <span className="text-[var(--text-mute)]"> · em paralelo</span>}
                </td>
                <td>{e.posto ?? '—'}</td>
                <td>{e.setor ?? '—'}</td>
                <td className="r">{e.horas === null ? '—' : qtd(e.horas)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <p className="pb-help">A OS de cada venda copia estas etapas. O responsável é escolhido na OS, entre os operadores do posto.</p>
    </div>
  );
}

/** UC-PIDX-32: o método de custo da variação, com a data da última compra recebida. */
function textoMetodoCusto(m: { metodo: 'medio' | 'ultimo' | null; ultima_compra_em: string | null }): string {
  if (m.metodo === null) return 'sem método definido';
  const base = m.metodo === 'medio' ? 'custo médio' : 'última compra';
  return m.ultima_compra_em ? `${base} · ${dataBr(m.ultima_compra_em)}` : `${base} · sem compra recebida`;
}

/** UC-PIDX-31: a natureza fiscal como a tela a chama. */
function rotuloNatureza(n: { natureza: string; codigo_servico: string | null }): string {
  if (n.natureza === 'servico') return `Serviço (NFS-e)${n.codigo_servico ? ` · item ${n.codigo_servico} da LC 116` : ''}`;
  if (n.natureza === 'pendente') return 'A decidir pelo contador';
  return 'Mercadoria (NF-e)';
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
  /** A empresa emite nota (módulo Fiscal no pacote): só então a aba Fiscal existe. */
  emiteNota: boolean;
  irPara: (url: string) => void;
}

export default function PainelDetalheProduto({ aberto, detalhe: d, aba, aoMudarAba, aoFechar, podeEditar, podePrecos, emiteNota, irPara }: Props) {
  // O `.cockpit` é o contêiner que entrega os tokens e a fonte do Design System.
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
  const combo = d?.tipo === 'combo';
  const custo = (d?.variacoes ?? []).some((v) => v.compra_exc !== undefined);
  const venda = (d?.variacoes ?? []).some((v) => v.venda_exc !== undefined);
  const lucro = (d?.variacoes ?? []).some((v) => v.lucro_percentual !== undefined);
  // UC-PIDX-32: só quando a empresa gravou a regra de custo (o servidor manda o campo).
  const metodoCusto = (d?.variacoes ?? []).some((v) => v.custo_metodo !== undefined);
  const grupos = d?.variacoes[0]?.grupos ?? [];
  // Imposto não é segredo (sai impresso na nota), então nada aqui depende de permissão; aparece onde
  // é útil (decisão [F] 2026-10-26): junto do preço na aba Dados, e as tabelas só separam "sem" e
  // "com imposto" quando os dois valores diferem.
  const variacoesComImposto = temImposto(d?.variacoes ?? []);
  const itensComImposto = temImposto(d?.composicao ?? []);

  const abas = d
    ? [
        { key: 'dados', label: 'Dados', href: '#' },
        combo
          ? { key: 'composicao', label: 'Composição', href: '#', badge: d.composicao.length }
          : { key: 'variacoes', label: 'Variações', href: '#', badge: d.variacoes.length },
        // Produto que não controla estoque também tem a aba, com o aviso (protótipo). Sem a
        // permissão de ver o relatório de estoque, a aba segue fora.
        ...(d.estoque !== null || !d.dados.controla_estoque ? [{ key: 'estoque', label: 'Estoque', href: '#' }] : []),
        // UC-PIDX-29: só aparece quando o produto tem roteiro de produção.
        ...(d.roteiro ? [{ key: 'roteiro', label: 'Roteiro', href: '#', badge: d.roteiro.etapas.length }] : []),
        // NCM, CEST, CFOP e origem só servem a quem emite nota: a mesma regra do botão "Abrir no
        // módulo Fiscal" (módulo no pacote). Quem vende sem nota não vê a aba.
        ...(emiteNota ? [{ key: 'fiscal', label: 'Fiscal', href: '#' }] : []),
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
        className="pb-root w-[820px] max-w-[95vw] gap-0 border-[var(--border)] bg-[var(--surface)] p-0 leading-[1.45] text-[var(--text)] sm:max-w-[820px]"
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

            {/* Abas com 39px, a altura da faixa do protótipo (o componente compartilhado tem 36), e as
                cores de texto e do contador do protótipo (`tone="prototype"`, só neste painel). */}
            <div className="border-b border-[var(--border)] bg-[var(--bg)] px-3 [&_a]:h-[39px]">
              <PageHeaderTabs
                density="compact"
                tone="prototype"
                activeGhostKey={abaAtiva}
                onGhostChange={(k) => aoMudarAba(k as Aba)}
                ghosts={abas}
              />
            </div>

            {/* Corpo com o fundo mais escuro do protótipo (`--bg-2`), abaixo do cabeçalho e do rodapé em
                `--surface`: sem ele o corpo herdava o `--surface` e os cartões e a caixa "Sem imagem"
                sumiam no fundo. Medido em produção, tema escuro, 2026-10-15. */}
            <div className="flex-1 overflow-y-auto bg-[var(--bg-2)] px-5 py-4" data-contract="produto-detalhe">
              {abaAtiva === 'dados' && (
                <Grid gap={4} className="grid-cols-[1fr_168px]">
                  <div className="pb-drawer-grid">
                    {/* O preço que a venda cobra, com nota ou sem: o com imposto (`sell_price_inc_tax`).
                        É o que o vendedor informa ao cliente. O sem imposto e o imposto só aparecem
                        quando o produto tem imposto. */}
                    {venda && <Dt rotulo="Preço de venda" valor={faixa(d.variacoes.map((v) => v.venda_inc))} />}
                    {venda && variacoesComImposto && <Dt rotulo="Preço sem imposto" valor={faixa(d.variacoes.map((v) => v.venda_exc))} />}
                    {d.dados.imposto && (
                      <Dt rotulo="Imposto aplicável" valor={[d.dados.imposto, tipoDeImposto(d.dados.tipo_imposto)].filter(Boolean).join(' · ')} />
                    )}
                    <Dt rotulo="SKU" valor={d.sku} />
                    <Dt rotulo="Tipo de código de barras" valor={d.dados.codigo_de_barras} />
                    <Dt rotulo="Unidade" valor={d.dados.unidade} />
                    <Dt rotulo="Marca" valor={d.dados.marca} />
                    <Dt rotulo="Categoria" valor={d.dados.categoria} />
                    <Dt rotulo="Subcategoria" valor={d.dados.subcategoria} />
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
                  {venda && variacoesComImposto && (
                    <p className="pb-help" style={{ gridColumn: '1 / -1' }} data-contract="produto-detalhe-preco-cobrado">
                      O preço de venda já inclui o imposto aplicável. É o valor que a venda cobra, com nota ou sem.
                    </p>
                  )}
                  {d.dados.descricao && (
                    <p className="text-[12.5px] leading-relaxed text-[var(--text-dim)]" style={{ gridColumn: '1 / -1' }}>
                      {d.dados.descricao}
                    </p>
                  )}
                  <PrecosPropostos itens={d.precos_propostos ?? []} irPara={irPara} />
                </Grid>
              )}

              {abaAtiva === 'roteiro' && d.roteiro && <RoteiroDoProdutoSecao r={d.roteiro} />}

              {abaAtiva === 'variacoes' && (
                <div>
                  <TituloDaSecao>Variações</TituloDaSecao>
                  <table className="pb-tbl">
                    <caption className="sr-only">Variações e preços</caption>
                    <thead>
                      <tr>
                        <th>Variação</th>
                        <th>SKU</th>
                        {custo && variacoesComImposto && <th className="r">Compra sem imposto</th>}
                        {custo && <th className="r">{variacoesComImposto ? 'Compra com imposto' : 'Compra'}</th>}
                        {metodoCusto && <th>Método do custo</th>}
                        {lucro && <th className="r">% lucro</th>}
                        {venda && variacoesComImposto && <th className="r">Venda sem imposto</th>}
                        {venda && <th className="r">{variacoesComImposto ? 'Venda com imposto' : 'Venda'}</th>}
                      </tr>
                    </thead>
                    <tbody>
                      {d.variacoes.map((v) => (
                        <tr key={v.sku}>
                          <td>{nomeDaVariacao(v.nome)}</td>
                          <td className="m">{v.sku}</td>
                          {custo && variacoesComImposto && <td className="r">{dinheiro(v.compra_exc)}</td>}
                          {custo && <td className="r">{dinheiro(v.compra_inc)}</td>}
                          {metodoCusto && <td data-contract="produto-detalhe-metodo-custo">{v.custo_metodo ? textoMetodoCusto(v.custo_metodo) : '—'}</td>}
                          {lucro && <td className="r">{v.lucro_percentual === undefined ? '—' : `${qtd(v.lucro_percentual)} %`}</td>}
                          {venda && variacoesComImposto && <td className="r">{dinheiro(v.venda_exc)}</td>}
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
                  <ImagensDasVariacoes
                    titulo="Imagens das variações"
                    itens={d.variacoes.map((v) => ({ nome: nomeDaVariacao(v.nome), imagens: v.imagens }))}
                  />
                  {!custo && !venda && <p className="pb-help">Os preços aparecem para quem tem permissão de vê-los.</p>}
                </div>
              )}

              {abaAtiva === 'composicao' && (
                <div className="space-y-3">
                  <TituloDaSecao>Composição</TituloDaSecao>
                  <table className="pb-tbl">
                    <caption className="sr-only">Composição</caption>
                    <thead>
                      <tr>
                        <th>Produto</th>
                        <th>SKU</th>
                        <th className="r">Qtd</th>
                        {custo && <th className="r">{itensComImposto ? 'Compra sem imposto' : 'Compra'}</th>}
                        {custo && <th className="r">{itensComImposto ? 'Total sem imposto' : 'Total'}</th>}
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
                    {custo && d.composicao_total_exc !== null && (
                      <tfoot>
                        <tr>
                          <td colSpan={4}>
                            <b>Total líquido</b>
                          </td>
                          <td className="r">
                            <b>{dinheiro(d.composicao_total_exc)}</b>
                          </td>
                        </tr>
                      </tfoot>
                    )}
                  </table>
                  <PrecoDaComposicao v={d.variacoes[0]} />
                  {(custo || venda) && d.composicao.length > 0 && (
                    <div className="pt-[6px]">
                      <TituloDaSecao>Preços dos itens</TituloDaSecao>
                      <table className="pb-tbl">
                        <caption className="sr-only">Preços dos itens da composição</caption>
                        <thead>
                          <tr>
                            <th>Item</th>
                            {custo && <th className="r">{itensComImposto ? 'Compra com imposto' : 'Compra'}</th>}
                            {lucro && <th className="r">% lucro</th>}
                            {venda && itensComImposto && <th className="r">Venda sem imposto</th>}
                            {venda && <th className="r">{itensComImposto ? 'Venda com imposto' : 'Venda'}</th>}
                          </tr>
                        </thead>
                        <tbody>
                          {d.composicao.map((c) => (
                            <tr key={c.sku}>
                              <td>{c.nome}</td>
                              {custo && <td className="r">{dinheiro(c.compra_inc)}</td>}
                              {lucro && <td className="r">{c.lucro_percentual === undefined ? '—' : `${qtd(c.lucro_percentual)} %`}</td>}
                              {venda && itensComImposto && <td className="r">{dinheiro(c.venda_exc)}</td>}
                              {venda && <td className="r">{dinheiro(c.venda_inc)}</td>}
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                  <ImagensDasVariacoes titulo="Imagens dos itens" itens={d.composicao.map((c) => ({ nome: c.nome, imagens: c.imagens }))} />
                </div>
              )}

              {abaAtiva === 'estoque' && d.estoque !== null && (
                <div className="space-y-3">
                  <TituloDaSecao>Detalhes de estoque</TituloDaSecao>
                  <IndicadoresDoEstoque d={d} />
                  {/* Espaços do protótipo: tabela → texto 10px, texto → botão 16px. */}
                  <table className="pb-tbl mb-[10px]">
                    <caption className="sr-only">Estoque por local</caption>
                    <thead>
                      <tr>
                        <th>Local</th>
                        {d.tipo === 'variable' && <th>Variação</th>}
                        <th className="r">Saldo</th>
                        <th className="r">Alerta</th>
                        <th className="r">Vendido</th>
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
                          {/* O "Total vendido" do Relatório de estoque para este local (vendas finalizadas
                              menos devoluções), que o servidor já manda na linha — nenhuma conta aqui. */}
                          <td className="r"><span className="pb-stock">{qtdComUnidade(e.vendido, e.unidade)}</span></td>
                          <td>{e.alerta ? <span className="pb-pill off">Repor</span> : <span className="pb-pill combo">Suficiente</span>}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  <p className="pb-help mb-4">Saldo e vendido por local, os mesmos de Relatórios → Estoque. O histórico completo fica em Histórico de estoque.</p>
                  {/* UC-PIDX-30 (UC-FLX-19): requisição de compra aberta, do estoque mínimo ou feita à mão. */}
                  {d.requisicao && (
                    <div className="mb-4" data-contract="produto-detalhe-requisicao">
                      <p className="pb-help mb-1.5">
                        Requisição de compra <b>{d.requisicao.numero}</b> aberta em {dataBr(d.requisicao.data)}
                        {d.requisicao.fornecedor ? `, para ${d.requisicao.fornecedor}` : ''}.
                      </p>
                      <Button variant="outline" size="sm" className="h-[26px] px-[10px] text-[11.5px]" onClick={() => d.requisicao && irPara(d.requisicao.url)}>
                        Abrir a requisição
                      </Button>
                    </div>
                  )}
                  {/* Botão pequeno do protótipo (`os-btn sm`): 26px de altura, 11,5px. */}
                  <Button variant="outline" size="sm" className="h-[26px] px-[10px] text-[11.5px]" onClick={() => irPara(`/products/stock-history/${d.id}`)}>
                    Abrir histórico de estoque
                  </Button>
                </div>
              )}
              {/* Protótipo: "Este produto não gerencia estoque (enable_stock = 0) — serviços e composições
                  entram assim." Fica só a 1ª frase: o nome da coluna não diz nada a quem usa a tela, e
                  produto composto também pode controlar estoque no cadastro. */}
              {abaAtiva === 'estoque' && !d.dados.controla_estoque && (
                <div data-contract="produto-detalhe-sem-estoque">
                  <TituloDaSecao>Detalhes de estoque</TituloDaSecao>
                  <p className="pb-help">Este produto não gerencia estoque.</p>
                </div>
              )}

              {abaAtiva === 'fiscal' && <TituloDaSecao>Dados fiscais</TituloDaSecao>}
              {abaAtiva === 'fiscal' && <ProntoParaNota f={d.fiscal} />}
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
                  {/* Origem da mercadoria (UC-PIDX-25): a do próprio produto, a mesma que vai no `orig`
                      da nota. O imposto aplicável não se repete aqui: fica junto do preço, na aba Dados. */}
                  <Dt rotulo="Origem da mercadoria" valor={rotuloOrigem(d.fiscal.origem)} />
                  {/* UC-PIDX-31: só com o módulo Fiscal. */}
                  {d.fiscal.natureza && <Dt rotulo="Natureza fiscal" valor={rotuloNatureza(d.fiscal.natureza)} />}
                </div>
              )}
              {abaAtiva === 'fiscal' && d.fiscal.natureza?.natureza === 'pendente' && (
                <p className="pb-help mt-2.5 text-[var(--warn)]" data-contract="produto-detalhe-natureza-pendente">
                  O contador ainda não decidiu se este produto é mercadoria ou serviço. A NF-e comum sai como mercadoria; a emissão
                  separada (NF-e + NFS-e) fica bloqueada até a decisão.
                </p>
              )}
              {abaAtiva === 'fiscal' && (
                <div data-contract="produto-detalhe-fiscal-ajuda">
                  {!d.fiscal.origem_por_produto && (
                    <p className="pb-help mt-2.5">
                      A origem é a mesma em todas as notas: o cadastro ainda não guarda a origem de cada produto.
                    </p>
                  )}
                  {temNcm(d.fiscal) && <p className="pb-help mt-1.5">{ajudaFiscal(d.fiscal)}</p>}
                  {d.fiscal.link_modulo && (
                    <Button
                      variant="outline"
                      size="sm"
                      className="mt-2 h-[26px] px-[10px] text-[11.5px]"
                      onClick={() => irPara(d.fiscal.link_modulo as string)}
                    >
                      Abrir no módulo Fiscal
                    </Button>
                  )}
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
                <Button size="sm" className={BOTAO_PRINCIPAL} onClick={() => irPara(`/products/${d.id}/edit`)}>
                  <Pencil size={13} aria-hidden /> Editar produto
                </Button>
              )}
              {/* Sem "Comprar": a consulta não é a tela de compras (decisão [F] 2026-10-26, UC-PIDX-11). */}
              {podePrecos && (
                <Button variant="outline" size="sm" className={BOTAO_CONTORNO} onClick={() => irPara(`/products/add-selling-prices/${d.id}`)}>
                  Preços por grupo
                </Button>
              )}
              <Button variant="outline" size="sm" className={BOTAO_CONTORNO} onClick={() => window.print()}>
                <Printer size={13} aria-hidden /> Imprimir
              </Button>
              <Button variant="ghost" size="sm" className={BOTAO_FECHAR} onClick={aoFechar}>
                Fechar
              </Button>
            </SheetFooter>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}
