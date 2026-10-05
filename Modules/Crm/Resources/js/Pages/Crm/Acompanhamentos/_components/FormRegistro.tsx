// Modal "Adicionar registro de acompanhamento" (thread Crm/07, PR-b) — o log do que aconteceu.
// Âncora: Blade crm::schedule_log.create (mesmos campos). Grava pela MESMA rota: POST /crm/follow-up-log
// (ScheduleLogController@store), que também troca o status do acompanhamento quando ele vem preenchido.

import { useState } from 'react';
import { toast } from 'sonner';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Stack } from '@/Components/layout';
import { enviar, type Listas, type Opcao } from './acompanhamento';

// O enum de crm_schedule_logs.log_type — a Blade usa a mesma lista fixa.
const TIPOS_REGISTRO: Opcao[] = [
  { value: 'call', label: 'Ligação' }, { value: 'sms', label: 'SMS' }, { value: 'meeting', label: 'Encontro' }, { value: 'email', label: 'E-mail' },
];

export default function FormRegistro({ acompanhamento, opcoes, onFechar, onSalvo }: {
  acompanhamento: { id: number; titulo: string; tipo: string | null; status: string | null }; opcoes?: Listas; onFechar: () => void; onSalvo: () => void;
}) {
  const [v, setV] = useState({
    subject: '', log_type: acompanhamento.tipo ?? '', start_datetime: '', end_datetime: '', description: '', status: acompanhamento.status ?? '',
  });
  const [salvando, setSalvando] = useState(false);
  const muda = (campo: Partial<typeof v>) => setV((x) => ({ ...x, ...campo }));

  async function salvar() {
    if (!v.subject.trim() || !v.log_type || !v.start_datetime || !v.end_datetime) {
      return toast.warning('Assunto, tipo, início e fim são obrigatórios.');
    }
    setSalvando(true);
    try {
      const r = await enviar('/crm/follow-up-log', 'POST', { ...v, schedule_id: acompanhamento.id });
      if (!r.ok || !r.json.success) return toast.error(r.json.msg || 'Não foi possível salvar o registro.');
      toast.success('Registro salvo.');
      onSalvo();
    } catch {
      toast.error('Falha de rede ao salvar. Tente de novo.');
    } finally {
      setSalvando(false);
    }
  }

  const sel = (id: string, valor: string, lista: Opcao[] | undefined, aoMudar: (x: string) => void) => (
    <Select value={valor || undefined} onValueChange={aoMudar}>
      <SelectTrigger id={id}><SelectValue placeholder="Selecione" /></SelectTrigger>
      <SelectContent>{(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}</SelectContent>
    </Select>
  );

  return (
    <Dialog open onOpenChange={(aberto) => { if (!aberto) onFechar(); }}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader><DialogTitle>Adicionar registro — {acompanhamento.titulo}</DialogTitle></DialogHeader>
        <Grid cols={3} gap={3}>
          <Stack gap={1} className="col-span-3">
            <Label htmlFor="reg-subject">Assunto *</Label>
            <Input id="reg-subject" value={v.subject} onChange={(e) => muda({ subject: e.target.value })} />
          </Stack>
          <Stack gap={1}>
            <Label htmlFor="reg-log_type">Tipo de registro *</Label>
            {sel('reg-log_type', v.log_type, TIPOS_REGISTRO, (x) => muda({ log_type: x }))}
          </Stack>
          <Stack gap={1}>
            <Label htmlFor="reg-start">Início *</Label>
            <Input id="reg-start" type="datetime-local" value={v.start_datetime} onChange={(e) => muda({ start_datetime: e.target.value })} />
          </Stack>
          <Stack gap={1}>
            <Label htmlFor="reg-end">Fim *</Label>
            <Input id="reg-end" type="datetime-local" value={v.end_datetime} onChange={(e) => muda({ end_datetime: e.target.value })} />
          </Stack>
          <Stack gap={1} className="col-span-3">
            <Label htmlFor="reg-description">Descrição</Label>
            <Textarea id="reg-description" value={v.description} onChange={(e) => muda({ description: e.target.value })} />
          </Stack>
          <Stack gap={1}>
            <Label htmlFor="reg-status">Status do acompanhamento</Label>
            {sel('reg-status', v.status, opcoes?.status?.filter((o) => o.value !== 'none'), (x) => muda({ status: x }))}
          </Stack>
        </Grid>
        <DialogFooter>
          <Button variant="outline" onClick={onFechar}>Fechar</Button>
          <Button onClick={salvar} disabled={salvando}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
