// Wave G — Pages/Cliente/_components/Avatar.tsx
//
// Avatar colorido determinístico via HSL hash do nome do cliente. Mesmo nome
// sempre gera mesma cor — reconhecimento visual instantâneo na listagem.
//
// Refs:
//   - ADR 0179 (drawer 760 substitui Show.tsx full-page) §dim 2 paleta cor semântica
//   - prototipo-ui/prototipos/clientes/clientes-icons.jsx (avatarFor + initialsFor)
//   - HANDOFF_CLIENTES.md §5.3 helpers (avatar.ts)
//   - visual-comparison cliente-drawer-760 dim 2 score 15→95
//
// Algoritmo: HSL hash com 12 hues distribuídas (0, 30, 60... 330) garante 12
// cores distinguíveis com WCAG AA contrast (text foreground hsl(hue, 55%, 30%)
// sobre background hsl(hue, 60%, 88%)).

import { useMemo } from 'react';

// Hash determinístico djb2-like. Resultado positivo, sem overflow JS int.
// Z-2.1: alinhado ao protótipo Cowork `clientes-icons.jsx::avatarFor`:
//   `(h * 31 + name.charCodeAt(i)) >>> 0`  (unsigned shift)
function hashStr(s: string): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) {
    h = ((h * 31 + s.charCodeAt(i)) >>> 0);
  }
  return h;
}

// Z-2.1 alinhado ao protótipo: 12 gradients oklch distinctos + texto branco.
// Estilo Stripe/Linear/Notion (vivos, legíveis). NÃO pastel chapado HSL.
// Ref: prototipo-ui/prototipos/clientes/clientes-icons.jsx:128-146 AV_GRADS.
const AVATAR_GRADIENTS: ReadonlyArray<string> = [
  'linear-gradient(135deg, oklch(0.65 0.18 25),   oklch(0.55 0.20 350))', // rosa-vermelho
  'linear-gradient(135deg, oklch(0.65 0.18 60),   oklch(0.55 0.20 30))',  // âmbar-vermelho
  'linear-gradient(135deg, oklch(0.65 0.18 110),  oklch(0.55 0.20 80))',  // lima-âmbar
  'linear-gradient(135deg, oklch(0.65 0.18 150),  oklch(0.55 0.20 130))', // verde-lima
  'linear-gradient(135deg, oklch(0.65 0.18 180),  oklch(0.55 0.20 170))', // teal-verde
  'linear-gradient(135deg, oklch(0.65 0.18 210),  oklch(0.55 0.20 200))', // azul-teal
  'linear-gradient(135deg, oklch(0.65 0.18 240),  oklch(0.55 0.20 270))', // azul-violeta
  'linear-gradient(135deg, oklch(0.65 0.18 290),  oklch(0.55 0.20 320))', // violeta-magenta
  'linear-gradient(135deg, oklch(0.55 0.15 47),   oklch(0.65 0.15 107))', // marrom-amarelo (Cowork)
  'linear-gradient(135deg, oklch(0.55 0.15 280),  oklch(0.65 0.15 340))', // roxo-rosa (Cowork)
  'linear-gradient(135deg, oklch(0.55 0.15 200),  oklch(0.65 0.15 160))', // azul-verde
  'linear-gradient(135deg, oklch(0.55 0.15 0),    oklch(0.65 0.15 60))',  // vermelho-âmbar
];

function gradientForName(name: string): string {
  return AVATAR_GRADIENTS[hashStr(name) % AVATAR_GRADIENTS.length];
}

// 1 letra se uma palavra, 2 letras (primeira + última) se mais.
// Idêntico ao window.initialsFor do protótipo Cowork.
export function avatarInitial(name: string): string {
  if (!name || !name.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase();
  }
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

export interface AvatarProps {
  name: string;
  /** Tamanho em px. Default 28 (linha tabela Cowork); drawer header usa 40. */
  size?: number;
  className?: string;
  /** Seed alternativa (ex: id numérico do contact em string). Default = name. */
  seed?: string;
  /** Z-2.1: forma. `'rounded'` = rounded-md (legacy); `'circle'` = full round (drawer Cowork). */
  shape?: 'rounded' | 'circle';
}

/**
 * Avatar colorido com gradient oklch determinístico, texto branco.
 * Z-2.1 alinhado ao protótipo Cowork `clientes-listagem.jsx::Avatar`:
 *   - Background: `linear-gradient(135deg, oklch...)` (12 gradients)
 *   - Color: `#fff`
 *   - Default size: 28px (tabela) — drawer header passa size={40}
 *
 * Exemplo de uso:
 *   <Avatar name="João da Silva" />                      → linha tabela 28px (rounded-md)
 *   <Avatar name="Acme" size={40} shape="circle" />       → drawer header 40px circle (Cowork)
 *   <Avatar name="Acme" seed={id.toString()} />           → cor estável mesmo se name mudar
 */
export function Avatar({
  name,
  size = 28,
  className = '',
  seed,
  shape = 'rounded',
}: AvatarProps) {
  const background = useMemo(() => gradientForName(seed ?? name), [name, seed]);
  const initials = useMemo(() => avatarInitial(name), [name]);
  const fontSize = Math.round(size * 0.4);
  const radiusClass = shape === 'circle' ? 'rounded-full' : 'rounded-md';

  return (
    <div
      className={radiusClass + ' flex items-center justify-center font-semibold flex-shrink-0 text-white ' + className}
      style={{
        background,
        width: size,
        height: size,
        fontSize,
      }}
      aria-hidden="true"
    >
      {initials}
    </div>
  );
}

export default Avatar;
