/**
 * Agenda da clínica do Fisio — ações da sessão e da lista de espera chamam as rotas que já existem
 * (UC-FAG-06, UC-FAG-07, UC-FAG-08 · Modules/Fisio/Resources/js/Pages/Fisio/Agenda/Index.casos.md).
 *
 * Âncora: etapa 04 §3.2 A10 (status), A7 (vaga para a lista de espera), A21 (faltas travam o app) e
 * Modules/Fisio/Routes/web.php (fisio.agenda.atendimentos.status/cancelar, fisio.agenda.espera.*,
 * fisio.pacientes.liberar-agendamento). A transição válida é a do AgendaService::TRANSICOES.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...r }: { children: unknown; href: string }) => <a href={href} {...r}>{children as never}</a>,
  router: { post: (...a: unknown[]) => post(...a), delete: vi.fn() },
  useForm: () => ({ data: { patient_id: '', dias: [], turnos: [], ate: '' }, setData: vi.fn(), post: vi.fn(), errors: {}, processing: false }),
}));

import { AtendimentoAcoes } from '../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/_components/AcoesAgenda';
import SemanaHoras from '../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/_components/SemanaHoras';
import { proximosStatus, type Atendimento } from '../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/_components/semana';

const sessao = (extra: Partial<Atendimento> = {}): Atendimento => ({
  id: 7, patient_id: 3, paciente: 'Marta L.', tipo: 'Sessão', teleconsulta: false,
  inicio: '2026-11-12T10:00:00', fim: '2026-11-12T10:50:00', status: 'agendado', encaixe: false, ...extra,
});

beforeEach(() => post.mockReset());

describe('ações da sessão na agenda', () => {
  it('UC-FAG-06 · status oferecidos seguem as transições do serviço; realizado fica na ficha', () => {
    expect(proximosStatus('agendado')).toEqual(['confirmado', 'chegou', 'em_atendimento', 'faltou']);
    expect(proximosStatus('em_atendimento')).toEqual([]);
    expect(proximosStatus('faltou')).toEqual([]);
  });

  it('UC-FAG-06 · "Confirmar" chama a rota de status do atendimento', () => {
    render(<AtendimentoAcoes a={sessao()} fechar={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Confirmar' }));
    expect(post).toHaveBeenCalledWith('/fisio/agenda/atendimentos/7/status', { status: 'confirmado' }, expect.any(Object));
  });

  it('UC-FAG-05 · "Desmarcar sessão" chama a rota de cancelar com quem desmarcou', () => {
    render(<AtendimentoAcoes a={sessao({ status: 'confirmado' })} fechar={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Desmarcar sessão' }));
    expect(post).toHaveBeenCalledWith('/fisio/agenda/atendimentos/7/cancelar', { por: 'paciente', motivo: null }, expect.any(Object));
  });

  it('UC-FAG-06 · em atendimento não oferece status nem desmarcar', () => {
    render(<AtendimentoAcoes a={sessao({ status: 'em_atendimento' })} fechar={vi.fn()} />);
    expect(screen.queryByRole('button', { name: 'Confirmar' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Desmarcar sessão' })).toBeNull();
  });

  it('UC-FAG-08 · só o paciente travado por faltas mostra "Liberar", que chama a rota do paciente', () => {
    const { unmount } = render(<AtendimentoAcoes a={sessao()} fechar={vi.fn()} />);
    expect(screen.queryByRole('button', { name: 'Liberar agendamento pelo app' })).toBeNull();
    unmount();

    render(<AtendimentoAcoes a={sessao({ app_bloqueado: true })} fechar={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Liberar agendamento pelo app' }));
    expect(post).toHaveBeenCalledWith('/fisio/pacientes/3/liberar-agendamento', {}, expect.any(Object));
  });

  it('UC-FAG-04 · com quem edita o bloco abre as ações; sem permissão ele leva à ficha', () => {
    const abrir = vi.fn();
    const props = { segunda: '2026-11-09', hoje: '2026-11-12', atendimentos: [sessao()], bloqueios: [] };
    const { container, unmount } = render(<SemanaHoras {...props} abrir={abrir} />);
    const bloco = container.querySelector('[data-atendimento="7"]') as HTMLElement;
    expect(bloco.tagName).toBe('BUTTON');
    fireEvent.click(bloco);
    expect(abrir).toHaveBeenCalledWith(expect.objectContaining({ id: 7 }));
    unmount();

    const leitura = render(<SemanaHoras {...props} />);
    expect(leitura.container.querySelector('[data-atendimento="7"]')?.getAttribute('href')).toBe('/fisio/pacientes/3');
  });
});
