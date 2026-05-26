// sefaz-codes.ts — Dicionário canônico de códigos cstat SEFAZ → label/tone/hint.
//
// Determinístico, sem IA. Port do fiscal-page.jsx §SEFAZ_CODES.
// Referência: Manual de Orientação do Contribuinte NF-e v7.0 (CONFAZ).
//
// Tone:
//  - ok: autorizada / aceita (100, 150, 102, etc)
//  - warn: processando / requer atenção (999, 105, 108, 109)
//  - bad: rejeitada / denegada (110, 204, 220, 539, 691, 778, 217, 301)
//  - neutral: status raro / informativo

export type SefazTone = 'ok' | 'warn' | 'bad' | 'neutral';

export interface SefazCodeMeta {
  tone: SefazTone;
  label: string;
  hint: string;
}

export const SEFAZ_CODES: Record<number, SefazCodeMeta> = {
  100: { tone: 'ok', label: 'Autorizada', hint: 'NF-e autorizada com sucesso pela SEFAZ.' },
  101: { tone: 'ok', label: 'Cancelamento aceito', hint: 'Cancelamento da NF-e homologado.' },
  102: { tone: 'ok', label: 'Inutilização aceita', hint: 'Faixa de numeração inutilizada com sucesso.' },
  105: { tone: 'warn', label: 'Em processamento', hint: 'Lote em fila SEFAZ. Reconsulte em alguns segundos.' },
  108: { tone: 'warn', label: 'Serviço paralisado', hint: 'SEFAZ está temporariamente fora do ar. Aguardar.' },
  109: { tone: 'warn', label: 'Paralisada sem previsão', hint: 'Indisponibilidade SEFAZ — usar contingência (EPEC/SVC).' },
  110: { tone: 'bad', label: 'Uso denegado', hint: 'Destinatário com IE inválida/suspensa ou problema cadastral.' },
  150: { tone: 'ok', label: 'Autorizada fora do prazo', hint: 'Autorizada com emissão extemporânea (acima de 24h).' },
  204: { tone: 'bad', label: 'Duplicidade', hint: 'NF-e já autorizada anteriormente (mesmo número/série/CNPJ).' },
  217: { tone: 'bad', label: 'Chave não consta', hint: 'NF-e não encontrada na base SEFAZ.' },
  220: { tone: 'bad', label: 'NF-e numérica', hint: 'Erro no preenchimento numérico (valor/quantidade inválido).' },
  301: { tone: 'bad', label: 'Uso denegado (emit)', hint: 'CNPJ emitente inscrito como inapto.' },
  539: { tone: 'bad', label: 'Destinatário inválido', hint: 'CNPJ/CPF destinatário não confere com cadastro.' },
  691: { tone: 'bad', label: 'Item rejeitado', hint: 'Erro em item da NF-e (NCM, CFOP, CST inválido).' },
  778: { tone: 'bad', label: 'XML inválido', hint: 'XML não passou na validação schema PL_009.' },
  999: { tone: 'warn', label: 'Processando SEFAZ', hint: 'Aguardando retorno do webservice. Reconsulte em ~10s.' },
};

export function sefazTone(code: number | string): SefazTone {
  const n = typeof code === 'number' ? code : parseInt(code, 10);
  return SEFAZ_CODES[n]?.tone ?? 'neutral';
}

export function sefazLabel(code: number | string): string {
  const n = typeof code === 'number' ? code : parseInt(code, 10);
  return SEFAZ_CODES[n]?.label ?? `Status ${code}`;
}

export function sefazHint(code: number | string): string {
  const n = typeof code === 'number' ? code : parseInt(code, 10);
  return SEFAZ_CODES[n]?.hint ?? 'Código SEFAZ não-mapeado. Consulte o Manual NF-e CONFAZ.';
}
