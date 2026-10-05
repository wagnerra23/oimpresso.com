// Modal "Adicionar / Editar acompanhamento" (thread Crm/07, PR-a).
// Âncora: prototipo-ui/cowork/Wagner/crm-blade.jsx → TelaAcompanhamentos(), Modal "Adicionar acompanhamento".
// Grava pelas MESMAS rotas da Blade: POST /crm/follow-ups (store) e PUT /crm/follow-ups/{id} (update).
// As duas devolvem JSON `{success, msg}` para pedido ajax; por isso é fetch, não router do Inertia.

import { useState } from 'react';
import { toast } from 'sonner';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Checkbox } from '@/Components/ui/checkbox';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Inline, Stack } from '@/Components/layout';

export interface Opcao { value: string; label: string }
export interface Valores {
  title: string; contact_id: string; status: string; start_datetime: string; end_datetime: string;
  description: string; schedule_type: string; followup_category_id: string; user_id: string[];
  allow_notification: boolean; notify_via: { sms: boolean; mail: boolean };
  notify_before: number | string | null; notify_type: string;
}
interface Listas { contatos?: Opcao[]; usuarios?: Opcao[]; status?: Opcao[]; tipos?: Opcao[]; categorias?: Opcao[]; notificar?: Opcao[] }

export const NOVO: Valores = {
  title: '', contact_id: '', status: 'scheduled', start_datetime: '', end_datetime: '', description: '',
  schedule_type: 'call', followup_category_id: '', user_id: [], allow_notification: false,
  notify_via: { sms: false, mail: true }, notify_before: 30, notify_type: 'minute',
};

export function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

export default function FormAcompanhamento({ id, inicial, opcoes, onFechar, onSalvo }: {
  id: number | null; inicial: Valores; opcoes?: Listas; onFechar: () => void; onSalvo: () => void;
}) {
  const [v, setV] = useState<Valores>(inicial);
  const [erros, setErros] = useState<Record<string, string[]>>({});
  const [salvando, setSalvando] = useState(false);
  const muda = (campo: Partial<Valores>) => setV((x) => ({ ...x, ...campo }));

  async function salvar() {
    if (!v.title.trim() || !v.contact_id) return toast.warning('Título e cliente/lead são obrigatórios.');
    setSalvando(true);
    try {
      const r = await fetch(id ? `/crm/follow-ups/${id}` : '/crm/follow-ups', {
        method: id ? 'PUT' : 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
          ...v,
          notify_via: { sms: v.notify_via.sms ? 1 : 0, mail: v.notify_via.mail ? 1 : 0 },
          allow_notification: v.allow_notification ? 1 : 0,
        }),
      });
      const json = await r.json().catch(() => ({}));
      if (r.status === 422) { setErros(json.errors ?? {}); return toast.error('Confira os campos destacados.'); }
      if (!r.ok || !json.success) return toast.error(json.msg || 'Não foi possível salvar o acompanhamento.');
      toast.success('Acompanhamento salvo.');
      onSalvo();
    } catch {
      toast.error('Falha de rede ao salvar. Tente de novo.');
    } finally {
      setSalvando(false);
    }
  }

  const campo = (rotulo: string, nome: keyof Valores, filho: React.ReactNode, span = '') => (
    <Stack gap={1} className={span}>
      <Label htmlFor={`aco-${nome}`}>{rotulo}</Label>
      {filho}
      {erros[nome] ? <span className="text-xs text-destructive">{erros[nome][0]}</span> : null}
    </Stack>
  );
  const sel = (nome: keyof Valores, lista?: Opcao[]) => (
    <Select value={(v[nome] as string) || undefined} onValueChange={(x) => muda({ [nome]: x } as Partial<Valores>)}>
      <SelectTrigger id={`aco-${nome}`}><SelectValue placeholder="Selecione" /></SelectTrigger>
      <SelectContent>{(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}</SelectContent>
    </Select>
  );

  return (
    <Dialog open onOpenChange={(aberto) => { if (!aberto) onFechar(); }}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader><DialogTitle>{id ? 'Editar acompanhamento' : 'Adicionar acompanhamento'}</DialogTitle></DialogHeader>
        <Grid cols={3} gap={3}>
          {campo('Título *', 'title', <Input id="aco-title" value={v.title} placeholder="Ligar — medidas da fachada" onChange={(e) => muda({ title: e.target.value })} />, 'col-span-2')}
          {campo('Cliente / lead *', 'contact_id', sel('contact_id', opcoes?.contatos))}
          {campo('Status', 'status', sel('status', opcoes?.status?.filter((o) => o.value !== 'none')))}
          {campo('Início *', 'start_datetime', <Input id="aco-start_datetime" type="datetime-local" value={v.start_datetime} onChange={(e) => muda({ start_datetime: e.target.value })} />)}
          {campo('Fim *', 'end_datetime', <Input id="aco-end_datetime" type="datetime-local" value={v.end_datetime} onChange={(e) => muda({ end_datetime: e.target.value })} />)}
          {campo('Descrição', 'description', <Textarea id="aco-description" value={v.description} onChange={(e) => muda({ description: e.target.value })} />, 'col-span-3')}
          {campo('Tipo de acompanhamento *', 'schedule_type', sel('schedule_type', opcoes?.tipos))}
          {campo('Categoria *', 'followup_category_id', sel('followup_category_id', opcoes?.categorias))}
          {campo('Atribuído *', 'user_id', (
            <Stack gap={1} className="max-h-32 overflow-y-auto rounded-md border p-2" id="aco-user_id">
              {(opcoes?.usuarios ?? []).map((u) => (
                <Inline key={u.value} gap={2} align="center" asChild>
                  <label className="text-sm">
                    <Checkbox checked={v.user_id.includes(u.value)} onCheckedChange={(c) => muda({ user_id: c ? [...v.user_id, u.value] : v.user_id.filter((x) => x !== u.value) })} />
                    {u.label}
                  </label>
                </Inline>
              ))}
            </Stack>
          ))}
        </Grid>
        <Inline gap={2} align="center" asChild>
          <label className="text-sm">
            <Checkbox checked={v.allow_notification} onCheckedChange={(c) => muda({ allow_notification: !!c })} />
            Enviar notificação <span className="text-muted-foreground">— sai no tempo escolhido antes do início do acompanhamento.</span>
          </label>
        </Inline>
        {v.allow_notification ? (
          <Grid cols={3} gap={3}>
            <Stack gap={1}>
              <Label>Notificar via</Label>
              <Inline gap={4}>
                {(['sms', 'mail'] as const).map((k) => (
                  <Inline key={k} gap={2} align="center" asChild>
                    <label className="text-sm">
                      <Checkbox checked={v.notify_via[k]} onCheckedChange={(c) => muda({ notify_via: { ...v.notify_via, [k]: !!c } })} />
                      {k === 'sms' ? 'SMS' : 'E-mail'}
                    </label>
                  </Inline>
                ))}
              </Inline>
            </Stack>
            {campo('Notificar antes *', 'notify_before', <Input id="aco-notify_before" type="number" min={0} value={v.notify_before ?? ''} onChange={(e) => muda({ notify_before: e.target.value })} />)}
            {campo('Unidade', 'notify_type', sel('notify_type', opcoes?.notificar))}
          </Grid>
        ) : null}
        <DialogFooter>
          <Button variant="outline" onClick={onFechar}>Fechar</Button>
          <Button onClick={salvar} disabled={salvando}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
