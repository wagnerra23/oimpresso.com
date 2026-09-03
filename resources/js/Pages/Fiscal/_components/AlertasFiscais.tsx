// AlertasFiscais.tsx — fila de alertas do cockpit fiscal
//
// Port do design `prototipo-ui/cowork/fiscal-page.jsx:125-137` (FxAlerts).
//
// POR QUE EXISTE
// --------------
// A prop `alerts` já viajava do `CockpitController` até a tela, mas era consumida
// SÓ na contagem do miolo do cabeçalho (`totalRej`) — a fila nunca era desenhada.
// O efeito em produção: o cockpit anuncia "N requerem ação" e não mostra QUAIS,
// enquanto o `computeAlerts()` já sabe (rejeição recente, certificado vencendo,
// DF-e por manifestar). Este componente é só o desenho da fila que já existia.
//
// O QUE NÃO É DAQUI
// -----------------
// Nada de regra fiscal: quem decide nível, título e ação é o `computeAlerts()`
// (determinístico por estado — `UC-FCKP-04`, e é anti-hook do charter). Aqui não
// há cálculo, ordenação nem filtro; a ordem é a que o backend mandou.

import { Button } from '@/Components/ui/button';
import { router } from '@inertiajs/react';

import { btnProps } from '../_lib/botao-fiscal';
import { iconeAlerta } from '../_lib/icones-alerta';
import { FX_PAGES } from '../_lib/paginas-fiscais';

/**
 * Um alerta como o `CockpitController::computeAlerts()` serializa.
 *
 * `goto` NÃO é um caminho: é o `id` de uma das sub-páginas do Fiscal — o mesmo
 * vocabulário do `FX_PAGES` do `FxShell` (`nfe` · `fiscal_config` · `dfe`).
 * Navegar com ele cru levaria a tela para uma URL relativa inexistente, por isso
 * a resolução passa pelo mapa (abaixo) e nunca pelo valor direto.
 */
export interface AlertaFiscal {
  level: 'crit' | 'warn' | 'info';
  icon: string;
  title: string;
  sub: string;
  action: string;
  goto: string;
  focus?: string;
}

/**
 * `goto` → caminho real, lido do `FX_PAGES` — o mapa que o `FxShell` já mantém
 * para a sub-nav. Derivar daqui em vez de reescrever as 7 entradas mantém um dono
 * só: rota que mudar no `FxShell` muda no alerta junto, sem ninguém lembrar.
 */
const URL_POR_ID: Record<string, string> = Object.fromEntries(
  FX_PAGES.map((p) => [p.id, p.url]),
);

export default function AlertasFiscais({ alerts }: { alerts: AlertaFiscal[] }) {
  // Zero alertas = nó ausente. Não há estado vazio a desenhar: "nada requer ação"
  // é a ausência da fila, e um card dizendo isso competiria com o ribbon por
  // atenção sem acrescentar leitura.
  if (alerts.length === 0) return null;

  return (
    <div className="fx-alerts" data-contract="alertas-fiscais">
      {alerts.map((a, i) => {
        const Icone = iconeAlerta(a.icon);
        const destino = URL_POR_ID[a.goto];

        return (
          <div className="fx-alert" data-level={a.level} key={`${a.goto}-${i}`}>
            {/* Ícone é redundante com o nível, que já está no texto e na cor da
                moldura — daí `aria-hidden`: para o leitor de tela ele é ruído. */}
            <span className="fx-alert-ic" aria-hidden="true">
              {Icone ? <Icone size={14} /> : null}
            </span>
            <span className="fx-alert-t">
              <b>{a.title}</b>
              <small>{a.sub}</small>
            </span>
            {/* Sem destino conhecido o botão não vira link morto: some. Um botão
                que não navega ensina o operador a desconfiar do resto da tela. */}
            {destino ? (
              <Button
                type="button"
                {...btnProps('ghost')}
                className="ml-auto shrink-0"
                onClick={() => router.visit(destino)}
              >
                {a.action}
              </Button>
            ) : null}
          </div>
        );
      })}
    </div>
  );
}
