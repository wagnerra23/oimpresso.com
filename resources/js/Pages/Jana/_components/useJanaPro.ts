// useJanaPro — lê o tier da Jana (`jana_pro_module`) da shared prop `jana.pro`.
//
// Existe como hook, e não como `usePage` repetido em cada tela, pelo mesmo motivo que o
// `JanaModuleChaveCanonicaTest` existe no backend: quando N arquivos perguntam a mesma
// coisa por conta própria, um deles muda sozinho e a divergência não é denunciada por
// lado nenhum — cada um responde certo à pergunta que faz.
//
// Mora em arquivo PRÓPRIO (e não junto do `JanaPlanoBadge`) por exigência do
// `react-refresh/only-export-components`: módulo que exporta componente E hook derruba
// o fast refresh do Vite em dev. A coesão que interessa não se perde — segue havendo UM
// lugar que lê a prop.

import { usePage } from '@inertiajs/react';

/**
 * `true` = a assinatura do business tem `jana_pro_module`.
 *
 * Default `false` acompanha o fail-safe do backend (`HandleInertiaRequests::janaPlanoPro`):
 * na dúvida, "Grátis" — afirmar Pro a quem não é promete recurso pago; o inverso só omite.
 */
export function useJanaPro(): boolean {
  const { props } = usePage<{ jana?: { pro?: boolean } }>();

  return props.jana?.pro === true;
}

export default useJanaPro;
