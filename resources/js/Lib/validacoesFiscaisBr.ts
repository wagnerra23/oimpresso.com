// Validações fiscais BR — Receita Federal + SEFAZ + LC 116/2003
// Refs: memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md gap #5
// Inspirado em Bling/Tiny/Omie — DV real (não regex), máscara dinâmica, consistência UF.

/**
 * Resultado de validação — objeto consistente pra UI consumir.
 *   { ok: true }                             — válido
 *   { ok: false, motivo: '...' }             — inválido com mensagem PT-BR
 */
export type ValidationResult = { ok: true } | { ok: false; motivo: string };

const ok = (): ValidationResult => ({ ok: true });
const bad = (motivo: string): ValidationResult => ({ ok: false, motivo });

// ───────────────────────────────────────────────────────────────
// CPF — algoritmo oficial Receita Federal
// ───────────────────────────────────────────────────────────────

export function validaCpf(input: string): ValidationResult {
  const cpf = input.replace(/\D/g, '');
  if (cpf.length !== 11) return bad('CPF precisa ter 11 dígitos');
  // Rejeita sequências (000.000.000-00, 111.111.111-11, etc — válidos no DV mas inválidos pela RF)
  if (/^(\d)\1{10}$/.test(cpf)) return bad('CPF inválido (sequência repetida)');

  // Primeiro DV
  let soma = 0;
  for (let i = 0; i < 9; i++) soma += parseInt(cpf[i] ?? '0', 10) * (10 - i);
  let resto = (soma * 10) % 11;
  if (resto === 10) resto = 0;
  if (resto !== parseInt(cpf[9] ?? '0', 10)) return bad('CPF inválido (DV1)');

  // Segundo DV
  soma = 0;
  for (let i = 0; i < 10; i++) soma += parseInt(cpf[i] ?? '0', 10) * (11 - i);
  resto = (soma * 10) % 11;
  if (resto === 10) resto = 0;
  if (resto !== parseInt(cpf[10] ?? '0', 10)) return bad('CPF inválido (DV2)');

  return ok();
}

// ───────────────────────────────────────────────────────────────
// CNPJ — algoritmo oficial Receita Federal
// ───────────────────────────────────────────────────────────────

export function validaCnpj(input: string): ValidationResult {
  const cnpj = input.replace(/\D/g, '');
  if (cnpj.length !== 14) return bad('CNPJ precisa ter 14 dígitos');
  if (/^(\d)\1{13}$/.test(cnpj)) return bad('CNPJ inválido (sequência repetida)');

  const calc = (slice: string, pesos: number[]): number => {
    let soma = 0;
    for (let i = 0; i < slice.length; i++) soma += parseInt(slice[i] ?? '0', 10) * (pesos[i] ?? 0);
    const resto = soma % 11;
    return resto < 2 ? 0 : 11 - resto;
  };

  const pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  const pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  const dv1 = calc(cnpj.slice(0, 12), pesos1);
  if (dv1 !== parseInt(cnpj[12] ?? '0', 10)) return bad('CNPJ inválido (DV1)');
  const dv2 = calc(cnpj.slice(0, 13), pesos2);
  if (dv2 !== parseInt(cnpj[13] ?? '0', 10)) return bad('CNPJ inválido (DV2)');

  return ok();
}

// ───────────────────────────────────────────────────────────────
// CPF/CNPJ unified — detecta pelo length
// ───────────────────────────────────────────────────────────────

export function validaCpfOuCnpj(input: string): ValidationResult {
  const digits = input.replace(/\D/g, '');
  if (digits.length === 11) return validaCpf(digits);
  if (digits.length === 14) return validaCnpj(digits);
  if (digits.length === 0) return ok(); // vazio é "não preenchido" — UI decide se é required
  return bad('Documento precisa ter 11 (CPF) ou 14 (CNPJ) dígitos');
}

// ───────────────────────────────────────────────────────────────
// Máscara dinâmica CPF/CNPJ
//   '11222333000181' → '11.222.333/0001-81'
//   '12345678900'    → '123.456.789-00'
// ───────────────────────────────────────────────────────────────

export function mascaraCpfCnpj(input: string): string {
  const digits = input.replace(/\D/g, '').slice(0, 14);
  if (digits.length <= 11) {
    // CPF: 000.000.000-00
    return digits
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1-$2')
      .slice(0, 14);
  }
  // CNPJ: 00.000.000/0000-00
  return digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d)/, '$1-$2')
    .slice(0, 18);
}

