// Breadcrumb "onde a venda está" — jornada da venda de oficina (Wagner 2026-06-05).
//
// Stepper horizontal direcional. Os nós + o estágio atual vêm prontos do backend
// (prop `journey`, montada por App\Services\SaleJourneyService — função pura
// testada no CI). Duas direções:
//   balcão : Orçamento → Venda → Oficina → Faturamento → Entrega
//   oficina: Oficina → Venda → Faturamento → Entrega
//
// CTA "Enviar para a oficina" aparece só quando há um nó Oficina pendente na
// direção balcão (= venda pronta, ainda sem OS). Reusa POST /sells/{id}/create-os
// (mesmo endpoint do CriarOsButton, modo 'single' = 1 OS pra venda toda, padrão
// Martinho/caçambas).
//
// Gate de visibilidade é do backend (journey.show=false pra varejo/ROTA LIVRE) —
// este componente só renderiza quando journey.show é true.

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Check, Loader2, Wrench } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';

export interface JourneyNode {
  key: string;
  label: string;
  state: 'done' | 'current' | 'todo';
}

export interface SaleJourney {
  show: boolean;
  direction: 'balcao' | 'oficina' | string;
  current: string | null;
  nodes: JourneyNode[];
}

interface Props {
  journey: SaleJourney;
  saleId: number;
}

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function SaleJourneyStepper({ journey, saleId }: Props) {
  const [sending, setSending] = useState(false);

  if (!journey?.show || !Array.isArray(journey.nodes) || journey.nodes.length === 0) {
    return null;
  }

  // CTA só na direção balcão, quando o nó Oficina existe e ainda está pendente
  // (venda pronta, sem OS criada). Não toca valor/estoque — só cria OS via endpoint.
  const oficinaNode = journey.nodes.find((n) => n.key === 'oficina');
  const canSendToWorkshop =
    journey.direction === 'balcao' && oficinaNode?.state === 'todo' && journey.current === 'venda';

  const handleSend = async () => {
    if (sending) return;
    setSending(true);
    try {
      const res = await fetch(`/sells/${saleId}/create-os`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ mode: 'single' }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) {
        toast.error(json.msg ?? json.message ?? 'Falha ao enviar para a oficina.');
        return;
      }
      toast.success(json.message ?? 'Venda enviada para a oficina.');
      router.reload({ only: ['journey'] });
    } catch (err) {
      toast.error(`Erro: ${(err as Error)?.message ?? err}`);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <ol className="flex items-center gap-1 flex-wrap" aria-label="Progresso da venda">
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

        {canSendToWorkshop && (
          <Button type="button" size="sm" onClick={handleSend} disabled={sending}>
            {sending ? (
              <Loader2 size={14} className="mr-1.5 animate-spin" />
            ) : (
              <Wrench size={14} className="mr-1.5" />
            )}
            Enviar para a oficina
          </Button>
        )}
      </div>
    </div>
  );
}
