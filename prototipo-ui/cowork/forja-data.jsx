// forja-data.jsx — Forja: módulo de desenvolvimento de software (Etapa 0+1)
// Mock ancorado em artefatos REAIS do repo (ADRs, ondas, sessões, review queue).
// Fonte da verdade segue o git; aqui é projeção (ver spec: read/sync, nunca banco paralelo).

// ─── Atores (6 papéis · humano vs agente) ───
const FORJA_ACTORS = {
  W:  { role: "W",  name: "Wagner",            kind: "human", color: "oklch(0.57 0.16 25)",  desc: "Decide · aprova screenshot e merge" },
  CC: { role: "CC", name: "Claude Cowork",     kind: "agent", color: "oklch(0.55 0.15 295)", model: "claude-opus-4", desc: "F1 — protótipo visual" },
  CD: { role: "CD", name: "Claude Design",     kind: "agent", color: "oklch(0.60 0.13 60)",  model: "claude-sonnet-4", desc: "F1.5 — critique" },
  CL: { role: "CL", name: "Claude Code",       kind: "agent", color: "oklch(0.52 0.10 195)", model: "claude-opus-4", desc: "F3 — Inertia/React real" },
  CA: { role: "CA", name: "Claude A11y",       kind: "agent", color: "oklch(0.55 0.13 150)", model: "claude-sonnet-4", desc: "F3.5 — WCAG 2.1 AA" },
  AN: { role: "AN", name: "Claude Analista",    kind: "agent", color: "oklch(0.50 0.10 195)", model: "claude-sonnet-4", desc: "F0 — triagem & enriquecimento de ticket" },
  W2: { role: "W2", name: "Wagner aprovador",  kind: "human", color: "oklch(0.52 0.08 250)", desc: "F2 + F4 síncronos" },
};

// ─── 7 fases (PROTOCOL.md §2) ───
const FORJA_PHASES = [
  { id: "F0",   label: "Brief",      hue: 250, owner: "W"  },
  { id: "F1",   label: "Design",     hue: 295, owner: "CC" },
  { id: "F1.5", label: "Critique",   hue: 270, owner: "CD" },
  { id: "F2",   label: "Screenshot", hue: 60,  owner: "W2" },
  { id: "F3",   label: "Code",       hue: 195, owner: "CL" },
  { id: "F3.5", label: "A11y",       hue: 150, owner: "CA" },
  { id: "F4",   label: "Merge",      hue: 145, owner: "W2" },
];

// ─── Tipos de issue ───
const FORJA_TYPES = {
  tela:  { label: "Tela",  hue: 295 },
  gate:  { label: "Gate",  hue: 195 },
  adr:   { label: "ADR",   hue: 270 },
  bug:   { label: "Bug",   hue: 25  },
  refino:{ label: "Refino",hue: 60  },
  infra: { label: "Infra", hue: 230 },
  doc:   { label: "Doc",   hue: 150 },
  epico: { label: "Épico", hue: 320 },
};

// ─── Ondas / ciclos (vocabulário nativo: W·FA·Q) ───
const FORJA_ONDAS = [
  { id: "FA-1", nome: "Completar §TEMPERO na fundação", estado: "ativa",      janela: "jun 11–16", milestone: "Financeiro 9.5" },
  { id: "FA-2", nome: "Snap 314 font-size ao ramp",     estado: "planejada",  janela: "jun 16–20", milestone: "Financeiro 9.5", depende: ["FA-1"] },
  { id: "Q1",   nome: "G-3 E2E required",               estado: "ativa",      janela: "jun 09–18", milestone: "Governança v1" },
  { id: "Q2",   nome: "G-7 honesto + ratchet cobertura",estado: "planejada",  janela: "jun 18–25", milestone: "Governança v1", depende: ["Q1"] },
  { id: "v1.1", nome: "Protocolo v1.1",                 estado: "planejada",  janela: "jul",       milestone: "Protocolo" },
];

