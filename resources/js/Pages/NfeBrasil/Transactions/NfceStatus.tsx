// @memcofre
//   modulo: NfeBrasil (NfceStatus)
//   stories: US-NFE-002 fase 2C (status NFC-e pós-emissão, polling SEFAZ)
//   gap-fix: board 2026-05-30 nota 38→≥70 — remove inline/oklch, Card/Badge DS,
//            ações reemitir/baixar DANFE, link de volta como Button DS
//   adrs: 0104 (MWART), 0236 (ratchet), 0093 (multi-tenant)

import { useEffect, useState, type ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import {
  ArrowLeft,
  CheckCircle2,
  Clock,
  Download,
  FileText,
  Loader2,
  RefreshCw,
  XCircle,
} from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useEmissoesPorTransaction, type Emissao } from '@/Hooks/useEmissoesPorTransaction';

interface PageProps {
  transaction_id: number;
}

type StatusVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const INTERVALO_CONSULTA_MS = 2000;
const MAX_CONSULTAS_SEM_NOTA = 30;

interface StatusView {
  label: string;
  variant: StatusVariant;
  icon: typeof CheckCircle2;
  spin?: boolean;
}

// Mapeia o enum real do hook (status) → apresentação. Sem cStat numérico
// inventado: o payload entrega `status` ('autorizada'|'rejeitada'|'denegada'|
// 'pendente'|null) — null = ainda não emitida.
function statusView(status: string | null): StatusView {
  switch (status) {
    case 'autorizada':
      return { label: 'Autorizada', variant: 'default', icon: CheckCircle2 };
    case 'rejeitada':
      return { label: 'Rejeitada', variant: 'destructive', icon: XCircle };
    case 'denegada':
      return { label: 'Denegada', variant: 'destructive', icon: XCircle };
    case 'cancelada':
      return { label: 'Cancelada', variant: 'outline', icon: XCircle };
    case 'inutilizada':
      return { label: 'Inutilizada', variant: 'outline', icon: XCircle };
    case 'pendente':
      return { label: 'Processando', variant: 'secondary', icon: Loader2, spin: true };
    default:
      return { label: 'Aguardando emissão', variant: 'secondary', icon: Clock };
  }
}

function DocumentoFiscal({
  emissao,
  transactionId,
  podeVerificar,
  aoVerificar,
}: {
  emissao: Emissao;
  transactionId: number;
  podeVerificar: boolean;
  aoVerificar: () => void;
}) {
  const view = statusView(emissao.status);
  const StatusIcon = view.icon;
  const isAuthorized = emissao.status === 'autorizada';
  const isRejected = emissao.status === 'rejeitada' || emissao.status === 'denegada';

  // Reemissão: rota real POST /nfe-brasil/transactions/{tx}/emitir (throttle 30/min).
  // Ação manual e humana (confirm), nunca disparo automático.
  const reemitir = () => {
    if (!window.confirm(`Reemitir a ${emissao.modelo_label} desta venda? A nota será reenviada à SEFAZ.`)) {
      return;
    }
    router.post(
      `/nfe-brasil/transactions/${transactionId}/emitir`,
      { modelo: emissao.modelo },
      { preserveScroll: true, onFinish: aoVerificar },
    );
  };

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
        <div className="space-y-1">
          <CardTitle className="flex items-center gap-2">
            <FileText className="size-5 text-muted-foreground" aria-hidden />
            {emissao.modelo_label} da venda #{transactionId}
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            {emissao.motivo ?? 'Consultando a situação da nota junto à SEFAZ.'}
          </p>
        </div>

        <Badge variant={view.variant} className="shrink-0">
          <StatusIcon className={view.spin ? 'animate-spin' : undefined} aria-hidden />
          {view.label}
        </Badge>
      </CardHeader>

      <CardContent className="space-y-6">
        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="space-y-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
              Situação na SEFAZ
            </dt>
            <dd className="text-sm font-semibold text-foreground">{view.label}</dd>
          </div>
          <div className="space-y-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
              Código de status (cStat)
            </dt>
            <dd className="text-sm font-semibold text-foreground">{emissao.cstat ?? '—'}</dd>
          </div>
          <div className="space-y-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
              Número / Série
            </dt>
            <dd className="text-sm font-semibold text-foreground">
              {emissao.numero} / {emissao.serie}
            </dd>
          </div>
          {isAuthorized && emissao.chave_44 ? (
            <div className="space-y-1 sm:col-span-2">
              <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Chave de acesso
              </dt>
              <dd className="break-all font-mono text-sm text-foreground">{emissao.chave_44}</dd>
            </div>
          ) : null}
        </dl>

        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={aoVerificar} disabled={!podeVerificar}>
            <RefreshCw className={podeVerificar ? undefined : 'animate-spin'} aria-hidden />
            Verificar agora
          </Button>

          {isAuthorized ? (
            <Button variant="outline" asChild>
              <a href={`/nfe-brasil/emissoes/${emissao.id}/danfe-pdf`} target="_blank" rel="noreferrer">
                <Download aria-hidden />
                Baixar DANFE
              </a>
            </Button>
          ) : null}

          {isRejected ? (
            <Button onClick={reemitir}>
              <RefreshCw aria-hidden />
              Reemitir nota
            </Button>
          ) : null}
        </div>
      </CardContent>
    </Card>
  );
}

