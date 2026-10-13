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
//
// Selecionar tudo ao entrar no campo (para digitar POR CIMA do valor):
//   Medido em produção em 2026-10-13 (janela de estoque inicial): ao clicar num campo com valor e
//   digitar, o número novo entrava JUNTO do antigo — "4" sobre "10" virava "410". O `select()` que
//   já existia no foco não sobrevivia, por dois motivos, na ordem em que o navegador os executa:
//     mousedown (cursor no ponto do clique) → focus (select + troca "10,00" por "10") →
//     o novo `value` desfaz a seleção (fica 0-0) → mouseup.
//   Por isso a seleção é refeita em dois momentos: depois do render que troca o texto (cobre a
//   entrada por Tab) e no `mouseup` do clique que deu o foco (cobre o mouse). Um segundo clique,
//   com o campo já focado, não reseleciona — é como se posiciona o cursor para corrigir um dígito.

import { useEffect, useLayoutEffect, useRef, useState, forwardRef } from 'react';
import { Input } from '@/Components/ui/input';
import { parseDecimalPtBR, formatDecimalPtBR } from '@/Lib/numberPtBR';

interface Props extends Omit<React.ComponentProps<typeof Input>, 'value' | 'onChange' | 'type'> {
  value: number;
  onChange: (n: number) => void;
  precision?: number;
}

const NumericInputPtBR = forwardRef<HTMLInputElement, Props>(function NumericInputPtBR(
  { value, onChange, precision = 2, onFocus, onBlur, onMouseDown, onMouseUp, ...rest },
  ref,
) {
  const [text, setText] = useState<string>(() => formatDecimalPtBR(value, precision));
  const focusedRef = useRef(false);
  const inputRef = useRef<HTMLInputElement | null>(null);
  /** O foco acabou de chegar e o texto ainda vai ser trocado: reselecionar depois do render. */
  const selecionarAposRenderRef = useRef(false);
  /** O `mousedown` em curso é o que está dando o foco ao campo. */
  const cliqueDeFocoRef = useRef(false);

  const guardarRef = (el: HTMLInputElement | null) => {
    inputRef.current = el;
    if (typeof ref === 'function') ref(el);
    else if (ref) ref.current = el;
  };

  // Entrada por teclado (Tab): o foco troca "10,00" por "10" e o novo `value` desfaz a seleção.
  useLayoutEffect(() => {
    if (!selecionarAposRenderRef.current) return;
    selecionarAposRenderRef.current = false;
    const el = inputRef.current;
    if (el && document.activeElement === el) el.select();
  }, [text]);

  // Sincroniza display quando value externo muda E não está focado
  // (ex: ao selecionar produto, unit_price vem de selling_price).
  useEffect(() => {
    if (!focusedRef.current) {
      setText(formatDecimalPtBR(value, precision));
    }
  }, [value, precision]);

  return (
    <Input
      ref={guardarRef}
      type="text"
      inputMode="decimal"
      autoComplete="off"
      // Permite digitar dígitos, vírgula, ponto, hífen (negativo) — paridade Blade.
      pattern="[0-9.,\-]*"
      value={text}
      onMouseDown={(e) => {
        // Só o clique que DÁ o foco reseleciona; clique com o campo já focado posiciona o cursor.
        cliqueDeFocoRef.current = document.activeElement !== e.currentTarget;
        onMouseDown?.(e);
      }}
      onMouseUp={(e) => {
        if (cliqueDeFocoRef.current) {
          cliqueDeFocoRef.current = false;
          e.currentTarget.select();
        }
        onMouseUp?.(e);
      }}
      onFocus={(e) => {
        focusedRef.current = true;
        // Mostra valor "editável" sem separador de milhar pra facilitar edição.
        // Mas mantém vírgula decimal se houver.
        const raw = value === 0 ? '' : String(value).replace('.', ',');
        // Se o texto vai mudar, a seleção é refeita depois do render (ver o topo do arquivo).
        selecionarAposRenderRef.current = raw !== text;
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
