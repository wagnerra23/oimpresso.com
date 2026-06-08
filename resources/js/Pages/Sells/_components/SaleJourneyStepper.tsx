// Breadcrumb "onde a venda está" — jornada da venda de oficina (Wagner 2026-06-05).
//
// READ-ONLY. Fluxo real (ADR 0192): a OS nasce na oficina e VIRA venda ao concluir
// (source='oficina'). Por isso este stepper só aparece em venda de ORIGEM oficina
// e apenas INDICA o estágio — Oficina → Venda → Faturamento → Entrega. Não há ação
// aqui: gerar venda é no board producao-oficina (lado da oficina). Varejo/ROTA
// LIVRE recebe journey.show=false e nada renderiza.

import { Check } from 'lucide-react';

export interface JourneyNode {
  key: string;
  label: string;
  state: 'done' | 'current' | 'todo';
}

export interface SaleJourney {
  show: boolean;
  direction: 'oficina' | string;
  current: string | null;
  os_ref?: string | null;
  nodes: JourneyNode[];
}

interface Props {
  journey: SaleJourney;
}

export default function SaleJourneyStepper({ journey }: Props) {
  // Fail-safe: sem journey.show (varejo/ROTA LIVRE) ou sem nós → não renderiza.
  if (!journey?.show || !Array.isArray(journey.nodes) || journey.nodes.length === 0) {
    return null;
  }

  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <ol className="flex items-center gap-1 flex-wrap" aria-label="Progresso da venda de oficina">
          {journey.nodes.map((node, i) => {
            const isDone = node.state === 'done';
            const isCurrent = node.state === 'current';
            return (
              <li key={node.key} className="flex items-center gap-1">
                <span
                  className={[
                    'flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors',
                    isCurrent
                      ? 'border-primary bg-primary/10 text-primary'
                      : isDone
                        ? 'border-border bg-card text-foreground'
                        : 'border-border bg-muted/40 text-muted-foreground',
                  ].join(' ')}
                  aria-current={isCurrent ? 'step' : undefined}
                >
                  <span
                    className={[
                      'flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-semibold',
                      isDone || isCurrent
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted-foreground/20 text-muted-foreground',
                    ].join(' ')}
                  >
                    {isDone ? <Check size={10} strokeWidth={3} /> : i + 1}
                  </span>
                  {node.label}
                </span>
                {i < journey.nodes.length - 1 && (
                  <span
                    className={['h-px w-5', isDone ? 'bg-primary' : 'bg-border'].join(' ')}
                    aria-hidden="true"
                  />
                )}
              </li>
            );
          })}
        </ol>

        {journey.os_ref && (
          <span className="text-xs text-muted-foreground">
            Origem: <span className="font-medium text-foreground">{journey.os_ref}</span>
          </span>
        )}
      </div>
    </div>
  );
}
