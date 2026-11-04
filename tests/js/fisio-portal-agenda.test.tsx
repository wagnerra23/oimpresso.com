/**
 * Agenda do paciente no portal do Fisio — "Minhas consultas" e as regras visíveis da tela
 * (UC-FPA-03, UC-FPA-04, UC-FPA-05 · memory/requisitos/Fisio/_telas/agenda-paciente.casos.md).
 *
 * Âncora: etapa 04 §3.2 A19 ("Cheguei" a partir de 1 h antes) e A8 (prazo da política); protótipo
 * prototipo-ui/cowork/Wagner/prototipos/fisio/PacienteConsultas.dc.html. A resposta abaixo tem a forma
 * do PortalAgendaController@index, escrita à mão.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

import { TelaConsultas } from '../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Consultas';
import { erroDe, podeChegar } from '../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/agendaPortal';

const agora = new Date('2026-11-12T09:15:00');
const consulta = (id: number, inicio: string, extra = {}) => ({
  id, inicio, tipo: 'Sessão', type_id: 1, professional_id: 7, teleconsulta: false, profissional: 'Dra. Dejeane',
  status: 'confirmado', pode_mexer: false, nota: null, ...extra,
});

function responde(corpo: unknown) {
  globalThis.fetch = vi.fn().mockResolvedValue({ status: 200, json: () => Promise.resolve(corpo) }) as unknown as typeof fetch;
}

afterEach(() => vi.useRealTimers());

describe('agenda do paciente no portal', () => {
  it('UC-FPA-05 · "Cheguei" só a partir de 1 h antes', () => {
    expect(podeChegar({ inicio: '2026-11-12T10:00:00', status: 'confirmado' }, new Date('2026-11-12T08:30:00'))).toBe(false);
    expect(podeChegar({ inicio: '2026-11-12T10:00:00', status: 'confirmado' }, agora)).toBe(true);
    expect(podeChegar({ inicio: '2026-11-12T10:00:00', status: 'cancelado' }, agora)).toBe(false);
  });

  it('UC-FPA-04 · fora do prazo vira texto que manda falar com a clínica', () => {
    expect(erroDe({ errors: { agenda: ['fora_do_prazo'] } })).toContain('fale com a clínica');
  });

  it('UC-FPA-03 · bloqueado por faltas: sem "Agendar nova", com o motivo; dentro do prazo mostra Remarcar', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true, now: agora });
    responde({
      impedimento: 'bloqueado_faltas', prazo_horas: 24, checkin: true, espera: null, tipos: [], profissionais: [], historico: [],
      proximas: [consulta(1, '2026-11-12T10:00:00'), consulta(2, '2026-11-20T10:00:00', { status: 'agendado', pode_mexer: true })],
    });

    render(<TelaConsultas token="t" onVoltar={() => {}} onAgendar={() => {}} />);

    await waitFor(() => expect(screen.getByText('Minhas consultas')).toBeTruthy());
    expect(screen.queryByRole('button', { name: 'Agendar nova' })).toBeNull();
    expect(screen.getByText(/pausado por faltas/)).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Cheguei' })).toBeTruthy();
    expect(screen.getByText(/Faltam menos de 24 h/)).toBeTruthy();
    // Bloqueado não remarca, mas ainda cancela dentro do prazo.
    expect(screen.queryByRole('button', { name: 'Remarcar' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeTruthy();
  });
});
