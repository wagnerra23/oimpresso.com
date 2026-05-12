import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
  UserCheck,
  UserPlus,
  Bot,
  Check,
  RotateCcw,
  Hourglass,
  Clock,
  AlertTriangle,
  ChevronRight,
  Phone,
  Mail,
  ExternalLink,
  X as XIcon,
  Ban,
  ShieldOff,
  Plus,
} from 'lucide-react';

import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';

import Avatar from './Avatar';
import ContactPickerModal from './ContactPickerModal';
import { formatDateTime, isLikelyLid, type ConvTag, type ThreadConversation } from './helpers';

interface Props {
  conversation: ThreadConversation;
  /** Reload partial: indica quais props recarregar após PATCH. */
  reloadOnly: string[];
  /** Quando true, registra atalhos E (resolver) e A (aguardar humano) globais. */
  enableShortcuts?: boolean;
  /** Quando fornecido, renderiza botão pra colapsar a sidebar (chama callback). */
  onCollapse?: () => void;
  /** Route name pro PATCH de updateStatus. Default `atendimento.inbox.update_status`
   * pós-US-WA-091 (rotas legacy `/whatsapp/conversations*` removidas). */
  updateStatusRouteName?: string;
  /** US-WA-063: tags disponíveis no business (catálogo). Quando fornecido,
   * renderiza card "Tags" com chips multi-select. Omite pra esconder UI. */
  availableTags?: ConvTag[];
  /** US-WA-063: route name pra PATCH sync tags (ex: `atendimento.inbox.update_tags`). */
  updateTagsRouteName?: string;
  /** US-WA-064: route name pra GET busca de Contacts (modal). */
  searchContactsRouteName?: string;
  /** US-WA-064: route name pra PATCH vincular/desvincular Contact à conv. */
  linkContactRouteName?: string;
  /** US-WA-078: route name pra POST que cria Contact UltimatePOS a partir
   * do phone da conversa e linka. Quando fornecido (junto com `linkContactRouteName`),
   * o card "Contato CRM" mostra um botão secundário "+ Cadastrar como contato"
   * quando `linked_contact` é null. */
  createContactFromPhoneRouteName?: string;
  /** US-WA-066: route name pra PATCH bloquear/desbloquear contato
   * (ex: `atendimento.inbox.block`). Quando fornecido, renderiza botão
   * "Bloquear" no card Ações. Omite pra esconder UI. */
  blockRouteName?: string;
}

