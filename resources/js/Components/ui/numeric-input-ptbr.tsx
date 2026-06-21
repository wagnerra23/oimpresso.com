// NumericInputPtBR — input numérico pt-BR safe (canon ui/ · BR inputs).
//
// PROMOVIDO de Pages/Sells/_components → ui/ em 2026-06-11 (R-DS-001 regra-de-2:
// PaymentRow + Sells/Create já consumiam; vira superfície canônica junto com
// document-input + phone-input). Registrado em REGISTRY_DS_COMPONENTES.md.
//
// Origem (Bug R$ [redacted Tier 0]k Larissa 2026-05-27):
//   <Input type="number" value={p.unit_price} onChange={Number(e.target.value)}> permitia
//   "25,00" virar NaN OU "25.000" virar 25000 dependendo do locale do navegador. Larissa
//   digitou R$ [redacted Tier 0] e o sistema gravou R$ [redacted Tier 0] em produção.
//
// Solução (paridade Blade legacy pos.js `__read_number`/`__write_number`):
//   - type="text" (não "number" — evita quirks de locale do navegador)
//   - inputMode="decimal" (teclado numérico mobile)
//   - Display formatado pt-BR quando NÃO focado
//   - Durante edição: aceita raw com vírgula/ponto, parse pt-BR
//   - onBlur: reformata display + emit value canônico
//
// State interno (string) ≠ state externo (number). Reconcilia em useEffect quando
// value externo muda sem foco.

import { useEffect, useRef, useState, forwardRef } from 'react';
import { Input } from '@/Components/ui/input';
import { parseDecimalPtBR, formatDecimalPtBR } from '@/Lib/numberPtBR';

interface Props extends Omit<React.ComponentProps<typeof Input>, 'value' | 'onChange' | 'type'> {
  value: number;
  onChange: (n: number) => void;
  precision?: number;
}

const NumericInputPtBR = forwardRef<HTMLInputElement, Props>(function NumericInputPtBR(
  { value, onChange, precision = 2, onFocus, onBlur, ...rest },
  ref,
) {
  const [text, setText] = useState<string>(() => formatDecimalPtBR(value, precision));
  const focusedRef = useRef(false);

  // Sincroniza display quando value externo muda E não está focado
  // (ex: ao selecionar produto, unit_price vem de selling_price).
  useEffect(() => {
    if (!focusedRef.current) {
      setText(formatDecimalPtBR(value, precision));
    }
  }, [value, precision]);

  return (
    <Input
      ref={ref}
      type="text"
      inputMode="decimal"
      autoComplete="off"
      // Permite digitar dígitos, vírgula, ponto, hífen (negativo) — paridade Blade.
      pattern="[0-9.,\-]*"
      value={text}
      onFocus={(e) => {
        focusedRef.current = true;
        // Mostra valor "editável" sem separador de milhar pra facilitar edição.
        // Mas mantém vírgula decimal se houver.
        const raw = value === 0 ? '' : String(value).replace('.', ',');
        setText(raw);
        // Seleciona pra facilitar overwrite (UX padrão input numérico).
        e.currentTarget.select();
        onFocus?.(e);
      }}
      onChange={(e) => {
        const v = e.target.value;
        setText(v);
        const parsed = parseDecimalPtBR(v);
        if (!Number.isNaN(parsed)) {
          onChange(parsed);
        }
      }}
      onBlur={(e) => {
        focusedRef.current = false;
        const parsed = parseDecimalPtBR(text);
        if (Number.isNaN(parsed)) {
          // Inválido — volta pro último value canônico.
          setText(formatDecimalPtBR(value, precision));
        } else {
          // Reformata display + emite.
          setText(formatDecimalPtBR(parsed, precision));
          onChange(parsed);
        }
        onBlur?.(e);
      }}
      {...rest}
    />
  );
});

export { NumericInputPtBR };
export default NumericInputPtBR;
