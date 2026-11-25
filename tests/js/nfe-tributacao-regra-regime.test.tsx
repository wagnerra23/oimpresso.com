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
 * Histórico: o UC-NFRF-12 nasceu ❌ em 2026-11-25. Com o `@inertiajs/react` 3.0.3 do lockfile, o
 * `submit` limpava o campo com `setData` e o `post`/`put` logo abaixo lia o valor velho: o 1º envio
 * saía com CST E CSOSN, o servidor recusava com o erro num campo que a tela não mostrava, e só o 2º
 * clique passava. Consertado no mesmo dia trocando os `setData` por `form.transform(...)`; os dois
 * casos de troca agora exigem o código do outro regime vazio já no 1º envio.
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
  it('UC-NFRF-12 · nova regra, trocar Simples para Normal — o 1º envio já sai sem o CSOSN', () => {
    render(<RegraForm regra={null} />);
    fireEvent.change(screen.getByLabelText(/NCM/), { target: { value: '84439100' } });

    trocarRegime('Simples (CSOSN)', 'Normal (CST)');
    fireEvent.change(campoCodigo(), { target: { value: '000' } });
    salvar();

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('/nfe-brasil/tributacao/regras');
    const primeiro = post.mock.calls[0][1] as Enviado;
    expect(primeiro.cst).toBe('000');
    expect(primeiro.csosn).toBe('');

    // O 2º clique sai igual.
    salvar();
    const segundo = post.mock.calls[1][1] as Enviado;
    expect(segundo.cst).toBe('000');
    expect(segundo.csosn).toBe('');
  });

  it('UC-NFRF-12 · edição, trocar Normal para Simples — o 1º envio já sai sem o CST', () => {
    render(<RegraForm regra={regraNormal} />);

    trocarRegime('Normal (CST)', 'Simples (CSOSN)');
    fireEvent.change(campoCodigo(), { target: { value: '102' } });
    salvar();

    expect(put).toHaveBeenCalledTimes(1);
    expect(put.mock.calls[0][0]).toBe('/nfe-brasil/tributacao/regras/7');
    const primeiro = put.mock.calls[0][1] as Enviado;
    expect(primeiro.csosn).toBe('102');
    expect(primeiro.cst).toBe('');

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
