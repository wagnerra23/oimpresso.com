// Mapa `icon` do alerta fiscal → componente Lucide.
//
// POR QUE EXISTE
// --------------
// O `CockpitController::computeAlerts()` serializa `icon` no vocabulário do
// protótipo Cowork (`audit` · `shield` · `receipt`), que é o `window.I` do
// `fiscal-page.jsx` — NÃO nomes do Lucide. Sem tradução, a tela não teria o que
// desenhar: `audit` e `shield` não existem no pacote.
//
// A correspondência sai do DESENHO de cada glifo no `prototipo-ui/cowork/icons.jsx`,
// não do palpite pelo nome:
//   - `shield`  (icons.jsx:25) = escudo + traço de confirmação  → ShieldCheck
//   - `receipt` (icons.jsx:67) = recibo serrilhado com linhas   → Receipt
//   - `audit`   (icons.jsx:70) = escudo + linhas de registro    → ShieldAlert
//
// `ShieldAlert` e `Receipt` são os mesmos que o `FxShell` já usa na sub-nav, então
// o alerta e a navegação falam a mesma língua visual.
//
// Nome fora do mapa devolve `null` e o item renderiza SEM ícone — é o mesmo
// comportamento do protótipo (`fiscal-page.jsx:6`, `F ? <F/> : null`). Inventar um
// glifo de reserva esconderia o dado errado; o `UC-FCKP-08` é quem impede que um
// `icon` desconhecido chegue à tela em silêncio.

import { Receipt, ShieldAlert, ShieldCheck, type LucideIcon } from 'lucide-react';

/** Os `icon` que o `computeAlerts()` do controller sabe emitir. */
export type IconeAlerta = 'audit' | 'shield' | 'receipt';

const ICONES: Record<IconeAlerta, LucideIcon> = {
  audit: ShieldAlert,
  shield: ShieldCheck,
  receipt: Receipt,
};

/** Componente Lucide do `icon`, ou `null` quando o nome não está no mapa. */
export function iconeAlerta(nome: string): LucideIcon | null {
  return ICONES[nome as IconeAlerta] ?? null;
}

/** Os nomes cobertos — o teste do `UC-FCKP-08` lê daqui, não de uma lista à mão. */
export const ICONES_ALERTA_CONHECIDOS = Object.keys(ICONES) as IconeAlerta[];
