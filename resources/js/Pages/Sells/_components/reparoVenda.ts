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
  /** UC-S06 — item do checklist → resposta. Itens ausentes contam como "não se aplica". */
  checklist: Record<string, ChecklistValor>;
  repair_security_pwd: string;
  /** Sequência de pontos da grade 3×3 (1–9), o formato do patternlock.js do POS Blade. */
  repair_security_pattern: string;
};

export type ChecklistValor = 'yes' | 'no' | 'not_applicable';

export type ModeloAparelho = {
  id: number;
  name: string;
  brand_id: number | null;
  device_id: number | null;
  checklist: string[];
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
    checklist: {},
    repair_security_pwd: '',
    repair_security_pattern: '',
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
export function camposDeReparo(
  r: ReparoForm,
  itensChecklist: string[] = [],
): Record<string, string | number | Record<string, ChecklistValor>> {
  const campos: Record<string, string | number | Record<string, ChecklistValor>> = {};
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
  // UC-S06 — senha/padrão só quando preenchidos; checklist só quando há itens exibidos.
  if (r.repair_security_pwd !== '') campos.repair_security_pwd = r.repair_security_pwd;
  if (r.repair_security_pattern !== '') campos.repair_security_pattern = r.repair_security_pattern;
  if (itensChecklist.length > 0) campos.repair_checklist = checklistParaEnvio(itensChecklist, r.checklist);
  return campos;
}

/**
 * Modelos que cabem na marca/aparelho escolhidos (o Blade recarrega a lista via
 * /repair/get-device-models ao trocar marca ou aparelho). Sem nenhum dos dois: todos.
 */
export function modelosFiltrados(
  modelos: ModeloAparelho[],
  brandId: number | null,
  deviceId: number | null,
): ModeloAparelho[] {
  return modelos.filter(
    (m) => (deviceId === null || m.device_id === deviceId) && (brandId === null || m.brand_id === brandId),
  );
}

/** Itens do checklist: os padrão das configurações, depois os do modelo (ordem do Blade), sem repetir. */
export function itensDoChecklist(padrao: string[], modelo: ModeloAparelho | undefined): string[] {
  return Array.from(new Set([...padrao, ...(modelo?.checklist ?? [])]));
}

/** Toca um ponto da grade: entra no fim da sequência; ponto já usado é ignorado. */
export function tocarPonto(padrao: string, ponto: number): string {
  if (ponto < 1 || ponto > 9) return padrao;
  const p = String(ponto);
  return padrao.includes(p) ? padrao : padrao + p;
}

/**
 * Checklist no formato que o `after_sale_saved` grava (json_encode do array do form):
 * TODOS os itens exibidos, com "não se aplica" onde não houve resposta — igual ao Blade,
 * que marca `not_applicable` por padrão.
 */
export function checklistParaEnvio(
  itens: string[],
  respostas: Record<string, ChecklistValor>,
): Record<string, ChecklistValor> {
  const out: Record<string, ChecklistValor> = {};
  for (const item of itens) out[item] = respostas[item] ?? 'not_applicable';
  return out;
}
