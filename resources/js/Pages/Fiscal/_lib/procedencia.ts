// procedencia.ts — CU-FISC-16 · o vocabulário e o estado do selo de procedência.
//
// O CONTRATO desta tela mora no `Cockpit.charter.md` §"Contrato do selo de procedência"
// (decisão [W] 2026-09-04, saída (a) do CU-FISC-16). Aqui ficam só as decisões técnicas
// que o charter não deve carregar:
//
// 1. `useSyncExternalStore` em vez de Context. O botão mora no cabeçalho (`FxShell`) e os
//    selos moram na Page — dois pontos da árvore que precisam do mesmo booleano. Um store
//    externo evita envolver as 7 telas do módulo num Provider só para carregar um bit, e
//    de quebra faz a preferência sobreviver à navegação SPA entre elas. A chave e os
//    valores ("1"/"0") são os do protótipo (`prototipo-ui/cowork/fiscal-actions.jsx:17,88`).
//
// 2. Este arquivo é `.ts` e os componentes moram em `_components/SeloProcedencia.tsx`.
//    `react-refresh/only-export-components` reprova arquivo que exporta componentes E
//    não-componentes juntos, e a catraca `eslint-baseline` conta cada warning; separar é o
//    conserto da causa (subir o baseline seria editar a régua pra passar). Bate também com
//    a divisão que o módulo já pratica: lógica em `_lib/`, JSX em `_components/`.
//
// ⚠️ Nada aqui infere procedência. O mapa chega do controller — o porquê está no charter.

import { useSyncExternalStore } from 'react';

/** De onde vem o que a superfície mostra. Vocabulário fechado — o controller declara. */
export type OrigemDoDado = 'real' | 'demonstracao';

export interface Procedencia {
  origem: OrigemDoDado;
  /** Uma frase: o que alimenta esta superfície, ou o que falta pra ela ser real. */
  explica: string;
}

/** Superfície (chave estável) -> procedência. Servido pelo controller da tela. */
export type MapaProcedencia = Record<string, Procedencia>;

const CHAVE_PREFERENCIA = 'oimpresso.fiscal.procedencia';

export const ROTULO: Record<OrigemDoDado, string> = {
  real: 'leitura real',
  demonstracao: 'demonstração',
};

// `success`/`warning` são as variantes de ESTADO do DS (fundo `-soft` + texto `-fg`),
// que já carregam o par light/dark — por isso nenhum `dark:` cru aqui.
export const TOM_DO_BADGE: Record<OrigemDoDado, 'success' | 'warning'> = {
  real: 'success',
  demonstracao: 'warning',
};

// ─── store mínimo (sem Context) ───────────────────────────────────────────────

const inscritos = new Set<() => void>();
let ligado = leDaPreferencia();

function leDaPreferencia(): boolean {
  try {
    return window.localStorage.getItem(CHAVE_PREFERENCIA) === '1';
  } catch {
    // Modo privado / storage bloqueado: a preferência simplesmente não persiste.
    return false;
  }
}

function inscrever(notificar: () => void) {
  inscritos.add(notificar);
  return () => {
    inscritos.delete(notificar);
  };
}

function lerAgora() {
  return ligado;
}

/** SSR e a passada de hidratação não têm `localStorage` — desligado é o estado neutro. */
function lerNoServidor() {
  return false;
}

export function alternarProcedencia() {
  ligado = !ligado;
  try {
    window.localStorage.setItem(CHAVE_PREFERENCIA, ligado ? '1' : '0');
  } catch {
    // Sem persistência, o toggle ainda vale para a sessão atual.
  }
  inscritos.forEach((notificar) => notificar());
}

/** `true` quando o operador pediu para ver a procedência. */
export function useProcedenciaLigada(): boolean {
  return useSyncExternalStore(inscrever, lerAgora, lerNoServidor);
}
