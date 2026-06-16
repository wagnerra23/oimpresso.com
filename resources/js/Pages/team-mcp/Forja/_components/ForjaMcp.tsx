// Forja — aba MCP do cockpit. Tela fiel ao protótipo aprovado.
//
// ESTÁTICA / MOCKADA POR DESIGN: o próprio protótipo rotula assim. O enforce
// real (contrato de ferramentas + auditoria de toda ação de agente) é do
// servidor TeamMcp ([CL]) — aqui é só a vitrine do contrato. Default = read +
// propose; merge e constituicao.edit são NEGADOS no contrato, não por convenção.
//
// Sem props: dados estáticos inline (vitrine do contrato aprovado). DS v6:
// só tokens semânticos (primary/success/warning/info/destructive/muted/
// foreground/border), tabular-nums em horários, layout via inline-flex/
// inline-grid (nunca flex/grid solto), máx rounded-lg, data-testid locators.
//
// Tier 0 (ADR 0081): NUNCA exibir/logar token raw — só o nome lógico do token.

import { AlertTriangle, KeyRound, ScrollText, ShieldCheck } from 'lucide-react';
import { cn } from '@/Lib/utils';

// --- Contrato de ferramentas (estático, do protótipo aprovado) ----------------

type Perm = 'PERMITIDO' | 'PROPÕE' | 'NEGADO';

interface Tool {
  ferramenta: string;
  acao: string;
  permissao: Perm;
  // Texto-extra do contrato (ex.: "→[W] aprova", "→transporte", "(só [W2])").
  detalhe?: string;
}

const TOOLS: Tool[] = [
  { ferramenta: 'backlog.read', acao: 'ler issues/filtros', permissao: 'PERMITIDO' },
  { ferramenta: 'changelog.read', acao: 'o que shippou', permissao: 'PERMITIDO' },
  { ferramenta: 'issue.transition', acao: 'mover fase', permissao: 'PROPÕE', detalhe: '→ [W] aprova' },
  { ferramenta: 'changelog.append', acao: 'registrar entrega', permissao: 'PROPÕE', detalhe: '→ transporte' },
  { ferramenta: 'adr.propose', acao: 'cria _PROPOSTA', permissao: 'PROPÕE', detalhe: 'nunca decisions/NNNN' },
  { ferramenta: 'git.merge', acao: 'fechar PR', permissao: 'NEGADO', detalhe: 'só [W2]' },
  { ferramenta: 'constituicao.edit', acao: 'ADR/PROTOCOL/BRIEFING', permissao: 'NEGADO', detalhe: 'só [W]' },
];

// Pílula de permissão por token semântico DS v6:
//   PERMITIDO = success · PROPÕE = warning · NEGADO = destructive.
const PERM_PILL: Record<Perm, string> = {
  PERMITIDO: 'bg-success/15 text-success-fg',
  PROPÕE: 'bg-warning-soft text-warning-fg',
  NEGADO: 'bg-destructive-soft text-destructive-fg',
};

// --- Tokens ativos (estático) -------------------------------------------------
// Tier 0 (ADR 0081): só o nome LÓGICO do token — nunca o valor raw.

interface Token {
  nome: string;
  ator: string; // selo [CC]/[CL]/[CD]
  escopo: string;
  exp: string;
  uso: string;
}

const TOKENS: Token[] = [
  { nome: 'frj_cc_live', ator: 'CC', escopo: 'read + propose', exp: 'exp 30d', uso: 'uso há 2 min' },
  { nome: 'frj_cl_ci', ator: 'CL', escopo: 'read + propose', exp: 'exp 90d', uso: 'uso há 1 h' },
  { nome: 'frj_cd_rev', ator: 'CD', escopo: 'read', exp: 'exp 30d', uso: 'uso há 3 h' },
];

// --- Auditoria (estático) — toda ação de agente, regra 6 mecanizada -----------

type Resultado = 'ok' | 'pendente' | 'negado';

interface AuditRow {
  ts: string;
  ator: string;
  acao: string;
  detalhe: string;
  resultado: Resultado;
  resultadoLabel: string;
}

const AUDIT: AuditRow[] = [
  { ts: '14:21', ator: 'CC', acao: 'backlog.read', detalhe: 'onda=FA-1', resultado: 'ok', resultadoLabel: 'ok' },
  { ts: '14:19', ator: 'CC', acao: 'adr.propose', detalhe: '--origin-DEV', resultado: 'ok', resultadoLabel: 'proposta criada' },
  { ts: '13:50', ator: 'CL', acao: 'issue.transition', detalhe: 'FORJA-141 → F3', resultado: 'pendente', resultadoLabel: 'aguarda [W]' },
  { ts: '12:30', ator: 'CC', acao: 'git.merge', detalhe: 'PR 2417', resultado: 'negado', resultadoLabel: 'NEGADO — só [W2]' },
  { ts: '11:05', ator: 'CD', acao: 'changelog.read', detalhe: 'desde 09/06', resultado: 'ok', resultadoLabel: 'ok' },
  { ts: '10:02', ator: 'CC', acao: 'constituicao.edit', detalhe: 'ADR 0235', resultado: 'negado', resultadoLabel: 'NEGADO — só [W]' },
];

const RESULTADO_TONE: Record<Resultado, string> = {
  ok: 'text-success-fg',
  pendente: 'text-warning-fg',
  negado: 'text-destructive-fg',
};

