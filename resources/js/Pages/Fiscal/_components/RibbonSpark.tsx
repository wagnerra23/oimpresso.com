/**
 * RibbonSpark — a mini-série de 14 dias que acompanha um KPI do ribbon do cockpit fiscal.
 *
 * PORTE 1:1 do `FxSpark` do protótipo Cowork (`prototipo-ui/cowork/fiscal-page.jsx:80-84`),
 * que é a âncora declarada no `Cockpit.charter.md` (`related_prototype`). A geometria abaixo
 * não é escolha de estilo — é o contrato de FORMA, e nele o protótipo é soberano
 * (ADR UI-0029): viewBox 56×15, base em y=14, amplitude 12, `strokeWidth` 1.2.
 *
 * `currentColor` é deliberado e carrega semântica: a série herda a cor do KPI que ela
 * acompanha, então a de "Rejeitadas" sai na tinta de alerta e a de "Autorizadas" na de
 * sucesso, sem nenhum mapa de cor aqui dentro.
 *
 * `aria-hidden` porque o gráfico é REDUNDÂNCIA VISUAL: o número ao lado já é o dado, e a
 * série não acrescenta informação que um leitor de tela precise ouvir. Por isso também não
 * há hover, tooltip nem foco — nada aqui é operável.
 *
 * POR QUE NÃO O `Chart` DO DS (`@/Components/shared/Chart`), medido em 2026-09-04:
 *   1. Ele se dimensiona por `width: '100%'` num wrapper sem prop de largura — o ribbon
 *      quer 56px fixos ao lado do número, não a largura do item.
 *   2. Ele fixa `role="img"` + `aria-label` no `<svg>`, o oposto do `aria-hidden` que este
 *      caso exige; sobrepor isso significaria editar o componente do DS (soberania [W]) e
 *      mexer nos seus 2 consumidores vivos (`Home/Index`, `RecurringBilling/Index`).
 *   3. Ele é interativo — `onMouseEnter`/`onMouseLeave` com estado e tooltip `label · valor`.
 *      Isso ADICIONA leitura de dado; aqui a peça é decorativa por contrato.
 *
 * POR QUE NÃO O `Sparkline` VIZINHO (`Pages/RecurringBilling/_components/Sparkline.tsx`),
 * apontado pelo `reuse:check` e medido no mesmo dia — 3 divergências de contrato:
 *   1. forma: dois `<path>`, um deles área com gradiente 0.45→0 × `<polyline>` puro;
 *   2. cor: verde fixo `oklch(0.75 0.13 145)` × `currentColor` — o literal pintaria a
 *      série de "Rejeitadas" de verde, exatamente onde a tinta carrega o alerta;
 *   3. a11y: sem `aria-hidden` × com — e aqui o gráfico é redundância por contrato.
 *   Reconciliar exigiria flags novas num componente que serve tela de COBRANÇA — risco
 *   em caminho de dinheiro, sem ganho.
 *
 *   ⚠️ Uma 4ª divergência que eu havia listado NÃO existe, e fica registrada porque a
 *   medição me corrigiu: a escala do vizinho, `(v - min) / range`, PARECE mostrar
 *   variação relativa contra a magnitude absoluta de `v / max`. Mas o `min` dele é
 *   `Math.min(...data, 0)` — piso zero. Como contagem de nota fiscal nunca é negativa,
 *   `min` é sempre 0, `range` vira `max`, e as duas fórmulas dão o MESMO ponto. Provado
 *   por bite-test: trocar uma pela outra deixa os 6 casos verdes (mutação equivalente),
 *   e as saídas só divergem com dado negativo, que este domínio não tem.
 */

interface Props {
  /** Uma contagem por dia. O controller emite 14 (`CockpitController::computeSparklines`). */
  data: number[];
}

/** Contrato de forma do protótipo — mexer aqui é mexer no desenho, não no código. */
const W = 56;
const BASE_Y = 14;
const AMPLITUDE = 12;

export default function RibbonSpark({ data }: Props) {
  // Guarda TÉCNICA, não editorial: com menos de 2 pontos o divisor `length - 1` zera e
  // todo x vira NaN, e o React serializaria `points="NaN,NaN"` sem levantar erro nenhum.
  // Não é hipótese — o `cena` de `fiscal-cockpit-paginacao.test.tsx` já passa `[1]`.
  if (!Array.isArray(data) || data.length < 2) return null;

  // Piso 1 no máximo (como o protótipo): 14 dias sem emissão dariam divisão por zero.
  // Uma série toda-zero DESENHA, de propósito — a linha reta na base é a leitura honesta
  // de "nada aconteceu", e é o que o protótipo faz. Esconder seria inventar divergência.
  const max = Math.max(...data, 1);
  const pontos = data
    .map((v, i) => `${(i / (data.length - 1)) * W},${BASE_Y - (v / max) * AMPLITUDE}`)
    .join(' ');

  return (
    <svg width={W} height="15" viewBox={`0 0 ${W} 15`} fill="none" aria-hidden="true">
      <polyline points={pontos} stroke="currentColor" strokeWidth="1.2" />
    </svg>
  );
}
