// Tabela de máquinas cadastradas — privada da tela Officeimpresso/Logs/Index.
//
// Sai daqui porque a Index já carrega header + KPIs + toolbar + chips; junto,
// passava de 300 linhas e virava PR não-revisável (commit-discipline Tier A).
// Vive em `_components/` que é, por construção, exempto do hook de RUNBOOK —
// componente privado não é tela migrável.
//
// Paridade: memory/requisitos/Officeimpresso/logs-parity.md itens 18-35.

import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import StatusBadge from '@/Components/shared/StatusBadge';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

export interface Maquina {
  licenca_id: number;
  business_id: number | null;
  business_name: string;
  business_blocked: boolean;
  machine_blocked: boolean;
  hd: string | null;
  user_win: string | null;
  hostname: string | null;
  ip_interno: string | null;
  versao_exe: string | null;
  versao_banco: string | null;
  last_login: string | null;
  last_ip: string | null;
  /** Tri-estado: null = nunca houve log. Ver pegadinha 5 do RUNBOOK. */
  was_blocked_last: boolean | null;
  dt_ultimo_acesso: string | null;
  last_location: { name: string; cnpj: string | null } | null;
}

interface Props {
  maquinas: Maquina[];
  podeBloquear: boolean;
  /** Monta a URL da lista preservando os filtros já aplicados. */
  urlComFiltro: (extra: Record<string, string | number>) => string;
}

