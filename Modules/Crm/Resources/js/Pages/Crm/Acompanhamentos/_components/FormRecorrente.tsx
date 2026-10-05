// Modal "Acompanhamento recorrente" — adicionar e editar (thread Crm/07, PR-b).
// Âncora: Blade crm::schedule.create_recursive_follow_up (mesmos campos) e o modal do PR-a.
// Grava pelas MESMAS rotas: POST /crm/follow-ups com is_recursive=1 (store) e PUT /crm/follow-ups/{id} (update).
// A Blade não tinha "Editar" no recorrente; aqui ele usa o mesmo update do avulso.

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
import { enviar, type Listas, type Opcao, type Recorrente } from './acompanhamento';

export default function FormRecorrente({ id, inicial, opcoes, onFechar, onSalvo }: {
  id: number | null; inicial: Recorrente; opcoes?: Listas; onFechar: () => void; onSalvo: () => void;
}) {
  const [v, setV] = useState<Recorrente>(inicial);
  const [erros, setErros] = useState<Record<string, string[]>>({});
  const [salvando, setSalvando] = useState(false);
  const muda = (campo: Partial<Recorrente>) => setV((x) => ({ ...x, ...campo }));

  async function salvar() {
    if (!v.title.trim() || !v.follow_up_by_value || !v.recursion_days || !v.user_id.length) {
      return toast.warning('Título, acompanhamento por, dias e atribuídos são obrigatórios.');
    }
    setSalvando(true);
    try {
      const r = await enviar(id ? `/crm/follow-ups/${id}` : '/crm/follow-ups', id ? 'PUT' : 'POST', {
        ...v,
        ...(id ? {} : { is_recursive: 1 }),
        notify_via: { sms: v.notify_via.sms ? 1 : 0, mail: v.notify_via.mail ? 1 : 0 },
        allow_notification: v.allow_notification ? 1 : 0,
      });
      if (r.status === 422) { setErros(r.json.errors ?? {}); return toast.error('Confira os campos destacados.'); }
      if (!r.ok || !r.json.success) return toast.error(r.json.msg || 'Não foi possível salvar o acompanhamento recorrente.');
      toast.success('Acompanhamento recorrente salvo.');
      onSalvo();
    } catch {
      toast.error('Falha de rede ao salvar. Tente de novo.');
    } finally {
      setSalvando(false);
    }
  }

  const campo = (rotulo: string, nome: keyof Recorrente, filho: React.ReactNode, span = '') => (
    <Stack gap={1} className={span}>
      <Label htmlFor={`rec-${nome}`}>{rotulo}</Label>
      {filho}
      {erros[nome] ? <span className="text-xs text-destructive">{erros[nome][0]}</span> : null}
    </Stack>
  );
  const sel = (nome: keyof Recorrente, lista: Opcao[] | undefined, aoMudar?: (x: string) => void) => (
    <Select value={(v[nome] as string) || undefined} onValueChange={(x) => (aoMudar ? aoMudar(x) : muda({ [nome]: x } as Partial<Recorrente>))}>
      <SelectTrigger id={`rec-${nome}`}><SelectValue placeholder="Selecione" /></SelectTrigger>
      <SelectContent>{(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}</SelectContent>
    </Select>
  );
  // `follow_up_by` é o grupo da opção escolhida — a Blade o derivava do optgroup.
  const porValor = (x: string) => muda({ follow_up_by_value: x, follow_up_by: opcoes?.recorrencia?.find((o) => o.value === x)?.grupo ?? '' });

  return (
    <Dialog open onOpenChange={(aberto) => { if (!aberto) onFechar(); }}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader><DialogTitle>{id ? 'Editar acompanhamento recorrente' : 'Adicionar acompanhamento recorrente'}</DialogTitle></DialogHeader>
        <Grid cols={3} gap={3}>
          {campo('Categoria *', 'followup_category_id', sel('followup_category_id', opcoes?.categorias))}
          {campo('Acompanhamento por *', 'follow_up_by_value', sel('follow_up_by_value', opcoes?.recorrencia, porValor))}
          {campo('Em dias *', 'recursion_days', <Input id="rec-recursion_days" type="number" min={1} value={v.recursion_days ?? ''} onChange={(e) => muda({ recursion_days: e.target.value })} />)}
          {campo('Título *', 'title', <Input id="rec-title" value={v.title} placeholder="Cobrar faturas em aberto" onChange={(e) => muda({ title: e.target.value })} />, 'col-span-3')}
          {campo('Descrição', 'description', <Textarea id="rec-description" value={v.description} onChange={(e) => muda({ description: e.target.value })} />, 'col-span-3')}
          {campo('Status', 'status', sel('status', opcoes?.status?.filter((o) => o.value !== 'none')))}
          {campo('Tipo de acompanhamento *', 'schedule_type', sel('schedule_type', opcoes?.tipos))}
          {campo('Atribuído *', 'user_id', (
            <Stack gap={1} className="max-h-32 overflow-y-auto rounded-md border p-2" id="rec-user_id">
              {(opcoes?.usuarios ?? []).map((u) => (
                <Inline key={u.value} gap={2} align="center" asChild>
                  <label className="text-sm" htmlFor={`rec-u-${u.value}`}>
                    <Checkbox id={`rec-u-${u.value}`} checked={v.user_id.includes(u.value)} onCheckedChange={(c) => muda({ user_id: c ? [...v.user_id, u.value] : v.user_id.filter((x) => x !== u.value) })} />
                    {u.label}
                  </label>
                </Inline>
              ))}
            </Stack>
          ))}
        </Grid>
        <Inline gap={2} align="center" asChild>
          <label className="text-sm" htmlFor="rec-allow_notification">
            <Checkbox id="rec-allow_notification" checked={v.allow_notification} onCheckedChange={(c) => muda({ allow_notification: !!c })} />
            Enviar notificação
          </label>
        </Inline>
        {v.allow_notification ? (
          <Grid cols={3} gap={3}>
            <Stack gap={1}>
              <Label>Notificar via</Label>
              <Inline gap={4}>
                {(['sms', 'mail'] as const).map((k) => (
                  <Inline key={k} gap={2} align="center" asChild>
                    <label className="text-sm" htmlFor={`rec-via-${k}`}>
                      <Checkbox id={`rec-via-${k}`} checked={v.notify_via[k]} onCheckedChange={(c) => muda({ notify_via: { ...v.notify_via, [k]: !!c } })} />
                      {k === 'sms' ? 'SMS' : 'E-mail'}
                    </label>
                  </Inline>
                ))}
              </Inline>
            </Stack>
            {campo('Notificar antes *', 'notify_before', <Input id="rec-notify_before" type="number" min={0} value={v.notify_before ?? ''} onChange={(e) => muda({ notify_before: e.target.value })} />)}
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