// ─── Backlog (issues) — ancorados em STATUS / COWORK_NOTES / review queue ───
const FORJA_ISSUES = [
  // ── ÉPICO (agrupa issues por programa, cross-onda) ──
  { id:"FORJA-160", frescor:"sync", frescorDias:5, titulo:"Épico — Identidade única (roxo canon)", tipo:"epico", prio:"P1", fase:"F1",
    assignee:"CC", onda:null, modulo:"Sistema", origem:"cowork_notes", vinculos:[{k:"adr",v:"0235"}], bloqueado_por:[], children:["FORJA-137","FORJA-138"],
    desc:"Programa de identidade: chrome roxo único + tokens semânticos governados. Mata accent-por-módulo.", subtarefas:[], criados:"CC · 08/06", atualizado:"CC · 08/06", atividade:[] },
  // ── em TRIAGEM (propostos · aguardam analista + aprovação [W]) ──
  { id:"FORJA-152", estado:"triagem", frescor:"inferido", titulo:"Busca semântica no KB (Meilisearch)", tipo:"tela", prio:"P2", fase:"F0",
    assignee:"CC", onda:null, modulo:"KB", origem:"agente_mcp", vinculos:[{k:"adr",v:"0033"}], bloqueado_por:[],
    desc:"Proposto: busca semântica no KB via Meilisearch, além do substring atual.", subtarefas:[], criados:"agente · agora", atualizado:"agente · agora", atividade:[] },
  { id:"FORJA-151", estado:"triagem", frescor:"inferido", titulo:"Bug: drawer financeiro corta o valor no dark", tipo:"bug", prio:"P1", fase:"F0",
    assignee:"CC", onda:null, modulo:"Financeiro", origem:"cowork_notes", vinculos:[], bloqueado_por:[],
    desc:"Relato [W]: o valor do hero do drawer some no tema escuro.", subtarefas:[], criados:"W · agora", atualizado:"W · agora", atividade:[] },
  { id:"FORJA-150", estado:"triagem", frescor:"inferido", titulo:"Rename inbox → Atendimento na topnav", tipo:"refino", prio:"P3", fase:"F0",
    assignee:"CC", onda:null, modulo:"Atendimento", origem:"cowork_notes", vinculos:[], bloqueado_por:[],
    desc:"Cosmético: ajustar label da topnav.", subtarefas:[], criados:"W · agora", atualizado:"W · agora", atividade:[] },
  { id:"FORJA-142", frescor:"lido", titulo:"Sells/Create — P0 do piloto ROTA LIVRE", tipo:"tela", prio:"P0", fase:"F1",
    assignee:"CC", onda:"v1.1", modulo:"Vendas", origem:"review_queue",
    vinculos:[{k:"adr",v:"0114"},{k:"sessao",v:"2026-06-03-vendas"}], bloqueado_por:[],
    desc:"Núcleo do POS de balcão. localStorage per-business `b<bizId>.` (multi-tenant Tier 0). Densidade alta, atalhos de teclado, monitor 1280×1024.",
    subtarefas:[{t:"Layout 3-col",done:true},{t:"localStorage per-business",done:false},{t:"Atalhos teclado",done:false}],
    criados:"CC · 13/06", atualizado:"CC · 14/06 09:21",
    atividade:[
      {ator:"W", t:"deu foco — aguarda 'vai' + ordem", quando:"03/06"},
      {ator:"CC", t:"reconciliou charter v2 ao git v6", quando:"03/06"},
    ]},
  { id:"FORJA-141", frescor:"sync", frescorDias:2, titulo:"Completar §TEMPERO (--sh/--ease/--t/--atmo) na fundação", tipo:"infra", prio:"P0", fase:"F3",
    assignee:"CL", onda:"FA-1", modulo:"Financeiro", origem:"cowork_notes",
    vinculos:[{k:"pr",v:"#?"},{k:"sessao",v:"2026-06-11-financeiro-diff"}], bloqueado_por:[],
    desc:"PR-4 aplicou só ½ da autorização [W] 06-10: tempero não landou em foundations/cockpit/inertia. Tier 0 (fundação) já autorizado.",
    subtarefas:[{t:"--sh-1/2 par dark",done:false},{t:"--ease curva única",done:false},{t:"--atmo no body",done:false}],
    criados:"CC · 11/06", atualizado:"CL · 12/06",
    atividade:[{ator:"CC", t:"diff ×main: §TEMPERO = 0 em foundations", quando:"11/06"}]},
  { id:"FORJA-140", frescor:"inferido", titulo:"Snap 314 font-size hardcoded ao ramp --fs-1..9", tipo:"refino", prio:"P1", fase:"F3",
    assignee:"CL", onda:"FA-2", modulo:"Financeiro", origem:"cowork_notes",
    vinculos:[{k:"adr",v:"0253"}], bloqueado_por:["FORJA-141"],
    desc:"Ramp existe em foundations.css + G8 ratchet, mas adoção no fin live = 0 (var(--fs-)). 314 font-size fora (bundle 208 · output 57 · curadoria 18).",
    subtarefas:[{t:"bundle 208",done:false},{t:"output 57",done:false}],
    criados:"CC · 11/06", atualizado:"CC · 11/06", atividade:[]},
  { id:"FORJA-139", frescor:"lido", titulo:"G-3 E2E Playwright → pull_request + required", tipo:"gate", prio:"P1", fase:"F3",
    assignee:"CL", onda:"Q1", modulo:"Sistema", origem:"cowork_notes",
    vinculos:[{k:"adr",v:"0264"},{k:"pr",v:"#run 27233429304"}], bloqueado_por:[],
    desc:"e2e-gate.yml ainda workflow_dispatch manual não-required. Harness reconstruído (MySQL service + visreg-login bypass). Faltam 2 verdes pré-flip.",
    subtarefas:[{t:"harness estável",done:true},{t:"2 verdes em PR",done:false},{t:"flip required",done:false}],
    criados:"CL · 09/06", atualizado:"CL · 09/06",
    atividade:[{ator:"CL", t:"run verde em branch (não conta p/ os 2)", quando:"09/06"}]},
    { id:"FORJA-138", frescor:"sync", frescorDias:8, parent:"FORJA-160", titulo:"Compras: ilha #1f3a5f → roxo canon", tipo:"refino", prio:"P2", fase:"F3",
    assignee:"CL", onda:null, modulo:"Compras", origem:"cowork_notes",
    vinculos:[{k:"adr",v:"0235"}], bloqueado_por:[],
    desc:"Única ilha git confirmada (cowork-compras-bundle.css@main = hex #1f3a5f). Censo provou que Sells/Financeiro já eram roxo — era espelho local stale.",
    subtarefas:[{t:"swap --cmp-* → --accent",done:false}],
    criados:"CC · 08/06", atualizado:"CC · 08/06", atividade:[]},
    { id:"FORJA-137", frescor:"inferido", parent:"FORJA-160", titulo:"ADR — token --origin-DEV (selo da Forja)", tipo:"adr", prio:"P2", fase:"F0",
    assignee:"W", onda:null, modulo:"Sistema", origem:"agente_mcp",
    vinculos:[{k:"adr",v:"0235"}], bloqueado_por:[],
    desc:"Teal oklch(0.52 0.09 195) só pros SELOS de proveniência/changelog — não accent de chrome (chrome = roxo canon). Token novo no @theme = Tier 0, decisão [W].",
    subtarefas:[{t:"proposta _PROPOSTA-*",done:true},{t:"[W] decide",done:false},{t:"[CL] numera",done:false}],
    criados:"CC · 16/06", atualizado:"CC · 16/06",
    atividade:[{ator:"CC", t:"proposto via refutação (chrome teal violaria identidade única)", quando:"16/06"}]},
  { id:"FORJA-136", frescor:"sync", frescorDias:7, titulo:"Financeiro — pilar Fiscal (parado em 5,5)", tipo:"tela", prio:"P1", fase:"F1",
    assignee:"CC", onda:null, modulo:"Financeiro", origem:"review_queue",
    vinculos:[{k:"sessao",v:"2026-06-09-reavaliacao"}], bloqueado_por:[],
    desc:"Único pilar parado da reavaliação: Caixa 8,5 · Concil 7,5 · Cobrança 8,0 · IA/DRE 8,0 · Fiscal 5,5. Censo do módulo Fiscal ANTES (Regra 7).",
    subtarefas:[{t:"censo módulo Fiscal",done:false}], criados:"CC · 09/06", atualizado:"CC · 09/06", atividade:[]},
  { id:"FORJA-135", frescor:"inferido", titulo:"Rename ds-v5 → ds-v6 no git (ADR 0244)", tipo:"infra", prio:"P2", fase:"F3",
    assignee:"CL", onda:null, modulo:"Sistema", origem:"cowork_notes",
    vinculos:[{k:"adr",v:"0244"}], bloqueado_por:[],
    desc:"Cowork já renomeou local (host repontado, testado). Git ainda diz ds-v5 — rename enfileirado pro [CL].",
    subtarefas:[], criados:"CC · 04/06", atualizado:"CC · 04/06", atividade:[]},
  { id:"FORJA-134", frescor:"inferido", titulo:"Painel de saúde — frescor do censo de gates", tipo:"infra", prio:"P3", fase:"F1",
    assignee:"CC", onda:null, modulo:"Sistema", origem:"agente_mcp",
    vinculos:[], bloqueado_por:[],
    desc:"memory-health.js ganha check de frescor (carimbo >14d = 🔴) → obriga re-derivar a tabela de gates por idade. Cada métrica linka a ação.",
    subtarefas:[], criados:"CC · 04/06", atualizado:"CC · 04/06", atividade:[]},
  { id:"FORJA-133", frescor:"sync", frescorDias:4, titulo:"Sync now: endereço cliente ↔ venda", tipo:"bug", prio:"P2", fase:"F1",
    assignee:"CC", onda:null, modulo:"Vendas", origem:"cowork_notes",
    vinculos:[{k:"sessao",v:"2026-06-12-vendas"}], bloqueado_por:[],
    desc:"Comentário [W] aberto na lista de Vendas. Plano de contas + sync de endereço.",
    subtarefas:[], criados:"W · 12/06", atualizado:"W · 12/06", atividade:[]},
];

