import { eq } from "drizzle-orm";

import { pushTokens } from "../drizzle/schema";
import { getDb } from "./db";

const EXPO_PUSH_ENDPOINT = "https://exp.host/--/api/v2/push/send";

export type PushPayload = {
  title: string;
  body: string;
  data?: Record<string, unknown>;
};

/**
 * Sends a push notification to all Expo tokens registered for a given user.
 *
 * Fire-and-forget: never throws. Logs failures via console.error so callers can
 * `.catch(() => {})` without further handling. Designed to be safe to invoke
 * without awaiting from request handlers.
 */
export async function sendPushToUser(
  userId: number,
  payload: PushPayload,
): Promise<void> {
  try {
    const db = await getDb();
    if (!db) {
      console.warn("[push] Database not available, skipping push");
      return;
    }

    const rows = await db
      .select()
      .from(pushTokens)
      .where(eq(pushTokens.userId, userId));

    if (rows.length === 0) return;

    const messages = rows.map((r) => ({
      to: r.token,
      title: payload.title,
      body: payload.body,
      data: payload.data,
      sound: "default" as const,
    }));

    const res = await fetch(EXPO_PUSH_ENDPOINT, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Accept-Encoding": "gzip, deflate",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(messages),
    });

    if (!res.ok) {
      const text = await res.text().catch(() => "<no body>");
      console.error(
        `[push] Expo push API returned ${res.status}: ${text.slice(0, 500)}`,
      );
      return;
    }

    // Parse tickets and clean up tokens the Expo service rejected as
    // DeviceNotRegistered (uninstalled / reset device). Ticket order matches
    // message order, so we can map back to the originating token.
    try {
      const json = (await res.json()) as {
        data?: Array<
          | { status: "ok"; id?: string }
          | {
              status: "error";
              message?: string;
              details?: { error?: string };
            }
        >;
      };
      const tickets = json.data ?? [];
      for (let i = 0; i < tickets.length; i++) {
        const ticket = tickets[i];
        if (
          ticket?.status === "error" &&
          ticket.details?.error === "DeviceNotRegistered"
        ) {
          const badToken = messages[i]?.to;
          if (!badToken) continue;
          try {
            await db.delete(pushTokens).where(eq(pushTokens.token, badToken));
            console.warn(
              `[push] Removed unregistered token: ${badToken.slice(0, 16)}…`,
            );
          } catch (delErr) {
            console.error(
              "[push] Failed to delete unregistered token:",
              delErr,
            );
          }
        }
      }
    } catch (parseErr) {
      console.error("[push] Failed to parse Expo response:", parseErr);
    }
  } catch (err) {
    console.error("[push] sendPushToUser failed:", err);
  }
}
