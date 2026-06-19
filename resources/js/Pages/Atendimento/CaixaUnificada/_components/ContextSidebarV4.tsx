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

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Ban, Check, Link as LinkIcon, Plus, Sparkles, UserMinus, UserPlus } from 'lucide-react';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import { Inline, Stack } from '@/Components/layout';
import ContactPickerModal from '@/Pages/Whatsapp/_components/ContactPickerModal';
import CustomerMemoryBlock from '@/Pages/Whatsapp/_components/CustomerMemoryBlock';
import type {
  AssigneeItem,
  CaixaUnifThread,
  ChannelCatalogItem,
  ConvTag,
  QueueConfig,
  CustomerContext,
} from './helpers';
import { avatarHue, formatBRL, initials, relativeTimeBR } from './helpers';
import InboxAiDialog, { type InboxAiMode } from './InboxAiDialog';

interface Props {
  thread: CaixaUnifThread;
  channels: ChannelCatalogItem[];
  queues: Record<string, QueueConfig>;
  /** Onda 3 — contexto comercial do cliente (Saldo + Histórico). null = sem thread/contato. */
  customerContext?: CustomerContext | null;
  /**
   * Wave 3 F1 (US-WA-095 paridade Inbox legacy): catálogo de tags do business
   * pro editor inline. Default [] quando deferred ainda não resolveu.
   */
  availableTags?: ConvTag[];
  /**
   * US-WA-302 — operadores atribuíveis (assignee picker section 2).
   * Default [] enquanto deferred não resolveu.
   */
  availableAssignees?: AssigneeItem[];
  /** Caixa Unificada — Contexto recolhível (canon Cowork `.om-ctx`). */
  open?: boolean;
  onToggle?: () => void;
}

