// UC-JPAIN-21 — o card de meta lê "<valor> de <alvo>" e "<pct>% do alvo".
//
// Âncora: `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmMetaCard` (re-localize com
// `grep -n "function JmMetaCard" prototipo-ui/cowork/Wagner/jana-merge.jsx`): `jm-meta-v` é
// `<b>{atual}</b><small>de {alvo}</small>` e `jm-meta-f` abre com `{pct}% do alvo`, com a
// projeção empurrada pra direita por `margin-left:auto`. Precedência de FORMA:
// protótipo > teste > casos > charter > SPEC (ADR UI-0029).
//
// POR QUE ESTE ARQUIVO EXISTE, se o `PainelContratoTest` já cobre o UC-21: aquele mede o
// ARQUIVO (Pest não monta React) e este mede o DOM que o componente RENDERIZA. São
// perguntas diferentes, e só a segunda pega a classe LC-30 — "correção que passa no CI
// inteiro e é INERTE no runtime". Mesmo par que o `janaKpiReplica.spec.tsx` faz pro KPI.
//
// Método (ADR 0258 — "todo ✅ tem que ter sido visto falhar"): o `describe` de controle
// negativo prova que os detectores acusam quando o texto ERRADO está presente.
//
// Os filhos pesados da tela são stubados de propósito: o alvo aqui é a seção METAS, e
// montar cockpit/drawers traria dependência que não participa da asserção.
import * as React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: '/ia', props: { shell: { menu: [] } } }),
  router: { reload: () => {}, visit: () => {} },
  Link: ({ href, children, ...rest }: any) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));

// O stub do cockpit PRECISA repassar `aposKpis`: a seção METAS não é irmã dele no JSX —
// é passada como prop e renderizada por ele (`JanaCockpit.tsx:724`, `Index.tsx:340`). A
// primeira versão deste stub devolvia só uma `<div>` e engolia a seção inteira: 0 card
// renderizado, e a mensagem de erro dizia "renderizou 0" sem dizer POR QUÊ. Foi a
// medição do DOM que revelou a estrutura — ler o `Index.tsx` não bastava.
vi.mock('@/Pages/Jana/_components/JanaCockpit', () => ({
  default: ({ aposKpis }: { aposKpis?: React.ReactNode }) => <div data-stub="cockpit">{aposKpis}</div>,
}));
vi.mock('@/Pages/Jana/_components/JanaConfigDrawer', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/JanaMetaDrawer', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/FabJana', () => ({ default: () => null }));
vi.mock('@/Pages/Jana/_components/JanaAreaHeader', () => ({ JanaAreaHeader: () => <header /> }));
vi.mock('@/Pages/Jana/_components/JanaPlanoBadge', () => ({ JanaPlanoBadge: () => null }));
vi.mock('@/Pages/Jana/_components/useJanaPro', () => ({ useJanaPro: () => false }));

import Dashboard from '@/Pages/Jana/Index';
import type { Meta } from '@/Pages/Jana/_components/metaFormat';

afterEach(cleanup);

/**
 * Fixtures nos TRÊS estados que o rodapé distingue. Unidade `un` de propósito: o card é
 * o mesmo pros três, e valor monetário não vai pro git (Tier 0 · `memory/proibicoes.md`).
 */
const META_BASE: Omit<Meta, 'id' | 'slug' | 'nome' | 'periodo_atual' | 'ultima_apuracao' | 'projecao'> = {
  unidade: 'un',
  tipo_agregacao: 'soma',
  apuracoes_recentes: [],
};

const periodo = { data_ini: '2026-09-01', data_fim: '2026-09-30', valor_alvo: 200, trajetoria: 'linear' };

const METAS: Meta[] = [
  {
    ...META_BASE,
    id: 1,
    slug: 'com-projecao',
    nome: 'Com projeção',
    farol: 'verde',
    periodo_atual: periodo,
    ultima_apuracao: { data_ref: '2026-09-07', valor_realizado: 64 },
    projecao: { progresso: 0.23, projetado: 278, desvio_pct: 12 },
  },
  {
    ...META_BASE,
    id: 2,
    slug: 'sem-projecao',
    nome: 'Sem projeção',
    farol: 'amarelo',
    periodo_atual: periodo,
    ultima_apuracao: { data_ref: '2026-09-07', valor_realizado: 150 },
    projecao: null,
  },
  {
    ...META_BASE,
    id: 3,
    slug: 'sem-apuracao',
    nome: 'Sem apuração',
    farol: 'cinza',
    periodo_atual: periodo,
    ultima_apuracao: null,
    projecao: null,
  },
];