function NfceStatus({ transaction_id }: PageProps) {
  const { emissoes, loading, error, isPolling, refetch } = useEmissoesPorTransaction(transaction_id);
  const [consultasSemNota, setConsultasSemNota] = useState(0);

  // O hook só repete a consulta enquanto há nota pendente. Logo depois da venda ainda não há
  // nota nenhuma, então a própria tela repete, no mesmo ritmo e com o mesmo teto.
  useEffect(() => {
    if (loading || emissoes.length > 0 || consultasSemNota >= MAX_CONSULTAS_SEM_NOTA) return;
    const timer = setTimeout(() => {
      setConsultasSemNota((n) => n + 1);
      refetch();
    }, INTERVALO_CONSULTA_MS);
    return () => clearTimeout(timer);
  }, [loading, emissoes.length, consultasSemNota, refetch]);

  const expirou =
    emissoes.length === 0
      ? consultasSemNota >= MAX_CONSULTAS_SEM_NOTA
      : !isPolling && emissoes.some((e) => !e.is_terminal);
  const aguardando = statusView(null);
  const IconeAguardando = aguardando.icon;

  return (
    <>
      <Head title={`Status fiscal — Venda #${transaction_id}`} />

      <div className="mx-auto w-full max-w-3xl space-y-6 p-6">
        <PageHeader
          title={`Status fiscal — Venda #${transaction_id}`}
          subtitle="Acompanhe o resultado dos documentos fiscais desta venda na SEFAZ. Esta tela atualiza sozinha até receber a resposta final."
          actions={
            <Button asChild variant="outline">
              <Link href="/sells">
                <ArrowLeft className="size-4" aria-hidden />
                Voltar para vendas
              </Link>
            </Button>
          }
        />

        {emissoes.map((emissao) => (
          <DocumentoFiscal
            key={emissao.id}
            emissao={emissao}
            transactionId={transaction_id}
            podeVerificar={!isPolling}
            aoVerificar={refetch}
          />
        ))}

        {emissoes.length === 0 ? (
          <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
              <div className="space-y-1">
                <CardTitle className="flex items-center gap-2">
                  <FileText className="size-5 text-muted-foreground" aria-hidden />
                  Nota fiscal da venda #{transaction_id}
                </CardTitle>
                <p className="text-sm text-muted-foreground">
                  {error
                    ? 'Não foi possível consultar a nota agora.'
                    : 'A nota fiscal ainda não foi emitida para esta venda.'}
                </p>
              </div>
              <Badge variant={aguardando.variant} className="shrink-0">
                <IconeAguardando aria-hidden />
                {aguardando.label}
              </Badge>
            </CardHeader>
            <CardContent>
              <Button variant="outline" onClick={() => refetch()} disabled={loading}>
                <RefreshCw className={loading ? 'animate-spin' : undefined} aria-hidden />
                Verificar agora
              </Button>
            </CardContent>
          </Card>
        ) : null}

        {expirou ? (
          <p className="text-sm text-muted-foreground">
            A consulta automática expirou. Use “Verificar agora” para tentar de novo.
          </p>
        ) : null}
      </div>
    </>
  );
}

NfceStatus.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default NfceStatus;

// ── Decisões / gaps fechados (board 2026-05-30, nota 38 → alvo ≥70) ──
// P1 Pre-Flight: removidos TODOS os style={{}} inline e oklch(...240) azul.
//   Agora Card/Badge/Button do DS + tokens. Zero cor crua.
// Affordance: link "voltar" virou <Button asChild><Link>. Ações reemitir
//   (rejeitada/denegada) e baixar DANFE (autorizada) como Button DS.
// Brand: status em <Badge variant> semântica + ícone lucide, não div ad-hoc.
// Contrato: useEmissoesPorTransaction lista todos os modelos da venda (charter, emenda [W] 2026-07-28).
//
// TODO (decisão de produto):
//  - Reemitir: confirma via window.confirm; trocar por AlertDialog DS depois.
