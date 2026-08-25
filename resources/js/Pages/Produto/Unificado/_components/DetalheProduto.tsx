/**
 * Drawer de detalhe do produto (padrão PT-02) — arquivo próprio: é a tela dentro da tela, e é
 * o SEGUNDO lugar onde a autorização de custo precisa ser respeitada (handoff §7).
 *
 * Ordem das seções — **disponibilidade primeiro, cadastro por último** (handoff 21/08 §5):
 *
 *   1. Alertas (item inativo · observação crítica)
 *   2. Tira de identidade (miniatura 60 + selo do tipo + categoria)
 *   3. Disponível → 4. Preço e margem → 5. Estoque → 6. Giro
 *   7. Identificação (código e referência, copiáveis) → 8. Observações
 *
 * A ordem MUDOU no pacote de 21/08. Antes o painel abria pelo bloco "Estoque" com saldo,
 * mínimo, unidade e última venda misturados; agora "tem?" é a primeira resposta sozinha, o
 * preço vem logo atrás, e o que é dado de cadastro (código, referência) desce pro fim. É a
 * ordem em que o balcão pergunta durante o atendimento — e quem está com o cliente na frente
 * não rola atrás da resposta que usa cem vezes por dia.
 *
 * Dentro de "Preço e margem" a sequência é preço → margem → custo, não o contrário: o número
 * que se fala ao cliente vem primeiro; o custo é o que fundamenta, e é o gateado.
 *
 * Cada seção só é montada quando tem dado. Seção vazia com título afirma que o dado existe e
 * que o app falhou em buscá-lo.
 *
 * Largura 420px, 480 quando há composição de kit (§5): cabe ao lado da lista no monitor 1280
 * da Larissa sem empurrar a tabela pra fora. É deliberadamente mais estreito que o drawer de
 * 760 do cadastro de cliente, porque aqui não se edita nada.
 *
 * ⚠️ Fora deste painel, por falta de dado no cadastro e não por decisão de design: Reposição
 * (fornecedor, última compra), faixas de preço por quantidade, alçada de desconto e a matriz
 * da grade. O handoff os especifica em §5, §6 e §7; nenhum deles existe hoje no UltimatePOS
 * desta base. Inventar a seção com número derivado seria pior que não tê-la.
 */

import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Inline, Stack } from '@/Components/layout';
import Disponibilidade from './Disponibilidade';
import MiniaturaProduto from './MiniaturaProduto';
import { AlertTriangle, ChevronLeft, ChevronRight, Copy } from 'lucide-react';
import {
  TIPO_LABEL,
  brl,
  estadoEstoque,
  qtdComUnidade,
  margemFrac,
  pct,
  saldoTexto,
  sobOPiso,
  type Permissoes,
  type ProdutoRow,
} from './catalogo';

/**
 * Rótulo em 12px, não 12.5 — o padrão do template canônico
 * (`Pt01Lista.dc.html` L101-104), não decisão desta tela (handoff §18.5).
 */
function Linha({ rotulo, children }: { rotulo: string; children: ReactNode }) {
  return (
    <Inline gap={4} align="baseline" justify="between">
      <span className="text-[12px] text-muted-foreground">{rotulo}</span>
      {children}
    </Inline>
  );
}

/**
 * 10.5px / tracking .05em — os valores do `h4` do `DrawerSection` do bundle
 * (`_ds_bundle.js` L3916-3922), não um nível tipográfico inventado na tela
 * (handoff §18.3). Mesmos valores do subtítulo "Por local" abaixo — os dois
 * eram divergentes por acidente, não por intenção.
 */
function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="px-6 py-4 border-b border-border">
      <h3 className="text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground mb-3">{titulo}</h3>
      <Stack gap={2}>{children}</Stack>
    </section>
  );
}

export interface DetalheProdutoProps {
  produto: ProdutoRow | null;
  perm: Permissoes;
  piso: number;
  onFechar: () => void;
  /**
   * Navegação entre itens SEM fechar o painel (handoff §5). Comparar dois produtos era um
   * ciclo de fechar-procurar-abrir; com ‹ › o painel vira uma esteira sobre o recorte que já
   * está na tela. `null` quando não há vizinho naquele sentido.
   */
  onVizinho?: (delta: -1 | 1) => void;
  temAnterior?: boolean;
  temProximo?: boolean;
  /** "2 de 13" — posição no recorte, não no cadastro. */
  posicao?: string;
  onCopiar?: (texto: string, rotulo: string) => void;
}

