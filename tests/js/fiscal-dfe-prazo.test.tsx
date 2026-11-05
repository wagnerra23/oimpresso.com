/**
 * Manifesto DF-e — a pílula de prazo tem três níveis de urgência.
 *
 * @covers-us UC-FDFE-14
 *
 * O CONTRATO (Fiscal/Dfe.charter.md §Goals 4 e §Anti-hooks): a pílula mostra os dias que
 * restam até `prazo_confirmacao_em` — o prazo que a SEFAZ calculou — em três níveis:
 * crítico abaixo de 7 dias, atenção abaixo de 30, tranquilo daí pra cima. E nada de 90 dias
 * fixos no código. A metade "de onde vem o número" é provada no Pest
 * (`DfeControllerTest`, UC-FDFE-14); este arquivo prova a metade que só existe no cliente:
 * o número vira o nível certo, na linha certa, na tela montada.
 *
 * POR QUE RENDER (e não um helper isolado): a regra de nível mora inline no `Dfe.tsx`.
 * Testar uma cópia dela num helper provaria a cópia (LC-11). Aqui a tela real é montada
 * com uma linha em cada borda, e a asserção lê a classe que o CSS consome
 * (`.fx-timepill.u-<nivel>`).
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children }: { children: React.ReactNode }) => <a href="#">{children}</a>,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), reload: vi.fn() },
  usePage: () => ({ props: {}, url: '/fiscal/dfe' }),
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import Dfe from '@/Pages/Fiscal/Dfe';

const linha = (id: number, prazoDias: number | null) => ({
  id,
  chave: '3526' + String(id).padStart(40, '0'),
  nsu: String(id),
  cnpjEmitente: '12345678000199',
  nomeEmitente: `Fornecedor ficticio ${id}`,
  valor: 100,
  numProtocolo: null,
  dataEmissaoIso: '2026-09-02T10:00:00-03:00',
  when: '02/09 10:00',
  statusManifestacao: 'pendente' as const,
  manifestadoEmIso: null,
  prazoDias,
});

// Uma linha em cada borda da regra: 6|7 separa crítico de atenção, 29|30 separa atenção
// de tranquilo. Sem as bordas, um `<=` no lugar de `<` passaria verde.
const cenario = {
  activeTab: 'pendente' as const,
  filters: { status: 'pendentes' as const, search: '' },
  counts: { total: 7, pendentes: 7, confirmadas: 0, desconhecidas: 0, naoRealizadas: 0, valorPendente: 700 },
  rows: {
    data: [linha(1, 6), linha(2, 7), linha(3, 29), linha(4, 30), linha(5, 0), linha(6, -3), linha(7, null)],
    meta: { total: 7, current_page: 1, last_page: 1 },
  },
};

const linhaDe = (id: number) =>
  Array.from(document.querySelectorAll('tr')).find((el) =>
    el.textContent?.includes(`Fornecedor ficticio ${id}`),
  );

/** O nível que a linha `id` pintou, lido da classe que o CSS consome. */
const nivelDa = (id: number): string | null =>
  linhaDe(id)?.querySelector('.fx-timepill')?.className.match(/\bu-(crit|warn|ok)\b/)?.[1] ?? null;

const textoDaPilula = (id: number): string | null =>
  linhaDe(id)?.querySelector('.fx-timepill')?.textContent ?? null;

afterEach(() => cleanup());

describe('UC-FDFE-14 · a pílula de prazo tem três níveis de urgência', () => {
  it('UC-FDFE-14 · abaixo de 7 dias é crítico; 7 já é atenção', () => {
    render(<Dfe {...cenario} />);
    expect(nivelDa(1), '6 dias tem que ser crítico').toBe('crit');
    expect(nivelDa(2), '7 dias tem que ser atenção, não crítico').toBe('warn');
  });

  it('UC-FDFE-14 · abaixo de 30 dias é atenção; 30 já é tranquilo', () => {
    render(<Dfe {...cenario} />);
    expect(nivelDa(3), '29 dias tem que ser atenção').toBe('warn');
    expect(nivelDa(4), '30 dias tem que ser tranquilo').toBe('ok');
  });

  it('UC-FDFE-14 · prazo esgotado é crítico e diz "vencido", não um número de dias', () => {
    render(<Dfe {...cenario} />);
    expect(nivelDa(5)).toBe('crit');
    expect(textoDaPilula(5)).toBe('vencido');
    expect(nivelDa(6)).toBe('crit');
    expect(textoDaPilula(6)).toBe('vencido');
    // controle positivo: com prazo sobrando, a pílula mostra os dias
    expect(textoDaPilula(1)).toBe('6d');
  });

  it('UC-FDFE-14 · sem prazo calculado pela SEFAZ não há pílula — a tela não inventa 90 dias', () => {
    render(<Dfe {...cenario} />);
    // controle positivo: a linha está na tela
    expect(linhaDe(7), 'a linha sem prazo nem foi renderizada').toBeTruthy();
    expect(textoDaPilula(7), 'linha sem prazo_confirmacao_em ganhou pílula de prazo').toBeNull();
  });
});
