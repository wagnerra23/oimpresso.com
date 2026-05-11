// SectionNav — topmenu horizontal de sub-rotas do Module Financeiro.
//
// Origem: Wagner 2026-05-11 — depois de remover o submenu lateral
// (PR #565), as sub-telas (Contas a receber, Boletos, Conciliação etc)
// ficaram sem navegação visual. Esse componente recupera essa navegação
// em formato horizontal contextual ao módulo, abaixo do PageHeader.
//
// Uso (em qualquer Page do Financeiro):
//   <PageHeader ... />
//   <SectionNav current="/financeiro/unificado" />
//   <KpiBar ... />

import { Link } from '@inertiajs/react';
import { cn } from '@/Lib/utils';

interface SectionItem {
  href: string;
  label: string;
}

const ITEMS: SectionItem[] = [
  { href: '/financeiro/unificado',        label: 'Visão unificada' },
  { href: '/financeiro/contas-receber',   label: 'A receber' },
  { href: '/financeiro/contas-pagar',     label: 'A pagar' },
  { href: '/financeiro/boletos',          label: 'Boletos' },
  { href: '/financeiro/contas-bancarias', label: 'Contas bancárias' },
  { href: '/financeiro/categorias',       label: 'Categorias' },
  { href: '/financeiro/extrato',          label: 'Conciliação' },
  { href: '/financeiro/relatorios',       label: 'Relatórios' },
];

interface SectionNavProps {
  /**
   * Path da rota atual (ex: '/financeiro/unificado'). A tab que bater
   * com esse path vai ficar destacada como ativa.
   */
  current: string;
  className?: string;
}

export default function SectionNav({ current, className }: SectionNavProps) {
  return (
    <nav
      data-slot="financeiro-section-nav"
      className={cn(
        'border-b border-border mt-4 -mx-4 px-4 overflow-x-auto',
        className,
      )}
      aria-label="Navegação do módulo Financeiro"
    >
      <ul className="flex items-center gap-1 text-sm whitespace-nowrap">
        {ITEMS.map((item) => {
          const active = item.href === current;
          return (
            <li key={item.href}>
              <Link
                href={item.href}
                aria-current={active ? 'page' : undefined}
                className={cn(
                  '-mb-px inline-flex items-center px-3 py-2 border-b-2 transition-colors',
                  active
                    ? 'border-primary text-foreground font-medium'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                )}
              >
                {item.label}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
