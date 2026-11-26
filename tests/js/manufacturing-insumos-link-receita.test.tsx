// UC-INS-08 · Manufacturing/Insumos — o nome da receita afetada abre a receita na aba Receitas.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// Pendências da Fabricação I-05 (medição de 2026-11-25): no protótipo o nome da receita, na gaveta do
// insumo, é link para a receita; na produção era texto, porque a aba Receitas não abria uma receita pelo
// endereço. Ela passou a abrir com `?receita=ID` (UC-RECIPE-27, defendido no Pest
// `ReceitaPorEnderecoTest.php`). Este arquivo defende a outra ponta: o link existe e aponta para lá.
//
// ── O QUE ELE MEDE (Page REAL) ───────────────────────────────────────────────
// A Page de produção com a gaveta aberta (`selecionado` + `usos`). Só o Inertia é simulado: o `Link`
// vira `<a>`, para o `href` ser lido como o navegador o leria.
//
// ── O QUE **NÃO** PROVA ──────────────────────────────────────────────────────
//   - Que a receita abre do outro lado: isso é do servidor (UC-RECIPE-27) e se confere no smoke.
//   - Cor e sublinhado do link: o jsdom não resolve `var(--…)`.
//
// Comando local (vitest, fora do CT 100 — ADR 0062 cobre Pest/PHPStan):
//   npx vitest run tests/js/manufacturing-insumos-link-receita.test.tsx
// @see resources/js/Pages/Manufacturing/Insumos.casos.md (UC-INS-08)

import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  router: { get: vi.fn(), patch: vi.fn() },
  Link: ({ children, href, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
  usePage: () => ({ props: {} }),
}));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));

import Insumos from '@/Pages/Manufacturing/Insumos';

afterEach(cleanup);

const insumo = {
  variation_id: 11,
  nome: 'Lona 440g',
  sku: 'INS-011',
  custo: 12.5,
  unidade: 'm²',
  estoque: 40,
  n_receitas: 2,
  maior_peso: 62,
};

const uso = (recipe_id: number, nome: string) => ({
  recipe_id,
  nome,
  sku: `REC-${recipe_id}`,
  qtd: 1.2,
  unidade_base: 'm²',
  peso: 62,
  unit_atual: 20,
  unit_novo: 21,
  margem_nova: 40,
});

const props = {
  insumos: [insumo],
  selecionado: 11,
  usos: [uso(31, 'Banner 1x1'), uso(47, 'Faixa 3x0,7')],
  variacao_pct: 0,
  preco_simulado: 12.5,
  metodo_custo: null,
  permissions: { prod: true },
  producao: { total: 0, rascunhos: 0 },
  recipes_count: 2,
};

describe('UC-INS-08 — o nome da receita afetada abre a receita', () => {
  it('UC-INS-08 cada receita da gaveta é link para /manufacturing/recipe?receita=ID', () => {
    render(<Insumos {...(props as any)} />);

    const banner = screen.getByRole('link', { name: 'Banner 1x1' });
    const faixa = screen.getByRole('link', { name: 'Faixa 3x0,7' });

    expect(banner.getAttribute('href')).toBe('/manufacturing/recipe?receita=31');
    expect(faixa.getAttribute('href')).toBe('/manufacturing/recipe?receita=47');
  });
});
