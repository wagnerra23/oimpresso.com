// Máquinas Cadastradas — Officeimpresso (licenciamento desktop Delphi).
//
//   rota:     /officeimpresso/licenca_log
//   módulo:   Officeimpresso
//   padrão:   PT-01 Lista
//   charter:  ./Index.charter.md · casos: ./Index.casos.md
//   RUNBOOK:  memory/requisitos/Officeimpresso/RUNBOOK-logs.md
//   paridade: memory/requisitos/Officeimpresso/logs-parity.md
//
// ⚠️ A rota se chama `licenca_log` mas a tela NÃO lista log: lista MÁQUINAS
// (`licenca_computador`) enriquecidas com o último acesso registrado. O título é
// "Máquinas Cadastradas" — renomear a rota não é escopo desta onda (pegadinha 2).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import EmptyState from '@/Components/shared/EmptyState';
import KpiCard from '@/Components/shared/KpiCard';
import MaquinasTable, { type Maquina } from './_components/MaquinasTable';

interface Filtros {
  q: string;
  estado_atual: string | null;
  business_id: string | null;
  licenca_id: string | null;
  hd: string;
}

interface Kpis {
  total_maquinas: number;
  maquinas_bloqueadas: number;
  empresas_bloqueadas: number;
  chamadas_24h: number;
}

interface Props {
  maquinas?: Maquina[];
  kpis?: Kpis;
  filters: Filtros;
  permissions: { pode_ver_todas_empresas: boolean; pode_bloquear: boolean };
}

const ROTA = '/officeimpresso/licenca_log';

// Sentinela pro item "Todos". NUNCA `value=""`: o Radix Select LANÇA e derruba
// a árvore React inteira (tela branca) — §5 proibicoes 2026-06-29 + o docblock
// do SafeSelectItem. Estas opções são fixas, então o sentinela basta; se um dia
// virarem data-driven, trocar por <SafeSelectItem>.
const TODOS = '__all__';

