import type { Express, Request, Response, NextFunction } from "express";
import express from "express";
import { and, desc, eq, gte } from "drizzle-orm";
import { randomBytes, randomUUID } from "node:crypto";

import {
  customerMagicLinks,
  customerSessions,
  customers,
  ops,
  orderItems,
  pedidos,
  quoteItems,
  quotes,
  serviceOrderItems,
  serviceOrderPhotos,
  serviceOrders,
  vehicles,
  whatsappMessages,
} from "../../drizzle/schema";
import { getDb } from "../db";

/**
 * F3-05 — Portal Web do Cliente.
 *
 * Server-rendered, mobile-first HTML portal with magic-link login. The portal
 * shows ALL of the customer's pedidos / OSs / orçamentos and lets them approve
 * quotes and service orders in-line.
 *
 * Cookie name is `portal_session_id` — purposely distinct from the admin
 * `app_session_id` so the two sessions never collide on the same browser.
 *
 * Auth flow:
 *   1. Admin generates a magic link via tRPC portal.magicLinks.generate
 *   2. Customer opens GET /portal/login/:token
 *   3. We validate (token exists, not expired), insert a customerSessions
 *      row, set HttpOnly cookie, redirect to /portal
 *   4. Subsequent /portal/* requests read the cookie and load the session
 */

const PORTAL_COOKIE_NAME = "portal_session_id";
const SESSION_TTL_MS = 1000 * 60 * 60 * 24 * 7; // 7 days
const ACTIVITY_DAYS = 90;

const QUOTE_STATUS_LABELS: Record<string, string> = {
  rascunho: "Rascunho",
  enviado: "Enviado",
  aprovado: "Aprovado",
  rejeitado: "Rejeitado",
  convertido: "Convertido",
};

const PEDIDO_STATUS_LABELS: Record<string, string> = {
  novo: "Novo",
  aprovado: "Aprovado",
  execucao: "Em execução",
  entregue: "Entregue",
};

const SO_STATUS_LABELS: Record<string, string> = {
  recepcao: "Recepção",
  diagnostico: "Diagnóstico",
  orcamento: "Orçamento",
  aguardando_aprovacao: "Aguardando Aprovação",
  aguardando_pecas: "Aguardando Peças",
  em_execucao: "Em Execução",
  revisao: "Revisão",
  pronto: "Pronto",
  entregue: "Entregue",
};

const formatBRL = (n: number) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(n);

function escapeHtml(input: string | null | undefined): string {
  if (input == null) return "";
  return String(input)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
}

