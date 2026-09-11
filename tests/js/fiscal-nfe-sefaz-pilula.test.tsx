/**
 * A pílula SEFAZ da lista de NF-e informa — nunca renderiza "Status" nu.
 *
 * @covers-us UC-FNFE-03
 *
 * POR QUE ESTE TESTE É DE RENDER: o defeito de 2026-09-04 não estava no dicionário — estava no
 * `??` da linha 286 do `Nfe.tsx`, que trocava o código desconhecido por `label: 'Status'`. Um
 * assert sobre o mapa provaria que o backend traduz; só o render prova que a CONTADORA vê algo.
 * Presença de chave não é comportamento (LC-11).
 *
 * AS DUAS CENAS SÃO O PRINT DE PRODUÇÃO, não hipótese. Medido em biz=1 em 2026-09-04
 * (`SELECT cstat, status FROM nfe_emissoes WHERE business_id=1`): das 9 emissões, 2 são modelo 55
 * e são exatamente estas — id=18 com `cstat=781` e id=235 com `cstat` NULL e `status='inutilizada'`.
 * Na tela apareciam como "781 Status" e "— Status".
 *
 * LIMITE HONESTO: aqui o mapa `sefazCodes` é montado à mão com os textos oficiais. Que o BACKEND
 * de fato os produza a partir da tabela da SEFAZ é contrato de outro arquivo —
 * `NfeCockpitMultiTenantTest::UC-FNFE-03`, com bite-test próprio. Este prova o consumo; aquele, a
 * origem. Nenhum dos dois sozinho fecha o UC.
 */
import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children }: { children: React.ReactNode }) => <a href="#">{children}</a>,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { visit: vi.fn(), get: vi.fn(), post: vi.fn() },
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import Nfe from '@/Pages/Fiscal/Nfe';
import { sefazPill } from '@/Pages/Fiscal/_lib/fiscal-helpers';

// Só o 781 — de propósito. O 709/716 do mesmo business e o `cstat` nulo NÃO estão aqui, para que
// o teste exercite os dois caminhos de fallback com o mapa realista que o backend serviria.
const sefazCodes = {
  781: { tone: 'bad' as const, label: 'Emissor não habilitado para emissão da NFC-e', hint: '' },
};

const nota = (over: Record<string, unknown>) => ({
  id: 1, num: 1, serie: 1, modelo: 55, key: null,
  status: 'rejeitada', cstat: 781, motivo: null, value: 0,
  emittedAtIso: null, when: null, transactionId: null,
  dest: '—', cnpj: null, cpf: null, uf: null, items: null, cancelavel: false,
  ...over,
});

const cena = (data: ReturnType<typeof nota>[]) => ({
  filters: { search: '', status: 'todas' as const, tab: 'saida_nfe' as const },
  counts: {
    total: 9, nfe: 2, nfce: 7, autorizadas: 0,
    rejeitadas: 6, processando: 0, canceladas: 0, cancelaveis: 0,
  },
  sefazCodes,
  rows: { data, meta: { current_page: 1, last_page: 1, total: data.length, per_page: 50 } },
});

const pilulas = () =>
  Array.from(document.querySelectorAll<HTMLElement>('.fx-table tbody .fx-sefaz'));

/**
 * O RÓTULO, isolado do código — e é aqui que mora a mordida.
 *
 * Ler `textContent` da pílula inteira NÃO serve: os dois `<span>` são adjacentes, então a
 * regressão renderiza a string colada `"—Status"`, e um regex com fronteira de palavra
 * (`/\sStatus\s/`) passa verde sobre o defeito. Foi o que aconteceu na 1ª versão deste arquivo:
 * 4 de 7 casos passavam com a regressão reintroduzida. Medir o `.lbl` é medir o que o código
 * escolheu como rótulo.
 */
const rotulos = () =>
  Array.from(document.querySelectorAll<HTMLElement>('.fx-table tbody .fx-sefaz .lbl'))
    .map((el) => (el.textContent ?? '').trim());

describe('UC-FNFE-03 · a pílula SEFAZ na lista', () => {
  it('a palavra "Status" nunca chega sozinha à tela — nas duas linhas reais de biz=1', () => {
    render(
      <Nfe {...cena([
        nota({ id: 18, num: 1, cstat: 781, status: 'rejeitada' }),
        nota({ id: 235, num: 2, cstat: 0, status: 'inutilizada' }),
      ])} />,
    );

    const labels = rotulos();
    expect(labels).toHaveLength(2);

    // A regressão exata: o fallback `{ label: 'Status' }` vazando como se fosse rótulo.
    for (const l of labels) {
      expect(l).not.toBe('Status');
      expect(l).not.toBe('');
    }
    expect(labels[0]).toContain('Emissor não habilitado');
    expect(labels[1]).toBe('Inutilizada');
  });

  it('cStat conhecido mostra o texto oficial da SEFAZ, não um apelido', () => {
    render(<Nfe {...cena([nota({ id: 18, cstat: 781, status: 'rejeitada' })])} />);

    expect(rotulos()[0]).toBe('Emissor não habilitado para emissão da NFC-e');
    expect(pilulas()[0]?.className).toContain('bad');
  });

  it('sem cStat, a pílula cai no status de domínio — "Inutilizada", não "—"', () => {
    render(<Nfe {...cena([nota({ id: 235, cstat: 0, status: 'inutilizada' })])} />);

    expect(rotulos()[0]).toBe('Inutilizada');
  });

  it('cStat presente mas fora da tabela mantém o número visível e não finge saber o que é', () => {
    render(<Nfe {...cena([nota({ id: 99, cstat: 424242, status: 'rejeitada' })])} />);

    expect(rotulos()[0]).toBe('Rejeitada · código 424242 não catalogado');
  });

  it('o tooltip traz o motivo que a SEFAZ gravou naquela nota, com o item citado', () => {
    // O `[nItem:1]` é o que separa "algum NCM está errado" de "o NCM do item 1 está errado" —
    // e é exatamente o texto que a SEFAZ gravou na nota id=8 de biz=1.
    render(
      <Nfe {...cena([
        nota({ id: 8, cstat: 781, motivo: 'Rejeicao: Informado NCM inexistente [nItem:1]' }),
      ])} />,
    );

    expect(pilulas()[0]?.getAttribute('title')).toBe('Rejeicao: Informado NCM inexistente [nItem:1]');
  });
});

describe('UC-FNFE-03 · sefazPill (unidade)', () => {
  it('prefere o motivo da nota ao hint genérico do mapa', () => {
    const mapa = { 100: { tone: 'ok' as const, label: 'Autorizado o uso da NF-e', hint: 'genérico' } };

    expect(sefazPill({ cstat: 100, status: 'autorizada', motivo: 'texto da SEFAZ' }, mapa).hint)
      .toBe('texto da SEFAZ');
    expect(sefazPill({ cstat: 100, status: 'autorizada', motivo: '   ' }, mapa).hint)
      .toBe('genérico');
  });

  it('status de domínio desconhecido não inventa rótulo nem estoura', () => {
    const r = sefazPill({ cstat: 0, status: 'estado_que_nao_existe', motivo: null }, {});

    expect(r.label).toBe('Status desconhecido');
    expect(r.tone).toBe('warn');
  });
});
