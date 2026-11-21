// Pages/Cliente/_components/Avatar.tsx
//
// Avatar de iniciais do cliente: círculo de COR SÓLIDA, texto branco, cor escolhida por hash do
// nome — o mesmo nome sempre ganha a mesma cor, que é o que faz a lista ser reconhecível de relance.
//
// Fonte de design: prototipo-ui/cowork/Wagner/clientes-page.jsx (`CLI_AVATAR_PALETTE`, `cliHash`,
// `initialsOf`, componente `Avatar`) + clientes-page.css (`.cli-avatar`, `.cli-avatar.lg`).
// Paleta, hash e iniciais são portados do protótipo 1:1, para o mesmo nome dar a mesma cor e as
// mesmas iniciais nos dois lados (a comparação medida pareia as duas telas).
//
// Histórico: até 2026-11-21 este componente desenhava um quadrado de 6px com degradê oklch (12
// gradientes). A medição `design-diff` de 2026-11-21 (prod × protótipo, tema escuro) acusou a
// forma e o preenchimento; pela UI-0029 o protótipo manda na forma, e a troca entrou na onda 1 da
// paridade da lista de clientes.

import { useMemo } from 'react';

// Paleta do protótipo (`CLI_AVATAR_PALETTE`): 8 tons, todos com texto branco.
const AVATAR_PALETTE: ReadonlyArray<string> = [
  'oklch(0.62 0.13 255)',
  'oklch(0.65 0.14 145)',
  'oklch(0.68 0.13 60)',
  'oklch(0.62 0.14 25)',
  'oklch(0.60 0.10 280)',
  'oklch(0.55 0.08 200)',
  'oklch(0.58 0.12 165)',
  'oklch(0.62 0.13 320)',
];

// Hash do protótipo (`cliHash`): inteiro de 32 bits com sinal e valor absoluto no fim.
function hashStr(s: string): number {
  let h = 0;
  const str = String(s);
  for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) | 0;
  return Math.abs(h);
}

export function avatarColor(name: string): string {
  return AVATAR_PALETTE[hashStr(name) % AVATAR_PALETTE.length];
}

// Iniciais do protótipo (`initialsOf`): primeira letra das duas primeiras palavras, ignorando o
// que não é letra. Uma palavra só dá uma letra.
export function avatarInitial(name: string): string {
  const letras = String(name ?? '')
    .split(/\s+/)
    .map((p) => p.replace(/[^\p{L}]/gu, ''))
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0])
    .join('')
    .toUpperCase();
  return letras || '?';
}

export interface AvatarProps {
  name: string;
  /** Tamanho em px. Default 32 (linha da tabela); a gaveta usa 44 (`.cli-avatar.lg`). */
  size?: number;
  className?: string;
  /** Seed alternativa para a cor. Default = name. */
  seed?: string;
}

/**
 * Exemplo:
 *   <Avatar name="João da Silva" />            → linha da tabela, 32px
 *   <Avatar name="Acme" size={44} />           → cabeçalho da gaveta, 44px
 */
export function Avatar({ name, size = 32, className = '', seed }: AvatarProps) {
  const background = useMemo(() => avatarColor(seed ?? name), [name, seed]);
  const initials = useMemo(() => avatarInitial(name), [name]);
  // `.cli-avatar` 11px · `.cli-avatar.lg` (≥44px) 14px.
  const fontSize = size >= 44 ? 14 : 11;

  return (
    <div
      className={
        'rounded-full flex items-center justify-center font-semibold tracking-[0.02em] flex-shrink-0 text-white ' +
        className
      }
      style={{ backgroundColor: background, width: size, height: size, flexBasis: size, fontSize }}
      aria-hidden="true"
    >
      {initials}
    </div>
  );
}

export default Avatar;
