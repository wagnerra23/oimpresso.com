// US-SELL-CUST-SUMMARY — P0-5 RUNBOOK paridade Sells/Create (Blade legacy ↔ Inertia).
//
// Card colapsível mostrando endereço cobrança/envio + saldo devedor + crédito
// adiantado APÓS contato selecionado. Reproduz feature do Blade legacy
// (resources/views/sell/create.blade.php:151-169) que estava AUSENTE no Inertia.
//
// Pain real:
//   - Dani (financeiro) confere CNPJ + endereço pra NFe antes de fechar venda
//   - Lara (estoque) pergunta "essa cliente já me deve?" antes de liberar caçamba
//
// Endpoint: GET /contacts/{id}/quick-info — método público novo em
// ContactController (não interfere com getCustomers/getContactDue existentes).
//
// Multi-tenant Tier 0 (ADR 0093): backend já filtra por business_id; este
// componente confia no shape retornado e não toma decisão de visibilidade.

import { useEffect, useState } from 'react';
import { ChevronDown, ChevronUp, ExternalLink, Loader2, MapPin, Phone, AlertCircle } from 'lucide-react';

const STORAGE_KEY = 'oimpresso.sells.create.customer_summary.open';

export interface ContactQuickInfo {
  id: number;
  name: string;
  supplier_business_name: string | null;
  tax_number_1: string | null;
  mobile: string | null;
  landline: string | null;
  billing_address: {
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    zip_code: string | null;
    country: string | null;
  };
  shipping_address_text: string | null;
  saldo_devedor_brl: number;
  saldo_credito_brl: number;
}

interface Props {
  contactId: number | null;
  /** Cliente walk-in id — pra suprimir card quando o user limpou pra walk-in. */
  walkInCustomerId: number;
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

function formatTaxNumber(raw: string | null): string {
  if (!raw) return '';
  const d = raw.replace(/\D/g, '');
  if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
  if (d.length === 14) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
  return raw;
}

function formatPhone(raw: string | null): string {
  if (!raw) return '';
  const d = raw.replace(/\D/g, '');
  if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
  if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return raw;
}

function buildAddressLine(addr: ContactQuickInfo['billing_address']): string {
  const parts: string[] = [];
  if (addr.address_line_1) parts.push(addr.address_line_1);
  if (addr.address_line_2) parts.push(addr.address_line_2);
  const cityState = [addr.city, addr.state].filter(Boolean).join('/');
  if (cityState) parts.push(cityState);
  if (addr.zip_code) parts.push(`CEP ${addr.zip_code}`);
  if (addr.country) parts.push(addr.country);
  return parts.join(' · ');
}

export default function ContactSelectedSummary({ contactId, walkInCustomerId }: Props) {
  const [open, setOpen] = useState<boolean>(true);
  const [info, setInfo] = useState<ContactQuickInfo | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Restore collapsed state do localStorage (per-user).
  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored === '0') setOpen(false);
    } catch {
      // localStorage indisponível — default open.
    }
  }, []);

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
    } catch {
      // silencioso.
    }
  }, [open]);

  // Fetch quick-info quando contactId mudar (e não for walk-in).
  useEffect(() => {
    if (!contactId || contactId === walkInCustomerId) {
      setInfo(null);
      setError(null);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);

    fetch(`/contacts/${contactId}/quick-info`, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
      .then(async (res) => {
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        return res.json();
      })
      .then((data: ContactQuickInfo) => {
        if (cancelled) return;
        setInfo(data);
      })
      .catch((err) => {
        if (cancelled) return;
        setError('Não foi possível carregar dados do cliente.');
        // Log defensivo — Dani veria card vazio com mensagem clara.
        // eslint-disable-next-line no-console
        console.warn('[ContactSelectedSummary] fetch failed', err);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [contactId, walkInCustomerId]);

  // Não renderiza nada quando walk-in ou sem seleção — evita ruído visual.
  if (!contactId || contactId === walkInCustomerId) return null;

  const billingLine = info ? buildAddressLine(info.billing_address) : '';
  const shippingDifferent =
    info && info.shipping_address_text && info.shipping_address_text.trim() !== '' &&
    info.shipping_address_text.trim() !== billingLine.trim();

  return (
    <div
      data-testid="contact-selected-summary"
      className="rounded-md border border-border bg-muted/30 px-4 py-3 text-sm"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1 min-w-0">
          {loading && (
            <div className="flex items-center gap-2 text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" />
              <span>Carregando dados do cliente…</span>
            </div>
          )}
          {error && !loading && (
            <div className="flex items-center gap-2 text-destructive">
              <AlertCircle className="h-4 w-4" />
              <span>{error}</span>
            </div>
          )}
          {info && !loading && (
            <>
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span className="font-semibold text-foreground truncate">
                  {info.supplier_business_name && info.supplier_business_name !== info.name
                    ? `${info.supplier_business_name} (${info.name})`
                    : info.name}
                </span>
                {info.tax_number_1 && (
                  <span className="text-xs text-muted-foreground">
                    {formatTaxNumber(info.tax_number_1)}
                  </span>
                )}
                {(info.mobile || info.landline) && (
                  <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                    <Phone className="h-3 w-3" />
                    {formatPhone(info.mobile || info.landline)}
                  </span>
                )}
              </div>
            </>
          )}
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {info && (
            <a
              href={`/contacts/${info.id}/edit`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
              aria-label="Editar cadastro do cliente em nova aba"
            >
              Editar <ExternalLink className="h-3 w-3" />
            </a>
          )}
          <button
            type="button"
            onClick={() => setOpen((v) => !v)}
            aria-label={open ? 'Recolher detalhes do cliente' : 'Expandir detalhes do cliente'}
            aria-expanded={open}
            className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-accent"
          >
            {open ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
          </button>
        </div>
      </div>

      {open && info && !loading && (
        <div className="mt-3 space-y-2">
          {billingLine && (
            <div className="flex items-start gap-2 text-xs">
              <MapPin className="h-3.5 w-3.5 mt-0.5 text-muted-foreground shrink-0" />
              <div>
                <div className="font-medium text-muted-foreground">Endereço de cobrança</div>
                <div className="text-foreground">{billingLine}</div>
              </div>
            </div>
          )}
          {shippingDifferent && (
            <div className="flex items-start gap-2 text-xs">
              <MapPin className="h-3.5 w-3.5 mt-0.5 text-muted-foreground shrink-0" />
              <div>
                <div className="font-medium text-muted-foreground">Endereço de entrega</div>
                <div className="text-foreground">{info.shipping_address_text}</div>
              </div>
            </div>
          )}
          <div className="flex flex-wrap gap-2 pt-1">
            {info.saldo_devedor_brl > 0 && (
              <span
                data-testid="badge-saldo-devedor"
                className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-rose-200"
              >
                <AlertCircle className="h-3 w-3" />
                Saldo devedor: {formatBRL(info.saldo_devedor_brl)}
              </span>
            )}
            {info.saldo_credito_brl > 0 && (
              <span
                data-testid="badge-saldo-credito"
                className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200"
              >
                Crédito disponível: {formatBRL(info.saldo_credito_brl)}
              </span>
            )}
            {info.saldo_devedor_brl <= 0 && info.saldo_credito_brl <= 0 && (
              <span className="text-xs text-muted-foreground">
                Sem pendências financeiras.
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
