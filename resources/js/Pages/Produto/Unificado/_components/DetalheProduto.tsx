/**
 * Drawer de detalhe do produto (padrão PT-02) — arquivo próprio: é a tela dentro da tela, e é
 * o SEGUNDO lugar onde a autorização de custo precisa ser respeitada (handoff §7).
 *
 * Ordem das seções (handoff V2 §5): Estado → Estoque (com "Por local") → Variações →
 * Observações → Formação de preço. É a ordem da pergunta do balcão: "tem?", "onde?", "qual?",
 * "tem pegadinha?", "quanto?". Cadastro completo é outra tela — o rodapé leva pra lá.
 *
 * Cada seção só é montada quando tem dado. Seção vazia com título afirma que o dado existe e
 * que o app falhou em buscá-lo.
 *
 * Largura 420px (o handoff mede 410): cabe ao lado da lista no monitor 1280 da Larissa sem
 * empurrar a tabela pra fora. É deliberadamente mais estreito que o drawer de 760 do cadastro
 * de cliente, porque aqui não se edita nada.
 */

import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Inline, Stack } from '@/Components/layout';
import Disponibilidade from './Disponibilidade';
import { AlertTriangle } from 'lucide-react';
import {
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

function Linha({ rotulo, children }: { rotulo: string; children: ReactNode }) {
  return (
    <Inline gap={4} align="baseline" justify="between">
      <span className="text-[12.5px] text-muted-foreground">{rotulo}</span>
      {children}
    </Inline>
  );
}

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="px-6 py-4 border-b border-border">
      <h3 className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-3">{titulo}</h3>
      <Stack gap={2}>{children}</Stack>
    </section>
  );
}

export interface DetalheProdutoProps {
  produto: ProdutoRow | null;
  perm: Permissoes;
  piso: number;
  onFechar: () => void;
}

