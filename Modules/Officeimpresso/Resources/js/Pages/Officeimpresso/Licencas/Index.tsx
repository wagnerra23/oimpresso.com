// Licenças de computador — Officeimpresso (licenciamento do desktop WR Comercial / Delphi).
//
//   rota:     /officeimpresso/licenca_computador (LicencaComputadorController::index, atrás da flag
//             useV2OfficeimpressoLicencas — desligada, a rota segue servindo o Blade)
//   padrão:   PT-01 Lista
//   charter:  ./Index.charter.md · casos: ./Index.casos.md
//   contrato: governance/design/contracts/officeimpresso-licencas.contract.json
//   âncora:   prototipo-ui/cowork/Wagner/officeimpresso-page.jsx → ViewLicencas() (rota oi-licencas)
//
// Thread Officeimpresso/06 — PR-a: índice + KPI-filtros · PR-b: drawer PT-02 (ficha + histórico)
// e liberar/bloquear com motivo (_components/LicencaDrawer). Senha nunca chega: o payload é DTO.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Search } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import EmptyState from '@/Components/shared/EmptyState';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import StatusBadge from '@/Components/shared/StatusBadge';
import LicencaDrawer, { type Detalhe } from './_components/LicencaDrawer';

interface Licenca {
  id: number; business_id: number; empresa: string | null; hostname: string | null; user_win: string | null;
  hd: string | null; versao_exe: string | null; versao_banco: string | null; versao_obrigatoria: string | null;
  dt_ultimo_acesso: string | null; frescor: 'recente' | 'fresc' | 'frio' | 'distante';
  dt_validade: string | null; bloqueado: boolean; motivo: string | null; hd_compartilhado: number;
}
interface Props {
  permissions: { pode_ver_todas_empresas: boolean; pode_gerenciar: boolean };
  licencas?: Licenca[];
  detalhe?: Detalhe | null;
}
type Kpi = 'campo' | 'sem7d' | 'bloqueados' | 'vencendo';

const POR_PAGINA = 25;
const hoje = () => new Date().toISOString().slice(0, 10);
const daqui30 = () => new Date(Date.now() + 30 * 864e5).toISOString().slice(0, 10);
const dataBr = (d: string | null) => (d ? new Date(`${d.slice(0, 10)}T00:00:00`).toLocaleDateString('pt-BR') : '—');
const atras = (inst: string | null, obrig: string | null) => {
  if (!inst || !obrig) return false;
  const a = inst.split('.'), b = obrig.split('.');
  for (let i = 0; i < 3; i++) { const d = (Number(a[i] ?? 0) || 0) - (Number(b[i] ?? 0) || 0); if (d) return d < 0; }
  return false;
};
const NA_REGRA: Record<Kpi, (l: Licenca) => boolean> = {
  campo: (l) => l.frescor === 'recente',
  sem7d: (l) => l.frescor === 'frio' || l.frescor === 'distante',
  bloqueados: (l) => l.bloqueado,
  vencendo: (l) => !!l.dt_validade && l.dt_validade >= hoje() && l.dt_validade <= daqui30(),
};

function LicencasIndex({ permissions, licencas, detalhe }: Props) {
  const todas = permissions.pode_ver_todas_empresas;
  const [aberta, setAberta] = useState<number | null>(null);
  return (
    <div className="pb-8">
      <div data-contract="header">
        <PageHeader title="Licenças de computador"
          subtitle={todas ? 'Máquinas do WR Comercial em todos os negócios · senha nunca é exibida' : 'Máquinas do WR Comercial deste negócio · senha nunca é exibida'} />
      </div>
      <div className="flex flex-col gap-4 px-6 pt-4">
        <Deferred data="licencas" fallback={<Skeleton className="h-64 w-full" />}>
          <Lista licencas={licencas ?? []} todas={todas} abrir={setAberta} />
        </Deferred>
      </div>
      <LicencaDrawer id={aberta} detalhe={detalhe} podeGerenciar={permissions.pode_gerenciar} onClose={() => setAberta(null)} />
    </div>
  );
}

