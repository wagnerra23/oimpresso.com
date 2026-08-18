// JanaMetaDrawer — a meta abre NA PRÓPRIA TELA.
//
// Âncora: `prototipo-ui/cowork/jana-merge.jsx` §`JmMetaDrawer` — âncora de
// SÍMBOLO (ref de linha apodrece no 1º refactor, §5 2026-07-26; re-localize com
// `grep -n "JmMetaDrawer" prototipo-ui/cowork/jana-merge.jsx`).
//
// Por que existe: até 2026-08-17 o clique numa meta era um `<Link>` que TIRAVA o
// usuário do Painel rumo a uma tela Blade. O `Index-visual-comparison.md` marcava
// isso como o maior buraco da tela (R5, ordem 1). O caminho pra tela própria não
// se perdeu — virou o rodapé deste drawer.
//
// DUAS DIVERGÊNCIAS DELIBERADAS vs a âncora, e as duas são o mesmo princípio:
//
//   1. ~~SEM "Projeção"~~ — **RESOLVIDO em 2026-08-18 (onda 5)**. O texto abaixo
//      fica como registro do que era verdade quando foi escrito:
//
//        > O protótipo projeta o fechamento derivando a série no FRONTEND
//        > (`jmMeta()`). Fazer isso aqui repetiria o defeito que o charter já
//        > catalogou no farol — §Anti-hooks "⛔ Cálculo de farol no frontend".
//        > Uma projeção é veredito sobre o futuro; ela nasce no servidor ou não
//        > nasce.
//
//      O princípio segue INTEIRO — e é justamente ele que destrava: o servidor
//      JÁ calcula (`ApuracaoService::projecao`) e JÁ manda no payload de `/ia`
//      (`IndexController`: `'projecao' => $apuracao->projecao($meta)`), pela mesma
//      porta do `farol`. O campo estava chegando e NINGUÉM lia — `rg projecao
//      resources/js/Pages/Jana/` não devolvia consumidor nenhum. A razão de 08-17
//      argumentava contra CALCULAR no frontend; aqui não se calcula nada, só se
//      consome. "% do alvo" continua onde estava.
//
//   2. SEM a "nota" do card. No protótipo cada meta traz uma frase explicando o
//      movimento ("mix de produto puxando pra baixo"). O payload de `/ia` não
//      tem esse campo — inventar a frase seria a mesma mentira com selo de
//      autoridade que o `JanaDrillDrawer` existe pra evitar.

import { Link } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Grid, Inline, Stack } from '@/Components/layout';
import {
  FAROL_CLASSES,
  farolDaMeta,
  formatValue,
  type Apuracao,
  type Meta,
} from './metaFormat';

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

/** Par rótulo/valor em coluna — o formato de "Origem do número" (mesmo do JanaDrillDrawer). */
function Linha({ rotulo, valor }: { rotulo: string; valor: string }) {
  return (
    <Stack gap={0}>
      <span className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
        {rotulo}
      </span>
      <span className="text-sm leading-snug text-foreground">{valor}</span>
    </Stack>
  );
}

/** Um número grande com rótulo — o trio da seção "Situação". */
function Numero({ rotulo, valor }: { rotulo: string; valor: string }) {
  return (
    <div className="rounded-lg border border-border bg-card p-3">
      <small className="block text-[11px] uppercase tracking-wide text-muted-foreground">{rotulo}</small>
      <b className="mt-0.5 block text-lg font-semibold tabular-nums text-foreground">{valor}</b>
    </div>
  );
}

/**
 * Série em barras — o `JmSerie` da âncora.
 *
 * Escala pelo MÁXIMO da própria série (não pelo alvo): a leitura aqui é a forma
 * do movimento, e uma meta muito abaixo do alvo viraria 12 tocos indistinguíveis
 * se o alvo fosse o teto. `Math.max(..., 1)` porque série toda em zero dividiria
 * por zero e apagaria as barras.
 */
function Serie({ dados, unidade }: { dados: Apuracao[]; unidade: string }) {
  const max = Math.max(...dados.map((d) => d.valor_realizado), 1);

  return (
    <Inline gap={1} align="end" className="h-24" role="img" aria-label={`Série de ${dados.length} janelas`}>
      {dados.map((d) => (
        <div
          key={d.data_ref}
          className="flex-1 rounded-t bg-primary/70"
          style={{ height: `${Math.max(4, (d.valor_realizado / max) * 100)}%` }}
          title={`${d.data_ref.slice(0, 10)} · ${formatValue(d.valor_realizado, unidade)}`}
        />
      ))}
    </Inline>
  );
}

