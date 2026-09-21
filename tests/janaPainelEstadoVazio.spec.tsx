// UC-JPAIN-29 — business sem histórico vê UM estado de página, não 6 caixas vazias.
//
// Âncora: `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JanaPage` — âncora de SÍMBOLO
// (`grep -n "ainda não tem histórico" prototipo-ui/cowork/Wagner/jana-merge.jsx`). Nela,
// quando não há histórico pra analisar, o corpo inteiro (brief + KPIs + análises + ações)
// dá lugar a um empty-state único com título, descrição e uma saída.
//
// A produção tinha empty-state POR BLOCO — "Sem histórico" no sparkline, "Sem dados de
// clientes", "Sem pagamentos registrados", "Ninguém de peso parou de comprar". Num business
// recém-onboardado o resultado é uma tela de caixas vazias e `R$ 0,00` repetido: cada bloco
// dizendo baixinho que não tem dado, nenhum dizendo por quê nem o que fazer.
//
// ⚠️ SÓ o ramo VAZIO desceu. O protótipo também bifurca em `erro` ("Não consegui ler os
// dados da empresa agora" + "Tentar de novo"), e esse NÃO tem fonte no `main`: o
// `IndexController` não emite sinal de falha. Exportá-lo seria pedir UI pra um estado que o
// servidor não sabe produzir — fica como fundação + decisão [W], não como pedido.
//
// ⚠️ PRECEDÊNCIA, e é o caso que mais importa aqui: `carregandoCockpit` (skeleton) →
// `semHistorico` → conteúdo. `coworkAggregates` é `Inertia::defer`; enquanto não chega,
// `undefined` significa "ainda não veio", não "não tem dado". Incluí-lo no predicado faria a
// tela PISCAR o empty-state durante o carregamento normal — o flicker é o defeito clássico
// desta onda, e o caso §"durante o defer" existe pra travá-lo.
//
// Método: jsdom + @testing-library/react, como `janaSectionTitleReplica.spec.tsx`
// (UC-JPAIN-27) e `janaPainelGatingPro.spec.tsx` (UC-JPAIN-28).

import * as React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import JanaCockpit from '@/Pages/Jana/_components/JanaCockpit';

afterEach(cleanup);

/** Copy LITERAL da âncora. */
const TITULO = 'A Jana ainda não tem histórico pra analisar';
const DESCRICAO =
  'Ela precisa de pelo menos um mês de movimento pra montar o brief, os KPIs e as análises. Enquanto isso, pergunte o que quiser na aba Conversa.';
const ACAO = 'Ir para a Conversa';

/** Marcadores de PRESENÇA do corpo — os mesmos do UC-JPAIN-28. */
const MARCA_BRIEF = 'Ouvir áudio';
const MARCA_GRADE = 'Top 5 clientes';

const VAZIO = {
  overdueCount: 0,
  overdueValue: 0,
  ageingBuckets: { '0-30d': 0, '30-90d': 0, '90-365d': 0, '>365d': 0 },
  methodsAgg: [],
  topClientes: [],
  topDevedor: null,
  ticketMedio: 0,
  totalAReceber: 0,
  churnOuro: [],
};

/**
 * `coworkAggregates` PRESENTE (mesmo `{}`) é o que faz `carregandoCockpit` virar `false`.
 * Omiti-lo mantém a tela no skeleton — e é justamente o caso do flicker, testado à parte.
 */
function renderCockpit({
  sell = { total: 0, paid: 0, due: 0, partial: 0, overdue: 0 },
  insights = VAZIO,
  carregando = false,
  aposKpis = undefined as React.ReactNode,
} = {}) {
  const { container } = render(
    <JanaCockpit
      sellKpis={sell}
      insightsAggregates={insights}
      {...(carregando ? {} : { coworkAggregates: {} })}
      aposKpis={aposKpis}
    />,
  );
  return container;
}

const texto = (c: HTMLElement) => c.textContent ?? '';

