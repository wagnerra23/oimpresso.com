import AsyncStorage from "@react-native-async-storage/async-storage";
import { getUntypedClient, type TRPCUntypedClient } from "@trpc/client";
import type { AppRouter } from "@/server/routers";

/**
 * F3-04 Offline-First — local mutation queue.
 *
 * When the device is offline (or a network error otherwise prevents a tRPC
 * mutation from completing) the global `mutationCache.onError` handler in
 * `app/_layout.tsx` enqueues the mutation here. A replay loop drains the
 * queue on reconnect and on a 60s timer.
 *
 * Storage layout: one AsyncStorage key per active company so different
 * tenants on the same device don't share pending writes.
 */

export type QueuedMutation = {
  id: string;
  /** tRPC dotted path, e.g. "produtos.create". */
  path: string;
  input: unknown;
  createdAt: string;
  attemptedAt?: string;
  error?: string;
};

const KEY_PREFIX = "mutation-queue:";

function keyFor(companyId: string | null | undefined): string {
  return `${KEY_PREFIX}${companyId ?? "anon"}`;
}

// In-memory write lock so concurrent enqueues don't clobber each other.
let writeChain: Promise<unknown> = Promise.resolve();
function serialized<T>(fn: () => Promise<T>): Promise<T> {
  const next = writeChain.then(fn, fn);
  writeChain = next.catch(() => undefined);
  return next;
}

function genId(): string {
  // Lightweight unique id — `crypto.randomUUID` is not guaranteed in RN runtime.
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Paths whose inputs must carry an `idempotencyKey` so server-side replay of
 * the offline queue doesn't double-charge / double-emit. Server dedupes on
 * (companyId, idempotencyKey) for payments and uses providerRef for fiscal.
 */
const IDEMPOTENT_PATHS = new Set<string>([
  "payments.create",
  "fiscal.documents.emit",
]);

function genIdempotencyKey(): string {
  // 36-char UUIDv4-shaped string; fits the server's 64-char column comfortably.
  // RN runtimes (Hermes ≥ 0.74) expose `crypto.randomUUID`; we fall back to
  // a Math.random hex blob if it isn't available — still unique per call.
  const cryptoApi =
    (globalThis as { crypto?: { randomUUID?: () => string } }).crypto;
  if (cryptoApi?.randomUUID) return cryptoApi.randomUUID();
  const rand = () =>
    Math.floor(Math.random() * 0xffffffff)
      .toString(16)
      .padStart(8, "0");
  return `${rand()}-${rand()}-${rand()}-${rand()}`;
}

/**
 * Inject `idempotencyKey` into the input payload for paths that need it.
 * No-op when the path isn't idempotency-sensitive or when the input already
 * carries a key (caller-provided keys win).
 */
export function decorateMutationForIdempotency(
  path: string,
  input: unknown,
): unknown {
  if (!IDEMPOTENT_PATHS.has(path)) return input;
  if (input === null || typeof input !== "object") {
    return { idempotencyKey: genIdempotencyKey() };
  }
  const obj = input as Record<string, unknown>;
  if (typeof obj.idempotencyKey === "string" && obj.idempotencyKey.length >= 8) {
    return input;
  }
  return { ...obj, idempotencyKey: genIdempotencyKey() };
}

export async function getQueue(
  companyId: string | null | undefined,
): Promise<QueuedMutation[]> {
  try {
    const raw = await AsyncStorage.getItem(keyFor(companyId));
    if (!raw) return [];
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) return [];
    return parsed as QueuedMutation[];
  } catch {
    return [];
  }
}

async function writeQueue(
  companyId: string | null | undefined,
  queue: QueuedMutation[],
): Promise<void> {
  await AsyncStorage.setItem(keyFor(companyId), JSON.stringify(queue));
}

