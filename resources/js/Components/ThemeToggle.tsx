import { Monitor, Moon, Sun } from 'lucide-react';
import { useTheme } from '@/Hooks/useTheme';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import type { ThemeMode } from '@/Types';

interface ThemeToggleProps {
  variant?: 'icon' | 'full';
  align?: 'start' | 'center' | 'end';
  side?: 'top' | 'right' | 'bottom' | 'left';
}

/**
 * Toggle de tema light/dark/auto com dropdown.
 *
 * - `icon`: só um botão redondo (ícone atual) — cabe na sidebar colapsada
 * - `full`: botão com texto do modo — cabe no user menu footer
 *
 * Obs: Tooltip foi REMOVIDO aninhando com DropdownMenuTrigger — o Radix não
 * gosta. Se precisar de hint, usar aria-label (screen readers já leem).
 */
export function ThemeToggle({ variant = 'icon', align = 'end', side = 'right' }: ThemeToggleProps) {
  const { mode, effective, setTheme } = useTheme();

  const Icon = effective === 'dark' ? Moon : Sun;
  const modeLabel: Record<'light' | 'dark' | 'auto', string> = {
    light: 'Claro',
    dark: 'Escuro',
    auto: 'Sistema',
  };
  const current = mode ?? 'auto';

  const trigger =
    variant === 'icon' ? (
      <Button
        variant="ghost"
        size="icon"
        aria-label={`Tema atual: ${modeLabel[current]}. Clique para trocar.`}
        title={`Tema: ${modeLabel[current]}`}
      >
        <Icon size={16} />
      </Button>
    ) : (
      <Button variant="ghost" size="sm" className="w-full justify-start gap-2">
        <Icon size={15} />
        <span>Tema: {modeLabel[current]}</span>
      </Button>
    );

  const apply = (next: ThemeMode) => () => setTheme(next);

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
      <DropdownMenuContent align={align} side={side} className="w-44">
        <DropdownMenuLabel>Tema</DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuCheckboxItem checked={mode === 'light'} onClick={apply('light')}>
          <Sun size={14} className="mr-2" />
          Claro
        </DropdownMenuCheckboxItem>
        <DropdownMenuCheckboxItem checked={mode === 'dark'} onClick={apply('dark')}>
          <Moon size={14} className="mr-2" />
          Escuro
        </DropdownMenuCheckboxItem>
        <DropdownMenuCheckboxItem checked={mode === null} onClick={apply(null)}>
          <Monitor size={14} className="mr-2" />
          Sistema
        </DropdownMenuCheckboxItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
