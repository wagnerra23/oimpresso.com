// NewConversationDialog.tsx — "+ Nova conversa" da Caixa Unificada V4
// (US-WA-307 · charter topnav).
//
// Dialog: conta ativa (select DS) + telefone OU Contact CRM (ContactPickerModal
// reusado, US-WA-064) + mensagem inicial opcional. POST
// atendimento.inbox.start_conversation faz find-or-create (número que já
// conversou REABRE a thread, não duplica) e redireciona com ?thread= aberto.
// Mensagem inicial reusa o pipeline send() inteiro no backend.

import { useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { MessageSquarePlus, Search, X } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Inline, Stack } from '@/Components/layout';
import ContactPickerModal from '@/Pages/Whatsapp/_components/ContactPickerModal';
import type { AccountItem } from './helpers';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  accounts: AccountItem[];
}

export default function NewConversationDialog({ open, onOpenChange, accounts }: Props) {
  const activeAccounts = useMemo(() => accounts.filter(a => a.status === 'ativo'), [accounts]);
  const [contactPickerOpen, setContactPickerOpen] = useState(false);
  const [linkedContactId, setLinkedContactId] = useState<number | null>(null);

  const form = useForm<{
    channel_id: string;
    contact_id: number | null;
    phone: string;
    name: string;
    body: string;
  }>({
    channel_id: '',
    contact_id: null,
    phone: '',
    name: '',
    body: '',
  });

  function submit() {
    if (form.processing) return;
    form.transform(data => ({
      ...data,
      channel_id: Number(data.channel_id),
      contact_id: linkedContactId,
    }));
    form.post(route('atendimento.inbox.start_conversation'), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        setLinkedContactId(null);
        onOpenChange(false);
      },
    });
  }

  const canSubmit = form.data.channel_id !== ''
    && (linkedContactId !== null || form.data.phone.replace(/\D/g, '').length >= 8)
    && !form.processing;

  return (
    <>
      <Dialog open={open} onOpenChange={(o) => { if (!o) { form.reset(); setLinkedContactId(null); } onOpenChange(o); }}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="inline-flex items-center gap-2">
              <MessageSquarePlus size={18} aria-hidden />
              Nova conversa
            </DialogTitle>
            <DialogDescription>
              Número que já conversou reabre a thread existente — não duplica.
            </DialogDescription>
          </DialogHeader>

          <Stack gap={3}>
            <Stack gap={1}>
              <Label className="text-[11px]">Conta de envio</Label>
              <Select value={form.data.channel_id} onValueChange={v => form.setData('channel_id', v)}>
                <SelectTrigger data-testid="caixa-unif-nova-conta">
                  <SelectValue placeholder={activeAccounts.length === 0 ? 'Nenhuma conta ativa' : 'Selecione a conta'} />
                </SelectTrigger>
                <SelectContent>
                  {activeAccounts.map(a => (
                    <SelectItem key={a.id} value={String(a.id)}>
                      {a.label} · {a.handle}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {activeAccounts.length === 0 && (
                <small className="text-[10.5px] text-muted-foreground">
                  Conecte um canal em Canais antes de iniciar conversas.
                </small>
              )}
            </Stack>

            <Stack gap={1}>
              <Inline gap={0} align="center" justify="between">
                <Label htmlFor="nova-conv-phone" className="text-[11px]">Telefone (DDI+DDD+número)</Label>
                <button
                  type="button"
                  onClick={() => setContactPickerOpen(true)}
                  className="inline-flex items-center gap-1 text-[10.5px] text-muted-foreground hover:text-foreground transition-colors"
                  data-testid="caixa-unif-nova-buscar-contato"
                >
                  <Search size={11} aria-hidden /> buscar no CRM
                </button>
              </Inline>
              {linkedContactId !== null ? (
                <Inline gap={2} align="center" className="border rounded-md px-3 py-2 bg-muted/30">
                  <span className="text-[12px] flex-1 min-w-0 truncate">
                    Contato CRM #{linkedContactId} vinculado — telefone vem do cadastro
                  </span>
                  <button
                    type="button"
                    onClick={() => setLinkedContactId(null)}
                    className="text-muted-foreground hover:text-destructive"
                    title="Remover vínculo e digitar telefone"
                    data-testid="caixa-unif-nova-unlink-contato"
                  >
                    <X size={13} aria-hidden />
                  </button>
                </Inline>
              ) : (
                <Input
                  id="nova-conv-phone"
                  value={form.data.phone}
                  onChange={e => form.setData('phone', e.target.value)}
                  placeholder="+55 48 99999-9999"
                  inputMode="tel"
                  data-testid="caixa-unif-nova-phone"
                />
              )}
              {form.errors.phone && <small className="text-[10.5px] text-destructive">{form.errors.phone}</small>}
              {form.errors.channel_id && <small className="text-[10.5px] text-destructive">{form.errors.channel_id}</small>}
            </Stack>

            {linkedContactId === null && (
              <Stack gap={1}>
                <Label htmlFor="nova-conv-name" className="text-[11px]">Nome (opcional)</Label>
                <Input
                  id="nova-conv-name"
                  value={form.data.name}
                  onChange={e => form.setData('name', e.target.value)}
                  placeholder="Nome do contato"
                  data-testid="caixa-unif-nova-name"
                />
              </Stack>
            )}

            <Stack gap={1}>
              <Label htmlFor="nova-conv-body" className="text-[11px]">Mensagem inicial (opcional)</Label>
              <Input
                id="nova-conv-body"
                value={form.data.body}
                onChange={e => form.setData('body', e.target.value)}
                placeholder="Ex.: Olá! Aqui é da Oimpresso…"
                data-testid="caixa-unif-nova-body"
              />
              <small className="text-[10.5px] text-muted-foreground">
                Sem mensagem, a conversa abre vazia e você usa o composer (templates, macros, variáveis).
              </small>
            </Stack>
          </Stack>

          <DialogFooter>
            <Button variant="outline" onClick={() => onOpenChange(false)}>Cancelar</Button>
            <Button onClick={submit} disabled={!canSubmit} data-testid="caixa-unif-nova-submit">
              {form.processing ? 'Abrindo…' : 'Iniciar conversa'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* US-WA-064 — picker de Contact CRM reusado (busca debounced) */}
      <ContactPickerModal
        open={contactPickerOpen}
        onOpenChange={setContactPickerOpen}
        searchRouteName="atendimento.inbox.contacts.search"
        onSelect={(contactId) => {
          setLinkedContactId(contactId);
          setContactPickerOpen(false);
        }}
      />
    </>
  );
}
