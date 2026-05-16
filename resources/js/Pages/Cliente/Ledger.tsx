// W1-B3 Cliente/Ledger — extrato financeiro Inertia/React (MWART F3).
// Divergence ADR 0149: tabela financeira densa diferente do Index card layout.
// Backend: ContactController::getLedger() — Inertia::render dual via config('mwart.cliente_ledger.enabled')

import AppShellV2 from '@/Layouts/AppShellV2';
import { useState, type ReactNode } from 'react';
import {
  ChevronLeft,
  Download,
  FileText,
  Filter,
  Printer,
} from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';

interface LedgerLine {
  date: string;
  ref_no: string;
  description: string;
  debit: number;
  credit: number;
  balance: number;
  payment_method: string | null;
  doc_type: string;
}

interface ContactSummary {
  id: number;
  name: string;
  tax_number_masked: string | null;
  mobile: string | null;
  email: string | null;
  opening_balance: number;
  current_balance: number;
}

interface ClienteLedgerPageProps {
  contact: ContactSummary;
  ledger: {
    lines: LedgerLine[];
    total_debit: number;
    total_credit: number;
    balance: number;
  };
  filters: {
    start_date: string | null;
    end_date: string | null;
    format: string | null;
    location_id: number | null;
  };
  locations: Array<{ id: number; name: string }>;
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

export default function ClienteLedger(props: ClienteLedgerPageProps) {
  const { contact, ledger, filters } = props;
  const [startDate, setStartDate] = useState(filters.start_date ?? '');
  const [endDate, setEndDate] = useState(filters.end_date ?? '');
  const [format, setFormat] = useState(filters.format ?? 'format_1');
  const [locationId, setLocationId] = useState(filters.location_id ? String(filters.location_id) : '');

  const applyFilters = () => {
    const params = new URLSearchParams();
    params.set('contact_id', String(contact.id));
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);
    if (format) params.set('format', format);
    if (locationId) params.set('location_id', locationId);
    window.location.href = `/contacts/ledger?${params.toString()}`;
  };

  const printPdf = () => {
    const params = new URLSearchParams();
    params.set('contact_id', String(contact.id));
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);
    if (format) params.set('format', format);
    if (locationId) params.set('location_id', locationId);
    params.set('action', 'pdf');
    window.open(`/contacts/ledger?${params.toString()}`, '_blank');
  };

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-center gap-3 mb-2">
            <a
              href={`/contacts/${contact.id}`}
              className="inline-flex items-center text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <ChevronLeft size={14} className="mr-1" />
              Voltar para detalhe
            </a>
          </div>
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">Extrato — {contact.name}</h1>
              <p className="text-sm text-muted-foreground mt-1">
                {contact.tax_number_masked ?? 'Documento não informado'}
              </p>
            </div>
            <div className="flex-shrink-0 flex items-center gap-2">
              <Button variant="outline" onClick={printPdf}>
                <Printer className="mr-1.5 h-4 w-4" />
                PDF
              </Button>
              <Button variant="outline" asChild>
                <a href={`/contacts/ledger?contact_id=${contact.id}&action=download`}>
                  <Download className="mr-1.5 h-4 w-4" />
                  Excel
                </a>
              </Button>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <KpiCard label="Total débitos" value={formatBRL(ledger.total_debit)} />
            <KpiCard label="Total créditos" value={formatBRL(ledger.total_credit)} />
            <KpiCard
              label="Saldo atual"
              value={formatBRL(ledger.balance)}
              danger={ledger.balance > 0}
            />
          </div>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-7xl">
        <div className="rounded-lg border border-border bg-background p-4 mb-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="flex-1 min-w-[140px]">
              <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Data inicial</label>
              <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            </div>
            <div className="flex-1 min-w-[140px]">
              <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Data final</label>
              <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
            </div>
            <div className="flex-1 min-w-[160px]">
              <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Formato</label>
              <select
                value={format}
                onChange={(e) => setFormat(e.target.value)}
                className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
              >
                <option value="format_1">Padrão</option>
                <option value="format_2">Resumido</option>
                <option value="format_3">Detalhado por linha</option>
              </select>
            </div>
            {props.locations.length > 0 && (
              <div className="flex-1 min-w-[160px]">
                <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Local</label>
                <select
                  value={locationId}
                  onChange={(e) => setLocationId(e.target.value)}
                  className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
                >
                  <option value="">Todos</option>
                  {props.locations.map((l) => (
                    <option key={l.id} value={l.id}>{l.name}</option>
                  ))}
                </select>
              </div>
            )}
            <Button onClick={applyFilters}>
              <Filter className="mr-1.5 h-4 w-4" />
              Aplicar
            </Button>
          </div>
        </div>

        <div className="rounded-lg border border-border bg-background overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
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
                    <tr key={i} className="border-b border-border hover:bg-muted/40">
                      <td className="px-4 py-2.5 text-xs text-muted-foreground tabular-nums">{formatDate(line.date)}</td>
                      <td className="px-4 py-2.5">
                        <span className="inline-flex items-center gap-1.5">
                          <FileText size={12} className="text-muted-foreground" />
                          <span className="font-medium text-foreground">{line.ref_no || '—'}</span>
                        </span>
                      </td>
                      <td className="px-4 py-2.5 text-foreground">{line.description}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-rose-700">
                        {line.debit > 0 ? formatBRL(line.debit) : '—'}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-emerald-700">
                        {line.credit > 0 ? formatBRL(line.credit) : '—'}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums font-medium text-foreground">
                        {formatBRL(line.balance)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

ClienteLedger.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function Th({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
        className
      }
    >
      {children}
    </th>
  );
}

function KpiCard({ label, value, danger }: { label: string; value: string; danger?: boolean }) {
  return (
    <div
      className={
        'rounded-xl border p-5 shadow-sm ' +
        (danger
          ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/30'
          : 'border-border bg-background')
      }
    >
      <div
        className={
          'text-[11px] font-semibold uppercase tracking-widest ' +
          (danger ? 'text-rose-700 dark:text-rose-400' : 'text-muted-foreground')
        }
      >
        {label}
      </div>
      <div className={'text-2xl font-semibold tabular-nums mt-2 ' + (danger ? 'text-rose-700 dark:text-rose-300' : 'text-foreground')}>
        {value}
      </div>
    </div>
  );
}
