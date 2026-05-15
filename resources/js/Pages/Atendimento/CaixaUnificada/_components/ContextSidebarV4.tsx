// ContextSidebarV4.tsx — sidebar direita 8 sections (Cowork redesign).
//
// Replica visual `.om-ctx` / `.om-ctx-body` / `.om-kv` do Cowork
// (inbox-page.css L682-714). 8 sections de cima pra baixo:
//
//   1. Fila (label + hue + SLA badge)
//   2. Atribuído (placeholder — TODO US-WA-XXX: implementar assignee picker)
//   3. Canal · Conta (short + label + handle)
//   4. Tags (chips coloridos)
//   5. OS vinculada (placeholder — TODO US-WA-XXX: linkar Repair)
//   6. Saldo cliente (placeholder — TODO US-WA-XXX: integrar Financeiro)
//   7. Histórico (placeholder — TODO US-WA-XXX: agregar vendas/LTV)
//   8. Último contato (relativeTimeBR)
//   9. Ações (3 botões: Emitir cobrança · Enviar arte · Ligar)
//
// Placeholders viram TODOs honestos (anti-padrão M-AP-1 do
// LICOES_F3_FINANCEIRO_REJEITADO.md §1) — não inventar Service que não existe.

import type {
  CaixaUnifThread,
  ChannelCatalogItem,
  QueueConfig,
} from './helpers';
import { relativeTimeBR } from './helpers';

interface Props {
  thread: CaixaUnifThread;
  channels: ChannelCatalogItem[];
  queues: Record<string, QueueConfig>;
}

export default function ContextSidebarV4({ thread, channels, queues }: Props) {
  const channel = channels.find(c => c.id === thread.channel_type);
  const queueCfg = queues[thread.queue.slug] ?? null;
  const isPreview = thread.preview_only;

  return (
    <aside
      className="flex flex-col bg-card border-l min-h-0 min-w-0"
      aria-label="Contexto da conversa"
    >
      <div className="flex items-baseline gap-2 border-b px-3.5 pt-3 pb-2">
        <b className="text-[13px] font-semibold text-foreground">Contexto</b>
      </div>

      <div className="flex-1 overflow-auto px-4 py-3 flex flex-col gap-2.5">
        {/* 1. Fila */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-wider text-muted-foreground font-semibold">
            Fila
          </small>
          <b className="inline-flex items-center gap-1.5 text-[12.5px] font-medium" data-testid="caixa-unif-ctx-queue">
            <span
              className="inline-block w-2 h-2 rounded-full flex-shrink-0"
              style={{ background: `oklch(0.55 0.13 ${thread.queue.hue})` }}
              aria-hidden
            />
            {thread.queue.label}
          </b>
          {queueCfg?.sla && (
            <small className="text-[11px] text-muted-foreground mt-0.5">
              SLA {queueCfg.sla}
            </small>
          )}
        </div>

        {/* 2. Atribuído — placeholder */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-wider text-muted-foreground font-semibold">
            Atribuído
          </small>
          <b className="text-[12.5px] font-medium text-muted-foreground italic" data-testid="caixa-unif-ctx-assignee">
            — sem atribuição
          </b>
          {/* TODO US-WA-XXX: assignee picker (select operators) */}
        </div>

        {/* 3. Canal · Conta */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-wider text-muted-foreground font-semibold">
            Canal · Conta
          </small>
          <b className="inline-flex items-center gap-1.5 text-[12.5px] font-medium">
            {channel && (
              <span
                className="inline-grid place-items-center rounded-full text-white font-bold flex-shrink-0"
                style={{
                  width: 12, height: 12, fontSize: 8,
                  background: `oklch(0.62 0.14 ${channel.hue})`,
                }}
                aria-hidden
              >
                {channel.glyph}
              </span>
            )}
            <span>
              {channel?.short ?? thread.channel_type}
              {thread.channel_label && ` · ${thread.channel_label}`}
            </span>
          </b>
          {thread.channel_handle && (
            <small className="text-[11px] font-mono text-muted-foreground mt-0.5">
              {thread.channel_handle}
            </small>
          )}
        </div>

        {/* 4. Tags */}
        {thread.tags.length > 0 && (
          <div className="pb-2.5 border-b border-border/50 flex flex-col gap-1">
            <small className="text-[9.5px] uppercase tracking-wider text-muted-foreground font-semibold">
              Tags
            </small>
            <div className="flex flex-wrap gap-1">
              {thread.tags.map(t => (
                <span
                  key={t.id}
                  className="inline-block px-2 py-px text-[10px] font-mono rounded-full bg-amber-50 border border-amber-200 text-amber-900"
                  data-testid={`caixa-unif-ctx-tag-${t.slug}`}
                >
                  {t.label}
                </span>
              ))}
            </div>
          </div>
        )}

        {/* 5. OS vinculada — placeholder */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-wider text-muted-foreground font-semibold">
            OS vinculada
          </small>
          <b className="text-[12.5px] font-medium text-muted-foreground italic">
            — nenhuma
          </b>
          {/* TODO US-WA-XXX: linkar Modules/Repair JobSheet via repair_jobsheet_id na conversa */}
        </div>

        {/* 6. Saldo cliente — placeholder */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-wider text-muted-foreground font-semibold">
            Saldo cliente
          </small>
          <b className="text-[12.5px] font-medium text-muted-foreground italic">
            —
          </b>
          {/* TODO US-WA-XXX: integrar Modules/Financeiro Titulo (sum tipo=receber) */}
        </div>

        {/* 7. Histórico — placeholder */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-wider text-muted-foreground font-semibold">
            Histórico
          </small>
          <b className="text-[12.5px] font-medium text-muted-foreground italic">
            —
          </b>
          {/* TODO US-WA-XXX: agregar Transaction.count + sum(final_total) por contact_id */}
        </div>

        {/* 8. Último contato */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-wider text-muted-foreground font-semibold">
            Último contato
          </small>
          <b className="text-[12.5px] font-medium" data-testid="caixa-unif-ctx-last-touch">
            {relativeTimeBR(thread.last_message_at) || '—'}
          </b>
        </div>

        {/* 9. Ações (3 atalhos) */}
        <div className="flex flex-col gap-1.5 mt-1">
          <button
            type="button"
            disabled={isPreview}
            data-testid="caixa-unif-ctx-action-billing"
            className="text-left text-[12px] px-2.5 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
            title="Emitir cobrança (em breve)"
          >
            Emitir cobrança
          </button>
          {/* TODO US-WA-XXX: integrar Modules/Financeiro emitir Titulo + boleto Asaas */}

          <button
            type="button"
            disabled={isPreview}
            data-testid="caixa-unif-ctx-action-arte"
            className="text-left text-[12px] px-2.5 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
            title="Enviar arte (em breve)"
          >
            Enviar arte
          </button>
          {/* TODO US-WA-XXX: composer media upload reusando InboxController::sendMedia */}

          <button
            type="button"
            disabled={isPreview}
            data-testid="caixa-unif-ctx-action-ligar"
            className="text-left text-[12px] px-2.5 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
            title={`Ligar para ${thread.customer_external_id}`}
          >
            Ligar
          </button>
          {/* TODO US-WA-XXX: tel: link OU integração WhatsApp voice (beta Meta) */}
        </div>
      </div>
    </aside>
  );
}
