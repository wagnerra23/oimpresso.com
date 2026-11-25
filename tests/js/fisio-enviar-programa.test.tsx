/**
 * Botão "Enviar ao paciente" na aba Programa da ficha (UC-FPS-42 · Show.casos.md).
 *
 * Âncora: rota `fisio.programas.enviar` (EnvioWhatsappController, US-FISIO-009) e seus contratos
 * já provados em Pest (UC-FPS-11 202/409, UC-FPS-36 wa.me manual). Aqui só a tela: chama a rota
 * certa e mostra o que ela respondeu, sem tratar 409 como sucesso nem abrir wa.me sozinho.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import EnviarPrograma from '../../Modules/Fisio/Resources/js/Pages/Fisio/_components/EnviarPrograma';

const responder = (status: number, corpo: unknown) =>
  vi.fn().mockResolvedValue({ status, json: () => Promise.resolve(corpo) } as unknown as Response);

let fetchMock: ReturnType<typeof responder>;
beforeEach(() => { fetchMock = responder(202, { modo: 'fila' }); vi.stubGlobal('fetch', fetchMock); });
afterEach(() => vi.unstubAllGlobals());

const clicar = () => fireEvent.click(screen.getByRole('button', { name: /Enviar ao paciente/ }));

describe('Enviar ao paciente (aba Programa)', () => {
  it('UC-FPS-42 · chama POST /fisio/programas/{id}/enviar e avisa que foi para a fila (202)', async () => {
    render(<EnviarPrograma programaId={7} />);
    clicar();
    expect((await screen.findByRole('status')).textContent).toContain('Enviado para a fila');
    expect(fetchMock).toHaveBeenCalledWith('/fisio/programas/7/enviar', expect.objectContaining({ method: 'POST' }));
  });

  it('UC-FPS-42 · segundo envio do dia (409) diz que já foi enviado hoje, não que enviou', async () => {
    vi.stubGlobal('fetch', responder(409, { erro: 'ja_enviado_hoje' }));
    render(<EnviarPrograma programaId={7} />);
    clicar();
    const aviso = await screen.findByRole('status');
    expect(aviso.textContent).toContain('já foi enviado hoje');
    expect(aviso.textContent).not.toContain('fila');
  });

  it('UC-FPS-42 · sem número ativo (200 manual) mostra os dois links wa.me, link antes do código', async () => {
    vi.stubGlobal('fetch', responder(200, { modo: 'manual', wa_me: { link: 'https://wa.me/5548?text=link', codigo: 'https://wa.me/5548?text=pin' } }));
    render(<EnviarPrograma programaId={7} />);
    clicar();
    const link = await screen.findByRole('link', { name: '1. Enviar o link' });
    const codigo = screen.getByRole('link', { name: '2. Enviar o código' });
    expect(link.getAttribute('href')).toBe('https://wa.me/5548?text=link');
    expect(codigo.getAttribute('href')).toBe('https://wa.me/5548?text=pin');
    expect(link.compareDocumentPosition(codigo) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('UC-FPS-42 · sem consentimento (422) mostra o motivo e nenhum link', async () => {
    vi.stubGlobal('fetch', responder(422, { erro: 'sem_consentimento_whatsapp' }));
    render(<EnviarPrograma programaId={7} />);
    clicar();
    expect((await screen.findByRole('status')).textContent).toContain('não autorizou mensagens pelo WhatsApp');
    expect(screen.queryByRole('link')).toBeNull();
  });
});
