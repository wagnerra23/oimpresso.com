// Ficha da empresa licenciada — Officeimpresso (dados, assinatura e máquinas do desktop WR Comercial).
//
//   rotas:    /officeimpresso/computadores (empresa da sessão) e
//             /officeimpresso/licenca_computado/licencas/{id} (qualquer empresa — decisão D1 [W] 2026-10-12),
//             atrás da flag useV2OfficeimpressoEmpresa; flag OFF = Blade computadores, rota de fuga
//   padrão:   PT-03 Detalhe
//   charter:  ./Show.charter.md · casos: ./Show.casos.md
//
// Só leitura nesta entrega: "Editar" (configuração), bloquear a empresa e bloquear máquina seguem na
// Blade até o PR das ações (empresa-parity 16-21, 34). O link "Abrir na tela antiga" leva até lá.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ClipboardList } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import EmptyState from '@/Components/shared/EmptyState';
import StatusBadge from '@/Components/shared/StatusBadge';

interface Empresa {
  id: number; name: string; razao_social: string | null; rua: string | null; telefone: string | null;
  versao_obrigatoria: string | null; versao_disponivel: string | null; caminho_banco_servidor: string | null;
  ultimo_acesso: string | null; bloqueado: boolean; pacote_url: string;
}
interface Computador {
  id: number; dt_cadastro: string | null; user_win: string | null; pasta_instalacao: string | null;
  versao_exe: string | null; ip_interno: string | null; caminho_banco: string | null;
  dt_ultimo_acesso: string | null; bloqueado: boolean;
}
interface Assinatura { limite_maquinas: number | null; fim: string | null; dias_restantes: number | null }
interface Props { empresa: Empresa; computadores?: Computador[]; assinatura?: Assinatura }

const dataHoraBr = (d: string | null) => (d ? new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const corta = (s: string | null, n: number) => (!s ? '—' : s.length > n ? `${s.slice(0, n)}…` : s);

function EmpresaShow({ empresa, computadores, assinatura }: Props) {
  return (
    <div className="pb-8">
      <div data-contract="header">
        <PageHeader title="Licenças Office Impresso" subtitle="Gestão de desktops Delphi por empresa"
          actions={
            <Button asChild variant="ghost" size="sm">
              <a href={`${typeof window !== 'undefined' ? window.location.pathname : ''}?legacy=1`}>Abrir na tela antiga</a>
            </Button>
          } />
      </div>
      <div className="flex flex-col gap-4 px-6 pt-4">
        <section data-contract="ficha" className="rounded-md border p-4" aria-labelledby="oi-emp-nome">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <h2 id="oi-emp-nome" className="text-lg font-semibold">{empresa.name}</h2>
              <p className="text-sm text-muted-foreground">{empresa.razao_social || '—'}</p>
            </div>
            <StatusBadge kind="licenca" value={empresa.bloqueado ? 'empresa_bloqueada' : 'ativa'} label={empresa.bloqueado ? 'Bloqueada' : 'Ativa'} />
          </div>
          <dl className="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            <Campo rotulo="Endereço" valor={empresa.rua} />
            <Campo rotulo="Telefone" valor={empresa.telefone} />
            <Campo rotulo="Versão obrigatória" valor={empresa.versao_obrigatoria} mono />
            <Campo rotulo="Versão disponível" valor={empresa.versao_disponivel} mono />
            <Campo rotulo="Caminho do banco" valor={empresa.caminho_banco_servidor} mono />
            <Campo rotulo="Último acesso" valor={empresa.ultimo_acesso ? dataHoraBr(empresa.ultimo_acesso) : null} mono />
            <Deferred data="assinatura" fallback={<Skeleton className="h-5 w-48" />}>
              <BlocoAssinatura assinatura={assinatura} />
            </Deferred>
          </dl>
          <div className="mt-3 flex flex-wrap gap-2">
            <Button asChild variant="outline" size="sm"><a href={empresa.pacote_url}>Ver pacote</a></Button>
          </div>
        </section>

        <section data-contract="computadores" className="flex flex-col gap-2" aria-labelledby="oi-emp-maquinas">
          <div className="flex items-center justify-between">
            <h3 id="oi-emp-maquinas" className="font-semibold">
              Computadores{computadores && <span className="text-muted-foreground"> ({computadores.length})</span>}
            </h3>
            <Button asChild variant="ghost" size="sm">
              <a href={`/officeimpresso/licenca_log?business_id=${empresa.id}`}><ClipboardList className="size-4" /> Ver log da empresa</a>
            </Button>
          </div>
          <Deferred data="computadores" fallback={<Skeleton className="h-48 w-full" />}>
            <Tabela computadores={computadores ?? []} />
          </Deferred>
        </section>
      </div>
    </div>
  );
}

function Campo({ rotulo, valor, mono }: { rotulo: string; valor: string | null; mono?: boolean }) {
  return (
    <div className="flex gap-2">
      <dt className="text-muted-foreground">{rotulo}:</dt>
      <dd className={mono ? 'font-mono text-xs leading-5' : undefined}>{valor || '—'}</dd>
    </div>
  );
}

// Os blocos só existem se houver pacote/assinatura — o Blade os omite em vez de mostrar "—".
function BlocoAssinatura({ assinatura }: { assinatura?: Assinatura }) {
  if (!assinatura) return null;
  return (
    <>
      {assinatura.limite_maquinas !== null && (
        <Campo rotulo="Limite de Máquinas" valor={assinatura.limite_maquinas === 0 ? 'Ilimitado' : String(assinatura.limite_maquinas)} />
      )}
      {assinatura.fim !== null && <Campo rotulo="Data final" valor={assinatura.fim} />}
      {assinatura.dias_restantes !== null && <Campo rotulo="Restante" valor={`${assinatura.dias_restantes} dia (s)`} />}
    </>
  );
}

function Tabela({ computadores }: { computadores: Computador[] }) {
  if (computadores.length === 0) return <EmptyState title="Nenhum registro encontrado" description="A empresa ainda não tem máquina registrada." />;
  return (
    <div data-contract="grade" className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead><tr className="border-b text-left text-xs text-muted-foreground">
          <th className="py-2">#</th><th>Cadastro</th><th>Máquina</th><th>Executável</th><th>Versão</th>
          <th>IP</th><th>Banco</th><th>Último acesso</th><th>Status</th><th><span className="sr-only">Ações</span></th>
        </tr></thead>
        <tbody>
          {computadores.map((c) => (
            <tr key={c.id} className="border-b align-top">
              <td className="py-2 font-mono text-xs">{c.id}</td>
              <td className="font-mono text-xs">{c.dt_cadastro ?? '—'}</td>
              <td className="font-semibold">{c.user_win || '—'}</td>
              <td className="font-mono text-xs" title={c.pasta_instalacao ?? undefined}>{corta(c.pasta_instalacao, 30)}</td>
              <td className="font-mono text-xs">{c.versao_exe || '—'}</td>
              <td className="font-mono text-xs">{c.ip_interno || '—'}</td>
              <td className="font-mono text-xs" title={c.caminho_banco ?? undefined}>{corta(c.caminho_banco, 20)}</td>
              <td className="font-mono text-xs">{c.dt_ultimo_acesso ?? '—'}</td>
              <td><StatusBadge kind="licenca" value={c.bloqueado ? 'maquina_bloqueada' : 'ativa'} label={c.bloqueado ? 'Bloqueada' : 'Ativa'} /></td>
              <td><a className="text-xs text-primary hover:underline" href={`/officeimpresso/licenca_log?licenca_id=${c.id}`}>Log</a></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

EmpresaShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default EmpresaShow;