export async function enqueueMutation(
  companyId: string | null | undefined,
  item: Omit<QueuedMutation, "id" | "createdAt"> &
    Partial<Pick<QueuedMutation, "id" | "createdAt">>,
): Promise<QueuedMutation> {
  return serialized(async () => {
    const queue = await getQueue(companyId);
    const entry: QueuedMutation = {
      id: item.id ?? genId(),
      path: item.path,
      // Stamp an idempotencyKey into the payload BEFORE persisting so that on
      // replay the server can dedupe — same item retried N times stays a single
      // server-side charge / fiscal emission.
      input: decorateMutationForIdempotency(item.path, item.input),
      createdAt: item.createdAt ?? new Date().toISOString(),
      attemptedAt: item.attemptedAt,
      error: item.error,
    };
    queue.push(entry);
    await writeQueue(companyId, queue);
    return entry;
  });
}

export async function clearMutation(
  companyId: string | null | undefined,
  id: string,
): Promise<void> {
  await serialized(async () => {
    const queue = await getQueue(companyId);
    const next = queue.filter((m) => m.id !== id);
    await writeQueue(companyId, next);
  });
}

export async function clearAllForCompany(
  companyId: string | null | undefined,
): Promise<void> {
  try {
    await AsyncStorage.removeItem(keyFor(companyId));
  } catch {
    /* non-fatal */
  }
}

/**
 * Drain the queue for the given company against the supplied tRPC client.
 *
 * Returns counts the UI can surface. Each item is tried at most once per
 * replay cycle — on failure we update `attemptedAt`/`error` and keep it so
 * the next cycle (timer or reconnect) tries again.
 *
 * Concurrency: only one replay runs at a time per process.
 */
let replayInFlight = false;

export async function replayQueue(
  companyId: string | null | undefined,
  trpcReactClient: unknown,
): Promise<{ ok: number; failed: number; remaining: number }> {
  if (replayInFlight) return { ok: 0, failed: 0, remaining: -1 };
  replayInFlight = true;
  try {
    const queue = await getQueue(companyId);
    if (queue.length === 0) return { ok: 0, failed: 0, remaining: 0 };

    // Extract the untyped client (works whether caller passed the React client
    // or an already-untyped one).
    let untyped: TRPCUntypedClient<AppRouter>;
    try {
      untyped = getUntypedClient(trpcReactClient as never);
    } catch {
      // Already untyped — fall back to a duck-typed cast.
      untyped = trpcReactClient as TRPCUntypedClient<AppRouter>;
    }

    let ok = 0;
    let failed = 0;

    for (const item of queue) {
      try {
        await untyped.mutation(item.path, item.input);
        await clearMutation(companyId, item.id);
        ok += 1;
      } catch (err) {
        failed += 1;
        // Update attemptedAt/error on the persisted record but keep it.
        await serialized(async () => {
          const fresh = await getQueue(companyId);
          const next = fresh.map((m) =>
            m.id === item.id
              ? {
                  ...m,
                  attemptedAt: new Date().toISOString(),
                  error: err instanceof Error ? err.message : String(err),
                }
              : m,
          );
          await writeQueue(companyId, next);
        });
        // If it's a transient network error we stop the cycle early so we
        // don't spam failures; non-network errors keep cycling (they may need
        // user intervention).
        if (isNetworkError(err)) break;
      }
    }

    const remaining = (await getQueue(companyId)).length;
    return { ok, failed, remaining };
  } finally {
    replayInFlight = false;
  }
}

export function isNetworkError(err: unknown): boolean {
  if (err instanceof TypeError && /network|fetch/i.test(err.message)) return true;
  if (err && typeof err === "object" && "message" in err) {
    const msg = String((err as { message: unknown }).message ?? "");
    if (/network|fetch failed|econn|offline|timeout|aborted/i.test(msg))
      return true;
  }
  // tRPC wraps the original under `cause`.
  if (err && typeof err === "object" && "cause" in err) {
    return isNetworkError((err as { cause: unknown }).cause);
  }
  return false;
}
