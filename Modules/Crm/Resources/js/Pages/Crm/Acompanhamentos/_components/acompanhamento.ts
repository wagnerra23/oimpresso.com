// Tipos e valores do modal de acompanhamento (thread Crm/07). Arquivo separado do componente
// para o react-refresh: módulo de componente só exporta componente.

export interface Opcao { value: string; label: string }
export interface Valores {
  title: string; contact_id: string; status: string; start_datetime: string; end_datetime: string;
  description: string; schedule_type: string; followup_category_id: string; user_id: string[];
  allow_notification: boolean; notify_via: { sms: boolean; mail: boolean };
  notify_before: number | string | null; notify_type: string;
}
export interface Listas { contatos?: Opcao[]; usuarios?: Opcao[]; status?: Opcao[]; tipos?: Opcao[]; categorias?: Opcao[]; notificar?: Opcao[] }

export const NOVO: Valores = {
  title: '', contact_id: '', status: 'scheduled', start_datetime: '', end_datetime: '', description: '',
  schedule_type: 'call', followup_category_id: '', user_id: [], allow_notification: false,
  notify_via: { sms: false, mail: true }, notify_before: 30, notify_type: 'minute',
};

export function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}
