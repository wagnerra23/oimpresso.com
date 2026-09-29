// Ponto/Conformidade — Painel de Conformidade CLT (thread 05 · ADR 0413 D0 · somente leitura).
// Carimbado do PT-04 Dashboard por criar-tela.mjs; âncora de design:
// prototipo-ui/cowork/Wagner/ponto-fechamento.jsx (função `Conformidade`).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader';
import { Inline } from '@/Components/layout';

interface Verificacao {
  id: string;
  titulo: string;
  /** Artigo literal. null = sem base legal citável → conferência, não apontamento. */
  artigo: string | null;
  tom: 'danger' | 'warn';
  /** false = a apuração não expõe o dado; total vem null e a tela mostra "—", nunca 0. */
  medido: boolean;
  total: number | null;
}

interface Caso {
  colaborador_id: number;
  nome: string;
  matricula: string | null;
  dia: string | null;
  apurado: string;
  limite: string;
  detalhe: string;
}

interface Cobertura {
  colaboradores: number;
  dias_apurados: number;
  /** Derivado das duas contagens no service (UC-CONF-09). */
  estado: 'sem_colaboradores' | 'sem_apuracao' | 'apurado';
}

interface Props {
  mes: string;
  painel?: { verificacoes: Verificacao[]; casos: Record<string, Caso[]>; cobertura: Cobertura };
}

const diaBR = (iso: string | null) => (iso ? iso.split('-').reverse().slice(0, 2).join('/') : '—');