export default function ConversationSidebar({
  conversation, reloadOnly, enableShortcuts = false, onCollapse,
  updateStatusRouteName = 'atendimento.inbox.update_status',
  availableTags,
  updateTagsRouteName,
  searchContactsRouteName,
  linkContactRouteName,
  createContactFromPhoneRouteName,
  blockRouteName,
}: Props) {
  // US-WA-064: modal busca de Contact
  const [contactPickerOpen, setContactPickerOpen] = useState(false);
  // US-WA-066: estado local pra confirmação inline antes do PATCH (evita
  // clique acidental — bloquear contato é ação destrutiva, ROTA LIVRE 99%
  // volume não pode perder cliente por erro de UI).
  const [confirmingBlock, setConfirmingBlock] = useState(false);
  const sharedAuth = (usePage().props as any)?.auth?.user as { id?: number } | undefined;
  const currentUserId = sharedAuth?.id ?? null;
  const isMineAssigned = !!(conversation.assigned_user && currentUserId && conversation.assigned_user.id === currentUserId);
  // US-WA-063: IDs das tags atualmente aplicadas à conv
  const activeTagIds = new Set((conversation.tags ?? []).map((t) => t.id));

  function patchConversation(payload: Record<string, string | number | boolean>) {
    router.patch(route(updateStatusRouteName, conversation.id), payload, {
      preserveScroll: true,
      preserveState: true,
      only: reloadOnly,
    });
  }

  // US-WA-064: vincular/desvincular Contact UltimatePOS via PATCH
  function linkContactAction(contactId: number | null) {
    if (!linkContactRouteName) return;
    router.patch(
      route(linkContactRouteName, conversation.id),
      { contact_id: contactId },
      { preserveScroll: true, preserveState: true, only: reloadOnly },
    );
  }

  // US-WA-078: cria Contact UltimatePOS a partir do phone+push_name e linka.
  // Backend gera o `contact_id` UltimatePOS (numérico) + type='customer'.
  // Atendente edita campos extras (email, endereço) depois via /contacts/{id}.
  function createContactFromPhoneAction() {
    if (!createContactFromPhoneRouteName) return;
    router.post(
      route(createContactFromPhoneRouteName, conversation.id),
      {},
      { preserveScroll: true, preserveState: true, only: reloadOnly },
    );
  }

  // US-WA-066: PATCH bloquear/desbloquear. UI optimistic (router.patch
  // re-render via partial reload). Backend tolera 404 do daemon — UI
  // refresh traz is_blocked atualizado.
  function toggleBlock() {
    if (!blockRouteName) return;
    const shouldBlock = !conversation.is_blocked;
    router.patch(
      route(blockRouteName, conversation.id),
      { block: shouldBlock },
      { preserveScroll: true, preserveState: true, only: reloadOnly },
    );
    setConfirmingBlock(false);
  }

  // US-WA-063: toggle tag → sync array completo (substitui, não merge).
  function toggleTag(tagId: number) {
    if (!updateTagsRouteName) return;
    const nextIds = new Set(activeTagIds);
    if (nextIds.has(tagId)) nextIds.delete(tagId);
    else nextIds.add(tagId);
    router.patch(
      route(updateTagsRouteName, conversation.id),
      { tag_ids: Array.from(nextIds) },
      { preserveScroll: true, preserveState: true, only: reloadOnly },
    );
  }

  // US-WA-063: mapping cor key → classes Tailwind chip
  function chipClasses(color: string, active: boolean): string {
    const map: Record<string, { border: string; text: string; bg: string }> = {
      blue:     { border: 'border-blue-500',     text: 'text-blue-700 dark:text-blue-300',       bg: 'bg-blue-50 dark:bg-blue-950/40' },
      green:    { border: 'border-green-500',    text: 'text-green-700 dark:text-green-300',     bg: 'bg-green-50 dark:bg-green-950/40' },
      emerald:  { border: 'border-emerald-500',  text: 'text-emerald-700 dark:text-emerald-300', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
      amber:    { border: 'border-amber-500',    text: 'text-amber-700 dark:text-amber-300',     bg: 'bg-amber-50 dark:bg-amber-950/40' },
      red:      { border: 'border-red-500',      text: 'text-red-700 dark:text-red-300',         bg: 'bg-red-50 dark:bg-red-950/40' },
      purple:   { border: 'border-purple-500',   text: 'text-purple-700 dark:text-purple-300',   bg: 'bg-purple-50 dark:bg-purple-950/40' },
      cyan:     { border: 'border-cyan-500',     text: 'text-cyan-700 dark:text-cyan-300',       bg: 'bg-cyan-50 dark:bg-cyan-950/40' },
      orange:   { border: 'border-orange-500',   text: 'text-orange-700 dark:text-orange-300',   bg: 'bg-orange-50 dark:bg-orange-950/40' },
      rose:     { border: 'border-rose-500',     text: 'text-rose-700 dark:text-rose-300',       bg: 'bg-rose-50 dark:bg-rose-950/40' },
      slate:    { border: 'border-slate-500',    text: 'text-slate-700 dark:text-slate-300',     bg: 'bg-slate-50 dark:bg-slate-900/40' },
    };
    const conf = map[color] ?? map.slate!;
    return active
      ? `${conf.border} ${conf.text} ${conf.bg}`
      : 'border-border text-muted-foreground bg-transparent hover:bg-accent';
  }

  // Atalhos teclado E (resolver) e A (aguardar humano) — ADR 0039 §2.
  useEffect(() => {
    if (!enableShortcuts) return;
    function handler(e: KeyboardEvent) {
      if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement ||
        (e.target instanceof HTMLElement && e.target.isContentEditable)
      ) return;
      if (e.metaKey || e.ctrlKey || e.altKey) return;

      if (e.key === 'e' || e.key === 'E') {
        if (conversation.status === 'resolved') return;
        e.preventDefault();
        patchConversation({ status: 'resolved' });
      }
      if (e.key === 'a' || e.key === 'A') {
        if (conversation.status === 'awaiting_human' || conversation.status === 'resolved') return;
        e.preventDefault();
        patchConversation({ status: 'awaiting_human' });
      }
    }
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [enableShortcuts, conversation.id, conversation.status]);

  return (
    <aside className="w-full lg:w-72 xl:w-80 shrink-0 space-y-3 overflow-y-auto" aria-label="Contexto da conversa">
      <Card className="p-4 relative">
        {onCollapse && (
          <button
            type="button"
            onClick={onCollapse}
            className="absolute top-2 right-2 w-6 h-6 rounded hover:bg-accent text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors"
            title="Minimizar painel"
            aria-label="Minimizar painel de contexto"
          >
            <ChevronRight size={14} />
          </button>
        )}
        <div className="flex flex-col items-center text-center gap-2">
          <Avatar name={conversation.contact_name} size="lg" />
          <div className="font-semibold leading-tight">{conversation.contact_name}</div>
          {conversation.contact_name !== conversation.customer_phone && (
            <div className="text-xs text-muted-foreground">{conversation.customer_phone}</div>
          )}
          {/* US-WA-093: badge LID — WhatsApp Multi-Device mascara phone real
              em conversas via Click-to-Chat / Status / Ads. Workaround custom
              (LidPhoneResolver) ainda não resolveu este LID — mostra hint pro
              atendente saber por que o número não parece BR. */}
          {isLikelyLid(conversation.customer_phone) && (
            <Badge
              variant="outline"
              className="text-[10px] gap-1 cursor-help"
              title="WhatsApp não enviou o número real (LID Multi-Device). Pode resolver vinculando contato CRM, ou aguardar próxima msg do cliente."
            >
              <ShieldOff size={10} aria-hidden />
              número oculto
            </Badge>
          )}
          <StatusBadge status={conversation.status} />
        </div>
      </Card>

      {/* US-WA-064: card Contato — só renderiza se Inbox novo passou rotas.
          - Não vinculado: botão "Vincular contato" abre modal busca
          - Vinculado: mostra nome/phones/email + link UltimatePOS + Desvincular */}
      {linkContactRouteName && searchContactsRouteName && (
        <Card className="p-3 space-y-2">
          <SectionLabel>Contato CRM</SectionLabel>
          {conversation.linked_contact ? (
            <div className="space-y-1.5">
              <div className="flex items-center gap-2">
                <Avatar name={conversation.linked_contact.name} size="sm" />
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-medium truncate">{conversation.linked_contact.name}</div>
                  <div className="text-[10px] text-muted-foreground uppercase tracking-wide">
                    {conversation.linked_contact.type}
                  </div>
                </div>
              </div>
              <div className="text-xs text-muted-foreground space-y-0.5">
                {conversation.linked_contact.mobile && (
                  <div className="inline-flex items-center gap-1.5"><Phone size={11} aria-hidden />{conversation.linked_contact.mobile}</div>
                )}
                {conversation.linked_contact.email && (
                  <div className="inline-flex items-center gap-1.5 truncate"><Mail size={11} aria-hidden />{conversation.linked_contact.email}</div>
                )}
              </div>
              <div className="flex gap-1.5 pt-1">
                <a
                  href={conversation.linked_contact.edit_url}
                  target="_blank"
                  className="flex-1"
                  rel="noreferrer"
                >
                  <Button variant="outline" size="sm" className="w-full text-xs h-7 gap-1">
                    <ExternalLink size={11} aria-hidden />
                    Abrir CRM
                  </Button>
                </a>
                <Button
                  variant="ghost"
                  size="sm"
                  className="text-xs h-7 px-2"
                  onClick={() => linkContactAction(null)}
                  title="Desvincular contato"
                >
                  <XIcon size={11} aria-hidden />
                </Button>
              </div>
            </div>
          ) : (
            <div className="space-y-1.5">
              <Button
                variant="outline"
                size="sm"
                className="w-full justify-start gap-2"
                onClick={() => setContactPickerOpen(true)}
                title="Vincular este número a um contato existente do CRM"
              >
                <UserPlus size={14} aria-hidden />
                Vincular contato
              </Button>
              {/* US-WA-078: cria Contact do zero usando push_name+phone que
                  o webhook já capturou. Só renderiza se Inbox passou a rota. */}
              {createContactFromPhoneRouteName && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="w-full justify-start gap-2 text-muted-foreground"
                  onClick={createContactFromPhoneAction}
                  title="Cadastrar este número como contato novo no CRM"
                >
                  <Plus size={14} aria-hidden />
                  Cadastrar como contato
                </Button>
              )}
            </div>
          )}
        </Card>
      )}

      <Card className="p-3 space-y-2">
        <SectionLabel>Ações</SectionLabel>
        <Button
          variant={isMineAssigned ? 'default' : 'outline'}
          size="sm"
          className="w-full justify-start gap-2"
          onClick={() => patchConversation({ assigned_to_me: !isMineAssigned })}
          title={isMineAssigned ? 'Clique pra liberar a conversa' : 'Atribuir esta conversa a mim'}
        >
          <UserCheck size={14} aria-hidden />
          {isMineAssigned ? 'Atribuída a mim' : 'Atribuir a mim'}
        </Button>
        <Button
          variant={conversation.bot_handling ? 'default' : 'outline'}
          size="sm"
          className="w-full justify-start gap-2"
          onClick={() => patchConversation({ bot_handling: !conversation.bot_handling })}
          title={conversation.bot_handling ? 'Desligar bot Jana' : 'Ligar bot Jana (HITL)'}
        >
          <Bot size={14} aria-hidden />
          {conversation.bot_handling ? 'Bot ligado' : 'Ativar bot'}
        </Button>
        <Separator className="my-2" />
        {conversation.status !== 'resolved' ? (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start gap-2 border-emerald-500 text-emerald-700 dark:text-emerald-400 dark:border-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
            onClick={() => patchConversation({ status: 'resolved' })}
            title={enableShortcuts ? 'Atalho: E' : 'Marcar conversa como resolvida'}
          >
            <Check size={14} aria-hidden />
            Marcar resolvida
            {enableShortcuts && <kbd className="ml-auto text-[10px] opacity-60">E</kbd>}
          </Button>
        ) : (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start gap-2"
            onClick={() => patchConversation({ status: 'open' })}
            title="Reabrir conversa"
          >
            <RotateCcw size={14} aria-hidden />
            Reabrir
          </Button>
        )}
        {conversation.status !== 'awaiting_human' && conversation.status !== 'resolved' && (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start gap-2 border-amber-500 text-amber-700 dark:text-amber-400 dark:border-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/30"
            onClick={() => patchConversation({ status: 'awaiting_human' })}
            title={enableShortcuts ? 'Atalho: A' : 'Marcar como aguardando atendimento humano'}
          >
            <Hourglass size={14} aria-hidden />
            Aguardar humano
            {enableShortcuts && <kbd className="ml-auto text-[10px] opacity-60">A</kbd>}
          </Button>
        )}
        {/* US-WA-066: bloquear/desbloquear contato (anti-spam) */}
        {blockRouteName && (
          <>
            <Separator className="my-2" />
            {conversation.is_blocked ? (
              <Button
                variant="outline"
                size="sm"
                className="w-full justify-start gap-2 border-slate-500 text-slate-700 dark:text-slate-400 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-900/30"
                onClick={toggleBlock}
                title="Desbloquear contato — voltará a receber msgs no Inbox"
              >
                <ShieldOff size={14} aria-hidden />
                Desbloquear contato
              </Button>
            ) : confirmingBlock ? (
              <div className="space-y-1.5">
                <div className="text-[11px] text-red-700 dark:text-red-400 px-1">
                  Confirmar? Inbound futuro será dropado.
                </div>
                <div className="flex gap-1.5">
                  <Button
                    variant="outline"
                    size="sm"
                    className="flex-1 border-red-500 text-red-700 dark:text-red-400 dark:border-red-700 hover:bg-red-50 dark:hover:bg-red-950/30"
                    onClick={toggleBlock}
                  >
                    Sim, bloquear
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="flex-1"
                    onClick={() => setConfirmingBlock(false)}
                  >
                    Cancelar
                  </Button>
                </div>
              </div>
            ) : (
              <Button
                variant="outline"
                size="sm"
                className="w-full justify-start gap-2 border-red-500 text-red-700 dark:text-red-400 dark:border-red-700 hover:bg-red-50 dark:hover:bg-red-950/30"
                onClick={() => setConfirmingBlock(true)}
                title="Bloquear contato — para de receber msgs deste número (anti-spam)"
              >
                <Ban size={14} aria-hidden />
                Bloquear contato
              </Button>
            )}
          </>
        )}
      </Card>

      {/* US-WA-063: card Tags — só renderiza se Inbox novo passou availableTags */}
      {availableTags && availableTags.length > 0 && updateTagsRouteName && (
        <Card className="p-3">
          <SectionLabel>Tags</SectionLabel>
          <div className="flex flex-wrap gap-1.5 mt-2">
            {availableTags.map((tag) => {
              const active = activeTagIds.has(tag.id);
              return (
                <button
                  key={tag.id}
                  type="button"
                  onClick={() => toggleTag(tag.id)}
                  className={`inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full border transition-colors ${chipClasses(tag.color, active)}`}
                  title={active ? `Remover tag "${tag.label}"` : `Adicionar tag "${tag.label}"`}
                  aria-pressed={active}
                >
                  {active && <Check size={10} aria-hidden />}
                  {tag.label}
                </button>
              );
            })}
          </div>
        </Card>
      )}

      <Card className="p-3">
        <SectionLabel>Janela 24h Meta</SectionLabel>
        <div className="text-xs text-muted-foreground mt-2 space-y-1">
          {conversation.within_24h_window ? (
            <p className="text-emerald-700 dark:text-emerald-400 inline-flex items-center gap-1">
              <Check size={12} aria-hidden />
              Aberta — freeform permitido em qualquer driver.
            </p>
          ) : (
            <p className="text-amber-700 dark:text-amber-400 inline-flex items-start gap-1">
              <AlertTriangle size={12} className="mt-0.5 shrink-0" aria-hidden />
              <span>Fechada — Meta Cloud exige template HSM aprovado. Z-API/Baileys ignoram.</span>
            </p>
          )}
          {conversation.last_inbound_at && (
            <p className="inline-flex items-center gap-1">
              <Clock size={12} aria-hidden />
              <span>
                Última msg do cliente:{' '}
                <span className="font-medium">{formatDateTime(conversation.last_inbound_at)}</span>
              </span>
            </p>
          )}
        </div>
      </Card>

      <Card className="p-3">
        <SectionLabel>Detalhes</SectionLabel>
        <dl className="text-xs space-y-1.5 mt-2">
          <Row label="Conversa #" value={`${conversation.id}`} />
          <Row label="Mensagens" value={`${conversation.messages_total}`} />
          {conversation.assigned_user && (
            <Row label="Atribuída a" value={conversation.assigned_user.name} />
          )}
          {conversation.created_at && (
            <Row label="Iniciada" value={formatDateTime(conversation.created_at)} />
          )}
          {conversation.last_message_at && (
            <Row label="Última msg" value={formatDateTime(conversation.last_message_at)} />
          )}
        </dl>
      </Card>

      {/* US-WA-064: modal busca de Contact UltimatePOS */}
      {searchContactsRouteName && linkContactRouteName && (
        <ContactPickerModal
          open={contactPickerOpen}
          onOpenChange={setContactPickerOpen}
          searchRouteName={searchContactsRouteName}
          customerPhone={conversation.customer_phone}
          onSelect={(contactId) => linkContactAction(contactId)}
        />
      )}
    </aside>
  );
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, { label: string; className: string }> = {
    open: { label: 'aberta', className: 'border-blue-500 text-blue-700 dark:text-blue-400 dark:border-blue-700 bg-blue-50 dark:bg-blue-950/30' },
    awaiting_human: { label: 'aguardando humano', className: 'border-amber-500 text-amber-700 dark:text-amber-400 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30' },
    resolved: { label: 'resolvida', className: 'border-emerald-500 text-emerald-700 dark:text-emerald-400 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/30' },
    archived: { label: 'arquivada', className: 'border-slate-400 text-slate-600 dark:text-slate-400 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/30' },
  };
  const conf = map[status] ?? map.open!;
  return <Badge variant="outline" className={conf.className}>{conf.label}</Badge>;
}

function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <div className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
      {children}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-2">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium text-right truncate">{value}</dd>
    </div>
  );
}
