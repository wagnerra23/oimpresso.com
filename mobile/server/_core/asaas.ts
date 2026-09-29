/**
 * F3-03 — Asaas payment gateway integration.
 *
 * Docs: https://docs.asaas.com/
 *
 * Auth: token in `access_token` header.
 * Base URLs:
 *   sandbox     -> https://sandbox.asaas.com/api/v3
 *   production  -> https://www.asaas.com/api/v3
 *
 * The helpers below are intentionally thin wrappers — the router does the
 * persistence and tenant-scoping. If `ASAAS_API_KEY` is missing we surface
 * `{ ok: false }` so the caller can degrade gracefully (paymentLinks row
 * stored with status="falhou").
 */

export type AsaasEnv = "sandbox" | "production";

export interface AsaasStatus {
  configured: boolean;
  ambiente: AsaasEnv;
}

export function getAsaasStatus(): AsaasStatus {
  const ambiente: AsaasEnv =
    (process.env.ASAAS_AMBIENTE as AsaasEnv) === "production"
      ? "production"
      : "sandbox";
  return {
    configured: Boolean(process.env.ASAAS_API_KEY),
    ambiente,
  };
}

function getBaseUrl(): string {
  return getAsaasStatus().ambiente === "production"
    ? "https://www.asaas.com/api/v3"
    : "https://sandbox.asaas.com/api/v3";
}

async function asaasFetch(path: string, init: RequestInit = {}): Promise<any> {
  const apiKey = process.env.ASAAS_API_KEY;
  if (!apiKey) {
    throw new Error("Asaas não configurado (ASAAS_API_KEY ausente).");
  }
  const url = `${getBaseUrl()}${path}`;
  const res = await fetch(url, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      access_token: apiKey,
      "User-Agent": "mini-erp-mobile/1.0",
      ...(init.headers ?? {}),
    },
  });
  const text = await res.text();
  let body: any = null;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = { raw: text };
  }
  if (!res.ok) {
    const msg =
      body?.errors?.[0]?.description ??
      body?.message ??
      `Asaas HTTP ${res.status}`;
    const err = new Error(msg) as Error & { status?: number; body?: unknown };
    err.status = res.status;
    err.body = body;
    throw err;
  }
  return body;
}

export interface CreateCustomerInput {
  customerName: string;
  customerEmail?: string | null;
  customerDoc?: string | null;
}

/**
 * Create (or attempt to retrieve) an Asaas customer.
 *
 * For MVP we POST `/customers`; if Asaas rejects with a duplicate cpfCnpj,
 * we GET `/customers?cpfCnpj=` and return the existing id. Without a doc we
 * simply create a new customer each time — Asaas allows it.
 */
export async function createOrGetAsaasCustomer(
  input: CreateCustomerInput,
): Promise<{ asaasCustomerId: string }> {
  const payload: Record<string, unknown> = {
    name: input.customerName,
  };
  if (input.customerEmail) payload.email = input.customerEmail;
  if (input.customerDoc) payload.cpfCnpj = input.customerDoc.replace(/\D/g, "");

  try {
    const created = await asaasFetch("/customers", {
      method: "POST",
      body: JSON.stringify(payload),
    });
    return { asaasCustomerId: String(created.id) };
  } catch (err) {
    // Fallback: when the doc collides, look it up.
    if (input.customerDoc) {
      try {
        const search = await asaasFetch(
          `/customers?cpfCnpj=${encodeURIComponent(
            input.customerDoc.replace(/\D/g, ""),
          )}`,
        );
        const existing = search?.data?.[0];
        if (existing?.id) {
          return { asaasCustomerId: String(existing.id) };
        }
      } catch {
        // fall through and rethrow original
      }
    }
    throw err;
  }
}

export type AsaasBillingType =
  | "BOLETO"
  | "PIX"
  | "CREDIT_CARD"
  | "UNDEFINED";

export interface CreatePaymentInput {
  asaasCustomerId: string;
  value: number;
  /** ISO date (YYYY-MM-DD). */
  dueDate: string;
  description?: string | null;
  billingType: AsaasBillingType;
  externalReference: string;
}

