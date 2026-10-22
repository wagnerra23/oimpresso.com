// US-NFE-MANUAL · Section Fiscal do drawer SaleSheet.
// Mostra emissões NFC-e (65) + NFe (55) com badges + ações (emitir/reenviar/PDF).
// Cancelar fica em PR separado (US-NFE-CANCEL).

import { useEffect, useState } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  Download,
  ExternalLink,
  FileText,
  Files,
  Loader2,
  Mail,
  Receipt,
  Send,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Grid, Inline } from '@/Components/layout';
import { FiscalStatusBadge } from '@/Components/NfeBrasil/FiscalStatusBadge';
import { useEmissoesPorTransaction, type Emissao } from '@/Hooks/useEmissoesPorTransaction';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';

interface Props {
  saleId: number;
  enabled?: boolean;
}

// US-NFE-069 · UC-FLX-27 — as duas partes da venda, calculadas pelo servidor (SeparacaoNotasDaVenda).
// A tela só mostra: nenhum valor é recalculado aqui (regra mestre de valor).
interface ParteNota {
  itens: { descricao: string; quantidade: number; bruto: number; codigo_servico: string | null }[];
  bruto: number;
  desconto: number;
  acrescimo: number;
  frete: number;
  total: number;
}
interface Separacao {
  separavel: boolean;
  final_total?: number;
  mercadoria?: ParteNota;
  servico?: ParteNota;
  pendentes?: number[];
}

