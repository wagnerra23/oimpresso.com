// UC-INGRED-08..13 · Manufacturing/IngredientesEditor — as 6 regras de TELA do handoff §5.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// As etapas 1-3 do editor (#9059 · #9068 · #9072) trouxeram as 6 regras `[FECHADA]` do §5 para
// a tela, mas a suíte do módulo só tinha Pest de SERVIDOR. As regras de tela ficaram no "Backlog
// de casos" do `IngredientesEditor.casos.md` como "precisa de teste de navegador". Este spec mede
// cada uma no componente REAL, em jsdom — e é o que permite promover o backlog a UC.
//
// ── O QUE ELE MEDE (componente REAL) ─────────────────────────────────────────
// Importa a Page do editor com o `useForm` de verdade (o `@inertiajs/react` real). Os mocks são
// só de borda:
//   - `@/Components/ui/select` vira `<select>` nativo. O Radix Select não abre de forma confiável
//     em jsdom e o repo não tem `user-event`. A regra 4 é sobre o MULTIPLICADOR vir da sub-unidade
//     — isso é do componente, não do widget: o `<select>` só entrega o valor escolhido.
//   - o `router` do Inertia (por onde o `useForm.post` sai) e o `fetch` (por onde a exclusão sai)
//     são espionados para provar que nada é enviado sem querer (regras 5 e 6). O espião do router
//     tem controle positivo próprio: clicar "Salvar receita" TEM de passar por ele.
//
// Cada bloco tem um CONTROLE POSITIVO: o mesmo render no estado em que a regra NÃO dispara, para
// provar que o harness enxerga o elemento. Sem ele, um "não existe" seria só um render quebrado.
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada do SERVIDOR (salvar recusa receita vazia, recalcula custo, só apaga a própria
//     empresa) — isso é Pest na lane MySQL (`SalvarReceitaServidorTest`, UC-RECIPE-14..19).
//   - O Radix Select de verdade (abrir/fechar, teclado) — só o valor que ele entrega.
//   - Pixel e layout — jsdom não renderiza CSS.
//
// Comando local (vitest, fora do CT 100 — ADR 0062 cobre Pest/PHPStan):
//   npx vitest run tests/js/manufacturing-ingredientes-editor.test.tsx
// @see resources/js/Pages/Manufacturing/IngredientesEditor.casos.md (UC-INGRED-08..13)
// @covers-us US-MANU-006

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, within, waitFor } from '@testing-library/react';

vi.mock('@/Components/ui/select', () => {
  // `<select>` nativo no lugar do Radix: SelectTrigger carrega o aria-label, SelectItem vira <option>.
  const Ctx: { onValueChange?: (v: string) => void } = {};
  return {
    Select: ({ value, onValueChange, disabled, children }: any) => {
      Ctx.onValueChange = onValueChange;
      const kids = Array.isArray(children) ? children : [children];
      const trigger = kids.find((k: any) => k?.type?.displayName === 'SelectTrigger');
      const content = kids.find((k: any) => k?.type?.displayName === 'SelectContent');
      return (
        <select aria-label={trigger?.props?.['aria-label']} value={value} disabled={disabled} onChange={(e) => onValueChange?.(e.target.value)}>
          {content?.props?.children}
        </select>
      );
    },
    SelectTrigger: Object.assign(() => null, { displayName: 'SelectTrigger' }),
    SelectValue: () => null,
    SelectContent: Object.assign(({ children }: any) => <>{children}</>, { displayName: 'SelectContent' }),
    SelectItem: ({ value, children }: any) => <option value={value}>{children}</option>,
  };
});
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));

import { router } from '@inertiajs/react';
import IngredientesEditor from '@/Pages/Manufacturing/IngredientesEditor';

