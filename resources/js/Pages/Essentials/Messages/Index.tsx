// @docvault
//   tela: /essentials/messages
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/MessagesIndexTest

import AppShell from '@/Layouts/AppShell';
import { useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import { MessageCircle, Send, Trash2 } from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

interface Message {
  id: number;
  user_id: number;
  message: string;
  location_id: number | null;
  sender_name: string;
  created_at: string | null;
  created_at_human: string | null;
}

interface LocationOption { id: number; label: string; }

interface Props {
  messages: Message[];
  locations: LocationOption[];
  can: { view: boolean; create: boolean };
  refreshInterval: number;
  me: number;
}

function initials(name: string): string {
  if (!name || name === '—') return '?';
  const parts = name.trim().split(/\s+/);
  const a = parts[0]?.[0] ?? '';
  const b = parts.length > 1 ? parts[parts.length - 1][0] : '';
  return (a + b).toUpperCase();
}

export default function MessagesIndex({
  messages: initialMessages,
  locations,
  can,
  refreshInterval,
  me,
}: Props) {
  const [messages, setMessages] = useState<Message[]>(initialMessages);
  const [deleteTarget, setDeleteTarget] = useState<Message | null>(null);
  const scrollRef = useRef<HTMLDivElement | null>(null);

  const form = useForm<{ message: string; location_id: number | null }>({
    message: '',
    location_id: null,
  });

  const locationName = useMemo(() => {
    const map: Record<number, string> = {};
    locations.forEach((l) => (map[l.id] = l.label));
    return map;
  }, [locations]);

  // Auto-scroll pra última mensagem em cada update
  useEffect(() => {
    const el = scrollRef.current;
    if (el) el.scrollTop = el.scrollHeight;
  }, [messages.length]);

  // Polling de novas mensagens (intervalo vindo do backend)
  useEffect(() => {
    if (!can.view && !can.create) return;
    const intervalMs = Math.max(5, refreshInterval) * 1000;

    const fetchNew = async () => {
      const last = messages[messages.length - 1]?.created_at ?? '';
      try {
        const res = await fetch(
          `/essentials/get-new-messages?last_chat_time=${encodeURIComponent(last)}`,
          { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
        );
        if (!res.ok) return;
        const data = await res.json();
        if (Array.isArray(data?.messages) && data.messages.length > 0) {
          setMessages((prev) => {
            const existingIds = new Set(prev.map((m) => m.id));
            const appended = (data.messages as Message[]).filter((m) => !existingIds.has(m.id));
            return appended.length > 0 ? [...prev, ...appended] : prev;
          });
        }
      } catch {
        // silencia — polling volta na próxima tentativa
      }
    };

    const id = setInterval(fetchNew, intervalMs);
    return () => clearInterval(id);
  }, [messages, refreshInterval, can.view, can.create]);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (!form.data.message.trim()) return;
    form.post('/essentials/messages', {
      preserveScroll: true,
      onSuccess: (page) => {
        // Backend faz redirect pra index e re-renderiza — pegamos props novos
        const newMessages = (page.props as any)?.messages as Message[] | undefined;
        if (newMessages) setMessages(newMessages);
        form.reset('message');
        toast.success('Mensagem enviada.');
      },
      onError: () => toast.error('Falha ao enviar.'),
    });
  };

  const confirmDelete = () => {
    if (!deleteTarget) return;
    fetch(`/essentials/messages/${deleteTarget.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
      credentials: 'same-origin',
    })
      .then((res) => {
        if (res.ok || res.status === 302 || res.status === 409) {
          setMessages((prev) => prev.filter((m) => m.id !== deleteTarget.id));
          toast.success('Mensagem removida.');
        } else {
          toast.error('Falha ao remover.');
        }
      })
      .catch(() => toast.error('Falha ao remover.'))
      .finally(() => setDeleteTarget(null));
  };

  return (
    <AppShell
      title="Mensagens"
      breadcrumb={[{ label: 'Essentials' }, { label: 'Mensagens' }]}
    >
      <div className="mx-auto max-w-4xl p-6">
        <Card className="flex flex-col h-[calc(100vh-12rem)]">
          <CardHeader className="border-b border-border">
            <CardTitle className="flex items-center gap-2">
              <MessageCircle size={18} /> Mural de mensagens
            </CardTitle>
          </CardHeader>

          <CardContent className="flex-1 overflow-y-auto p-4" ref={scrollRef}>
            {!can.view ? (
              <div className="py-8 text-center text-sm text-muted-foreground">
                Você não tem permissão para ver mensagens.
              </div>
            ) : messages.length === 0 ? (
              <div className="py-12 text-center text-sm text-muted-foreground">
                Ainda não há mensagens. Seja o primeiro a escrever.
              </div>
            ) : (
              <ul className="space-y-3">
                {messages.map((m) => {
                  const mine = m.user_id === me;
                  return (
                    <li key={m.id} className={`flex gap-2 items-start ${mine ? 'flex-row-reverse' : ''}`}>
                      <div className={`flex-shrink-0 size-9 rounded-full flex items-center justify-center text-xs font-semibold ${mine ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground'}`}>
                        {initials(m.sender_name)}
                      </div>
                      <div className={`flex-1 max-w-md ${mine ? 'text-right' : ''}`}>
                        <div className="text-xs text-muted-foreground mb-1">
                          <strong>{mine ? 'Você' : m.sender_name}</strong>
                          {m.location_id && locationName[m.location_id] && (
                            <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-muted">
                              {locationName[m.location_id]}
                            </span>
                          )}
                          <span className="ml-2">{m.created_at_human}</span>
                          {mine && (
                            <button
                              type="button"
                              onClick={() => setDeleteTarget(m)}
                              className="ml-2 text-destructive/70 hover:text-destructive"
                              title="Remover"
                            >
                              <Trash2 size={12} />
                            </button>
                          )}
                        </div>
                        <div
                          className={`inline-block px-3 py-2 rounded-lg text-sm max-w-full break-words ${
                            mine ? 'bg-primary text-primary-foreground' : 'bg-muted'
                          }`}
                          dangerouslySetInnerHTML={{ __html: m.message }}
                        />
                      </div>
                    </li>
                  );
                })}
              </ul>
            )}
          </CardContent>

          {can.create && (
            <div className="border-t border-border p-3">
              <form onSubmit={submit} className="flex gap-2 items-end">
                <div className="flex-1">
                  <Textarea
                    rows={2}
                    value={form.data.message}
                    onChange={(e) => form.setData('message', e.target.value)}
                    placeholder="Digite sua mensagem…"
                    required
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        submit(e as unknown as FormEvent);
                      }
                    }}
                  />
                  {form.errors.message && (
                    <p className="text-xs text-destructive mt-1">{form.errors.message}</p>
                  )}
                </div>
                {locations.length > 0 && (
                  <Select
                    value={form.data.location_id ? String(form.data.location_id) : 'ALL'}
                    onValueChange={(v) =>
                      form.setData('location_id', v === 'ALL' ? null : Number(v))
                    }
                  >
                    <SelectTrigger className="w-40">
                      <SelectValue placeholder="Todas as lojas" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="ALL">Todas as lojas</SelectItem>
                      {locations.map((l) => (
                        <SelectItem key={l.id} value={String(l.id)}>{l.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
                <Button type="submit" disabled={form.processing || !form.data.message.trim()} className="gap-1.5">
                  <Send size={14} /> Enviar
                </Button>
              </form>
            </div>
          )}
        </Card>
      </div>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover mensagem?</AlertDialogTitle>
            <AlertDialogDescription>Essa ação não pode ser desfeita.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Remover
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AppShell>
  );
}