function renderPainel(metas: Meta[] = METAS) {
  const { container } = render(
    <Dashboard
      metas={metas}
      sellKpis={undefined as never}
      insightsAggregates={undefined as never}
      coworkAggregates={undefined as never}
      janaContext={{ businessId: 1, businessName: 'WR2 Sistemas', userName: null }}
    />,
  );
  const cards = Array.from(container.querySelectorAll<HTMLElement>('button[aria-label^="Abrir a meta"]'));
  if (cards.length !== metas.length) {
    throw new Error(`esperava ${metas.length} card(s) de meta, renderizou ${cards.length}`);
  }
  return { container, cards };
}

/** Texto visível do card, com espaços normalizados (o JSX quebra linha à vontade). */
function texto(el: HTMLElement): string {
  return (el.textContent || '').replace(/\s+/g, ' ').trim();
}

describe('detectores — controle negativo (ADR 0258: o teste tem de poder falhar)', () => {
  it('SENSIBILIDADE: o rótulo antigo e a porcentagem sem substantivo são detectáveis', () => {
    const antigo = document.createElement('div');
    antigo.textContent = 'Alvo: 200 32%';
    expect(texto(antigo)).toMatch(/Alvo:/);
    expect(texto(antigo)).not.toMatch(/% do alvo/);

    const novo = document.createElement('div');
    novo.textContent = '64 de 200 32% do alvo';
    expect(texto(novo)).toMatch(/% do alvo/);
    expect(texto(novo)).not.toMatch(/Alvo:/);
  });
});

describe('UC-JPAIN-21 · card de meta: valor com "de <alvo>" e rodapé "<pct>% do alvo"', () => {
  it('UC-JPAIN-21: a linha do VALOR mostra o realizado seguido de "de <alvo>"', () => {
    const { cards } = renderPainel();
    // `formatValue(64,'un')` e `formatValue(200,'un')` — o teste lê o que o componente
    // escreveu, sem reimplementar o formatador.
    expect(texto(cards[0])).toMatch(/64\s*de\s*200/);
    expect(texto(cards[1])).toMatch(/150\s*de\s*200/);
  });

  it('UC-JPAIN-21: o RODAPÉ diz "<pct>% do alvo", e o rótulo "Alvo:" sumiu', () => {
    const { cards, container } = renderPainel();
    expect(texto(cards[0])).toMatch(/32% do alvo/); // 64/200
    expect(texto(cards[1])).toMatch(/75% do alvo/); // 150/200
    expect(texto(container as unknown as HTMLElement)).not.toMatch(/Alvo:/);
  });

  it('UC-JPAIN-21: meta SEM apuração cai pra "alvo <X>" — nunca "0% do alvo"', () => {
    const { cards } = renderPainel();
    const semApuracao = texto(cards[2]);
    expect(semApuracao).toMatch(/Aguardando apuração/); // copy pinada, intacta
    expect(semApuracao).toMatch(/alvo 200/);
    expect(semApuracao).not.toMatch(/% do alvo/);
    expect(semApuracao).not.toMatch(/0%/);
  });

  it('UC-JPAIN-21: a projeção segue à DIREITA (ml-auto, mono) e só quando o servidor manda', () => {
    const { cards } = renderPainel();
    const proj = cards[0].querySelector<HTMLElement>('.ml-auto');
    expect(proj, 'projeção deveria renderizar quando meta.projecao existe').not.toBeNull();
    expect(proj!.className).toMatch(/font-mono/);
    expect(proj!.textContent).toMatch(/278/); // consumido do payload, não calculado
    // sem projeção no payload: nada é desenhado (ausência não é projeção de zero)
    expect(cards[1].querySelector('.ml-auto')).toBeNull();
  });

  it('UC-JPAIN-21: meta SEM alvo não renderiza rodapé nenhum', () => {
    const semAlvo: Meta = { ...METAS[0], id: 4, slug: 'sem-alvo', nome: 'Sem alvo', periodo_atual: null };
    const { cards } = renderPainel([semAlvo]);
    const t = texto(cards[0]);
    expect(t).not.toMatch(/% do alvo/);
    expect(t).not.toMatch(/\bde 200\b/);
  });
});
