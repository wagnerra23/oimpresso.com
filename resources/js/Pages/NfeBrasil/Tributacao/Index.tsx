// @memcofre tela=/nfe-brasil/tributacao module=NfeBrasil
//   us: US-NFE-010 fase 2 (UI tributação — regras NCM + config default)
//   adrs: NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto, ADR 0029 (Inertia + UPos)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import { Skeleton } from '@/Components/ui/skeleton';
import {
  CheckCircle2, ChevronRight, FilePlus2, FileSpreadsheet, Package, Pencil, Percent, Printer,
  Settings, ShoppingBag, Sparkles, Trash2, Zap,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import { toast } from 'sonner';

interface Regra {
  id: number;
  ncm: string;
  uf_origem: string;
  uf_destino: string | null;
  cfop: string;
  csosn: string | null;
  cst: string | null;
  aliquota_icms: number;
  aliquota_pis: number;
  aliquota_cofins: number;
  aliquota_ipi: number;
  mva: number | null;
  fcp: number | null;
}

interface ConfigDefault {
  regime: string;
  tributacao_default: Record<string, unknown>;
  auto_emission_enabled?: boolean;
}

interface Template {
  slug: string;
  titulo: string;
  descricao: string;
  icon: string;
  setor: string;
  regime: string;
  uf: string;
  modelo_nfe: string;
  recomendado_para: string;
  tributacao_default: Record<string, unknown>;
  observacoes: string[];
}

interface Props {
  // regras e templates vêm via Inertia::defer — undefined no first render. config é eager.
  regras?: Regra[];
  config: ConfigDefault | null;
  templates?: Template[];
}

interface FlashProps {
  flash?: { success?: string; error?: string };
}

const TEMPLATE_ICON_MAP: Record<string, typeof Package> = {
  'shopping-bag': ShoppingBag,
  package: Package,
  printer: Printer,
};

const REGIME_LABEL: Record<string, string> = {
  mei: 'MEI',
  simples: 'Simples Nacional',
  lucro_presumido: 'Lucro Presumido',
  lucro_real: 'Lucro Real',
};

function pct(decimal: number): string {
  return `${(decimal * 100).toFixed(2).replace('.', ',')}%`;
}

function formatNcm(ncm: string): string {
  if (!ncm || ncm.length !== 8) return ncm;
  return `${ncm.slice(0, 4)}.${ncm.slice(4, 6)}.${ncm.slice(6)}`;
}

function Index({ regras, config, templates }: Props) {
  const { props } = usePage<FlashProps>();
  const success = props.flash?.success;
  const error = props.flash?.error;

  // Guardas defensivas: props deferidas são undefined no first render.
  const rows = regras ?? [];

  const aplicarTemplate = (tpl: Template) => {
    const aviso = config
      ? `Aplicar template "${tpl.titulo}"?\n\nIsso vai SUBSTITUIR a configuração atual (regime + tributação default).\nRegras NCM existentes ${rows.length > 0 ? `(${rows.length})` : ''} permanecem inalteradas.`
      : `Aplicar template "${tpl.titulo}"?\n\nIsso vai criar a configuração tributária inicial do business.`;

    if (!confirm(aviso)) return;
    router.post(`/nfe-brasil/tributacao/templates/${tpl.slug}/aplicar`, {}, {
      preserveScroll: true,
      onError: () => toast.error('Falha ao aplicar template.'),
    });
  };

  const toggleAutoEmission = (checked: boolean) => {
    router.post('/nfe-brasil/tributacao/auto-emission/toggle',
      { enabled: checked },
      {
        preserveScroll: true,
        onError: () => toast.error('Falha ao atualizar emissão automática.'),
      }
    );
  };

  const remover = (regra: Regra) => {
    if (!confirm(`Remover regra NCM ${formatNcm(regra.ncm)} ${regra.uf_origem}→${regra.uf_destino ?? 'todas'}?`)) {
      return;
    }
    router.delete(`/nfe-brasil/tributacao/regras/${regra.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Regra removida.'),
      onError: () => toast.error('Falha ao remover.'),
    });
  };

  return (
    <>
      <Head title="Tributação · NF-e Brasil" />

      <div className="p-6 max-w-6xl mx-auto space-y-6">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
            <Percent className="h-6 w-6" />
            Tributação
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Configure a tributação padrão do seu negócio e, se precisar, regras específicas por NCM.
            Comece pela <strong>configuração padrão</strong> — sem ela a NFe não pode ser emitida automaticamente.
          </p>
        </header>

        {success && (
          <div className="flex items-start gap-2 px-4 py-3 rounded-md bg-success-soft text-success-fg border border-success/20">
            <CheckCircle2 className="h-5 w-5 mt-0.5 shrink-0" />
            <p className="text-sm">{success}</p>
          </div>
        )}

        {error && (
          <div className="flex items-start gap-2 px-4 py-3 rounded-md bg-destructive/10 text-destructive border border-destructive/30">
            <Trash2 className="h-5 w-5 mt-0.5 shrink-0" />
            <p className="text-sm">{error}</p>
          </div>
        )}

        {/* Templates L1 — atalho de configuração rápida (US-NFE-TPL-001) */}
        <Deferred data="templates" fallback={<Skeleton className="h-40 w-full" />}>
        {templates && templates.length > 0 && (
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center gap-2">
                <Sparkles className="h-4 w-4 text-primary" />
                Configuração rápida por setor
              </CardTitle>
              <p className="text-xs text-muted-foreground">
                {config
                  ? 'Já tem configuração — aplicar template substitui o regime e a tributação default. Regras NCM existentes ficam intactas.'
                  : 'Comece aqui se for novo: 1 clique aplica regime + tributação default pra seu setor.'}
              </p>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                {templates.map((tpl) => {
                  const Icon = TEMPLATE_ICON_MAP[tpl.icon] ?? Package;
                  return (
                    <div
                      key={tpl.slug}
                      className="rounded-md border border-border p-4 flex flex-col gap-2 hover:border-primary/50 transition-colors"
                    >
                      <div className="flex items-start gap-2">
                        <div className="rounded-md bg-primary/10 text-primary p-2 shrink-0">
                          <Icon className="h-4 w-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="font-medium text-sm leading-tight">{tpl.titulo}</div>
                          <div className="flex flex-wrap items-center gap-1 mt-1">
                            <Badge variant="outline" className="text-[10px] py-0 h-4">{REGIME_LABEL[tpl.regime] ?? tpl.regime}</Badge>
                            <Badge variant="outline" className="text-[10px] py-0 h-4">{tpl.uf}</Badge>
                            <Badge variant="outline" className="text-[10px] py-0 h-4">NFe {tpl.modelo_nfe}</Badge>
                          </div>
                        </div>
                      </div>
                      <p className="text-xs text-muted-foreground line-clamp-2">{tpl.descricao}</p>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => aplicarTemplate(tpl)}
                        className="mt-1 w-full"
                      >
                        Aplicar template
                      </Button>
                    </div>
                  );
                })}
              </div>
            </CardContent>
          </Card>
        )}
        </Deferred>

        {/* Config default (Nível 4) */}
        <Card className={!config ? 'border-destructive/50' : undefined}>
          <CardHeader className="pb-3">
            <CardTitle className="text-base flex items-center justify-between gap-2">
              <span className="flex items-center gap-2 flex-wrap">
                <Settings className="h-4 w-4" />
                Configuração padrão
                {!config && <Badge variant="destructive">Pendente</Badge>}
              </span>
              <Button asChild variant={config ? 'outline' : 'default'} size="sm">
                <Link href="/nfe-brasil/tributacao/config-default">
                  {config ? 'Editar' : 'Configurar agora'}
                </Link>
              </Button>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!config ? (
              <p className="text-sm text-muted-foreground">
                Sem isso, a NFe não será emitida automaticamente nas cobranças recorrentes.
              </p>
            ) : (
              <dl className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                  <dt className="text-xs text-muted-foreground uppercase tracking-wide">Regime</dt>
                  <dd className="mt-0.5 font-medium">{REGIME_LABEL[config.regime] ?? config.regime}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground uppercase tracking-wide">NCM padrão</dt>
                  <dd className="mt-0.5 font-mono">{formatNcm(String(config.tributacao_default.ncm_default ?? '—'))}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground uppercase tracking-wide">CFOP</dt>
                  <dd className="mt-0.5 font-mono">{String(config.tributacao_default.cfop_default ?? config.tributacao_default.cfop ?? '—')}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground uppercase tracking-wide">CSOSN/CST</dt>
                  <dd className="mt-0.5 font-mono">
                    {String(config.tributacao_default.csosn ?? config.tributacao_default.cst ?? '—')}
                  </dd>
                </div>
              </dl>
            )}
          </CardContent>
        </Card>

        {/* Per-business auto-emission gate (ADR 0093 multi-tenant Tier 0) */}
        {config && (
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between gap-3">
                <span className="flex items-center gap-2">
                  <Zap className="h-4 w-4" />
                  Emissão automática
                </span>
                <Switch
                  checked={config.auto_emission_enabled ?? false}
                  onCheckedChange={toggleAutoEmission}
                  aria-label="Habilitar emissão automática"
                />
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground">
                {config.auto_emission_enabled
                  ? 'Habilitado: vendas finalizadas pagas neste tenant emitem NFC-e/NFe automaticamente.'
                  : 'Desabilitado: vendas finalizadas NÃO disparam emissão automática neste tenant. Habilite só após validar o smoke fiscal em homologação.'}
              </p>
            </CardContent>
          </Card>
        )}

        {/* Regras NCM (Níveis 2 e 3) */}
        <Deferred data="regras" fallback={<Skeleton className="h-64 w-full" />}>
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base flex items-center justify-between gap-2">
              <span className="flex items-center gap-2">
                Regras NCM
                <Badge variant="secondary">{rows.length}</Badge>
              </span>
              <div className="flex gap-2">
                <Button asChild size="sm" variant="outline">
                  <Link href="/nfe-brasil/tributacao/import">
                    <FileSpreadsheet className="h-4 w-4 mr-1.5" />
                    Import CSV
                  </Link>
                </Button>
                <Button asChild size="sm" variant="outline">
                  <Link href="/nfe-brasil/tributacao/regras/create">
                    <FilePlus2 className="h-4 w-4 mr-1.5" />
                    Nova regra
                  </Link>
                </Button>
              </div>
            </CardTitle>
            <p className="text-xs text-muted-foreground">
              Deixe a UF destino em branco para a regra valer para todas as UFs.
            </p>
          </CardHeader>
          <CardContent>
            {rows.length === 0 ? (
              <p className="text-sm text-muted-foreground py-6 text-center">
                Nenhuma regra cadastrada. Os defaults da configuração padrão atendem todos NCMs até você adicionar
                regras específicas.
              </p>
            ) : (
              <div className="overflow-x-auto -mx-6">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-left">
                    <tr>
                      <th className="px-6 py-2 font-medium">NCM</th>
                      <th className="px-3 py-2 font-medium">UF Orig</th>
                      <th className="px-3 py-2 font-medium">UF Dest</th>
                      <th className="px-3 py-2 font-medium">CFOP</th>
                      <th className="px-3 py-2 font-medium">CSOSN/CST</th>
                      <th className="px-3 py-2 font-medium text-right">ICMS</th>
                      <th className="px-3 py-2 font-medium text-right">PIS</th>
                      <th className="px-3 py-2 font-medium text-right">COFINS</th>
                      <th className="px-6 py-2 font-medium text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((r) => (
                      <tr key={r.id} className="border-t hover:bg-muted/30">
                        <td className="px-6 py-2 font-mono">{formatNcm(r.ncm)}</td>
                        <td className="px-3 py-2 font-mono">{r.uf_origem}</td>
                        <td className="px-3 py-2 font-mono">
                          {r.uf_destino ?? <span className="text-muted-foreground italic">todas</span>}
                        </td>
                        <td className="px-3 py-2 font-mono">{r.cfop}</td>
                        <td className="px-3 py-2 font-mono">{r.csosn ?? r.cst}</td>
                        <td className="px-3 py-2 text-right font-mono">{pct(r.aliquota_icms)}</td>
                        <td className="px-3 py-2 text-right font-mono">{pct(r.aliquota_pis)}</td>
                        <td className="px-3 py-2 text-right font-mono">{pct(r.aliquota_cofins)}</td>
                        <td className="px-6 py-2 text-right whitespace-nowrap">
                          <Button asChild size="sm" variant="ghost">
                            <Link href={`/nfe-brasil/tributacao/regras/${r.id}/edit`}>
                              <Pencil className="h-3.5 w-3.5 mr-1" /> Editar
                            </Link>
                          </Button>
                          <Button size="sm" variant="ghost" onClick={() => remover(r)}>
                            <Trash2 className="h-3.5 w-3.5 mr-1 text-destructive" />
                            Remover
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
        </Deferred>

        <div className="flex items-center gap-2 text-xs text-muted-foreground flex-wrap">
          <span className="font-medium">Ordem de prioridade:</span>
          <Badge variant="secondary">1 · Produto</Badge>
          <ChevronRight className="h-3 w-3 shrink-0" />
          <Badge variant="secondary">2 · NCM + UF origem + destino</Badge>
          <ChevronRight className="h-3 w-3 shrink-0" />
          <Badge variant="secondary">3 · NCM (qualquer UF destino)</Badge>
          <ChevronRight className="h-3 w-3 shrink-0" />
          <Badge variant="secondary">4 · Padrão</Badge>
        </div>
      </div>
    </>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Tributação · NF-e Brasil"
    breadcrumbItems={[{ label: 'NF-e Brasil' }, { label: 'Tributação' }]}
  >
    {page}
  </AppShellV2>
);

export default Index;