// ─── Changelog (3 fontes: PR · ADR · sessão · onda) ───
const FORJA_CHANGELOG = [
  { tipo:"pr",     ref:"#2417", resumo:"Convergência caçamba→reparo (Oficina): UI vira reparo, pills m³ removidas", autor:"CL", data:"09/06", modulos:["Oficina"], flags:[] },
  { tipo:"adr",    ref:"ADR 0264", resumo:"Governança executável G-1..G-7 (trio-de-tela, caso↔teste, domínio, e2e)", autor:"CL", data:"09/06", modulos:["Sistema"], flags:["tier-0"] },
  { tipo:"adr",    ref:"ADR 0261", resumo:"Enforcement faseado: baseline → bloqueante → ratchet=0", autor:"CL", data:"09/06", modulos:["Sistema"], flags:["tier-0"] },
  { tipo:"sessao", ref:"2026-06-12-produtos", resumo:"Produtos: P0 tokenização (79 cruas) + 2 fixes dark + P1 acabamento 9.75", autor:"CC", data:"12/06", modulos:["Produtos"], flags:[] },
  { tipo:"pr",     ref:"#2212", resumo:"RecurringBilling re-skin DS v6 (Tailwind-cru → warm), UI-Judge 90", autor:"CL", data:"04/06", modulos:["Cobrança"], flags:[] },
  { tipo:"pr",     ref:"#2216", resumo:"Gates DS: cor crua TRAVA de verdade (foundation-guard + conformance-gate)", autor:"CL", data:"04/06", modulos:["Sistema"], flags:["breaking"] },
  { tipo:"pr",     ref:"#2054", resumo:"Stylelint .css: anti-hex + radius + anti-redeclare --accent (gate CI verde)", autor:"CL", data:"31/05", modulos:["Sistema"], flags:[] },
  { tipo:"onda",   ref:"FA (parcial)", resumo:"Pacote F2 Financeiro landou: lentes 029 · Impostos · drawer 3 camadas · ramp", autor:"CL", data:"11/06", modulos:["Financeiro"], flags:[] },
];