export function DetalheProduto({ produto, perm, piso, onFechar }: DetalheProdutoProps) {
  const aberto = produto !== null;
  const estado = produto ? estadoEstoque(produto) : null;
  const zerado = produto?.stockQty === 0;
  const margem = produto ? margemFrac(produto) : undefined;
  const locais = produto?.locais ?? [];
  const variacoes = produto?.variacoes ?? [];

  // Mesmo alerta do popover da linha, mesma regra: só no caso MISTO (zero em um local, saldo
  // em outro). Tudo zerado já é o badge vermelho do topo do painel.
  const zeradosNoLocal = locais.filter((l) => l.qtd === 0);
  const alertaLocal = zeradosNoLocal.length > 0 && locais.some((l) => l.qtd > 0)
    ? `0 na ${zeradosNoLocal.map((l) => l.nome).join(' e ')} — saldo disponível em outro local.`
    : '';

  return (
    <Sheet open={aberto} onOpenChange={(v) => { if (!v) onFechar(); }}>
      <SheetContent side="right" className="w-[420px] sm:max-w-[420px] p-0">
        {produto && estado && (
          <Stack gap={0} className="h-full">
            <SheetHeader className="px-6 py-4 border-b border-border space-y-2 text-left">
              <SheetTitle className="text-[15px] font-semibold uppercase leading-snug pr-6">
                {produto.name}
              </SheetTitle>
              <SheetDescription className="font-mono text-[11.5px] tabular-nums">
                {produto.codigo}
                {produto.referencia ? ` · ${produto.referencia}` : ''}
              </SheetDescription>
              <div className="pt-1">
                <Disponibilidade estado={estado} />
              </div>
            </SheetHeader>

            <div className="flex-1 overflow-y-auto">
              <Secao titulo="Estoque">
                <Linha rotulo="Saldo atual">
                  {/* Vermelho quando zerado: "sem estoque" bloqueia venda, é dado [V0]. */}
                  <span className={'font-mono tabular-nums text-[16px] font-semibold ' + (zerado ? 'text-destructive-fg' : 'text-foreground')}>
                    {saldoTexto(produto)}
                  </span>
                </Linha>
                <Linha rotulo="Mínimo">
                  <span className="font-mono tabular-nums text-[12.5px]">
                    {produto.minimo === null ? '—' : `${produto.minimo} ${produto.unit}`}
                  </span>
                </Linha>
                <Linha rotulo="Unidade">
                  <span className="font-mono text-[12.5px]">{produto.unit}</span>
                </Linha>
                <Linha rotulo="Última venda">
                  <span className="font-mono tabular-nums text-[12.5px]">
                    {produto.ultimaVenda ?? 'sem registro'}
                  </span>
                </Linha>

                {/* "Por local" (V2 §5): a mesma informação do popover da linha, aqui aberta —
                    no painel há espaço, e quem abriu o painel já demonstrou o interesse que o
                    popover exige um gesto pra revelar. */}
                {locais.length > 0 && (
                  <div className="pt-2 mt-1 border-t border-border">
                    <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1.5">
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

              {/* Variações — o que existe e quantos valores cada atributo tem. O nome do
                  atributo é texto livre do cadastro do tenant, então vai literal. */}
              {variacoes.length > 0 && (
                <Secao titulo="Variações">
                  {variacoes.map((v) => (
                    <Linha key={v.nome} rotulo={v.nome}>
                      <span className="font-mono tabular-nums text-[12.5px]">{v.n}</span>
                    </Linha>
                  ))}
                  {/* Combinações só faz sentido com DOIS ou mais atributos: com um só, o número
                      repetiria a linha de cima. */}
                  {variacoes.length > 1 && (
                    <Linha rotulo="Combinações">
                      <span className="font-mono tabular-nums text-[12.5px] font-semibold">
                        {variacoes.reduce((acc, v) => acc * v.n, 1)}
                      </span>
                    </Linha>
                  )}
                </Secao>
              )}

              {produto.obs && (
                <Secao titulo="Observações">
                  <p className="text-[12.5px] leading-relaxed whitespace-pre-line text-foreground">
                    {produto.obs}
                  </p>
                </Secao>
              )}

              {/* A seção inteira só existe pra quem pode ver ALGUM dos dois valores. Um card
                  vazio "Formação de preço" seria dizer que o dado existe e o app quebrou. */}
              {(perm.preco || perm.custo) && (
                <Secao titulo="Formação de preço">
                  {/* Custo só existe pra quem tem papel — mesma regra da tabela. */}
                  {perm.custo && produto.cost !== undefined && (
                    <Linha rotulo="Custo">
                      <span className="font-mono tabular-nums text-[12.5px]">{brl(produto.cost)}</span>
                    </Linha>
                  )}
                  {perm.preco && produto.price !== undefined && (
                    <Linha rotulo="Preço de venda">
                      <span className="font-mono tabular-nums text-[12.5px] font-semibold">{brl(produto.price)}</span>
                    </Linha>
                  )}
                  {perm.custo && perm.preco && margem !== undefined && (
                    <Linha rotulo="Margem">
                      <span className={'font-mono tabular-nums text-[16px] font-semibold ' + (sobOPiso(produto, piso) ? 'text-destructive-fg' : 'text-foreground')}>
                        {pct(margem)}
                      </span>
                    </Linha>
                  )}
                </Secao>
              )}

              {perm.composicao && produto.bomCount !== undefined && (
                <Secao titulo="Composição">
                  <Linha rotulo="Itens na receita">
                    <span className="font-mono tabular-nums text-[12.5px]">{produto.bomCount}</span>
                  </Linha>
                </Secao>
              )}
            </div>

            <Inline gap={2} justify="end" asChild>
              <footer className="px-6 py-3 border-t border-border">
                <Button variant="ghost" size="sm" onClick={onFechar}>Fechar</Button>
                <Button size="sm" onClick={() => router.visit(`/products/${produto.id}`)}>Abrir cadastro</Button>
              </footer>
            </Inline>
          </Stack>
        )}
      </SheetContent>
    </Sheet>
  );
}

export default DetalheProduto;
