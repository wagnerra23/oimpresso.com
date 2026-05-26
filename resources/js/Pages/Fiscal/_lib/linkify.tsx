// linkify.tsx — Auto-detect V-XXXX (venda), OS #XXXX (ordem serviço),
// CNPJ no texto (formato XX dot YYY dot YYY slash ZZZZ dash ZZ) e transforma em <a>.
//
// Onda 3 F. Deterministico (regex), sem IA. Backend real consultar:
//  - V-XXXX -> /sells/show/{numeric_part}
//  - OS #XXXX -> /repair/orders/{numeric_part}
//  - CNPJ -> /clientes?cnpj={digits} (lookup)
//
// pii-allowlist: regex pattern (sem PII real)

import { Fragment } from 'react';
import { router } from '@inertiajs/react';

// Patterns canonicos:
// - V-1234, V-12345 (venda)
// - OS #4807, OS #48071 (ordem servico; tolera espaco opcional)
// - CNPJ formatado (pattern 2.3.3/4-2 digitos pontuados) — pii-allowlist
const TOKEN_REGEX = /(V-\d{3,6}|OS\s?#\d{3,6}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/g;

function navigateTo(token: string): void {
  if (token.startsWith('V-')) {
    const num = token.slice(2);
    router.visit(`/sells/show/${num}`);
    return;
  }
  if (token.startsWith('OS')) {
    const num = token.replace(/\D/g, '');
    router.visit(`/repair/orders/${num}`);
    return;
  }
  // CNPJ -> search cliente
  const digits = token.replace(/\D/g, '');
  router.visit(`/clientes?cnpj=${digits}`);
}

/**
 * Renderiza texto com auto-deteccao de tokens fiscais -> links clicaveis.
 *
 * Uso: <Linkify text="Cliente X comprou via V-4821 (OS #4807)" />
 */
export function Linkify({ text, className }: { text: string; className?: string }) {
  const parts = text.split(TOKEN_REGEX);
  return (
    <span className={className}>
      {parts.map((part, i) => {
        if (TOKEN_REGEX.test(part)) {
          // Reset regex lastIndex (TOKEN_REGEX e stateful por causa do flag /g)
          TOKEN_REGEX.lastIndex = 0;
          return (
            <a
              key={i}
              href="#"
              className="fx-link"
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                navigateTo(part);
              }}
              title={`Abrir ${part}`}
            >
              {part}
            </a>
          );
        }
        return <Fragment key={i}>{part}</Fragment>;
      })}
    </span>
  );
}

/**
 * Variante "passive" — renderiza sem navegacao, so formatacao visual
 * (util dentro de drawer onde click pode conflitar com row click).
 */
export function LinkifyPassive({ text, className }: { text: string; className?: string }) {
  const parts = text.split(TOKEN_REGEX);
  return (
    <span className={className}>
      {parts.map((part, i) => {
        if (TOKEN_REGEX.test(part)) {
          TOKEN_REGEX.lastIndex = 0;
          return (
            <span key={i} className="fx-link" style={{ cursor: 'default' }}>
              {part}
            </span>
          );
        }
        return <Fragment key={i}>{part}</Fragment>;
      })}
    </span>
  );
}

/**
 * Helper pra apenas extrair tokens detectados (sem renderizar) — util
 * pra contar links ou popular menu contextual.
 */
export function extractTokens(text: string): string[] {
  TOKEN_REGEX.lastIndex = 0;
  return text.match(TOKEN_REGEX) ?? [];
}
