// @memcofre
//   modulo: NfeBrasil (NfceStatus)
//   stories: US-NFE-002 fase 2C (status NFC-e pós-emissão, polling SEFAZ)
//   gap-fix: board 2026-05-30 nota 38→≥70 — remove inline/oklch, Card/Badge DS,
//            ações reemitir/baixar DANFE, link de volta como Button DS
//   adrs: 0104 (MWART), 0236 (ratchet), 0093 (multi-tenant)

import type { ReactNode } from 'react';
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
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useNfceStatus, type NfceStatus as NfceStatusValue } from '@/Hooks/useNfceStatus';

interface PageProps {
  transaction_id: number;
}

type StatusVariant = 'default' | 'secondary' | 'destructive' | 'outline';

interface StatusView {
  label: string;
  variant: StatusVariant;
  icon: typeof CheckCircle2;
  spin?: boolean;
}

// Mapeia o enum real do hook (status) → apresentação. Sem cStat numérico
// inventado: o payload entrega `status` ('autorizada'|'rejeitada'|'denegada'|
// 'pendente'|null) — null = ainda não emitida.
function statusView(status: NfceStatusValue): StatusView {
  switch (status) {
    case 'autorizada':
      return { label: 'Autorizada', variant: 'default', icon: CheckCircle2 };
    case 'rejeitada':
      return { label: 'Rejeitada', variant: 'destructive', icon: XCircle };
    case 'denegada':
      return { label: 'Denegada', variant: 'destructive', icon: XCircle };
    case 'pendente':
      return { label: 'Processando', variant: 'secondary', icon: Loader2, spin: true };
    default:
      return { label: 'Aguardando emissão', variant: 'secondary', icon: Clock };
  }
}

function NfceStatus({ transaction_id }: PageProps) {
  const { data, isPolling, hasGivenUp, refetch } = useNfceStatus(transaction_id);

  const status = data?.status ?? null;
  const view = statusView(status);
  const StatusIcon = view.icon;

  const isAuthorized = status === 'autorizada';
  const isRejected = status === 'rejeitada' || status === 'denegada';
  const motivo =
    data?.motivo ?? 'Consultando a situação da nota junto à SEFAZ.';

  // Reemissão: rota real POST /nfe-brasil/transactions/{tx}/emitir (throttle 30/min).
  // Ação manual e humana (confirm) — não é disparo automático. Só aparece quando
  // a nota foi rejeitada/denegada. Reusa o tx_id que já temos nas props.
  const reemitir = () => {
    if (!window.confirm('Reemitir a NFC-e desta venda? A nota será reenviada à SEFAZ.')) {
      return;
    }
    router.post(
      `/nfe-brasil/transactions/${transaction_id}/emitir`,
      {},
      { preserveScroll: true, onFinish: () => refetch() },
    );
  };

  return (
    <>
      <Head title={`Status NFC-e — Venda #${transaction_id}`} />

      <div className="mx-auto w-full max-w-3xl space-y-6 p-6">
        <PageHeader
          title={`Status fiscal — Venda #${transaction_id}`}
          description="Acompanhe o resultado da emissão da NFC-e enviada à SEFAZ. Esta tela atualiza sozinha até receber a resposta final."
          action={
            <Button asChild variant="outline">
              <Link href="/sells">
                <ArrowLeft className="size-4" aria-hidden />
                Voltar para vendas
              </Link>
            </Button>
          }
        />

        <Card>
          <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
            <div className="space-y-1">
              <CardTitle className="flex items-center gap-2">
                <FileText className="size-5 text-muted-foreground" aria-hidden />
                NFC-e da venda #{transaction_id}
              </CardTitle>
              <p className="text-sm text-muted-foreground">{motivo}</p>
            </div>

            <Badge variant={view.variant} className="shrink-0">
              <StatusIcon
                className={view.spin ? 'animate-spin' : undefined}
                aria-hidden
              />
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
                <dd className="text-sm font-semibold text-foreground">
                  {data?.cstat ?? '—'}
                </dd>
              </div>
              {data?.numero ? (
                <div className="space-y-1">
                  <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Número / Série
                  </dt>
                  <dd className="text-sm font-semibold text-foreground">
                    {data.numero}
                    {data.serie ? ` / ${data.serie}` : ''}
                  </dd>
                </div>
              ) : null}
              {isAuthorized && data?.chave_44 ? (
                <div className="space-y-1 sm:col-span-2">
                  <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Chave de acesso
                  </dt>
                  <dd className="break-all font-mono text-sm text-foreground">
                    {data.chave_44}
                  </dd>
                </div>
              ) : null}
            </dl>

            {hasGivenUp && !isAuthorized && !isRejected ? (
              <p className="text-sm text-muted-foreground">
                A consulta automática expirou. Use “Verificar agora” para tentar de novo.
              </p>
            ) : null}

            {/* Ações contextuais — Button DS, não <a>/style inline */}
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" onClick={() => refetch()} disabled={isPolling}>
                <RefreshCw className={isPolling ? 'animate-spin' : undefined} aria-hidden />
                Verificar agora
              </Button>

              {isAuthorized ? (
                <Button variant="outline" asChild>
                  <a
                    href={`/nfe-brasil/transactions/${transaction_id}/danfe`}
                    target="_blank"
                    rel="noreferrer"
                  >
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
// Contrato real do hook useNfceStatus: { data, isPolling, hasGivenUp, refetch }
//   e payload.status (enum) / cstat / motivo / chave_44 / numero / serie.
//
// TODO (decisão de produto, sem alucinar API):
//  - "Baixar DANFE": rota /danfe assumida; confirmar nome real no controller.
//  - Reemitir: confirma via window.confirm; trocar por AlertDialog DS depois.