export default function ForjaMcp() {
  return (
    <div data-testid="forja-mcp" className="inline-flex w-full flex-col gap-6">
      {/* Banner topo — tom muted/aviso. Estabelece o contrato mental: MOCKADO. */}
      <div className="inline-flex w-full items-start gap-2 rounded-lg border border-warning-soft bg-warning-soft px-4 py-3 text-warning-fg">
        <AlertTriangle size={16} className="mt-0.5 shrink-0" />
        <p className="text-xs leading-relaxed">
          <strong>MOCKADO</strong> — Contrato e auditoria como design — o enforce real é do servidor{' '}
          <span className="font-medium">TeamMcp</span> ([CL]). Default ={' '}
          <span className="font-mono">read + propose</span>; <span className="font-mono">merge</span> e{' '}
          <span className="font-mono">constituicao.edit</span> negados no contrato, não por convenção.
        </p>
      </div>

      {/* CONTRATO DE FERRAMENTAS */}
      <section className="inline-flex w-full flex-col gap-2">
        <div className="inline-flex items-center gap-2">
          <ShieldCheck size={14} className="text-muted-foreground" />
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground">
            CONTRATO DE FERRAMENTAS
          </h2>
        </div>

        <div className="overflow-hidden rounded-lg border">
          {/* Cabeçalho (grid de 3 zonas) */}
          <div className="inline-grid w-full grid-cols-[minmax(9rem,1.2fr)_minmax(0,2fr)_minmax(8rem,auto)] gap-3 border-b bg-muted/40 px-4 py-2 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
            <span>Ferramenta</span>
            <span>Ação</span>
            <span className="text-right">Permissão</span>
          </div>

          <div className="divide-y">
            {TOOLS.map((t) => (
              <div
                key={t.ferramenta}
                className="inline-grid w-full grid-cols-[minmax(9rem,1.2fr)_minmax(0,2fr)_minmax(8rem,auto)] items-center gap-3 px-4 py-2.5"
              >
                <span className="font-mono text-xs text-foreground">{t.ferramenta}</span>
                <span className="min-w-0 truncate text-xs text-muted-foreground">{t.acao}</span>
                <span className="inline-flex items-center justify-end gap-1.5">
                  <span
                    className={cn(
                      'shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold',
                      PERM_PILL[t.permissao],
                    )}
                    data-testid="forja-mcp-perm"
                  >
                    {t.permissao}
                  </span>
                  {t.detalhe && (
                    <span className="hidden text-[10px] text-muted-foreground sm:inline">
                      {t.detalhe}
                    </span>
                  )}
                </span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* TOKENS ATIVOS */}
      <section className="inline-flex w-full flex-col gap-2">
        <div className="inline-flex items-center gap-2">
          <KeyRound size={14} className="text-muted-foreground" />
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground">
            TOKENS ATIVOS
          </h2>
        </div>

        <div className="inline-grid w-full grid-cols-1 gap-3 sm:grid-cols-3">
          {TOKENS.map((tk) => (
            <div
              key={tk.nome}
              className="inline-flex flex-col gap-2 rounded-lg border p-3"
              data-testid="forja-mcp-token"
            >
              <div className="inline-flex items-center justify-between gap-2">
                {/* Nome LÓGICO do token — NUNCA o valor raw (Tier 0 ADR 0081). */}
                <span className="font-mono text-xs font-medium text-foreground">{tk.nome}</span>
                <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                  [{tk.ator}]
                </span>
              </div>
              <div className="inline-flex flex-wrap items-center gap-1.5 text-[10px] text-muted-foreground">
                <span className="rounded bg-muted px-1.5 py-0.5">{tk.escopo}</span>
                <span className="tabular-nums">· {tk.exp}</span>
                <span className="tabular-nums">· {tk.uso}</span>
              </div>
              <button
                type="button"
                className="mt-1 inline-flex w-fit items-center rounded-md border border-destructive/30 px-2 py-1 text-[11px] font-medium text-destructive-fg transition-colors hover:bg-destructive-soft"
                data-testid="forja-mcp-revogar"
              >
                revogar
              </button>
            </div>
          ))}
        </div>
      </section>

      {/* AUDITORIA — toda ação de agente (regra 6 mecanizada) */}
      <section className="inline-flex w-full flex-col gap-2">
        <div className="inline-flex items-center gap-2">
          <ScrollText size={14} className="text-muted-foreground" />
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground">
            AUDITORIA · TODA AÇÃO DE AGENTE (regra 6 mecanizada)
          </h2>
        </div>

        <div className="overflow-hidden rounded-lg border">
          {/* Cabeçalho */}
          <div className="inline-grid w-full grid-cols-[3rem_3rem_minmax(8rem,1.2fr)_minmax(0,1.5fr)_minmax(7rem,auto)] gap-3 border-b bg-muted/40 px-4 py-2 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
            <span>ts</span>
            <span>ator</span>
            <span>ação</span>
            <span>detalhe</span>
            <span className="text-right">resultado</span>
          </div>

          <div className="divide-y">
            {AUDIT.map((row, i) => (
              <div
                key={`${row.ts}-${row.acao}-${i}`}
                className={cn(
                  'inline-grid w-full grid-cols-[3rem_3rem_minmax(8rem,1.2fr)_minmax(0,1.5fr)_minmax(7rem,auto)] items-center gap-3 px-4 py-2 text-xs',
                  // Linhas NEGADO com tom destructive sutil.
                  row.resultado === 'negado' && 'bg-destructive-soft/40',
                )}
                data-testid="forja-mcp-audit-row"
              >
                <span className="font-mono tabular-nums text-muted-foreground">{row.ts}</span>
                <span className="font-mono text-[11px] text-muted-foreground">[{row.ator}]</span>
                <span className="font-mono text-foreground">{row.acao}</span>
                <span className="min-w-0 truncate text-muted-foreground">{row.detalhe}</span>
                <span className={cn('text-right font-medium', RESULTADO_TONE[row.resultado])}>
                  {row.resultadoLabel}
                </span>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
