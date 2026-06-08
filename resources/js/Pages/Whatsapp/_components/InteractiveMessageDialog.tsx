// InteractiveMessageDialog — composer pra mensagens interativas HSM
// (buttons / list / cta_url). US-WA-045b plug em ConversationThread.
//
// 3 tabs:
//  - Botões  (até 3 reply buttons; texto + opcional header/footer)
//  - Lista   (sections com items, até 10 items total)
//  - CTA URL (link único — Meta Cloud only; disabled em Baileys/Z-API)
//
// Envio: POST `/atendimento/inbox/conversations/{id}/send-interactive` via
// Inertia router.post (formato form). Backend valida + dispatcha pro daemon
// Baileys direto OU SendInteractiveJob (Meta/Z-API) — ver
// `InboxController::sendInteractive`.
//
// Preview WhatsApp-like ao rodapé: render mock simplificado do payload
// (bubble cinza + lista botões/items/cta) — não tem fidelidade WhatsApp
// pixel-perfect, mas dá ideia da estrutura antes do envio.
//
// @see Modules/Whatsapp/Jobs/SendInteractiveJob.php
// @see Modules/Whatsapp/Services/Drivers/DriverInterface.php::sendInteractive

import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Trash2, ExternalLink, LayoutList, MessageSquareDashed, Globe } from 'lucide-react';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Badge } from '@/Components/ui/badge';

// Tipo conservador pra payload do POST — espelha o que `router.post`
// aceita: primitivos + arrays aninhados de objetos com mesmos shapes.
type FormDataConvertibleSafe =
  | string
  | number
  | boolean
  | Array<Record<string, string | number | boolean | undefined>>
  | Array<{ title: string; items: Array<Record<string, string | undefined>> }>;

const BODY_MAX = 1024;
const HEADER_MAX = 60;
const FOOTER_MAX = 60;
const BTN_LABEL_MAX = 20;
const BTN_ID_MAX = 64;
const LIST_TITLE_MAX = 24;
const LIST_ITEM_DESC_MAX = 72;
const LIST_BUTTON_LABEL_MAX = 20;
const MAX_BUTTONS = 3;
const MAX_LIST_ITEMS_TOTAL = 10;
const CTA_LABEL_MAX = 20;
const CTA_URL_MAX = 2048;

type TabKind = 'buttons' | 'list' | 'cta_url';
type DriverType = 'meta_cloud' | 'baileys' | 'zapi' | string;

interface ButtonRow {
  id: string;
  label: string;
}

interface ListItem {
  id: string;
  title: string;
  description: string;
}

interface ListSection {
  title: string;
  items: ListItem[];
}

interface Props {
  /** ID da conversa (URL POST). */
  conversationId: number;
  /** Aberto/fechado controlado pelo parent. */
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /**
   * Tipo do canal — vem do `conversation.channel_type` no Inertia props.
   * `meta_cloud` libera tab CTA URL; outros desabilitam (Baileys/Z-API).
   * Aceita string ampla pra futuros canais não-whatsapp (ele só desabilita).
   */
  driverType: DriverType;
}

function slugify(s: string): string {
  return s
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, BTN_ID_MAX) || `btn_${Date.now()}`;
}

