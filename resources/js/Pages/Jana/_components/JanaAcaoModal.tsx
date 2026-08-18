// JanaAcaoModal — confirmação HITL das ações sugeridas do Painel (/ia).
//
// Âncora de design: prototipo-ui/cowork/jana-merge.jsx §`JmAcaoModal` — âncora de
// SÍMBOLO, nunca de linha (`grep -n "JmAcaoModal" prototipo-ui/cowork/jana-merge.jsx`;
// resolva a âncora com `node prototipo-ui/ancora.mjs Jana/Index`, não no olho).
//
// ⚠️ DIVERGÊNCIA DELIBERADA vs o protótipo, e é o ponto do componente — a mesma
// que o `JanaDrillDrawer` já registra no eixo da FONTE, agora no eixo da PRÉVIA:
// o `JmAcaoModal` traz as 4 prévias em texto FIXO, com números do Martinho
// (biz=164), e cita `Analise*Service` que não existem no repo (medido 2026-08-17
// no espelho E no Cowork vivo). Aqui a prévia vem de `GET /ia/acoes/{key}/previa`,
// gerada por `AcaoHitlService` a partir do MESMO agregado que pinta a linha da
// ação (`SellsCockpitAggregator::buildInsightsAggregates`). Prévia escrita no
// cliente seria a mentira com selo de autoridade que o drill existe pra evitar.
//
// MODAL, e não Drawer: é confirmação (decisão bloqueante), não detalhe — o drill
// e a meta são Sheet porque o usuário LÊ e volta; aqui ele decide.
//
// ⚠️ ESCOPO: "Aprovar" REGISTRA a decisão. Nada é enviado — o disparo é PR
// próprio, e é por isso que o CTA da linha diz "Revisar", não "Disparar".
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack } from '@/Components/layout';

/** A ação em confirmação. Só o que o modal EXIBE — a prévia ele mesmo busca. */
export interface AcaoHitl {
  /** `acao_key` — casa 1:1 com `AcaoHitlService::ACOES`. */
  id: string;
  title: string;
  sub: string;
}

type Fase = 'carregando' | 'pronto' | 'enviando' | 'erro';

interface PreviaResposta {
  previa: string;
  /** Destinatários atingidos, ou `null` quando a ação é leitura (não manda nada). */
  alcance: number | null;
}

export default function JanaAcaoModal({
  acao,
  onClose,
}: {
  acao: AcaoHitl | null;
  onClose: () => void;
}) {
  const [fase, setFase] = useState<Fase>('carregando');
  const [previa, setPrevia] = useState<string | null>(null);
  const [alcance, setAlcance] = useState<number | null>(null);

  // `fetch` e não visita Inertia: a prévia enche um parágrafo de overlay; uma
  // visita recarregaria as props da página inteira (inclusive a deferida) pra isso.
  useEffect(() => {
    if (!acao) return;
    // `vivo` guarda contra a resposta de uma ação já fechada/trocada sobrescrever
    // a prévia da ação atual — trocar de linha rápido é o caso real.
    let vivo = true;
    setFase('carregando');
    setPrevia(null);
    setAlcance(null);

    fetch(`/ia/acoes/${acao.id}/previa`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((d: PreviaResposta) => {
        if (!vivo) return;
        setPrevia(d.previa);
        setAlcance(d.alcance ?? null);
        setFase('pronto');
      })
      .catch(() => {
        if (vivo) setFase('erro');
      });

    return () => {
      vivo = false;
    };
  }, [acao]);

  const aprovar = () => {
    if (!acao) return;
    setFase('enviando');
    // `router.post` e não `fetch`: o Inertia já resolve CSRF e o flash `success`
    // do `back()` vira toast pelo handler global do `app.tsx`
    // (`router.on('success')` → `showFlashToast`). Montar um segundo caminho de
    // request aqui — ou um `useEffect` de toast na Page — daria toast em dobro.
    router.post(
      `/ia/acoes/${acao.id}/aprovar`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onError: () => setFase('erro'),
      },
    );
  };

  return (
    <Dialog open={!!acao} onOpenChange={(aberto) => !aberto && onClose()}>
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle className="text-base">{acao?.title}</DialogTitle>
          <DialogDescription>{acao?.sub}</DialogDescription>
        </DialogHeader>

        <Stack gap={3}>
          <section className="rounded-md border border-border bg-muted/40 p-3">
            <h3 className="text-[10.5px] font-semibold uppercase tracking-widest text-muted-foreground">
              O que a Jana preparou
            </h3>
            {fase === 'carregando' ? (
              <Skeleton className="mt-2 h-12 w-full" />
            ) : fase === 'erro' ? (
              <p data-contract="painel-acao-erro" className="mt-2 text-sm text-destructive">
                Não deu pra carregar a prévia. Tente de novo — nada foi aprovado.
              </p>
            ) : (
              <p className="mt-2 text-sm leading-relaxed text-foreground">{previa}</p>
            )}
          </section>

          <Stack gap={1} asChild>
            <ul className="text-xs leading-relaxed text-muted-foreground">
              <li>
                Você aprova <strong className="font-medium text-foreground">cada</strong> ação antes do
                envio — a Jana não dispara sozinha.
              </li>
              <li>
                Escopo <code className="font-mono">business_id</code> da sessão — nada cruza empresa.
              </li>
              {alcance !== null && (
                <li>
                  Alcance:{' '}
                  <strong className="font-medium text-foreground">{alcance}</strong> destinatário
                  {alcance === 1 ? '' : 's'}.
                </li>
              )}
              {/* Literal, porque é exatamente o que este PR entrega: registro
                  auditável, não envio. Sem esta linha o botão "Aprovar" herdaria
                  a promessa que o CTA acabou de largar. */}
              <li>Aprovar registra sua decisão. O envio entra quando o disparo for ligado.</li>
            </ul>
          </Stack>
        </Stack>

        <DialogFooter className="gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={aprovar} disabled={fase !== 'pronto'}>
            {fase === 'enviando' ? 'Registrando…' : 'Aprovar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
