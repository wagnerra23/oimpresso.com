// Grade da semana do profissional: horas × dias (fonte: Agenda.dc.html do canvas Prescreve Pro,
// prototipo-ui/cowork/Wagner/prototipos/fisio/). Cada atendimento vira bloco posicionado pela hora;
// a cor vem do status (tokens semânticos do DS, nenhuma cor crua). Só exibe: a regra mora no servidor.
import { Link } from '@inertiajs/react';
import { HORAS, HORA_INICIAL, PX_HORA, TOM, dataLocal, diasDaSemana, isoDia, tomDo, topo, type Atendimento, type Bloqueio } from './semana';

export type { Atendimento, Bloqueio } from './semana';

export default function SemanaHoras({ segunda, hoje, atendimentos, bloqueios }: {
  segunda: string;
  hoje: string;
  atendimentos: Atendimento[];
  bloqueios: Bloqueio[];
}) {
  const dias = diasDaSemana(segunda);
  const altura = HORAS * PX_HORA;

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card" role="region" aria-label="Semana">
      <div className="grid min-w-[860px]" style={{ gridTemplateColumns: '56px repeat(6, minmax(130px, 1fr))' }}>
        <div className="border-b border-border" />
        {dias.map((d) => (
          <div key={d.iso} aria-current={d.iso === hoje ? 'date' : undefined}
            className="flex flex-col border-b border-l border-border/60 p-2.5 aria-[current=date]:text-primary">
            <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{d.rotulo}</span>
            <span className="font-mono text-lg font-medium">{d.dia}</span>
          </div>
        ))}
        <div className="relative" style={{ height: altura }}>
          {Array.from({ length: HORAS }, (_, i) => (
            <span key={i} className="absolute right-2 font-mono text-[11px] text-muted-foreground" style={{ top: i * PX_HORA - 6 }}>
              {String(HORA_INICIAL + i).padStart(2, '0')}h
            </span>
          ))}
        </div>
        {dias.map((d) => (
          <div key={d.iso} data-dia={d.iso} className="relative border-l border-border/60"
            style={{ height: altura, backgroundImage: `repeating-linear-gradient(to bottom, transparent 0, transparent ${PX_HORA - 1}px, var(--border) ${PX_HORA - 1}px, var(--border) ${PX_HORA}px)` }}>
            {bloqueios.filter((b) => isoDia(dataLocal(b.inicio)) === d.iso).map((b) => (
              <div key={`b${b.id}`} data-bloqueio={b.id} className="absolute inset-x-1 rounded-md border border-dashed border-border bg-muted/60 px-1.5 text-xs text-muted-foreground"
                style={{ top: topo(b.inicio) + 2, height: Math.max(topo(b.fim) - topo(b.inicio) - 4, 16) }}>
                {b.motivo ?? 'Bloqueado'}
              </div>
            ))}
            {atendimentos.filter((a) => isoDia(dataLocal(a.inicio)) === d.iso).map((a) => (
              <Link key={a.id} href={`/fisio/pacientes/${a.patient_id}`} data-atendimento={a.id} data-tom={tomDo(a)}
                className={`absolute inset-x-1 flex flex-col overflow-hidden rounded-md border-l-[3px] px-1.5 py-1 text-xs leading-tight no-underline ${TOM[tomDo(a)]}`}
                style={{ top: topo(a.inicio) + 2, height: Math.max(topo(a.fim) - topo(a.inicio) - 4, 18) }}>
                <b className="font-semibold">{a.paciente}{a.encaixe ? ' · encaixe' : ''}</b>
                <span>{a.teleconsulta ? 'teleconsulta' : (a.tipo ?? '')}</span>
              </Link>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}
