// ApprovalGateCard — gate de aprovação do cliente (US-OFICINA-041).
// Delta do protótipo Cowork "Nova OS" (card "Aprovação do cliente"):
//   aguardando → enviado/aguardando resposta → aprovado.
// A execução não inicia até o cliente aprovar. Botão dispara status → orcamento,
// que faz o ServiceOrderObserver enviar o link público + PIN via WhatsApp.

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Clock, Send, Loader2, ShieldCheck } from 'lucide-react';

interface Props {
  serviceOrderId: number;
  status: string;
}

export default function ApprovalGateCard({ serviceOrderId, status }: Props) {
  const [sending, setSending] = useState(false);

  const aprovada = ['aprovada', 'concluida', 'entregue'].includes(status);
  const aguardando = status === 'orcamento';
  const cancelada = status === 'cancelada';

  if (cancelada) return null;

  function enviar() {
    setSending(true);
    router.post(
      `/oficina-auto/ordens-servico/${serviceOrderId}/enviar-aprovacao`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setSending(false),
      },
    );
  }

  // Aprovado — liberado pra execução
  if (aprovada) {
    return (
      <div className="rounded-md border border-success/40 bg-success/5 p-4 mt-4">
        <div className="flex items-center gap-2">
          <ShieldCheck className="size-5 text-success-foreground" />
          <h2 className="text-sm font-semibold text-foreground">Aprovado pelo cliente</h2>
        </div>
        <p className="text-xs text-muted-foreground mt-1">
          Liberado para execução — o mecânico já pode iniciar o serviço.
        </p>
      </div>
    );
  }

  // Aguardando aprovação (orçamento enviado)
  if (aguardando) {
    return (
      <div className="rounded-md border border-warning/40 bg-warning/5 p-4 mt-4">
        <div className="flex items-center gap-2">
          <Clock className="size-5 text-warning-foreground" />
          <h2 className="text-sm font-semibold text-foreground">Aguardando aprovação do cliente</h2>
        </div>
        <p className="text-xs text-muted-foreground mt-1">
          Orçamento enviado por WhatsApp (link + PIN). A execução não inicia até o cliente aprovar.
        </p>
      </div>
    );
  }

  // Pré-aprovação — botão pra enviar
  return (
    <div className="rounded-md border bg-card p-4 mt-4">
      <div className="flex items-center gap-2">
        <Clock className="size-5 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">Aprovação do cliente</h2>
      </div>
      <p className="text-xs text-muted-foreground mt-1 mb-3">
        A execução não inicia sem o cliente autorizar. Envie o orçamento com a vistoria por WhatsApp.
      </p>
      <button
        type="button"
        onClick={enviar}
        disabled={sending}
        className="inline-flex items-center gap-2 rounded-md bg-primary text-primary-foreground px-3 py-2 text-sm font-medium hover:opacity-90 disabled:opacity-50"
      >
        {sending ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
        Enviar orçamento por WhatsApp
      </button>
    </div>
  );
}
