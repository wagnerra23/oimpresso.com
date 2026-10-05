// Modal "Acompanhamento antecipado" — vários acompanhamentos de uma vez (thread Crm/07, PR-c2).
// Âncora: protótipo `crm-blade-forms.jsx` → AntecipadoForm (base · "Quem vai receber" · conteúdo) e a
// Blade crm::schedule.create_advance_follow_up (mesmos campos). Lê as faturas em GET /crm/get-invoices e
// os grupos em GET /crm/get-followup-groups (JSON); grava em POST /crm/follow-ups com `follow_ups`.

import { useState } from 'react';
import { toast } from 'sonner';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Checkbox } from '@/Components/ui/checkbox';
import { Select, SelectContent, SelectGroup, SelectLabel, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Inline, Stack } from '@/Components/layout';
import { enviar, type Listas, type Opcao } from './acompanhamento';

// Valores da Blade: o grupo vai em `follow_up_by`, a opção decide o filtro.
const POR = [
  { grupo: 'Status do pagamento', cat: 'payment_status', itens: [['all', 'Todos'], ['due', 'Devido'], ['partial', 'Parcial'], ['overdue', 'Vencido']] },
  { grupo: 'Pedidos', cat: 'orders', itens: [['has_transactions', 'Tem transações'], ['has_no_transactions', 'Não tem transações']] },
  { grupo: 'Contato', cat: 'contact_name', itens: [['contact_name', 'Nome']] },
] as const;
const categoria = (por: string) => POR.find((g) => g.itens.some(([v]) => v === por))?.cat ?? '';

interface Grupo { contact_id: number; cliente: string; faturas: { id: number; numero: string }[]; atribuido: string | null }

