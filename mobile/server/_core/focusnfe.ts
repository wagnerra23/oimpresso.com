/**
 * Focus NFe integration (F3-01).
 *
 * Focus NFe (https://focusnfe.com.br) is a SaaS that abstracts SEFAZ
 * integration for NF-e (mercadoria) and NFS-e (serviços). Auth is HTTP Basic
 * with the token as username and an empty password.
 *
 * Endpoints (homologação):
 *   POST   https://homologacao.focusnfe.com.br/v2/nfe?ref=<ref>
 *   GET    https://homologacao.focusnfe.com.br/v2/nfe/<ref>
 *   DELETE https://homologacao.focusnfe.com.br/v2/nfe/<ref>
 *   GET    https://homologacao.focusnfe.com.br/v2/nfe/<ref>.pdf
 *   ... and equivalent /v2/nfse/*
 *
 * Produção troca o host para `https://api.focusnfe.com.br`.
 *
 * Graceful degradation: when FOCUS_NFE_TOKEN is missing, every helper returns
 * { ok: false, error: "Focus NFe não configurado" } and the router stores the
 * document as a "rascunho" without calling the network (same pattern used in
 * server/_core/whatsapp.ts).
 */

const ENV = process.env;

export type FocusAmbiente = "producao" | "homologacao";

export interface FocusCompany {
  razaoSocial: string | null;
  nomeFantasia?: string | null;
  cnpj: string | null;
  inscricaoEstadual?: string | null;
  inscricaoMunicipal?: string | null;
  regimeTributario?: "simples_nacional" | "lucro_presumido" | "lucro_real" | null;
  cep?: string | null;
  endereco?: string | null;
  cidade?: string | null;
  uf?: string | null;
  telefone?: string | null;
  email?: string | null;
}

export interface FocusCustomer {
  nome: string;
  tipo: "PF" | "PJ";
  documento?: string | null;
  email?: string | null;
  telefone?: string | null;
  endereco?: string | null;
}

export interface FocusItem {
  descricao: string;
  quantidade: number;
  valorUnit: number;
  /** NCM (8 dígitos) — opcional. Default genérico "00000000". */
  ncm?: string;
  /** CFOP. Default "5102" (venda dentro do estado). */
  cfop?: string;
}

export interface FocusResult<T = unknown> {
  ok: boolean;
  status?: number;
  data?: T;
  raw?: string;
  error?: string;
}

export interface FocusStatusResponse {
  status?: string;
  status_sefaz?: string;
  mensagem_sefaz?: string;
  numero?: string;
  serie?: string;
  chave_nfe?: string;
  chave_nfse?: string;
  caminho_xml_nota_fiscal?: string;
  caminho_danfe?: string;
  caminho_xml?: string;
  caminho_pdf?: string;
  ref?: string;
  erros?: Array<{ codigo?: string; mensagem?: string }>;
  [k: string]: unknown;
}

function ambiente(): FocusAmbiente {
  const v = (ENV.FOCUS_NFE_AMBIENTE ?? "homologacao").toLowerCase();
  return v === "producao" ? "producao" : "homologacao";
}

function baseUrl(): string {
  return ambiente() === "producao"
    ? "https://api.focusnfe.com.br"
    : "https://homologacao.focusnfe.com.br";
}

function authHeader(): string {
  const token = ENV.FOCUS_NFE_TOKEN ?? "";
  // HTTP Basic — token as username, blank password.
  const encoded = Buffer.from(`${token}:`).toString("base64");
  return `Basic ${encoded}`;
}

export function isFocusConfigured(): boolean {
  return !!(ENV.FOCUS_NFE_TOKEN && ENV.FOCUS_NFE_TOKEN.trim());
}

export function focusAmbiente(): FocusAmbiente {
  return ambiente();
}

function onlyDigits(s: string | null | undefined): string {
  return (s ?? "").replace(/\D/g, "");
}