const linha = (over: Record<string, unknown> = {}) => ({
  variation_id: 501,
  nome: 'Tinta solvente',
  sku: 'INS-022',
  custo_unitario: 10,
  unidade_base: 'L',
  unidade_base_id: 7,
  sub_unidades: [
    { id: 7, nome: 'L', multiplicador: 1 },
    { id: 8, nome: 'galão (5 L)', multiplicador: 5 },
  ],
  linha_id: 91,
  quantidade: 2,
  sub_unit_id: null,
  waste_percent: 0,
  ...over,
});

const grupo = (itens = [linha()]) => ({ id: 31, nome: 'Impressão', descricao: '', sem_grupo: false, itens });

const props = (over: Record<string, unknown> = {}) => ({
  produto: { variation_id: 900, product_id: 90, nome: 'Banner 440g', sku: 'PROD-BAN-440', unidade: 'm²', unidade_id: 3, sub_unidades: [] },
  receita: {
    id: 12,
    copiada_de: null,
    total_quantity: 10,
    waste_percent: 0,
    extra_cost: 0,
    production_cost_type: 'fixed' as const,
    instructions: '',
    sub_unit_id: null,
  },
  grupos: [grupo()],
  grupos_da_empresa: ['Impressão', 'Acabamento'],
  perms: { editar: true },
  travar_qtd: false,
  ...over,
});

const salvar = () => screen.queryByRole('button', { name: 'Salvar receita' }) as HTMLButtonElement | null;

let fetchSpy: ReturnType<typeof vi.fn>;
let visitSpy: ReturnType<typeof vi.spyOn>;
beforeEach(() => {
  fetchSpy = vi.fn(() => Promise.resolve(new Response('{}')));
  vi.stubGlobal('fetch', fetchSpy);
  visitSpy = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
});
afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  visitSpy.mockRestore();
});