function LogsIndex({ maquinas, kpis, filters, permissions }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const primeiraRenderizacao = useRef(true);

  /** Preserva os filtros já aplicados ao montar um link novo. */
  const urlComFiltro = (extra: Record<string, string | number>) => {
    const p = new URLSearchParams();
    if (filters.q) p.set('q', filters.q);
    if (filters.estado_atual) p.set('estado_atual', filters.estado_atual);
    for (const [k, v] of Object.entries(extra)) p.set(k, String(v));
    return `${ROTA}?${p.toString()}`;
  };

  // Busca com debounce + partial reload: só `maquinas` e `filters` voltam, os
  // KPIs (que são globais e não seguem o filtro) não pagam a viagem de novo.
  useEffect(() => {
    if (primeiraRenderizacao.current) {
      primeiraRenderizacao.current = false;
      return;
    }
    const t = setTimeout(() => {
      if (q.trim() === (filters.q ?? '').trim()) return;
      router.get(
        ROTA,
        { ...limparVazios(filters), q: q.trim() },
        { only: ['maquinas', 'filters'], preserveState: true, preserveScroll: true, replace: true },
      );
    }, 300);
    return () => clearTimeout(t);
  }, [q, filters]);

  const trocarEstado = (valor: string) => {
    const estado = valor === TODOS ? '' : valor;
    router.get(ROTA, { ...limparVazios(filters), estado_atual: estado }, { preserveScroll: true });
  };

  const chips = [
    filters.business_id && { rotulo: `Empresa #${filters.business_id}`, chave: 'business_id' },
    filters.licenca_id && { rotulo: `Equipamento #${filters.licenca_id}`, chave: 'licenca_id' },
    filters.hd && { rotulo: `HD ${filters.hd}`, chave: 'hd' },
  ].filter(Boolean) as { rotulo: string; chave: string }[];

  const temFiltro = chips.length > 0 || !!filters.q || !!filters.estado_atual;

  const removerChip = (chave: string) =>
    router.get(ROTA, limparVazios({ ...filters, [chave]: '' }), { preserveScroll: true });

  return (
    <>
      <PageHeader
        title="Máquinas Cadastradas"
        subtitle={
          <>
            O cadastro é populado por <code className="font-mono">/connector/api/processa-dados-cliente</code>;
            cada linha mostra o último acesso registrado.
          </>
        }
      />

      <div className="space-y-4 p-6">
        <Deferred data="kpis" fallback={<GradeSkeleton n={4} altura="h-24" />}>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard label="Máquinas cadastradas" value={kpis?.total_maquinas ?? 0} icon="monitor" description="total de equipamentos" />
            <KpiCard label="Máquinas bloqueadas" value={kpis?.maquinas_bloqueadas ?? 0} icon="lock" tone="warning" description="bloqueio individual" />
            <KpiCard label="Empresas bloqueadas" value={kpis?.empresas_bloqueadas ?? 0} icon="ban" tone="danger" description="bloqueio em massa" />
            <KpiCard label="Acessos 24h" value={kpis?.chamadas_24h ?? 0} icon="refresh-cw" tone="success" description="processa-dados-cliente" />
          </div>
        </Deferred>

        <div className="flex flex-wrap items-center gap-2">
          <Input
            type="search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Nome, CNPJ, HD, hostname, IP…"
            aria-label="Buscar empresa ou máquina"
            className="max-w-sm"
          />
          <Select value={filters.estado_atual || TODOS} onValueChange={trocarEstado}>
            <SelectTrigger className="w-44" aria-label="Estado atual">
              <SelectValue placeholder="Estado atual" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={TODOS}>Todos</SelectItem>
              <SelectItem value="ativa">Ativa</SelectItem>
              <SelectItem value="bloqueada">Bloqueada</SelectItem>
            </SelectContent>
          </Select>
          {temFiltro && (
            <Button variant="ghost" onClick={() => router.get(ROTA)}>
              Limpar
            </Button>
          )}
          {chips.map((c) => (
            <Button key={c.chave} variant="secondary" size="sm" onClick={() => removerChip(c.chave)}>
              {c.rotulo} ✕
            </Button>
          ))}
        </div>

        <Card className="py-0">
          <CardContent className="px-0">
            <Deferred data="maquinas" fallback={<GradeSkeleton n={6} altura="h-10" />}>
              {!maquinas?.length ? (
                temFiltro ? (
                  <EmptyState
                    icon="search-x"
                    variant="search"
                    title="Nenhuma máquina encontrada com os filtros aplicados."
                    action={<Button variant="outline" onClick={() => router.get(ROTA)}>Limpar filtros</Button>}
                  />
                ) : (
                  <EmptyState
                    icon="monitor"
                    title="Nenhuma máquina cadastrada ainda."
                    description="A tabela é populada pela rotina /connector/api/processa-dados-cliente quando o Delphi envia CNPJ + HD."
                  />
                )
              ) : (
                <MaquinasTable
                  maquinas={maquinas}
                  podeBloquear={permissions.pode_bloquear}
                  urlComFiltro={urlComFiltro}
                />
              )}
            </Deferred>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

/** Remove chaves vazias pra não sujar a query string com `?hd=&licenca_id=`. */
function limparVazios(f: Record<string, unknown>): Record<string, string> {
  const saida: Record<string, string> = {};
  for (const [k, v] of Object.entries(f)) {
    if (v !== null && v !== undefined && String(v) !== '') saida[k] = String(v);
  }
  return saida;
}

function GradeSkeleton({ n, altura }: { n: number; altura: string }) {
  return (
    <div className="space-y-2 p-3" aria-busy="true">
      {Array.from({ length: n }).map((_, i) => (
        <Skeleton key={i} className={`w-full ${altura}`} />
      ))}
    </div>
  );
}

LogsIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Máquinas Cadastradas">{page}</AppShellV2>
);

export default LogsIndex;
