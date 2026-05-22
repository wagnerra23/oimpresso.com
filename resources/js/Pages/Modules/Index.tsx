// @memcofre
//   tela: /modulos
//   module: Superadmin
//   status: implementada
//   pattern: espelha Pages/Cliente/Index.tsx (tabela + FilterDropdown + StatusBadge + ActionsMenu)
//
// Notas:
//   - Rota /modulos é cross-tenant intencional (gerenciamento app-wide, não per-business).
//     ADR 0093 §exceções — não há business_id scope aqui.
//   - Props vêm de App\Http\Controllers\ModuleManagementController::index().
//     Toggle/Install/Uninstall via POST /modulos/{name}/* (rotas mantidas).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
  ChevronDown,
  Database,
  Eye,
  MoreVertical,
  Power,
  Search,
  Settings2,
  X,
} from 'lucide-react';
import { Icon } from '@/Components/Icon';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Switch } from '@/Components/ui/switch';
import { cn } from '@/Lib/utils';

interface ModuleRow {
  name: string;
  alias: string;
  version: string;
  description: string;
  area: string;
  active: boolean;
  registered: boolean;
  has_migrations: boolean;
  migration_count: number;
  has_datacontroller: boolean;
  error: string | null;
}

interface Props {
  modules: ModuleRow[];
}

// Heurística: ícone Lucide por área (coerente com ShellMenuBuilder/LegacyMenuAdapter).
const areaIcons: Record<string, string> = {
  'Recursos Humanos': 'Users',
  'Comercial':        'ShoppingCart',
  'Operações':        'Factory',
  'Financeiro':       'Coins',
  'IA':               'Sparkles',
  'Comunicação':      'MessageSquare',
  'Integrações':      'Plug',
  'Catálogo':         'BookOpen',
  'Administração':    'ShieldCheck',
  'Conteúdo':         'LayoutList',
  'Office Impresso':  'Building2',
  'Outros':           'Package',
};

type StatusKey = 'active' | 'inactive' | 'errored' | 'unregistered';

const STATUS_LABEL: Record<StatusKey, string> = {
  active:       'Ativo',
  inactive:     'Inativo',
  errored:      'Com erro',
  unregistered: 'Não registrado',
};

const STATUS_STYLE: Record<StatusKey, string> = {
  active:       'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  inactive:     'bg-stone-50 text-stone-700 border-stone-200 dark:bg-stone-950/40 dark:text-stone-300',
  errored:      'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300',
  unregistered: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300',
};

function rowStatus(m: ModuleRow): StatusKey {
  if (m.error) return 'errored';
  if (!m.registered) return 'unregistered';
  return m.active ? 'active' : 'inactive';
}

