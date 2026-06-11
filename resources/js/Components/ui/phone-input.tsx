// PhoneInput — telefone BR com máscara progressiva fixo/celular.
//
// Compõe <Input> canon (variant cowork default · ADR UI-0015) + `maskTel` de
// `@/Lib/br-mask` — pattern "9 separado" do protótipo Cowork, Wagner aprovou
// sessão understand 2026-05-21:
//   "11988887777" → "(11) 9 8888-7777"  (celular 11d)
//   "1133334444"  → "(11) 3333-4444"    (fixo 10d)
//
// Substitui o hand-wiring de maskTel + Input repetido (ContatoTab do drawer
// Cliente e forms novos com telefone). Persistir SEMPRE `digits` (sem máscara).
//
// Uso:
//   <PhoneInput value={telefone} onValueChange={({ masked, digits }) => ...} />

import { Input, type InputProps } from '@/Components/ui/input';
import { maskTel, onlyDigits } from '@/Lib/br-mask';

interface PhoneInputProps extends Omit<InputProps, 'value' | 'onChange' | 'type'> {
  /** Valor controlado — aceita com ou sem máscara (deriva sempre dos dígitos). */
  value: string;
  /** Emite a cada tecla: masked (display) + digits (persistir). */
  onValueChange: (v: { masked: string; digits: string }) => void;
}

function PhoneInput({ value, onValueChange, ...rest }: PhoneInputProps) {
  return (
    <Input
      {...rest}
      type="tel"
      inputMode="tel"
      autoComplete="tel-national"
      value={maskTel(onlyDigits(value))}
      onChange={(e) => {
        const d = onlyDigits(e.target.value);
        onValueChange({ masked: maskTel(d), digits: d });
      }}
    />
  );
}

export { PhoneInput };
export type { PhoneInputProps };
