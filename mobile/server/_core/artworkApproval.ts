import type { Express, Request, Response } from "express";
import { randomUUID } from "node:crypto";
import { eq } from "drizzle-orm";

import { artworkApprovals, artworks } from "../../drizzle/schema";
import { getDb } from "../db";

/**
 * Public artwork approval routes.
 *
 * GET /a/:token  → renders a server-side HTML page with the artwork preview
 *                  and a small form for approve/reject + name/email/comment.
 * POST /a/:token → records the decision, updates the artwork status.
 *
 * The token is generated server-side at artwork creation time (64 hex chars)
 * and stored on `artworks.publicToken` (unique). No auth required: knowing the
 * link grants the right to (single-shot) approve. The history is kept in
 * `artworkApprovals`, so multiple decisions on the same link are auditable.
 */

function escapeHtml(s: string | null | undefined): string {
  if (s === null || s === undefined) return "";
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function notFoundHtml(): string {
  return `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Arte não encontrada</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:520px;margin:48px auto;padding:24px;color:#111}</style>
</head><body><h1>Link inválido</h1><p>A arte solicitada não foi encontrada ou o link expirou.</p></body></html>`;
}

function renderPageHtml(opts: {
  artworkId: string;
  titulo: string;
  descricao: string | null;
  fileKey: string;
  mimeType: string;
  status: string;
}): string {
  const isImage = opts.mimeType.startsWith("image/");
  const isPdf = opts.mimeType === "application/pdf";
  const fileUrl = `/manus-storage/${opts.fileKey}`;
  const previewHtml = isImage
    ? `<img src="${escapeHtml(fileUrl)}" alt="" style="max-width:100%;border:1px solid #ddd;border-radius:8px"/>`
    : isPdf
      ? `<iframe src="${escapeHtml(fileUrl)}" style="width:100%;height:520px;border:1px solid #ddd;border-radius:8px"></iframe>`
      : `<p><a href="${escapeHtml(fileUrl)}" target="_blank">Abrir arquivo (${escapeHtml(opts.mimeType)})</a></p>`;

  const statusBadge =
    opts.status === "aprovado"
      ? `<span style="background:#EAF3DE;color:#27500A;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:600">Aprovada</span>`
      : opts.status === "rejeitado"
        ? `<span style="background:#FCEBEB;color:#791F1F;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:600">Rejeitada</span>`
        : `<span style="background:#FAEEDA;color:#633806;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:600">Pendente</span>`;

  return `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Aprovação de Arte — ${escapeHtml(opts.titulo)}</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:720px;margin:24px auto;padding:16px;color:#111;background:#fafafa}
h1{margin:0 0 8px;font-size:20px}
.card{background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:16px;margin-top:16px}
label{display:block;font-size:13px;margin:8px 0 4px;font-weight:500}
input[type=text],input[type=email],textarea{width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:8px;font-size:14px;font-family:inherit}
textarea{min-height:80px;resize:vertical}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row label{flex:1;min-width:240px}
.radio-row{display:flex;gap:16px;margin-top:8px}
.radio-row label{font-weight:400;display:flex;align-items:center;gap:6px;cursor:pointer}
button{margin-top:16px;width:100%;background:#2563eb;color:#fff;border:0;padding:12px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}
button:hover{background:#1d4ed8}
.muted{color:#666;font-size:13px}
</style></head><body>
<h1>Aprovação de Arte</h1>
<div class="muted">${statusBadge} &nbsp; <strong>${escapeHtml(opts.titulo)}</strong></div>
${opts.descricao ? `<p class="muted">${escapeHtml(opts.descricao)}</p>` : ""}
<div class="card">${previewHtml}</div>
<form method="POST" class="card" action="">
  <strong>Sua decisão</strong>
  <div class="radio-row">
    <label><input type="radio" name="decisao" value="aprovado" required> Aprovar</label>
    <label><input type="radio" name="decisao" value="rejeitado"> Rejeitar</label>
  </div>
  <div class="row">
    <label>Nome
      <input type="text" name="nome" maxlength="255" required>
    </label>
    <label>Email
      <input type="email" name="email" maxlength="320">
    </label>
  </div>
  <label>Comentário (opcional)
    <textarea name="comentario" maxlength="2000" placeholder="Observações, ajustes, etc."></textarea>
  </label>
  <button type="submit">Enviar decisão</button>
</form>
</body></html>`;
}

function renderConfirmationHtml(decisao: "aprovado" | "rejeitado"): string {
  const aprovado = decisao === "aprovado";
  return `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Decisão registrada</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:520px;margin:48px auto;padding:24px;color:#111;text-align:center}
.box{background:${aprovado ? "#EAF3DE" : "#FCEBEB"};color:${aprovado ? "#27500A" : "#791F1F"};padding:24px;border-radius:12px}
h1{margin:0 0 8px;font-size:22px}</style></head><body>
<div class="box">
<h1>${aprovado ? "Arte aprovada!" : "Arte rejeitada"}</h1>
<p>Obrigado pela resposta. A equipe foi notificada.</p>
</div></body></html>`;
}

async function findArtworkByToken(token: string) {
  const db = await getDb();
  if (!db) return null;
  const rows = await db
    .select()
    .from(artworks)
    .where(eq(artworks.publicToken, token))
    .limit(1);
  return rows[0] ?? null;
}

export function registerArtworkApprovalRoutes(app: Express) {
  app.get("/a/:token", async (req: Request, res: Response) => {
    try {
      const token = String(req.params.token ?? "");
      if (!/^[a-f0-9]{16,64}$/i.test(token)) {
        res.status(404).type("html").send(notFoundHtml());
        return;
      }
      const artwork = await findArtworkByToken(token);
      if (!artwork) {
        res.status(404).type("html").send(notFoundHtml());
        return;
      }
      res
        .status(200)
        .type("html")
        .send(
          renderPageHtml({
            artworkId: artwork.id,
            titulo: artwork.titulo,
            descricao: artwork.descricao,
            fileKey: artwork.fileKey,
            mimeType: artwork.mimeType,
            status: artwork.status,
          }),
        );
    } catch (err) {
      console.error("[approval] GET error", err);
      res.status(500).type("html").send(notFoundHtml());
    }
  });

  app.post("/a/:token", async (req: Request, res: Response) => {
    try {
      const token = String(req.params.token ?? "");
      if (!/^[a-f0-9]{16,64}$/i.test(token)) {
        res.status(404).type("html").send(notFoundHtml());
        return;
      }
      const artwork = await findArtworkByToken(token);
      if (!artwork) {
        res.status(404).type("html").send(notFoundHtml());
        return;
      }
      const body = (req.body ?? {}) as Record<string, unknown>;
      const decisaoRaw = String(body.decisao ?? "");
      if (decisaoRaw !== "aprovado" && decisaoRaw !== "rejeitado") {
        res.status(400).type("html").send(notFoundHtml());
        return;
      }
      const decisao = decisaoRaw as "aprovado" | "rejeitado";
      const nome = body.nome ? String(body.nome).trim().slice(0, 255) : null;
      const email = body.email ? String(body.email).trim().slice(0, 320) : null;
      const comentario = body.comentario
        ? String(body.comentario).trim().slice(0, 2000)
        : null;
      const ip =
        (req.headers["x-forwarded-for"] as string | undefined)?.split(",")[0]?.trim() ??
        req.socket.remoteAddress ??
        null;

      const db = await getDb();
      if (!db) {
        res.status(503).type("html").send(notFoundHtml());
        return;
      }
      await db.transaction(async (tx) => {
        await tx.insert(artworkApprovals).values({
          id: randomUUID(),
          artworkId: artwork.id,
          decisao,
          comentario,
          aprovadorNome: nome,
          aprovadorEmail: email,
          ipAddress: ip,
        });
        await tx
          .update(artworks)
          .set({ status: decisao })
          .where(eq(artworks.id, artwork.id));
      });

      res.status(200).type("html").send(renderConfirmationHtml(decisao));
    } catch (err) {
      console.error("[approval] POST error", err);
      res.status(500).type("html").send(notFoundHtml());
    }
  });
}
