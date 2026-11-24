/**
 * Vendas — a mesma barra de abas em todas as telas do módulo, na ordem do protótipo.
 *
 * Fonte: `prototipo-ui/cowork/Wagner/venda-blade.jsx` (`VendaBladePage`, barra "Telas do módulo
 * Venda"). O teste LÊ o protótipo, para não virar um terceiro dono da lista.
 *
 * Medido em produção em 2026-11-24: só `/pos` tinha a barra; `/sells/drafts` não tinha; a aba
 * Assinaturas apontava para `/subscriptions` (404). O menu do hub Vendas que o servidor manda
 * não tinha `/sales-order` nem `/vendas/caixa` — é o caso que o filtro abaixo reproduz.
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { ABAS_VENDAS, abasVisiveis } from '@/Pages/Sells/_components/AbasVendas';

const raiz = resolve(__dirname, '../..');
const ler = (p: string) => readFileSync(resolve(raiz, p), 'utf8');

function ordemDoPrototipo(): string[] {
  const src = ler('prototipo-ui/cowork/Wagner/venda-blade.jsx');
  const ini = src.indexOf('ariaLabel="Telas do módulo Venda"');
  const bloco = src.slice(src.indexOf('tabs={[', ini), src.indexOf('].map', ini));
  return [...bloco.matchAll(/"(\w+)"/g)].map((m) => m[1]);
}

// Chave da aba (= chave do protótipo) → tela que a mostra ativa.
const TELAS: Record<string, string> = {
  pos: 'resources/js/Pages/Sells/Pos/Index.tsx',
  rascunhos: 'resources/js/Pages/Sells/Drafts.tsx',
  cotacoes: 'resources/js/Pages/Sells/Quotations.tsx',
  remessas: 'resources/js/Pages/Sells/Shipments/Index.tsx',
  descontos: 'resources/js/Pages/Discount/Index.tsx',
  assinaturas: 'resources/js/Pages/Sells/Subscriptions.tsx',
  pedidos: 'resources/js/Pages/SalesOrder/Index.tsx',
  caixa: 'resources/js/Pages/Sells/Caixa/Index.tsx',
  importar: 'resources/js/Pages/ImportSales/Index.tsx',
};

// Hub Vendas como chegou em produção (2026-11-24), sem Pedido de venda nem Caixa do dia.
const MENU = [
  { href: '/home' },
  {
    children: [
      { href: '/sells' }, { href: '/sells/create' }, { href: '/pos' }, { href: '/pos/create' },
      { href: '/sells/create?status=draft' }, { href: '/sells/drafts' }, { href: '/sells/quotations' },
      { href: '/sell-return' }, { href: '/shipments' }, { href: '/discount' },
      { href: '/sells/subscriptions' }, { href: '/import-sales' },
    ],
  },
];

describe('Vendas · barra de abas do módulo', () => {
  it('controle: a leitura do protótipo achou as 9 telas', () => {
    expect(ordemDoPrototipo()).toHaveLength(9);
  });

  it('mesmas telas e mesma ordem do protótipo', () => {
    expect(ABAS_VENDAS.map((a) => a.key)).toEqual(ordemDoPrototipo());
  });

  it('cada tela do módulo mostra a barra com a sua própria aba ativa', () => {
    for (const [chave, arquivo] of Object.entries(TELAS)) {
      expect(ler(arquivo), arquivo).toContain(`<AbasVendas ativa="${chave}" />`);
    }
  });

  it('Assinaturas aponta para a rota que existe', () => {
    expect(ABAS_VENDAS.find((a) => a.key === 'assinaturas')?.href).toBe('/sells/subscriptions');
  });

  it('esconde a aba cujo item o servidor não mandou na sidebar', () => {
    const chaves = abasVisiveis('pos', MENU, {}).map((a) => a.key);
    expect(chaves).not.toContain('pedidos');
    expect(chaves).toContain('importar');
  });

  it('Caixa do dia segue as permissões da rota, não a sidebar', () => {
    expect(abasVisiveis('pos', MENU, {}).map((a) => a.key)).not.toContain('caixa');
    expect(abasVisiveis('pos', MENU, { view_own_sell_only: true }).map((a) => a.key)).toContain('caixa');
  });

  it('a aba da tela atual sempre aparece, mesmo fora do menu', () => {
    expect(abasVisiveis('pedidos', MENU, {}).map((a) => a.key)).toContain('pedidos');
  });

  it('sem menu no payload, mostra todas (comportamento anterior)', () => {
    expect(abasVisiveis('pos', undefined, {})).toHaveLength(9);
  });
});