describe('sensibilidade (ADR 0258)', () => {
  it('CONTROLE: com movimento, o corpo renderiza e o estado vazio NÃO aparece', () => {
    // Sem este controle, "o empty-state apareceu" não distinguiria o predicado funcionando
    // de o componente sempre mostrá-lo.
    const t = texto(
      renderCockpit({
        sell: { total: 1, paid: 0, due: 1, partial: 0, overdue: 1 },
        insights: { ...VAZIO, overdueCount: 1, totalAReceber: 100 },
      }),
    );
    expect(t).toContain(MARCA_BRIEF);
    expect(t).toContain(MARCA_GRADE);
    expect(t).not.toContain(TITULO);
  });
});

describe('UC-JPAIN-29 — sem histórico, o corpo vira UM estado', () => {
  it('mostra o estado de página com a copy literal da âncora', () => {
    const t = texto(renderCockpit());
    expect(t).toContain(TITULO);
    expect(t).toContain(DESCRICAO);
    expect(t).toContain(ACAO);
  });

  it('o corpo some inteiro — brief, KPIs e análises não montam', () => {
    const t = texto(renderCockpit());
    expect(t).not.toContain(MARCA_BRIEF);
    expect(t).not.toContain(MARCA_GRADE);
    // E nenhum dos empty-states POR BLOCO, que é o ponto do caso: um estado, não seis.
    expect(t).not.toContain('Sem histórico');
    expect(t).not.toContain('Ninguém de peso parou de comprar');
  });

  it('a saída leva a `/ia/conversa`, e o botão não nasce mudo', () => {
    const links = Array.from(
      renderCockpit().querySelectorAll<HTMLAnchorElement>('a[href="/ia/conversa"]'),
    );
    expect(links).toHaveLength(1);
    expect(links[0]?.querySelector('button')).not.toBeNull();
  });

  it('METAS continuam — eixos separados, e a copy pinada delas não é tocada', () => {
    // Um business pode ter meta cadastrada e zero venda. Este estado cobre o eixo VENDAS;
    // a seção METAS segue com o `painel-metas-vazio` dela, cuja copy é lei [W].
    const c = renderCockpit({ aposKpis: <div data-testid="metas-sentinela">METAS</div> });
    expect(c.querySelector('[data-testid="metas-sentinela"]')).not.toBeNull();
    expect(texto(c)).toContain(TITULO);
  });
});

describe('UC-JPAIN-29 — precedência: skeleton ganha do vazio (anti-flicker)', () => {
  it('durante o defer de `coworkAggregates`, NUNCA o empty-state', () => {
    // O defeito clássico desta onda: o payload zerado chega antes do deferido, e a tela
    // pisca "não tem histórico" no meio de um carregamento normal.
    const t = texto(renderCockpit({ carregando: true }));
    expect(t).not.toContain(TITULO);
    // E o corpo segue de pé, em skeleton — não é que a tela some.
    expect(t).toContain(MARCA_BRIEF);
  });
});

describe('UC-JPAIN-29 — o predicado exige as QUATRO pernas', () => {
  // Cada caso liga UMA perna e prova que ela sozinha tira a tela do estado vazio. Sem isto,
  // um predicado que olhasse só `total` passaria igual — e a tela esconderia dado real.
  const pernas: Array<[string, Parameters<typeof renderCockpit>[0]]> = [
    ['sellKpis.total', { sell: { total: 3, paid: 0, due: 0, partial: 0, overdue: 0 } }],
    ['totalAReceber', { insights: { ...VAZIO, totalAReceber: 250 } }],
    ['topClientes', { insights: { ...VAZIO, topClientes: [{ name: 'ACME', total: 10 }] } }],
    ['methodsAgg', { insights: { ...VAZIO, methodsAgg: [{ method: 'pix', total: 10 }] } }],
  ];

  for (const [nome, props] of pernas) {
    it(`com ${nome} != 0, a tela NÃO está vazia`, () => {
      expect(texto(renderCockpit(props))).not.toContain(TITULO);
    });
  }

  it('com as quatro zeradas, a tela ESTÁ vazia (o outro sentido)', () => {
    expect(texto(renderCockpit())).toContain(TITULO);
  });
});
