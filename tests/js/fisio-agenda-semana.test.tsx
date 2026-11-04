/**
 * Agenda da clínica do Fisio — a grade da semana põe cada atendimento no dia e na hora certos, com a
 * cor do status do protótipo (UC-FAG-02 · Modules/Fisio/Resources/js/Pages/Fisio/Agenda/Index.casos.md).
 *
 * Âncora: prototipo-ui/cowork/Wagner/prototipos/fisio/Agenda.dc.html — 52 px por hora a partir das 8h,
 * legenda confirmado (pos) · aguardando (warn) · teleconsulta (info) · falta (neg).
 */
import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...r }: { children: unknown; href: string }) => <a href={href} {...r}>{children as never}</a>,
}));

import SemanaHoras from '../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/_components/SemanaHoras';
import { diasDaSemana, tomDo, topo } from '../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/_components/semana';

const base = { patient_id: 1, tipo: 'Sessão', teleconsulta: false, encaixe: false };

describe('grade da semana (UC-FAG-02)', () => {
  it('semana começa na segunda e vai até o sábado', () => {
    const d = diasDaSemana('2026-10-19');
    expect(d.map((x) => x.rotulo)).toEqual(['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']);
    expect(d[5].iso).toBe('2026-10-24');
  });

  it('8h30 fica a 26 px do topo, como no protótipo (52 px por hora)', () => {
    expect(topo('2026-10-19T08:30:00')).toBe(26);
    expect(topo('2026-10-19T07:00:00')).toBe(0);
  });

  it('cor pelo status: aguardando, confirmado, teleconsulta, falta', () => {
    expect(tomDo({ status: 'agendado', teleconsulta: false })).toBe('aguardando');
    expect(tomDo({ status: 'confirmado', teleconsulta: false })).toBe('ok');
    expect(tomDo({ status: 'confirmado', teleconsulta: true })).toBe('tele');
    expect(tomDo({ status: 'faltou', teleconsulta: true })).toBe('falta');
  });

  it('desenha o atendimento e o bloqueio no dia certo', () => {
    const { container } = render(
      <SemanaHoras
        segunda="2026-10-19"
        hoje="2026-10-21"
        atendimentos={[{ ...base, id: 7, paciente: 'Marta L.', inicio: '2026-10-21T10:00:00', fim: '2026-10-21T10:50:00', status: 'faltou' }]}
        bloqueios={[{ id: 3, inicio: '2026-10-20T12:00:00', fim: '2026-10-20T13:00:00', motivo: 'almoço' }]}
      />,
    );

    const bloco = container.querySelector('[data-dia="2026-10-21"] [data-atendimento="7"]');
    expect(bloco?.textContent).toContain('Marta L.');
    expect(bloco?.getAttribute('data-tom')).toBe('falta');
    expect(container.querySelector('[data-dia="2026-10-20"] [data-bloqueio="3"]')?.textContent).toBe('almoço');
    expect(container.querySelector('[aria-current="date"]')?.textContent).toContain('21');
  });
});