describe('UC-INGRED-08 · salvar exige pelo menos 1 ingrediente (regra 1)', () => {
  it('UC-INGRED-08 controle positivo: com ingrediente, "Salvar receita" fica habilitado', () => {
    render(<IngredientesEditor {...props()} />);
    expect(salvar()).not.toBeNull();
    expect(salvar()!.disabled).toBe(false);
  });

  it('UC-INGRED-08 tirar o último ingrediente desabilita o salvar e diz por quê', () => {
    render(<IngredientesEditor {...props()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Remover Tinta solvente' }));
    expect(salvar()!.disabled).toBe(true);
    expect(screen.getByRole('status').textContent).toContain('A receita precisa de pelo menos 1 ingrediente.');
  });

  it('UC-INGRED-08 receita que já abre vazia: salvar desabilitado com o mesmo motivo', () => {
    render(<IngredientesEditor {...props({ grupos: [] })} />);
    expect(salvar()!.disabled).toBe(true);
    expect(screen.getByText('Esta receita ainda não tem ingredientes.')).toBeTruthy();
  });
});

describe('UC-INGRED-09 · quantidade travada em Configurações vira texto (regra 2)', () => {
  it('UC-INGRED-09 controle positivo: sem trava, a quantidade é campo editável', () => {
    render(<IngredientesEditor {...props()} />);
    expect(screen.getByLabelText('Quantidade de Tinta solvente')).toBeTruthy();
    expect(screen.queryByText('Edição de quantidade de ingrediente está bloqueada em Configurações.')).toBeNull();
  });

  it('UC-INGRED-09 com a trava, a quantidade aparece como texto e a tela avisa', () => {
    render(<IngredientesEditor {...props({ travar_qtd: true })} />);
    expect(screen.queryByLabelText('Quantidade de Tinta solvente')).toBeNull();
    expect(screen.getByText('2,00')).toBeTruthy();
    expect(screen.getByText('Edição de quantidade de ingrediente está bloqueada em Configurações.')).toBeTruthy();
  });
});

describe('UC-INGRED-10 · quem só consulta abre em leitura, sem salvar nem excluir (regra 3)', () => {
  it('UC-INGRED-10 controle positivo: quem grava vê Salvar, Excluir e campos ativos', () => {
    render(<IngredientesEditor {...props()} />);
    expect(salvar()).not.toBeNull();
    expect(screen.getByRole('button', { name: 'Excluir receita' })).toBeTruthy();
    expect((screen.getByLabelText('Quantidade produzida') as HTMLInputElement).disabled).toBe(false);
  });

  it('UC-INGRED-10 sem permissão: campos desabilitados, sem Salvar/Excluir, aviso com a permissão real', () => {
    render(<IngredientesEditor {...props({ perms: { editar: false } })} />);
    expect(salvar()).toBeNull();
    expect(screen.queryByRole('button', { name: 'Excluir receita' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Remover Tinta solvente' })).toBeNull();
    expect(screen.queryByLabelText('Quantidade de Tinta solvente')).toBeNull();
    for (const rotulo of ['Quantidade produzida', 'Desperdício em porcentagem', 'Instruções']) {
      expect((screen.getByLabelText(rotulo) as HTMLInputElement).disabled).toBe(true);
    }
    expect(screen.getByText('Sua permissão é apenas de leitura (manufacturing.access_recipe).')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Voltar para Receitas' }).getAttribute('href')).toBe('/manufacturing/recipe');
  });
});

describe('UC-INGRED-11 · trocar a sub-unidade troca o multiplicador junto (regra 4)', () => {
  const subtotalDaLinha = () => {
    const nome = screen.getByText('Tinta solvente');
    return nome.closest('.mfg-ing')!.querySelector('.tot')!.textContent ?? '';
  };

  it('UC-INGRED-11 controle positivo: na unidade base, subtotal = 2 × 10 × 1', () => {
    render(<IngredientesEditor {...props()} />);
    expect(subtotalDaLinha()).toMatch(/20,00/);
    expect(screen.queryByText(/equivale a/)).toBeNull();
  });

  it('UC-INGRED-11 escolher "galão (5 L)" multiplica o subtotal por 5 e mostra a equivalência', () => {
    render(<IngredientesEditor {...props()} />);
    fireEvent.change(screen.getByLabelText('Unidade de Tinta solvente'), { target: { value: '8' } });
    expect(subtotalDaLinha()).toMatch(/100,00/);
    expect(screen.getByText(/equivale a 10,000 L/)).toBeTruthy();
    // O multiplicador não é digitável: não existe campo para ele na linha.
    expect(screen.queryByLabelText(/multiplicador/i)).toBeNull();
  });

  it('UC-INGRED-11 voltar para a unidade base volta o multiplicador para 1', () => {
    render(<IngredientesEditor {...props({ grupos: [grupo([linha({ sub_unit_id: 8 })])] })} />);
    expect(subtotalDaLinha()).toMatch(/100,00/);
    fireEvent.change(screen.getByLabelText('Unidade de Tinta solvente'), { target: { value: 'base' } });
    expect(subtotalDaLinha()).toMatch(/20,00/);
  });
});

describe('UC-INGRED-12 · o editor trabalha numa cópia e cancelar não grava nada (regra 5)', () => {
  it('UC-INGRED-12 controle positivo: editar a quantidade muda o subtotal na tela', () => {
    render(<IngredientesEditor {...props()} />);
    fireEvent.change(screen.getByLabelText('Quantidade de Tinta solvente'), { target: { value: '3' } });
    const linhaTela = screen.getByText('Tinta solvente').closest('.mfg-ing')!;
    expect(linhaTela.querySelector('.tot')!.textContent).toMatch(/30,00/);
  });

  it('UC-INGRED-12 controle do espião: "Salvar receita" passa pelo router (o espião enxerga o salvar)', () => {
    render(<IngredientesEditor {...props()} />);
    fireEvent.click(salvar()!);
    expect(visitSpy).toHaveBeenCalledTimes(1);
    expect(visitSpy.mock.calls[0][0]).toBe('/manufacturing/recipe');
  });

  it('UC-INGRED-12 editar e remover mexem só na cópia: a ficha recebida fica intacta e nada é enviado', () => {
    const p = props();
    const antes = JSON.stringify(p.grupos);
    render(<IngredientesEditor {...p} />);
    fireEvent.change(screen.getByLabelText('Quantidade de Tinta solvente'), { target: { value: '7' } });
    fireEvent.click(screen.getByRole('button', { name: 'Remover Tinta solvente' }));
    expect(JSON.stringify(p.grupos)).toBe(antes);
    expect(visitSpy).not.toHaveBeenCalled();
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('UC-INGRED-12 "Cancelar" é só um link de volta para Receitas', () => {
    render(<IngredientesEditor {...props()} />);
    const cancelar = screen.getByRole('link', { name: 'Cancelar' });
    expect(cancelar.getAttribute('href')).toBe('/manufacturing/recipe');
  });
});

describe('UC-INGRED-13 · excluir receita pede confirmação que diz o que se perde (regra 6)', () => {
  it('UC-INGRED-13 controle positivo: receita ainda não gravada não oferece "Excluir receita"', () => {
    render(<IngredientesEditor {...props({ receita: { ...props().receita, id: null } })} />);
    expect(screen.queryByRole('button', { name: 'Excluir receita' })).toBeNull();
    expect(salvar()).not.toBeNull();
  });

  it('UC-INGRED-13 a confirmação diz o nome, os ingredientes GRAVADOS e que as ordens ficam com o custo', () => {
    const doisItens = [grupo([linha(), linha({ variation_id: 502, nome: 'Lona 440g', sku: 'INS-001', linha_id: 92 })])];
    render(<IngredientesEditor {...props({ grupos: doisItens })} />);
    // Remover na cópia NÃO muda o número da confirmação: o que se apaga é a ficha gravada.
    fireEvent.click(screen.getByRole('button', { name: 'Remover Lona 440g' }));
    fireEvent.click(screen.getByRole('button', { name: 'Excluir receita' }));
    const dialogo = screen.getByRole('alertdialog');
    const texto = dialogo.textContent ?? '';
    expect(texto).toContain('Banner 440g');
    expect(texto).toContain('os 2 ingredientes');
    expect(texto).toContain('Ordens de produção já lançadas continuam com o custo registrado.');
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('UC-INGRED-13 confirmar apaga ESTA receita e volta para a lista de Receitas', async () => {
    fetchSpy.mockImplementation(() => Promise.resolve(new Response(JSON.stringify({ success: 1 }), { status: 200 })));
    render(<IngredientesEditor {...props()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Excluir receita' }));
    fireEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Excluir receita' }));
    await waitFor(() => expect(visitSpy).toHaveBeenCalledTimes(1));
    expect(fetchSpy).toHaveBeenCalledTimes(1);
    const [url, init] = fetchSpy.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/manufacturing/recipe/12');
    expect(init.method).toBe('DELETE');
    expect(visitSpy.mock.calls[0][0]).toBe('/manufacturing/recipe');
  });

  it('UC-INGRED-13 se o servidor recusa, a tela diz por quê e não sai do editor', async () => {
    fetchSpy.mockImplementation(() => Promise.resolve(new Response(JSON.stringify({ success: 0, msg: 'Receita de outra empresa.' }), { status: 200 })));
    render(<IngredientesEditor {...props()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Excluir receita' }));
    fireEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Excluir receita' }));
    expect(await screen.findByText('Receita de outra empresa.')).toBeTruthy();
    expect(visitSpy).not.toHaveBeenCalled();
  });

  it('UC-INGRED-13 "Cancelar" na confirmação fecha sem apagar', () => {
    render(<IngredientesEditor {...props()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Excluir receita' }));
    fireEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Cancelar' }));
    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(fetchSpy).not.toHaveBeenCalled();
  });
});