async function ler<T>(url: string, params: URLSearchParams): Promise<T> {
  const r = await fetch(`${url}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
  if (!r.ok) throw new Error(String(r.status));
  return r.json();
}

export default function FormAntecipado({ opcoes, onFechar, onSalvo }: { opcoes?: Listas; onFechar: () => void; onSalvo: () => void }) {
  const [por, setPor] = useState('');
  const [dias, setDias] = useState('');
  const [faturas, setFaturas] = useState<{ id: number; text: string }[] | null>(null);
  const [marcados, setMarcados] = useState<string[]>([]);
  const [grupos, setGrupos] = useState<(Grupo & { usuario: string })[] | null>(null);
  const [v, setV] = useState({ followup_category_id: '', title: '', description: '', status: 'scheduled', schedule_type: 'call', start_datetime: '', end_datetime: '' });
  const [erros, setErros] = useState<Record<string, string[]>>({});
  const [ocupado, setOcupado] = useState(false);
  const cat = categoria(por);
  const muda = (c: Partial<typeof v>) => setV((x) => ({ ...x, ...c }));

  async function escolherPor(x: string) {
    setPor(x); setMarcados([]); setGrupos(null); setFaturas(null);
    if (categoria(x) !== 'payment_status') return;
    try {
      setFaturas(await ler('/crm/get-invoices', new URLSearchParams({ follow_up_by: 'payment_status', payment_status: x })));
    } catch { toast.error('Não foi possível carregar as faturas.'); }
  }

  async function proximo() {
    const p = new URLSearchParams({ follow_up_by: cat === 'orders' ? por : cat });
    if (cat === 'orders') { if (!dias) return toast.warning('Insira os dias.'); p.set('days', dias); }
    else if (!marcados.length) return toast.warning(cat === 'payment_status' ? 'Selecione as faturas.' : 'Selecione os clientes.');
    marcados.forEach((m) => p.append(cat === 'payment_status' ? 'invoices[]' : 'contact_ids[]', m));
    setOcupado(true);
    try {
      const r = await ler<{ grupos: Grupo[] }>('/crm/get-followup-groups', p);
      setGrupos(r.grupos.map((g) => ({ ...g, usuario: g.atribuido ?? '' })));
    } catch { toast.error('Não foi possível montar a lista.'); } finally { setOcupado(false); }
  }

  async function salvar() {
    if (!grupos?.length) return toast.warning('Não há nenhum cliente para adicionar acompanhamento.');
    if (!v.followup_category_id || !v.title.trim() || !v.schedule_type || grupos.some((g) => !g.usuario)) {
      return toast.warning('Categoria, título, tipo e o atribuído de cada linha são obrigatórios.');
    }
    const follow_ups = Object.fromEntries(grupos.map((g) => [g.contact_id, { user_id: [g.usuario], ...(g.faturas.length ? { invoices: g.faturas.map((f) => f.id) } : {}) }]));
    setOcupado(true);
    try {
      const r = await enviar('/crm/follow-ups', 'POST', { ...v, follow_up_by: cat, in_days: cat === 'orders' ? dias : null, follow_ups, allow_notification: 0 });
      if (r.status === 422) { setErros(r.json.errors ?? {}); return toast.error('Confira os campos destacados.'); }
      if (!r.ok || !r.json.success) return toast.error(r.json.msg || 'Não foi possível salvar.');
      toast.success(`${grupos.length} acompanhamento(s) criado(s).`);
      onSalvo();
    } catch { toast.error('Falha de rede ao salvar. Tente de novo.'); } finally { setOcupado(false); }
  }

  const campo = (rotulo: string, nome: string, filho: React.ReactNode, span = '') => (
    <Stack gap={1} className={span}>
      <Label htmlFor={`ant-${nome}`}>{rotulo}</Label>
      {filho}
      {erros[nome] ? <span className="text-xs text-destructive">{erros[nome][0]}</span> : null}
    </Stack>
  );
  const sel = (nome: keyof typeof v, lista?: Opcao[]) => (
    <Select value={v[nome] || undefined} onValueChange={(x) => muda({ [nome]: x })}>
      <SelectTrigger id={`ant-${nome}`}><SelectValue placeholder="Selecione" /></SelectTrigger>
      <SelectContent>{(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}</SelectContent>
    </Select>
  );
  const escolhas = cat === 'payment_status' ? (faturas ?? []).map((f) => ({ value: String(f.id), label: f.text })) : cat === 'contact_name' ? (opcoes?.contatos ?? []) : [];

  return (
    <Dialog open onOpenChange={(aberto) => { if (!aberto) onFechar(); }}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl" data-contract="crm-antecipado">
        <DialogHeader><DialogTitle>Acompanhamento antecipado</DialogTitle></DialogHeader>
        <Grid cols={3} gap={3} data-contract="crm-antecipado-base">
          {campo('Categoria *', 'followup_category_id', sel('followup_category_id', opcoes?.categorias))}
          {campo('Acompanhamento por *', 'follow_up_by', (
            <Select value={por || undefined} onValueChange={escolherPor}>
              <SelectTrigger id="ant-follow_up_by"><SelectValue placeholder="Selecione" /></SelectTrigger>
              <SelectContent>{POR.map((g) => (
                <SelectGroup key={g.cat}><SelectLabel>{g.grupo}</SelectLabel>
                  {g.itens.map(([valor, rot]) => <SafeSelectItem key={valor} value={valor}>{rot}</SafeSelectItem>)}
                </SelectGroup>
              ))}</SelectContent>
            </Select>
          ))}
          {cat === 'orders' && campo('Em dias *', 'in_days', <Input id="ant-in_days" type="number" min={1} value={dias} onChange={(e) => { setDias(e.target.value); setGrupos(null); }} />)}
        </Grid>
        {escolhas.length > 0 && (
          <Stack gap={1} className="max-h-40 overflow-y-auto rounded-md border p-2">
            {escolhas.map((o) => (
              <Inline key={o.value} gap={2} align="center" asChild>
                <label className="text-sm" htmlFor={`ant-m-${o.value}`}>
                  <Checkbox id={`ant-m-${o.value}`} checked={marcados.includes(o.value)} onCheckedChange={(c) => { setGrupos(null); setMarcados(c ? [...marcados, o.value] : marcados.filter((x) => x !== o.value)); }} />
                  {o.label}
                </label>
              </Inline>
            ))}
          </Stack>
        )}
        {cat === 'payment_status' && faturas?.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma fatura neste status.</p>}
        {cat && <Inline gap={2} justify="end"><Button variant="outline" size="sm" onClick={proximo} disabled={ocupado}>Próximo</Button></Inline>}

        {grupos && (
          <table className="w-full text-sm" data-contract="crm-antecipado-grupo">
            <caption className="text-left text-xs text-muted-foreground">Quem vai receber · {grupos.length} acompanhamento(s)</caption>
            <thead><tr className="border-b text-left text-xs text-muted-foreground">
              <th scope="col" className="py-1">Cliente</th><th scope="col">Faturas</th><th scope="col">Atribuído</th><th scope="col"><span className="sr-only">Remover</span></th>
            </tr></thead>
            <tbody>{grupos.map((g) => (
              <tr key={g.contact_id} className="border-b">
                <td className="py-1">{g.cliente}</td>
                <td className="font-mono text-xs">{g.faturas.map((f) => f.numero).join(', ') || '—'}</td>
                <td><Select value={g.usuario || undefined} onValueChange={(x) => setGrupos(grupos.map((o) => (o.contact_id === g.contact_id ? { ...o, usuario: x } : o)))}>
                  <SelectTrigger aria-label={`Atribuído de ${g.cliente}`}><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>{(opcoes?.usuarios ?? []).map((u) => <SafeSelectItem key={u.value} value={u.value}>{u.label}</SafeSelectItem>)}</SelectContent>
                </Select></td>
                <td className="text-right"><Button variant="ghost" size="sm" onClick={() => setGrupos(grupos.filter((o) => o.contact_id !== g.contact_id))}>Remover</Button></td>
              </tr>
            ))}</tbody>
          </table>
        )}

        {grupos && (
          <Grid cols={3} gap={3} data-contract="crm-antecipado-form">
            {campo('Título *', 'title', <Input id="ant-title" value={v.title} onChange={(e) => muda({ title: e.target.value })} />, 'col-span-3')}
            <p className="col-span-3 text-xs text-muted-foreground">Etiquetas: {'{customer_name}'}, {'{customer_business_name}'}{cat === 'payment_status' ? ', {invoice_numbers}' : ''}{cat === 'orders' ? ', {days}' : ''}</p>
            {campo('Status', 'status', sel('status', opcoes?.status?.filter((o) => o.value !== 'none')))}
            {campo('Início *', 'start_datetime', <Input id="ant-start_datetime" type="datetime-local" value={v.start_datetime} onChange={(e) => muda({ start_datetime: e.target.value })} />)}
            {campo('Fim *', 'end_datetime', <Input id="ant-end_datetime" type="datetime-local" value={v.end_datetime} onChange={(e) => muda({ end_datetime: e.target.value })} />)}
            {campo('Descrição', 'description', <Textarea id="ant-description" value={v.description} onChange={(e) => muda({ description: e.target.value })} />, 'col-span-3')}
            {campo('Tipo de acompanhamento *', 'schedule_type', sel('schedule_type', opcoes?.tipos))}
          </Grid>
        )}
        <DialogFooter>
          <Button variant="outline" onClick={onFechar}>Fechar</Button>
          <Button onClick={salvar} disabled={ocupado || !grupos?.length}>{ocupado ? 'Salvando…' : 'Salvar'}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
