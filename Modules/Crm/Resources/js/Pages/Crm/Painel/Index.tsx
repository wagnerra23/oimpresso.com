// @memcofre
//   tela: /crm/dashboard
//   module: Crm
//   stories: thread Crm/04 (CrmDashboardController@index · Blade → Inertia)
//   permissao: seções por crm.access_*_schedule · crm.access_*_leads · Admin#<biz>
//
// Painel do CRM (PT-04 Dashboard). Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade.jsx → TelaPainel()
// Contrato: governance/design/contracts/crm-painel.contract.json
//
// Os números caros chegam por `Inertia::defer`. O bloco do negócio só existe para Admin —
// o backend nem manda o dado para quem não vê (mesmo gate da Blade, `?classico=1`).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { PageHeader } from '@/Components/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';

interface Permissoes { acompanhamentos: boolean; leads: boolean; chamadas: boolean; admin: boolean }
interface Pessoal {
  hoje: number | null; meus_leads: number | null; convertidos: number;
  por_status: { rotulo: string; total: number }[] | null;
  chamadas: { hoje: number; ontem: number; mes: number } | null;
}
interface Aniversario { id: number; name: string; dob?: string }
interface Negocio {
  clientes: number; leads: number; total_fontes: number; total_fases: number;
  status: { value: string; label: string }[];
  por_fonte: { fonte: string; total: number; conversao: number }[];
  por_fase: { fase: string; total: number }[];
  aniversarios: { hoje: Aniversario[]; proximos: Aniversario[] };
  por_usuario: (Record<string, number | string> & { usuario: string; nenhum: number; total: number })[];
  conversao: { usuario: string; total: number }[];
  chamadas: { usuario: string; hoje: number; mes: number; todas: number }[] | null;
}
interface Props { permissoes: Permissoes; pessoal?: Pessoal; negocio?: Negocio | null }

const espera = <Skeleton className="h-32 w-full" />;

function Bloco({ 'data-contract': contrato, titulo, acao, children }: { 'data-contract': string; titulo: ReactNode; acao?: ReactNode; children: ReactNode }) {
  return (
    <Card data-contract={contrato}>
      <CardContent className="flex flex-col gap-3 p-4">
        <div className="flex items-center justify-between gap-2">
          <h3 className="text-sm font-medium">{titulo}</h3>
          {acao}
        </div>
        {children}
      </CardContent>
    </Card>
  );
}

