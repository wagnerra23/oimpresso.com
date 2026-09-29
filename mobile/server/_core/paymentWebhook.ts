/**
 * F3-03 — Asaas webhook receiver.
 *
 * Asaas POSTs JSON events of the form:
 *   { event: "PAYMENT_RECEIVED", payment: { id, status, value, netValue, paymentDate, ... } }
 *
 * Configure the URL `https://<host>/api/asaas/webhook` in the Asaas dashboard
 * (Menu → Integrações → Webhooks). Set the same token on both sides via the
 * env var `ASAAS_WEBHOOK_TOKEN`. When unset we accept all traffic (dev mode)
 * and emit a single warning per process so the operator notices.
 */
import type { Express, Request, Response } from "express";
import { randomUUID } from "node:crypto";
import { eq } from "drizzle-orm";

import { paymentLinks, webhookEvents } from "../../drizzle/schema";
import { getDb } from "../db";
import { mapAsaasEvent, mapAsaasStatus } from "./asaas";

/** MySQL ER_DUP_ENTRY code surfaced as `errno` by mysql2. */
function isDuplicateKeyError(err: unknown): boolean {
  if (!err || typeof err !== "object") return false;
  const e = err as { errno?: number; code?: string };
  return e.errno === 1062 || e.code === "ER_DUP_ENTRY";
}

let warnedNoToken = false;

export function registerAsaasWebhook(app: Express) {
  app.post("/api/asaas/webhook", async (req: Request, res: Response) => {
    const expected = process.env.ASAAS_WEBHOOK_TOKEN;
    if (expected) {
      const received = req.header("asaas-access-token");
      if (received !== expected) {
        res.status(401).json({ ok: false, error: "invalid token" });
        return;
      }
    } else if (!warnedNoToken) {
      warnedNoToken = true;
      console.warn(
        "[asaas] ASAAS_WEBHOOK_TOKEN not set — accepting all webhook calls (dev mode).",
      );
    }

    const body = req.body ?? {};
    const event: string = String(body.event ?? "");
    const payment = body.payment ?? {};
    const providerPaymentId: string | undefined = payment?.id
      ? String(payment.id)
      : undefined;

    if (!providerPaymentId) {
      // Acknowledge so Asaas doesn't keep retrying; nothing actionable here.
      res.json({ ok: true, ignored: "missing payment.id" });
      return;
    }

    const status =
      mapAsaasEvent(event) ?? mapAsaasStatus(String(payment?.status ?? ""));

    try {
      const db = await getDb();
      if (!db) {
        console.warn("[asaas] webhook received but DB not configured");
        res.json({ ok: true, persisted: false });
        return;
      }

      // Idempotency gate: insert a webhookEvents row before processing. If the
      // unique (provider, eventType, externalId) constraint trips, this is a
      // duplicate delivery — ACK with 200 so Asaas stops retrying.
      try {
        await db.insert(webhookEvents).values({
          id: randomUUID(),
          provider: "asaas",
          eventType: event || "UNKNOWN",
          externalId: providerPaymentId,
          payload: JSON.stringify(body).slice(0, 60000),
        });
      } catch (dupErr) {
        if (isDuplicateKeyError(dupErr)) {
          console.log(
            `[webhook] duplicate event ignored event=${event} payment=${providerPaymentId}`,
          );
          res.json({ ok: true, duplicate: true });
          return;
        }
        throw dupErr;
      }

      const update: Record<string, unknown> = {
        status,
        providerResponse: JSON.stringify(body).slice(0, 60000),
      };
      if (status === "pago") {
        update.pagoEm = payment.paymentDate
          ? String(payment.paymentDate)
          : new Date().toISOString();
        if (payment.netValue !== undefined && payment.netValue !== null) {
          update.netValue = Number(payment.netValue).toFixed(2);
        }
      }

      await db
        .update(paymentLinks)
        .set(update)
        .where(eq(paymentLinks.providerPaymentId, providerPaymentId));

      console.log(
        `[asaas] webhook ${event} -> ${providerPaymentId} status=${status}`,
      );
      res.json({ ok: true, status });
    } catch (err) {
      console.error("[asaas] webhook error", err);
      // Still 200 so Asaas doesn't enter retry storm on our internal bug.
      res.json({ ok: false, error: (err as Error).message });
    }
  });
}