function layout(title: string, body: string): string {
  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>${escapeHtml(title)}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-6">
    ${body}
  </div>
</body>
</html>`;
}

function notFoundPage(message?: string): string {
  return layout(
    "Link inválido",
    `<div class="bg-white rounded-xl shadow-sm p-8 text-center">
       <h1 class="text-2xl font-bold mb-2">Link inválido ou expirado</h1>
       <p class="text-slate-600">${escapeHtml(message ?? "Solicite um novo link de acesso.")}</p>
     </div>`,
  );
}

function errorPage(message: string): string {
  return layout(
    "Erro",
    `<div class="bg-white rounded-xl shadow-sm p-8 text-center">
       <h1 class="text-2xl font-bold mb-2">Algo deu errado</h1>
       <p class="text-slate-600">${escapeHtml(message)}</p>
     </div>`,
  );
}

function parseCookies(header: string | undefined): Record<string, string> {
  if (!header) return {};
  const out: Record<string, string> = {};
  header.split(";").forEach((part) => {
    const idx = part.indexOf("=");
    if (idx < 0) return;
    const k = part.slice(0, idx).trim();
    const v = part.slice(idx + 1).trim();
    if (!k) return;
    out[k] = decodeURIComponent(v);
  });
  return out;
}

type PortalSession = {
  session: typeof customerSessions.$inferSelect;
  customer: typeof customers.$inferSelect;
};

async function loadSession(req: Request): Promise<PortalSession | null> {
  const cookies = parseCookies(req.headers.cookie);
  const tok = cookies[PORTAL_COOKIE_NAME];
  if (!tok) return null;
  const db = await getDb();
  if (!db) return null;
  const rows = await db
    .select({ session: customerSessions, customer: customers })
    .from(customerSessions)
    .leftJoin(customers, eq(customerSessions.customerId, customers.id))
    .where(eq(customerSessions.sessionToken, tok))
    .limit(1);
  const row = rows[0];
  if (!row || !row.customer) return null;
  const exp = Date.parse(row.session.expiresAt);
  if (Number.isFinite(exp) && exp < Date.now()) return null;
  return { session: row.session, customer: row.customer };
}

function setPortalCookie(res: Response, req: Request, token: string, maxAgeMs: number) {
  const hostname = req.hostname;
  const isLocal = hostname === "localhost" || hostname === "127.0.0.1";
  const proto = (req.headers["x-forwarded-proto"] as string) ?? req.protocol;
  const isHttps = proto === "https";
  const parts = [
    `${PORTAL_COOKIE_NAME}=${encodeURIComponent(token)}`,
    "Path=/",
    "HttpOnly",
    `Max-Age=${Math.floor(maxAgeMs / 1000)}`,
    `SameSite=Lax`,
  ];
  if (isHttps) parts.push("Secure");
  if (!isLocal && hostname.split(".").length >= 3) {
    parts.push(`Domain=.${hostname.split(".").slice(-2).join(".")}`);
  }
  res.setHeader("Set-Cookie", parts.join("; "));
}

function clearPortalCookie(res: Response, req: Request) {
  const hostname = req.hostname;
  const isLocal = hostname === "localhost" || hostname === "127.0.0.1";
  const parts = [
    `${PORTAL_COOKIE_NAME}=`,
    "Path=/",
    "HttpOnly",
    "Max-Age=0",
    "SameSite=Lax",
  ];
  if (!isLocal && hostname.split(".").length >= 3) {
    parts.push(`Domain=.${hostname.split(".").slice(-2).join(".")}`);
  }
  res.setHeader("Set-Cookie", parts.join("; "));
}

// ─── HTML pieces ──────────────────────────────────────────────────────────

function header(customerName: string, sub?: string): string {
  return `
    <div class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">Olá, ${escapeHtml(customerName)}</h1>
        ${sub ? `<p class="text-slate-600 text-sm mt-1">${escapeHtml(sub)}</p>` : ""}
      </div>
      <form method="POST" action="/portal/logout">
        <button type="submit" class="text-sm text-slate-500 underline">Sair</button>
      </form>
    </div>`;
}

function statBadge(label: string, value: number, color: string): string {
  return `
    <div class="bg-white rounded-xl shadow-sm p-4 flex-1">
      <div class="text-3xl font-bold ${color}">${value}</div>
      <div class="text-xs text-slate-500 mt-1 uppercase tracking-wide">${escapeHtml(label)}</div>
    </div>`;
}

function backLink(): string {
  return `<a href="/portal" class="text-sm text-blue-600 hover:underline mb-4 inline-block">&larr; Voltar ao painel</a>`;
}

// ─── Data loaders ─────────────────────────────────────────────────────────

async function loadDashboardData(customerId: string, userId: number) {
  const db = await getDb();
  if (!db) return { quotes: [], pedidos: [], oss: [] };
  const sinceIso = new Date(Date.now() - ACTIVITY_DAYS * 86400_000).toISOString();
  const [qRows, pRows, sRows] = await Promise.all([
    db
      .select()
      .from(quotes)
      .where(and(eq(quotes.customerId, customerId), eq(quotes.userId, userId)))
      .orderBy(desc(quotes.updatedAt))
      .limit(50),
    db
      .select()
      .from(pedidos)
      .where(and(eq(pedidos.customerId, customerId), eq(pedidos.userId, userId)))
      .orderBy(desc(pedidos.updatedAt))
      .limit(50),
    db
      .select()
      .from(serviceOrders)
      .where(
        and(eq(serviceOrders.customerId, customerId), eq(serviceOrders.userId, userId)),
      )
      .orderBy(desc(serviceOrders.updatedAt))
      .limit(50),
  ]);
  // Filter recent (>= sinceIso) but always include open/pending items.
  const isRecent = (updatedAt: Date | null) =>
    !updatedAt || updatedAt.toISOString() >= sinceIso;
  return {
    quotes: qRows.filter((q) => isRecent(q.updatedAt) || q.status === "enviado"),
    pedidos: pRows.filter(
      (p) => isRecent(p.updatedAt) || p.status !== "entregue",
    ),
    oss: sRows.filter(
      (s) =>
        isRecent(s.updatedAt) || (s.status !== "entregue" && s.status !== "pronto"),
    ),
  };
}

// ─── Render: dashboard ────────────────────────────────────────────────────

function renderDashboard(
  customer: typeof customers.$inferSelect,
  data: {
    quotes: (typeof quotes.$inferSelect)[];
    pedidos: (typeof pedidos.$inferSelect)[];
    oss: (typeof serviceOrders.$inferSelect)[];
  },
): string {
  const openPedidos = data.pedidos.filter((p) => p.status !== "entregue").length;
  const ossAndamento = data.oss.filter(
    (s) => s.status !== "entregue" && s.status !== "pronto",
  ).length;
  const pendingQuotes = data.quotes.filter((q) => q.status === "enviado").length;

  type Activity = {
    kind: "quote" | "pedido" | "os";
    id: string;
    title: string;
    status: string;
    statusLabel: string;
    updatedAt: number;
    valor?: number;
  };
  const activities: Activity[] = [
    ...data.quotes.map<Activity>((q) => ({
      kind: "quote",
      id: q.id,
      title: q.titulo,
      status: q.status,
      statusLabel: QUOTE_STATUS_LABELS[q.status] ?? q.status,
      updatedAt: q.updatedAt?.getTime() ?? 0,
      valor: Number(q.valorTotal),
    })),
    ...data.pedidos.map<Activity>((p) => ({
      kind: "pedido",
      id: p.id,
      title: p.produto,
      status: p.status,
      statusLabel: PEDIDO_STATUS_LABELS[p.status] ?? p.status,
      updatedAt: p.updatedAt?.getTime() ?? 0,
      valor: Number(p.valor),
    })),
    ...data.oss.map<Activity>((s) => ({
      kind: "os",
      id: s.id,
      title: `OS #${s.numero}`,
      status: s.status,
      statusLabel: SO_STATUS_LABELS[s.status] ?? s.status,
      updatedAt: s.updatedAt?.getTime() ?? 0,
      valor: Number(s.valorTotal),
    })),
  ].sort((a, b) => b.updatedAt - a.updatedAt);

  const kindBadge: Record<Activity["kind"], string> = {
    quote: "bg-purple-100 text-purple-700",
    pedido: "bg-blue-100 text-blue-700",
    os: "bg-amber-100 text-amber-700",
  };
  const kindLabel: Record<Activity["kind"], string> = {
    quote: "Orçamento",
    pedido: "Pedido",
    os: "OS",
  };
  const kindPath: Record<Activity["kind"], string> = {
    quote: "orcamento",
    pedido: "pedido",
    os: "os",
  };

  const list =
    activities.length === 0
      ? `<div class="bg-white rounded-xl p-8 text-center text-slate-500">Nenhuma atividade recente.</div>`
      : activities
          .map(
            (a) => `
        <a href="/portal/${kindPath[a.kind]}/${encodeURIComponent(a.id)}" class="block bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-semibold px-2 py-0.5 rounded ${kindBadge[a.kind]}">${kindLabel[a.kind]}</span>
                <span class="text-xs text-slate-500">${escapeHtml(a.statusLabel)}</span>
              </div>
              <div class="font-medium truncate">${escapeHtml(a.title)}</div>
            </div>
            ${
              a.valor != null
                ? `<div class="text-right shrink-0">
                     <div class="font-semibold">${formatBRL(a.valor)}</div>
                   </div>`
                : ""
            }
          </div>
        </a>`,
          )
          .join("");

  return layout(
    "Portal — " + customer.nome,
    `${header(customer.nome, "Acompanhe seus pedidos, orçamentos e ordens de serviço.")}
     <div class="flex gap-3 mb-6">
       ${statBadge("Pedidos abertos", openPedidos, "text-blue-600")}
       ${statBadge("OSs em andamento", ossAndamento, "text-amber-600")}
       ${statBadge("Orçamentos p/ aprovar", pendingQuotes, "text-purple-600")}
     </div>
     <h2 class="text-lg font-semibold mb-3">Suas atividades recentes</h2>
     <div class="space-y-2">${list}</div>`,
  );
}