const codigo = 'rounded bg-muted px-1.5 py-1 font-mono text-[12px] text-foreground';

export default function JanaMetaDrawer({
  meta,
  // Período já formatado pelo card. Chega como prop pra que `periodoLabel`
  // continue morando num lugar só (`Index.tsx`) — ele é do card, e duplicar a
  // regra de "mai–jul/2026" nos dois lados é como as duas versões drifam.
  periodo,
  onClose,
}: {
  meta: Meta | null;
  periodo: string | null;
  onClose: () => void;
}) {
  if (!meta) {
    return <Sheet open={false} onOpenChange={() => undefined} />;
  }

  const farol = farolDaMeta(meta);
  const realizado = meta.ultima_apuracao?.valor_realizado ?? null;
  const alvo = meta.periodo_atual?.valor_alvo ?? null;
  // Mesmo cálculo do card (`Index.tsx`), inclusive o teto em 100 — os dois
  // mostram "% do alvo" e divergir aqui seria a tela contradizendo a si mesma.
  const progresso = alvo && realizado !== null ? Math.min(100, (realizado / alvo) * 100) : null;
  const serie = meta.apuracoes_recentes;
  // Vem do servidor; a Page só consome (mesma porta do `farol`).
  const projecao = meta.projecao ?? null;

  // Delta contra a janela anterior — subtração entre dois valores APURADOS, não
  // previsão. Só aparece com duas janelas; com uma só não há "anterior".
  const anterior = serie.length >= 2 ? serie[serie.length - 2]!.valor_realizado : null;
  const ultimo = serie.length >= 1 ? serie[serie.length - 1]!.valor_realizado : null;
  const delta = anterior !== null && ultimo !== null ? ultimo - anterior : null;

  return (
    <Sheet open onOpenChange={(open) => !open && onClose()}>
      <SheetContent side="right" className="w-full gap-0 overflow-y-auto sm:max-w-[520px]">
        <SheetHeader className="gap-1.5 border-b p-5">
          <SheetTitle className="text-base" asChild>
            <Inline gap={2} align="center">
              <span aria-hidden className={`inline-block size-2 shrink-0 rounded-full ${FAROL_CLASSES[farol]}`} />
              {meta.nome}
            </Inline>
          </SheetTitle>
          <SheetDescription>
            Meta ativa{periodo ? ` · ${periodo}` : ''} · apuração das últimas {serie.length}{' '}
            {serie.length === 1 ? 'janela' : 'janelas'}
          </SheetDescription>
          <Badge variant="outline" className="w-fit">
            farol {farol}
          </Badge>
        </SheetHeader>

        <Stack gap={5} className="grow p-5">
          <Secao titulo="Situação">
            <Grid cols={3} gap={2}>
              <Numero rotulo="Realizado" valor={realizado !== null ? formatValue(realizado, meta.unidade) : '—'} />
              <Numero rotulo="Alvo" valor={alvo !== null ? formatValue(alvo, meta.unidade) : '—'} />
              <Numero rotulo="% do alvo" valor={progresso !== null ? `${progresso.toFixed(0)}%` : '—'} />
            </Grid>
            {delta !== null && (
              <p className={`text-sm ${delta >= 0 ? 'text-success' : 'text-destructive'}`}>
                {delta >= 0 ? '+' : '−'}
                {formatValue(Math.abs(delta), meta.unidade)} vs a janela anterior
              </p>
            )}
            {/* Projeção — SERVIDOR (`ApuracaoService::projecao`), nunca derivada aqui.
                `null` = sem base pra projetar; nesse caso não se desenha nada, porque
                ausência de projeção não é projeção de zero. */}
            {projecao && (
              <p className="text-sm text-muted-foreground">
                No ritmo atual fecha em{' '}
                <strong className="font-semibold text-foreground tabular-nums">
                  {formatValue(projecao.projetado, meta.unidade)}
                </strong>{' '}
                <span className="tabular-nums">
                  ({projecao.desvio_pct >= 0 ? '+' : '−'}
                  {Math.abs(projecao.desvio_pct).toFixed(0)}% vs o ritmo necessário)
                </span>
              </p>
            )}
          </Secao>

          {/* Origem do número — mesmo contrato do `JanaDrillDrawer` (tabela · regra ·
              método), que é o padrão já validado desta área. Onda 5 da paridade:
              a âncora (§`JmMetaDrawer`) tem "Origem do número" e "Escopo", e o drawer
              vivo não tinha nenhum dos dois.

              ⚠️ Os valores abaixo são LIDOS do código, não inferidos do protótipo: a
              própria âncora cita 6 `Analise*Service` que NÃO existem no repo (o
              `ancora.mjs` acusa), então o que ela diz sobre FONTE DE DADO não vale —
              só o que ela diz sobre forma visual. */}
          <Secao titulo="Origem do número">
            <Stack gap={1}>
              <Linha rotulo="Tabelas" valor="jana_metas · jana_meta_periodos · jana_meta_apuracoes · jana_meta_fontes" />
              <Linha
                rotulo="Regra"
                valor="Apuração do período vigente da meta; o driver de cada meta vive em jana_meta_fontes."
              />
              <Linha rotulo="Método" valor="ApuracaoService::apurar · ::farol · ::projecao" />
              {/* Escopo — Tier 0 visível ao operador, como no JanaDrillDrawer. */}
              <Linha rotulo="Escopo" valor="Somente a empresa da sessão (business_id) — ADR 0093." />
            </Stack>
          </Secao>

          <Secao titulo={`Série · ${serie.length} ${serie.length === 1 ? 'janela' : 'janelas'}`}>
            {serie.length === 0 ? (
              // Mesma copy do contrato (`painel-meta-sem-historico`): ausência de
              // dado se declara, não se desenha como zero.
              <p className="text-sm text-muted-foreground">Sem histórico</p>
            ) : (
              <>
                <Serie dados={serie} unidade={meta.unidade} />
                <Inline justify="between" className="text-[10px] text-muted-foreground">
                  <span>início da série</span>
                  <span>{periodo ?? 'agora'}</span>
                </Inline>
              </>
            )}
          </Secao>

          <Secao titulo="De onde vem esse número">
            <Stack gap={2} asChild>
              <ul className="text-sm leading-relaxed text-muted-foreground">
                <li>
                  Farol apurado no servidor por <code className={codigo}>ApuracaoService::farol</code> — a tela
                  só exibe o veredito que chega.
                  {/* A âncora cita aqui outra classe: ela existe, mas o método
                      `farol` NÃO é dela (charter v4, PR 5394 — o número vai sem
                      cerquilha de propósito: o R1 do `ui:lint` lê "cerquilha +
                      4 dígitos" como literal hexadecimal, e o ratchet acusa).
                      O nome certo é o
                      de cima — e o `ancora.mjs` acusa símbolo de backend
                      inexistente justamente pra este tipo de citação.
                      O nome errado não é repetido aqui de propósito: o UC-11
                      asserta a ausência dele, e citá-lo criaria o falso-positivo
                      que o §5 2026-07-26 cataloga. */}
                </li>
                <li>
                  Restrito ao seu negócio (<code className="font-mono text-[12px]">business_id</code> da sessão).
                  Nenhuma meta de outro negócio entra nesta conta.
                </li>
                <li>
                  Série lida de <code className={codigo}>jana_meta_apuracoes</code>, as 12 janelas mais recentes.
                </li>
              </ul>
            </Stack>
          </Secao>
        </Stack>

        <SheetFooter className="flex-row justify-end gap-2 border-t p-4">
          <Button variant="ghost" onClick={onClose}>
            Fechar
          </Button>
          {/* Rótulo "Abrir a meta", não "Editar": o destino é a tela de leitura
              (`show`) — que é pra onde o card já apontava antes deste drawer, e
              nenhuma capacidade se perdeu. Prometer "editar" mandaria o usuário
              pra um lugar que não é o formulário. */}
          <Link href={`/ia/metas/${meta.id}`}>
            <Button variant="outline">Abrir a meta</Button>
          </Link>
          {/* Sem semear a pergunta, pelo mesmo motivo medido no JanaDrillDrawer:
              `ChatController@novaConversa` não aceita pergunta inicial e o
              `Chat.tsx` não lê query param. */}
          <Link href="/ia/conversa">
            <Button>Conversar com a Jana</Button>
          </Link>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
