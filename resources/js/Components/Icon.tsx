import * as Icons from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

interface IconProps extends React.SVGAttributes<SVGSVGElement> {
  /**
   * Nome do ícone Lucide. Aceita 3 convenções pra interop com:
   * - PascalCase (`Wrench`, `MessageCircle`) — direto do `lucide-react`, igual TOPNAV_ICON_MAP do AppShellV2
   * - kebab-case (`wrench`, `message-circle`) — convenção das Pages Inertia / shared/EmptyState (icon prop)
   * - snake_case (`message_circle`) — fallback raro
   */
  name: string;
  size?: number;
}

/**
 * Renderiza um ícone Lucide por nome string (vindo do servidor Laravel ou de Pages
 * React). Tenta PascalCase direto primeiro; se não achar, normaliza kebab/snake →
 * PascalCase. Fallback final: `Circle` (evita quebrar shell, mas indica nome inválido).
 *
 * Bug histórico (2026-05-07 PR #184): antes do normalize, `<Icon name="wrench"/>`
 * caía direto no fallback `Circle` porque lucide-react exporta `Wrench` (PascalCase).
 * Resultado: TODAS as telas Inertia mostravam bolas vazias em vez de ícones.
 */
export function Icon({ name, size = 16, className, ...rest }: IconProps) {
  const map = Icons as unknown as Record<string, LucideIcon>;
  const Component = (typeof name === 'string' && name.length > 0)
    ? (map[name] ?? map[toPascalCase(name)] ?? Icons.Circle)
    : Icons.Circle;
  return <Component size={size} className={className} {...rest} />;
}

function toPascalCase(s: string): string {
  // Guard defensivo: callers que passam undefined/number/null caíam em
  // crash `split is not a function` antes do hotfix PR #186.
  if (typeof s !== 'string' || s.length === 0) return '';
  return s
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1).toLowerCase())
    .join('');
}
