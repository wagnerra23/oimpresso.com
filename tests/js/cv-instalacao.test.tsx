/**
 * Instalação no celular (CV) — a foto vai para a API do comprovante e a recusa fica visível
 * (UC-CVCEL-04 · US-COMVIS-007 · UC-PCV-25).
 *
 * Âncora: charter da Instalação ("o item fica marcado quando a API grava"; "quando a API recusa … o alerta
 * 'Não enviado' mostra a mensagem dela"). A mensagem de recusa abaixo é a que ComprovanteInstalacao devolve
 * para instalação concluída (InstalacaoComprovanteContratoTest UC-CVINST-14), escrita à mão.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

const reload = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { reload: (...a: unknown[]) => reload(...a) },
  Head: () => null,
}));

import Instalacao, { type Comprovante, type InstalacaoCampo } from '@/Pages/ComunicacaoVisual/Instalacao';

const INST: InstalacaoCampo = {
  id: 55,
  os_numero: 'OS-55',
  cliente: 'Padaria Exemplo Teste',
  endereco: 'Rua das Placas, 100 — Cidade Teste/SC',
  inicio: '2026-11-19T09:00:00-03:00',
  fim: '2026-11-19T11:00:00-03:00',
  equipe: ['Instalador Gama'],
  veiculo: 'Fiorino ABC1D23',
  status: 'agendada',
};
const VAZIO: Comprovante = { foto_antes: false, foto_depois: false, assinatura: null, status: 'agendada', pode_concluir: false };

function responde(status: number, corpo: unknown) {
  globalThis.fetch = vi.fn().mockResolvedValue({ status, json: () => Promise.resolve(corpo) }) as unknown as typeof fetch;
}

function escolherFotoAntes() {
  render(<Instalacao instalacao={INST} comprovante={VAZIO} podeRegistrar />);
  const arquivo = new File(['x'], 'antes.jpg', { type: 'image/jpeg' });
  fireEvent.change(screen.getByLabelText('Tirar foto antes'), { target: { files: [arquivo] } });
  return arquivo;
}

afterEach(() => {
  vi.restoreAllMocks();
  reload.mockReset();
});

describe('Instalação CV · foto do comprovante', () => {
  it('UC-CVCEL-04 · a foto antes vai para a API com o tipo e, com 201, recarrega só o comprovante', async () => {
    responde(201, VAZIO);
    const arquivo = escolherFotoAntes();

    await waitFor(() => expect(reload).toHaveBeenCalledWith({ only: ['comprovante'] }));
    const [url, init] = (globalThis.fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(url).toBe('/comunicacao-visual/api/instalacoes/55/fotos');
    expect(init.method).toBe('POST');
    expect((init.body as FormData).get('tipo')).toBe('foto_antes');
    expect((init.body as FormData).get('arquivo')).toBe(arquivo);
    expect(screen.queryByText('Não enviado')).toBeNull();
  });

  it('UC-CVCEL-04 · 422 vira o alerta "Não enviado" com a mensagem da API, e nada recarrega', async () => {
    responde(422, { message: 'A instalação está concluída e não recebe mais comprovante.' });
    escolherFotoAntes();

    const titulo = await screen.findByText('Não enviado');
    const caixa = titulo.closest('[data-contract="erro"]');
    expect(caixa?.textContent).toContain('A instalação está concluída e não recebe mais comprovante.');
    expect(reload).not.toHaveBeenCalled();
  });

  it('UC-CVCEL-04 · a tela mostra onde e quando, e quem só vê não tem o botão da foto', () => {
    render(<Instalacao instalacao={INST} comprovante={{ ...VAZIO, foto_antes: true }} podeRegistrar={false} />);

    const card = document.querySelector('[data-contract="instalacao"]');
    expect(card?.textContent).toContain('19/11 às 09:00–11:00');
    expect(card?.textContent).toContain('Padaria Exemplo Teste');
    expect(screen.getByRole('link', { name: /Abrir no mapa/ }).getAttribute('href')).toBe(
      `https://maps.google.com/?q=${encodeURIComponent(INST.endereco ?? '')}`,
    );
    expect(screen.queryByLabelText('Tirar foto antes')).toBeNull();
    expect(document.querySelector('[data-item="foto_antes"]')?.textContent).toContain('Enviada');
    expect(document.querySelector('[data-item="foto_depois"]')?.textContent).toContain('Pendente');
  });
});
