// DocumentInput — CPF/CNPJ com máscara progressiva + validação mod 11 (UX-only).
//
// Compõe <Input> canon (variant cowork default · ADR UI-0015) + `@/Lib/br-mask`
// + `@/Lib/br-validate`. Substitui o hand-wiring repetido nos 4 tabs do drawer
// Cliente (IdentificacaoTab etc — Wave C-FE) e em qualquer form novo com documento.
//
// Validação client-side é UX-only (borda vermelha via aria-invalid). Backend
// (Rule\BR\CpfCnpj + FormRequest) é a verdade canônica — ADR 0093 Tier 0 força
// revalidação server-side em TODA mutação.
//
// Contrato de `valid` (espelha br-validate):
//   true  = documento completo e mod 11 OK
//   false = documento completo e ERRADO (só aqui acende aria-invalid)
//   null  = incompleto (user ainda digitando — não acende erro)
//
// Uso:
//   <DocumentInput value={cpfCnpj} onValueChange={({ masked, digits, valid }) => ...} />
//   <DocumentInput tipo="cpf" value={cpf} onValueChange={...} />

import { Input, type InputProps } from '@/Components/ui/input';
import { maskCPF, maskCNPJ, maskCpfCnpjAuto, onlyDigits } from '@/Lib/br-mask';
import { validateCPF, validateCNPJ } from '@/Lib/br-validate';

type DocumentTipo = 'cpf' | 'cnpj' | 'auto';

interface DocumentInputProps extends Omit<InputProps, 'value' | 'onChange' | 'type'> {
  /** Valor controlado — aceita com ou sem máscara (deriva sempre dos dígitos). */
  value: string;
  /** Emite a cada tecla: masked (display) + digits (persistir) + valid (UX). */
  onValueChange: (v: { masked: string; digits: string; valid: boolean | null }) => void;
  /** `auto` (default) detecta CPF↔CNPJ pelo nº de dígitos (≤11 CPF, >11 CNPJ). */
  tipo?: DocumentTipo;
}

function maskDocument(tipo: DocumentTipo, raw: string): string {
  if (tipo === 'cpf') return maskCPF(raw);
  if (tipo === 'cnpj') return maskCNPJ(raw);
  return maskCpfCnpjAuto(raw);
}

// Trunca digits no limite do tipo — garante display (mask trunca) === digits
// emitido pra persistir. Sem isso, colar 14 dígitos em tipo=cpf mascararia 11
// mas emitiria 14 (drift display↔banco).
function clampDigits(tipo: DocumentTipo, digits: string): string {
  return digits.slice(0, tipo === 'cpf' ? 11 : 14);
}

function validateDocument(tipo: DocumentTipo, digits: string): boolean | null {
  if (tipo === 'cpf') return validateCPF(digits);
  if (tipo === 'cnpj') return validateCNPJ(digits);
  return digits.length > 11 ? validateCNPJ(digits) : validateCPF(digits);
}

function DocumentInput({ value, onValueChange, tipo = 'auto', ...rest }: DocumentInputProps) {
  const digits = clampDigits(tipo, onlyDigits(value));
  const invalid = validateDocument(tipo, digits) === false;

  return (
    <Input
      {...rest}
      type="text"
      inputMode="numeric"
      autoComplete="off"
      value={maskDocument(tipo, digits)}
      aria-invalid={invalid || undefined}
      onChange={(e) => {
        const d = clampDigits(tipo, onlyDigits(e.target.value));
        onValueChange({
          masked: maskDocument(tipo, d),
          digits: d,
          valid: validateDocument(tipo, d),
        });
      }}
    />
  );
}

export { DocumentInput };
export type { DocumentInputProps, DocumentTipo };
