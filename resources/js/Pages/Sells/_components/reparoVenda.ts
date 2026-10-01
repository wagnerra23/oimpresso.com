/**
 * Seção "Reparo" do Sells/Create (UC-S05) — o que a venda de reparo envia além da venda.
 *
 * Paridade com o parcial Blade `repair::repair.partials.repair_pos` (o POS de reparo):
 * os mesmos nomes de campo, gravados pelo `after_sale_saved` do Repair. Nada aqui toca
 * valor nem estoque — são dados do aparelho e do atendimento.
 *
 * Formatos que o servidor espera, medidos no consumidor e não no form:
 * - datas: "DD/MM/YYYY HH:mm" (o `after_sale_saved` passa por `uf_date`, igual à data da venda);
 * - defeitos: JSON do Tagify `[{"value":"..."}]` — é o que `show.blade.php` e o recibo
 *   (`common_repair_invoice.blade.php`) fazem `json_decode`. Texto solto lá vira vazio.
 */
export type ReparoForm = {
  repair_status_id: number | null;
  repair_brand_id: number | null;
  repair_device_id: number | null;
  repair_model_id: number | null;
  repair_warranty_id: number | null;
  repair_serial_no: string;
  /** valor do input datetime-local: "YYYY-MM-DDTHH:mm" ou "" */
  repair_due_date: string;
  repair_completed_on: string;
  defeitos: string[];
};

export function reparoInicial(defaultStatusId: number | null | undefined): ReparoForm {
  return {
    repair_status_id: defaultStatusId ?? null,
    repair_brand_id: null,
    repair_device_id: null,
    repair_model_id: null,
    repair_warranty_id: null,
    repair_serial_no: '',
    repair_due_date: '',
    repair_completed_on: '',
    defeitos: [],
  };
}

/** "YYYY-MM-DDTHH:mm" → "DD/MM/YYYY HH:mm"; vazio ou fora do formato → null. */
export function dataParaServidor(local: string): string | null {
  const m = local.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
  return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : null;
}

/** Lista de defeitos → JSON do Tagify, sem vazios nem repetidos. */
export function defeitosParaTagify(defeitos: string[]): string {
  const limpos = Array.from(new Set(defeitos.map((d) => d.trim()).filter((d) => d !== '')));
  return JSON.stringify(limpos.map((value) => ({ value })));
}

/** Acrescenta um defeito digitado (aceita vários separados por vírgula). */
export function adicionarDefeitos(atuais: string[], digitado: string): string[] {
  const novos = digitado.split(',').map((d) => d.trim()).filter((d) => d !== '');
  return Array.from(new Set([...atuais, ...novos]));
}

/** O status é obrigatório no POS Blade (`<select required>`); o resto é opcional. */
export function reparoValido(r: ReparoForm): boolean {
  return r.repair_status_id !== null;
}

/** Campos que o envio leva. Opcionais vazios não vão — o servidor mantém o que já tem. */
export function camposDeReparo(r: ReparoForm): Record<string, string | number> {
  const campos: Record<string, string | number> = {};
  const ids = ['repair_status_id', 'repair_brand_id', 'repair_device_id', 'repair_model_id', 'repair_warranty_id'] as const;
  for (const k of ids) {
    if (r[k] !== null) campos[k] = r[k] as number;
  }
  if (r.repair_serial_no.trim() !== '') campos.repair_serial_no = r.repair_serial_no.trim();
  const entrega = dataParaServidor(r.repair_due_date);
  if (entrega) campos.repair_due_date = entrega;
  const concluido = dataParaServidor(r.repair_completed_on);
  if (concluido) campos.repair_completed_on = concluido;
  if (r.defeitos.length > 0) campos.repair_defects = defeitosParaTagify(r.defeitos);
  return campos;
}
