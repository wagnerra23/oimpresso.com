// Fila offline do apontamento (UC-PCV-23 · E04 A8 · [W] 2026-10-15: "Apontamento pode ficar numa fila
// sem internet e ser enviado depois, sem duplicar"). Cada toque nasce com a sua `chave` e a hora do
// aparelho; o reenvio manda o MESMO corpo, e o servidor devolve a linha já gravada em vez de duplicar.
// Chave de localStorage com prefixo `oimpresso.<modulo>.` e por usuário (ADR 0093).

export interface Envio { url: string; body: Record<string, unknown> }
export type Resposta = { ok: true; status: number; json: Record<string, unknown> } | { ok: false; status: number; json: Record<string, unknown> } | { ok: 'fila' };

const chaveDaFila = (userId: number) => `oimpresso.producao.fila.${userId}`;

function ler(userId: number): Envio[] {
  try { return JSON.parse(localStorage.getItem(chaveDaFila(userId)) ?? '[]') as Envio[]; } catch { return []; }
}

function gravar(userId: number, fila: Envio[]): void {
  try { localStorage.setItem(chaveDaFila(userId), JSON.stringify(fila)); } catch { /* armazenamento indisponível: segue sem fila */ }
}

export function novaChave(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/** Hora do toque em ISO com fuso: o servidor converte para o dele. */
export function agoraIso(): string {
  const d = new Date();
  const off = -d.getTimezoneOffset();
  const sinal = off >= 0 ? '+' : '-';
  const pad = (n: number) => String(Math.floor(Math.abs(n))).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}${sinal}${pad(off / 60)}:${pad(off % 60)}`;
}

async function post(envio: Envio): Promise<Response> {
  const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
  return fetch(envio.url, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(envio.body),
  });
}

/** Envia agora; sem rede, guarda na fila e devolve `{ ok: 'fila' }`. */
export async function enviar(userId: number, envio: Envio): Promise<Resposta> {
  try {
    const r = await post(envio);
    const json = (await r.json().catch(() => ({}))) as Record<string, unknown>;
    return { ok: r.ok, status: r.status, json } as Resposta;
  } catch {
    gravar(userId, [...ler(userId), envio]);
    return { ok: 'fila' };
  }
}

/** Esvazia a fila na ordem dos toques. Para no primeiro erro de rede; recusa do servidor sai da fila. */
export async function esvaziar(userId: number, aoRecusar: (json: Record<string, unknown>) => void): Promise<number> {
  const fila = ler(userId);
  let enviados = 0;
  for (let atual = fila[0]; atual; atual = fila[0]) {
    try {
      const r = await post(atual);
      if (!r.ok) aoRecusar((await r.json().catch(() => ({}))) as Record<string, unknown>);
    } catch {
      break;
    }
    fila.shift();
    enviados++;
    gravar(userId, fila);
  }
  return enviados;
}

export const pendentes = (userId: number): number => ler(userId).length;