// ─── Render: pedido detail ────────────────────────────────────────────────

async function renderPedido(
  customer: typeof customers.$inferSelect,
  pedidoId: string,
  message?: string,
): Promise<string | null> {
  const db = await getDb();
  if (!db) return null;
  const pRows = await db
    .select()
    .from(pedidos)
    .where(and(eq(pedidos.id, pedidoId), eq(pedidos.customerId, customer.id)))
    .limit(1);
  const p = pRows[0];
  if (!p) return null;
  const items = await db
    .select()
    .from(orderItems)
    .where(eq(orderItems.pedidoId, p.id));

  const itemRows = items.length
    ? items
        .map(
          (i) => `
        <tr class="border-b last:border-0">
          <td class="py-2 pr-2">${escapeHtml(i.descricao)}</td>
          <td class="py-2 text-right">${Number(i.quantidade)}</td>
          <td class="py-2 text-right">${formatBRL(Number(i.valorUnit))}</td>
          <td class="py-2 text-right font-medium">${formatBRL(Number(i.valorTotal))}</td>
        </tr>`,
        )
        .join("")
    : `<tr><td colspan="4" class="py-3 text-center text-slate-500">Sem itens detalhados</td></tr>`;

  const msg = message
    ? `<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm">${escapeHtml(message)}</div>`
    : "";

  return layout(
    `Pedido — ${p.produto}`,
    `${header(customer.nome)}
     ${backLink()}
     ${msg}
     <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
       <div class="text-xs text-slate-500 uppercase">Pedido</div>
       <h2 class="text-xl font-bold mb-2">${escapeHtml(p.produto)}</h2>
       <div class="flex items-center gap-3 text-sm">
         <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700">${escapeHtml(PEDIDO_STATUS_LABELS[p.status] ?? p.status)}</span>
         <span class="text-slate-500">${formatDate(p.data)}</span>
       </div>
       <div class="text-2xl font-bold mt-3">${formatBRL(Number(p.valor))}</div>
     </div>
     <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
       <h3 class="font-semibold mb-2">Itens</h3>
       <table class="w-full text-sm">
         <thead><tr class="text-left text-xs text-slate-500 uppercase border-b">
           <th class="py-2">Descrição</th><th class="py-2 text-right">Qtd</th><th class="py-2 text-right">Unit.</th><th class="py-2 text-right">Total</th>
         </tr></thead>
         <tbody>${itemRows}</tbody>
       </table>
     </div>
     <form method="POST" action="/portal/pedido/${encodeURIComponent(p.id)}/request-update">
       <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg">
         Solicitar atualização
       </button>
     </form>`,
  );
}

