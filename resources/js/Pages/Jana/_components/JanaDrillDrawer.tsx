// JanaDrillDrawer — "De onde vem esse número" para as análises do Painel (/ia).
//
// Âncora de design: prototipo-ui/cowork/jana-merge.jsx :640 (`JmDrillDrawer`).
//
// ⚠️ DIVERGÊNCIA DELIBERADA vs o protótipo, e é o ponto do componente:
// o protótipo lista fontes FICTÍCIAS (`AnaliseInadimplenciaService`,
// `AnaliseFaturamentoService`, …). Medido em 2026-08-07: nenhuma dessas classes
// existe no repo (`git grep -l Analise*Service -- Modules/ app/` → rc=1). Um
// drawer que se chama "de onde vem esse número" e cita uma classe inexistente
// é pior que não existir — ele mente com selo de autoridade.
//
// As fontes abaixo foram lidas do código real que alimenta cada card
// (`app/Services/Sells/SellsCockpitAggregator.php`) e descrevem tabela, regra e
// método de verdade. Ao mexer no aggregator, mexa aqui no mesmo PR — senão este
// arquivo vira a mentira que ele existe pra evitar.

import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Stack } from '@/Components/layout';
import { Link } from '@inertiajs/react';

/** Análise que o drawer sabe explicar. `id` casa com JANA_DRILL_FONTES. */
export interface DrillAnalise {
  id: DrillId;
  /** Título do card, reusado como título do drawer. */
  title: string;
  /** Subtítulo do card — vira "De onde vem esse número · <sub>". */
  sub: string;
  /** Leitura opcional (o "footer" do card no protótipo). */
  leitura?: string;
}

export type DrillId = 'inad' | 'fat' | 'conc' | 'metodos' | 'churn';

interface Fonte {
  /** Tabelas que o número atravessa. */
  tabelas: string;
  /** A regra que define o recorte — o que entra e o que fica de fora. */
  regra: string;
  /** Método real que calcula, renderizado como <code>. */
  metodo: string;
}

/**
 * Fonte real de cada análise, lida de `SellsCockpitAggregator` em 2026-08-07;
 * o `churn` entrou depois, no UC-13.
 *
 * Todas as cinco passam pelo mesmo recorte de base do aggregator
 * (`type=sell · status=final · sub_type NULL`) e todas são escopadas por
 * `business_id` — multi-tenant Tier 0, ADR 0093.
 */
const JANA_DRILL_FONTES: Record<DrillId, Fonte> = {
  inad: {
    tabelas: 'transactions + contacts',
    regra:
      'Vencida = a receber (paga em parte ou não paga) com prazo de pagamento definido e vencimento anterior a hoje. Venda sem prazo cadastrado não entra. As faixas agrupam pelos dias de atraso.',
    metodo: 'SellsCockpitAggregator::buildInsightsAggregates',
  },
  fat: {
    tabelas: 'transactions',
    regra:
      'Soma do total de cada venda, dia a dia, nos últimos 30 dias. Dia sem venda entra como zero — por isso a linha encosta no eixo em vez de pular o dia.',
    metodo: 'SellsCockpitAggregator::buildCoworkAggregates',
  },
  conc: {
    tabelas: 'transactions + contacts',
    regra:
      'Soma do total das vendas por cliente, os 5 maiores. Venda sem cliente identificado é somada como "Cliente padrão".',
    metodo: 'SellsCockpitAggregator::buildInsightsAggregates',
  },
  metodos: {
    tabelas: 'transaction_payments + transactions',
    regra:
      'Soma dos pagamentos registrados por forma de pagamento, as 5 maiores. Uma venda paga em duas formas aparece nas duas.',
    metodo: 'SellsCockpitAggregator::buildInsightsAggregates',
  },
  churn: {
    tabelas: 'transactions + contacts',
    regra:
      'Os 5 clientes de maior valor acumulado entre os que não compram há mais de 90 dias. "Maior valor" é relativo ao seu movimento — não há piso fixo em reais, senão a lista viria vazia em quem vende ticket pequeno. Venda sem cliente identificado fica de fora: a lista existe pra ligar pra alguém.',
    metodo: 'SellsCockpitAggregator::buildInsightsAggregates',
  },
};

function Secao({ titulo, children }: { titulo: string; children: React.ReactNode }) {
  return (
    <Stack gap={2} asChild>
      <section>
        <h3 className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">{titulo}</h3>
        {children}
      </section>
    </Stack>
  );
}

/** Rótulo curto + conteúdo — o par que compõe a seção "Fonte". */
function Campo({ rotulo, children }: { rotulo: string; children: React.ReactNode }) {
  return (
    <Stack gap={1} asChild>
      <li>
        <span className="text-xs uppercase tracking-wide text-muted-foreground opacity-70">{rotulo}</span>
        {children}
      </li>
    </Stack>
  );
}

const codigo = 'rounded bg-muted px-1.5 py-1 font-mono text-[12px] text-foreground';

export default function JanaDrillDrawer({
  analise,
  onClose,
}: {
  analise: DrillAnalise | null;
  onClose: () => void;
}) {
  const fonte = analise ? JANA_DRILL_FONTES[analise.id] : null;

  return (
    <Sheet open={!!analise} onOpenChange={(open) => !open && onClose()}>
      <SheetContent side="right" className="w-full gap-0 overflow-y-auto sm:max-w-[480px]">
        {analise && fonte && (
          <>
            <SheetHeader className="gap-1.5 border-b p-5">
              <SheetTitle className="text-base">{analise.title}</SheetTitle>
              <SheetDescription>De onde vem esse número · {analise.sub}</SheetDescription>
            </SheetHeader>

            <Stack gap={5} className="grow p-5">
              <Secao titulo="Fonte">
                <Stack gap={2} asChild>
                  <ul>
                    <Campo rotulo="Tabelas">
                      <code className={codigo}>{fonte.tabelas}</code>
                    </Campo>
                    <Campo rotulo="Regra">
                      <span className="text-sm leading-relaxed text-foreground">{fonte.regra}</span>
                    </Campo>
                    <Campo rotulo="Calculado por">
                      <code className={`${codigo} break-all`}>{fonte.metodo}</code>
                    </Campo>
                  </ul>
                </Stack>
              </Secao>

              {analise.leitura && (
                <Secao titulo="Leitura">
                  <p className="text-sm leading-relaxed text-foreground">{analise.leitura}</p>
                </Secao>
              )}

              <Secao titulo="Escopo">
                <p className="text-sm leading-relaxed text-muted-foreground">
                  Apurado no carregamento desta página, restrito ao seu negócio (
                  <code className="font-mono text-[12px]">business_id</code> da sessão). Nenhum dado de outro
                  negócio entra nesta conta.
                </p>
              </Secao>
            </Stack>

            <SheetFooter className="flex-row justify-end gap-2 border-t p-4">
              <Button variant="ghost" onClick={onClose}>
                Fechar
              </Button>
              {/* Leva pro chat SEM semear a pergunta: medido em 2026-08-07 que
                  `ChatController@novaConversa` não aceita pergunta inicial e o
                  `Chat.tsx` não lê query param. Um botão "Perguntar sobre isso"
                  descartaria o texto em silêncio — então o rótulo promete só o
                  que a rota entrega. Semear é PR próprio (backend + Page). */}
              <Link href="/ia/conversa">
                <Button>Conversar com a Jana</Button>
              </Link>
            </SheetFooter>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}
