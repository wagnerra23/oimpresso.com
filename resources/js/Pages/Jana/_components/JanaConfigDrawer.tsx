// JanaConfigDrawer — "Configurar a Jana" no Painel (/ia).
//
// Âncora de design: `prototipo-ui/cowork/jana-merge.jsx` §`JmConfigDrawer` —
// âncora de SÍMBOLO, nunca linha (ref de linha apodrece no 1º refactor, §5
// 2026-07-26). Re-localize com:
//   grep -n "JmConfigDrawer" prototipo-ui/cowork/jana-merge.jsx
//
// ── DIVERGÊNCIA DELIBERADA vs o protótipo, e é o ponto do componente ─────────
// O protótipo entrega 4 promessas que o servidor NÃO cumpre hoje. Portá-las
// como toggle reintroduziria exatamente a classe que o contrato desta tela já
// barrou em `_pendente_w` — *"a MESMA classe do rodapé do brief que prometia
// 'próximo brief: amanhã, 8h' — promessa sem data que o produto não cumpre"*.
// Medido em 2026-08-17:
//
//   protótipo                     | estado real
//   ------------------------------|--------------------------------------------
//   6 toggles de análise          | a tela tem 5 cards (inad/fat/conc/metodos/
//                                 | churn); frota e cheques NÃO existem — o mapa
//                                 | põe isso na ordem 7 ("fonte de dado que não
//                                 | existe"), Index-visual-comparison.md.
//   "Enviar brief todo dia" +hora | o brief é gerado server-side (BriefingAgent);
//                                 | nenhum cron lê o localStorage deste browser.
//   "Versão em áudio (TTS)"       | não existe — o próprio protótipo diz que
//                                 | "entra na M2, fora deste protótipo".
//   "Retenção · ela esquece       | `jana:retention-purge` foi DESCARTADO por
//    sozinha"                     | decisão [W] ("num ERP não se apaga PII").
//
// Fica só o que é VERDADE e é de fato local: quais análises aparecem no painel.
// Esse toggle não mente porque não promete cálculo — o aggregator segue apurando
// tudo; o que muda é o que a tela MOSTRA, e disso o localStorage é dono legítimo.
// Brief e avisos apontam pro dono real que já existe (`/ia/alertas/config` →
// `business.essentials_settings.alertas`, per-business, Tier 0), em vez de
// ganharem um segundo dono aqui.
//
// O modelo, a chave de `localStorage` e o hook moram em `useJanaConfig.ts` —
// arquivo de componente não exporta não-componente (`react-refresh`).

import { Link } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Switch } from '@/Components/ui/switch';
import { Inline, Stack } from '@/Components/layout';
import { JANA_ANALISES, type JanaAnaliseId, type JanaConfig } from './useJanaConfig';

function Secao({ titulo, children }: { titulo: string; children: React.ReactNode }) {
  return (
    <Stack gap={3} asChild>
      <section>
        <h3 className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">{titulo}</h3>
        {children}
      </section>
    </Stack>
  );
}

/** Linha rótulo+descrição à esquerda, controle à direita — o `jm-cfg-row`. */
function Linha({
  titulo,
  sub,
  htmlFor,
  children,
}: {
  titulo: React.ReactNode;
  sub: React.ReactNode;
  htmlFor?: string;
  children: React.ReactNode;
}) {
  return (
    <Inline gap={4} justify="between" align="start">
      <Stack gap={1}>
        {/* `shadcn` e não o default `cowork`: aqui o rótulo é o texto PRIMÁRIO
            da linha (o `.cw-label` é 11.5px dim, feito pra rotular um valor). */}
        <Label htmlFor={htmlFor} variant="shadcn">
          {titulo}
        </Label>
        <span className="text-xs leading-relaxed text-muted-foreground">{sub}</span>
      </Stack>
      {children}
    </Inline>
  );
}

