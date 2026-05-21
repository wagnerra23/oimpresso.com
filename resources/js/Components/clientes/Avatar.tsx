// Wave G — Components/clientes/Avatar.tsx
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
function hashStr(s: string): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) {
    h = (h * 31 + s.charCodeAt(i)) | 0;
  }
  return Math.abs(h);
}

// 12 hues distribuídas em círculo cromático. Saturação alta (60%) + luminosidade
// média (88% bg / 30% fg) garantem contraste e leveza.
function colorForName(name: string): { bg: string; fg: string } {
  const hue = (hashStr(name) % 12) * 30; // 0, 30, 60, 90, ... 330
  return {
    bg: `hsl(${hue}, 60%, 88%)`,
    fg: `hsl(${hue}, 55%, 30%)`,
  };
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
  /** Tamanho em px. Default 32 (linha tabela); drawer header usa 56. */
  size?: number;
  className?: string;
  /** Seed alternativa (ex: id numérico do contact em string). Default = name. */
  seed?: string;
}

/**
 * Avatar colorido HSL hash determinístico.
 *
 * Exemplo de uso:
 *   <Avatar name="João da Silva" />          → linha tabela 32px
 *   <Avatar name="Acme" size={56} />          → drawer header 56px
 *   <Avatar name="Acme" seed={id.toString()}  → cor estável mesmo se name mudar
 */
export function Avatar({ name, size = 32, className = '', seed }: AvatarProps) {
  const { bg, fg } = useMemo(() => colorForName(seed ?? name), [name, seed]);
  const initials = useMemo(() => avatarInitial(name), [name]);
  const fontSize = Math.round(size * 0.4);

  return (
    <div
      className={'rounded-md flex items-center justify-center font-semibold flex-shrink-0 ' + className}
      style={{
        background: bg,
        color: fg,
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
