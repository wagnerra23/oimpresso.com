// US-NFE-MANUAL · Section Fiscal do drawer SaleSheet.
// Mostra emissões NFC-e (65) + NFe (55) com badges + ações (emitir/reenviar/PDF).
// Cancelar fica em PR separado (US-NFE-CANCEL).

import { useState } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  Download,
  ExternalLink,
  FileText,
  Loader2,
  Mail,
  Receipt,
  Send,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { FiscalStatusBadge } from '@/Components/NfeBrasil/FiscalStatusBadge';
import { useEmissoesPorTransaction, type Emissao } from '@/Hooks/useEmissoesPorTransaction';

interface Props {
  saleId: number;
  enabled?: boolean;
}

export default function FiscalSection({ saleId, enabled = true }: Props) {
  const { emissoes, loading, error, isPolling, refetch } = useEmissoesPorTransaction(saleId, { enabled });
  const [emitting, setEmitting] = useState<'55' | '65' | null>(null);
  const [actionMsg, setActionMsg] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);

  const hasNfce = emissoes.some((e) => e.modelo === '65' && e.status === 'autorizada');
  const hasNfe = emissoes.some((e) => e.modelo === '55' && e.status === 'autorizada');

  async function emitir(modelo: '55' | '65') {
    setEmitting(modelo);
    setActionMsg(null);
    try {
      const res = await fetch(`/nfe-brasil/transactions/${saleId}/emitir`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ modelo }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json?.message || json?.error || 'Falha ao emitir');
      setActionMsg({
        tone: 'success',
        text: modelo === '65' ? 'NFC-e emitida — aguarde processamento SEFAZ.' : 'NFe emitida — aguarde processamento SEFAZ.',
      });
      refetch();
    } catch (e) {
      setActionMsg({ tone: 'error', text: String((e as Error).message || e) });
    } finally {
      setEmitting(null);
    }
  }

  async function reenviarEmail(emissaoId: number) {
    setActionMsg(null);
    try {
      const res = await fetch(`/nfe-brasil/emissoes/${emissaoId}/reenviar-email`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
        credentials: 'same-origin',
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json?.message || json?.error || 'Falha ao reenviar');
      setActionMsg({ tone: 'success', text: 'DANFE reenviada por email.' });
    } catch (e) {
      setActionMsg({ tone: 'error', text: String((e as Error).message || e) });
    }
  }

  return (
    <section>
      <h3 className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2 flex items-center gap-1.5">
        <Receipt size={11} />
        Fiscal
        {isPolling && <Loader2 size={10} className="animate-spin text-muted-foreground" />}
      </h3>

      {error && (
        <div className="rounded-md border border-destructive/20 bg-destructive-soft px-3 py-2 text-xs text-destructive-fg mb-2">
          <AlertTriangle size={11} className="inline mr-1" />
          {error}
        </div>
      )}

      {actionMsg && (
        <div
          className={
            'rounded-md border px-3 py-2 text-xs mb-2 ' +
            (actionMsg.tone === 'success'
              ? 'border-success/20 bg-success-soft text-success-fg'
              : 'border-destructive/20 bg-destructive-soft text-destructive-fg')
          }
        >
          {actionMsg.tone === 'success' ? <CheckCircle2 size={11} className="inline mr-1" /> : <AlertTriangle size={11} className="inline mr-1" />}
          {actionMsg.text}
        </div>
      )}

      {/* Lista emissões existentes */}
      {emissoes.length > 0 && (
        <ul className="space-y-2 mb-3">
          {emissoes.map((em) => (
            <EmissaoRow key={em.id} emissao={em} onReenviarEmail={reenviarEmail} />
          ))}
        </ul>
      )}

      {/* Botões emitir manual — só mostra se ainda não tem emissão autorizada do modelo */}
      {!loading && (
        <div className="flex flex-wrap gap-2">
          {!hasNfce && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => emitir('65')}
              disabled={emitting !== null}
            >
              {emitting === '65' ? (
                <Loader2 size={13} className="mr-1.5 animate-spin" />
              ) : (
                <FileText size={13} className="mr-1.5" />
              )}
              Emitir NFC-e
            </Button>
          )}
          {!hasNfe && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => emitir('55')}
              disabled={emitting !== null}
            >
              {emitting === '55' ? (
                <Loader2 size={13} className="mr-1.5 animate-spin" />
              ) : (
                <Send size={13} className="mr-1.5" />
              )}
              Emitir NFe
            </Button>
          )}
        </div>
      )}

      {emissoes.length === 0 && !loading && (
        <p className="text-xs text-muted-foreground mt-2">
          Nenhuma emissão fiscal nessa venda. Clique em <strong className="text-foreground">Emitir NFC-e</strong> ou <strong className="text-foreground">NFe</strong> acima.
        </p>
      )}
    </section>
  );
}

// ─── Subcomponente — linha de emissão ────────────────────────────────────────

function EmissaoRow({
  emissao: em,
  onReenviarEmail,
}: {
  emissao: Emissao;
  onReenviarEmail: (emissaoId: number) => void;
}) {
  const isAuth = em.status === 'autorizada';
  return (
    <li className="rounded-md border border-border bg-background p-3">
      <div className="flex items-start justify-between gap-2">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap mb-1">
            <span className="font-mono text-xs font-medium text-foreground">{em.modelo_label}</span>
            <span className="text-xs text-muted-foreground tabular-nums">
              {em.serie}-{em.numero}
            </span>
            <FiscalStatusBadge
              variant="pill"
              model={em.modelo}
              status={em.status}
              numero={em.numero}
              chave={em.chave_44}
              cstat={em.cstat}
              motivo={em.motivo}
            />
          </div>
          {em.chave_44 && (
            <div className="font-mono text-[10px] text-muted-foreground break-all leading-tight">
              {em.chave_44}
            </div>
          )}
          {em.motivo && em.status !== 'autorizada' && (
            <div className="text-xs text-destructive-fg mt-1">{em.motivo}</div>
          )}
        </div>
      </div>
      {isAuth && (
        <div className="flex flex-wrap gap-2 mt-2 pt-2 border-t border-border/60">
          <Button variant="ghost" size="sm" asChild className="h-7 px-2 text-xs">
            <a href={`/nfe-brasil/emissoes/${em.id}/danfe-pdf`} target="_blank" rel="noopener noreferrer">
              <Download size={11} className="mr-1" />
              DANFE PDF
            </a>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onReenviarEmail(em.id)}
            className="h-7 px-2 text-xs"
          >
            <Mail size={11} className="mr-1" />
            Reenviar email
          </Button>
          <Button variant="ghost" size="sm" asChild className="h-7 px-2 text-xs">
            <a href={`/nfe-brasil/transactions/${em.id}/status`} target="_blank" rel="noopener noreferrer">
              <ExternalLink size={11} className="mr-1" />
              Detalhes
            </a>
          </Button>
        </div>
      )}
    </li>
  );
}

