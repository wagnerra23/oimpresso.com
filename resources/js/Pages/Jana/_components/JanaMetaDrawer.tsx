// JanaMetaDrawer — abre a meta NA PRÓPRIA TELA, em vez de mandar o usuário embora.
//
// Âncora de design: `prototipo-ui/cowork/jana-merge.jsx` §`JmMetaDrawer` — âncora de
// SÍMBOLO, não de linha (`grep -n "JmMetaDrawer" prototipo-ui/cowork/jana-merge.jsx`).
//
// POR QUE EXISTE: no protótipo, clicar numa meta abre um drawer com situação, série e
// origem do número. Na tela viva o card era um `<Link href="/ia/metas/{id}">` — o
// usuário perdia o painel inteiro pra ver 12 barras. Era a maior divergência medida
// no mapa por região (`memory/requisitos/Jana/Index-visual-comparison.md`, R5).
//
// ⚠️ DIVERGÊNCIA DELIBERADA vs o protótipo, pelo mesmo motivo do JanaDrillDrawer:
// a seção "Origem do número" do protótipo cita `MetricasApurador::farol`. Essa classe
// EXISTE, mas **não tem** esse método — ela é de métrica da própria Jana (latência,
// tokens), domínio diferente. O real é `ApuracaoService::farol`, e o charter já
// registrava esse erro desde a v4. Citar o nome errado num painel que se chama "origem
// do número" é mentir com selo de autoridade.
//
// A projeção NÃO é calculada aqui: vem pronta do servidor (`ApuracaoService::projecao`),
// porque o §Anti-hooks do charter proíbe cálculo de farol na Page — e refazer a mesma
// conta com outro nome seria a mesma doença.

import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Stack } from '@/Components/layout';
import { Link } from '@inertiajs/react';

export interface MetaDrawerApuracao {
  data_ref: string;
  valor_realizado: number;
}

export interface MetaDrawerMeta {
  id: number;
  nome: string;
  unidade: string;
  farol?: 'verde' | 'amarelo' | 'vermelho' | 'cinza';
  periodo_atual: { data_ini: string; data_fim: string; valor_alvo: number } | null;
  ultima_apuracao: MetaDrawerApuracao | null;
  apuracoes_recentes: MetaDrawerApuracao[];
  /** Do servidor. `null` = sem base pra projetar (os mesmos casos do farol 'cinza'). */
  projecao?: { progresso: number; projetado: number; desvio_pct: number } | null;
}

/** Mesmo vocabulário de variante do resto do módulo — token, nunca escala crua. */
const FAROL_BADGE: Record<string, { variante: 'success' | 'warning' | 'danger' | 'neutral'; rotulo: string }> = {
  verde:    { variante: 'success', rotulo: 'no rumo' },
  amarelo:  { variante: 'warning', rotulo: 'atenção' },
  vermelho: { variante: 'danger',  rotulo: 'fora do rumo' },
  cinza:    { variante: 'neutral', rotulo: 'sem base pra julgar' },
};

function formatar(valor: number, unidade: string) {
  if (unidade === 'R$') {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(valor);
  }
  if (unidade === '%') return `${valor.toFixed(1)}%`;
  return new Intl.NumberFormat('pt-BR').format(valor);
}

function formatarData(iso: string) {
  try {
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
  } catch {
    return iso;
  }
}

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

/** Série como barras — a leitura é a FORMA da curva, então o eixo é o próprio máximo. */
function Serie({ dados, unidade }: { dados: MetaDrawerApuracao[]; unidade: string }) {
  const valores = dados.map((d) => d.valor_realizado);
  const max = Math.max(...valores, 0);

  if (!dados.length) {
    return <p className="text-sm text-muted-foreground">Sem apuração no período — nada a desenhar.</p>;
  }

  return (
    <Stack gap={2}>
      <div className="flex h-24 items-end gap-1" role="img" aria-label={`Série de ${dados.length} apurações`}>
        {dados.map((d, i) => (
          <div
            key={`${d.data_ref}-${i}`}
            className="flex-1 rounded-t bg-primary/70"
            // `max || 1` evita divisão por zero quando toda a série é 0 — sem isso
            // as barras somem e a tela parece quebrada em vez de "tudo zerado".
            style={{ height: `${Math.max(4, (d.valor_realizado / (max || 1)) * 100)}%` }}
            title={`${formatarData(d.data_ref)} · ${formatar(d.valor_realizado, unidade)}`}
          />
        ))}
      </div>
      <div className="flex justify-between text-xs text-muted-foreground">
        <span>{formatarData(dados[0]!.data_ref)}</span>
        <span>{formatarData(dados[dados.length - 1]!.data_ref)}</span>
      </div>
    </Stack>
  );
}

const codigo = 'rounded bg-muted px-1.5 py-1 font-mono text-[12px] text-foreground';