function Tabela({ cabecalho, linhas }: { cabecalho: string[]; linhas: (string | number)[][] }) {
  return (
    <table className="w-full text-sm">
      <thead>
        <tr className="text-left text-muted-foreground">
          {cabecalho.map((c, i) => <th key={c} className={i ? 'text-right font-medium' : 'font-medium'}>{c}</th>)}
        </tr>
      </thead>
      <tbody>
        {linhas.length === 0 && <tr><td colSpan={cabecalho.length} className="py-2 text-center text-muted-foreground">Sem dados</td></tr>}
        {linhas.map((l, i) => (
          <tr key={i} className="border-t">
            {l.map((v, j) => <td key={j} className={j ? 'py-1 text-right tabular-nums' : 'py-1'}>{v}</td>)}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

export default function PainelIndex({ permissoes, pessoal, negocio }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Painel do CRM" subtitle="Seus acompanhamentos, leads e chamadas — e, para Admin, o quadro do negócio." />

      <Deferred data="pessoal" fallback={espera}>
        <PainelPessoal permissoes={permissoes} pessoal={pessoal} />
      </Deferred>

      {permissoes.admin && (
        <Deferred data="negocio" fallback={espera}>
          {negocio ? <PainelNegocio negocio={negocio} /> : null}
        </Deferred>
      )}
    </div>
  );
}

function PainelPessoal({ permissoes, pessoal }: { permissoes: Permissoes; pessoal?: Pessoal }) {
  if (!pessoal) return null;
  return (
    <>
      <KpiGrid cols={4}>
        {pessoal.hoje !== null && <KpiCard label="Acompanhamentos de hoje" value={pessoal.hoje} tone="info" />}
        {pessoal.meus_leads !== null && <KpiCard label="Meus leads" value={pessoal.meus_leads} tone="info" />}
        <KpiCard label="Meus leads convertidos" value={pessoal.convertidos} tone="success" />
        {pessoal.chamadas && <KpiCard label="Chamadas hoje" value={pessoal.chamadas.hoje} tone="warning" />}
      </KpiGrid>
      <div className="grid gap-4 md:grid-cols-2">
        {permissoes.acompanhamentos && pessoal.por_status && (
          <Bloco data-contract="crm-painel-meus" titulo="Meus acompanhamentos">
            <Tabela cabecalho={['Status', 'Total']} linhas={pessoal.por_status.map((s) => [s.rotulo, s.total])} />
          </Bloco>
        )}
        {pessoal.chamadas && (
          <Bloco data-contract="crm-painel-chamadas" titulo="Meus registros de chamadas">
            <Tabela cabecalho={['Período', 'Total']} linhas={[
              ['Chamadas hoje', pessoal.chamadas.hoje],
              ['Chamadas ontem', pessoal.chamadas.ontem],
              ['Chamadas neste mês', pessoal.chamadas.mes],
            ]} />
          </Bloco>
        )}
      </div>
    </>
  );
}

function PainelNegocio({ negocio }: { negocio: Negocio }) {
  const [marcados, setMarcados] = useState<number[]>([]);
  const alternar = (id: number) => setMarcados((m) => (m.includes(id) ? m.filter((x) => x !== id) : [...m, id]));
  const desejos = `/crm/campaigns/create?contact_ids=${marcados.join(',')}`;
  const lista = (titulo: string, pessoas: Aniversario[]) => (
    <div className="flex flex-col gap-1">
      <p className="text-xs font-semibold">{titulo}</p>
      {pessoas.length === 0 && <span className="text-xs text-muted-foreground">Sem dados</span>}
      {pessoas.map((p) => (
        <label key={p.id} className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={marcados.includes(p.id)} onChange={() => alternar(p.id)} />
          <span>{p.name}</span>
          {p.dob && <small className="text-muted-foreground">{p.dob.split('-').reverse().join('/')}</small>}
        </label>
      ))}
    </div>
  );

  return (
    <>
      <KpiGrid cols={4}>
        <KpiCard label="Clientes" value={negocio.clientes} />
        <KpiCard label="Leads" value={negocio.leads} />
        <KpiCard label="Fontes" value={negocio.total_fontes} tone="warning" />
        <KpiCard label="Estágios de vida" value={negocio.total_fases} tone="warning" />
      </KpiGrid>
      <div className="grid gap-4 md:grid-cols-2">
        <Bloco data-contract="crm-painel-fontes" titulo="Fontes">
          <Tabela cabecalho={['Fonte', 'Total', 'Conversão']} linhas={negocio.por_fonte.map((f) => [f.fonte, f.total, `${f.conversao}%`])} />
        </Bloco>
        <Bloco data-contract="crm-painel-fases" titulo="Estágios de vida">
          <Tabela cabecalho={['Estágio', 'Total']} linhas={negocio.por_fase.map((f) => [f.fase, f.total])} />
        </Bloco>
      </div>
      <Bloco
        data-contract="crm-painel-aniversarios"
        titulo="Aniversários"
        acao={marcados.length > 0
          ? <Button asChild size="sm"><a href={desejos}>Enviar desejos</a></Button>
          : <Button size="sm" disabled>Enviar desejos</Button>}
      >
        <div className="grid gap-4 md:grid-cols-2">
          {lista('Hoje', negocio.aniversarios.hoje)}
          {lista('Próximos', negocio.aniversarios.proximos)}
        </div>
      </Bloco>
      <Bloco data-contract="crm-painel-por-usuario" titulo="Acompanhamentos por usuário">
        <Tabela
          cabecalho={['Usuário', ...negocio.status.map((s) => s.label), 'Nenhum', 'Acompanhamentos totais']}
          linhas={negocio.por_usuario.map((u) => [u.usuario, ...negocio.status.map((s) => Number(u[s.value] ?? 0)), u.nenhum, u.total])}
        />
      </Bloco>
      <div className="grid gap-4 md:grid-cols-2">
        <Bloco data-contract="crm-painel-conversao" titulo="Leads convertidos em cliente">
          <Tabela cabecalho={['Convertido por', 'Total']} linhas={negocio.conversao.map((c) => [c.usuario, c.total])} />
        </Bloco>
        {negocio.chamadas && (
          <Bloco data-contract="crm-painel-chamadas-todos" titulo="Registro de chamadas — todos os usuários">
            <Tabela cabecalho={['Usuário', 'Hoje', 'No mês', 'Todas']} linhas={negocio.chamadas.map((c) => [c.usuario, c.hoje, c.mes, c.todas])} />
          </Bloco>
        )}
      </div>
    </>
  );
}

PainelIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