function Lista({ licencas, todas, abrir }: { licencas: Licenca[]; todas: boolean; abrir: (id: number) => void }) {
  const [q, setQ] = useState('');
  const [kpi, setKpi] = useState<Kpi | null>(null);
  const [pagina, setPagina] = useState(1);
  const termo = q.trim().toLowerCase();
  const filtradas = useMemo(() => licencas.filter((l) => (!kpi || NA_REGRA[kpi](l))
    && (!termo || [l.hostname, l.user_win, l.hd, l.empresa, l.versao_exe].some((v) => String(v ?? '').toLowerCase().includes(termo)))),
  [licencas, kpi, termo]);
  const total = Math.max(1, Math.ceil(filtradas.length / POR_PAGINA));
  const atual = Math.min(pagina, total);
  const visiveis = filtradas.slice((atual - 1) * POR_PAGINA, atual * POR_PAGINA);
  const limpar = () => { setQ(''); setKpi(null); setPagina(1); };
  const alterna = (k: Kpi) => { setKpi(kpi === k ? null : k); setPagina(1); };
  const conta = (k: Kpi) => licencas.filter(NA_REGRA[k]).length;

  return (
    <>
      <div data-contract="toolbar" className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative min-w-64 flex-1">
            <Search className="absolute left-2 top-2.5 size-4 text-muted-foreground" />
            <Input className="pl-8" value={q} onChange={(e) => { setQ(e.target.value); setPagina(1); }}
              placeholder="Buscar host, usuário do Windows, HD ou empresa…" aria-label="Buscar licença" />
          </div>
          {(q || kpi) && <Button variant="ghost" size="sm" onClick={limpar}>Limpar</Button>}
          <span className="text-xs text-muted-foreground">{filtradas.length} de {licencas.length}</span>
        </div>
        <KpiGrid cols={4}>
          <KpiCard variant="filter" filterTone="emerald" label="Em campo" description="acesso nas últimas 24 h" value={conta('campo')} selected={kpi === 'campo'} onClick={() => alterna('campo')} />
          <KpiCard variant="filter" filterTone="amber" label="Sem acesso há 7 dias" description="ou nunca acessou" value={conta('sem7d')} selected={kpi === 'sem7d'} onClick={() => alterna('sem7d')} />
          <KpiCard variant="filter" filterTone="rose" label="Bloqueados" description="não autenticam no desktop" value={conta('bloqueados')} selected={kpi === 'bloqueados'} onClick={() => alterna('bloqueados')} />
          <KpiCard variant="filter" filterTone="violet" label="Vencendo em 30 dias" description="pela validade da licença" value={conta('vencendo')} selected={kpi === 'vencendo'} onClick={() => alterna('vencendo')} />
        </KpiGrid>
      </div>

      {filtradas.length === 0 ? (
        <EmptyState title={licencas.length ? 'Nenhuma licença com esses filtros' : 'Nenhuma licença cadastrada.'}
          description="Host, usuário do Windows, HD e empresa entram na busca."
          action={licencas.length ? <Button variant="ghost" onClick={limpar}>Limpar filtros</Button> : undefined} />
      ) : (
        <div data-contract="grade" className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead><tr className="border-b text-left text-xs text-muted-foreground">
              <th className="py-2">Computador</th>{todas && <th>Empresa</th>}<th>Versão instalada</th>
              <th>Último acesso</th><th>Validade</th><th>Status</th><th />
            </tr></thead>
            <tbody>
              {visiveis.map((l) => (
                <tr key={l.id} className="border-b align-top">
                  <td className="py-2">
                    <button type="button" className="font-semibold text-primary hover:underline" onClick={() => abrir(l.id)}
                      title="Abrir ficha e histórico">{l.hostname || l.user_win || '—'}</button>
                    {l.hostname && l.user_win && <span className="text-muted-foreground"> · {l.user_win}</span>}
                    <div className="font-mono text-xs text-muted-foreground">{l.hd ?? '—'}</div>
                    {l.hd_compartilhado > 0 && (
                      <div className="text-xs text-warning">HD também em {l.hd_compartilhado} outro{l.hd_compartilhado > 1 ? 's' : ''} negócio{l.hd_compartilhado > 1 ? 's' : ''} — o desktop recusa se qualquer um estiver bloqueado.</div>
                    )}
                  </td>
                  {todas && <td>{l.empresa ?? '—'}<div className="font-mono text-xs text-muted-foreground">biz #{l.business_id}</div></td>}
                  <td className="font-mono text-xs">
                    <span className={atras(l.versao_exe, l.versao_obrigatoria) ? 'text-warning' : undefined}
                      title={atras(l.versao_exe, l.versao_obrigatoria) ? `Abaixo da obrigatória (${l.versao_obrigatoria})` : undefined}>exe {l.versao_exe ?? '—'}</span>
                    <div className="text-muted-foreground">banco {l.versao_banco ?? '—'}</div>
                  </td>
                  <td><StatusBadge kind="frescor" value={l.frescor} rel={l.dt_ultimo_acesso ? dataBr(l.dt_ultimo_acesso) : 'nunca'} /></td>
                  <td className="font-mono text-xs">{dataBr(l.dt_validade)}</td>
                  <td>
                    <StatusBadge kind="licenca" value={l.bloqueado ? 'maquina_bloqueada' : 'ativa'} label={l.bloqueado ? 'Bloqueada' : 'Ativa'} />
                    {l.bloqueado && l.motivo && <div className="mt-1 max-w-56 text-xs text-muted-foreground">{l.motivo}</div>}
                  </td>
                  <td><a className="text-xs text-primary hover:underline" href={`/officeimpresso/licenca_log?licenca_id=${l.id}`}>Log</a></td>
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

LicencasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default LicencasIndex;
