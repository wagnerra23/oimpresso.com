// Timeline de acessos de uma máquina — Officeimpresso.
//
//   rota:     /officeimpresso/licenca_log/timeline/{licenca_id}
//   módulo:   Officeimpresso
//   padrão:   PT-07 Feed/Timeline
//   charter:  ./Timeline.charter.md · casos: ./Timeline.casos.md
//   RUNBOOK:  memory/requisitos/Officeimpresso/RUNBOOK-logs.md
//   paridade: memory/requisitos/Officeimpresso/logs-parity.md (itens 38-51)
//
// Responde uma pergunta só: "o Delphi desta máquina falou com o servidor, quando,
// e ele estava liberado na hora?". São os últimos 200 acessos a
// /connector/api/processa-dados-cliente — não o log inteiro da máquina.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import StatusBadge from '@/Components/shared/StatusBadge';
import EmptyState from '@/Components/shared/EmptyState';

interface Maquina {
  id: number;
  business_id: number | null;
  business_name: string | null;
  business_blocked: boolean;
  machine_blocked: boolean;
  hd: string | null;
  user_win: string | null;
  hostname: string | null;
  ip_interno: string | null;
}

interface LogRow {
  id: number;
  created_at: string;
  http_status: number | null;
  ip: string | null;
  duration_ms: number | null;
  /** Chega como objeto OU string JSON — ver pegadinha 7 do RUNBOOK. */
  metadata: Record<string, unknown> | string | null;
}

interface Props {
  maquina: Maquina;
  logs?: LogRow[];
  permissions: { pode_bloquear: boolean };
}

function dataHora(valor: string): string {
  const d = new Date(valor.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return valor;
  const p = (n: number) => String(n).padStart(2, '0');
  return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

/**
 * `metadata` vem do model com cast `array`, MAS as consultas que usam `DB::table`
 * fogem do cast e entregam string. O Blade trata os dois casos nos dois arquivos;
 * perder isso faz a coluna "Estado no login" mentir sobre o bloqueio.
 */
function estavaBloqueada(metadata: LogRow['metadata']): boolean {
  if (!metadata) return false;
  const obj = typeof metadata === 'string' ? seguroJson(metadata) : metadata;
  return Boolean(obj?.was_blocked);
}

function seguroJson(s: string): Record<string, unknown> | null {
  try {
    return JSON.parse(s);
  } catch {
    return null;
  }
}

function LogsTimeline({ maquina, logs, permissions }: Props) {
  const nome = maquina.user_win || maquina.hostname || 'sem hostname';

  const estado = maquina.business_blocked
    ? 'empresa_bloqueada'
    : maquina.machine_blocked
      ? 'maquina_bloqueada'
      : 'ativa';

  return (
    <>
      <PageHeader
        title={`Timeline — ${nome}`}
        subtitle={
          <>
            {maquina.business_name || '—'} · HD{' '}
            <span className="font-mono">{maquina.hd || '—'}</span> · IP{' '}
            <span className="font-mono">{maquina.ip_interno || '—'}</span>
          </>
        }
        actions={
          <div className="flex items-center gap-3">
            <StatusBadge kind="licenca" value={estado} />
            <Button asChild variant="outline" size="sm">
              <Link href="/officeimpresso/licenca_log">Voltar</Link>
            </Button>
          </div>
        }
      />

      <div className="p-6">
        <Card className="py-0">
          <CardContent className="px-0">
            <div className="border-b px-4 py-3">
              <h2 className="text-sm font-semibold">
                Últimos 200 acessos a <span className="font-mono">processa-dados-cliente</span>
                {logs && <span className="ml-1 font-normal text-muted-foreground">({logs.length})</span>}
              </h2>
            </div>

            <Deferred
              data="logs"
              fallback={
                <div className="space-y-2 p-4" aria-busy="true">
                  {Array.from({ length: 6 }).map((_, i) => (
                    <Skeleton key={i} className="h-10 w-full" />
                  ))}
                </div>
              }
            >
              {!logs?.length ? (
                <EmptyState
                  icon="history"
                  title="Nenhum acesso registrado para esta máquina."
                  description="A máquina está cadastrada, mas o Delphi ainda não chamou o servidor a partir dela."
                />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="border-b text-left text-xs uppercase text-muted-foreground">
                      <tr>
                        <th className="px-4 py-2">Data/hora</th>
                        <th className="px-4 py-2">Status HTTP</th>
                        <th className="px-4 py-2">Estado no login</th>
                        <th className="px-4 py-2">IP</th>
                        <th className="px-4 py-2">Duração</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {logs.map((log) => (
                        <tr key={log.id} className="hover:bg-muted/40">
                          <td className="px-4 py-2 font-mono tabular-nums">{dataHora(log.created_at)}</td>
                          <td className="px-4 py-2">
                            {log.http_status === null ? (
                              <span className="text-muted-foreground">—</span>
                            ) : (
                              <StatusBadge
                                kind="licenca_no_acesso"
                                value={log.http_status < 400 ? 'liberada' : 'bloqueada'}
                                label={String(log.http_status)}
                              />
                            )}
                          </td>
                          <td className="px-4 py-2">
                            <StatusBadge
                              kind="licenca_no_acesso"
                              value={estavaBloqueada(log.metadata) ? 'bloqueada' : 'liberada'}
                            />
                          </td>
                          <td className="px-4 py-2 font-mono">
                            {log.ip || <span className="text-muted-foreground">—</span>}
                          </td>
                          <td className="px-4 py-2 font-mono tabular-nums">
                            {log.duration_ms ? `${log.duration_ms}ms` : <span className="text-muted-foreground">—</span>}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Deferred>
          </CardContent>
        </Card>

        {!permissions.pode_bloquear && (
          <p className="mt-3 text-xs text-muted-foreground">
            Você tem acesso de leitura. Bloquear ou liberar esta máquina exige a permissão{' '}
            <span className="font-mono">officeimpresso.licencas.gerenciar</span>.
          </p>
        )}
      </div>
    </>
  );
}

LogsTimeline.layout = (page: ReactNode) => (
  <AppShellV2 title="Timeline da máquina">{page}</AppShellV2>
);

export default LogsTimeline;
