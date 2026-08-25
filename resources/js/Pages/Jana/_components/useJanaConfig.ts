// Config do Painel da Jana (/ia) — modelo, persistência e hook.
//
// Mora FORA do `JanaConfigDrawer.tsx` de propósito: um arquivo que exporta
// componente e não-componente quebra `react-refresh/only-export-components`
// (medido — a regressão apareceu no `lint:baseline:check`, e a saída certa era
// separar, não regravar o baseline). Mesmo padrão do irmão
// `RecurringBilling/_components/useJanaAsk.ts`.
//
// ── PERSISTÊNCIA ────────────────────────────────────────────────────────────
// `localStorage` sob o prefixo `oimpresso.jana.*` — canon declarado no charter
// irmão `Chat.charter.md` (§Goals "prefix oimpresso.jana.*" · §Anti-hooks
// "❌ sessionStorage"). Mesma chave do protótipo (`oimpresso.jana.cfg`,
// `jana-merge.jsx` §JanaPage).
//
// ── O QUE MORA AQUI, E O QUE NÃO ────────────────────────────────────────────
// Só a preferência de EXIBIÇÃO das análises. O protótipo grava também `brief`,
// `briefHora`, `audio`, `retencao` e `pro` — nenhuma delas entra, porque o
// servidor não as honra (ver o cabeçalho de `JanaConfigDrawer.tsx` pra medição).
// Preferência que vale pra empresa toda tem dono próprio e server-side:
// `PATCH /ia/alertas/config` → `business.essentials_settings.alertas`.

import { useCallback, useEffect, useState } from 'react';

/** Análises que a tela REALMENTE renderiza — casa 1:1 com os `AnalysisCard`
 *  de `JanaCockpit` e com os `DrillId` de `JanaDrillDrawer`. */
export type JanaAnaliseId = 'inad' | 'fat' | 'conc' | 'metodos' | 'churn';

export interface JanaConfig {
  /** Quais análises aparecem no painel. */
  analises: Record<JanaAnaliseId, boolean>;
}

export const JANA_CFG_KEY = 'oimpresso.jana.cfg';

export const JANA_ANALISES: ReadonlyArray<{ id: JanaAnaliseId; label: string; sub: string }> = [
  { id: 'inad', label: 'Inadimplência', sub: 'Vencidas por faixa de atraso' },
  { id: 'fat', label: 'Faturamento', sub: 'Curva dos últimos 30 dias' },
  { id: 'conc', label: 'Top 5 clientes', sub: 'Concentração da receita' },
  { id: 'metodos', label: 'Métodos de pagamento', sub: 'Participação de cada forma' },
  { id: 'churn', label: 'Churn ouro', sub: 'Maior LTV sem comprar há 90 dias' },
];

const CFG_PADRAO: JanaConfig = {
  analises: { inad: true, fat: true, conc: true, metodos: true, churn: true },
};

/** Lê do storage. Mesmo padrão do `Chat.tsx` §lerHistAberto: o try/catch cobre
 *  storage bloqueado (modo privado/iframe) E o SSR, onde `localStorage` nem
 *  existe — em ambos degrada pro default (tudo visível), nunca pra tela vazia. */
function lerCfg(): JanaConfig {
  try {
    const cru = JSON.parse(localStorage.getItem(JANA_CFG_KEY) || '{}');
    return { analises: { ...CFG_PADRAO.analises, ...(cru?.analises ?? {}) } };
  } catch {
    return CFG_PADRAO;
  }
}

/**
 * Estado da config + persistência. Vive no `Index.tsx` (dono da tela) porque
 * dois filhos precisam dele: o drawer ESCREVE e o `JanaCockpit` LÊ.
 *
 * A escrita preserva as chaves que não são nossas: o protótipo grava `brief`,
 * `pro`, `retencao` etc. na MESMA chave, e um `setItem` cru as apagaria.
 */
export function useJanaConfig() {
  const [config, setConfig] = useState<JanaConfig>(lerCfg);

  useEffect(() => {
    try {
      const cru = JSON.parse(localStorage.getItem(JANA_CFG_KEY) || '{}');
      localStorage.setItem(JANA_CFG_KEY, JSON.stringify({ ...cru, analises: config.analises }));
    } catch {
      /* storage bloqueado — a sessão segue, só não persiste entre reloads */
    }
  }, [config]);

  const alternarAnalise = useCallback((id: JanaAnaliseId, visivel: boolean) => {
    setConfig((c) => ({ ...c, analises: { ...c.analises, [id]: visivel } }));
  }, []);

  return { config, alternarAnalise };
}
