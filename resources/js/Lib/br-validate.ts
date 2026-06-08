// Wave C-FE — validadores BR (mod 11 CPF/CNPJ + email + CEP) pro drawer 760.
//
// Refs: ADR 0179 (drawer 760) · Charter Index.charter.md v3 · HANDOFF_CLIENTES.md §2
// Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-icons.jsx (BRValidate)
//
// Validação CLIENT-SIDE é UX-only: vermelho borda + erro inline antes do save.
// Backend (Rule\BR\CpfCnpj + FormRequest) é a verdade canônica — ADR 0093 Tier 0
// força revalidação server-side em TODA mutação.
//
// Algoritmo mod 11 conforme Receita Federal (CPF) + spec oficial (CNPJ pesos
// canônicos [5,4,3,2,9,8,7,6,5,4,3,2] e [6,5,...,3,2]). Sequências repetidas
// (111.111.111-11, 00.000.000/0000-00) são inválidas por convenção.

import { unmaskDigits } from './format-br';

/**
 * Valida CPF (11 dígitos + mod 11).
 *
 *   - Aceita string com ou sem máscara
 *   - Sequência repetida (000.000.000-00, 111.111.111-11, etc) → false
 *   - Mod 11: cada dígito × peso decrescente, somatória × 10 mod 11
 *
 * @returns `true` se válido, `false` se inválido, `null` se incompleto (UX —
 *          não acende erro enquanto user ainda digita).
 */
export function validateCPF(cpf: string | null | undefined): boolean | null {
  const d = unmaskDigits(cpf);
  if (d.length === 0) return null;
  if (d.length < 11) return null;
  if (d.length !== 11) return false;
  if (/^(\d)\1+$/.test(d)) return false;

  const calc = (slice: number, factor: number): number => {
    let s = 0;
    for (let i = 0; i < slice; i++) s += parseInt(d[i], 10) * (factor - i);
    const m = (s * 10) % 11;
    return m === 10 ? 0 : m;
  };

  return calc(9, 10) === parseInt(d[9], 10) && calc(10, 11) === parseInt(d[10], 10);
}

/**
 * Valida CNPJ (14 dígitos + mod 11 com pesos canônicos).
 *
 *   - Aceita string com ou sem máscara
 *   - Sequência repetida → false
 *   - Pesos DV1: [5,4,3,2,9,8,7,6,5,4,3,2]
 *   - Pesos DV2: [6,5,4,3,2,9,8,7,6,5,4,3,2]
 *
 * @returns `true` válido, `false` inválido, `null` incompleto.
 */
export function validateCNPJ(cnpj: string | null | undefined): boolean | null {
  const d = unmaskDigits(cnpj);
  if (d.length === 0) return null;
  if (d.length < 14) return null;
  if (d.length !== 14) return false;
  if (/^(\d)\1+$/.test(d)) return false;

  const calc = (slice: number): number => {
    const weights =
      slice === 12
        ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
        : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    let s = 0;
    for (let i = 0; i < slice; i++) s += parseInt(d[i], 10) * weights[i];
    const m = s % 11;
    return m < 2 ? 0 : 11 - m;
  };

  return calc(12) === parseInt(d[12], 10) && calc(13) === parseInt(d[13], 10);
}

/**
 * Valida email — RFC 5322 simplificado.
 *   - Aceita `user@domain.tld`, `user.name+tag@sub.domain.com.br`
 *   - Rejeita espaços e múltiplos @
 *
 * @returns `true` válido, `false` inválido, `null` vazio (UX — não acusa).
 */
export function validateEmail(email: string | null | undefined): boolean | null {
  if (!email) return null;
  const trimmed = email.trim();
  if (trimmed.length === 0) return null;
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed);
}

/**
 * Valida CEP — 8 dígitos numéricos.
 *
 * @returns `true` válido, `false` inválido, `null` incompleto.
 */
export function validateCEP(cep: string | null | undefined): boolean | null {
  const d = unmaskDigits(cep);
  if (d.length === 0) return null;
  if (d.length < 8) return null;
  return d.length === 8;
}

/**
 * Helper boolean estrito — usado em form submit (incompleto = inválido).
 */
export function isValidCPF(cpf: string | null | undefined): boolean {
  return validateCPF(cpf) === true;
}

export function isValidCNPJ(cnpj: string | null | undefined): boolean {
  return validateCNPJ(cnpj) === true;
}

export function isValidEmail(email: string | null | undefined): boolean {
  return validateEmail(email) === true;
}

export function isValidCEP(cep: string | null | undefined): boolean {
  return validateCEP(cep) === true;
}
