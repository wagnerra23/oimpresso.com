// Manufacturing — a barra de abas (`_components/FabricacaoAbas.tsx`) segue a permissão. UC-RECIPE-24.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// Auditoria do Manufacturing, lote 2.4 (eixo 5 M5): as cinco abas apareciam para todo mundo. Quem só
// tinha `access_production` via Receitas e Insumos e caía em 403 ao clicar; Relatório e Configurações
// passaram a pedir `access_production` no lote 2.1 (decisão B3 de [W], 2026-11-09). A barra lê a
// permissão do prop compartilhado da tela (`permissions.receita` e `permissions.prod`), que os cinco
// controllers mandam.
//
// ── O QUE **NÃO** PROVA ──────────────────────────────────────────────────────
//   - Que o servidor manda as duas chaves: isso é do Pest (`MenuPorPermissaoTest` e os controllers).
//
// Comando local (vitest, fora do CT 100 — ADR 0062 cobre Pest/PHPStan):
//   npx vitest run tests/js/manufacturing-abas.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

let permissoes: Record<string, boolean> | undefined;

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
  usePage: () => ({ props: { permissions: permissoes } }),
}));

import FabricacaoAbas from '@/Pages/Manufacturing/_components/FabricacaoAbas';

afterEach(() => cleanup());

const hrefs = () =>
  Array.from(document.querySelectorAll('nav[aria-label="Fabricação"] a')).map((a) => a.getAttribute('href'));

describe('UC-RECIPE-24 · abas da Fabricação por permissão', () => {
  it('só receitas: Receitas e Insumos', () => {
    permissoes = { receita: true, prod: false };
    render(<FabricacaoAbas ativa="receitas" podeProduzir={false} />);
    expect(hrefs()).toEqual(['/manufacturing/recipe', '/manufacturing/insumos']);
  });

  it('só produção: Ordens, Relatório e Configurações', () => {
    permissoes = { receita: false, prod: true };
    render(<FabricacaoAbas ativa="producao" />);
    expect(hrefs()).toEqual(['/manufacturing/production', '/manufacturing/report', '/manufacturing/settings']);
  });

  it('âncora: com as duas, as cinco abas', () => {
    permissoes = { receita: true, prod: true };
    render(<FabricacaoAbas ativa="receitas" />);
    expect(hrefs()).toEqual([
      '/manufacturing/recipe',
      '/manufacturing/insumos',
      '/manufacturing/production',
      '/manufacturing/report',
      '/manufacturing/settings',
    ]);
    expect(screen.getByText('Configurações')).toBeTruthy();
  });
});
