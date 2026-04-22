import * as Icons from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

interface IconProps extends React.SVGAttributes<SVGSVGElement> {
  name: string;
  size?: number;
}

/**
 * Renderiza um ícone Lucide por nome string (vindo do servidor Laravel).
 * Fallback para "Circle" quando o nome não existe (evita quebrar shell).
 */
export function Icon({ name, size = 16, className, ...rest }: IconProps) {
  const map = Icons as unknown as Record<string, LucideIcon>;
  const Component = map[name] ?? Icons.Circle;
  return <Component size={size} className={className} {...rest} />;
}
