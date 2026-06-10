// @memcofre
//   tela: /financeiro/impostos
//   module: Financeiro
//   status: live (F1 — estimativa visual)
//   stories: PACOTE-FINANCEIRO-F2 PR-2
//   rules: R-FIN-001 (multi-tenant), R-FIN-002 (audit via TituloCriado)
//   adrs: 0093 (Tier 0), arq/0005 (modulo-financeiro)
//   tests: Modules/Financeiro/Tests/Feature/ImpostosGuardTest
//
// Origem: protótipo Cowork TelaImpostos (financeiro-telas-extras.jsx), pacote
// F2 aprovado [W] 2026-06-10. ESTIMATIVA VISUAL Simples Nacional regime caixa —
// apuração oficial, cálculo por anexo e emissão de guia moram no módulo Fiscal.
// Persona: Eliana [E] — financeiro escritório, densidade alta.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { Calendar, Check, FileText, Receipt } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Grid, Inline, Stack } from '@/Components/layout';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';

type GuiaStatus = 'a_vencer' | 'paga' | 'atrasada';

interface Guia {
  id: string;
  nome: string;
  det: string;
  competencia: string;
  competencia_label: string;
  vencimento: string; // YYYY-MM-DD
  valor: number;
  status: GuiaStatus;
  estimado: boolean;
  lancavel: boolean;
  lanc: string | null; // numero do título no caixa unificado
}

interface SemNf {
  id: number;
  numero: string;
  contraparte: string;
  valor: number;
}

interface Props {
  kpis: {
    a_recolher: { valor: number; qtd: number };
    proxima: Guia | null;
    pct_com_nf: number;
    sem_nf_qtd: number;
  };
  guias: Guia[];
  calendario: Guia[];
  sem_nf: SemNf[];
  receita_recebida: number;
  das_rate: number;
  periodLabel: string;
  businessName: string;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 }).format(v ?? 0);

const brlK = (v: number) => {
  const abs = Math.abs(v);
  if (abs >= 1000) return 'R$ ' + (v / 1000).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + 'k';
  return brl(v);
};

// Defensivo: aceita date-only E datetime legacy (corta o timestamp antes de formatar).
const dataBr = (d: string) => (d ? d.slice(0, 10).split('-').reverse().slice(0, 2).join('/') : '—');

// Status pill — tons semânticos calmos via tokens do @theme (zero cor crua).
const GUIA_STATUS: Record<GuiaStatus, { label: string; cls: string }> = {
  a_vencer: { label: 'a vencer', cls: 'bg-warning/10 text-warning-foreground' },
  paga: { label: 'paga', cls: 'bg-success/10 text-success-foreground' },
  atrasada: { label: 'atrasada', cls: 'bg-destructive/10 text-destructive' },
};

function CardSection({ icon: Icon, title, extra, children }: {
  icon: typeof Receipt;
  title: string;
  extra?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="border border-border rounded-lg bg-card overflow-hidden">
      <Inline asChild gap={2} className="px-4 h-10 border-b border-border">
        <header>
        <Icon size={13} className="text-muted-foreground" aria-hidden />
        <b className="text-[12.5px] font-semibold">{title}</b>
          {extra && <span className="ml-auto text-[11.5px] text-muted-foreground">{extra}</span>}
        </header>
      </Inline>
      {children}
    </section>
  );
}