async function focusFetch<T>(
  method: "GET" | "POST" | "DELETE",
  path: string,
  body?: unknown,
): Promise<FocusResult<T>> {
  if (!isFocusConfigured()) {
    return { ok: false, error: "Focus NFe não configurado" };
  }
  try {
    const res = await fetch(`${baseUrl()}${path}`, {
      method,
      headers: {
        Authorization: authHeader(),
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    const raw = await res.text();
    let parsed: unknown = undefined;
    try {
      parsed = raw ? JSON.parse(raw) : undefined;
    } catch {
      parsed = undefined;
    }
    if (!res.ok) {
      const errMsg =
        (parsed && typeof parsed === "object" && parsed !== null && "mensagem" in parsed
          ? String((parsed as { mensagem: unknown }).mensagem)
          : raw) || `HTTP ${res.status}`;
      return { ok: false, status: res.status, raw, data: parsed as T, error: errMsg };
    }
    return { ok: true, status: res.status, raw, data: parsed as T };
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : String(e);
    return { ok: false, error: msg };
  }
}

function buildNFeBody(input: {
  company: FocusCompany;
  customer: FocusCustomer;
  items: FocusItem[];
  valorTotal: number;
  naturezaOperacao?: string;
}): Record<string, unknown> {
  const { company, customer, items, valorTotal } = input;
  const cpfCnpj = onlyDigits(customer.documento);
  const isPF = customer.tipo === "PF" || cpfCnpj.length === 11;

  return {
    natureza_operacao: input.naturezaOperacao ?? "Venda de mercadoria",
    data_emissao: new Date().toISOString(),
    tipo_documento: 1,
    finalidade_emissao: 1,
    cnpj_emitente: onlyDigits(company.cnpj),
    nome_emitente: company.razaoSocial ?? "",
    nome_fantasia_emitente: company.nomeFantasia ?? undefined,
    logradouro_emitente: company.endereco ?? "",
    municipio_emitente: company.cidade ?? "",
    uf_emitente: company.uf ?? "",
    cep_emitente: onlyDigits(company.cep),
    inscricao_estadual_emitente: company.inscricaoEstadual ?? undefined,
    regime_tributario_emitente:
      company.regimeTributario === "simples_nacional"
        ? 1
        : company.regimeTributario === "lucro_presumido"
          ? 2
          : 3,
    nome_destinatario: customer.nome,
    [isPF ? "cpf_destinatario" : "cnpj_destinatario"]: cpfCnpj,
    email_destinatario: customer.email ?? undefined,
    telefone_destinatario: onlyDigits(customer.telefone) || undefined,
    logradouro_destinatario: customer.endereco ?? undefined,
    valor_produtos: valorTotal.toFixed(2),
    valor_total: valorTotal.toFixed(2),
    modalidade_frete: 9,
    items: items.map((it, idx) => ({
      numero_item: idx + 1,
      codigo_produto: `ITEM-${idx + 1}`,
      descricao: it.descricao,
      cfop: it.cfop ?? "5102",
      unidade_comercial: "UN",
      quantidade_comercial: it.quantidade.toFixed(4),
      valor_unitario_comercial: it.valorUnit.toFixed(2),
      valor_bruto: (it.quantidade * it.valorUnit).toFixed(2),
      unidade_tributavel: "UN",
      quantidade_tributavel: it.quantidade.toFixed(4),
      valor_unitario_tributavel: it.valorUnit.toFixed(2),
      ncm: it.ncm ?? "00000000",
      icms_origem: 0,
      icms_situacao_tributaria: company.regimeTributario === "simples_nacional" ? "102" : "00",
    })),
  };
}

function buildNFSeBody(input: {
  company: FocusCompany;
  customer: FocusCustomer;
  servico: string;
  valor: number;
  codigoServico?: string;
}): Record<string, unknown> {
  const { company, customer, servico, valor } = input;
  const cpfCnpj = onlyDigits(customer.documento);
  return {
    data_emissao: new Date().toISOString(),
    prestador: {
      cnpj: onlyDigits(company.cnpj),
      inscricao_municipal: company.inscricaoMunicipal ?? undefined,
      codigo_municipio: undefined, // Focus aceita inferir pelo CNPJ.
    },
    tomador: {
      cpf: cpfCnpj.length === 11 ? cpfCnpj : undefined,
      cnpj: cpfCnpj.length === 14 ? cpfCnpj : undefined,
      razao_social: customer.nome,
      email: customer.email ?? undefined,
      endereco: {
        logradouro: customer.endereco ?? undefined,
      },
    },
    servico: {
      aliquota: 0,
      discriminacao: servico,
      iss_retido: false,
      item_lista_servico: input.codigoServico ?? "14.01",
      valor_servicos: valor.toFixed(2),
    },
  };
}

export async function emitirNFe(input: {
  referenciaId: string;
  company: FocusCompany;
  customer: FocusCustomer;
  items: FocusItem[];
  valorTotal: number;
  naturezaOperacao?: string;
}): Promise<FocusResult<FocusStatusResponse>> {
  const body = buildNFeBody(input);
  return focusFetch<FocusStatusResponse>(
    "POST",
    `/v2/nfe?ref=${encodeURIComponent(input.referenciaId)}`,
    body,
  );
}

export async function emitirNFSe(input: {
  referenciaId: string;
  company: FocusCompany;
  customer: FocusCustomer;
  servico: string;
  valor: number;
  codigoServico?: string;
}): Promise<FocusResult<FocusStatusResponse>> {
  const body = buildNFSeBody(input);
  return focusFetch<FocusStatusResponse>(
    "POST",
    `/v2/nfse?ref=${encodeURIComponent(input.referenciaId)}`,
    body,
  );
}

export async function consultarStatus(
  ref: string,
  tipo: "NFe" | "NFCe" | "NFSe",
): Promise<FocusResult<FocusStatusResponse>> {
  const path = tipo === "NFSe" ? `/v2/nfse/${encodeURIComponent(ref)}` : `/v2/nfe/${encodeURIComponent(ref)}`;
  return focusFetch<FocusStatusResponse>("GET", path);
}

export async function cancelar(
  ref: string,
  tipo: "NFe" | "NFCe" | "NFSe",
  justificativa: string,
): Promise<FocusResult<FocusStatusResponse>> {
  const path = tipo === "NFSe" ? `/v2/nfse/${encodeURIComponent(ref)}` : `/v2/nfe/${encodeURIComponent(ref)}`;
  return focusFetch<FocusStatusResponse>("DELETE", path, { justificativa });
}

export async function downloadPdf(
  ref: string,
  tipo: "NFe" | "NFCe" | "NFSe",
): Promise<{ ok: boolean; buffer?: Buffer; error?: string }> {
  if (!isFocusConfigured()) return { ok: false, error: "Focus NFe não configurado" };
  const path =
    tipo === "NFSe"
      ? `/v2/nfse/${encodeURIComponent(ref)}.pdf`
      : `/v2/nfe/${encodeURIComponent(ref)}.pdf`;
  try {
    const res = await fetch(`${baseUrl()}${path}`, {
      headers: { Authorization: authHeader() },
    });
    if (!res.ok) {
      const t = await res.text().catch(() => res.statusText);
      return { ok: false, error: `HTTP ${res.status}: ${t.slice(0, 200)}` };
    }
    const arr = new Uint8Array(await res.arrayBuffer());
    return { ok: true, buffer: Buffer.from(arr) };
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : String(e);
    return { ok: false, error: msg };
  }
}

export async function downloadXml(
  ref: string,
  tipo: "NFe" | "NFCe" | "NFSe",
): Promise<{ ok: boolean; buffer?: Buffer; error?: string }> {
  if (!isFocusConfigured()) return { ok: false, error: "Focus NFe não configurado" };
  const path =
    tipo === "NFSe"
      ? `/v2/nfse/${encodeURIComponent(ref)}.xml`
      : `/v2/nfe/${encodeURIComponent(ref)}.xml`;
  try {
    const res = await fetch(`${baseUrl()}${path}`, {
      headers: { Authorization: authHeader() },
    });
    if (!res.ok) {
      const t = await res.text().catch(() => res.statusText);
      return { ok: false, error: `HTTP ${res.status}: ${t.slice(0, 200)}` };
    }
    const arr = new Uint8Array(await res.arrayBuffer());
    return { ok: true, buffer: Buffer.from(arr) };
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : String(e);
    return { ok: false, error: msg };
  }
}

/**
 * Normaliza o `status` da Focus NFe para o nosso enum interno.
 * Focus envia coisas como `processando_autorizacao`, `autorizado`,
 * `cancelado`, `erro_autorizacao`, `denegado`, etc.
 */
export function mapFocusStatus(
  s: string | undefined | null,
): "rascunho" | "processando" | "autorizado" | "cancelado" | "rejeitado" {
  if (!s) return "processando";
  const v = s.toLowerCase();
  if (v.includes("autorizado")) return "autorizado";
  if (v.includes("cancelad")) return "cancelado";
  if (v.includes("erro") || v.includes("rejeitad") || v.includes("denegad")) return "rejeitado";
  if (v.includes("processando")) return "processando";
  return "processando";
}
