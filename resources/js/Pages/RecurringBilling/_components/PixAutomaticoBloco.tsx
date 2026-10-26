// Pix Automático do BCB no drawer da assinatura (US-RB-020 · decisão [W] 2026-10-26).
// Forma: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx → PixAutoCard (bloco `cr-card`
// depois do fiscal). O cliente aprova uma vez no app do banco lendo o QR; depois o motor cobra cada
// fatura de 10 a 2 dias antes do vencimento. Estados = status da recorrência na API Pix do BCB.
import { useState } from 'react';
import QRCode from 'react-qr-code';
import { Copy, RefreshCw } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';

export type PixAutoStatus = 'CRIADA' | 'APROVADA' | 'REJEITADA' | 'EXPIRADA' | 'CANCELADA';

const TEXTO: Record<PixAutoStatus, string> = {
  CRIADA: 'Aguardando o cliente aprovar no app do banco.',
  APROVADA: 'Autorizado. Cada fatura é cobrada sozinha, de 10 a 2 dias antes do vencimento.',
  REJEITADA: 'O cliente recusou a autorização.',
  EXPIRADA: 'A autorização expirou sem resposta do cliente.',
  CANCELADA: 'A autorização foi cancelada.',
};

const PEDE_DE_NOVO: ReadonlyArray<PixAutoStatus | null> = [null, 'REJEITADA', 'EXPIRADA', 'CANCELADA'];

export default function PixAutomaticoBloco({
  subId,
  inicial,
}: {
  subId: number;
  inicial: { status: PixAutoStatus | null; pix_copia_cola: string | null };
}) {
  const [mandato, setMandato] = useState(inicial);
  const [pedindo, setPedindo] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [copiado, setCopiado] = useState(false);

  function pedir() {
    if (pedindo) return;
    setPedindo(true);
    setErro(null);
    fetch(`/recurring-billing/${subId}/pix-automatico`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
    })
      .then(async (r) => {
        const d = await r.json().catch(() => null);
        if (r.ok && d?.mandato) {
          setMandato({ status: d.mandato.status, pix_copia_cola: d.mandato.pix_copia_cola });
        } else {
          setErro(d?.message || 'Não foi possível pedir a autorização.');
        }
      })
      .catch(() => setErro('Não foi possível pedir a autorização.'))
      .finally(() => setPedindo(false));
  }

  function copiar() {
    if (!mandato.pix_copia_cola) return;
    navigator.clipboard?.writeText(mandato.pix_copia_cola);
    setCopiado(true);
    window.setTimeout(() => setCopiado(false), 1500);
  }

  return (
    <div className="rounded-lg border border-border p-3" data-contract="pix-automatico">
      <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Pix Automático</div>
      <p className="mt-1 text-xs text-foreground">
        {mandato.status ? TEXTO[mandato.status] : 'Cobrança automática por Pix: o cliente autoriza uma vez no app do banco.'}
      </p>

      {mandato.status === 'CRIADA' && mandato.pix_copia_cola && (
        <Inline gap={3} align="start" className="mt-3">
          {/* Preto no branco nos dois temas: QR invertido no tema escuro nem todo app de banco lê. */}
          <div className="shrink-0 rounded border border-border bg-[white] p-1.5">
            <QRCode value={mandato.pix_copia_cola} size={88} fgColor="black" bgColor="white" title="QR Code da autorização do Pix Automático" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="break-all font-mono text-[11px] text-muted-foreground">{mandato.pix_copia_cola}</div>
            <Button type="button" variant="outline" size="sm" className="mt-2" onClick={copiar}>
              <Copy size={12} />
              {copiado ? 'Copiado!' : 'Copiar Pix copia e cola'}
            </Button>
          </div>
        </Inline>
      )}

      {PEDE_DE_NOVO.includes(mandato.status) && (
        <Button type="button" variant="outline" size="sm" className="mt-3" onClick={pedir} disabled={pedindo}>
          <RefreshCw size={12} />
          {pedindo ? 'Pedindo ao banco…' : mandato.status ? 'Pedir nova autorização' : 'Pedir autorização'}
        </Button>
      )}

      {erro && <p className="mt-2 text-xs text-destructive" role="alert">{erro}</p>}
    </div>
  );
}
