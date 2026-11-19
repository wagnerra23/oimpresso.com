/**
 * Instalação no celular (CV) — foto, assinatura e concluir vão para a API do comprovante e a recusa fica
 * visível (UC-CVCEL-04..06 · US-COMVIS-007 · UC-PCV-25).
 *
 * Âncora: charter da Instalação ("o item fica marcado quando a API grava"; "quando a API recusa … o alerta
 * 'Não enviado' mostra a mensagem dela"). A mensagem de recusa abaixo é a que ComprovanteInstalacao devolve
 * para instalação concluída (InstalacaoComprovanteContratoTest UC-CVINST-14), escrita à mão.
 *
 * O quadro de assinatura desenha em <canvas>, que o jsdom não tem: aqui ele é trocado por um botão que entrega
 * um PNG de mentira, para testar o que a TELA faz com ele.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

const reload = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { reload: (...a: unknown[]) => reload(...a) },
  Head: () => null,
}));
vi.mock('@/Components/shared/QuadroAssinatura', () => ({
  QuadroAssinatura: ({ onChange }: { onChange: (png: string | null) => void }) => (
    <button type="button" onClick={() => onChange('data:image/png;base64,AAAA')}>assinar no quadro</button>
  ),
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

  it('UC-CVCEL-05 · a assinatura só sai com desenho, nome e aceite, e vai para a API com os três', async () => {
    responde(201, VAZIO);
    render(<Instalacao instalacao={INST} comprovante={{ ...VAZIO, foto_antes: true, foto_depois: true }} podeRegistrar />);
    const salvar = screen.getByRole('button', { name: 'Salvar assinatura' });

    fireEvent.click(screen.getByRole('button', { name: 'assinar no quadro' }));
    expect(salvar).toHaveProperty('disabled', true);
    fireEvent.change(screen.getByLabelText('Nome de quem assinou'), { target: { value: '  Marta Ribeiro ' } });
    expect(salvar).toHaveProperty('disabled', true); // falta o aceite
    fireEvent.click(screen.getByRole('checkbox'));
    expect(salvar).toHaveProperty('disabled', false);

    fireEvent.click(salvar);
    await waitFor(() => expect(reload).toHaveBeenCalledWith({ only: ['comprovante'] }));
    const [url, init] = (globalThis.fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(url).toBe('/comunicacao-visual/api/instalacoes/55/assinatura');
    expect(JSON.parse(init.body)).toEqual({ png: 'data:image/png;base64,AAAA', assinado_por: 'Marta Ribeiro', consentimento: true });
  });

  it('UC-CVCEL-06 · Concluir fica travado até ter os três; com os três vai para a API e recarrega a instalação', async () => {
    const { unmount } = render(<Instalacao instalacao={INST} comprovante={{ ...VAZIO, foto_antes: true }} podeRegistrar />);
    expect(screen.getByRole('button', { name: 'Concluir instalação' })).toHaveProperty('disabled', true);
    expect(screen.getByText('Libera com foto antes, foto depois e assinatura do cliente.')).not.toBeNull();
    unmount();

    responde(200, VAZIO);
    const completo: Comprovante = { foto_antes: true, foto_depois: true, assinatura: { assinado_por: 'Marta Ribeiro', em: '2026-11-19 10:50:00' }, status: 'agendada', pode_concluir: true };
    render(<Instalacao instalacao={INST} comprovante={completo} podeRegistrar />);
    expect(screen.queryByRole('button', { name: 'Salvar assinatura' })).toBeNull(); // já assinada
    fireEvent.click(screen.getByRole('button', { name: 'Concluir instalação' }));

    await waitFor(() => expect(reload).toHaveBeenCalledWith({ only: ['comprovante', 'instalacao'] }));
    const [url] = (globalThis.fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(url).toBe('/comunicacao-visual/api/instalacoes/55/concluir');
  });

  it('UC-CVCEL-06 · concluída: sem botões, com o aviso de comprovante completo', () => {
    const fechado: Comprovante = { foto_antes: true, foto_depois: true, assinatura: { assinado_por: 'Marta Ribeiro', em: '2026-11-19 10:50:00' }, status: 'concluida', pode_concluir: false };
    render(<Instalacao instalacao={{ ...INST, status: 'concluida' }} comprovante={fechado} podeRegistrar />);
    expect(screen.queryByRole('button', { name: 'Concluir instalação' })).toBeNull();
    expect(screen.queryByLabelText('Refazer foto antes')).toBeNull();
    expect(screen.getByText('Instalação concluída')).not.toBeNull();
  });

  it('UC-CVCEL-10 · concluída oferece o protocolo em PDF; aberta não oferece', () => {
    const fechado: Comprovante = { foto_antes: true, foto_depois: true, assinatura: { assinado_por: 'Marta Ribeiro', em: '2026-11-19 10:50:00' }, status: 'concluida', pode_concluir: false };
    const { unmount } = render(<Instalacao instalacao={{ ...INST, status: 'concluida' }} comprovante={fechado} podeRegistrar={false} />);
    const link = screen.getByRole('link', { name: /Baixar protocolo/ });
    expect(link.getAttribute('href')).toBe('/comunicacao-visual/instalacoes/55/protocolo');
    expect(link.getAttribute('target')).toBe('_blank');
    unmount();

    render(<Instalacao instalacao={INST} comprovante={VAZIO} podeRegistrar />);
    expect(screen.queryByRole('link', { name: /Baixar protocolo/ })).toBeNull();
  });
});
