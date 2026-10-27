/**
 * Calculadora da CV — "Enviar no WhatsApp" gera o link para o cliente aceitar (UC-CVACE-03 · US-COMVIS-023).
 *
 * Âncora: SPEC US-COMVIS-023 ("operador gera o link de um orçamento salvo; o orçamento passa a enviado") +
 * charter do Index (3e). A resposta da API abaixo é a forma que OrcamentoAceiteController::gerar devolve,
 * escrita à mão.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: unknown }) => children,
  Link: ({ children }: { children: unknown }) => children,
  router: { get: vi.fn(), reload: vi.fn(), replace: vi.fn() },
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import Index from '@/Pages/ComunicacaoVisual/Index';

const URL_LINK = 'https://oimpresso.com/orcamento-cv/' + 'a'.repeat(48);

function orcamento(status: string) {
  return {
    id: 5, numero: 'ORC-2026-00005', status, desconto: 0, extras: 0, custo_instalacao: 0, custo_entrega: 0,
    total: 270, os: null, arquivos: [],
    itens: [{ material_id: null, descricao: 'Banner', largura_m: 3, altura_m: 1.5, quantidade: 1, preco_unitario_m2: 60 }],
  };
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('Calculadora CV · link para o cliente', () => {
  it('UC-CVACE-03 · orçamento salvo gera o link e oferece copiar e abrir no WhatsApp', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true, status: 201, json: () => Promise.resolve({ url: URL_LINK, expira_em: '2026-11-02T10:00:00-03:00' }),
    }) as unknown as typeof fetch;
    render(<Index podeCriar bizName="Gráfica Teste" orcamento={orcamento('rascunho')} recentes={[]} />);

    fireEvent.click(screen.getByRole('button', { name: /Enviar no WhatsApp/ }));

    const campo = (await screen.findByLabelText(/Link para o cliente aceitar o ORC-2026-00005/)) as HTMLInputElement;
    expect(campo.value).toBe(URL_LINK);
    const [url, init] = (globalThis.fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(url).toBe('/comunicacao-visual/api/orcamentos/5/link');
    expect(init.method).toBe('POST');

    const wa = screen.getByRole('link', { name: /Abrir no WhatsApp/ }) as HTMLAnchorElement;
    expect(wa.href.startsWith('https://wa.me/?text=')).toBe(true);
    expect(decodeURIComponent(wa.href)).toContain(URL_LINK);
    expect(decodeURIComponent(wa.href)).toContain('ORC-2026-00005');
  });

  it('UC-CVACE-03 · controle: orçamento já aprovado não oferece o link', () => {
    render(<Index podeCriar orcamento={orcamento('aprovado')} recentes={[]} />);

    expect(screen.queryByRole('button', { name: /Enviar no WhatsApp/ })).toBeNull();
    expect(screen.getByRole('button', { name: /Aprovar e criar OS/ })).not.toBeNull();
  });
});