export default function JanaMetaDrawer({ meta, onClose }: { meta: MetaDrawerMeta | null; onClose: () => void }) {
  const farol = meta?.farol ?? 'cinza';
  const badge = FAROL_BADGE[farol]!;
  const realizado = meta?.ultima_apuracao?.valor_realizado ?? null;
  const alvo = meta?.periodo_atual?.valor_alvo ?? null;
  const projecao = meta?.projecao ?? null;

  return (
    <Sheet open={!!meta} onOpenChange={(aberto) => !aberto && onClose()}>
      <SheetContent side="right" className="w-full gap-0 overflow-y-auto sm:max-w-[520px]">
        {meta && (
          <>
            <SheetHeader className="gap-2 border-b p-5">
              <div className="flex items-start justify-between gap-3">
                <SheetTitle className="text-base">{meta.nome}</SheetTitle>
                <Badge variant={badge.variante}>{badge.rotulo}</Badge>
              </div>
              <SheetDescription>
                {meta.periodo_atual
                  ? `Período ${formatarData(meta.periodo_atual.data_ini)} – ${formatarData(meta.periodo_atual.data_fim)}`
                  : 'Sem período ativo'}
                {' · '}
                {meta.apuracoes_recentes.length} {meta.apuracoes_recentes.length === 1 ? 'apuração' : 'apurações'}
              </SheetDescription>
            </SheetHeader>

            <Stack gap={5} className="grow p-5">
              <Secao titulo="Situação">
                <div className="grid grid-cols-3 gap-3">
                  <Stack gap={1}>
                    <span className="text-xs uppercase tracking-wide text-muted-foreground">Realizado</span>
                    <b className="text-lg tabular-nums">
                      {realizado !== null ? formatar(realizado, meta.unidade) : '—'}
                    </b>
                  </Stack>
                  <Stack gap={1}>
                    <span className="text-xs uppercase tracking-wide text-muted-foreground">Alvo</span>
                    <b className="text-lg tabular-nums">{alvo !== null ? formatar(alvo, meta.unidade) : '—'}</b>
                  </Stack>
                  <Stack gap={1}>
                    <span className="text-xs uppercase tracking-wide text-muted-foreground">Projeção</span>
                    {/* Traço quando o servidor manda `null`: é "não há base pra
                        projetar", e escrever 0 aqui seria afirmar um número que
                        ninguém calculou. */}
                    <b className="text-lg tabular-nums">
                      {projecao ? formatar(projecao.projetado, meta.unidade) : '—'}
                    </b>
                  </Stack>
                </div>

                {projecao ? (
                  <p className="text-sm leading-relaxed text-muted-foreground">
                    O período está <b className="text-foreground">{Math.round(projecao.progresso * 100)}%</b> percorrido.
                    Nesse ritmo, o esperado até aqui seria {formatar(projecao.projetado, meta.unidade)} — o realizado está{' '}
                    <b className="text-foreground">
                      {projecao.desvio_pct >= 0 ? '+' : ''}
                      {projecao.desvio_pct.toFixed(1)}%
                    </b>{' '}
                    em relação a isso.
                  </p>
                ) : (
                  <p className="text-sm leading-relaxed text-muted-foreground">
                    Não há base pra projetar — falta período ativo, falta apuração, ou o período ainda não começou. Por
                    isso o farol está cinza, que quer dizer <b className="text-foreground">não dá pra dizer</b>, e não
                    "indo mal".
                  </p>
                )}
              </Secao>

              <Secao titulo={`Série · ${meta.apuracoes_recentes.length} apurações`}>
                <Serie dados={meta.apuracoes_recentes} unidade={meta.unidade} />
              </Secao>

              <Secao titulo="Origem do número">
                <Stack gap={2} asChild>
                  <ul className="text-sm leading-relaxed text-muted-foreground">
                    <li>
                      Farol e projeção apurados no servidor por <code className={codigo}>ApuracaoService::farol</code> e{' '}
                      <code className={codigo}>ApuracaoService::projecao</code> — esta tela só consome.
                    </li>
                    <li>
                      Restrito ao seu negócio (<code className="font-mono text-[12px]">business_id</code> da sessão).
                      Nenhuma meta de outro negócio entra nesta conta.
                    </li>
                    <li>Editar a meta abre a tela própria, em modo foco.</li>
                  </ul>
                </Stack>
              </Secao>
            </Stack>

            <SheetFooter className="flex-row justify-end gap-2 border-t p-4">
              <Button variant="ghost" onClick={onClose}>
                Fechar
              </Button>
              <Link href={`/ia/metas/${meta.id}`}>
                <Button variant="outline">Editar meta</Button>
              </Link>
              {/* Mesmo limite do JanaDrillDrawer: o rótulo promete só o que a rota
                  entrega. `ChatController@novaConversa` não aceita pergunta inicial. */}
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