export default function JanaConfigDrawer({
  open,
  onClose,
  config,
  onAlternarAnalise,
}: {
  open: boolean;
  onClose: () => void;
  config: JanaConfig;
  onAlternarAnalise: (id: JanaAnaliseId, visivel: boolean) => void;
}) {
  return (
    <Sheet open={open} onOpenChange={(aberto) => !aberto && onClose()}>
      <SheetContent side="right" className="w-full gap-0 overflow-y-auto sm:max-w-[520px]">
        <SheetHeader className="gap-1.5 border-b p-5">
          <SheetTitle className="text-base">Configurar a Jana</SheetTitle>
          <SheetDescription>O que ela mostra, e até onde ela pode agir</SheetDescription>
        </SheetHeader>

        <Stack gap={6} className="grow p-5">
          <Secao titulo="Análises no painel">
            {JANA_ANALISES.map(({ id, label, sub }) => (
              <Linha key={id} titulo={label} sub={sub} htmlFor={`jana-cfg-${id}`}>
                <Switch
                  id={`jana-cfg-${id}`}
                  variant="cowork"
                  checked={config.analises[id]}
                  onCheckedChange={(v) => onAlternarAnalise(id, v)}
                />
              </Linha>
            ))}
            {/* O rótulo diz MOSTRAR, não "rodar". A Jana apura as cinco de
                qualquer jeito (SellsCockpitAggregator, uma consulta só) — dizer
                "análises que ela roda", como o protótipo diz, prometeria uma
                economia de processamento que desligar o card não entrega. */}
            <p className="text-xs leading-relaxed text-muted-foreground">
              Esconder um card muda só o que aparece pra você, neste navegador. O cálculo continua o
              mesmo, e nada é apagado.
            </p>
          </Secao>

          <Secao titulo="Avisos e brief diário">
            <Linha
              titulo="Quando a Jana te procura"
              sub="Canais, horário de silêncio e quando ela avisa sobre uma meta ficam na tela de alertas — valem pra empresa toda, não só pra este navegador."
            >
              <Link href="/ia/alertas/config">
                <Button variant="outline" size="sm">
                  Abrir alertas
                </Button>
              </Link>
            </Linha>
          </Secao>

          <Secao titulo="Memória e privacidade">
            <Linha
              titulo="Fatos que a Jana lembra"
              sub="Tudo o que ela guardou sobre o seu negócio, com origem e data. Você revisa e remove item a item."
            >
              <Link href="/ia/memoria">
                <Button variant="outline" size="sm">
                  Ver fatos
                </Button>
              </Link>
            </Linha>
          </Secao>

          <Secao titulo="Até onde ela age">
            {/* `disabled` em vez do switch "fixo" do protótipo: um controle que
                parece clicável e não reage é pior que um que se declara travado. */}
            <Linha
              titulo="Aprovação obrigatória"
              sub="Toda ação passa por você antes de sair. Não pode ser desligado."
              htmlFor="jana-cfg-hitl"
            >
              <Switch id="jana-cfg-hitl" variant="cowork" checked disabled aria-readonly="true" />
            </Linha>
            <p className="rounded-md border border-border bg-muted/40 p-3 text-xs leading-relaxed text-muted-foreground">
              A Jana só lê e escreve dentro da empresa desta sessão. É regra de isolamento, sem
              exceção.
            </p>
          </Secao>

          <Secao titulo="Plano">
            <Linha
              titulo="Jana Pro"
              sub="Brief diário, análises e ações sugeridas fazem parte do Pro. Conversa, memória e metas estão nos dois planos."
            >
              <Link href="/ia/pro">
                <Button variant="outline" size="sm">
                  Conhecer
                </Button>
              </Link>
            </Linha>
          </Secao>
        </Stack>

        <SheetFooter className="flex-row justify-end gap-2 border-t p-4">
          <Button variant="ghost" onClick={onClose}>
            Fechar
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
