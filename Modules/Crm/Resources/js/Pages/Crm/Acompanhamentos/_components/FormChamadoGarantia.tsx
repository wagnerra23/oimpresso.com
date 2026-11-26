// Janela "Abrir chamado de garantia" (US-CRM-094, fatia 3; CRM-30). Âncora: protótipo `crm-blade.jsx`
// → TelaAcompanhamentos, Modal "Abrir chamado de garantia" (mesmos campos e ordem).
// Grava em POST /crm/follow-ups/garantia: o servidor acha a venda ou a OS pelo número, só entre as
// do cliente nesta empresa, e o prazo vem da política do tipo. O protótipo diz que o responsável é
// avisado 4h antes; esse aviso é a fatia 2, que não existe, então a janela não o promete.

import { useState } from 'react';
import { toast } from 'sonner';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Stack } from '@/Components/layout';
import { enviar, type Listas, type Opcao } from './acompanhamento';
import { politicaDe, type Garantia } from './garantia';
import { useCockpit } from '@/Hooks/useCockpit';

const NENHUM = '__nenhum';

export default function FormChamadoGarantia({ opcoes, garantia, onFechar, onSalvo }: {
  opcoes?: Listas; garantia?: Garantia; onFechar: () => void; onSalvo: () => void;
}) {
  const cockpit = useCockpit();
  const [v, setV] = useState({ contact_id: '', origem: '', followup_category_id: '', fila: '', responsavel_user_id: '', relato: '' });
  const [erros, setErros] = useState<Record<string, string>>({});
  const [salvando, setSalvando] = useState(false);
  const muda = (campo: Partial<typeof v>) => setV((x) => ({ ...x, ...campo }));
  const politica = politicaDe(garantia, v.followup_category_id || null);

  async function salvar() {
    if (!v.contact_id || !v.origem.trim()) return toast.warning('Cliente e venda ou OS são obrigatórios.');
    if (v.relato.trim().length < 5) return toast.warning('Descreva o defeito.');
    setSalvando(true);
    try {
      const r = await enviar('/crm/follow-ups/garantia', 'POST', {
        contact_id: Number(v.contact_id), origem: v.origem.trim(), relato: v.relato.trim(),
        followup_category_id: v.followup_category_id ? Number(v.followup_category_id) : null,
        fila: v.fila || null, responsavel_user_id: v.responsavel_user_id ? Number(v.responsavel_user_id) : null,
      });
      if (r.status === 422) {
        setErros(Object.fromEntries(Object.entries(r.json.errors ?? {}).map(([k, m]) => [k, m[0] ?? ''])));
        return toast.error('Confira os campos destacados.');
      }
      if (!r.ok || !r.json.success) return toast.error(r.json.msg || 'Não foi possível abrir o chamado.');
      toast.success('Chamado de garantia aberto.');
      onSalvo();
    } catch {
      toast.error('Falha de rede ao abrir o chamado. Tente de novo.');
    } finally {
      setSalvando(false);
    }
  }

  const erro = (campo: string) => (erros[campo] ? <p className="m-0 text-xs text-destructive" role="alert">{erros[campo]}</p> : null);
  const sel = (id: string, valor: string, lista: Opcao[], vazio: string, aoMudar: (x: string) => void) => (
    <Select value={valor || NENHUM} onValueChange={(x) => aoMudar(x === NENHUM ? '' : x)}>
      <SelectTrigger id={id}><SelectValue /></SelectTrigger>
      <SelectContent container={cockpit}>
        <SelectItem value={NENHUM}>{vazio}</SelectItem>
        {lista.map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
      </SelectContent>
    </Select>
  );

  return (
    <Dialog open onOpenChange={(aberto) => { if (!aberto && !salvando) onFechar(); }}>
      <DialogContent container={cockpit} data-contract="crm-garantia-chamado" className="sm:max-w-2xl">
        <DialogHeader><DialogTitle>Abrir chamado de garantia</DialogTitle></DialogHeader>
        <Grid cols={3} gap={3}>
          <Stack gap={1}>
            <Label htmlFor="gar-cliente">Cliente *</Label>
            {sel('gar-cliente', v.contact_id, opcoes?.contatos ?? [], 'Selecione', (x) => muda({ contact_id: x }))}
            {erro('contact_id')}
          </Stack>
          <Stack gap={1} className="col-span-2">
            <Label htmlFor="gar-origem">Venda ou OS *</Label>
            <Input id="gar-origem" value={v.origem} placeholder="POS-2026-0471 ou OS-2026-0312" onChange={(e) => muda({ origem: e.target.value })} />
            {erro('origem') ?? erro('transaction_id') ?? erro('repair_job_sheet_id')}
          </Stack>
          <Stack gap={1}>
            <Label htmlFor="gar-tipo">Tipo *</Label>
            {sel('gar-tipo', v.followup_category_id, opcoes?.categorias ?? [], 'Padrão da empresa', (x) => muda({ followup_category_id: x }))}
          </Stack>
          <Stack gap={1}>
            <Label htmlFor="gar-fila">Fila</Label>
            {sel('gar-fila', v.fila, (garantia?.filas ?? []).map((f) => ({ value: f, label: f })),
              politica.fila ? `Da política (${politica.fila})` : 'Sem fila', (x) => muda({ fila: x }))}
          </Stack>
          <Stack gap={1}>
            <Label htmlFor="gar-responsavel">Responsável</Label>
            {sel('gar-responsavel', v.responsavel_user_id, opcoes?.usuarios ?? [],
              politica.responsavel ? `Da política (${politica.responsavel})` : 'Quem abre', (x) => muda({ responsavel_user_id: x }))}
            {erro('responsavel_user_id')}
          </Stack>
          <Stack gap={1} className="col-span-3">
            <Label htmlFor="gar-relato">Defeito relatado *</Label>
            <Textarea id="gar-relato" value={v.relato} onChange={(e) => muda({ relato: e.target.value })} />
            {erro('relato')}
          </Stack>
        </Grid>
        <p className="m-0 text-xs text-muted-foreground">Prazo: {politica.horas}h a partir da abertura ({politica.tipo}).</p>
        <DialogFooter>
          <Button variant="outline" onClick={onFechar} disabled={salvando}>Fechar</Button>
          <Button className="text-white" onClick={salvar} disabled={salvando}>{salvando ? 'Abrindo…' : 'Abrir chamado'}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