// ─── Render: OS detail ────────────────────────────────────────────────────

async function renderOS(
  customer: typeof customers.$inferSelect,
  osId: string,
  message?: string,
): Promise<string | null> {
  const db = await getDb();
  if (!db) return null;
  const rows = await db
    .select({ so: serviceOrders, vehicle: vehicles })
    .from(serviceOrders)
    .leftJoin(vehicles, eq(serviceOrders.vehicleId, vehicles.id))
    .where(
      and(eq(serviceOrders.id, osId), eq(serviceOrders.customerId, customer.id)),
    )
    .limit(1);
  const row = rows[0];
  if (!row) return null;
  const so = row.so;
  const vehicle = row.vehicle;
  const items = await db
    .select()
    .from(serviceOrderItems)
    .where(eq(serviceOrderItems.serviceOrderId, so.id));
  const photos = await db
    .select()
    .from(serviceOrderPhotos)
    .where(eq(serviceOrderPhotos.serviceOrderId, so.id));

  const renderItems = (tipo: "peca" | "servico") => {
    const subset = items.filter((i) => i.tipo === tipo);
    if (subset.length === 0)
      return `<tr><td colspan="4" class="py-3 text-center text-slate-500">Sem ${tipo === "peca" ? "peças" : "serviços"}</td></tr>`;
    return subset
      .map(
        (i) => `
        <tr class="border-b last:border-0">
          <td class="py-2 pr-2">${escapeHtml(i.descricao)}</td>
          <td class="py-2 text-right">${Number(i.quantidade)}</td>
          <td class="py-2 text-right">${formatBRL(Number(i.valorUnit))}</td>
          <td class="py-2 text-right font-medium">${formatBRL(Number(i.valorTotal))}</td>
        </tr>`,
      )
      .join("");
  };

  const already = so.aprovacaoCliente !== null;
  const aprovado = so.aprovacaoCliente === 1;

  const approvalBox = already
    ? `<div class="${aprovado ? "bg-emerald-50 border-emerald-200" : "bg-rose-50 border-rose-200"} border rounded-lg p-4">
         <strong>${aprovado ? "OS aprovada" : "OS rejeitada"}</strong>
         ${so.aprovacaoData ? `<div class="text-xs text-slate-500 mt-1">em ${escapeHtml(so.aprovacaoData)}</div>` : ""}
       </div>`
    : `<form method="POST" class="space-y-3">
         <textarea name="comentario" rows="3" placeholder="Comentário (opcional)" class="w-full border border-slate-300 rounded-lg p-2"></textarea>
         <div class="flex gap-3">
           <button type="submit" formaction="/portal/os/${encodeURIComponent(so.id)}/approve" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg">Aprovar</button>
           <button type="submit" formaction="/portal/os/${encodeURIComponent(so.id)}/reject" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-semibold py-3 rounded-lg">Rejeitar</button>
         </div>
       </form>`;

  const photoStrip = photos.length
    ? `<div class="bg-white rounded-xl shadow-sm p-5 mb-4">
         <h3 class="font-semibold mb-2">Fotos (${photos.length})</h3>
         <div class="grid grid-cols-3 gap-2">
           ${photos
             .map(
               (ph) => `
             <div class="aspect-square bg-slate-100 rounded overflow-hidden">
               <img src="/api/storage/${encodeURIComponent(ph.fileKey)}" alt="${escapeHtml(ph.descricao ?? "foto")}" class="w-full h-full object-cover" />
             </div>`,
             )
             .join("")}
         </div>
       </div>`
    : "";

  const msg = message
    ? `<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm">${escapeHtml(message)}</div>`
    : "";

  return layout(
    `OS #${so.numero}`,
    `${header(customer.nome)}
     ${backLink()}
     ${msg}
     <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
       <div class="text-xs text-slate-500 uppercase">Ordem de Serviço</div>
       <h2 class="text-xl font-bold">#${so.numero}</h2>
       <div class="text-sm mt-1"><span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700">${escapeHtml(SO_STATUS_LABELS[so.status] ?? so.status)}</span></div>
       ${
         vehicle
           ? `<div class="mt-3 text-sm text-slate-700">
                <strong>${escapeHtml(vehicle.placa)}</strong> — ${escapeHtml(vehicle.marca)} ${escapeHtml(vehicle.modelo)}${vehicle.ano ? ` (${vehicle.ano})` : ""}
              </div>`
           : ""
       }
     </div>
     ${
       so.queixaCliente || so.diagnostico
         ? `<div class="bg-white rounded-xl shadow-sm p-5 mb-4 text-sm">
              ${so.queixaCliente ? `<div class="text-xs text-slate-500 uppercase mb-1">Queixa</div><div class="mb-3 whitespace-pre-wrap">${escapeHtml(so.queixaCliente)}</div>` : ""}
              ${so.diagnostico ? `<div class="text-xs text-slate-500 uppercase mb-1">Diagnóstico</div><div class="whitespace-pre-wrap">${escapeHtml(so.diagnostico)}</div>` : ""}
            </div>`
         : ""
     }
     ${photoStrip}
     <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
       <h3 class="font-semibold mb-2">Serviços</h3>
       <table class="w-full text-sm">
         <thead><tr class="text-left text-xs text-slate-500 uppercase border-b">
           <th class="py-2">Descrição</th><th class="py-2 text-right">Qtd</th><th class="py-2 text-right">Unit.</th><th class="py-2 text-right">Total</th>
         </tr></thead>
         <tbody>${renderItems("servico")}</tbody>
       </table>
     </div>
     <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
       <h3 class="font-semibold mb-2">Peças</h3>
       <table class="w-full text-sm">
         <thead><tr class="text-left text-xs text-slate-500 uppercase border-b">
           <th class="py-2">Descrição</th><th class="py-2 text-right">Qtd</th><th class="py-2 text-right">Unit.</th><th class="py-2 text-right">Total</th>
         </tr></thead>
         <tbody>${renderItems("peca")}</tbody>
       </table>
     </div>
     <div class="bg-white rounded-xl shadow-sm p-5 mb-4 text-sm">
       <div class="flex justify-between py-1"><span>Peças</span><span>${formatBRL(Number(so.valorPecas))}</span></div>
       <div class="flex justify-between py-1"><span>Mão de obra</span><span>${formatBRL(Number(so.valorMaoObra))}</span></div>
       <div class="flex justify-between py-2 border-t mt-2 font-bold text-base"><span>Total</span><span>${formatBRL(Number(so.valorTotal))}</span></div>
     </div>
     ${approvalBox}`,
  );
}

