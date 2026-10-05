// Tipos e valores do modal de acompanhamento (thread Crm/07). Arquivo separado do componente
// para o react-refresh: módulo de componente só exporta componente.

export interface Opcao { value: string; label: string }
export interface Valores {
  title: string; contact_id: string; status: string; start_datetime: string; end_datetime: string;
  description: string; schedule_type: string; followup_category_id: string; user_id: string[];
  allow_notification: boolean; notify_via: { sms: boolean; mail: boolean };
  notify_before: number | string | null; notify_type: string;
}
export interface Listas { contatos?: Opcao[]; usuarios?: Opcao[]; status?: Opcao[]; tipos?: Opcao[]; categorias?: Opcao[]; notificar?: Opcao[]; recorrencia?: (Opcao & { grupo: string })[] }

export const NOVO: Valores = {
  title: '', contact_id: '', status: 'scheduled', start_datetime: '', end_datetime: '', description: '',
  schedule_type: 'call', followup_category_id: '', user_id: [], allow_notification: false,
  notify_via: { sms: false, mail: true }, notify_before: 30, notify_type: 'minute',
};

export function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

// Recorrente (thread Crm/07, PR-b): sem contato nem datas; roda a cada `recursion_days` sobre o
// grupo `follow_up_by` (payment_status | orders). Mesmos campos da Blade create_recursive_follow_up.
export type Recorrente = Omit<Valores, 'contact_id' | 'start_datetime' | 'end_datetime'> & {
  follow_up_by: string; follow_up_by_value: string; recursion_days: number | string | null;
};

export const NOVO_RECORRENTE: Recorrente = {
  title: '', status: 'scheduled', description: '', schedule_type: 'call', followup_category_id: '', user_id: [],
  allow_notification: false, notify_via: { sms: false, mail: true }, notify_before: 1, notify_type: 'hour',
  follow_up_by: '', follow_up_by_value: '', recursion_days: '',
};

export type Resposta = { ok: boolean; status: number; json: { success?: boolean; msg?: string; errors?: Record<string, string[]> } };

/** Grava pelas rotas da Blade, que respondem `{success, msg}` a pedido ajax (422 traz `errors`). */
export async function enviar(url: string, method: 'POST' | 'PUT', corpo: unknown): Promise<Resposta> {
  const r = await fetch(url, {
    method,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(corpo),
  });
  return { ok: r.ok, status: r.status, json: await r.json().catch(() => ({})) };
}