function FinanceiroImpostos({ kpis, guias, calendario, sem_nf, receita_recebida, das_rate, periodLabel, businessName }: Props) {
  const lancar = (g: Guia) => {
    router.post('/financeiro/impostos/lancar', { competencia: g.competencia }, { preserveScroll: true });
  };

  return (
    <div className="fin-curadoria">
      <PageHeader
        title="Financeiro"
        suffix=" · Impostos & obrigações"
        subtitle={<>{periodLabel}{businessName ? ` · ${businessName}` : ''} · estimativa Simples Nacional</>}
      >
        <Inline gap={1} className="flex-shrink-0 gap-1.5 ml-auto">
          <FinanceiroSubNav active="impostos" hidePrimary />
        </Inline>
      </PageHeader>

      {/* 3 KPIs — a recolher no mês · próxima obrigação · % receita com NF */}
      <Grid cols={3} gap={3} className="px-6 pt-4 max-[1100px]:grid-cols-1">
        <div className="border border-border rounded-lg bg-card px-5 py-4">
          <div className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">A recolher</div>
          <div className="mt-1 text-[length:var(--fs-8,28px)] leading-none font-semibold tracking-tight font-mono tabular-nums">{brl(kpis.a_recolher.valor)}</div>
          <div className="mt-2 text-[11.5px] text-muted-foreground">{kpis.a_recolher.qtd} guia(s) em aberto</div>
        </div>
        <div className="border border-border rounded-lg bg-card px-5 py-4">
          <div className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">Próxima obrigação</div>
          <div className="mt-1 text-[length:var(--fs-8,28px)] leading-none font-semibold tracking-tight tabular-nums">{kpis.proxima ? dataBr(kpis.proxima.vencimento) : '—'}</div>
          <div className="mt-2 text-[11.5px] text-muted-foreground truncate">{kpis.proxima ? kpis.proxima.nome : 'nada em aberto'}</div>
        </div>
        <div className="border border-border rounded-lg bg-card px-5 py-4">
          <div className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">Receita com NF</div>
          <div className="mt-1 text-[length:var(--fs-8,28px)] leading-none font-semibold tracking-tight font-mono tabular-nums">{kpis.pct_com_nf}%</div>
          <div className="mt-2 text-[11.5px] text-muted-foreground">
            {kpis.sem_nf_qtd === 0 ? 'todos os títulos com NF ✓' : `${kpis.sem_nf_qtd} título(s) sem NF vinculada`}
          </div>
        </div>
      </Grid>

      <Grid gap={4} className="px-6 mt-4 grid-cols-[1fr_300px] max-[1100px]:grid-cols-1 items-start">
        {/* Guias do período */}
        <CardSection icon={Receipt} title="Guias do período" extra="estimado + lançadas no caixa (6 meses)">
          <table className="w-full text-[12.5px]">
            <thead>
              <tr className="text-left text-[10.5px] uppercase tracking-wider text-muted-foreground">
                <th className="px-4 py-2 font-medium">Guia</th>
                <th className="px-2 py-2 font-medium">Competência</th>
                <th className="px-2 py-2 font-medium">Venc.</th>
                <th className="px-2 py-2 font-medium text-right">Valor</th>
                <th className="px-2 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium text-right">No caixa</th>
              </tr>
            </thead>
            <tbody>
              {guias.map((g) => {
                const st = GUIA_STATUS[g.status];
                return (
                  <tr key={g.id} className="border-t border-border">
                    <td className="px-4 py-2.5">
                      <div className="font-medium">{g.nome}</div>
                      <div className="text-[11.5px] text-muted-foreground">{g.det}{g.estimado && ' · estimado'}</div>
                    </td>
                    <td className="px-2 py-2.5 text-muted-foreground">{g.competencia_label}</td>
                    <td className="px-2 py-2.5 font-mono tabular-nums">{dataBr(g.vencimento)}</td>
                    <td className="px-2 py-2.5 font-mono tabular-nums text-right">{brl(g.valor)}</td>
                    <td className="px-2 py-2.5">
                      <span className={'inline-flex items-center px-1.5 py-0.5 rounded text-[10.5px] font-medium ' + st.cls}>{st.label}</span>
                    </td>
                    <td className="px-4 py-2.5 text-right">
                      {g.lanc ? (
                        <span className="text-[11.5px] text-muted-foreground inline-flex items-center gap-1">
                          <Check size={11} className="text-success-foreground" aria-hidden /> {g.status === 'paga' ? 'paga · ' : 'a pagar · '}
                          <span className="font-mono">{g.lanc}</span>
                        </span>
                      ) : g.lancavel ? (
                        <button type="button" className="os-btn ghost" onClick={() => lancar(g)} title="Cria o título a pagar no caixa unificado">
                          Lançar a pagar
                        </button>
                      ) : (
                        <span className="text-[11.5px] text-muted-foreground">—</span>
                      )}
                    </td>
                  </tr>
                );
              })}
              {guias.length === 0 && (
                <tr className="border-t border-border">
                  <td colSpan={6} className="px-4 py-6 text-center text-[12.5px] text-muted-foreground">
                    Sem guias no período — a guia DAS estimada aparece quando houver receita recebida no mês.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </CardSection>

        {/* Coluna lateral: calendário + costura NF↔título */}
        <Stack gap={4}>
          <CardSection icon={Calendar} title="Calendário de obrigações">
            <ul className="py-1">
              {calendario.map((g) => (
                <Inline asChild align="baseline" gap={2} key={g.id} className="px-4 py-2 gap-2.5 text-[12.5px]">
                  <li>
                  <span className="font-mono text-[11.5px] text-muted-foreground shrink-0 w-11 tabular-nums">{dataBr(g.vencimento)}</span>
                  <span className="flex-1 truncate">{g.nome}</span>
                    <span className="font-mono text-[11.5px] text-muted-foreground tabular-nums">{brlK(g.valor)}</span>
                  </li>
                </Inline>
              ))}
              {calendario.length === 0 && (
                <li className="px-4 py-3 text-[12.5px] text-muted-foreground">Nada em aberto — obrigações pagas em dia ✓</li>
              )}
              <Inline asChild align="baseline" gap={2} className="px-4 py-2 gap-2.5 text-[12.5px] border-t border-border">
                <li>
                  <span className="font-mono text-[11.5px] text-muted-foreground shrink-0 w-11">fim/mês</span>
                  <span className="flex-1 text-muted-foreground">Fechamento mensal (trilha no Unificado)</span>
                </li>
              </Inline>
            </ul>
          </CardSection>

          <CardSection icon={FileText} title="NF ↔ título">
            {sem_nf.length === 0 ? (
              <p className="px-4 py-3 text-[12.5px] text-muted-foreground leading-relaxed">
                <Check size={12} className="inline text-success-foreground mr-1" aria-hidden />
                Todos os recebíveis do período têm NF vinculada — base do DAS consistente.
              </p>
            ) : (
              <ul className="py-1">
                {sem_nf.map((r) => (
                  <Inline asChild align="baseline" gap={2} key={r.id} className="px-4 py-2 text-[12.5px]">
                    <li>
                      <span className="font-mono text-muted-foreground">{r.numero}</span>
                      <span className="flex-1 truncate">{r.contraparte}</span>
                      <span className="font-mono tabular-nums">{brlK(r.valor)}</span>
                    </li>
                  </Inline>
                ))}
                <li className="px-4 py-2 text-[11.5px] text-muted-foreground border-t border-border">
                  Sem NF a base do DAS sai distorcida — vincule antes do fechamento.
                </li>
              </ul>
            )}
          </CardSection>
        </Stack>
      </Grid>

      {/* Disclaimer fixo — exigência do pacote F2 (anti-pattern: apresentar estimativa como apuração) */}
      <p className="px-6 mt-4 pb-6 text-[11.5px] text-muted-foreground leading-relaxed">
        Estimativa visual (Simples Nacional · alíquota efetiva ≈ {Math.round(das_rate * 100)}% sobre {brl(receita_recebida)} recebidos no mês, regime caixa) —
        a apuração oficial, o cálculo por anexo e a emissão de guia moram no módulo <b className="font-medium">Fiscal</b>.
      </p>
    </div>
  );
}

// Mesmo wrapper do DRE: .fin-cowork ativa o vocabulário visual do módulo
// (os-btn ghost, fin-page-h) dentro do AppShellV2.
FinanceiroImpostos.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro · Impostos & obrigações"
    breadcrumbItems={[
      { label: 'Financeiro', href: '/financeiro' },
      { label: 'Impostos & obrigações' },
    ]}
  >
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default FinanceiroImpostos;