const brl = (v: number) => `R$ ${formatDecimalPtBR(v, 2)}`;

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export default function FiscalSection({ saleId, enabled = true }: Props) {
  const { emissoes, loading, error, isPolling, refetch } = useEmissoesPorTransaction(saleId, { enabled });
  const [emitting, setEmitting] = useState<'55' | '65' | 'duas' | null>(null);
  const [separacao, setSeparacao] = useState<Separacao | null>(null);
  const [confirmarDuas, setConfirmarDuas] = useState(false);

  // Só pergunta a separação; sem permissão de NFS-e (403) ou venda de uma natureza só, o botão não aparece.
  useEffect(() => {
    if (!enabled) return;
    let vivo = true;
    fetch(`/nfe-brasil/api/transactions/${saleId}/separacao`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((j) => vivo && setSeparacao(j))
      .catch(() => vivo && setSeparacao(null));
    return () => {
      vivo = false;
    };
  }, [saleId, enabled]);
  // `acao`: saída de 1 clique que o servidor devolve com o bloqueio (R-NFE-022 · NCM padrão ausente).
  const [actionMsg, setActionMsg] = useState<{
    tone: 'success' | 'error';
    text: string;
    acao?: { label: string; url: string | null };
  } | null>(null);

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
      if (!res.ok && json?.acao?.url) {
        setActionMsg({ tone: 'error', text: json.message, acao: json.acao });
        return;
      }
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

  // NF-e da parte mercadoria primeiro (síncrona, SEFAZ); só com ela aceita, a NFS-e da parte serviço.
  async function emitirDuas() {
    setEmitting('duas');
    setActionMsg(null);
    const headers = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf(),
    };
    try {
      const nfe = await fetch(`/nfe-brasil/transactions/${saleId}/emitir`, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify({ modelo: '55', separada: true }),
      });
      const jNfe = await nfe.json();
      if (!nfe.ok) throw new Error(jNfe?.message || jNfe?.error || 'Falha ao emitir a NF-e');
      if (jNfe?.emissao?.status && !['autorizada', 'pendente', 'enviando', 'contingencia'].includes(jNfe.emissao.status)) {
        throw new Error(`NF-e não autorizada (${jNfe.emissao.motivo || jNfe.emissao.status}) — a NFS-e não foi enviada.`);
      }

      const nfse = await fetch(`/nfse/transactions/${saleId}/emitir`, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify({ separada: true }),
      });
      const jNfse = await nfse.json();
      if (!nfse.ok) throw new Error(`NF-e emitida, mas a NFS-e falhou: ${jNfse?.message || jNfse?.error || nfse.status}`);

      setConfirmarDuas(false);
      setActionMsg({ tone: 'success', text: 'NF-e emitida e NFS-e enviada para processamento.' });
      refetch();
    } catch (e) {
      setActionMsg({ tone: 'error', text: String((e as Error).message || e) });
      refetch();
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
          {actionMsg.acao?.url && (
            <a href={actionMsg.acao.url} className="ml-1 font-semibold underline" data-acao="bloqueio-emissao">
              {actionMsg.acao.label}
            </a>
          )}
        </div>
      )}

      {/* Lista emissões existentes */}
      {emissoes.length > 0 && (
        <ul className="space-y-2 mb-3" data-contract="drawer-fiscal">
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
          {!hasNfe && separacao?.separavel && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setConfirmarDuas(true)}
              disabled={emitting !== null || confirmarDuas}
              data-acao="emitir-nfe-nfse"
            >
              <Files size={13} className="mr-1.5" />
              Emitir NF-e + NFS-e
            </Button>
          )}
        </div>
      )}

      {confirmarDuas && separacao?.mercadoria && separacao?.servico && (
        <div className="mt-3 rounded-md border border-border bg-background p-3 text-xs" data-contract="fiscal-duas-notas">
          <p className="font-medium text-foreground mb-2">A venda sai em duas notas:</p>
          <Grid cols={2} gap={3}>
            <ParteResumo titulo="NF-e · mercadoria" parte={separacao.mercadoria} />
            <ParteResumo titulo="NFS-e · serviço" parte={separacao.servico} />
          </Grid>
          <p className="mt-2 text-muted-foreground tabular-nums">
            Soma das duas: <strong className="text-foreground">{brl(separacao.final_total ?? 0)}</strong> (total da venda).
            Desconto e acréscimo divididos pelo valor de cada parte; frete na NF-e.
          </p>
          {(separacao.pendentes?.length ?? 0) > 0 && (
            <p className="mt-2 text-destructive-fg">
              <AlertTriangle size={11} className="inline mr-1" />
              Há produto com natureza fiscal a decidir: o contador decide antes de emitir.
            </p>
          )}
          <Inline gap={2} className="mt-3">
            <Button
              size="sm"
              onClick={emitirDuas}
              disabled={emitting !== null || (separacao.pendentes?.length ?? 0) > 0}
            >
              {emitting === 'duas' && <Loader2 size={13} className="mr-1.5 animate-spin" />}
              Confirmar e emitir
            </Button>
            <Button variant="ghost" size="sm" onClick={() => setConfirmarDuas(false)} disabled={emitting !== null}>
              Cancelar
            </Button>
          </Inline>
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

// ─── Subcomponente — uma das duas partes (NF-e ou NFS-e) ─────────────────────

function ParteResumo({ titulo, parte }: { titulo: string; parte: ParteNota }) {
  return (
    <div className="rounded border border-border/60 p-2 tabular-nums">
      <div className="font-medium text-foreground mb-1">{titulo}</div>
      <ul className="mb-1 text-muted-foreground">
        {parte.itens.map((it, i) => (
          <li key={i} className="truncate" title={it.descricao}>
            {it.descricao} · {brl(it.bruto)}
          </li>
        ))}
      </ul>
      <Inline justify="between"><span>Bruto</span><span>{brl(parte.bruto)}</span></Inline>
      {parte.desconto > 0 && <Inline justify="between"><span>Desconto</span><span>− {brl(parte.desconto)}</span></Inline>}
      {parte.acrescimo > 0 && <Inline justify="between"><span>Acréscimo</span><span>+ {brl(parte.acrescimo)}</span></Inline>}
      {parte.frete > 0 && <Inline justify="between"><span>Frete</span><span>+ {brl(parte.frete)}</span></Inline>}
      <Inline justify="between" className="font-semibold text-foreground border-t border-border/60 mt-1 pt-1">
        <span>Total</span><span>{brl(parte.total)}</span>
      </Inline>
    </div>
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

