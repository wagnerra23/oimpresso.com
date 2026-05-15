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

import { router } from '@inertiajs/react';
import { Ban, Check, Plus, UserPlus } from 'lucide-react';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import type {
  CaixaUnifThread,
  ChannelCatalogItem,
  ConvTag,
  QueueConfig,
} from './helpers';
import { relativeTimeBR } from './helpers';

interface Props {
  thread: CaixaUnifThread;
  channels: ChannelCatalogItem[];
  queues: Record<string, QueueConfig>;
  /**
   * Wave 3 F1 (US-WA-095 paridade Inbox legacy): catálogo de tags do business
   * pro editor inline. Default [] quando deferred ainda não resolveu.
   */
  availableTags?: ConvTag[];
}

export default function ContextSidebarV4({ thread, channels, queues, availableTags = [] }: Props) {
  const channel = channels.find(c => c.id === thread.channel_type);
  const queueCfg = queues[thread.queue.slug] ?? null;
  const isPreview = thread.preview_only;
  const activeTagIds = new Set(thread.tags.map(t => t.id));

  // Wave 3 F1 — toggle tag (PATCH /atendimento/inbox/{id}/tags)
  function toggleTag(tagId: number) {
    const next = activeTagIds.has(tagId)
      ? thread.tags.filter(t => t.id !== tagId).map(t => t.id)
      : [...thread.tags.map(t => t.id), tagId];
    router.patch(
      route('atendimento.inbox.update_tags', thread.id),
      { tag_ids: next },
      { preserveScroll: true, preserveState: true, only: ['thread', 'conversations', 'stats'] },
    );
  }

  // Wave 3 F1 — bloquear contato (PATCH /atendimento/inbox/{id}/block)
  function toggleBlock() {
    if (!confirm(thread.is_blocked
      ? `Desbloquear ${thread.contact_name || thread.customer_external_id}?`
      : `Bloquear ${thread.contact_name || thread.customer_external_id}? Mensagens deste contato serão descartadas.`)) {
      return;
    }
    router.patch(
      route('atendimento.inbox.block', thread.id),
      { is_blocked: !thread.is_blocked },
      { preserveScroll: true, preserveState: true, only: ['thread', 'conversations'] },
    );
  }

  // Wave 3 F1 — criar Contact CRM a partir do phone (POST .../contact/create-from-phone)
  function createContactFromPhone() {
    const name = prompt(
      `Criar Contact CRM a partir do telefone?\n\nNome do contato (pode editar depois):`,
      thread.contact_name || '',
    );
    if (!name || !name.trim()) return;
    router.post(
      route('atendimento.inbox.contact.create_from_phone', thread.id),
      { name: name.trim() },
      { preserveScroll: true, preserveState: true, only: ['thread', 'conversations'] },
    );
  }

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
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
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
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            Atribuído
          </small>
          <b className="text-[12.5px] font-medium text-muted-foreground italic" data-testid="caixa-unif-ctx-assignee">
            — sem atribuição
          </b>
          {/* TODO US-WA-XXX: assignee picker (select operators) */}
        </div>

        {/* 3. Canal · Conta */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
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

        {/* 4. Tags — Wave 3 F1: editor inline via Popover (PATCH update_tags) */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-1">
          <div className="flex items-center justify-between">
            <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
              Tags
            </small>
            <Popover>
              <PopoverTrigger asChild>
                <button
                  type="button"
                  className="inline-flex items-center gap-0.5 text-[10px] text-muted-foreground hover:text-foreground transition-colors"
                  title="Editar tags"
                  data-testid="caixa-unif-ctx-tags-edit"
                >
                  <Plus size={11} aria-hidden /> editar
                </button>
              </PopoverTrigger>
              <PopoverContent align="end" className="w-56 p-1.5">
                <div className="text-[10px] uppercase tracking-[0.06em] text-muted-foreground font-semibold px-2 pt-1 pb-1.5">
                  Tags do business
                </div>
                {availableTags.length === 0 ? (
                  <div className="px-2 py-2 text-[11px] text-muted-foreground italic">
                    Nenhuma tag cadastrada
                  </div>
                ) : (
                  <ul className="max-h-64 overflow-auto">
                    {availableTags.map(t => {
                      const active = activeTagIds.has(t.id);
                      return (
                        <li key={t.id}>
                          <button
                            type="button"
                            onClick={() => toggleTag(t.id)}
                            data-testid={`caixa-unif-ctx-tags-toggle-${t.slug}`}
                            className="w-full flex items-center justify-between gap-2 px-2 py-1.5 text-[11.5px] hover:bg-muted rounded text-left"
                          >
                            <span className="inline-flex items-center gap-1.5">
                              <span
                                className="inline-block w-2 h-2 rounded-full"
                                style={{ background: `oklch(0.62 0.13 ${(t.color === 'red' ? 0 : t.color === 'emerald' ? 145 : t.color === 'blue' ? 220 : t.color === 'purple' ? 280 : t.color === 'amber' ? 80 : t.color === 'cyan' ? 200 : 60)})` }}
                                aria-hidden
                              />
                              {t.label}
                            </span>
                            {active && <Check size={13} className="text-primary flex-shrink-0" aria-label="Aplicada" />}
                          </button>
                        </li>
                      );
                    })}
                  </ul>
                )}
              </PopoverContent>
            </Popover>
          </div>
          {thread.tags.length > 0 ? (
            <div className="flex flex-wrap gap-1">
              {thread.tags.map(t => (
                // Cowork .om-tag — padding 1px 8px, font 10px mono, OKLCH §710
                <span
                  key={t.id}
                  className="inline-block px-2 py-px text-[10px] font-mono rounded-full text-foreground"
                  style={{
                    background: 'oklch(0.94 0.03 80)',
                    border: '1px solid oklch(0.86 0.06 80)',
                  }}
                  data-testid={`caixa-unif-ctx-tag-${t.slug}`}
                >
                  {t.label}
                </span>
              ))}
            </div>
          ) : (
            <small className="text-[11px] text-muted-foreground italic">— sem tags</small>
          )}
        </div>

        {/* 5. OS vinculada — placeholder */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            OS vinculada
          </small>
          <b className="text-[12.5px] font-medium text-muted-foreground italic">
            — nenhuma
          </b>
          {/* TODO US-WA-XXX: linkar Modules/Repair JobSheet via repair_jobsheet_id na conversa */}
        </div>

        {/* 6. Saldo cliente — placeholder */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            Saldo cliente
          </small>
          <b className="text-[12.5px] font-medium text-muted-foreground italic">
            —
          </b>
          {/* TODO US-WA-XXX: integrar Modules/Financeiro Titulo (sum tipo=receber) */}
        </div>

        {/* 7. Histórico — placeholder */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            Histórico
          </small>
          <b className="text-[12.5px] font-medium text-muted-foreground italic">
            —
          </b>
          {/* TODO US-WA-XXX: agregar Transaction.count + sum(final_total) por contact_id */}
        </div>

        {/* 8. Último contato */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            Último contato
          </small>
          <b className="text-[12.5px] font-medium" data-testid="caixa-unif-ctx-last-touch">
            {relativeTimeBR(thread.last_message_at) || '—'}
          </b>
        </div>

        {/* Wave 3 F1 — Contato CRM (criar do phone) + Bloquear */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-1.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            Contato CRM
          </small>
          <button
            type="button"
            onClick={createContactFromPhone}
            disabled={isPreview}
            className="inline-flex items-center gap-1.5 text-left text-[11.5px] px-2 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
            data-testid="caixa-unif-ctx-create-contact"
            title="Cria registro no CRM a partir do número de telefone"
          >
            <UserPlus size={12} aria-hidden />
            Criar contato do telefone
          </button>
        </div>

        {/* 9. Ações (3 atalhos + Bloquear) */}
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

          {/* Wave 3 F1 — Bloquear contato (separado por cor de risco) */}
          <button
            type="button"
            onClick={toggleBlock}
            data-testid="caixa-unif-ctx-action-block"
            className={`inline-flex items-center gap-1.5 text-left text-[12px] px-2.5 py-1.5 border rounded transition-colors mt-1 ${
              thread.is_blocked
                ? 'bg-destructive/10 border-destructive/30 text-destructive hover:bg-destructive/15'
                : 'bg-card border-border text-muted-foreground hover:text-destructive hover:border-destructive/30 hover:bg-destructive/5'
            }`}
            title={thread.is_blocked ? 'Desbloquear contato' : 'Bloquear contato — mensagens serão descartadas'}
          >
            <Ban size={12} aria-hidden />
            {thread.is_blocked ? 'Desbloquear contato' : 'Bloquear contato'}
          </button>
        </div>
      </div>
    </aside>
  );
}
