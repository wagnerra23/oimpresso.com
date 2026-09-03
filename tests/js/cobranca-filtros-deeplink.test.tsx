// UC-COB-03 · Financeiro/Cobranca — QUEM VENCE: a querystring ou o localStorage?
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// O #6632 registrou no `Index.casos.md` uma HIPÓTESE de leitura: os cinco
// `useState(() => lsGet<string>(...))` do `Index.tsx:75-79` fariam o localStorage vencer a
// querystring, e um deep-link `?status=vencida` poderia esvaziar a tabela. Hipótese derivada
// de leitura não é achado (§5 2026-07-15). Este spec EXECUTA e responde.
//
// Veredito: a hipótese PROCEDE. E o perdedor é o CONTRATO, não o código — o charter (lei),
// linha 45, exige a persistência dos 5 filtros (`tab`/`tipo`/`gateway`/`account`/`origem`).
// O `Index.tsx` cumpre a lei; o UC-COB-03 é que prometia deep-link que o charter nunca pediu.
// Por isso a correção foi no `.casos.md` e **nada** mudou em `Index.tsx`.
//
// ── O QUE ELE MEDE (componente REAL + lsGet REAL) ────────────────────────────
// Nenhuma lógica é reimplementada: importa `CobrancaPage` e `lsGet` dos arquivos de produção.
// Reimplementar seria medir a minha cópia, não a tela (§5 2026-06-05, teste tautológico).
// Os `vi.mock` cobrem só a CASCA (shell, header, subnav, Radix Select, sheets/drawer) — o
// `useMemo` que filtra, o `lsGet` e o `<tbody>` são os de verdade.
//
// ── A MORDIDA (provada, não afirmada) ────────────────────────────────────────
// Mutação aplicada ao código real em 2026-09-03: dando precedência à querystring
// (`(filtros.status || lsGet('tab','all'))` nos 5 useState), os DOIS casos de achado ficam
// VERMELHOS — a linha reaparece — e os 6 restantes seguem verdes. O `.tsx` foi restaurado
// byte-idêntico (`git status` limpo) e o verde reconfirmado (8 passed). Sem esse par o
// arquivo seria carimbo: verde que não sabe ficar vermelho.
//
// O primeiro caso é CONTROLE POSITIVO e é ele que valida o harness: se os mocks tivessem
// quebrado o render, ele falharia (exige a linha PRESENTE). Só então o segundo caso — mesma
// prop, mudando apenas o localStorage — significa alguma coisa.
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada sobre o SERVIDOR. Que o Controller filtra por querystring é do
//     `CobrancaControllerTest`, hoje em `.github/financeiro-pest-quarantine.list`.
//   - Nada sobre VALOR. Zero cálculo é tocado: `valor` é constante de fixture e nenhum
//     assert lê dinheiro (REGRA MESTRE, memory/proibicoes.md).
//   - Não afirma que a URL "deveria" mudar. Que deep-link seja desejável é decisão [W],
//     registrada como `[BACKLOG] Deep-link de verdade` no casos.md.
//
// Comando local: npm run test:cobranca-filtros
// @see resources/js/Pages/Financeiro/Cobranca/Index.casos.md (UC-COB-03)
// @see resources/js/Pages/Financeiro/Cobranca/Index.charter.md (linha 45 — a lei)

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  router: { visit: vi.fn(), post: vi.fn(), reload: vi.fn(), get: vi.fn() },
  Deferred: ({ children }: any) => <>{children}</>,
}));
vi.mock('@/Pages/Financeiro/_shared/FinanceiroSubNav', () => ({ default: () => null }));
vi.mock('@/Components/PageHeader', () => ({ PageHeader: () => null, PageHeaderPrimary: () => null }));
vi.mock('@/Components/ui/select', () => ({
  Select: ({ children }: any) => <div>{children}</div>,
  SelectContent: ({ children }: any) => <div>{children}</div>,
  SelectItem: ({ children }: any) => <div>{children}</div>,
  SelectTrigger: ({ children }: any) => <div>{children}</div>,
  SelectValue: () => null,
}));
vi.mock('@/Pages/Financeiro/Cobranca/_components/FunnelStrip', () => ({ default: () => null }));
vi.mock('@/Pages/Financeiro/Cobranca/_components/DrawerCobranca', () => ({ default: () => null }));
vi.mock('@/Pages/Financeiro/Cobranca/_components/SheetNovaCobranca', () => ({ default: () => null }));
vi.mock('@/Pages/Financeiro/Cobranca/_components/SheetRemessaRetorno', () => ({ default: () => null }));
vi.mock('@/Pages/Financeiro/Cobranca/_components/CheatSheet', () => ({ default: () => null }));
vi.mock('@/Pages/Financeiro/Cobranca/_components/AiResumoMes', () => ({ default: () => null }));

