// @memcofre
//   tela: /modulos
//   module: Superadmin
//   status: implementada
//   tests: Tests/Feature/ModulesIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  Database,
  Power,
  Search,
  Settings2,
  XCircle,
} from 'lucide-react';
import { Icon } from '@/Components/Icon';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/Components/ui/card';
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

// Heurística: ícone Lucide por área (coerente com ShellMenuBuilder/LegacyMenuAdapter)
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

export default function ModulesIndex({ modules }: Props) {
  const [query, setQuery] = useState('');
  const [selectedArea, setSelectedArea] = useState<string | null>(null);

  const areas = useMemo(() => {
    const set = new Set(modules.map((m) => m.area));
    return Array.from(set).sort();
  }, [modules]);

  const counts = useMemo(
    () => ({
      total: modules.length,
      active: modules.filter((m) => m.active).length,
      inactive: modules.filter((m) => !m.active).length,
      errored: modules.filter((m) => m.error !== null).length,
    }),
    [modules],
  );

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return modules.filter((m) => {
      if (selectedArea && m.area !== selectedArea) return false;
      if (!q) return true;
      return (
        m.name.toLowerCase().includes(q) ||
        m.alias.toLowerCase().includes(q) ||
        m.description.toLowerCase().includes(q) ||
        m.area.toLowerCase().includes(q)
      );
    });
  }, [modules, query, selectedArea]);

  const grouped = useMemo(() => {
    const byArea: Record<string, ModuleRow[]> = {};
    for (const m of filtered) {
      (byArea[m.area] ??= []).push(m);
    }
    return byArea;
  }, [filtered]);

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
    <>
      <div className="mx-auto max-w-7xl p-6 space-y-6">
        <header>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <Settings2 size={22} /> Gerenciador de Módulos
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Ative, desative e instale módulos do sistema. Tabelas do banco são preservadas ao desativar.
          </p>
        </header>

        {/* KPIs */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard label="Total" value={counts.total} icon="Package" tone="muted" />
          <StatCard label="Ativos" value={counts.active} icon="CheckCircle2" tone="success" />
          <StatCard label="Inativos" value={counts.inactive} icon="Power" tone="muted" />
          <StatCard label="Com erro" value={counts.errored} icon="AlertTriangle" tone="destructive" />
        </div>

        {/* Filtros */}
        <Card>
          <CardContent className="pt-4">
            <div className="flex flex-col md:flex-row gap-3">
              <div className="flex-1 flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5">
                <Search size={14} className="text-muted-foreground" />
                <Input
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Buscar por nome, alias, descrição ou área…"
                  className="h-7 border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0"
                />
              </div>

              <div className="flex gap-1.5 flex-wrap">
                <Button
                  variant={selectedArea === null ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setSelectedArea(null)}
                >
                  Todas ({modules.length})
                </Button>
                {areas.map((area) => {
                  const n = modules.filter((m) => m.area === area).length;
                  return (
                    <Button
                      key={area}
                      variant={selectedArea === area ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => setSelectedArea(area)}
                    >
                      {area} ({n})
                    </Button>
                  );
                })}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Lista agrupada por área */}
        {Object.keys(grouped).length === 0 ? (
          <Card>
            <CardContent className="py-12 text-center text-muted-foreground text-sm">
              Nenhum módulo encontrado com esses filtros.
            </CardContent>
          </Card>
        ) : (
          Object.entries(grouped).map(([area, mods]) => (
            <section key={area} className="space-y-3">
              <div className="flex items-center gap-2">
                <Icon name={areaIcons[area] ?? 'Package'} size={16} className="text-muted-foreground" />
                <h2 className="text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                  {area}
                </h2>
                <div className="flex-1 h-px bg-border" />
                <span className="text-xs text-muted-foreground">{mods.length}</span>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {mods.map((m) => (
                  <ModuleCard
                    key={m.name}
                    module={m}
                    onToggle={(v) => handleToggle(m, v)}
                    onInstall={() => handleInstall(m)}
                    onUninstall={() => handleUninstall(m)}
                  />
                ))}
              </div>
            </section>
          ))
        )}
      </div>
    </>
  );
}

ModulesIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Gerenciador de Módulos" breadcrumbItems={[{ label: 'Administração' }, { label: 'Módulos' }]}>
    {page}
  </AppShellV2>
);

// ============================================================================
// Card de módulo
// ============================================================================

function ModuleCard({
  module: m,
  onToggle,
  onInstall,
  onUninstall,
}: {
  module: ModuleRow;
  onToggle: (v: boolean) => void;
  onInstall: () => void;
  onUninstall: () => void;
}) {
  return (
    <Card className={cn(m.error && 'border-destructive/50')}>
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <CardTitle className="text-base truncate">{m.name}</CardTitle>
            <CardDescription className="text-xs mt-0.5">
              v{m.version} · {m.alias}
            </CardDescription>
          </div>
          <Switch checked={m.active} onCheckedChange={onToggle} aria-label={`Ativar/desativar ${m.name}`} />
        </div>
      </CardHeader>

      <CardContent className="pb-3 space-y-2">
        {m.description ? (
          <p className="text-xs text-muted-foreground line-clamp-2 min-h-[2rem]">{m.description}</p>
        ) : (
          <p className="text-xs text-muted-foreground italic min-h-[2rem]">Sem descrição.</p>
        )}

        <div className="flex flex-wrap gap-1.5 text-[10px]">
          {m.active ? (
            <Badge variant="default" className="gap-1">
              <CheckCircle2 size={10} /> Ativo
            </Badge>
          ) : (
            <Badge variant="secondary" className="gap-1">
              <Power size={10} /> Inativo
            </Badge>
          )}
          {m.has_migrations && (
            <Badge variant="outline" className="gap-1">
              <Database size={10} /> {m.migration_count} migration{m.migration_count !== 1 ? 's' : ''}
            </Badge>
          )}
          {!m.registered && (
            <Badge variant="outline" className="gap-1 text-amber-700 border-amber-400/40">
              <AlertTriangle size={10} /> Não registrado
            </Badge>
          )}
          {m.error && (
            <Badge variant="destructive" className="gap-1">
              <XCircle size={10} /> Erro
            </Badge>
          )}
        </div>

        {m.error && (
          <p className="text-xs text-destructive mt-1 line-clamp-2">{m.error}</p>
        )}
      </CardContent>

      <CardFooter className="pt-0 gap-2">
        {m.has_migrations && (
          <Button variant="outline" size="sm" onClick={onInstall} className="flex-1">
            <Database size={13} className="mr-1.5" />
            {m.active ? 'Reinstalar' : 'Instalar'}
          </Button>
        )}
        {m.active && (
          <Button variant="ghost" size="sm" onClick={onUninstall} className="flex-1 text-muted-foreground">
            <Power size={13} className="mr-1.5" />
            Desativar
          </Button>
        )}
      </CardFooter>
    </Card>
  );
}

// ============================================================================
// Stat card
// ============================================================================

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
            <p className="text-2xl font-bold mt-0.5">{value}</p>
          </div>
          <div className={cn('flex size-10 items-center justify-center rounded-lg', toneClasses[tone])}>
            <Icon name={icon} size={18} />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
