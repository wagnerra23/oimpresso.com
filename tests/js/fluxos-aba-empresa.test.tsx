// UC-FLXT-14/15/16 · Fluxos/Index, aba Fluxos da empresa — os switches e a Compra configurável.
//
// Componente REAL (`AbaEmpresa`); o mock cobre só o `router`/`usePage`/`Link` do Inertia, para
// ler o que a tela manda ao servidor. Que o servidor grava certo é do FluxosTelaContratoTest.
//
// Comando local: npx vitest run tests/js/fluxos-aba-empresa.test.tsx
// @see resources/js/Pages/Fluxos/Index.casos.md

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const patch = vi.fn();
let erros: Record<string, string> = {};
vi.mock('@inertiajs/react', () => ({
  router: { patch: (...a: unknown[]) => patch(...a) },
  usePage: () => ({ props: { errors: erros } }),
  Link: ({ children, href }: any) => <a href={href}>{children}</a>,
}));

import AbaEmpresa from '@/Pages/Fluxos/_components/AbaEmpresa';
import type { Modelo, RegraCompra } from '@/Pages/Fluxos/_components/tipos';

const modelo: Modelo = {
  id: 'cv-pequena', nome: 'Comunicação visual · pequena', para: '', portes: ['pequena'], atividades: ['cv'],
  casos: [], insp: [],
  processos: [
    { dominio: 'producao', nome: 'Produção', etapas: ['Arte', 'Impressão', 'Entregue'], regras: [
      { etapa: 'Impressão', efeito: 'Apontamento pelo celular', uc: 'UC-FLX-09' },
      { etapa: 'Entregue', efeito: 'Protocolo + pesquisa em 2 dias', uc: 'UC-FLX-23' },
    ] },
    { dominio: 'compra', nome: 'Compra', etapas: ['Pedido', 'Recebido', 'Pago'], regras: [
      { etapa: 'Recebido', efeito: 'Custo médio', uc: 'UC-FLX-17' },
    ] },
  ],
};

const compra = (preco: string): RegraCompra[] => [
  { id: 'recebido.estoque', titulo: 'Dar entrada no estoque', desc: '', trava: true, ativo: true, params: null, etapa_efetiva: 'recebido' },
  { id: 'insumo.custo', titulo: 'Atualizar o custo dos insumos', desc: '', trava: false, ativo: true,
    params: { etapa: 'recebido', metodo: 'medio', preco, regraEm: 'produto', aprovacao: 'recebimento', ops: 'congela' }, etapa_efetiva: 'recebido' },
];

const opcoesCompra = Object.fromEntries(['etapa', 'metodo', 'preco', 'regraEm', 'aprovacao', 'ops'].map((k) => [k, {
  rotulo: { etapa: 'Dispara na etapa', metodo: 'Novo custo do insumo', preco: 'Preço de venda do produto', regraEm: 'Quais produtos seguem', aprovacao: 'Quem aprova e onde', ops: 'Ordens de produção abertas' }[k]!,
  opcoes: [{ value: 'medio', label: 'Custo médio ponderado' }, { value: 'recebido', label: 'Recebido' }, { value: 'propor', label: 'Propõe' }, { value: 'so_custo', label: 'Só custo' }, { value: 'produto', label: 'Produto' }, { value: 'recebimento', label: 'No recebimento' }, { value: 'congela', label: 'Congela' }],
}]));

function tela(pode: boolean, preco = 'propor', ligadas = { producao: [true, false], compra: [true] }) {
  return render(
    <AbaEmpresa
      empresa={{ modelo: 'cv-pequena', porte: 'pequena', atividade: 'cv', aplicado_em: null }}
      emUso={modelo}
      portes={[]}
      atividades={[]}
      ligadas={ligadas}
      compra={compra(preco)}
      opcoesCompra={opcoesCompra}
      podeConfigurar={pode}
    />,
  );
}

beforeEach(() => { patch.mockReset(); erros = {}; });
afterEach(cleanup);

describe('aba Fluxos da empresa', () => {
  it('UC-FLXT-14 · cada regra tem switch com o estado gravado e mandar desligar vai para /fluxos/regra', () => {
    tela(true);
    const impressao = screen.getByRole('switch', { name: /Apontamento pelo celular/ });
    const entregue = screen.getByRole('switch', { name: /Protocolo \+ pesquisa/ });
    expect(impressao.getAttribute('aria-checked')).toBe('true');
    expect(entregue.getAttribute('aria-checked')).toBe('false');

    fireEvent.click(impressao);
    expect(patch).toHaveBeenCalledWith('/fluxos/regra', { dominio: 'producao', indice: 0, ligada: false }, expect.anything());
  });

  it('UC-FLXT-15 · a Compra mostra as regras de fsm-regras, não as do modelo, com os parâmetros do custo', () => {
    tela(true);
    expect(screen.getByText('configurável')).toBeTruthy();
    expect(screen.queryByRole('switch', { name: /Custo médio/ })).toBeNull();
    expect(screen.getByRole('switch', { name: /Atualizar o custo dos insumos/ })).toBeTruthy();
    expect(screen.getByText('Novo custo do insumo')).toBeTruthy();
    // "Quem aprova" só aparece quando o preço é proposto (fsm-regras.jsx `visivel`).
    expect(screen.getByText('Quem aprova e onde')).toBeTruthy();

    fireEvent.click(screen.getByRole('switch', { name: /Atualizar o custo dos insumos/ }));
    expect(patch).toHaveBeenCalledWith('/fluxos/compra', { regra: 'insumo.custo', ativo: false }, expect.anything());
  });

  it('UC-FLXT-15 · só custo esconde quem aprova e quais produtos seguem', () => {
    tela(true, 'so_custo');
    expect(screen.queryByText('Quem aprova e onde')).toBeNull();
    expect(screen.queryByText('Quais produtos seguem')).toBeNull();
    expect(screen.getByText('Ordens de produção abertas')).toBeTruthy();
  });

  it('UC-FLXT-16 · a entrada no estoque é obrigatória: switch travado', () => {
    tela(true);
    const estoque = screen.getByRole('switch', { name: /Dar entrada no estoque/ });
    expect((estoque as HTMLButtonElement).disabled).toBe(true);
    expect(screen.getByText(/obrigatória/)).toBeTruthy();
  });

  it('UC-FLXT-17 · sem fluxos.configurar todos os switches ficam só para leitura', () => {
    tela(false);
    for (const s of screen.getAllByRole('switch')) {
      expect((s as HTMLButtonElement).disabled).toBe(true);
    }
  });

  it('erro do servidor aparece na aba', () => {
    erros = { regra: 'Escolha um modelo antes de configurar as regras.' };
    tela(true);
    expect(screen.getByRole('alert').textContent).toContain('Escolha um modelo antes de configurar as regras.');
  });
});