// ─── Gates de CI (checks por fase) ───
const FORJA_GATES = [
  { id:"ui:lint",          fase:"F3",   estado:"green" },
  { id:"conformance-gate", fase:"F1",   estado:"green" },
  { id:"foundation-guard", fase:"F3",   estado:"green" },
  { id:"stylelint",        fase:"F3",   estado:"green" },
  { id:"eslint ds/*",      fase:"F3",   estado:"green" },
  { id:"a11y (WCAG AA)",   fase:"F3.5", estado:"amber" },
  { id:"e2e (Playwright)", fase:"F3.5", estado:"red"   },
];

// ─── Camada MCP (mockada · contrato + tokens + auditoria) ───
const FORJA_MCP_TOOLS = [
  { tool:"backlog.read",     acao:"ler issues / filtros",     perm:"ok",     nota:"leitura livre" },
  { tool:"changelog.read",   acao:"o que shippou",            perm:"ok",     nota:"leitura livre" },
  { tool:"issue.transition", acao:"mover fase",               perm:"propoe", nota:"propõe → [W] aprova" },
  { tool:"changelog.append", acao:"registrar entrega",        perm:"propoe", nota:"propõe → transporte" },
  { tool:"adr.propose",      acao:"cria _PROPOSTA",           perm:"propoe", nota:"nunca decisions/NNNN" },
  { tool:"git.merge",        acao:"fechar PR",                perm:"deny",   nota:"só [W2]" },
  { tool:"constituicao.edit",acao:"ADR/PROTOCOL/BRIEFING",    perm:"deny",   nota:"só [W]" },
  { tool:"handoff-pending",  acao:"puxar handoff F1→F3",      perm:"ok",     nota:"Code lê, assinado" },
  { tool:"handoff-ack",      acao:"confirmar aplicado + gate", perm:"propoe", nota:"422 sem gate verde" },
];
const FORJA_MCP_TOKENS = [
  { id:"frj_cc_live", papel:"CC", escopo:"read + propose", exp:"30d", uso:"há 2 min" },
  { id:"frj_cl_ci",   papel:"CL", escopo:"read + propose", exp:"90d", uso:"há 1 h" },
  { id:"frj_cd_rev",  papel:"CD", escopo:"read",           exp:"30d", uso:"há 3 h" },
];
const FORJA_MCP_AUDIT = [
  { ts:"14:21", ator:"CC", tool:"backlog.read",     args:"onda=FA-1",      res:"ok · 3 issues",        deny:false },
  { ts:"14:19", ator:"CC", tool:"adr.propose",      args:"--origin-DEV",   res:"proposta criada",      deny:false },
  { ts:"13:50", ator:"CL", tool:"issue.transition", args:"FORJA-141 →F3",  res:"aguarda [W]",          deny:false },
  { ts:"12:30", ator:"CC", tool:"git.merge",        args:"#2417",          res:"NEGADO — só [W2]",     deny:true  },
  { ts:"11:05", ator:"CD", tool:"changelog.read",   args:"desde 09/06",    res:"ok · 8 entradas",      deny:false },
  { ts:"10:02", ator:"CC", tool:"constituicao.edit",args:"ADR 0235",       res:"NEGADO — só [W]",      deny:true  },
];