export default function ContextSidebarV4({ thread, customerContext, channels, queues, availableTags = [], availableAssignees = [], open = true, onToggle }: Props) {
  const channel = channels.find(c => c.id === thread.channel_type);
  const queueCfg = queues[thread.queue.slug] ?? null;
  const isPreview = thread.preview_only;
  const activeTagIds = new Set(thread.tags.map(t => t.id));
  const [pickerOpen, setPickerOpen] = useState(false);

  // US-WA-305 — mover conversa entre filas (PATCH /atendimento/inbox/{id}/queue)
  const [queuePopOpen, setQueuePopOpen] = useState(false);
  function moveToQueue(slug: string | null) {
    router.patch(
      route('atendimento.inbox.move_queue', thread.id),
      { queue_slug: slug },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'conversations', 'stats'],
        onSuccess: () => setQueuePopOpen(false),
      },
    );
  }

  // US-WA-302 — atribuir/remover operador (PATCH /atendimento/inbox/{id}/assign)
  const [assignPopOpen, setAssignPopOpen] = useState(false);
  // T1 (handoff 2026-06-19) — IA movida do header da thread pra cá (seção Inteligência)
  const [aiMode, setAiMode] = useState<InboxAiMode | null>(null);
  function assignTo(assigneeId: number | null) {
    router.patch(
      route('atendimento.inbox.assign', thread.id),
      { assigned_user_id: assigneeId },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'conversations', 'stats'],
        onSuccess: () => setAssignPopOpen(false),
      },
    );
  }

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

  // Wave 3-B F1 — vincular Contact CRM existente (PATCH .../contact)
  function linkContact(contactId: number) {
    router.patch(
      route('atendimento.inbox.link_contact', thread.id),
      { contact_id: contactId },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'conversations'],
        onSuccess: () => setPickerOpen(false),
      },
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
      {/* Recolhido (canon Cowork `.om-ctx-expand`) — trilho 44px só no desktop;
          no mobile a tab Contexto sempre mostra o painel cheio. */}
      {!open && (
        <button
          type="button"
          onClick={onToggle}
          className="hidden lg:block w-full h-full text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
          title="Expandir contexto"
          aria-label="Expandir contexto"
          data-testid="caixa-unif-ctx-expand"
        >
          <Stack gap={2} align="center" justify="center" className="h-full py-3.5">
            <span className="text-[15px] font-semibold leading-none" aria-hidden>‹</span>
            <span
              className="text-[10px] font-semibold uppercase tracking-[0.08em]"
              style={{ writingMode: 'vertical-rl' }}
            >
              Contexto
            </span>
          </Stack>
        </button>
      )}

      <Stack gap={0} className={`${open ? '' : 'lg:hidden'} flex-1 min-h-0 min-w-0`}>
      <div className="flex items-center gap-2 border-b px-3.5 pt-3 pb-2">
        <b className="text-[13px] font-semibold text-foreground">Contexto</b>
        <button
          type="button"
          onClick={onToggle}
          className="ml-auto hidden lg:block w-6 h-6 rounded-md border text-center text-[14px] leading-[20px] text-muted-foreground hover:text-foreground hover:border-muted-foreground transition-colors"
          title="Recolher contexto"
          aria-label="Recolher contexto"
          data-testid="caixa-unif-ctx-toggle"
        >
          <span aria-hidden>›</span>
        </button>
      </div>

      <div className="flex-1 overflow-auto px-4 py-3 flex flex-col gap-2.5">
        {/* US-WA-VOZ-001/002/003 — Customer Memory block (perfil persistente cliente).
            Renderiza no topo do contexto. Lazy fetch client-side via endpoint
            /atendimento/customer/{ext}/profile — não bloqueia abertura nem polling 5s.
            Mostra identidade Contact CRM, stats, reclamações 30d (heurística),
            external_sources Firebird OfficeImpresso, flags VIP/frágil, LGPD. */}
        {thread.customer_external_id && (
          <CustomerMemoryBlock customerExternalId={thread.customer_external_id} />
        )}

        {/* T1 — Inteligência (Resumir/Perguntar movidos do header da thread · protótipo .om-ctx-ai) */}
        {!thread.preview_only && (
          <Stack gap={1} className="pb-2.5 border-b border-border/50">
            <span className="text-[10px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">Inteligência</span>
            <button
              type="button"
              onClick={() => setAiMode('summarize')}
              className="inline-flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-transparent hover:bg-muted text-[12px] text-foreground transition-colors text-left"
              data-testid="caixa-unif-thread-ai-summarize"
            >
              <Sparkles size={13} className="text-primary" aria-hidden /> Resumir conversa
            </button>
            <button
              type="button"
              onClick={() => setAiMode('ask')}
              className="inline-flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-transparent hover:bg-muted text-[12px] text-foreground transition-colors text-left"
              data-testid="caixa-unif-thread-ai-ask"
            >
              <Sparkles size={13} className="text-primary" aria-hidden /> Perguntar ao histórico
            </button>
          </Stack>
        )}

        {/* 1. Fila — US-WA-305: select de override manual (vence heurística tag→fila) */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <Inline gap={0} align="center" justify="between">
            <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
              Fila
            </small>
            <Popover open={queuePopOpen} onOpenChange={setQueuePopOpen}>
              <PopoverTrigger asChild>
                <button
                  type="button"
                  className="inline-flex items-center gap-0.5 text-[10px] text-muted-foreground hover:text-foreground transition-colors"
                  title="Mover conversa pra outra fila (override manual)"
                  data-testid="caixa-unif-ctx-queue-move"
                >
                  <Plus size={11} aria-hidden /> mover
                </button>
              </PopoverTrigger>
              <PopoverContent align="end" className="w-60 p-1.5">
                <div className="text-[10px] uppercase tracking-[0.06em] text-muted-foreground font-semibold px-2 pt-1 pb-1.5">
                  Mover pra fila
                </div>
                <ul className="max-h-64 overflow-auto">
                  {Object.entries(queues).map(([slug, cfg]) => {
                    const active = thread.queue.slug === slug;
                    return (
                      <li key={slug}>
                        <button
                          type="button"
                          onClick={() => moveToQueue(slug)}
                          data-testid={`caixa-unif-ctx-queue-pick-${slug}`}
                          className="w-full inline-flex items-center justify-between gap-2 px-2 py-1.5 text-[11.5px] hover:bg-muted rounded text-left"
                        >
                          <span className="inline-flex items-center gap-1.5 min-w-0">
                            <span
                              className="inline-block w-2 h-2 rounded-full flex-shrink-0"
                              style={{ background: `oklch(0.55 0.13 ${cfg.hue})` }}
                              aria-hidden
                            />
                            <span className="truncate">{cfg.label}</span>
                            {cfg.sla && <small className="text-[10px] text-muted-foreground flex-shrink-0">SLA {cfg.sla}</small>}
                          </span>
                          {active && <Check size={13} className="text-primary flex-shrink-0" aria-label="Fila atual" />}
                        </button>
                      </li>
                    );
                  })}
                </ul>
                {thread.queue_is_override && (
                  <button
                    type="button"
                    onClick={() => moveToQueue(null)}
                    data-testid="caixa-unif-ctx-queue-auto"
                    className="w-full mt-1 px-2 py-1.5 text-[10.5px] text-muted-foreground hover:text-foreground hover:bg-muted rounded text-center transition-colors"
                  >
                    Voltar pra automática (heurística por tags)
                  </button>
                )}
              </PopoverContent>
            </Popover>
          </Inline>
          <b className="inline-flex items-center gap-1.5 text-[12.5px] font-medium" data-testid="caixa-unif-ctx-queue">
            <span
              className="inline-block w-2 h-2 rounded-full flex-shrink-0"
              style={{ background: `oklch(0.55 0.13 ${thread.queue.hue})` }}
              aria-hidden
            />
            {thread.queue.label}
            {thread.queue_is_override && (
              <span
                className="inline-flex text-[9px] font-medium text-muted-foreground bg-muted border rounded-full px-1.5 flex-shrink-0"
                title="Fila definida manualmente — vence a heurística por tags"
              >
                manual
              </span>
            )}
          </b>
          {queueCfg?.sla && (
            <small className="text-[11px] text-muted-foreground mt-0.5">
              SLA {queueCfg.sla}
            </small>
          )}
        </div>

        {/* 2. Atribuído — US-WA-302 assignee picker (Popover, mesmo pattern do editor de tags) */}
        <Stack gap={1} className="pb-2.5 border-b border-border/50">
          <Inline gap={0} align="center" justify="between">
            <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
              Atribuído
            </small>
            <Popover open={assignPopOpen} onOpenChange={setAssignPopOpen}>
              <PopoverTrigger asChild>
                <button
                  type="button"
                  className="inline-flex items-center gap-0.5 text-[10px] text-muted-foreground hover:text-foreground transition-colors"
                  title="Atribuir conversa a um operador"
                  data-testid="caixa-unif-ctx-assignee-edit"
                >
                  <Plus size={11} aria-hidden /> {thread.assigned_user_id ? 'trocar' : 'atribuir'}
                </button>
              </PopoverTrigger>
              <PopoverContent align="end" className="w-60 p-1.5">
                <div className="text-[10px] uppercase tracking-[0.06em] text-muted-foreground font-semibold px-2 pt-1 pb-1.5">
                  Operadores do business
                </div>
                {availableAssignees.length === 0 ? (
                  <div className="px-2 py-2 text-[11px] text-muted-foreground italic">
                    Nenhum operador com acesso ao atendimento
                  </div>
                ) : (
                  <ul className="max-h-64 overflow-auto">
                    {availableAssignees.map(a => {
                      const active = thread.assigned_user_id === a.id;
                      return (
                        <li key={a.id}>
                          <button
                            type="button"
                            onClick={() => assignTo(active ? null : a.id)}
                            data-testid={`caixa-unif-ctx-assignee-pick-${a.id}`}
                            className="w-full flex items-center justify-between gap-2 px-2 py-1.5 text-[11.5px] hover:bg-muted rounded text-left"
                          >
                            <span className="inline-flex items-center gap-1.5 min-w-0">
                              <span
                                className="inline-grid place-items-center w-5 h-5 rounded-full text-white text-[8.5px] font-bold flex-shrink-0"
                                style={{ background: `oklch(0.60 0.12 ${avatarHue(a.name)})` }}
                                aria-hidden
                              >
                                {initials(a.name)}
                              </span>
                              <span className="truncate">{a.name}</span>
                            </span>
                            {active && <Check size={13} className="text-primary flex-shrink-0" aria-label="Atribuído" />}
                          </button>
                        </li>
                      );
                    })}
                  </ul>
                )}
                {thread.assigned_user_id !== null && (
                  <button
                    type="button"
                    onClick={() => assignTo(null)}
                    data-testid="caixa-unif-ctx-assignee-remove"
                    className="w-full mt-1 inline-flex items-center justify-center gap-1 px-2 py-1.5 text-[10.5px] text-muted-foreground hover:text-destructive hover:bg-destructive/5 rounded transition-colors"
                  >
                    <UserMinus size={11} aria-hidden /> Remover atribuição
                  </button>
                )}
              </PopoverContent>
            </Popover>
          </Inline>
          {thread.assigned_user_name ? (
            <b className="inline-flex items-center gap-1.5 text-[12.5px] font-medium" data-testid="caixa-unif-ctx-assignee">
              <span
                className="inline-grid place-items-center w-5 h-5 rounded-full text-white text-[8.5px] font-bold flex-shrink-0"
                style={{ background: `oklch(0.60 0.12 ${avatarHue(thread.assigned_user_name)})` }}
                aria-hidden
              >
                {initials(thread.assigned_user_name)}
              </span>
              {thread.assigned_user_name}
            </b>
          ) : (
            <b className="text-[12.5px] font-medium text-muted-foreground italic" data-testid="caixa-unif-ctx-assignee">
              — sem atribuição
            </b>
          )}
        </Stack>

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
                // Cowork .om-tag — âmbar-pastel. Dark-aware via warning-soft/warning
                // (flipam no .dark); texto foreground mantém contraste nos 2 temas
                // (antes: oklch claro cru → chip claro-no-claro no escuro).
                <span
                  key={t.id}
                  className="inline-block px-2 py-px text-[10px] font-mono rounded-full text-foreground bg-warning-soft border border-warning/30"
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

        {/* 6. Saldo cliente — Onda 3: a receber em aberto (transactions UPOS, due/partial) */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            Saldo cliente
          </small>
          {!customerContext?.linked ? (
            <b className="text-[12.5px] font-medium text-muted-foreground italic" data-testid="caixa-unif-ctx-saldo">—</b>
          ) : customerContext.saldo_aberto > 0 ? (
            <b className="text-[12.5px] font-semibold text-destructive" data-testid="caixa-unif-ctx-saldo">
              {formatBRL(customerContext.saldo_aberto)} a receber
            </b>
          ) : (
            <b className="text-[12.5px] font-medium text-muted-foreground" data-testid="caixa-unif-ctx-saldo">
              {formatBRL(0)} · em dia
            </b>
          )}
        </div>

        {/* 7. Histórico — Onda 3: pedidos + LTV (transactions, status != draft) */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-0.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            Histórico
          </small>
          {customerContext?.linked && customerContext.sells_count > 0 ? (
            <b className="text-[12.5px] font-medium" data-testid="caixa-unif-ctx-historico">
              {customerContext.sells_count} {customerContext.sells_count === 1 ? 'pedido' : 'pedidos'} · {formatBRL(customerContext.ltv)} LTV
            </b>
          ) : (
            <b className="text-[12.5px] font-medium text-muted-foreground italic" data-testid="caixa-unif-ctx-historico">
              {customerContext?.linked ? 'sem pedidos ainda' : '—'}
            </b>
          )}
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

        {/* Wave 3 F1 — Contato CRM (vincular existente + criar do phone + Bloquear) */}
        <div className="pb-2.5 border-b border-border/50 flex flex-col gap-1.5">
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold">
            Contato CRM
          </small>
          <button
            type="button"
            onClick={() => setPickerOpen(true)}
            disabled={isPreview}
            className="inline-flex items-center gap-1.5 text-left text-[12.5px] font-medium px-3 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
            data-testid="caixa-unif-ctx-link-contact"
            title="Buscar e vincular Contact CRM existente"
          >
            <LinkIcon size={12} aria-hidden />
            Vincular contato existente
          </button>
          <button
            type="button"
            onClick={createContactFromPhone}
            disabled={isPreview}
            className="inline-flex items-center gap-1.5 text-left text-[12.5px] font-medium px-3 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
            data-testid="caixa-unif-ctx-create-contact"
            title="Cria registro novo no CRM a partir do número de telefone"
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
            className="text-left text-[12.5px] font-medium px-3 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
            title="Emitir cobrança (em breve)"
          >
            Emitir cobrança
          </button>
          {/* TODO US-WA-XXX: integrar Modules/Financeiro emitir Titulo + boleto Asaas */}

          <button
            type="button"
            disabled={isPreview}
            data-testid="caixa-unif-ctx-action-arte"
            className="text-left text-[12.5px] font-medium px-3 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
            title="Enviar arte (em breve)"
          >
            Enviar arte
          </button>
          {/* TODO US-WA-XXX: composer media upload reusando InboxController::sendMedia */}

          <button
            type="button"
            disabled={isPreview}
            data-testid="caixa-unif-ctx-action-ligar"
            className="text-left text-[12.5px] font-medium px-3 py-1.5 bg-card border rounded hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed transition-colors"
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
            className={`inline-flex items-center gap-1.5 text-left text-[12.5px] font-medium px-3 py-1.5 border rounded transition-colors mt-1 ${
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
      </Stack>

      {/* Wave 3-B F1 — Contact CRM picker modal (reusa legacy Pages/Whatsapp) */}
      <ContactPickerModal
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        searchRouteName="atendimento.inbox.contacts.search"
        onSelect={linkContact}
        customerPhone={thread.customer_external_id}
      />

      {/* T1 — IA Resumir/Perguntar (movido do header da thread) */}
      {aiMode !== null && (
        <InboxAiDialog
          open={aiMode !== null}
          onOpenChange={(o) => { if (!o) setAiMode(null); }}
          mode={aiMode}
          conversationId={thread.id}
        />
      )}
    </aside>
  );
}
