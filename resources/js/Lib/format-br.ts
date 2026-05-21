// Helpers de formatação BR — máscaras dinâmicas pra inputs.
// Slice 2 da restauração dos campos fiscais BR (PR pós #1313).
//
// Não fazem validação mod-11 — Rule\BR\CpfCnpj cuida disso no backend.
// Aqui só aplica/remove máscara visual.

/**
 * Retorna apenas dígitos do valor (remove pontuação, espaços, etc).
 *   "123.456.789-09" → "12345678909"
 */
export function unmaskDigits(value: string | null | undefined): string {
  if (!value) return '';
  return value.replace(/\D/g, '');
}

/**
 * Máscara dinâmica CPF (11 dígitos) ou CNPJ (14 dígitos).
 *   "12345678909"     → "123.456.789-09"   (CPF)
 *   "12345678000195"  → "12.345.678/0001-95" (CNPJ)
 *   "123"             → "123"               (incomplete, no mask)
 *
 * Detecção pelo comprimento numérico:
 *   ≤ 11 dígitos → formata progressivamente como CPF
 *   > 11 dígitos → formata progressivamente como CNPJ (até 14)
 *
 * Trunca em 14 dígitos (máx CNPJ).
 */
export function formatCpfCnpj(value: string | null | undefined): string {
  const digits = unmaskDigits(value).slice(0, 14);
  if (digits.length === 0) return '';

  // CPF: até 11 dígitos
  if (digits.length <= 11) {
    return digits
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
  }

  // CNPJ: 12 a 14 dígitos
  return digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
}

/**
 * Máscara CEP — 8 dígitos → "00000-000"
 */
export function formatCep(value: string | null | undefined): string {
  const digits = unmaskDigits(value).slice(0, 8);
  if (digits.length === 0) return '';
  if (digits.length <= 5) return digits;
  return digits.replace(/^(\d{5})(\d)/, '$1-$2');
}

/**
 * Máscara telefone BR — (XX) XXXX-XXXX ou (XX) 9XXXX-XXXX.
 *
 *   "11999998888"  → "(11) 99999-8888"   (celular 11d)
 *   "1133334444"   → "(11) 3333-4444"    (fixo 10d)
 *   "11"           → "(11) "
 */
export function formatPhone(value: string | null | undefined): string {
  const digits = unmaskDigits(value).slice(0, 11);
  if (digits.length === 0) return '';
  if (digits.length <= 2) return `(${digits}`;
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  if (digits.length <= 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
  }
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

/**
 * Labels canônicos do `indicador_ie` (NFe SEFAZ).
 * 1 = Contribuinte ICMS regularmente inscrito
 * 2 = Contribuinte isento de inscrição estadual
 * 9 = Não contribuinte (pessoa física, etc)
 */
export const INDICADOR_IE_OPTIONS: Array<{ value: string; label: string }> = [
  { value: '', label: '— Selecione —' },
  { value: '1', label: '1 — Contribuinte ICMS' },
  { value: '2', label: '2 — Contribuinte isento de inscrição' },
  { value: '9', label: '9 — Não contribuinte' },
];

/**
 * Labels canônicos do `regime` tributário.
 */
export const REGIME_TRIBUTARIO_OPTIONS: Array<{ value: string; label: string }> = [
  { value: '', label: '— Selecione —' },
  { value: 'simples', label: 'Simples Nacional' },
  { value: 'presumido', label: 'Lucro Presumido' },
  { value: 'real', label: 'Lucro Real' },
  { value: 'mei', label: 'MEI' },
];
