// Essentials/Painel — carimbado do PT-04 Dashboard por criar-tela.mjs (UI-0013).
// Alvo de layout: prototipo-ui/cowork/Wagner/hrm-page.jsx (`Painel`). Dado: SÓ o que
// DashboardController@hrmDashboard já calculava — card sem agregado mostra "—" e link,
// nunca número inventado (Painel.charter.md). A tela não escreve nada.
// RUNBOOK: memory/requisitos/Essentials/RUNBOOK-painel.md (ADR 0104 F1 PLAN)
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';

interface Periodo { id: number; inicio: string; fim: string }
interface Licenca extends Periodo { tipo: string }
interface Feriado extends Periodo { nome: string; local: string | null }
interface Faixa { inicio: string; fim: string; pct: string }
interface PainelData {
  colaboradores: number | null;
  setores: { nome: string; total: number }[];
  minhas_licencas: Licenca[];
  feriados: Feriado[];
  faixas_meta: Faixa[];
}
interface Props { is_admin: boolean; painel?: PainelData }

const dt = (iso: string) => iso.split('-').reverse().slice(0, 2).join('/');
const periodo = (p: Periodo) => (p.inicio === p.fim ? dt(p.inicio) : `${dt(p.inicio)} – ${dt(p.fim)}`);
// Exibição de valor GRAVADO (faixa de meta) — nenhuma aritmética de valor roda aqui.
const brl = (v: string) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const ir = (href: string) => router.visit(href);

function Linha({ t, s, v }: { t: ReactNode; s?: ReactNode; v?: ReactNode }) {
  return (
    <li className="flex items-center justify-between gap-3 py-2">
      <span className="flex min-w-0 flex-col">
        <span className="text-sm font-medium">{t}</span>
        {s && <span className="text-xs text-muted-foreground">{s}</span>}
      </span>
      {v !== undefined && <span className="shrink-0 text-sm tabular-nums">{v}</span>}
    </li>
  );
}

function Bloco({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <Card>
      <CardHeader className="pb-2"><CardTitle className="text-base">{titulo}</CardTitle></CardHeader>
      <CardContent><ul className="divide-y">{children}</ul></CardContent>
    </Card>
  );
}

const Vazio = ({ texto }: { texto: string }) => <li className="py-2 text-sm text-muted-foreground">{texto}</li>;

export default function Painel({ is_admin, painel }: Props) {
  return (
    <AppShellV2>
      {/* `data-contract` = âncora do contrato de tela (governance/design/contracts/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <div data-contract="cabecalho">
        <PageHeader title="Painel" subtitle="Pessoas: licenças, metas, feriados e setores" />
      </div>
      <Deferred data="painel" fallback={<Skeleton className="h-64 w-full" />}>
        <Conteudo is_admin={is_admin} painel={painel} />
      </Deferred>
    </AppShellV2>
  );
}

function Conteudo({ is_admin, painel }: { is_admin: boolean; painel?: PainelData }) {
  if (!painel) return null;
  const p = painel;
  return (
    <>
      <div data-contract="kpis">
        <KpiGrid cols={is_admin ? 3 : 2}>
          {is_admin && (
            <KpiCard label="Colaboradores" value={p.colaboradores ?? '—'} description={`${p.setores.length} setores`} onClick={() => ir('/users')} />
          )}
          <KpiCard label="Licenças pendentes" value="—" description="ver em Licenças" onClick={() => ir('/hrm/leave')} />
          {is_admin && (
            <KpiCard label="Presença de hoje" value="—" description="a jornada é do Ponto" onClick={() => ir('/ponto')} />
          )}
        </KpiGrid>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <Bloco titulo="O que fazer primeiro">
          <Linha t="Marcações e jornada" s="a jornada é do Ponto — feche lá as marcações em aberto"
            v={<Button variant="ghost" size="sm" onClick={() => ir('/ponto')}>Abrir no Ponto</Button>} />
          <Linha t="Licenças esperando resposta" s="a lista completa fica em Licenças"
            v={<Button variant="ghost" size="sm" onClick={() => ir('/hrm/leave')}>Analisar</Button>} />
        </Bloco>

        <Bloco titulo="Minhas licenças">
          {p.minhas_licencas.map((l) => <Linha key={l.id} t={periodo(l)} s={l.tipo} v="Aprovada" />)}
          {!p.minhas_licencas.length && <Vazio texto="Nenhuma licença aprovada no próximo mês." />}
        </Bloco>

        <Bloco titulo="Minhas metas de venda">
          {p.faixas_meta.map((f, i) => <Linha key={i} t={`${brl(f.inicio)} – ${brl(f.fim)}`} v={`${Number(f.pct)}%`} />)}
          {!p.faixas_meta.length && <Vazio texto="Nenhuma faixa de meta cadastrada." />}
        </Bloco>

        <Bloco titulo="Próximos feriados">
          {p.feriados.map((f) => <Linha key={f.id} t={f.nome} s={f.local ? `só ${f.local}` : 'todas as localidades'} v={periodo(f)} />)}
          {!p.feriados.length && <Vazio texto="Nenhum feriado no próximo mês." />}
        </Bloco>

        {is_admin && (
          <Bloco titulo="Colaboradores por setor">
            {p.setores.map((s) => <Linha key={s.nome} t={s.nome} v={s.total} />)}
            {!p.setores.length && <Vazio texto="Nenhum colaborador cadastrado." />}
          </Bloco>
        )}
      </div>
    </>
  );
}
