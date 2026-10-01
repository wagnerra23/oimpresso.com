// EditarRemessaSheet — drawer PT-02 (760px) que substitui o modal Blade sell.partials.edit_shipping.
// Lê   GET /sells/edit-shipping/{id} (JSON — mesmo endpoint, Accept application/json)
// Grava PUT /sells/update-shipping/{id} (endpoint existente; nenhuma rota nova).
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import type { Remessa } from '../Index';

interface Props {
  remessa: Remessa | null;
  urls: { edit: string; update: string };
  customLabels: Record<string, string>;
  onClose: () => void;
  onSalvo: () => void;
}

type Form = Record<string, string>;

const NENHUM = '__nenhum__';
const CAMPOS_TEXTO = ['delivered_to', 'shipping_details', 'shipping_address', 'shipping_note'];

function csrf(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function Campo({ rotulo, children }: { rotulo: string; children: ReactNode }) {
  return <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">{rotulo}{children}</label>;
}

export default function EditarRemessaSheet({ remessa, urls, customLabels, onClose, onSalvo }: Props) {
  const [form, setForm] = useState<Form>({});
  const [status, setStatus] = useState<Record<string, string>>({});
  const [usuarios, setUsuarios] = useState<Record<string, string>>({});
  const [carregando, setCarregando] = useState(false);
  const [salvando, setSalvando] = useState(false);
  const fechar = useRef(onClose);
  useEffect(() => { fechar.current = onClose; }, [onClose]);

  useEffect(() => {
    if (!remessa) return;
    setCarregando(true);
    setForm({});
    fetch(urls.edit + remessa.id, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => { if (!r.ok) throw new Error(String(r.status)); return r.json(); })
      .then((d) => {
        const inicial: Form = {};
        ['shipping_status', 'delivery_person', ...CAMPOS_TEXTO, ...Object.keys(customLabels)].forEach((k) => {
          inicial[k] = d[k] == null ? '' : String(d[k]);
        });
        setForm(inicial);
        setStatus(d.shipping_statuses ?? {});
        setUsuarios(d.users ?? {});
      })
      .catch(() => { toast.error('Não foi possível abrir a remessa.'); fechar.current(); })
      .finally(() => setCarregando(false));
  }, [remessa, urls.edit, customLabels]);

  const set = (k: string, v: string) => setForm((x) => ({ ...x, [k]: v }));

  const salvar = async (e: FormEvent) => {
    e.preventDefault();
    if (!remessa || salvando) return;
    setSalvando(true);
    const corpo: Record<string, string | null> = {};
    Object.entries(form).forEach(([k, v]) => { corpo[k] = v === '' ? null : v; });
    try {
      const res = await fetch(urls.update + remessa.id, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(corpo),
      });
      const json = await res.json().catch(() => null);
      if (!res.ok || !json?.success) {
        toast.error(json?.msg ?? `Falha ao salvar a remessa (HTTP ${res.status}).`);
        return;
      }
      const rotulo = status[form.shipping_status ?? ''] ?? 'sem status';
      toast.success(`Remessa de ${remessa.fatura} atualizada para “${rotulo}”.`);
      onSalvo();
    } catch {
      toast.error('Erro de rede ao salvar a remessa.');
    } finally {
      setSalvando(false);
    }
  };

  return (
    <Sheet open={remessa !== null} onOpenChange={(o) => !o && !salvando && onClose()}>
      <SheetContent side="right" className="flex w-full flex-col p-0 sm:max-w-[760px]">
        <header className="border-b border-border px-5 py-4">
          <SheetTitle>Editar remessa · {remessa?.fatura}</SheetTitle>
          <SheetDescription>{remessa?.cliente}</SheetDescription>
        </header>
        {carregando ? (
          <p className="p-6 text-sm text-muted-foreground">Carregando remessa…</p>
        ) : (
          <form onSubmit={salvar} className="flex flex-1 flex-col overflow-hidden">
            <div className="grid flex-1 gap-4 overflow-y-auto p-5 sm:grid-cols-2">
              <Campo rotulo="Status de envio">
                <Select value={form.shipping_status || NENHUM} onValueChange={(v) => set('shipping_status', v === NENHUM ? '' : v)}>
                  <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value={NENHUM}>Selecione</SelectItem>
                    {Object.entries(status).map(([k, l]) => <SafeSelectItem key={k} value={k}>{l}</SafeSelectItem>)}
                  </SelectContent>
                </Select>
              </Campo>
              <Campo rotulo="Entregador">
                <Select value={form.delivery_person || NENHUM} onValueChange={(v) => set('delivery_person', v === NENHUM ? '' : v)}>
                  <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value={NENHUM}>Sem entregador</SelectItem>
                    {Object.entries(usuarios).map(([k, l]) => <SafeSelectItem key={k} value={k}>{l}</SafeSelectItem>)}
                  </SelectContent>
                </Select>
              </Campo>
              <Campo rotulo="Entregue a">
                <Input value={form.delivered_to ?? ''} onChange={(e) => set('delivered_to', e.target.value)} />
              </Campo>
              {Object.entries(customLabels).map(([k, l]) => (
                <Campo key={k} rotulo={l}>
                  <Input value={form[k] ?? ''} onChange={(e) => set(k, e.target.value)} />
                </Campo>
              ))}
              <Campo rotulo="Detalhes de envio *">
                <Textarea required rows={4} value={form.shipping_details ?? ''} onChange={(e) => set('shipping_details', e.target.value)} />
              </Campo>
              <Campo rotulo="Endereço de entrega">
                <Textarea rows={4} value={form.shipping_address ?? ''} onChange={(e) => set('shipping_address', e.target.value)} />
              </Campo>
              <div className="sm:col-span-2">
                <Campo rotulo="Observação da alteração">
                  <Textarea rows={3} value={form.shipping_note ?? ''} onChange={(e) => set('shipping_note', e.target.value)} />
                </Campo>
              </div>
            </div>
            <footer className="flex justify-end gap-2 border-t border-border px-5 py-3">
              <Button type="button" variant="outline" onClick={onClose} disabled={salvando}>Cancelar</Button>
              <Button type="submit" disabled={salvando}>{salvando ? 'Salvando…' : 'Salvar remessa'}</Button>
            </footer>
          </form>
        )}
      </SheetContent>
    </Sheet>
  );
}
