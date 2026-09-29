import type { Express, Request, Response } from "express";
import express from "express";
import { eq } from "drizzle-orm";

import {
  customers,
  serviceOrderItems,
  serviceOrderPublicTokens,
  serviceOrders,
  vehicles,
} from "../../drizzle/schema";
import { getDb } from "../db";

/**
 * Public (no-auth) approval routes for Service Orders.
 *
 * Customers receive a link like /os/<token>. They see a server-rendered
 * HTML summary with two buttons (Aprovar / Rejeitar) that POST back to
 * /os/<token>/approve|reject.
 *
 * Tokens are looked up in `serviceOrderPublicTokens` (unique constraint
 * on token). Approval status writes back to the parent `serviceOrders` row.
 */

const STATUS_LABELS: Record<string, string> = {
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

type LoadedOS = {
  so: typeof serviceOrders.$inferSelect;
  vehicle: typeof vehicles.$inferSelect | null;
  customer: typeof customers.$inferSelect | null;
  items: (typeof serviceOrderItems.$inferSelect)[];
  token: typeof serviceOrderPublicTokens.$inferSelect;
};

async function loadByToken(token: string): Promise<LoadedOS | null> {
  const db = await getDb();
  if (!db) return null;
  const tokenRows = await db
    .select()
    .from(serviceOrderPublicTokens)
    .where(eq(serviceOrderPublicTokens.token, token))
    .limit(1);
  const tokenRow = tokenRows[0];
  if (!tokenRow) return null;

  if (tokenRow.expiresAt) {
    const exp = Date.parse(tokenRow.expiresAt);
    if (Number.isFinite(exp) && exp < Date.now()) return null;
  }

  const soRows = await db
    .select({ so: serviceOrders, vehicle: vehicles, customer: customers })
    .from(serviceOrders)
    .leftJoin(vehicles, eq(serviceOrders.vehicleId, vehicles.id))
    .leftJoin(customers, eq(serviceOrders.customerId, customers.id))
    .where(eq(serviceOrders.id, tokenRow.serviceOrderId))
    .limit(1);
  const row = soRows[0];
  if (!row) return null;

  const items = await db
    .select()
    .from(serviceOrderItems)
    .where(eq(serviceOrderItems.serviceOrderId, tokenRow.serviceOrderId));

  return {
    so: row.so,
    vehicle: row.vehicle,
    customer: row.customer,
    items,
    token: tokenRow,
  };
}

function renderItemRows(
  items: (typeof serviceOrderItems.$inferSelect)[],
  tipo: "peca" | "servico",
) {
  const subset = items.filter((i) => i.tipo === tipo);
  if (subset.length === 0) {
    return `<tr><td colspan="5" style="text-align:center;color:#888;padding:12px;">Nenhum item</td></tr>`;
  }
  return subset
    .map((i) => {
      const qty = Number(i.quantidade);
      const vu = Number(i.valorUnit);
      const total = Number(i.valorTotal);
      return `
        <tr>
          <td style="padding:8px;border-bottom:1px solid #eee;">${escapeHtml(i.codigo ?? "—")}</td>
          <td style="padding:8px;border-bottom:1px solid #eee;">${escapeHtml(i.descricao)}</td>
          <td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">${qty}</td>
          <td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">${formatBRL(vu)}</td>
          <td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">${formatBRL(total)}</td>
        </tr>`;
    })
    .join("");
}

function renderPage(loaded: LoadedOS, opts?: { message?: string }): string {
  const { so, vehicle, customer, items, token } = loaded;
  const valorPecas = Number(so.valorPecas);
  const valorMaoObra = Number(so.valorMaoObra);
  const valorTotal = Number(so.valorTotal);
  const statusLabel = STATUS_LABELS[so.status] ?? so.status;

  const already = so.aprovacaoCliente !== null;
  const aprovadoFlag = so.aprovacaoCliente === 1;

  const message = opts?.message
    ? `<div style="background:#fff8e1;border:1px solid #ffe082;padding:12px;border-radius:8px;margin-bottom:16px;">${escapeHtml(opts.message)}</div>`
    : "";

  const decisionBox = already
    ? `<div style="background:${aprovadoFlag ? "#e8f5e9" : "#ffebee"};border:1px solid ${aprovadoFlag ? "#a5d6a7" : "#ef9a9a"};padding:16px;border-radius:8px;margin-top:24px;">
         <strong>${aprovadoFlag ? "Orçamento aprovado" : "Orçamento rejeitado"}</strong>
         ${so.aprovacaoData ? `<div style="font-size:12px;color:#555;margin-top:4px;">em ${escapeHtml(so.aprovacaoData)}</div>` : ""}
         ${so.aprovacaoComentario ? `<div style="margin-top:8px;">${escapeHtml(so.aprovacaoComentario)}</div>` : ""}
       </div>`
    : `<form method="POST" style="margin-top:24px;">
         <label for="comentario" style="display:block;font-weight:600;margin-bottom:6px;">Comentário (opcional)</label>
         <textarea id="comentario" name="comentario" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;font-family:inherit;"></textarea>
         <div style="display:flex;gap:12px;margin-top:16px;">
           <button type="submit" formaction="/os/${encodeURIComponent(token.token)}/approve" style="flex:1;background:#2e7d32;color:white;border:0;padding:14px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;">Aprovar</button>
           <button type="submit" formaction="/os/${encodeURIComponent(token.token)}/reject" style="flex:1;background:#c62828;color:white;border:0;padding:14px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;">Rejeitar</button>
         </div>
       </form>`;

  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>OS #${so.numero} — Aprovação</title>
  <style>
    body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f5f7;color:#222;}
    .container{max-width:720px;margin:0 auto;padding:24px 16px;}
    h1{font-size:22px;margin:0 0 8px;}
    h2{font-size:16px;margin:24px 0 8px;color:#444;}
    .card{background:white;border-radius:12px;padding:16px;margin-top:12px;box-shadow:0 1px 2px rgba(0,0,0,0.06);}
    table{width:100%;border-collapse:collapse;font-size:14px;}
    th{text-align:left;padding:8px;border-bottom:2px solid #ddd;background:#fafafa;}
    .total-row{font-weight:700;}
    .label{color:#666;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;}
    .value{font-size:15px;margin-bottom:8px;}
  </style>
</head>
<body>
  <div class="container">
    <h1>OS #${so.numero}</h1>
    <div style="color:#666;font-size:13px;">Status atual: <strong>${escapeHtml(statusLabel)}</strong></div>

    ${message}

    <div class="card">
      <h2 style="margin-top:0;">Veículo</h2>
      <div class="value"><strong>${escapeHtml(vehicle?.placa ?? "—")}</strong> &mdash; ${escapeHtml(vehicle?.marca ?? "")} ${escapeHtml(vehicle?.modelo ?? "")}${vehicle?.ano ? ` (${vehicle.ano})` : ""}</div>
      ${vehicle?.cor ? `<div class="value">Cor: ${escapeHtml(vehicle.cor)}</div>` : ""}
    </div>

    <div class="card">
      <h2 style="margin-top:0;">Cliente</h2>
      <div class="value">${escapeHtml(customer?.nome ?? "—")}</div>
      ${customer?.telefone ? `<div class="value">Telefone: ${escapeHtml(customer.telefone)}</div>` : ""}
    </div>

    ${
      so.queixaCliente || so.diagnostico
        ? `<div class="card">
            ${so.queixaCliente ? `<div class="label">Queixa do cliente</div><div class="value" style="white-space:pre-wrap;">${escapeHtml(so.queixaCliente)}</div>` : ""}
            ${so.diagnostico ? `<div class="label" style="margin-top:12px;">Diagnóstico</div><div class="value" style="white-space:pre-wrap;">${escapeHtml(so.diagnostico)}</div>` : ""}
          </div>`
        : ""
    }

    <div class="card">
      <h2 style="margin-top:0;">Serviços (mão de obra)</h2>
      <table>
        <thead><tr><th>Cód.</th><th>Descrição</th><th style="text-align:right;">Qtd</th><th style="text-align:right;">Valor unit.</th><th style="text-align:right;">Total</th></tr></thead>
        <tbody>${renderItemRows(items, "servico")}</tbody>
      </table>
    </div>

    <div class="card">
      <h2 style="margin-top:0;">Peças</h2>
      <table>
        <thead><tr><th>Cód.</th><th>Descrição</th><th style="text-align:right;">Qtd</th><th style="text-align:right;">Valor unit.</th><th style="text-align:right;">Total</th></tr></thead>
        <tbody>${renderItemRows(items, "peca")}</tbody>
      </table>
    </div>

    <div class="card">
      <table>
        <tr><td>Total peças</td><td style="text-align:right;">${formatBRL(valorPecas)}</td></tr>
        <tr><td>Total mão de obra</td><td style="text-align:right;">${formatBRL(valorMaoObra)}</td></tr>
        <tr class="total-row"><td>Total geral</td><td style="text-align:right;font-size:18px;">${formatBRL(valorTotal)}</td></tr>
      </table>
    </div>

    ${decisionBox}
  </div>
</body>
</html>`;
}

function notFoundPage(): string {
  return `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Não encontrado</title></head>
<body style="font-family:sans-serif;padding:40px;text-align:center;">
  <h1>Link inválido ou expirado</h1>
  <p>Solicite um novo link de aprovação à oficina.</p>
</body></html>`;
}

async function recordDecision(
  serviceOrderId: string,
  aprovado: boolean,
  comentario: string | null,
  ip: string | null,
): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db
    .update(serviceOrders)
    .set({
      aprovacaoCliente: aprovado ? 1 : 0,
      aprovacaoData: new Date().toISOString(),
      aprovacaoIp: ip,
      aprovacaoComentario: comentario,
    })
    .where(eq(serviceOrders.id, serviceOrderId));
}

function getClientIp(req: Request): string | null {
  const xfwd = req.headers["x-forwarded-for"];
  if (typeof xfwd === "string" && xfwd.length > 0) {
    return xfwd.split(",")[0].trim();
  }
  if (Array.isArray(xfwd) && xfwd.length > 0) return xfwd[0];
  return req.ip ?? req.socket?.remoteAddress ?? null;
}

export function registerServiceOrderApprovalRoutes(app: Express) {
  const formParser = express.urlencoded({ extended: false });

  app.get("/os/:token", async (req: Request, res: Response) => {
    try {
      const loaded = await loadByToken(req.params.token);
      if (!loaded) {
        res.status(404).type("html").send(notFoundPage());
        return;
      }
      res.type("html").send(renderPage(loaded));
    } catch (err) {
      console.error("[os-approval] GET failed:", err);
      res.status(500).type("html").send(notFoundPage());
    }
  });

  app.post("/os/:token/approve", formParser, async (req: Request, res: Response) => {
    try {
      const loaded = await loadByToken(req.params.token);
      if (!loaded) {
        res.status(404).type("html").send(notFoundPage());
        return;
      }
      // Idempotência: se já decidido, apenas re-renderize a página sem sobrescrever.
      if (loaded.so.aprovacaoCliente !== null) {
        res
          .type("html")
          .send(renderPage(loaded, { message: "Esta OS já foi respondida anteriormente." }));
        return;
      }
      const comentario =
        typeof req.body?.comentario === "string"
          ? (req.body.comentario as string).trim() || null
          : null;
      await recordDecision(loaded.so.id, true, comentario, getClientIp(req));
      const fresh = await loadByToken(req.params.token);
      if (!fresh) {
        res.status(500).type("html").send(notFoundPage());
        return;
      }
      res
        .type("html")
        .send(renderPage(fresh, { message: "Obrigado! Sua aprovação foi registrada." }));
    } catch (err) {
      console.error("[os-approval] approve failed:", err);
      res.status(500).type("html").send(notFoundPage());
    }
  });

  app.post("/os/:token/reject", formParser, async (req: Request, res: Response) => {
    try {
      const loaded = await loadByToken(req.params.token);
      if (!loaded) {
        res.status(404).type("html").send(notFoundPage());
        return;
      }
      if (loaded.so.aprovacaoCliente !== null) {
        res
          .type("html")
          .send(renderPage(loaded, { message: "Esta OS já foi respondida anteriormente." }));
        return;
      }
      const comentario =
        typeof req.body?.comentario === "string"
          ? (req.body.comentario as string).trim() || null
          : null;
      await recordDecision(loaded.so.id, false, comentario, getClientIp(req));
      const fresh = await loadByToken(req.params.token);
      if (!fresh) {
        res.status(500).type("html").send(notFoundPage());
        return;
      }
      res.type("html").send(
        renderPage(fresh, {
          message: "Sua resposta foi registrada. A oficina entrará em contato.",
        }),
      );
    } catch (err) {
      console.error("[os-approval] reject failed:", err);
      res.status(500).type("html").send(notFoundPage());
    }
  });
}