export interface AsaasPayment {
  id: string;
  status: string;
  value: number;
  netValue?: number | null;
  invoiceUrl?: string | null;
  bankSlipUrl?: string | null;
  dueDate?: string | null;
  paymentDate?: string | null;
  /** Raw provider payload. */
  _raw: unknown;
}

function normalizePayment(raw: any): AsaasPayment {
  return {
    id: String(raw.id),
    status: String(raw.status ?? ""),
    value: Number(raw.value ?? 0),
    netValue: raw.netValue !== undefined ? Number(raw.netValue) : null,
    invoiceUrl: raw.invoiceUrl ?? null,
    bankSlipUrl: raw.bankSlipUrl ?? null,
    dueDate: raw.dueDate ?? null,
    paymentDate: raw.paymentDate ?? null,
    _raw: raw,
  };
}

export async function createPayment(
  input: CreatePaymentInput,
): Promise<AsaasPayment> {
  const payload = {
    customer: input.asaasCustomerId,
    billingType: input.billingType,
    value: Number(input.value.toFixed(2)),
    dueDate: input.dueDate,
    description: input.description ?? undefined,
    externalReference: input.externalReference,
  };
  const raw = await asaasFetch("/payments", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return normalizePayment(raw);
}

export async function getPayment(id: string): Promise<AsaasPayment> {
  const raw = await asaasFetch(`/payments/${encodeURIComponent(id)}`);
  return normalizePayment(raw);
}

export async function cancelPayment(
  id: string,
): Promise<{ ok: boolean; deleted: boolean }> {
  const raw = await asaasFetch(`/payments/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
  return { ok: true, deleted: Boolean(raw?.deleted ?? true) };
}

/**
 * Map an Asaas payment status to our pt-BR enum used in the `paymentLinks`
 * table. Unknown statuses default to "pendente" to avoid losing the row.
 */
export function mapAsaasStatus(
  asaas: string,
): "pendente" | "pago" | "vencido" | "cancelado" | "estornado" | "falhou" {
  switch (asaas.toUpperCase()) {
    case "RECEIVED":
    case "CONFIRMED":
    case "RECEIVED_IN_CASH":
      return "pago";
    case "OVERDUE":
      return "vencido";
    case "REFUNDED":
    case "REFUND_REQUESTED":
    case "CHARGEBACK_REQUESTED":
    case "CHARGEBACK_DISPUTE":
    case "AWAITING_CHARGEBACK_REVERSAL":
      return "estornado";
    case "DELETED":
    case "CANCELED":
    case "CANCELLED":
      return "cancelado";
    case "PENDING":
    case "AWAITING_PAYMENT":
    case "AWAITING_RISK_ANALYSIS":
      return "pendente";
    default:
      return "pendente";
  }
}

/**
 * Map an Asaas event name (PAYMENT_RECEIVED, PAYMENT_OVERDUE, ...) to our
 * enum. We accept both the event name and the embedded payment.status as
 * fallback for completeness.
 */
export function mapAsaasEvent(
  event: string,
): "pendente" | "pago" | "vencido" | "cancelado" | "estornado" | "falhou" | null {
  switch (event.toUpperCase()) {
    case "PAYMENT_RECEIVED":
    case "PAYMENT_CONFIRMED":
    case "PAYMENT_RECEIVED_IN_CASH":
      return "pago";
    case "PAYMENT_OVERDUE":
      return "vencido";
    case "PAYMENT_DELETED":
    case "PAYMENT_RESTORED": // status will be re-derived
      return "cancelado";
    case "PAYMENT_REFUNDED":
    case "PAYMENT_CHARGEBACK_REQUESTED":
    case "PAYMENT_CHARGEBACK_DISPUTE":
    case "PAYMENT_AWAITING_CHARGEBACK_REVERSAL":
      return "estornado";
    case "PAYMENT_CREATED":
    case "PAYMENT_UPDATED":
    case "PAYMENT_AWAITING_RISK_ANALYSIS":
      return "pendente";
    default:
      return null;
  }
}