export default function ModulesIndex({ modules }: Props) {
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput.trim().toLowerCase()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  const [areaFilter, setAreaFilter] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<string>('');

  const areas = useMemo(() => {
    const set = new Set(modules.map((m) => m.area));
    return Array.from(set).sort();
  }, [modules]);

  const counts = useMemo(
    () => ({
      total: modules.length,
      active: modules.filter((m) => m.active).length,
      inactive: modules.filter((m) => !m.active && !m.error).length,
      errored: modules.filter((m) => m.error !== null).length,
    }),
    [modules],
  );

  const filtered = useMemo(() => {
    return modules.filter((m) => {
      if (areaFilter && m.area !== areaFilter) return false;
      if (statusFilter && rowStatus(m) !== statusFilter) return false;
      if (!search) return true;
      return (
        m.name.toLowerCase().includes(search) ||
        m.alias.toLowerCase().includes(search) ||
        m.description.toLowerCase().includes(search) ||
        m.area.toLowerCase().includes(search)
      );
    });
  }, [modules, search, areaFilter, statusFilter]);

  const handleToggle = (mod: ModuleRow, newValue: boolean) => {
    router.post(
      `/modulos/${mod.name}/toggle`,
      { active: newValue },
      { preserveScroll: true, only: ['modules', 'flash'] },
    );
  };

  const handleInstall = (mod: ModuleRow) => {
    if (!confirm(`Instalar ${mod.name}?\nIsso vai rodar ${mod.migration_count} migration(s) e ativar o módulo.`)) return;
    router.post(`/modulos/${mod.name}/install`, {}, { preserveScroll: true, only: ['modules', 'flash'] });
  };

  const handleUninstall = (mod: ModuleRow) => {
    if (!confirm(`Desativar ${mod.name}?\nAs tabelas do banco são PRESERVADAS (não são apagadas).`)) return;
    router.post(`/modulos/${mod.name}/uninstall`, {}, { preserveScroll: true, only: ['modules', 'flash'] });
  };

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground flex items-center gap-2">
                <Settings2 size={22} /> Gerenciador de Módulos
              </h1>
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed tabular-nums">
                {counts.total} módulos
                {' · '}
                {counts.active} ativos
                {' · '}
                {counts.inactive} inativos
                {counts.errored > 0 && (
                  <>
                    {' · '}
                    <span className="text-rose-700 dark:text-rose-400">
                      {counts.errored} com erro
                    </span>
                  </>
                )}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <StatCard label="Total" value={counts.total} icon="Package" tone="muted" />
            <StatCard label="Ativos" value={counts.active} icon="CheckCircle2" tone="success" />
            <StatCard label="Inativos" value={counts.inactive} icon="Power" tone="muted" />
            <StatCard label="Com erro" value={counts.errored} icon="AlertTriangle" tone="destructive" />
          </div>

          <nav className="flex items-center gap-2 mt-6 flex-wrap" aria-label="Filtros de módulo">
            <FilterDropdown
              label="Área"
              value={areaFilter}
              onChange={setAreaFilter}
              options={areas.map((a) => ({ value: a, label: a }))}
            />
            <FilterDropdown
              label="Status"
              value={statusFilter}
              onChange={setStatusFilter}
              options={[
                { value: 'active',       label: 'Ativo' },
                { value: 'inactive',     label: 'Inativo' },
                { value: 'errored',      label: 'Com erro' },
                { value: 'unregistered', label: 'Não registrado' },
              ]}
            />
            <span className="text-xs text-muted-foreground tabular-nums ml-1">
              {filtered.length === modules.length
                ? `${modules.length} módulo${modules.length !== 1 ? 's' : ''}`
                : `${filtered.length} de ${modules.length} módulos`}
            </span>
          </nav>

          {(areaFilter || statusFilter) && (
            <div className="flex flex-wrap items-center gap-1.5 mt-3" aria-label="Filtros ativos">
              {areaFilter && (
                <ActiveChip label="Área" value={areaFilter} onRemove={() => setAreaFilter('')} />
              )}
              {statusFilter && (
                <ActiveChip
                  label="Status"
                  value={STATUS_LABEL[statusFilter as StatusKey] ?? statusFilter}
                  onRemove={() => setStatusFilter('')}
                />
              )}
              <button
                type="button"
                onClick={() => {
                  setAreaFilter('');
                  setStatusFilter('');
                }}
                className="text-[10px] text-muted-foreground underline-offset-2 hover:underline hover:text-foreground ml-1"
              >
                limpar tudo
              </button>
            </div>
          )}
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-7xl">
        <div className="mb-4 flex items-center gap-2">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
            <Input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar por nome, alias, descrição ou área…"
              className="pl-9 pr-9 h-9"
              aria-label="Buscar módulo"
            />
            {searchInput && (
              <button
                type="button"
                onClick={() => setSearchInput('')}
                aria-label="Limpar busca"
                className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>
        </div>

        <div className="rounded-lg border border-border bg-background overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr className="border-b border-border">
                  <Th className="w-10">&nbsp;</Th>
                  <Th>Módulo</Th>
                  <Th className="w-40">Área</Th>
                  <Th className="w-32">Status</Th>
                  <Th align="right" className="w-28">Migrations</Th>
                  <Th className="w-20">Ativo</Th>
                  <Th className="w-10 text-right pr-4">&nbsp;</Th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="text-center py-12 text-muted-foreground text-xs">
                      Nenhum módulo encontrado nesse filtro.
                    </td>
                  </tr>
                ) : (
                  filtered.map((m) => {
                    const status = rowStatus(m);
                    return (
                      <tr key={m.name} className="border-b border-border hover:bg-muted/40">
                        <td className="px-4 py-2.5">
                          <div
                            className={cn(
                              'h-8 w-8 rounded-md flex items-center justify-center',
                              'bg-gradient-to-br from-stone-100 to-stone-200',
                              'dark:from-stone-800 dark:to-stone-700 text-stone-700 dark:text-stone-200',
                            )}
                            aria-hidden="true"
                          >
                            <Icon name={areaIcons[m.area] ?? 'Package'} size={16} />
                          </div>
                        </td>
                        <td className="px-4 py-2.5">
                          <div className="text-foreground font-medium leading-tight">{m.name}</div>
                          <div className="text-[11px] text-muted-foreground/70 leading-tight mt-0.5">
                            {m.alias} · v{m.version}
                          </div>
                          {m.description && (
                            <div className="text-[11px] text-muted-foreground line-clamp-1 mt-0.5">
                              {m.description}
                            </div>
                          )}
                          {m.error && (
                            <div className="text-[11px] text-rose-600 dark:text-rose-400 line-clamp-1 mt-0.5">
                              {m.error}
                            </div>
                          )}
                        </td>
                        <td className="px-4 py-2.5">
                          <span className="text-xs text-foreground">{m.area}</span>
                        </td>
                        <td className="px-4 py-2.5">
                          <StatusBadge status={status} />
                        </td>
                        <td className="px-4 py-2.5 text-right tabular-nums text-foreground">
                          {m.has_migrations ? (
                            <span className="inline-flex items-center gap-1 text-xs">
                              <Database size={11} className="text-muted-foreground" />
                              {m.migration_count}
                            </span>
                          ) : (
                            <span className="text-xs text-muted-foreground/50">—</span>
                          )}
                        </td>
                        <td className="px-4 py-2.5">
                          <Switch
                            checked={m.active}
                            onCheckedChange={(v) => handleToggle(m, v)}
                            aria-label={`Ativar/desativar ${m.name}`}
                          />
                        </td>
                        <td className="px-2 py-2.5 text-right pr-4">
                          <ActionsMenu
                            module={m}
                            onInstall={() => handleInstall(m)}
                            onUninstall={() => handleUninstall(m)}
                          />
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

ModulesIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Gerenciador de Módulos" breadcrumbItems={[{ label: 'Administração' }, { label: 'Módulos' }]}>
    {page}
  </AppShellV2>
);

// ─── Subcomponents ───────────────────────────────────────────────────────────

function Th({
  children,
  className = '',
  align = 'left',
}: {
  children: ReactNode;
  className?: string;
  align?: 'left' | 'right';
}) {
  return (
    <th
      className={
        (align === 'right' ? 'text-right ' : 'text-left ') +
        'px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
        className
      }
    >
      {children}
    </th>
  );
}

function StatusBadge({ status }: { status: StatusKey }) {
  return (
    <span
      className={
        'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium ' +
        STATUS_STYLE[status]
      }
    >
      {STATUS_LABEL[status]}
    </span>
  );
}

function ActiveChip({
  label,
  value,
  onRemove,
}: {
  label: string;
  value: string;
  onRemove: () => void;
}) {
  return (
    <span className="inline-flex items-center gap-1 rounded-md border border-blue-300 bg-blue-50 px-2 py-0.5 text-[11px] text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
      <span className="font-medium">{label}:</span>
      <span>{value}</span>
      <button
        type="button"
        onClick={onRemove}
        aria-label={`Remover filtro ${label}`}
        className="hover:text-blue-900 dark:hover:text-blue-100"
      >
        <X size={10} />
      </button>
    </span>
  );
}

function ActionsMenu({
  module: m,
  onInstall,
  onUninstall,
}: {
  module: ModuleRow;
  onInstall: () => void;
  onUninstall: () => void;
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
          aria-label={`Ações do módulo ${m.name}`}
        >
          <MoreVertical size={16} />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-48">
        {m.has_migrations && (
          <DropdownMenuItem onClick={onInstall}>
            <Database size={14} className="mr-2" />
            {m.active ? 'Reinstalar' : 'Instalar'}
          </DropdownMenuItem>
        )}
        {m.active && (
          <DropdownMenuItem onClick={onUninstall}>
            <Power size={14} className="mr-2" />
            Desativar
          </DropdownMenuItem>
        )}
        {!m.has_migrations && !m.active && (
          <DropdownMenuItem disabled>
            <Eye size={14} className="mr-2" />
            Sem ações disponíveis
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

// ─── FilterDropdown — espelha Pages/Cliente/Index.tsx (single-select only) ───

interface FilterDropdownOption {
  value: string;
  label: string;
}

function FilterDropdown({
  label,
  value,
  options,
  onChange,
}: {
  label: string;
  value: string;
  options: FilterDropdownOption[];
  onChange: (v: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const hasSel = !!value;
  const displayLabel = (() => {
    if (!value) return label;
    const opt = options.find((o) => o.value === value);
    return opt ? `${label}: ${opt.label}` : `${label}: ${value}`;
  })();

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className={
          'inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ' +
          (hasSel
            ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300'
            : 'border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground')
        }
        aria-haspopup="listbox"
        aria-expanded={open}
      >
        <span>{displayLabel}</span>
        <ChevronDown size={11} className="opacity-70" />
      </button>
      {open && (
        <div
          className="absolute z-50 top-full mt-1 left-0 min-w-[180px] max-h-[320px] overflow-y-auto rounded-md border border-border bg-background shadow-lg p-1"
          role="listbox"
        >
          {value && (
            <button
              type="button"
              onClick={() => {
                onChange('');
                setOpen(false);
              }}
              className="w-full text-left rounded px-2 py-1.5 text-xs text-muted-foreground hover:bg-muted border-b border-border mb-0.5"
            >
              Limpar
            </button>
          )}
          {options.map((opt) => {
            const isOn = value === opt.value;
            return (
              <button
                key={opt.value}
                type="button"
                role="option"
                aria-selected={isOn}
                onClick={() => {
                  onChange(isOn ? '' : opt.value);
                  setOpen(false);
                }}
                className={
                  'w-full text-left rounded px-2 py-1.5 text-xs transition-colors ' +
                  (isOn
                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                    : 'text-foreground hover:bg-muted')
                }
              >
                {opt.label}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

// ─── StatCard ────────────────────────────────────────────────────────────────

function StatCard({
  label,
  value,
  icon,
  tone,
}: {
  label: string;
  value: number;
  icon: string;
  tone: 'muted' | 'success' | 'destructive';
}) {
  const toneClasses: Record<typeof tone, string> = {
    muted:       'text-muted-foreground bg-muted',
    success:     'text-emerald-700 bg-emerald-100 dark:text-emerald-300 dark:bg-emerald-950',
    destructive: 'text-destructive bg-destructive/10',
  };
  return (
    <Card>
      <CardContent className="pt-4 pb-4">
        <div className="flex items-center justify-between gap-2">
          <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-2xl font-semibold mt-0.5 tabular-nums">{value}</p>
          </div>
          <div className={cn('flex size-10 items-center justify-center rounded-lg', toneClasses[tone])}>
            <Icon name={icon} size={18} />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