export default function Conformidade({ mes, painel }: Props) {
  const [regra, setRegra] = useState<string | null>(null);
  const verificacoes = painel?.verificacoes ?? [];
  const medidas = verificacoes.filter((v) => v.medido);
  const total = medidas.reduce((n, v) => n + (v.total ?? 0), 0);
  const duras = medidas.filter((v) => v.tom === 'danger').reduce((n, v) => n + (v.total ?? 0), 0);
  const sel = verificacoes.find((v) => v.id === regra) ?? verificacoes.find((v) => (v.total ?? 0) > 0) ?? verificacoes[0];
  const casos = sel ? painel?.casos[sel.id] ?? [] : [];
  // Sem apuração, "0 violações" seria número inventado (charter do protótipo) — KPIs saem "—".
  const cobertura = painel?.cobertura;
  const apurado = cobertura?.estado === 'apurado';
  const semDado = !apurado;
  // "Sem PIS" vem do cadastro, não da apuração: segue contado mesmo sem dia apurado.
  const daApuracao = (v: Verificacao) => v.id !== 'sem_pis';
  const semPis = verificacoes.find((v) => v.id === 'sem_pis')?.total ?? 0;
  const notaSemDado =
    cobertura?.estado === 'sem_colaboradores'
      ? 'Nenhum colaborador com controle de ponto — sem quem apurar, não há o que verificar nesta competência.'
      : `Competência sem apuração — ${cobertura?.colaboradores ?? 0} ${cobertura?.colaboradores === 1 ? 'colaborador' : 'colaboradores'} com ponto controlado, nenhum dia apurado em ${mes}.${semPis > 0 ? ` ${semPis} sem PIS no cadastro.` : ''}`;

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PontoAreaHeader active="conformidade" />
      <Inline justify="between" align="center" gap={3} data-contract="cabecalho">
        <div>
          <h2 className="text-lg font-semibold">Conformidade CLT <span className="text-muted-foreground font-normal">· competência {mes}</span></h2>
          <p className="text-sm text-muted-foreground">Somente leitura — a correção acontece no Espelho ou em Intercorrências.</p>
        </div>
        <Inline gap={2} align="center">
          <Input
            type="month"
            aria-label="Competência"
            value={mes}
            onChange={(e) => e.target.value && router.get('/ponto/conformidade', { mes: e.target.value })}
            className="w-40"
          />
        </Inline>
      </Inline>

      <Deferred data="painel" fallback={<Skeleton className="h-16 w-full" />}>
        <div
          data-contract="nota"
          role="status"
          data-estado={cobertura?.estado}
          className={`rounded-md border p-3 text-sm ${semDado ? 'border-border bg-muted/30 text-muted-foreground' : total === 0 ? 'border-success/40 bg-success/10' : duras ? 'border-destructive/40 bg-destructive/10' : 'border-warning/40 bg-warning/10'}`}
        >
          {semDado
            ? notaSemDado
            : total === 0
            ? 'Nenhuma violação apurada nas verificações medidas.'
            : `${total} ${total === 1 ? 'apontamento' : 'apontamentos'} na competência — ${duras} de regra dura (Art. 66 e Art. 71).`}
        </div>
      </Deferred>

      <Deferred data="painel" fallback={<Skeleton className="h-28 w-full" />}>
        <div data-contract="kpis">
          <KpiGrid cols={6}>
            {verificacoes.map((v) => (
              <KpiCard
                key={v.id}
                label={v.titulo}
                value={v.medido && (apurado || !daApuracao(v)) ? v.total ?? 0 : '—'}
                description={
                  !v.medido
                    ? 'não medido: a apuração não expõe este dado'
                    : apurado || !daApuracao(v)
                    ? v.artigo ?? 'conferência — sem artigo citado'
                    : 'sem apuração na competência'
                }
                selected={sel?.id === v.id}
                onClick={() => setRegra(v.id)}
              />
            ))}
          </KpiGrid>
        </div>
      </Deferred>

      <Deferred data="painel" fallback={<Skeleton className="h-64 w-full" />}>
        <Card data-contract="casos">
          <CardHeader>
            <CardTitle>{sel?.titulo ?? 'Verificação'}</CardTitle>
            <CardDescription>
              {sel?.artigo ?? 'conferência'} ·{' '}
              {!sel?.medido
                ? 'não medido'
                : semDado && daApuracao(sel)
                ? 'sem apuração'
                : `${casos.length} ${casos.length === 1 ? 'caso' : 'casos'}`}
            </CardDescription>
          </CardHeader>
          <CardContent className="p-0 overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                <tr>
                  <th className="text-left p-3 font-medium">Colaborador</th>
                  <th className="text-left p-3 font-medium">Dia</th>
                  <th className="text-left p-3 font-medium">Apurado</th>
                  <th className="text-left p-3 font-medium">Limite</th>
                  <th className="text-left p-3 font-medium">Detalhe</th>
                  <th className="text-right p-3 font-medium">Ação</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {casos.length === 0 && (
                  <tr>
                    <td colSpan={6} className="p-8 text-center text-muted-foreground">
                      {sel?.medido === false
                        ? 'Esta verificação ainda não é medida.'
                        : semDado && sel && daApuracao(sel)
                        ? 'Sem apuração na competência — nada a listar.'
                        : 'Nenhum caso nesta verificação.'}
                    </td>
                  </tr>
                )}
                {casos.map((c, i) => (
                  <tr key={`${c.colaborador_id}-${c.dia}-${i}`}>
                    <td className="p-3">
                      <span className="font-medium">{c.nome}</span>
                      <span className="block text-xs text-muted-foreground font-mono">{c.matricula ?? '—'}</span>
                    </td>
                    <td className="p-3 font-mono text-xs">{diaBR(c.dia)}</td>
                    <td className="p-3 font-mono text-xs">{c.apurado}</td>
                    <td className="p-3 font-mono text-xs">{c.limite}</td>
                    <td className="p-3 text-xs text-muted-foreground">{c.detalhe}</td>
                    <td className="p-3 text-right">
                      <Button size="sm" variant="outline" asChild>
                        <Link href={`/ponto/espelho/${c.colaborador_id}?mes=${mes}`} className="text-xs gap-1">
                          Ver espelho <ArrowRight size={12} />
                        </Link>
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      </Deferred>
    </div>
  );
}

Conformidade.layout = (page: ReactNode) => (
  <AppShellV2 title="Conformidade CLT" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Conformidade' }]}>
    {page}
  </AppShellV2>
);