// ─── Render: quote detail ─────────────────────────────────────────────────

async function renderQuote(
  customer: typeof customers.$inferSelect,
  quoteId: string,
  message?: string,
): Promise<string | null> {
  const db = await getDb();
  if (!db) return null;
  const rows = await db
    .select()
    .from(quotes)
    .where(and(eq(quotes.id, quoteId), eq(quotes.customerId, customer.id)))
    .limit(1);
  const q = rows[0];
  if (!q) return null;
  const items = await db
    .select()
    .from(quoteItems)
    .where(eq(quoteItems.quoteId, q.id));

  const itemRows = items.length
    ? items
        .map(
          (i) => `
        <tr class="border-b last:border-0">
          <td class="py-2 pr-2">
            <div>${escapeHtml(i.descricao)}</div>
            <div class="text-xs text-slate-500">${Number(i.larguraCm)}×${Number(i.alturaCm)}cm${i.material ? ` · ${escapeHtml(i.material)}` : ""}</div>
          </td>
          <td class="py-2 text-right">${Number(i.quantidade)}</td>
          <td class="py-2 text-right font-medium">${formatBRL(Number(i.valorTotal))}</td>
        </tr>`,
        )
        .join("")
    : `<tr><td colspan="3" class="py-3 text-center text-slate-500">Sem itens</td></tr>`;

  const canApprove = q.status === "enviado";
  const decisionBox =
    q.status === "aprovado"
      ? `<div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4"><strong>Orçamento aprovado</strong></div>`
      : q.status === "rejeitado"
        ? `<div class="bg-rose-50 border border-rose-200 rounded-lg p-4"><strong>Orçamento rejeitado</strong></div>`
        : canApprove
          ? `<form method="POST" action="/portal/orcamento/${encodeURIComponent(q.id)}/approve">
               <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg">Aprovar orçamento</button>
             </form>`
          : `<div class="text-center text-slate-500 text-sm">Aguardando envio do orçamento.</div>`;

  const msg = message
    ? `<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm">${escapeHtml(message)}</div>`
    : "";

  return layout(
    `Orçamento — ${q.titulo}`,
    `${header(customer.nome)}
     ${backLink()}
     ${msg}
     <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
       <div class="text-xs text-slate-500 uppercase">Orçamento</div>
       <h2 class="text-xl font-bold mb-2">${escapeHtml(q.titulo)}</h2>
       <div class="text-sm"><span class="px-2 py-0.5 rounded bg-purple-100 text-purple-700">${escapeHtml(QUOTE_STATUS_LABELS[q.status] ?? q.status)}</span></div>
       <div class="text-2xl font-bold mt-3">${formatBRL(Number(q.valorTotal))}</div>
       ${q.observacoes ? `<div class="text-sm text-slate-600 mt-2 whitespace-pre-wrap">${escapeHtml(q.observacoes)}</div>` : ""}
     </div>
     <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
       <h3 class="font-semibold mb-2">Itens</h3>
       <table class="w-full text-sm">
         <thead><tr class="text-left text-xs text-slate-500 uppercase border-b">
           <th class="py-2">Descrição</th><th class="py-2 text-right">Qtd</th><th class="py-2 text-right">Total</th>
         </tr></thead>
         <tbody>${itemRows}</tbody>
       </table>
     </div>
     ${decisionBox}`,
  );
}

