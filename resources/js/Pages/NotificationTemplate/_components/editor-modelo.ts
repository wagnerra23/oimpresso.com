// Regras puras do editor de modelos (US-NOTIF-004). Sem lista fixa de modelos: tudo deriva
// do payload do controller (`extra_tags` de cada modelo, inclusive os injetados por módulo).

export type Campo = 'subject' | 'email_body' | 'sms_body' | 'whatsapp_text' | 'cc' | 'bcc';
export type Auto = 'auto_send' | 'auto_send_sms' | 'auto_send_wa_notif';
export interface Valores {
  subject: string; email_body: string; sms_body: string; whatsapp_text: string; cc: string; bcc: string;
  auto_send: number; auto_send_sms: number; auto_send_wa_notif: number;
}

// Paridade com a Blade (partials/tabs.blade.php): só estes dois modelos mostram os automáticos;
// nos demais o POST da Blade manda checkbox ausente = 0, e aqui também.
export const COM_AUTOMATICO = ['new_sale', 'payment_reminder'];

const CAMPOS: Campo[] = ['subject', 'email_body', 'sms_body', 'whatsapp_text', 'cc', 'bcc'];
const AUTOS: Auto[] = ['auto_send', 'auto_send_sms', 'auto_send_wa_notif'];

export function valoresDe(modelo: Record<string, unknown>): Valores {
  const v = {} as Valores;
  for (const c of CAMPOS) v[c] = typeof modelo[c] === 'string' ? (modelo[c] as string) : '';
  for (const a of AUTOS) v[a] = Number(modelo[a]) === 1 ? 1 : 0;
  return v;
}

export function iguais(a: Valores, b: Valores): boolean {
  return [...CAMPOS, ...AUTOS].every((k) => a[k] === b[k]);
}

/** Corpo do POST: só os modelos alterados, no mesmo `template_data` que a Blade usa. */
export function payload(alterados: Record<string, Valores>): { template_data: Record<string, Valores> } {
  const template_data: Record<string, Valores> = {};
  for (const [key, v] of Object.entries(alterados)) {
    template_data[key] = COM_AUTOMATICO.includes(key) ? { ...v } : { ...v, auto_send: 0, auto_send_sms: 0, auto_send_wa_notif: 0 };
  }
  return { template_data };
}

/** Grupos de tags vindos do `extra_tags`; formato desconhecido de módulo vira lista vazia. */
export function gruposDeTags(extra: unknown): string[][] {
  if (!Array.isArray(extra)) return [];
  return extra.map((g) => (Array.isArray(g) ? g.filter((t): t is string => typeof t === 'string') : []))
    .filter((g) => g.length > 0);
}

export const ehPersonalizado = (tag: string) => /_custom_field_\d+\}$/.test(tag);

export function tagsDesconhecidas(texto: string, permitidas: string[][]): string[] {
  const set = new Set(permitidas.flat());
  const achadas = texto.match(/\{[a-z_0-9]+\}/g) ?? [];
  return [...new Set(achadas.filter((t) => !set.has(t)))];
}

// GSM-7: fora deste conjunto a operadora codifica em UCS-2 e o segmento cai de 160 para 70.
const GSM7 = /^[A-Za-z0-9 \r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-./:;<=>?¡ÄÖÑÜ§¿äöñüà^{}[\]~|€]*$/;

export function segmentosSms(texto: string): { limite: 160 | 70; segmentos: number; acento: boolean } {
  const acento = !GSM7.test(texto);
  const limite = acento ? 70 : 160;
  return { limite, segmentos: Math.max(1, Math.ceil(texto.length / limite)), acento };
}

/** Insere `tag` na seleção [ini, fim) e devolve o texto novo e a posição do cursor depois dela. */
export function inserirNaSelecao(texto: string, tag: string, ini: number, fim: number): { texto: string; cursor: number } {
  const a = Math.max(0, Math.min(ini, texto.length));
  const b = Math.max(a, Math.min(fim, texto.length));
  return { texto: texto.slice(0, a) + tag + texto.slice(b), cursor: a + tag.length };
}
