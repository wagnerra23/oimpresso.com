// UC-JPAIN-28 — o tier Pro governa brief, análises e ações do Painel `/ia`.
//
// Âncora: `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JanaPage` — âncora de SÍMBOLO
// (`grep -n "upsell({ t:" prototipo-ui/cowork/Wagner/jana-merge.jsx`). Nela, com `pro`
// falso, o brief e a grade de análises viram card de upsell e a faixa de ações some
// INTEIRA (h2 junto). Na produção as três renderizavam pra todo mundo — a tela entregava
// de graça o que o `/ia/pro` vende (ADR 0140), com o selo "Grátis" ao lado.
//
// O dado já existia: `jana.pro` é shared prop lazy em `HandleInertiaRequests.php:170`,
// default `false` (fail-safe: na dúvida, Grátis). Esta onda não criou campo nem query.
//
// ⚠️ MARCADORES — por que não uso "Inadimplência" pra detectar a grade: a descrição do
// upsell de análises CONTÉM a palavra ("Inadimplência, faturamento, concentração, churn
// ouro e métodos de pagamento"). Usá-la daria positivo no Grátis, medindo o upsell e
// chamando de grade — a armadilha de §5 2026-09-16 (prefixo que também é prefixo de
// outra coisa). O marcador é `Top 5 clientes`, que só existe no card, e o primeiro
// `describe` abaixo PROVA essa unicidade antes dos casos usarem.
//
// Método: jsdom + @testing-library/react, como `janaSectionTitleReplica.spec.tsx`
// (UC-JPAIN-27). Detectores com controle positivo e negativo (ADR 0258).

import * as React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import JanaCockpit from '@/Pages/Jana/_components/JanaCockpit';

afterEach(cleanup);

/** Copy LITERAL da âncora — conferida byte a byte contra `jana-merge.jsx:1078-1103`. */
const UPSELL_BRIEF = 'O brief diário é do plano Pro';
const UPSELL_ANALISES = 'As 5 análises são do plano Pro';
const DESCRICAO_ANALISES =
  'Inadimplência, faturamento, concentração, churn ouro e métodos de pagamento — recalculadas todo dia, com drill-down até a origem do número.';

/** Marcadores de PRESENÇA de cada seção. Únicos — ver o describe de sensibilidade. */
const MARCA_BRIEF = 'Ouvir áudio';
const MARCA_GRADE = 'Top 5 clientes';
const MARCA_ACOES = 'Ações que Jana sugere';

/**
 * `overdueCount: 1` não é decorativo: é ele que faz `acoes` ter ≥1 item
 * (`JanaCockpit.tsx` §`const acoes` → `if (overdueCount > 0)`). Sem isso o caso das
 * ações ficaria VERDE nos dois planos por vacuidade — um teste que não pode reprovar
 * (§5 2026-09-05).
 */
function renderCockpit(props: { pro?: boolean } = {}) {
  const { container } = render(
    <JanaCockpit
      {...props}
      sellKpis={{ total: 1, paid: 0, due: 1, partial: 0, overdue: 1 }}
      insightsAggregates={{
        overdueCount: 1,
        overdueValue: 0,
        ageingBuckets: { '0-30d': 0, '30-90d': 0, '90-365d': 0, '>365d': 0 },
        methodsAgg: [],
        topClientes: [],
        topDevedor: null,
        ticketMedio: 0,
        totalAReceber: 0,
        churnOuro: [],
      }}
    />,
  );
  return container;
}

const texto = (c: HTMLElement) => c.textContent ?? '';

describe('sensibilidade dos marcadores (ADR 0258)', () => {
  it('CONTROLE: `Top 5 clientes` não é substring da descrição do upsell', () => {
    // Se um dia a copy do upsell passar a citar "Top 5 clientes", este teste cai e
    // avisa que o marcador da grade virou ambíguo — antes dos casos mentirem.
    expect(DESCRICAO_ANALISES).not.toContain(MARCA_GRADE);
    // E o inverso do erro que este comentário evita: "Inadimplência" ESTÁ na descrição,
    // e é por isso que ela não serve de marcador.
    expect(DESCRICAO_ANALISES).toContain('Inadimplência');
  });

  it('CONTROLE: o render Pro contém os 3 marcadores (senão a ausência não prova nada)', () => {
    const t = texto(renderCockpit({ pro: true }));
    expect(t).toContain(MARCA_BRIEF);
    expect(t).toContain(MARCA_GRADE);
    expect(t).toContain(MARCA_ACOES);
  });
});