// ─── Routes ───────────────────────────────────────────────────────────────

export function registerCustomerPortalRoutes(app: Express) {
  const formParser = express.urlencoded({ extended: false });

  // Magic-link login
  app.get("/portal/login/:token", async (req: Request, res: Response) => {
    try {
      const db = await getDb();
      if (!db) {
        res.status(500).type("html").send(errorPage("Banco indisponível"));
        return;
      }
      const tokenStr = req.params.token;
      const rows = await db
        .select()
        .from(customerMagicLinks)
        .where(eq(customerMagicLinks.token, tokenStr))
        .limit(1);
      const link = rows[0];
      if (!link) {
        res.status(404).type("html").send(notFoundPage());
        return;
      }
      const exp = Date.parse(link.expiresAt);
      if (Number.isFinite(exp) && exp < Date.now()) {
        res.status(410).type("html").send(notFoundPage("Este link expirou."));
        return;
      }
      // Mark first use (but keep usable until expiresAt).
      if (!link.usedAt) {
        await db
          .update(customerMagicLinks)
          .set({ usedAt: new Date().toISOString() })
          .where(eq(customerMagicLinks.id, link.id));
      }
      // Create session.
      const sessionToken = randomBytes(48).toString("hex"); // 96 chars
      const sessionExpires = new Date(Date.now() + SESSION_TTL_MS).toISOString();
      await db.insert(customerSessions).values({
        id: randomUUID(),
        customerId: link.customerId,
        userId: link.userId,
        sessionToken,
        expiresAt: sessionExpires,
      });
      setPortalCookie(res, req, sessionToken, SESSION_TTL_MS);
      res.redirect(302, "/portal");
    } catch (err) {
      console.error("[portal] login failed:", err);
      res.status(500).type("html").send(errorPage("Falha ao validar link."));
    }
  });

  // Auth middleware for /portal* (except login)
  const requirePortal = async (req: Request, res: Response, next: NextFunction) => {
    const session = await loadSession(req);
    if (!session) {
      res
        .status(401)
        .type("html")
        .send(notFoundPage("Sessão expirada. Solicite um novo link de acesso."));
      return;
    }
    (req as Request & { portal?: PortalSession }).portal = session;
    next();
  };

  // Dashboard
  app.get("/portal", requirePortal, async (req: Request, res: Response) => {
    try {
      const portal = (req as Request & { portal: PortalSession }).portal;
      const data = await loadDashboardData(portal.customer.id, portal.session.userId);
      res.type("html").send(renderDashboard(portal.customer, data));
    } catch (err) {
      console.error("[portal] dashboard failed:", err);
      res.status(500).type("html").send(errorPage("Erro ao carregar painel."));
    }
  });

  // Pedido detail
  app.get("/portal/pedido/:id", requirePortal, async (req: Request, res: Response) => {
    try {
      const portal = (req as Request & { portal: PortalSession }).portal;
      const html = await renderPedido(portal.customer, req.params.id);
      if (!html) {
        res.status(404).type("html").send(notFoundPage("Pedido não encontrado."));
        return;
      }
      res.type("html").send(html);
    } catch (err) {
      console.error("[portal] pedido failed:", err);
      res.status(500).type("html").send(errorPage("Erro ao carregar pedido."));
    }
  });

  // Request update on pedido — logs a WhatsApp message stub (fire-and-forget)
  app.post(
    "/portal/pedido/:id/request-update",
    requirePortal,
    formParser,
    async (req: Request, res: Response) => {
      try {
        const portal = (req as Request & { portal: PortalSession }).portal;
        const db = await getDb();
        if (db) {
          const pRows = await db
            .select()
            .from(pedidos)
            .where(
              and(
                eq(pedidos.id, req.params.id),
                eq(pedidos.customerId, portal.customer.id),
              ),
            )
            .limit(1);
          const p = pRows[0];
          if (p) {
            await db.insert(whatsappMessages).values({
              id: randomUUID(),
              userId: portal.session.userId,
              customerId: portal.customer.id,
              telefone: portal.customer.telefone ?? "",
              mensagem: `Cliente ${portal.customer.nome} solicitou atualização sobre o pedido "${p.produto}" pelo portal.`,
              tipo: "pending",
              referenciaTipo: "pedido",
              referenciaId: p.id,
            });
          }
        }
        const html = await renderPedido(
          portal.customer,
          req.params.id,
          "Solicitação registrada. A empresa entrará em contato em breve.",
        );
        if (!html) {
          res.status(404).type("html").send(notFoundPage());
          return;
        }
        res.type("html").send(html);
      } catch (err) {
        console.error("[portal] pedido request-update failed:", err);
        res.status(500).type("html").send(errorPage("Erro ao registrar solicitação."));
      }
    },
  );

  // OS detail
  app.get("/portal/os/:id", requirePortal, async (req: Request, res: Response) => {
    try {
      const portal = (req as Request & { portal: PortalSession }).portal;
      const html = await renderOS(portal.customer, req.params.id);
      if (!html) {
        res.status(404).type("html").send(notFoundPage("OS não encontrada."));
        return;
      }
      res.type("html").send(html);
    } catch (err) {
      console.error("[portal] os failed:", err);
      res.status(500).type("html").send(errorPage("Erro ao carregar OS."));
    }
  });

  // OS approve / reject
  const handleOSDecision = (aprovado: boolean) => async (req: Request, res: Response) => {
    try {
      const portal = (req as Request & { portal: PortalSession }).portal;
      const db = await getDb();
      if (!db) {
        res.status(500).type("html").send(errorPage("Banco indisponível."));
        return;
      }
      const rows = await db
        .select()
        .from(serviceOrders)
        .where(
          and(
            eq(serviceOrders.id, req.params.id),
            eq(serviceOrders.customerId, portal.customer.id),
            eq(serviceOrders.userId, portal.session.userId),
          ),
        )
        .limit(1);
      const so = rows[0];
      if (!so) {
        res.status(404).type("html").send(notFoundPage("OS não encontrada."));
        return;
      }
      let message = aprovado
        ? "OS aprovada. Obrigado!"
        : "Resposta registrada. A empresa entrará em contato.";
      // Idempotência
      if (so.aprovacaoCliente !== null) {
        message = "Esta OS já foi respondida anteriormente.";
      } else {
        const comentario =
          typeof req.body?.comentario === "string"
            ? (req.body.comentario as string).trim() || null
            : null;
        await db
          .update(serviceOrders)
          .set({
            aprovacaoCliente: aprovado ? 1 : 0,
            aprovacaoData: new Date().toISOString(),
            aprovacaoComentario: comentario,
          })
          .where(eq(serviceOrders.id, so.id));
      }
      const html = await renderOS(portal.customer, req.params.id, message);
      if (!html) {
        res.status(404).type("html").send(notFoundPage());
        return;
      }
      res.type("html").send(html);
    } catch (err) {
      console.error("[portal] os decision failed:", err);
      res.status(500).type("html").send(errorPage("Erro ao registrar decisão."));
    }
  };
  app.post("/portal/os/:id/approve", requirePortal, formParser, handleOSDecision(true));
  app.post("/portal/os/:id/reject", requirePortal, formParser, handleOSDecision(false));

  // Quote detail
  app.get(
    "/portal/orcamento/:id",
    requirePortal,
    async (req: Request, res: Response) => {
      try {
        const portal = (req as Request & { portal: PortalSession }).portal;
        const html = await renderQuote(portal.customer, req.params.id);
        if (!html) {
          res
            .status(404)
            .type("html")
            .send(notFoundPage("Orçamento não encontrado."));
          return;
        }
        res.type("html").send(html);
      } catch (err) {
        console.error("[portal] quote failed:", err);
        res.status(500).type("html").send(errorPage("Erro ao carregar orçamento."));
      }
    },
  );

  // Quote approve
  app.post(
    "/portal/orcamento/:id/approve",
    requirePortal,
    formParser,
    async (req: Request, res: Response) => {
      try {
        const portal = (req as Request & { portal: PortalSession }).portal;
        const db = await getDb();
        if (!db) {
          res.status(500).type("html").send(errorPage("Banco indisponível."));
          return;
        }
        const rows = await db
          .select()
          .from(quotes)
          .where(
            and(
              eq(quotes.id, req.params.id),
              eq(quotes.customerId, portal.customer.id),
              eq(quotes.userId, portal.session.userId),
            ),
          )
          .limit(1);
        const q = rows[0];
        if (!q) {
          res
            .status(404)
            .type("html")
            .send(notFoundPage("Orçamento não encontrado."));
          return;
        }
        let message = "Orçamento aprovado. Obrigado!";
        if (q.status === "aprovado") {
          message = "Este orçamento já estava aprovado.";
        } else if (q.status !== "enviado") {
          message = "Este orçamento não está disponível para aprovação.";
        } else {
          await db
            .update(quotes)
            .set({ status: "aprovado" })
            .where(eq(quotes.id, q.id));
        }
        const html = await renderQuote(portal.customer, q.id, message);
        if (!html) {
          res.status(404).type("html").send(notFoundPage());
          return;
        }
        res.type("html").send(html);
      } catch (err) {
        console.error("[portal] quote approve failed:", err);
        res.status(500).type("html").send(errorPage("Erro ao aprovar orçamento."));
      }
    },
  );

  // Logout
  app.post("/portal/logout", async (req: Request, res: Response) => {
    try {
      const cookies = parseCookies(req.headers.cookie);
      const tok = cookies[PORTAL_COOKIE_NAME];
      if (tok) {
        const db = await getDb();
        if (db) {
          await db
            .delete(customerSessions)
            .where(eq(customerSessions.sessionToken, tok));
        }
      }
      clearPortalCookie(res, req);
      res.redirect(302, "/portal/logged-out");
    } catch (err) {
      console.error("[portal] logout failed:", err);
      clearPortalCookie(res, req);
      res.redirect(302, "/portal/logged-out");
    }
  });

  app.get("/portal/logged-out", (_req: Request, res: Response) => {
    res
      .type("html")
      .send(
        layout(
          "Sessão encerrada",
          `<div class="bg-white rounded-xl shadow-sm p-8 text-center">
             <h1 class="text-2xl font-bold mb-2">Você saiu</h1>
             <p class="text-slate-600">Solicite um novo link de acesso para entrar novamente.</p>
           </div>`,
        ),
      );
  });
}
