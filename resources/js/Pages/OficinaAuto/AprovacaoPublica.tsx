// @memcofre tela=/aprovar-os/{token} module=OficinaAuto
// Rota PÚBLICA sem auth — cliente aprova/rejeita OS via WhatsApp + PIN (US-OFICINA-006)
// Charter: AprovacaoPublica.charter.md
// Multi-tenant Tier 0: token HMAC + business_id assinado (ADR 0093)

import { useState, FormEvent } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Wrench, ShieldCheck, AlertTriangle, Check, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface OsVehicle {
  plate: string;
  vehicle_type: string;
}

interface OsPayload {
  id: number;
  numero: string;
  order_type: string;
  status: string;
  entered_at: string | null;
  expected_completion: string | null;
  notes: string | null;
  valor_total: number | null;
  vehicle: OsVehicle | null;
}

interface Flash {
  type: 'success' | 'error' | 'info';
  message: string;
}

interface Props {
  erro: 'link_invalido' | null;
  mensagem: string | null;
  token: string;
  os: OsPayload | null;
  tentativasRestantes: number;
}

export default function AprovacaoPublica({ erro, mensagem, token, os, tentativasRestantes }: Props) {
  const { flash } = usePage<{ flash?: Flash }>().props;
  const [decisao, setDecisao] = useState<'aprovar' | 'rejeitar' | null>(null);

  const { data, setData, post, processing, errors, reset } = useForm({
    pin: '',
    decisao: '' as 'aprovar' | 'rejeitar' | '',
  });

  function handleSubmit(e: FormEvent, escolha: 'aprovar' | 'rejeitar') {
    e.preventDefault();
    setDecisao(escolha);
    setData('decisao', escolha);
    post(`/aprovar-os/${token}`, {
      preserveScroll: true,
      onFinish: () => reset('pin'),
    });
  }

  // ────────────────────────────────────────────────────────────
  // Empty state: token inválido/expirado
  // ────────────────────────────────────────────────────────────
  if (erro === 'link_invalido' || os === null) {
    return (
      <div className="min-h-screen bg-muted/30 flex items-center justify-center px-4 py-8">
        <Head title="Link inválido · Aprovação OS" />
        <div className="max-w-md w-full bg-card rounded-lg border shadow-sm p-6 text-center">
          <div className="inline-flex items-center justify-center size-12 rounded-full bg-destructive/10 text-destructive mb-4">
            <AlertTriangle className="size-6" />
          </div>
          <h1 className="text-lg font-semibold mb-2">Link inválido</h1>
          <p className="text-sm text-muted-foreground">
            {mensagem ?? 'Este link expirou ou é inválido. Entre em contato com a oficina pra solicitar um novo link.'}
          </p>
        </div>
      </div>
    );
  }

  const valorFormatado = os.valor_total
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(os.valor_total)
    : '—';

  const lockedOut = tentativasRestantes === 0;

  return (
    <div className="min-h-screen bg-muted/30 flex items-center justify-center px-4 py-8">
      <Head title={`Aprovação OS #${os.numero} · Oficina Auto`} />

      <div className="max-w-md w-full space-y-4">
        {/* Cabeçalho */}
        <div className="text-center">
          <div className="inline-flex items-center justify-center size-12 rounded-full bg-primary/10 text-primary mb-3">
            <Wrench className="size-6" />
          </div>
          <h1 className="text-xl font-bold">Aprovação OS #{os.numero}</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Revise as informações e digite o PIN recebido pra aprovar.
          </p>
        </div>

        {/* Card info OS */}
        <div className="bg-card rounded-lg border shadow-sm p-5 space-y-3">
          <h2 className="font-semibold text-sm uppercase text-muted-foreground tracking-wide">
            Resumo do serviço
          </h2>

          <dl className="space-y-2 text-sm">
            {os.vehicle && (
              <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Veículo</dt>
                <dd className="font-medium tabular-nums">{os.vehicle.plate}</dd>
              </div>
            )}
            <div className="flex justify-between gap-4">
              <dt className="text-muted-foreground">Tipo</dt>
              <dd className="font-medium capitalize">{os.order_type}</dd>
            </div>
            {os.entered_at && (
              <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Entrada</dt>
                <dd>{new Date(os.entered_at).toLocaleDateString('pt-BR')}</dd>
              </div>
            )}
            {os.expected_completion && (
              <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Previsão entrega</dt>
                <dd>{new Date(os.expected_completion).toLocaleDateString('pt-BR')}</dd>
              </div>
            )}
            {os.valor_total !== null && (
              <div className="flex justify-between gap-4 pt-2 border-t">
                <dt className="text-muted-foreground font-medium">Valor estimado</dt>
                <dd className="font-bold tabular-nums">{valorFormatado}</dd>
              </div>
            )}
          </dl>

          {os.notes && (
            <div className="pt-3 border-t">
              <dt className="text-xs text-muted-foreground uppercase tracking-wide mb-1">Observações</dt>
              <dd className="text-sm whitespace-pre-wrap">{os.notes}</dd>
            </div>
          )}
        </div>

        {/* Flash messages */}
        {flash && (
          <div
            className={`rounded-md border p-3 text-sm ${
              flash.type === 'success'
                ? 'bg-green-50 border-green-200 text-green-900'
                : flash.type === 'error'
                  ? 'bg-destructive/10 border-destructive/30 text-destructive'
                  : 'bg-blue-50 border-blue-200 text-blue-900'
            }`}
          >
            {flash.message}
          </div>
        )}

        {/* Form PIN + ações */}
        <div className="bg-card rounded-lg border shadow-sm p-5 space-y-4">
          <div className="flex items-center gap-2 text-sm">
            <ShieldCheck className="size-4 text-primary" />
            <span className="font-medium">Digite o PIN recebido</span>
          </div>

          <form className="space-y-3" onSubmit={(e) => e.preventDefault()}>
            <div>
              <Label htmlFor="pin" className="sr-only">
                PIN
              </Label>
              <Input
                id="pin"
                type="text"
                inputMode="numeric"
                pattern="\d{4}"
                maxLength={4}
                autoComplete="one-time-code"
                placeholder="0000"
                value={data.pin}
                onChange={(e) => setData('pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
                disabled={processing || lockedOut}
                className="text-center text-2xl tracking-[0.5em] font-mono h-14"
              />
              {errors.pin && <p className="text-xs text-destructive mt-1">{errors.pin}</p>}
              {!lockedOut && tentativasRestantes < 5 && (
                <p className="text-xs text-muted-foreground mt-1.5 text-center">
                  {tentativasRestantes} tentativa(s) restante(s)
                </p>
              )}
            </div>

            <div className="grid grid-cols-2 gap-2 pt-2">
              <Button
                type="button"
                variant="outline"
                disabled={processing || lockedOut || data.pin.length !== 4}
                onClick={(e) => handleSubmit(e as unknown as FormEvent, 'rejeitar')}
              >
                <X className="size-4 mr-1.5" />
                {processing && decisao === 'rejeitar' ? 'Enviando…' : 'Rejeitar'}
              </Button>
              <Button
                type="button"
                disabled={processing || lockedOut || data.pin.length !== 4}
                onClick={(e) => handleSubmit(e as unknown as FormEvent, 'aprovar')}
              >
                <Check className="size-4 mr-1.5" />
                {processing && decisao === 'aprovar' ? 'Enviando…' : 'Aprovar'}
              </Button>
            </div>
          </form>
        </div>

        <p className="text-xs text-center text-muted-foreground">
          Link válido por 7 dias · oimpresso.com
        </p>
      </div>
    </div>
  );
}