/** Valor copiável — o código e a referência são o que se dita e se cola (§5 item 9). */
function Copiavel({ texto, rotulo, onCopiar }: { texto: string; rotulo: string; onCopiar?: (t: string, r: string) => void }) {
  if (!onCopiar) return <span className="font-mono text-[12.5px] tabular-nums">{texto}</span>;
  return (
    <button
      type="button"
      onClick={() => onCopiar(texto, rotulo)}
      title={`Copiar ${rotulo.toLowerCase()}`}
      className="inline-flex cursor-copy items-center gap-1.5 font-mono text-[12.5px] tabular-nums hover:underline"
    >
      {texto}
      <Copy size={11} className="opacity-50" aria-hidden="true" />
    </button>
  );
}

export function DetalheProduto({
  produto, perm, piso, onFechar, onVizinho, temAnterior = false, temProximo = false, posicao = '', onCopiar,
}: DetalheProdutoProps) {
  const aberto = produto !== null;
  const estado = produto ? estadoEstoque(produto) : null;
  const zerado = produto?.stockQty === 0;
  const margem = produto ? margemFrac(produto) : undefined;
  const locais = produto?.locais ?? [];
  const variacoes = produto?.variacoes ?? [];
  const temComposicao = perm.composicao && produto?.bomCount !== undefined;

  // Mesmo alerta do popover da linha, mesma regra: só no caso MISTO (zero em um local, saldo
  // em outro). Tudo zerado já é o badge vermelho do topo do painel.
  const zeradosNoLocal = locais.filter((l) => l.qtd === 0);
  const alertaLocal = zeradosNoLocal.length > 0 && locais.some((l) => l.qtd > 0)
    ? `0 na ${zeradosNoLocal.map((l) => l.nome).join(' e ')} — saldo disponível em outro local.`
    : '';

  return (
    <Sheet open={aberto} onOpenChange={(v) => { if (!v) onFechar(); }}>
      <SheetContent
        side="right"
        className={(temComposicao ? 'w-[480px] sm:max-w-[480px]' : 'w-[420px] sm:max-w-[420px]') + ' p-0'}
      >
        {produto && estado && (
          <Stack gap={0} className="h-full">
            <SheetHeader className="px-6 py-4 border-b border-border space-y-1 text-left">
              <SheetTitle className="text-[15px] font-semibold leading-snug pr-6">
                {produto.name}
              </SheetTitle>
              <SheetDescription className="font-mono text-[11.5px] tabular-nums">
                {produto.codigo}
                {produto.referencia ? ` · ${produto.referencia}` : ''}
              </SheetDescription>
            </SheetHeader>

            <div className="flex-1 overflow-y-auto">
              {/* ───── 1 · ALERTAS ─────
                  Antes de qualquer número. Item inativo e observação crítica mudam se a venda
                  pode acontecer — descobrir isso depois de ler saldo e preço é descobrir tarde. */}
              {!produto.active && (
                <div className="px-6 pt-4">
                  <div className="rounded-md border border-warning/25 bg-warning-soft px-3 py-2.5">
                    <p className="text-[12px] font-semibold text-warning-fg">Produto inativo</p>
                    <p className="mt-0.5 text-[11.5px] leading-relaxed text-warning-fg/85">
                      Não pode ser vendido nem incluído em orçamento. Consulta e histórico seguem disponíveis.
                    </p>
                  </div>
                </div>
              )}

              {/* ───── 2 · TIRA DE IDENTIDADE ─────
                  Miniatura 60 + selo do tipo + categoria (§5 item 2). Responde "é este mesmo?"
                  antes de o operador ler qualquer número — é a conferência que ele faria
                  voltando o olho pra lista. */}
              <Inline gap={3} className="px-6 pt-4">
                <MiniaturaProduto nome={produto.name} tamanho={60} />
                <Stack gap={1} className="min-w-0">
                  <span className="inline-flex w-fit items-center rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] tracking-wide text-muted-foreground">
                    {TIPO_LABEL[produto.tipo]}
                  </span>
                  <span className="truncate text-[12px] text-muted-foreground">{produto.cat_label ?? '—'}</span>
                </Stack>
              </Inline>

              {/* ───── 3 · DISPONÍVEL ─────
                  A primeira pergunta do balcão, sozinha na seção. */}
              <Secao titulo="Disponível">
                <Linha rotulo={locais.length > 1 ? 'Saldo atual (todos os locais)' : 'Saldo atual'}>
                  {/* Vermelho quando zerado: "sem saldo" bloqueia venda, é dado [V0]. */}
                  <span className={'font-mono tabular-nums text-[16px] font-semibold ' + (zerado ? 'text-destructive-fg' : 'text-foreground')}>
                    {saldoTexto(produto)}
                  </span>
                </Linha>
                <div className="pt-1">
                  <Disponibilidade estado={estado} unidade={produto.unit} />
                </div>

                {/* "Por local": a mesma informação do tooltip da linha, aqui aberta — no painel
                    há espaço, e quem abriu já demonstrou o interesse que o tooltip exige um
                    gesto pra revelar. */}
                {locais.length > 0 && (
                  <div className="pt-2 mt-1 border-t border-border">
                    <p role="heading" aria-level={5} className="text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                      Por local
                    </p>
                    {locais.map((l) => (
                      <Linha key={l.nome} rotulo={l.nome}>
                        <span className={'font-mono tabular-nums text-[12.5px] ' + (l.qtd === 0 ? 'font-semibold text-destructive-fg' : '')}>
                          {qtdComUnidade(l.qtd, produto.unit)}
                        </span>
                      </Linha>
                    ))}
                    {alertaLocal && (
                      <Inline gap={1} align="start" className="mt-2 text-[11.5px] text-destructive-fg">
                        <AlertTriangle size={12} className="mt-px shrink-0" />
                        {alertaLocal}
                      </Inline>
                    )}
                  </div>
                )}
              </Secao>

              {/* ───── 4 · PREÇO E MARGEM ─────
                  Preço → margem → custo (§5 item 5). O número que se fala ao cliente primeiro;
                  o custo é o que fundamenta, e é o que a permissão gateia. A seção inteira só
                  existe pra quem pode ver ALGUM dos dois — card vazio "Preço e margem" seria
                  dizer que o dado existe e o app quebrou. */}
              {(perm.preco || perm.custo) && (
                <Secao titulo="Preço e margem">
                  {perm.preco && produto.price !== undefined && (
                    <Linha rotulo="Preço de venda">
                      <span className="font-mono tabular-nums text-[16px] font-semibold">{brl(produto.price)}</span>
                    </Linha>
                  )}
                  {perm.custo && perm.preco && margem !== undefined && (
                    <Linha rotulo="Margem">
                      <span className={'font-mono tabular-nums text-[12.5px] font-semibold ' + (sobOPiso(produto, piso) ? 'text-destructive-fg' : 'text-foreground')}>
                        {pct(margem)}
                      </span>
                    </Linha>
                  )}
                  {perm.custo && produto.cost !== undefined && (
                    <Linha rotulo="Custo">
                      <span className="font-mono tabular-nums text-[12.5px] text-muted-foreground">
                        {brl(produto.cost)} / {produto.unit}
                      </span>
                    </Linha>
                  )}
                  {/* Sem permissão a tela DIZ que o campo é restrito, em vez de deixar o
                      espaço em branco (§8): vazio parece falha do sistema. */}
                  {!perm.custo && (
                    <p className="text-[11.5px] leading-relaxed text-muted-foreground">
                      Custo e margem são restritos ao administrador.
                    </p>
                  )}
                </Secao>
              )}

              {temComposicao && (
                <Secao titulo="Composição">
                  <Linha rotulo="Itens na receita">
                    <span className="font-mono tabular-nums text-[12.5px]">{produto.bomCount}</span>
                  </Linha>
                </Secao>
              )}

              {/* ───── 5 · ESTOQUE ─────
                  O que ENQUADRA o saldo: mínimo, unidade e a grade de variações. Depois da
                  resposta, não antes. */}
              <Secao titulo="Estoque">
                <Linha rotulo="Mínimo">
                  <span className="font-mono tabular-nums text-[12.5px]">
                    {produto.minimo === null ? '—' : `${produto.minimo} ${produto.unit}`}
                  </span>
                </Linha>
                <Linha rotulo="Unidade">
                  <span className="font-mono text-[12.5px]">{produto.unit}</span>
                </Linha>
                {variacoes.map((v) => (
                  <Linha key={v.nome} rotulo={v.nome}>
                    <span className="font-mono tabular-nums text-[12.5px]">{v.n}</span>
                  </Linha>
                ))}
                {/* Combinações só faz sentido com DOIS ou mais atributos: com um só, o
                    número repetiria a linha de cima. */}
                {variacoes.length > 1 && (
                  <Linha rotulo="Combinações">
                    <span className="font-mono tabular-nums text-[12.5px] font-semibold">
                      {variacoes.reduce((acc, v) => acc * v.n, 1)}
                    </span>
                  </Linha>
                )}
              </Secao>

              {/* ───── 6 · GIRO ───── */}
              <Secao titulo="Giro">
                <Linha rotulo="Última venda">
                  <span className="font-mono tabular-nums text-[12.5px]">
                    {produto.ultimaVenda ?? 'sem registro'}
                  </span>
                </Linha>
              </Secao>

              {/* ───── 7 · IDENTIFICAÇÃO ─────
                  Dado de CADASTRO, por último (§5). Código e referência copiáveis: é o que se
                  dita no telefone e se cola no orçamento. */}
              <Secao titulo="Identificação">
                <Linha rotulo="Código">
                  <Copiavel texto={String(produto.codigo)} rotulo="Código" onCopiar={onCopiar} />
                </Linha>
                {produto.referencia && (
                  <Linha rotulo="Referência">
                    <Copiavel texto={produto.referencia} rotulo="Referência" onCopiar={onCopiar} />
                  </Linha>
                )}
              </Secao>

              {/* ───── 8 · OBSERVAÇÕES ───── */}
              {produto.obs && (
                <Secao titulo="Observações">
                  <p className="text-[12.5px] leading-relaxed whitespace-pre-line text-foreground">
                    {produto.obs}
                  </p>
                </Secao>
              )}
            </div>

            {/* ───── RODAPÉ ─────
                À esquerda a esteira ‹ › com a posição no recorte; à direita as três saídas do
                §5. O painel é LEITURA: ele entrega o operador na tela responsável em vez de
                tentar fazer o trabalho dela. "Formar preço" é cadastro; "Usar em orçamento" é
                o PDV. */}
            <footer className="border-t border-border px-4 py-3">
              <Inline gap={2} justify="between">
                {onVizinho ? (
                  <Inline gap={1}>
                    <button
                      type="button"
                      onClick={() => onVizinho(-1)}
                      disabled={!temAnterior}
                      aria-label="Item anterior"
                      className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                    >
                      <ChevronLeft size={14} />
                    </button>
                    <span className="px-1 text-[11.5px] tabular-nums text-muted-foreground" aria-live="polite">{posicao}</span>
                    <button
                      type="button"
                      onClick={() => onVizinho(1)}
                      disabled={!temProximo}
                      aria-label="Próximo item"
                      className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                    >
                      <ChevronRight size={14} />
                    </button>
                  </Inline>
                ) : (
                  <span />
                )}

                <Inline gap={1} justify="end" className="gap-1.5">
                  <Button variant="ghost" size="sm" onClick={() => router.visit(`/products/${produto.id}`)}>
                    Abrir cadastro
                  </Button>
                  {perm.custo && (
                    <Button variant="ghost" size="sm" onClick={() => router.visit(`/products/${produto.id}/edit`)}>
                      Formar preço
                    </Button>
                  )}
                  <Button size="sm" onClick={() => router.visit(`/pos/create?product_id=${produto.id}`)}>
                    Usar em orçamento
                  </Button>
                </Inline>
              </Inline>
            </footer>
          </Stack>
        )}
      </SheetContent>
    </Sheet>
  );
}

export default DetalheProduto;
