// Wave C-FE — máscaras BR (CPF/CNPJ/tel/CEP) pro drawer 760 do Cliente.
//
// Refs: ADR 0179 (drawer 760) · Charter Index.charter.md v3 · HANDOFF_CLIENTES.md §2
// Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-icons.jsx (BRMask)
//
// IMPORTANTE: estas funções NÃO validam — só formatam visualmente (progressivo).
// Validação mod 11 + email/CEP vivem em `@/Lib/br-validate.ts` e backend
// `Rule\BR\CpfCnpj` (skill multi-tenant-patterns + ADR 0093 Tier 0 server-side).
//
// Coexiste com `@/Lib/format-br.ts` (Slice 2 fiscal BR — PR pós #1313); este
// arquivo é Wave C-FE específico do drawer paradigma 760 (PT-BR-only, sem
// indicador_ie/regime tributário). Não duplica; reusa internamente.

import { unmaskDigits, formatCpfCnpj, formatCep, formatPhone } from './format-br';

/**
 * Apenas dígitos. Atalho mantido pro código que ainda usa BRMask.onlyDigits.
 *   "(11) 9 8888-7777" → "11988887777"
 */
export function onlyDigits(value: string | null | undefined): string {
  return unmaskDigits(value);
}

/**
 * Máscara CPF — `000.000.000-00` (progressiva conforme digitação).
 * Trunca em 11 dígitos.
 *
 *   "123456789" → "123.456.789"
 *   "12345678909" → "123.456.789-09"
 */
export function maskCPF(value: string | null | undefined): string {
  const d = unmaskDigits(value).slice(0, 11);
  if (d.length === 0) return '';
  return d
    .replace(/^(\d{3})(\d)/, '$1.$2')
    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
}

/**
 * Máscara CNPJ — `00.000.000/0000-00`.
 * Trunca em 14 dígitos.
 *
 *   "12345678" → "12.345.678"
 *   "12345678000195" → "12.345.678/0001-95"
 */
export function maskCNPJ(value: string | null | undefined): string {
  const d = unmaskDigits(value).slice(0, 14);
  if (d.length === 0) return '';
  return d
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
}

/**
 * Máscara dinâmica — detecta pelo número de dígitos:
 *  - ≤ 11 dígitos → formata como CPF
 *  - > 11 dígitos → formata como CNPJ
 *
 * Atalho que reusa `formatCpfCnpj` de `format-br.ts` (Slice 2 fiscal BR).
 */
export function maskCpfCnpjAuto(value: string | null | undefined): string {
  return formatCpfCnpj(value);
}

/**
 * Máscara telefone BR — `(00) 0000-0000` ou `(00) 0 0000-0000`.
 * Diferença vs `formatPhone` de format-br.ts: usa o pattern com "9 separado"
 * conforme protótipo Cowork (`(11) 9 8888-7777`) — Wagner aprovou na sessão
 * understand 2026-05-21.
 *
 *   "11988887777" → "(11) 9 8888-7777"  (celular 11d, 9 separado)
 *   "1133334444"  → "(11) 3333-4444"    (fixo 10d)
 *   "11"          → "(11) "
 */
export function maskTel(value: string | null | undefined): string {
  const d = unmaskDigits(value).slice(0, 11);
  if (d.length === 0) return '';
  if (d.length <= 2) return `(${d}`;
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10) {
    return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  }
  return `(${d.slice(0, 2)}) ${d.slice(2, 3)} ${d.slice(3, 7)}-${d.slice(7)}`;
}

/**
 * Máscara CEP — `00000-000`.
 *
 *   "01310100" → "01310-100"
 *   "0131"     → "0131"
 */
export function maskCEP(value: string | null | undefined): string {
  return formatCep(value);
}
