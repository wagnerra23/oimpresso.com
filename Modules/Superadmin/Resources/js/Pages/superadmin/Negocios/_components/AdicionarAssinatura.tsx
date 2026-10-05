// "Adicionar assinatura" do drawer do negócio — substitui a Blade `add_subscription`, que abria por
// AJAX das listas Blade e ficou sem entrada quando Negócios e Assinaturas viraram Inertia (thread
// Superadmin 04). Lugar e rótulo do protótipo (`superadmin-page.jsx`, rodapé do NegocioDrawer);
// campos e regra de hoje: pacote, pago via e transação, gravando pelo `store()` do
// SuperadminSubscriptionsController. O valor NÃO é digitado: vem do pacote, no servidor.

import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';

export interface OpcoesAssinatura {
  pacotes: { id: number; nome: string; usuarios: number; locais: number }[];
  gateways: { id: string; nome: string }[];
}

function Lista({ id, valor, onChange, opcoes }: { id: string; valor: string; onChange: (v: string) => void; opcoes: { v: string; label: string }[] }) {
  return (
    <Select value={valor || undefined} onValueChange={onChange}>
      <SelectTrigger id={id} className="h-9 w-full text-xs">
        <SelectValue placeholder="Selecione" />
      </SelectTrigger>
      <SelectContent>
        {/* Radix não aceita SelectItem com valor vazio (§5 2026-06-29). */}
        {opcoes
          .filter((o) => Boolean(o.v))
          .map((o) => (
            <SelectItem key={o.v} value={o.v}>
              {o.label}
            </SelectItem>
          ))}
      </SelectContent>
    </Select>
  );
}

export function AdicionarAssinatura({
  negocio,
  opcoes,
  aberto,
  onFechar,
}: {
  negocio: { id: number; nome: string };
  opcoes: OpcoesAssinatura;
  aberto: boolean;
  onFechar: () => void;
}) {
  const form = useForm({ business_id: negocio.id, package_id: '', paid_via: '', payment_transaction_id: '' });
  const d = form.data;
  const pacote = opcoes.pacotes.find((p) => String(p.id) === d.package_id);
  const semForma = opcoes.gateways.length === 0;

  const fechar = () => {
    form.reset();
    form.clearErrors();
    onFechar();
  };

  const salvar = () => {
    if (!d.package_id || !d.paid_via || form.processing) return;
    form.post('/superadmin/superadmin-subscription', { preserveScroll: true, onSuccess: fechar });
  };

  return (
    <Dialog open={aberto} onOpenChange={(v) => !v && fechar()}>
      <DialogContent className="sm:max-w-sm" data-contract="superadmin.negocios.adicionar-assinatura">
        <DialogHeader>
          <DialogTitle>Adicionar assinatura</DialogTitle>
          <DialogDescription>
            Para {negocio.nome}. Entra aprovada na hora, com a vigência e o valor do pacote.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-3">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="aa-pacote" className="text-xs">Pacote</Label>
            <Lista id="aa-pacote" valor={d.package_id} onChange={(v) => form.setData('package_id', v)}
              opcoes={opcoes.pacotes.map((p) => ({ v: String(p.id), label: p.nome }))} />
            {form.errors.package_id ? (
              <span className="text-[11px] text-destructive">{form.errors.package_id}</span>
            ) : pacote ? (
              <span className="text-[11px] text-muted-foreground">
                {pacote.usuarios === 0 ? 'Usuários ilimitados' : `${pacote.usuarios} usuários`} ·{' '}
                {pacote.locais === 0 ? 'locais ilimitados' : `${pacote.locais} ${pacote.locais === 1 ? 'local' : 'locais'}`}
              </span>
            ) : null}
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="aa-pago" className="text-xs">Pago via</Label>
            <Lista id="aa-pago" valor={d.paid_via} onChange={(v) => form.setData('paid_via', v)}
              opcoes={opcoes.gateways.map((g) => ({ v: g.id, label: g.nome }))} />
            {form.errors.paid_via ? (
              <span className="text-[11px] text-destructive">{form.errors.paid_via}</span>
            ) : semForma ? (
              <span className="text-[11px] text-warning">Nenhuma forma de pagamento configurada nas Configurações.</span>
            ) : null}
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="aa-transacao" className="text-xs">Transação</Label>
            <Input id="aa-transacao" value={d.payment_transaction_id} placeholder="Opcional"
              onChange={(e) => form.setData('payment_transaction_id', e.target.value)} />
            {form.errors.payment_transaction_id && <span className="text-[11px] text-destructive">{form.errors.payment_transaction_id}</span>}
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={fechar} disabled={form.processing}>Cancelar</Button>
          <Button onClick={salvar} disabled={form.processing || !d.package_id || !d.paid_via}>
            {form.processing ? 'Salvando…' : 'Adicionar assinatura'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