// ─── Handoffs F1→F3 (Cowork→Code via MCP · mockado · espelha cowork_handoffs) ───
// estado: pending (aguarda Code puxar) · applied (PR aberto, gates rodando) ·
//         merged (gate verde + auto-merge) · stale (pending > 3d, alerta ops) ·
//         blocked (gate vermelho — volta pro [CC])
// gate: do CI REAL (required checks do PR), não do auto-relato do ack. gateConflito=true
//       quando o ack diz verde mas o CI não confirma (adversário Gap 2).
// lastIngest: heartbeat do sync (PR-6) — empty-state distingue ocioso de quebrado (Gap 7).
const FORJA_HANDOFF_HEARTBEAT = { lastIngest: "há 6 min", saudavel: true };
const FORJA_HANDOFFS = [
  { slug:"compras-reconciliacao", v:2, tela:"Compras /purchases", onda:"FA-6", estado:"pending",
    sig:"ok", arquivos:4, gate:null, gateConflito:false, pr:null, quando:"há 8 min", autor:"CC",
    nota:"drawer overlay + PageHeader canon + KPI R$ + sort acessível" },
  { slug:"caixa-filtros-2botoes", v:1, tela:"Caixa unificada", onda:"FA-5", estado:"applied",
    sig:"ok", arquivos:3, gate:"rodando", gateConflito:false, pr:"#2461", quando:"há 1 h", autor:"CC",
    nota:"conformance ok · critique 86 · a11y rodando" },
  { slug:"fa5-drawer-975", v:1, tela:"Financeiro/Unificado", onda:"FA-5", estado:"merged",
    sig:"ok", arquivos:2, gate:"verde", gateConflito:false, pr:"#2458", quando:"há 3 h", autor:"CC",
    nota:"3 gates verdes · auto-merge" },
  { slug:"ondas-financeiro-aplicar", v:3, tela:"Financeiro (FA-1..4)", onda:"FA-4", estado:"blocked",
    sig:"ok", arquivos:6, gate:"vermelho", gateConflito:false, pr:"#2440", quando:"há 5 h", autor:"CC",
    nota:"critique 74 (<80) — cor crua no delta · volta pro [CC]" },
  { slug:"censo-adocao-ds", v:1, tela:"DS adoption", onda:"FA-3", estado:"stale",
    sig:"ok", arquivos:1, gate:null, gateConflito:false, pr:null, quando:"há 4 d", autor:"CC",
    nota:"pending > 3d — alerta no inbox ops (anti feedback-void)" },
  { slug:"vendas-aplus-drawer", v:2, tela:"Vendas /sells", onda:"FA-6", estado:"applied",
    sig:"ok", arquivos:5, gate:"verde", gateConflito:true, pr:"#2463", quando:"há 22 min", autor:"CC",
    nota:"ack diz verde, mas a11y do PR ainda vermelho — CONFLITO" },
];

window.FORJA = {
  ACTORS: FORJA_ACTORS, PHASES: FORJA_PHASES, TYPES: FORJA_TYPES,
  ONDAS: FORJA_ONDAS, ISSUES: FORJA_ISSUES, CHANGELOG: FORJA_CHANGELOG, GATES: FORJA_GATES,
  MCP_TOOLS: FORJA_MCP_TOOLS, MCP_TOKENS: FORJA_MCP_TOKENS, MCP_AUDIT: FORJA_MCP_AUDIT,
  HANDOFFS: FORJA_HANDOFFS, HANDOFF_HEARTBEAT: FORJA_HANDOFF_HEARTBEAT,
};
