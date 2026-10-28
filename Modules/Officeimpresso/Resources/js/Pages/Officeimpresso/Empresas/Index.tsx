// Empresas licenciadas — Officeimpresso (clientes da WR2 com o desktop WR Comercial / Delphi).
//
//   rota:     /officeimpresso/businessall (LicencaComputadorController::businessall; sem flag nem
//             Blade desde o cutover de 2026-10-27, RUNBOOK-empresas §F5)
//   padrão:   PT-01 Lista
//   charter:  ./Index.charter.md · casos: ./Index.casos.md
//   âncora:   prototipo-ui/cowork/Wagner/officeimpresso-page.jsx → ViewEmpresas() (rota oi-empresas)
//
// Global por design: lista todas as empresas licenciadas (a guarda é a da operadora). Sem ação de
// escrita — a Blade também não tinha; bloquear e configurar ficam na ficha da empresa.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { ClipboardList, Monitor, Search } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import EmptyState from '@/Components/shared/EmptyState';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import StatusBadge from '@/Components/shared/StatusBadge';

interface Empresa {
  id: number; name: string; razao_social: string | null; cnpj: string | null;
  versao_disponivel: string | null; versao_obrigatoria: string | null;
  ultimo_acesso: string | null; bloqueado: boolean;
}
interface Kpis { total: number; ativas: number; bloqueadas: number }
interface Props { empresas?: Empresa[]; kpis?: Kpis }

const POR_PAGINA = 25;
const dataHoraBr = (d: string | null) => (d ? new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—');

function EmpresasIndex({ empresas, kpis }: Props) {
  return (
    <div className="pb-8">
      <div data-contract="header">
        <PageHeader title="Empresas Licenciadas" subtitle="Todas as empresas com licença Office Impresso ativa" />
      </div>
      <div className="flex flex-col gap-4 px-6 pt-4">
        <Deferred data="kpis" fallback={<Skeleton className="h-20 w-full" />}>
          <Indicadores kpis={kpis} />
        </Deferred>
        <Deferred data="empresas" fallback={<Skeleton className="h-64 w-full" />}>
          <Lista empresas={empresas ?? []} />
        </Deferred>
      </div>
    </div>
  );
}

function Indicadores({ kpis }: { kpis?: Kpis }) {
  if (!kpis) return null;
  return (
    <KpiGrid cols={3} data-contract="kpis">
      <KpiCard label="Empresas" description="com módulo Office Impresso" value={kpis.total} />
      <KpiCard label="Ativas" description="em operação" value={kpis.ativas} />
      <KpiCard label="Bloqueadas" description="requer ação" value={kpis.bloqueadas} />
    </KpiGrid>
  );
}

function Lista({ empresas }: { empresas: Empresa[] }) {
  const [q, setQ] = useState('');
  const [pagina, setPagina] = useState(1);
  const termo = q.trim().toLowerCase();
  const filtradas = useMemo(() => empresas.filter((e) => !termo
    || [e.name, e.razao_social, e.cnpj, e.versao_disponivel, e.versao_obrigatoria, String(e.id)]
      .some((v) => String(v ?? '').toLowerCase().includes(termo))), [empresas, termo]);
  const total = Math.max(1, Math.ceil(filtradas.length / POR_PAGINA));
  const atual = Math.min(pagina, total);
  const visiveis = filtradas.slice((atual - 1) * POR_PAGINA, atual * POR_PAGINA);

  if (empresas.length === 0) {
    return <EmptyState title="Nenhum registro encontrado"
      description="Nenhuma empresa está marcada como licenciada do Office Impresso." />;
  }

  return (
    <>
      <div data-contract="toolbar" className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-64 flex-1">
          <Search className="absolute left-2 top-2.5 size-4 text-muted-foreground" />
          <Input className="pl-8" value={q} onChange={(e) => { setQ(e.target.value); setPagina(1); }}
            placeholder="Buscar nome, razão social, CNPJ, versão ou #…" aria-label="Buscar empresa" />
        </div>
        {q && <Button variant="ghost" size="sm" onClick={() => { setQ(''); setPagina(1); }}>Limpar</Button>}
        <span className="text-xs text-muted-foreground">{filtradas.length} de {empresas.length}</span>
      </div>

      {filtradas.length === 0 ? (
        <EmptyState title="Nenhuma empresa encontrada" description="Nome, razão social, CNPJ, versão e # entram na busca."
          action={<Button variant="ghost" onClick={() => setQ('')}>Limpar busca</Button>} />
      ) : (
        <div data-contract="grade" className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b text-left text-xs text-muted-foreground">
              <th className="py-2">Empresa</th><th>CNPJ</th><th>Versão disponível</th><th>Versão obrigatória</th>
              <th>Último acesso</th><th>Status</th><th><span className="sr-only">Ações</span></th>
            </tr></thead>
            <tbody>
              {visiveis.map((e) => (
                <tr key={e.id} className="border-b align-top">
                  <td className="py-2">
                    <span className="font-semibold">{e.name}</span>
                    <span className="font-mono text-xs text-muted-foreground"> #{e.id}</span>
                    {e.razao_social && <div className="text-xs text-muted-foreground">{e.razao_social}</div>}
                  </td>
                  <td className="font-mono text-xs">{e.cnpj ?? '—'}</td>
                  <td className="font-mono text-xs">{e.versao_disponivel || '—'}</td>
                  <td className="font-mono text-xs">{e.versao_obrigatoria || '—'}</td>
                  <td className="font-mono text-xs">{dataHoraBr(e.ultimo_acesso)}</td>
                  <td>
                    <StatusBadge kind="licenca" value={e.bloqueado ? 'empresa_bloqueada' : 'ativa'} label={e.bloqueado ? 'Bloqueada' : 'Ativa'} />
                  </td>
                  <td className="whitespace-nowrap text-right">
                    <Button asChild variant="outline" size="icon">
                      <a href={`/officeimpresso/licenca_computado/licencas/${e.id}`} aria-label={`Ver computadores de ${e.name}`} title="Ver computadores desta empresa">
                        <Monitor className="size-4" />
                      </a>
                    </Button>
                    <Button asChild variant="ghost" size="icon">
                      <a href={`/officeimpresso/licenca_log?business_id=${e.id}`} aria-label={`Ver log de ${e.name}`} title="Ver log desta empresa">
                        <ClipboardList className="size-4" />
                      </a>
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {total > 1 && (
        <nav data-contract="rodape" className="flex items-center justify-end gap-2 text-sm" aria-label="Paginação">
          <Button variant="ghost" size="sm" disabled={atual === 1} onClick={() => setPagina(atual - 1)}>Anterior</Button>
          <span className="text-xs text-muted-foreground">{atual} / {total}</span>
          <Button variant="ghost" size="sm" disabled={atual === total} onClick={() => setPagina(atual + 1)}>Próxima</Button>
        </nav>
      )}
    </>
  );
}

EmpresasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default EmpresasIndex;
