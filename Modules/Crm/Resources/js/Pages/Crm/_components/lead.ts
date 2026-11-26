// Selos da lista e da gaveta de leads (pendência CRM-22), tirados do `crm-blade.jsx`.

import type { TomPilula } from './CrmBlocos';

/**
 * Tom do estágio de vida (`TOM` do crm-blade.jsx): Novo/Contatado = destaque, Qualificado/Proposta/
 * Negociação = aviso, Cliente = sucesso, Perdido = negativo. Os estágios são categorias do negócio
 * com nome livre, então o tom sai do nome; estágio que não bate fica neutro.
 */
export function tomDoEstagio(nome: string | null): TomPilula {
  const n = (nome ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  if (/^(novo|contatado)/.test(n)) return 'info';
  if (/^(qualificado|proposta|negociacao)/.test(n)) return 'warn';
  if (/^cliente/.test(n)) return 'ok';
  if (/^perdido/.test(n)) return 'neg';
  return 'mute';
}

/**
 * Frescor do último acompanhamento (`StatusBadge kind="frescor"` do DS): até 2 dias recente
 * (sucesso), até 7 morno (aviso), mais que isso frio (negativo). A data chega "dd/mm/aaaa hh:mm".
 */
export function frescor(data: string | null, agora = new Date()): { texto: string; tom: TomPilula } | null {
  const m = data?.match(/^(\d{2})\/(\d{2})\/(\d{4})(?: (\d{2}):(\d{2}))?/);
  if (!m) return null;
  const quando = new Date(Number(m[3]), Number(m[2]) - 1, Number(m[1]), Number(m[4] ?? 0), Number(m[5] ?? 0));
  const horas = Math.max(0, (agora.getTime() - quando.getTime()) / 36e5);
  const dias = Math.floor(horas / 24);
  const texto = horas < 1 ? 'agora' : horas < 24 ? `há ${Math.floor(horas)}h` : `há ${dias}d`;
  return { texto, tom: dias <= 2 ? 'ok' : dias <= 7 ? 'warn' : 'neg' };
}

const AJAX = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

/** Rotas da Blade que a tela nova reaproveita (`lead/{id}/post-life-stage`). */
async function chamar(url: string): Promise<boolean> {
  try {
    const r = await fetch(url, { headers: AJAX, credentials: 'same-origin' });
    const json = r.ok ? await r.json() : null;
    return !!json?.success;
  } catch {
    return false;
  }
}

/** CRM-23: muda o estágio de vida do lead (o arrasto no kanban). */
export function moverEstagio(id: number, estagioId: string): Promise<boolean> {
  return chamar(`/crm/lead/${id}/post-life-stage?crm_life_stage=${encodeURIComponent(estagioId)}`);
}