/** `d/m/Y H:i:s` — o mesmo formato do Blade, sem depender de locale do browser. */
function dataHora(valor: string): string {
  const d = new Date(valor.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return valor;
  const p = (n: number) => String(n).padStart(2, '0');
  return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

type Confirmacao = { titulo: string; descricao: string; url: string } | null;

export default function MaquinasTable({ maquinas, podeBloquear, urlComFiltro }: Props) {
  const [confirmacao, setConfirmacao] = useState<Confirmacao>(null);

  // As duas ações de bloqueio são `Route::get` no legado, protegidas só por
  // `confirm()` do browser. NÃO renderizamos elas como <Link>/<a href>: um href
  // é seguível por prefetch, crawler e "abrir em nova aba" — que é o vetor real
  // do GET-que-muda-estado. Aqui só um clique deliberado dispara, e ainda passa
  // pelo diálogo. A conversão pra POST é a divergência D1 do -parity.md e sai em
  // PR próprio: mexer em `Modules/**/Routes/**` deve o Infra Contract.
  const executar = () => {
    if (!confirmacao) return;
    router.visit(confirmacao.url, { preserveScroll: true });
    setConfirmacao(null);
  };

  return (
    <>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs uppercase text-muted-foreground">
            <tr>
              <th className="px-3 py-2">Empresa</th>
              <th className="px-3 py-2">Location / CNPJ</th>
              <th className="px-3 py-2">Máquina</th>
              <th className="px-3 py-2">HD</th>
              <th className="px-3 py-2">Versão</th>
              <th className="px-3 py-2">IP</th>
              <th className="px-3 py-2">Último login</th>
              <th className="px-3 py-2">Estado no último login</th>
              <th className="px-3 py-2">Estado atual</th>
              <th className="px-3 py-2 text-right">Ações</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {maquinas.map((m) => (
              <tr key={m.licenca_id} className="hover:bg-muted/40">
                <td className="px-3 py-2">
                  {m.business_id ? (
                    <Link
                      href={urlComFiltro({ business_id: m.business_id })}
                      className="font-medium text-primary hover:underline"
                      title="Filtrar por esta empresa"
                    >
                      {m.business_name}
                    </Link>
                  ) : (
                    <span className="text-muted-foreground">—</span>
                  )}
                </td>

                <td className="px-3 py-2">
                  {m.last_location ? (
                    <>
                      <span className="font-medium">{m.last_location.name}</span>
                      {m.last_location.cnpj && (
                        <span className="block font-mono text-xs text-muted-foreground">
                          CNPJ {m.last_location.cnpj}
                        </span>
                      )}
                    </>
                  ) : (
                    <span className="text-muted-foreground">—</span>
                  )}
                </td>

                <td className="px-3 py-2">
                  <Link
                    href={urlComFiltro({ licenca_id: m.licenca_id })}
                    className="font-mono font-medium text-primary hover:underline"
                    title="Filtrar por este equipamento"
                  >
                    {m.user_win || m.hostname || '(sem hostname)'}
                  </Link>
                </td>

                <td className="px-3 py-2 font-mono">
                  {m.hd ? (
                    <Link
                      href={urlComFiltro({ hd: m.hd })}
                      className="text-primary hover:underline"
                      title="Ver todas as empresas com este HD"
                    >
                      {m.hd}
                    </Link>
                  ) : (
                    <span className="text-muted-foreground">—</span>
                  )}
                </td>

                <td className="px-3 py-2 font-mono">
                  {m.versao_exe || m.versao_banco ? (
                    <>
                      {m.versao_exe && <span title="Versão do .exe">{m.versao_exe}</span>}
                      {m.versao_banco && (
                        <span className="text-xs text-muted-foreground" title="Versão do banco">
                          {' '}/ {m.versao_banco}
                        </span>
                      )}
                    </>
                  ) : (
                    <span className="text-muted-foreground">—</span>
                  )}
                </td>

                <td className="px-3 py-2 font-mono">
                  {m.last_ip || m.ip_interno || <span className="text-muted-foreground">—</span>}
                </td>

                <td className="px-3 py-2 font-mono tabular-nums">
                  {m.last_login ? (
                    dataHora(m.last_login)
                  ) : m.dt_ultimo_acesso ? (
                    <>
                      {dataHora(m.dt_ultimo_acesso)}
                      {/* O Blade distingue log real de data do cadastro. Perder
                          esse rótulo faria a coluna afirmar um acesso que não
                          foi logado (item 24 do -parity.md, severidade alta). */}
                      <span className="block text-xs text-muted-foreground">(cadastro)</span>
                    </>
                  ) : (
                    <span className="text-muted-foreground">nunca</span>
                  )}
                </td>

                <td className="px-3 py-2">
                  {m.was_blocked_last === null ? (
                    <span className="text-muted-foreground">—</span>
                  ) : (
                    <StatusBadge
                      kind="licenca_no_acesso"
                      value={m.was_blocked_last ? 'bloqueada' : 'liberada'}
                    />
                  )}
                </td>

                <td className="px-3 py-2">
                  <StatusBadge
                    kind="licenca"
                    value={
                      m.business_blocked
                        ? 'empresa_bloqueada'
                        : m.machine_blocked
                          ? 'maquina_bloqueada'
                          : 'ativa'
                    }
                  />
                </td>

                <td className="px-3 py-2 text-right">
                  {!podeBloquear ? null : m.business_blocked && m.business_id ? (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() =>
                        setConfirmacao({
                          titulo: 'Desbloquear a empresa inteira?',
                          descricao: `Isto libera o Delphi de TODAS as máquinas de ${m.business_name}, não só desta.`,
                          url: `/officeimpresso/licenca_computador/businessbloqueado/${m.business_id}`,
                        })
                      }
                    >
                      Desbloquear empresa
                    </Button>
                  ) : (
                    <Button
                      size="sm"
                      variant={m.machine_blocked ? 'outline' : 'destructive'}
                      onClick={() =>
                        setConfirmacao({
                          titulo: m.machine_blocked
                            ? 'Desbloquear esta máquina?'
                            : 'Bloquear esta máquina?',
                          descricao: `${m.user_win || m.hostname || 'Máquina sem hostname'} — ${m.business_name}.`,
                          url: `/officeimpresso/licenca_computador/${m.licenca_id}/toggle-block`,
                        })
                      }
                    >
                      {m.machine_blocked ? 'Desbloquear máquina' : 'Bloquear máquina'}
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <AlertDialog open={confirmacao !== null} onOpenChange={(o) => !o && setConfirmacao(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{confirmacao?.titulo}</AlertDialogTitle>
            <AlertDialogDescription>{confirmacao?.descricao}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={executar}>Confirmar</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