// ───────────────────────────────────────────────────────────────
// NCM — 8 dígitos numéricos (não valida tabela TIPI, apenas formato)
// ───────────────────────────────────────────────────────────────

export function validaNcm(input: string): ValidationResult {
  const ncm = input.replace(/\D/g, '');
  if (ncm.length === 0) return ok();
  if (ncm.length !== 8) return bad('NCM precisa ter 8 dígitos');
  return ok();
}

// ───────────────────────────────────────────────────────────────
// CFOP — 4 dígitos + consistência UF
//   1xxx/2xxx/3xxx = entradas
//   5xxx           = saídas intra-UF
//   6xxx           = saídas interestaduais
//   7xxx           = saídas exterior
// ───────────────────────────────────────────────────────────────

export interface CfopContext {
  ufEmitente?: string;
  ufDestinatario?: string;
}

export function validaCfop(input: string, ctx: CfopContext = {}): ValidationResult {
  const cfop = input.replace(/\D/g, '');
  if (cfop.length === 0) return ok();
  if (cfop.length !== 4) return bad('CFOP precisa ter 4 dígitos');
  const primeiro = cfop[0] ?? '';
  if (!['1', '2', '3', '5', '6', '7'].includes(primeiro)) {
    return bad('CFOP deve começar com 1/2/3 (entradas) ou 5/6/7 (saídas)');
  }
  // Consistência UF apenas pra saídas (5xxx intra, 6xxx inter)
  if (ctx.ufEmitente && ctx.ufDestinatario) {
    const mesmaUf = ctx.ufEmitente.toUpperCase() === ctx.ufDestinatario.toUpperCase();
    if (primeiro === '5' && !mesmaUf) {
      return bad(`CFOP 5xxx é intra-UF — UF emitente (${ctx.ufEmitente}) ≠ destinatário (${ctx.ufDestinatario})`);
    }
    if (primeiro === '6' && mesmaUf) {
      return bad(`CFOP 6xxx é interestadual — UF emitente = destinatário (${ctx.ufEmitente})`);
    }
  }
  return ok();
}

// ───────────────────────────────────────────────────────────────
// CST (ICMS) — 2 dígitos · lista oficial RICMS
// ───────────────────────────────────────────────────────────────

const CST_VALIDOS = new Set([
  '00', '10', '20', '30', '40', '41', '50', '51', '60', '70', '90',
]);

export function validaCst(input: string): ValidationResult {
  const cst = input.replace(/\D/g, '').padStart(2, '0').slice(0, 2);
  if (cst === '00' && input.trim() === '') return ok(); // vazio é não-preenchido
  if (!CST_VALIDOS.has(cst)) {
    return bad(`CST ${cst} inválido — válidos: ${Array.from(CST_VALIDOS).join(', ')}`);
  }
  return ok();
}

// ───────────────────────────────────────────────────────────────
// CSOSN (Simples Nacional) — 3 dígitos · lista oficial RICMS
// ───────────────────────────────────────────────────────────────

const CSOSN_VALIDOS = new Set([
  '101', '102', '103', '201', '202', '203', '300', '400', '500', '900',
]);

export function validaCsosn(input: string): ValidationResult {
  const csosn = input.replace(/\D/g, '').slice(0, 3);
  if (csosn === '') return ok();
  if (!CSOSN_VALIDOS.has(csosn)) {
    return bad(`CSOSN ${csosn} inválido — válidos: ${Array.from(CSOSN_VALIDOS).join(', ')}`);
  }
  return ok();
}

// ───────────────────────────────────────────────────────────────
// ISS — Alíquota 2% a 5% (LC 116/2003 art. 8º-A)
// ───────────────────────────────────────────────────────────────

export function validaIss(aliquota: number): ValidationResult {
  if (Number.isNaN(aliquota)) return bad('Alíquota ISS inválida');
  if (aliquota < 2) return bad('Alíquota ISS mínima é 2% (LC 116/2003)');
  if (aliquota > 5) return bad('Alíquota ISS máxima é 5% (LC 116/2003)');
  return ok();
}

// ───────────────────────────────────────────────────────────────
// Email — RFC 5322 simplificado (pragmático)
// ───────────────────────────────────────────────────────────────

const EMAIL_RX = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;

export function validaEmail(input: string): ValidationResult {
  if (!input || input.trim() === '') return ok();
  if (!EMAIL_RX.test(input.trim())) return bad('Email inválido');
  return ok();
}
