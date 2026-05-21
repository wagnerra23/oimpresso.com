// Wave B — US-CRM-064 Tab Ledger inline (MWART F3 paridade /contacts/{id} tab ledger)
// Restrições Tier 0 (ADR 0093): backend ContactController::getLedger() filtra business_id global scope.
// Source funcional: resources/views/contact/partials/ledger_tab.blade.php (range datas + format 1/2/3 + location + PDF/email)
// Backend endpoint existente: GET /contacts/ledger?contact_id={id}&start_date&end_date&format&location_id&action=pdf
//                             POST /contacts/send-ledger (linha 186 routes/web.php)
//
// Pattern reuse: Cliente/Ledger.tsx (standalone) — versão inline simplificada pra tab.

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Calendar, Download, Filter, Mail, Printer, FileText } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

export interface LedgerLine {
  date: string | null;
  ref_no: string;
  description: string;
  debit: number;
  credit: number;
  balance: number;
  payment_method: string | null;
  doc_type: string;
}

export interface LedgerSummary {
  total_debit: number;
  total_credit: number;
  balance: number; // saldo do período
}

export interface LedgerAllTime {
  total_debit: number;
  total_credit: number;
  balance: number; // saldo total all-time
  opening_balance: number;
}

export interface LedgerTabProps {
  contactId: number;
  contactName: string;
  /** Pode vir via Inertia::defer */
  ledger?: {
    lines: LedgerLine[];
    period: LedgerSummary;
    all_time: LedgerAllTime;
  };
  locations?: Array<{ id: number; name: string }>;
  initialFilters?: {
    start_date?: string | null;
    end_date?: string | null;
    format?: 'format_1' | 'format_2' | 'format_3' | null;
    location_id?: number | null;
  };
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatDate = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(d);
};

const errorMessage = (e: unknown): string => {
  if (e instanceof Error) return e.message;
  if (typeof e === 'string') return e;
  return 'erro desconhecido';
};