import CobrancaPage from '@/Pages/Financeiro/Cobranca/Index';
import { lsGet, LS_PREFIX } from '@/Pages/Financeiro/Cobranca/_lib/cobranca-shared';

const PAGADOR_VENCIDA = 'ACME VENCIDA LTDA';

/** Uma cobranca VENCIDA — e so ela. E o que o servidor devolveria pra `?status=vencida`. */
const COB_VENCIDA = {
  id: 1,
  contato: PAGADOR_VENCIDA,
  contato_doc: '12345678000199',
  tipo: 'boleto',
  gateway: 'inter',
  account_id: 10,
  nosso_numero: 'NN-001',
  valor: 123456,
  status: 'vencida',
  vencimento: '2026-06-01',
  origem_type: null,
  origem_label: null,
} as any;

function montar(filtrosOver: Record<string, unknown> = {}) {
  const props = {
    cobrancas: [COB_VENCIDA],
    kpis: undefined as any,
    funil: undefined as any,
    accounts: [{ id: 10, name: 'Conta Inter', banco: 'Inter', agencia: '0001' }] as any,
    gateways: [] as any,
    filtros: { status: null, tipo: null, gateway: null, account_id: null, origem: null, busca: null, ...filtrosOver } as any,
    isSaasBusiness: false,
    today: '2026-06-11',
  };
  return render(<CobrancaPage {...props} />);
}

beforeEach(() => { localStorage.clear(); });
afterEach(() => { cleanup(); localStorage.clear(); });

describe('UC-COB-03 contestado — querystring x localStorage', () => {
  it('CONTROLE POSITIVO: sem localStorage, o deep-link ?status=vencida mostra a linha', () => {
    montar({ status: 'vencida' });
    expect(screen.queryByText(PAGADOR_VENCIDA)).not.toBeNull();
    expect(screen.queryByText('Nenhuma cobrança encontrada')).toBeNull();
  });

  it('ACHADO: com localStorage tab="paga", o MESMO deep-link ?status=vencida esvazia a tabela', () => {
    localStorage.setItem(LS_PREFIX + 'tab', JSON.stringify('paga'));
    montar({ status: 'vencida' });
    expect(screen.queryByText(PAGADOR_VENCIDA)).toBeNull();
    expect(screen.queryByText('Nenhuma cobrança encontrada')).not.toBeNull();
  });

  it('a raiz: lsGet ignora o default (a querystring) quando a chave existe', () => {
    localStorage.setItem(LS_PREFIX + 'tab', JSON.stringify('paga'));
    expect(lsGet('tab', 'vencida')).toBe('paga');
    localStorage.removeItem(LS_PREFIX + 'tab');
    expect(lsGet('tab', 'vencida')).toBe('vencida');
  });
});

describe('quantos filtros persistem (o "5 de 6" da hipotese)', () => {
  it.each(['tipo', 'gateway', 'origem'])('o filtro %s tambem deixa o localStorage vencer', (chave) => {
    const valorLs: Record<string, string> = { tipo: 'card', gateway: 'asaas', origem: 'invoice' };
    localStorage.setItem(LS_PREFIX + chave, JSON.stringify(valorLs[chave]));
    montar({});
    expect(screen.queryByText(PAGADOR_VENCIDA)).toBeNull();
  });

  it('account: localStorage aponta outra conta e a linha some', () => {
    localStorage.setItem(LS_PREFIX + 'account', JSON.stringify('99'));
    montar({ account_id: 10 });
    expect(screen.queryByText(PAGADOR_VENCIDA)).toBeNull();
  });

  it('CONTRASTE: busca NAO persiste — vem so da querystring', () => {
    localStorage.setItem(LS_PREFIX + 'busca', JSON.stringify('texto-que-nao-casa'));
    montar({});
    expect(screen.queryByText(PAGADOR_VENCIDA)).not.toBeNull();
  });
});
