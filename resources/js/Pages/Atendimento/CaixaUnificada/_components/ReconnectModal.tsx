// ReconnectModal — re-parear canal via QR, in-place na Caixa (port inbox-cur "Modal Reconectar").
// Resposta ao [W]: clicar "Reconectar" mostra o QR pra re-parear (Baileys/whatsmeow/Z-API).
// Canal Meta Cloud = token, NÃO tem QR (mostra mensagem de credencial/webhook).
//
// REUSA os endpoints que já existem (zero backend novo · gate can:whatsapp.settings.manage):
//   POST atendimento.channels.connect → { ok, qr_png_data_url (PNG real), pairing_code, state }
//   GET  atendimento.channels.status  → { state }  (poll 3s até 'connected')
// O QR é o PNG REAL rasterizado pelo daemon — não a matriz fake do protótipo.

import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Loader2, RefreshCw, ScanLine, ShieldAlert } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import { Inline, Stack } from '@/Components/layout';

interface Props {
  channelId: number;
  /** Tipo/provider do canal — só 'meta_cloud'/'wa_meta' não tem QR. Default = QR (banner é Baileys/whatsmeow). */
  channelType?: string;
  label: string;
  handle?: string | null;
  onClose: () => void;
  /** Chamado quando o canal volta a 'connected' (a tela recarrega a saúde). */
  onReconnected?: () => void;
}

const csrf = () =>
  (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement | null)?.content || '';

export default function ReconnectModal({ channelId, channelType, label, handle, onClose, onReconnected }: Props) {
  // Meta Cloud usa token (sem QR). Demais (baileys/whatsmeow/zapi) re-pareiam por QR.
  const isQR = channelType !== 'meta_cloud' && channelType !== 'wa_meta';

  const [loading, setLoading] = useState(isQR);
  const [qrImage, setQrImage] = useState<string | null>(null);
  const [pairingCode, setPairingCode] = useState<string | null>(null);
  const [state, setState] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const connected = state === 'connected';

  // 1) connect → QR PNG real (só pra canais QR)
  useEffect(() => {
    if (!isQR) return;
    let alive = true;
    (async () => {
      setLoading(true); setError(null);
      try {
        const r = await fetch(route('atendimento.channels.connect', channelId), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
          credentials: 'same-origin',
        });
        const data = await r.json();
        if (!alive) return;
        if (!r.ok || !data.ok) {
          setError(data.error || 'Falha ao iniciar o re-pareamento.');
        } else {
          setQrImage(data.qr_png_data_url || null);
          setPairingCode(data.pairing_code || null);
          setState(data.state || null);
          if (!data.qr_png_data_url && !data.pairing_code && data.state !== 'connected') {
            setError(data.message || 'O canal respondeu sem QR nem código.');
          }
        }
      } catch (e) {
        if (alive) setError('Erro de rede ao falar com o canal.');
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => { alive = false; };
  }, [channelId, isQR]);

  // 2) poll status até conectar
  useEffect(() => {
    if (!isQR || connected) return;
    const t = setInterval(async () => {
      try {
        const r = await fetch(route('atendimento.channels.status', channelId), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        const data = await r.json();
        setState(data.state);
        if (data.state === 'connected') {
          setTimeout(() => { onReconnected?.(); router.reload({ only: ['unhealthyChannels', 'conversations'] }); onClose(); }, 1500);
        }
      } catch { /* engole — próximo tick tenta de novo */ }
    }, 3000);
    return () => clearInterval(t);
  }, [channelId, isQR, connected, onClose, onReconnected]);

  return (
    <Dialog open onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent className="sm:max-w-sm" data-testid="caixa-unif-reconnect-modal" data-contract="reconnect-modal">
        <DialogHeader>
          <DialogTitle>Reconectar canal</DialogTitle>
          <DialogDescription>
            {label}{handle ? <span className="font-mono"> · {handle}</span> : null}
          </DialogDescription>
        </DialogHeader>

        {!isQR ? (
          <Stack gap={2} align="center" className="py-2 text-center" data-contract="reconnect-meta">
            <span className="grid place-items-center h-10 w-10 rounded-full bg-warning-soft text-warning-fg">
              <ShieldAlert size={20} aria-hidden />
            </span>
            <b className="text-[13px] font-semibold">Canal via API oficial da Meta — sem QR</b>
            <p className="text-[12px] text-muted-foreground leading-relaxed">
              Este canal usa token da Cloud API. A queda costuma ser token expirado ou webhook fora do ar —
              verifique a credencial e o webhook na página do canal.
            </p>
          </Stack>
        ) : connected ? (
          <Stack gap={2} align="center" className="py-4 text-center" data-contract="reconnect-ok">
            <span className="grid place-items-center h-10 w-10 rounded-full bg-success-soft text-success-fg">
              <RefreshCw size={20} aria-hidden />
            </span>
            <b className="text-[13px] font-semibold text-success-fg">Canal reconectado!</b>
            <p className="text-[12px] text-muted-foreground">As mensagens novas voltam a chegar.</p>
          </Stack>
        ) : (
          <Stack gap={2} align="center" className="py-1 text-center" data-contract="reconnect-qr">
            {loading ? (
              <Inline gap={2} align="center" className="py-8 text-muted-foreground">
                <Loader2 size={16} className="animate-spin" aria-hidden /> <span className="text-[12px]">Gerando QR…</span>
              </Inline>
            ) : error ? (
              <p className="py-6 text-[12px] text-destructive-fg">{error}</p>
            ) : qrImage ? (
              <>
                <img src={qrImage} alt="QR code pra parear o WhatsApp" width={232} height={232} className="rounded-md border bg-card" />
                <Inline gap={1} align="center" className="text-[11.5px] text-muted-foreground">
                  <ScanLine size={13} aria-hidden /> WhatsApp → Aparelhos conectados → Conectar aparelho
                </Inline>
              </>
            ) : pairingCode ? (
              <Stack gap={1} align="center" className="py-4">
                <span className="text-[11px] text-muted-foreground">Código de pareamento</span>
                <b className="font-mono text-2xl tracking-[0.3em]">{pairingCode}</b>
              </Stack>
            ) : (
              <p className="py-6 text-[12px] text-muted-foreground">Sem QR disponível no momento.</p>
            )}
          </Stack>
        )}

        <Inline gap={2} align="center" justify="end" className="pt-1">
          <button
            type="button"
            onClick={() => router.visit(route('atendimento.channels.show', channelId))}
            className="mr-auto text-[11.5px] font-medium text-muted-foreground hover:text-foreground"
          >
            Ver todos os canais
          </button>
          <button type="button" onClick={onClose} className="rounded-md border px-3 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:bg-muted">
            Cancelar
          </button>
          {!connected && (
            <button
              type="button"
              onClick={() => { if (isQR) { onReconnected?.(); router.reload({ only: ['unhealthyChannels'] }); } onClose(); }}
              className="rounded-md border border-primary bg-primary px-3 py-1.5 text-[11.5px] font-semibold text-primary-foreground hover:bg-primary/90"
              data-testid="caixa-unif-reconnect-confirm"
            >
              {isQR ? 'Já escaneei' : 'Reautenticar'}
            </button>
          )}
        </Inline>
      </DialogContent>
    </Dialog>
  );
}