describe('UC-JPAIN-28 — Grátis: brief e análises viram upsell, ações somem', () => {
  it('o brief dá lugar ao upsell, com a copy literal da âncora', () => {
    const t = texto(renderCockpit({ pro: false }));
    expect(t).not.toContain(MARCA_BRIEF);
    expect(t).toContain(UPSELL_BRIEF);
  });

  it('a grade de análises dá lugar ao upsell, com a copy literal da âncora', () => {
    const t = texto(renderCockpit({ pro: false }));
    expect(t).not.toContain(MARCA_GRADE);
    expect(t).toContain(UPSELL_ANALISES);
    expect(t).toContain(DESCRICAO_ANALISES);
  });

  it('a faixa de ações some INTEIRA — h2 junto, e sem virar um terceiro upsell', () => {
    const t = texto(renderCockpit({ pro: false }));
    expect(t).not.toContain(MARCA_ACOES);
  });

  it('os dois upsells levam a `/ia/pro`, e o botão não nasce mudo', () => {
    const c = renderCockpit({ pro: false });
    const links = Array.from(c.querySelectorAll<HTMLAnchorElement>('a[href="/ia/pro"]'));
    expect(links).toHaveLength(2);
    // Cada um embrulha um botão — é o wrapper que dá comportamento ao filho, o mesmo
    // padrão que o `painelBotoesMudos` do UC-JPAIN-16 reconhece como vivo.
    for (const a of links) expect(a.querySelector('button')).not.toBeNull();
  });

  it('o KPI não abre drill no Grátis — nada de `<button>` que não faz nada', () => {
    const gratis = renderCockpit({ pro: false }).querySelectorAll('button').length;
    const pro = renderCockpit({ pro: true }).querySelectorAll('button').length;
    // Não fixo número: fixo a DIREÇÃO. Grátis tem menos botões que Pro — se um dia o
    // gating for revertido, os dois empatam e isto cai.
    expect(gratis).toBeLessThan(pro);
  });

  it('METAS nunca são gated — o slot `aposKpis` renderiza nos dois planos', () => {
    const marca = 'metas-sentinela';
    for (const pro of [true, false]) {
      const { container } = render(
        <JanaCockpit
          pro={pro}
          aposKpis={<div data-testid={marca}>METAS</div>}
          sellKpis={{ total: 1, paid: 0, due: 1, partial: 0, overdue: 1 }}
          insightsAggregates={{
            overdueCount: 1,
            overdueValue: 0,
            ageingBuckets: { '0-30d': 0, '30-90d': 0, '90-365d': 0, '>365d': 0 },
            methodsAgg: [],
            topClientes: [],
            topDevedor: null,
            ticketMedio: 0,
            totalAReceber: 0,
            churnOuro: [],
          }}
        />,
      );
      expect(
        container.querySelector(`[data-testid="${marca}"]`),
        `aposKpis sumiu com pro=${pro}`,
      ).not.toBeNull();
      cleanup();
    }
  });
});

describe('UC-JPAIN-28 — o default da prop protege o outro consumidor', () => {
  it('sem a prop `pro`, o cockpit se comporta como Pro (blast radius zero pro Chat.tsx)', () => {
    // `Chat.tsx` também monta este componente e NÃO passa `pro`. O default `true` é o que
    // garante que esta onda não mudou aquela tela. Se alguém trocar por `false`, o Chat
    // perde brief/análises/ações em silêncio — e este caso é quem denuncia.
    const semProp = texto(renderCockpit());
    expect(semProp).toContain(MARCA_BRIEF);
    expect(semProp).toContain(MARCA_GRADE);
    expect(semProp).toContain(MARCA_ACOES);
    expect(semProp).not.toContain(UPSELL_BRIEF);
  });
});