export default function InteractiveMessageDialog({
  conversationId, open, onOpenChange, driverType,
}: Props) {
  // Aceita tanto channel.type polimórfico (`whatsapp_meta`) quanto driver
  // legacy (`meta_cloud`) — CTA URL liberado em ambos.
  const supportsCtaUrl = driverType === 'meta_cloud' || driverType === 'whatsapp_meta';

  const [tab, setTab] = useState<TabKind>('buttons');
  const [body, setBody] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Buttons form
  const [header, setHeader] = useState('');
  const [footer, setFooter] = useState('');
  const [buttons, setButtons] = useState<ButtonRow[]>([
    { id: 'sim', label: 'Sim' },
    { id: 'nao', label: 'Não' },
  ]);

  // List form
  const [listButtonLabel, setListButtonLabel] = useState('Ver opções');
  const [sections, setSections] = useState<ListSection[]>([
    { title: 'Opções', items: [{ id: 'op1', title: 'Opção 1', description: '' }] },
  ]);

  // CTA URL form
  const [ctaLabel, setCtaLabel] = useState('Acessar');
  const [ctaUrl, setCtaUrl] = useState('https://');

  const totalListItems = useMemo(
    () => sections.reduce((acc, s) => acc + s.items.length, 0),
    [sections],
  );

  // Validação leve client-side — backend revalida tudo (Tier 0).
  const canSubmit = useMemo(() => {
    if (submitting) return false;
    if (!body.trim() || body.length > BODY_MAX) return false;
    if (tab === 'buttons') {
      if (buttons.length === 0 || buttons.length > MAX_BUTTONS) return false;
      return buttons.every((b) => b.id.trim() && b.label.trim() && b.label.length <= BTN_LABEL_MAX);
    }
    if (tab === 'list') {
      if (!listButtonLabel.trim() || listButtonLabel.length > LIST_BUTTON_LABEL_MAX) return false;
      if (totalListItems === 0 || totalListItems > MAX_LIST_ITEMS_TOTAL) return false;
      return sections.every(
        (s) =>
          s.title.trim() &&
          s.title.length <= LIST_TITLE_MAX &&
          s.items.length > 0 &&
          s.items.every(
            (i) =>
              i.id.trim() &&
              i.title.trim() &&
              i.title.length <= LIST_TITLE_MAX &&
              i.description.length <= LIST_ITEM_DESC_MAX,
          ),
      );
    }
    if (tab === 'cta_url') {
      if (!supportsCtaUrl) return false;
      if (!ctaLabel.trim() || ctaLabel.length > CTA_LABEL_MAX) return false;
      try {
        const u = new URL(ctaUrl);
        return ['http:', 'https:'].includes(u.protocol);
      } catch {
        return false;
      }
    }
    return false;
  }, [submitting, body, tab, buttons, listButtonLabel, sections, totalListItems, ctaLabel, ctaUrl, supportsCtaUrl]);

  function resetForm() {
    setBody('');
    setHeader('');
    setFooter('');
    setButtons([{ id: 'sim', label: 'Sim' }, { id: 'nao', label: 'Não' }]);
    setListButtonLabel('Ver opções');
    setSections([{ title: 'Opções', items: [{ id: 'op1', title: 'Opção 1', description: '' }] }]);
    setCtaLabel('Acessar');
    setCtaUrl('https://');
    setTab('buttons');
  }

  function handleSubmit() {
    if (!canSubmit) return;
    setSubmitting(true);

    // Payload alinhado ao InboxController::sendInteractive (flat keys).
    // Backend monta o `interactive` discriminated union internamente.
    // FormDataConvertible — Inertia aceita primitivos + array aninhados.
    const payload: Record<string, FormDataConvertibleSafe> = {
      body,
      type: tab,
    };

    if (tab === 'buttons') {
      payload.buttons = buttons.map((b) => ({ id: b.id, label: b.label }));
      if (header.trim()) payload.header = header;
      if (footer.trim()) payload.footer = footer;
    } else if (tab === 'list') {
      payload.button_label = listButtonLabel;
      payload.sections = sections.map((s) => ({
        title: s.title,
        items: s.items.map((i) => ({
          id: i.id,
          title: i.title,
          ...(i.description.trim() ? { description: i.description } : {}),
        })),
      }));
    } else if (tab === 'cta_url') {
      payload.cta_label = ctaLabel;
      payload.cta_url = ctaUrl;
    }

    router.post(
      route('atendimento.inbox.send_interactive', { id: conversationId }),
      // Inertia v3 `RequestPayload` é mais restrito que `Record<string, unknown>`;
      // unknown-cast aqui é seguro pq serializamos só strings + arrays planos
      // tipados acima.
      payload as unknown as Parameters<typeof router.post>[1],
      {
        preserveScroll: true,
        onSuccess: () => {
          resetForm();
          onOpenChange(false);
        },
        onFinish: () => setSubmitting(false),
      },
    );
  }

  // ============ Mutators dos forms ============

  function updateButton(idx: number, patch: Partial<ButtonRow>) {
    setButtons((prev) => prev.map((b, i) => (i === idx ? { ...b, ...patch } : b)));
  }
  function addButton() {
    if (buttons.length >= MAX_BUTTONS) return;
    setButtons((prev) => [...prev, { id: `btn_${prev.length + 1}`, label: '' }]);
  }
  function removeButton(idx: number) {
    setButtons((prev) => prev.filter((_, i) => i !== idx));
  }

  function updateSection(idx: number, patch: Partial<ListSection>) {
    setSections((prev) => prev.map((s, i) => (i === idx ? { ...s, ...patch } : s)));
  }
  function addSection() {
    setSections((prev) => [
      ...prev,
      { title: `Seção ${prev.length + 1}`, items: [{ id: `op${prev.length + 1}_1`, title: '', description: '' }] },
    ]);
  }
  function removeSection(idx: number) {
    setSections((prev) => prev.filter((_, i) => i !== idx));
  }
  function updateItem(sIdx: number, iIdx: number, patch: Partial<ListItem>) {
    setSections((prev) =>
      prev.map((s, si) =>
        si !== sIdx ? s : { ...s, items: s.items.map((it, ii) => (ii === iIdx ? { ...it, ...patch } : it)) },
      ),
    );
  }
  function addItem(sIdx: number) {
    if (totalListItems >= MAX_LIST_ITEMS_TOTAL) return;
    setSections((prev) =>
      prev.map((s, si) =>
        si !== sIdx
          ? s
          : { ...s, items: [...s.items, { id: `op${si + 1}_${s.items.length + 1}`, title: '', description: '' }] },
      ),
    );
  }
  function removeItem(sIdx: number, iIdx: number) {
    setSections((prev) =>
      prev.map((s, si) =>
        si !== sIdx ? s : { ...s, items: s.items.filter((_, ii) => ii !== iIdx) },
      ),
    );
  }

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!submitting) onOpenChange(o); }}>
      <DialogContent
        className="max-w-2xl max-h-[85vh] overflow-y-auto"
        data-testid="interactive-dialog"
      >
        <DialogHeader>
          <DialogTitle>Mensagem interativa</DialogTitle>
          <DialogDescription>
            Envie botões reply, menu de lista ou link CTA. WhatsApp limita estrutura
            (max 3 botões / 10 itens lista / CTA URL só Meta).
          </DialogDescription>
        </DialogHeader>

        {/* Tabs custom — sem Components/ui/tabs disponível no projeto */}
        <div className="flex gap-1 border-b border-border">
          <TabButton
            active={tab === 'buttons'}
            onClick={() => setTab('buttons')}
            icon={<MessageSquareDashed size={13} aria-hidden />}
            label="Botões"
            testid="tab-buttons"
          />
          <TabButton
            active={tab === 'list'}
            onClick={() => setTab('list')}
            icon={<LayoutList size={13} aria-hidden />}
            label="Lista"
            testid="tab-list"
          />
          <TabButton
            active={tab === 'cta_url'}
            disabled={!supportsCtaUrl}
            title={supportsCtaUrl ? 'Link CTA' : 'CTA URL só está disponível em Meta Cloud'}
            onClick={() => setTab('cta_url')}
            icon={<Globe size={13} aria-hidden />}
            label="CTA URL"
            testid="tab-cta-url"
          />
        </div>

        {/* Body comum a todas tabs */}
        <div className="space-y-2">
          <Label htmlFor="interactive-body">
            Texto da mensagem <span className="text-xs text-muted-foreground">({body.length}/{BODY_MAX})</span>
          </Label>
          <Textarea
            id="interactive-body"
            value={body}
            onChange={(e) => setBody(e.target.value)}
            placeholder="Texto principal que aparece acima dos botões/lista…"
            rows={3}
            maxLength={BODY_MAX}
            data-testid="interactive-body"
          />
        </div>

        {tab === 'buttons' && (
          <div className="space-y-3" data-testid="interactive-form-buttons">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label htmlFor="interactive-header" className="text-xs">
                  Cabeçalho (opcional, {header.length}/{HEADER_MAX})
                </Label>
                <Input
                  id="interactive-header"
                  value={header}
                  onChange={(e) => setHeader(e.target.value)}
                  maxLength={HEADER_MAX}
                  data-testid="interactive-header"
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="interactive-footer" className="text-xs">
                  Rodapé (opcional, {footer.length}/{FOOTER_MAX})
                </Label>
                <Input
                  id="interactive-footer"
                  value={footer}
                  onChange={(e) => setFooter(e.target.value)}
                  maxLength={FOOTER_MAX}
                  data-testid="interactive-footer"
                />
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label className="text-xs">Botões ({buttons.length}/{MAX_BUTTONS})</Label>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={addButton}
                  disabled={buttons.length >= MAX_BUTTONS}
                  className="h-7 px-2"
                  data-testid="interactive-add-button"
                >
                  <Plus size={12} className="mr-1" aria-hidden />
                  Adicionar
                </Button>
              </div>
              {buttons.map((b, idx) => (
                <div key={idx} className="flex gap-2 items-end" data-testid={`interactive-button-row-${idx}`}>
                  <div className="flex-1 space-y-1">
                    <Label className="text-[10px] text-muted-foreground">ID interno</Label>
                    <Input
                      value={b.id}
                      onChange={(e) => updateButton(idx, { id: e.target.value })}
                      maxLength={BTN_ID_MAX}
                      placeholder="sim"
                      onBlur={() => { if (!b.id.trim() && b.label.trim()) updateButton(idx, { id: slugify(b.label) }); }}
                    />
                  </div>
                  <div className="flex-1 space-y-1">
                    <Label className="text-[10px] text-muted-foreground">Rótulo ({b.label.length}/{BTN_LABEL_MAX})</Label>
                    <Input
                      value={b.label}
                      onChange={(e) => updateButton(idx, { label: e.target.value })}
                      maxLength={BTN_LABEL_MAX}
                      placeholder="Sim"
                    />
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => removeButton(idx)}
                    disabled={buttons.length <= 1}
                    className="h-9 w-9 p-0"
                    aria-label="Remover botão"
                    data-testid={`interactive-remove-button-${idx}`}
                  >
                    <Trash2 size={14} aria-hidden />
                  </Button>
                </div>
              ))}
            </div>
          </div>
        )}

        {tab === 'list' && (
          <div className="space-y-3" data-testid="interactive-form-list">
            <div className="space-y-1">
              <Label htmlFor="interactive-list-button" className="text-xs">
                Rótulo do botão da lista ({listButtonLabel.length}/{LIST_BUTTON_LABEL_MAX})
              </Label>
              <Input
                id="interactive-list-button"
                value={listButtonLabel}
                onChange={(e) => setListButtonLabel(e.target.value)}
                maxLength={LIST_BUTTON_LABEL_MAX}
                placeholder="Ver opções"
                data-testid="interactive-list-button-label"
              />
            </div>

            <div className="flex items-center justify-between">
              <Label className="text-xs">
                Seções ({sections.length}) — total {totalListItems}/{MAX_LIST_ITEMS_TOTAL} itens
              </Label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addSection}
                className="h-7 px-2"
                data-testid="interactive-add-section"
              >
                <Plus size={12} className="mr-1" aria-hidden />
                Seção
              </Button>
            </div>

            <div className="space-y-3">
              {sections.map((s, sIdx) => (
                <div
                  key={sIdx}
                  className="border border-border rounded-md p-3 space-y-2"
                  data-testid={`interactive-section-${sIdx}`}
                >
                  <div className="flex gap-2 items-end">
                    <div className="flex-1 space-y-1">
                      <Label className="text-[10px] text-muted-foreground">Título da seção</Label>
                      <Input
                        value={s.title}
                        onChange={(e) => updateSection(sIdx, { title: e.target.value })}
                        maxLength={LIST_TITLE_MAX}
                        placeholder="Tamanhos"
                      />
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => removeSection(sIdx)}
                      disabled={sections.length <= 1}
                      className="h-9 w-9 p-0"
                      aria-label="Remover seção"
                    >
                      <Trash2 size={14} aria-hidden />
                    </Button>
                  </div>

                  <div className="space-y-2 pl-2 border-l-2 border-muted">
                    {s.items.map((it, iIdx) => (
                      <div key={iIdx} className="space-y-1" data-testid={`interactive-item-${sIdx}-${iIdx}`}>
                        <div className="flex gap-2 items-end">
                          <div className="flex-1 space-y-1">
                            <Label className="text-[10px] text-muted-foreground">ID</Label>
                            <Input
                              value={it.id}
                              onChange={(e) => updateItem(sIdx, iIdx, { id: e.target.value })}
                              maxLength={BTN_ID_MAX}
                              placeholder="p"
                              onBlur={() => { if (!it.id.trim() && it.title.trim()) updateItem(sIdx, iIdx, { id: slugify(it.title) }); }}
                            />
                          </div>
                          <div className="flex-1 space-y-1">
                            <Label className="text-[10px] text-muted-foreground">Título</Label>
                            <Input
                              value={it.title}
                              onChange={(e) => updateItem(sIdx, iIdx, { title: e.target.value })}
                              maxLength={LIST_TITLE_MAX}
                              placeholder="P"
                            />
                          </div>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => removeItem(sIdx, iIdx)}
                            disabled={s.items.length <= 1}
                            className="h-9 w-9 p-0"
                            aria-label="Remover item"
                          >
                            <Trash2 size={14} aria-hidden />
                          </Button>
                        </div>
                        <Input
                          value={it.description}
                          onChange={(e) => updateItem(sIdx, iIdx, { description: e.target.value })}
                          maxLength={LIST_ITEM_DESC_MAX}
                          placeholder={`Descrição opcional (${it.description.length}/${LIST_ITEM_DESC_MAX})`}
                          className="text-xs"
                        />
                      </div>
                    ))}
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => addItem(sIdx)}
                      disabled={totalListItems >= MAX_LIST_ITEMS_TOTAL}
                      className="h-7 text-xs"
                      data-testid={`interactive-add-item-${sIdx}`}
                    >
                      <Plus size={12} className="mr-1" aria-hidden />
                      Adicionar item
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {tab === 'cta_url' && (
          <div className="space-y-3" data-testid="interactive-form-cta">
            {!supportsCtaUrl && (
              <div className="rounded-md bg-amber-50 dark:bg-amber-950/30 text-amber-900 dark:text-amber-200 text-xs p-2">
                Botão CTA URL só está disponível em canais Meta Cloud. Este canal usa
                <strong> {driverType}</strong> — selecione outra tab.
              </div>
            )}
            <div className="space-y-1">
              <Label htmlFor="interactive-cta-label" className="text-xs">
                Rótulo do botão ({ctaLabel.length}/{CTA_LABEL_MAX})
              </Label>
              <Input
                id="interactive-cta-label"
                value={ctaLabel}
                onChange={(e) => setCtaLabel(e.target.value)}
                maxLength={CTA_LABEL_MAX}
                placeholder="Acessar"
                disabled={!supportsCtaUrl}
                data-testid="interactive-cta-label"
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor="interactive-cta-url" className="text-xs">
                URL (https://…)
              </Label>
              <Input
                id="interactive-cta-url"
                value={ctaUrl}
                onChange={(e) => setCtaUrl(e.target.value)}
                maxLength={CTA_URL_MAX}
                type="url"
                placeholder="https://exemplo.com/promo"
                disabled={!supportsCtaUrl}
                data-testid="interactive-cta-url"
              />
            </div>
          </div>
        )}

        {/* Preview WhatsApp-like (mock simplificado) */}
        <div
          className="rounded-md border border-border bg-muted/40 p-3 space-y-2"
          data-testid="interactive-preview"
        >
          <div className="flex items-center justify-between">
            <Badge variant="outline" className="text-[10px]">Preview</Badge>
            <span className="text-[10px] text-muted-foreground">Aproximação — render real é do WhatsApp.</span>
          </div>
          <div className="max-w-sm rounded-lg bg-card p-3 text-sm shadow-sm space-y-2">
            {tab === 'buttons' && header.trim() && (
              <div className="font-semibold text-xs">{header}</div>
            )}
            <div className="whitespace-pre-wrap break-words">
              {body.trim() || <span className="opacity-50 italic">(texto da mensagem…)</span>}
            </div>
            {tab === 'buttons' && footer.trim() && (
              <div className="text-[10px] text-muted-foreground">{footer}</div>
            )}
            {tab === 'buttons' && (
              <div className="flex flex-col gap-1 pt-1 border-t border-border/50">
                {buttons.map((b, i) => (
                  <div
                    key={i}
                    className="text-xs text-center text-primary border border-border rounded py-1.5 px-2"
                  >
                    {b.label.trim() || <span className="opacity-50">(rótulo…)</span>}
                  </div>
                ))}
              </div>
            )}
            {tab === 'list' && (
              <div className="pt-1 border-t border-border/50 space-y-2">
                <div className="text-xs text-center text-primary border border-border rounded py-1.5 px-2 flex items-center justify-center gap-1">
                  <LayoutList size={11} aria-hidden />
                  {listButtonLabel.trim() || 'Ver opções'}
                </div>
                <div className="text-[10px] text-muted-foreground space-y-1">
                  {sections.map((s, si) => (
                    <div key={si}>
                      <div className="font-semibold">{s.title || `Seção ${si + 1}`}</div>
                      <ul className="pl-3 list-disc">
                        {s.items.map((it, ii) => (
                          <li key={ii}>
                            {it.title || '(item…)'}
                            {it.description && <span className="opacity-70"> — {it.description}</span>}
                          </li>
                        ))}
                      </ul>
                    </div>
                  ))}
                </div>
              </div>
            )}
            {tab === 'cta_url' && (
              <div className="pt-1 border-t border-border/50">
                <a
                  href={ctaUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="text-xs text-center text-primary border border-border rounded py-1.5 px-2 flex items-center justify-center gap-1"
                >
                  <ExternalLink size={11} aria-hidden />
                  {ctaLabel.trim() || 'Acessar'}
                </a>
              </div>
            )}
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={submitting}
          >
            Cancelar
          </Button>
          <Button
            type="button"
            onClick={handleSubmit}
            disabled={!canSubmit}
            data-testid="interactive-submit"
          >
            {submitting ? 'Enviando…' : 'Enviar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function TabButton({
  active, disabled, onClick, icon, label, title, testid,
}: {
  active: boolean;
  disabled?: boolean;
  onClick: () => void;
  icon: React.ReactNode;
  label: string;
  title?: string;
  testid: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      title={title}
      data-testid={testid}
      className={[
        'flex items-center gap-1 px-3 py-2 text-xs border-b-2 -mb-px transition-colors',
        active ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground',
        disabled ? 'opacity-50 cursor-not-allowed' : '',
      ].join(' ')}
    >
      {icon}
      {label}
    </button>
  );
}