export default function LedgerTab({
  contactId,
  contactName,
  ledger,
  locations = [],
  initialFilters = {},
}: LedgerTabProps) {
  const [startDate, setStartDate] = useState(initialFilters.start_date ?? '');
  const [endDate, setEndDate] = useState(initialFilters.end_date ?? '');
  const [format, setFormat] = useState<'format_1' | 'format_2' | 'format_3'>(initialFilters.format ?? 'format_1');
  const [locationId, setLocationId] = useState(initialFilters.location_id ? String(initialFilters.location_id) : '');
  const [emailModal, setEmailModal] = useState(false);
  const [emailValue, setEmailValue] = useState('');
  const [emailSending, setEmailSending] = useState(false);
  const [emailFeedback, setEmailFeedback] = useState<string | null>(null);

  const buildUrl = (action?: 'pdf' | 'download') => {
    const params = new URLSearchParams();
    params.set('contact_id', String(contactId));
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);
    if (format) params.set('format', format);
    if (locationId) params.set('location_id', locationId);
    if (action) params.set('action', action);
    return `/contacts/ledger?${params.toString()}`;
  };

  // Onda Final.F — usa Inertia router.visit em vez de window.location.href.
  // Garante SPA navigation (mantém AppShellV2) e renderiza /contacts/ledger
  // (Cliente/Ledger.tsx React, já validada em prod via Onda 1.D) com filtros aplicados.
  const applyFilters = () => {
    router.visit(buildUrl(), { preserveScroll: false });
  };

  const printPdf = () => {
    window.open(buildUrl('pdf'), '_blank');
  };

  const sendLedgerEmail = async () => {
    if (!emailValue) return;
    setEmailSending(true);
    setEmailFeedback(null);
    try {
      const csrfMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
      const csrf = csrfMeta?.content ?? '';
      const formData = new FormData();
      formData.append('contact_id', String(contactId));
      formData.append('email', emailValue);
      if (startDate) formData.append('start_date', startDate);
      if (endDate) formData.append('end_date', endDate);
      if (format) formData.append('format', format);
      if (locationId) formData.append('location_id', locationId);

      const res = await fetch('/contacts/send-ledger', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setEmailFeedback('Extrato enviado por e-mail.');
      setTimeout(() => setEmailModal(false), 1500);
    } catch (e) {
      setEmailFeedback(`Falha: ${errorMessage(e)}`);
    } finally {
      setEmailSending(false);
    }
  };

  // testids literais para grep-ability (Pest assertion strict toContain)
  const FORMAT_TESTIDS: Record<'format_1' | 'format_2' | 'format_3', string> = {
    format_1: 'ledger-format-format_1',
    format_2: 'ledger-format-format_2',
    format_3: 'ledger-format-format_3',
  };
  const FORMAT_LABELS: Record<'format_1' | 'format_2' | 'format_3', string> = {
    format_1: 'Padrão',
    format_2: 'Resumido',
    format_3: 'Detalhado',
  };

  return (
    <div className="space-y-4" data-testid="ledger-tab-root">
      {/* Filtros */}
      <div className="rounded-lg border border-border bg-background p-4">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[140px]">
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block flex items-center gap-1">
              <Calendar size={12} /> Data inicial
            </label>
            <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
          </div>
          <div className="flex-1 min-w-[140px]">
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block flex items-center gap-1">
              <Calendar size={12} /> Data final
            </label>
            <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
          </div>
          <div className="flex-1 min-w-[200px]">
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Formato</label>
            <div className="inline-flex rounded-md border border-border overflow-hidden h-9" role="group" aria-label="Formato do extrato">
              {(['format_1', 'format_2', 'format_3'] as const).map((f) => (
                <button
                  key={f}
                  type="button"
                  onClick={() => setFormat(f)}
                  className={
                    'px-3 text-xs font-medium transition-colors ' +
                    (format === f
                      ? 'bg-foreground text-background'
                      : 'bg-background text-muted-foreground hover:bg-muted/40')
                  }
                  data-testid={FORMAT_TESTIDS[f]}
                >
                  {FORMAT_LABELS[f]}
                </button>
              ))}
            </div>
          </div>
          {locations.length > 0 && (
            <div className="flex-1 min-w-[160px]">
              <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Local</label>
              <select
                value={locationId}
                onChange={(e) => setLocationId(e.target.value)}
                className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
              >
                <option value="">Todos</option>
                {locations.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.name}
                  </option>
                ))}
              </select>
            </div>
          )}
          <div className="flex items-center gap-2">
            <Button onClick={applyFilters} data-testid="ledger-apply-btn">
              <Filter className="mr-1.5 h-4 w-4" />
              Aplicar
            </Button>
            <Button variant="outline" onClick={printPdf} data-testid="ledger-pdf-btn" aria-label="Baixar PDF">
              <Printer className="mr-1.5 h-4 w-4" />
              PDF
            </Button>
            <Button variant="outline" onClick={() => setEmailModal(true)} data-testid="ledger-email-btn" aria-label="Enviar por e-mail">
              <Mail className="mr-1.5 h-4 w-4" />
              E-mail
            </Button>
          </div>
        </div>
      </div>

      {/* Resumo período + Resumo geral */}
      {ledger && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="rounded-lg border border-border bg-background p-4" data-testid="ledger-summary-period">
            <SummaryCardInner
              title="Resumo do período"
              subtitle={startDate || endDate ? `${formatDate(startDate || null)} → ${formatDate(endDate || null)}` : 'Sem filtro de data'}
              debit={ledger.period.total_debit}
              credit={ledger.period.total_credit}
              balance={ledger.period.balance}
            />
          </div>
          <div className="rounded-lg border border-border bg-background p-4" data-testid="ledger-summary-all">
            <SummaryCardInner
              title="Resumo geral (all-time)"
              subtitle={`Saldo abertura: ${formatBRL(ledger.all_time.opening_balance)}`}
              debit={ledger.all_time.total_debit}
              credit={ledger.all_time.total_credit}
              balance={ledger.all_time.balance}
            />
          </div>
        </div>
      )}

      {/* Lista de lançamentos */}
      {ledger && (
        <div className="rounded-lg border border-border bg-background overflow-hidden">
          <div className="p-3 border-b border-border bg-muted/30 text-xs text-muted-foreground flex items-center justify-between">
            <span>{ledger.lines.length} {ledger.lines.length === 1 ? 'lançamento' : 'lançamentos'}</span>
            <Button variant="ghost" size="sm" asChild>
              <a href={`/contacts/ledger?contact_id=${contactId}`} className="text-xs">
                Ver extrato completo
                <Download size={12} className="ml-1" />
              </a>
            </Button>
          </div>
          <div className="overflow-x-auto max-h-[480px]">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 sticky top-0">
                <tr className="border-b border-border">
                  <Th className="w-24">Data</Th>
                  <Th>Documento</Th>
                  <Th>Descrição</Th>
                  <Th className="text-right w-28">Débito</Th>
                  <Th className="text-right w-28">Crédito</Th>
                  <Th className="text-right w-28">Saldo</Th>
                </tr>
              </thead>
              <tbody>
                {ledger.lines.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="text-center py-12 text-muted-foreground text-xs">
                      Nenhum lançamento no período selecionado.
                    </td>
                  </tr>
                ) : (
                  ledger.lines.map((line, i) => (
                    <tr key={i} className="border-b border-border hover:bg-muted/40" data-testid={`ledger-line-${i}`}>
                      <td className="px-4 py-2.5 text-xs text-muted-foreground tabular-nums">{formatDate(line.date)}</td>
                      <td className="px-4 py-2.5">
                        <span className="inline-flex items-center gap-1.5">
                          <FileText size={12} className="text-muted-foreground" aria-hidden />
                          <span className="font-medium text-foreground">{line.ref_no || '—'}</span>
                        </span>
                      </td>
                      <td className="px-4 py-2.5 text-foreground">{line.description}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-400">
                        {line.debit > 0 ? formatBRL(line.debit) : '—'}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-400">
                        {line.credit > 0 ? formatBRL(line.credit) : '—'}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums font-medium text-foreground">{formatBRL(line.balance)}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {!ledger && <LedgerSkeleton />}

      {/* Email modal — minimal sem dialog shadcn pra evitar dependência cross-wave */}
      {emailModal && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/50 p-4"
          onClick={() => setEmailModal(false)}
          data-testid="ledger-email-modal"
        >
          <div
            className="rounded-lg border border-border bg-background p-6 w-full max-w-md shadow-lg"
            onClick={(e) => e.stopPropagation()}
          >
            <h3 className="text-base font-semibold text-foreground mb-3">Enviar extrato para {contactName}</h3>
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block">E-mail destinatário</label>
            <Input
              type="email"
              value={emailValue}
              onChange={(e) => setEmailValue(e.target.value)}
              placeholder="cliente@exemplo.com"
              autoFocus
              aria-label="E-mail destinatário"
            />
            {emailFeedback && (
              <p className={'text-xs mt-2 ' + (emailFeedback.startsWith('Falha') ? 'text-rose-700' : 'text-emerald-700')}>
                {emailFeedback}
              </p>
            )}
            <div className="flex justify-end gap-2 mt-4">
              <Button variant="outline" onClick={() => setEmailModal(false)} disabled={emailSending}>
                Cancelar
              </Button>
              <Button onClick={sendLedgerEmail} disabled={emailSending || !emailValue}>
                {emailSending ? 'Enviando…' : 'Enviar'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function SummaryCardInner({
  title,
  subtitle,
  debit,
  credit,
  balance,
}: {
  title: string;
  subtitle: string;
  debit: number;
  credit: number;
  balance: number;
}) {
  const isNegative = balance > 0; // saldo "devedor" positivo significa cliente deve
  return (
    <>
      <div className="flex items-center justify-between mb-2">
        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{title}</h4>
        <span className="text-[10px] text-muted-foreground">{subtitle}</span>
      </div>
      <div className="grid grid-cols-3 gap-2 text-sm">
        <div>
          <div className="text-[10px] uppercase text-muted-foreground">Débito</div>
          <div className="font-medium tabular-nums text-rose-700 dark:text-rose-400">{formatBRL(debit)}</div>
        </div>
        <div>
          <div className="text-[10px] uppercase text-muted-foreground">Crédito</div>
          <div className="font-medium tabular-nums text-emerald-700 dark:text-emerald-400">{formatBRL(credit)}</div>
        </div>
        <div>
          <div className="text-[10px] uppercase text-muted-foreground">Saldo</div>
          <div
            className={
              'font-semibold tabular-nums ' +
              (isNegative ? 'text-rose-700 dark:text-rose-400' : 'text-foreground')
            }
          >
            {formatBRL(balance)}
          </div>
        </div>
      </div>
    </>
  );
}

function LedgerSkeleton() {
  return (
    <div className="rounded-lg border border-border bg-background p-4 space-y-2" data-testid="ledger-tab-skeleton">
      <div className="h-4 w-1/3 bg-muted/40 rounded animate-pulse" />
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className="h-8 bg-muted/30 rounded animate-pulse" />
      ))}
    </div>
  );
}

function Th({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' + className
      }
    >
      {children}
    </th>
  );
}
