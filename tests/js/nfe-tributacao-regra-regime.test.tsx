/**
 * Regra tributária — trocar o regime limpa o código do outro regime antes de salvar.
 *
 * @covers-us UC-NFRF-12
 *
 * Por que RENDER: quem decide qual código vai no corpo do POST é o `submit` do `RegraForm.tsx`
 * ("Limpa o campo não usado dependendo do regime"). O servidor aceita CST e CSOSN juntos, então
 * nenhum teste Pest enxerga se a tela mandou os dois. O `useForm` aqui é o REAL do
 * `@inertiajs/react` (versão do lockfile, a que o build usa) — mockar o `useForm` provaria só o mock.
 * Só o envio é interceptado (`router.post` / `router.put`), nada chega ao servidor.
 *
 * Contrato: RegraForm.charter.md §Goals (CSOSN para Simples, CST para Normal) e o motor tributário,
 * que tem dois caminhos se as duas colunas vierem preenchidas (backlog "Exclusividade CSOSN × CST").
 *
 * ⚠️ ACHADO (2026-11-25) — UC-NFRF-12 nasce ❌. Os dois casos de troca fixam o que a tela FAZ hoje
 * (o 1º envio leva os dois códigos), assert a assert, para não ficarem verdes por um motivo qualquer
 * como um `it.fails` ficaria. Quando o defeito for consertado eles reprovam a lane, e quem consertar
 * troca o `ACHADO` pelo contrato (`csosn`/`cst` vazio no 1º envio) no mesmo PR. Medido com o `@inertiajs/react` 3.0.3 do
 * lockfile: o `setData` do `submit` só chega ao `dataRef` do `useForm` depois do próximo render, e
 * o `post`/`put` logo abaixo lê o valor velho. O 1º envio sai com CST E CSOSN; o servidor recusa
 * ("CSOSN e CST não podem ser preenchidos juntos", erro no campo `csosn`, que no modo CST a tela não
 * mostra); o 2º clique sai limpo. Controle por mutação: trocando os dois `setData` por um
 * `form.transform(...)`, os asserts de CONTRATO passam e os dois `ACHADO` caem. O conserto não entra aqui (escopo: teste).
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { router } from '@inertiajs/react';

vi.mock('@inertiajs/react', async (original) => {
  const real = await original<typeof import('@inertiajs/react')>();
  return {
    ...real,
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => children,
  };
});
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import RegraForm from '@/Pages/NfeBrasil/Tributacao/RegraForm';

type Enviado = Record<string, unknown>;
let post: ReturnType<typeof vi.spyOn>;
let put: ReturnType<typeof vi.spyOn>;

beforeEach(() => {
  post = vi.spyOn(router, 'post').mockImplementation(() => {});
  put = vi.spyOn(router, 'put').mockImplementation(() => {});
});
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

/** Abre o seletor de regime (o que mostra o regime atual) e escolhe a opção. */
function trocarRegime(atual: string, novo: string) {
  const campo = screen.getAllByRole('combobox').find((c) => c.textContent?.includes(atual));
  expect(campo, `seletor de regime mostrando "${atual}"`).toBeTruthy();
  fireEvent.keyDown(campo!, { key: 'ArrowDown' });
  fireEvent.click(screen.getByRole('option', { name: novo }));
}

/** O campo do código (CSOSN ou CST, conforme o regime) — é um só, com id `codigo`. */
function campoCodigo() {
  const el = document.getElementById('codigo');
  expect(el, 'campo do código CSOSN/CST').toBeTruthy();
  return el!;
}

function salvar() {
  fireEvent.click(screen.getByRole('button', { name: /Salvar|Criar|Atualizar/ }));
}

const regraNormal = {
  id: 7, ncm: '84439100', uf_origem: 'SP', uf_destino: null, cfop: '5102',
  csosn: null, cst: '000',
  aliquota_icms: 0.18, aliquota_pis: 0.0165, aliquota_cofins: 0.076, aliquota_ipi: 0,
  cst_pis: null, cst_cofins: null, mva: null, fcp: null,
  c_class_trib: null, cst_ibs: null, cst_cbs: null, aliquota_ibs: 0, aliquota_cbs: 0,
};

describe('Regra tributária — o código do regime que não vale sai do envio', () => {
  it('UC-NFRF-12 · ACHADO: nova regra, trocar Simples para Normal — o 1º envio ainda leva o CSOSN junto do CST', () => {
    render(<RegraForm regra={null} />);
    fireEvent.change(screen.getByLabelText(/NCM/), { target: { value: '84439100' } });

    trocarRegime('Simples (CSOSN)', 'Normal (CST)');
    fireEvent.change(campoCodigo(), { target: { value: '000' } });
    salvar();

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('/nfe-brasil/tributacao/regras');
    const primeiro = post.mock.calls[0][1] as Enviado;
    expect(primeiro.cst).toBe('000');
    // CONTRATO: deveria ser ''. Hoje vai o '102' padrão do Simples, e o servidor recusa os dois juntos.
    expect(primeiro.csosn).toBe('102');

    // O 2º clique já sai limpo: o setData do 1º chegou ao useForm depois do render.
    salvar();
    const segundo = post.mock.calls[1][1] as Enviado;
    expect(segundo.cst).toBe('000');
    expect(segundo.csosn).toBe('');
  });

  it('UC-NFRF-12 · ACHADO: edição, trocar Normal para Simples — o 1º envio ainda leva o CST junto do CSOSN', () => {
    render(<RegraForm regra={regraNormal} />);

    trocarRegime('Normal (CST)', 'Simples (CSOSN)');
    fireEvent.change(campoCodigo(), { target: { value: '102' } });
    salvar();

    expect(put).toHaveBeenCalledTimes(1);
    expect(put.mock.calls[0][0]).toBe('/nfe-brasil/tributacao/regras/7');
    const primeiro = put.mock.calls[0][1] as Enviado;
    expect(primeiro.csosn).toBe('102');
    // CONTRATO: deveria ser ''. Hoje vai o CST que a regra tinha.
    expect(primeiro.cst).toBe('000');

    salvar();
    const segundo = put.mock.calls[1][1] as Enviado;
    expect(segundo.csosn).toBe('102');
    expect(segundo.cst).toBe('');
  });

  it('UC-NFRF-12 · controle: sem trocar o regime, a edição envia o CST que já tinha e o CSOSN vazio', () => {
    render(<RegraForm regra={regraNormal} />);
    salvar();

    expect(put).toHaveBeenCalledTimes(1);
    const corpo = put.mock.calls[0][1] as Enviado;
    expect(corpo.cst).toBe('000');
    expect(corpo.csosn).toBe('');
  });
});
