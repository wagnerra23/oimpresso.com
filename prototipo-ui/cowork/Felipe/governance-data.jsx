// governance-data.jsx — dados do módulo Governança no app único. Espelha o que os controllers do
// Modules/Governance do main servem: DashboardController (KPIs da Constituição, ADRs propostos,
// ocorrências 24 h, saúde do ecossistema, SDD, seção MCP), PoliciesController (mcp_governance_rules),
// AuditController (mcp_audit_log, teto 200), DriftAlertsController (scan de SCOPE.md) e
// ModuleGradeController (rubrica module-grade-v3, ADR 0155).
// Números são de protótipo — a forma é a do vivo. Expõe window.GovernanceData.
(() => {

// ── Constituição — os 10 artigos e o juízo escrito à mão no controller ──
// compliancePct = (7 * 10) + (2 * 5) + 0 = 80. É soma literal, não apuração (achado A2 do charter).
const ARTIGOS = [
  { n: 1, t: "Fonte única da verdade", estado: "pleno" },
  { n: 2, t: "Mexeu, registra", estado: "pleno" },
  { n: 3, t: "Decisão vira ADR", estado: "pleno" },
  { n: 4, t: "Skill versionada", estado: "parcial" },
  { n: 5, t: "Isolamento multi-tenant", estado: "pleno" },
  { n: 6, t: "Exceção formal declarada", estado: "pleno" },
  { n: 7, t: "Module Charter", estado: "parcial" },
  { n: 8, t: "Policy gating", estado: "pendente" },
  { n: 9, t: "Auditoria append-only", estado: "pleno" },
  { n: 10, t: "Ambiente reproduzível", estado: "pleno" },
];

const KPIS_CONST = [
  { id: "adrs", l: "ADRs aguardando decisão", v: 7, tone: "warning", sub: "status proposto" },
  { id: "pol", l: "Políticas ativas", v: 23, tone: "default", sub: "de 31 no catálogo" },
  { id: "skill", l: "Aprovações de skill", v: 2, tone: "info", sub: "versões em revisão" },
  { id: "atores", l: "Atores registrados", v: 14, tone: "default", sub: "sem revogação" },
  { id: "ocor", l: "Ocorrências em 24 h", v: 9, tone: "danger", sub: "resultado diferente de concluído" },
];

const ADRS = [
  { slug: "0372-fila-de-impressao-como-fsm", t: "Fila de impressão como máquina de estados", dias: 2 },
  { slug: "0371-retencao-de-anexos-por-modulo", t: "Retenção de anexos por módulo (LGPD Art. 16)", dias: 3 },
  { slug: "0369-ledger-de-conformidade-do-ds", t: "Ledger de conformidade do design system", dias: 5 },
  { slug: "0368-cotas-por-ator-no-mcp", t: "Cotas por ator no servidor MCP", dias: 6 },
  { slug: "0367-orcamento-versionado-sem-clone", t: "Orçamento versionado sem clonar registro", dias: 9 },
  { slug: "0365-catraca-semantica-de-enum", t: "Catraca semântica: enum nunca aparece cru", dias: 12 },
  { slug: "0364-escopo-de-anexo-por-negocio", t: "Escopo de anexo por negócio no DMS", dias: 18 },
];

// mcp_audit_log — últimas 24 h, status != ok. É o que o painel destaca e a auditoria detalha.
const OCORRENCIAS = [
  { h: "18:42", ator: "delphi-wr-comercial", ep: "tools/call", alvo: "vendas.registrar", res: "quota_exceeded", ms: 41 },
  { h: "17:05", ator: "jana-brain-b", ep: "tools/call", alvo: "financeiro.conciliar", res: "error", ms: 2380 },
  { h: "16:58", ator: "jana-brain-b", ep: "tools/call", alvo: "financeiro.conciliar", res: "error", ms: 2411 },
  { h: "15:30", ator: "app-tecnico", ep: "resources/read", alvo: "os.anexo", res: "denied", ms: 12 },
  { h: "14:12", ator: "claude-code", ep: "tools/call", alvo: "memory.write", res: "denied", ms: 8 },
  { h: "11:47", ator: "delphi-oficina", ep: "tools/list", alvo: "—", res: "error", ms: 1902 },
  { h: "09:21", ator: "app-tecnico", ep: "tools/call", alvo: "os.fechar", res: "denied", ms: 15 },
  { h: "08:03", ator: "jana-brain-a", ep: "tools/call", alvo: "bi.narrar", res: "error", ms: 3105 },
  { h: "07:14", ator: "delphi-wr-comercial", ep: "tools/call", alvo: "estoque.baixar", res: "quota_exceeded", ms: 37 },
];

const SAUDE = [
  { id: "jobs", l: "Jobs falhos em 24 h", v: 3, tone: "warning", sub: "fila do Horizon" },
  { id: "custo", l: "Custo de IA em 24 h", v: "R$ 41,80", tone: "default", sub: "tokens de entrada e saída" },
  { id: "narrativa", l: "Última narrativa", v: "06:00", tone: "info", sub: "rotina diária" },
];

const NARRATIVAS = [
  { sev: "warn", h: "06:00", txt: "Três jobs de conciliação falharam de madrugada, todos no mesmo extrato do Sicredi. A fila drenou sozinha às 05:40." },
  { sev: "info", h: "05:12", txt: "Volume de chamadas ao MCP 22% acima da média para uma segunda-feira — origem é o balcão da ROTA LIVRE." },
  { sev: "ok", h: "00:15", txt: "Fechamento do dia sem intercorrência: 41 vendas, 6 ordens de produção encerradas." },
];

const SDD = { data: "22/08/2026", composta: 7.8, delta: 0.3, vivas: 8, total: 10, alertas: ["Cobertura de caso↔teste abaixo da meta em 2 módulos"] };

// ── Seção MCP (gate jana.mcp.usage.all) ──
const MCP = {
  chamadas: 18432, sucesso: 97.4, p50: 84, p95: 412, p99: 1240, max: 3105, custo: "R$ 268,40",
  serie: [980, 1120, 1044, 1310, 1502, 640, 410, 1290, 1388, 1420, 1610, 1502, 980, 520, 1180, 1244],
  resultado: [
    { l: "Concluído", v: 17953, tone: "ok" },
    { l: "Negado", v: 287, tone: "warn" },
    { l: "Erro", v: 141, tone: "danger" },
    { l: "Cota excedida", v: 51, tone: "warn" },
  ],
  negadas: [
    { cod: "rbac.tool_not_allowed", v: 148 },
    { cod: "rbac.resource_scope", v: 86 },
    { cod: "actor.revoked", v: 33 },
    { cod: "quota.daily", v: 20 },
  ],
  usuarios: [
    { n: "delphi-wr-comercial", v: 7420 }, { n: "jana-brain-b", v: 4102 }, { n: "claude-code", v: 3180 },
    { n: "app-tecnico", v: 1904 }, { n: "delphi-oficina", v: 1120 }, { n: "jana-brain-a", v: 706 },
  ],
  tools: [
    { n: "vendas.registrar", v: 5210 }, { n: "clientes.buscar", v: 3980 }, { n: "estoque.baixar", v: 2740 },
    { n: "memory.search", v: 2110 }, { n: "financeiro.conciliar", v: 1640 }, { n: "os.abrir", v: 1180 },
  ],
};

const PERIODOS = [
  { id: "hoje", l: "Hoje" }, { id: "ontem", l: "Ontem" }, { id: "7d", l: "7 dias" },
  { id: "30d", l: "30 dias" }, { id: "mes_anterior", l: "Mês anterior" },
];

// ── Políticas (mcp_governance_rules) ──
const POLITICAS = [
  { id: 1, cat: "Multi-tenant", k: "tenant.scope_required", n: "Consulta sem escopo de negócio", d: "Bloqueia consulta em tabela com business_id quando o escopo não é aplicado.", v: 3, disparos: 412, on: true },
  { id: 2, cat: "Multi-tenant", k: "tenant.cross_business_write", n: "Escrita cruzando negócios", d: "Nenhuma escrita atravessa negócio, nem por superadmin, sem exceção declarada.", v: 2, disparos: 7, on: true },
  { id: 3, cat: "Multi-tenant", k: "tenant.mcp_exception", n: "Exceção formal das tabelas mcp_*", d: "Declara que as mcp_* são cross-tenant por design — Constituição Art. 6+8.", v: 1, disparos: 0, on: true },
  { id: 4, cat: "Dinheiro e fiscal", k: "money.no_autonomous_merge", n: "Mudança em dinheiro escala para decisão humana", d: "Toda alteração em cálculo, imposto ou baixa financeira vai para [W]. Nunca merge autônomo.", v: 4, disparos: 63, on: true },
  { id: 5, cat: "Dinheiro e fiscal", k: "fiscal.nfe_layout_frozen", n: "Layout de NF-e congelado", d: "Alterar campo de layout fiscal exige ADR e homologação em separado.", v: 2, disparos: 4, on: true },
  { id: 6, cat: "Dinheiro e fiscal", k: "money.rounding_policy", n: "Arredondamento por cálculo de m²", d: "Meia unidade para cima, duas casas, em toda a cadeia de orçamento.", v: 1, disparos: 128, on: false },
  { id: 7, cat: "Registro e memória", k: "memory.touch_requires_record", n: "Mexeu, registra", d: "Alteração de módulo sem registro correspondente é divergência — REGRA PRIMÁRIA.", v: 5, disparos: 981, on: true },
  { id: 8, cat: "Registro e memória", k: "adr.decision_requires_adr", n: "Decisão vira ADR", d: "Escolha estrutural sem ADR não passa do gate.", v: 3, disparos: 74, on: true },
  { id: 9, cat: "Registro e memória", k: "charter.trio_required", n: "Trio de tela obrigatório", d: "Tela nova nasce com componente, charter e casos de uso — ADR 0264.", v: 2, disparos: 39, on: true },
  { id: 10, cat: "Auditoria", k: "audit.append_only", n: "Auditoria é imutável", d: "UPDATE ou DELETE em mcp_audit_log é bloqueado por trigger — incidente P0.", v: 1, disparos: 0, on: true },
  { id: 11, cat: "Auditoria", k: "audit.no_pii", n: "Sem dado pessoal no registro", d: "Nome, documento e telefone não entram no corpo do registro de auditoria.", v: 2, disparos: 11, on: true },
  { id: 12, cat: "Interface", k: "ui.no_raw_palette", n: "Cor crua de paleta", d: "Hex e classe de paleta fora dos tokens contam como regressão medida.", v: 4, disparos: 314, on: true },
  { id: 13, cat: "Interface", k: "ui.pt_br_only", n: "Português na interface do cliente", d: "Termo fora do dicionário de domínio bloqueia — inglês em tela cliente-facing não passa.", v: 2, disparos: 57, on: true },
  { id: 14, cat: "Interface", k: "ui.no_fullscreen_detail", n: "Detalhe não é modal em tela cheia", d: "Detalhe vive em gaveta lateral (PT-02); modal é só confirmação.", v: 1, disparos: 6, on: false },
  { id: 15, cat: "Agentes", k: "agent.destructive_tool_gate", n: "Ferramenta destrutiva pede confirmação", d: "Apagar, revogar e truncar exigem confirmação explícita do ator humano.", v: 3, disparos: 22, on: true },
  { id: 16, cat: "Agentes", k: "agent.daily_quota", n: "Cota diária por ator", d: "Ator que estoura a cota recebe recusa com código, não fila.", v: 2, disparos: 51, on: true },
  { id: 17, cat: "Agentes", k: "agent.secret_never_read", n: "Segredo não se lê", d: "Nenhuma rota devolve segredo de credencial — nem para o administrador.", v: 1, disparos: 3, on: true },
];

// ── Auditoria (mcp_audit_log — teto de 200 por consulta) ──
const RES_LABEL = { ok: "Concluído", denied: "Negado", error: "Erro", quota_exceeded: "Cota excedida" };
const ENDPOINTS = ["tools/call", "tools/list", "resources/read", "resources/list", "prompts/get"];
const ATORES = ["delphi-wr-comercial", "delphi-oficina", "jana-brain-a", "jana-brain-b", "claude-code", "app-tecnico"];
const ALVOS = ["vendas.registrar", "clientes.buscar", "estoque.baixar", "memory.search", "financeiro.conciliar", "os.abrir", "os.fechar", "bi.narrar", "memory.write", "os.anexo"];

// Gerador estável (semente fixa) — a amostra não pode dançar entre renders.
function auditoria() {
  let s = 20260823;
  const r = () => { s = (s * 1103515245 + 12345) % 2147483648; return s / 2147483648; };
  const out = [];
  for (let i = 0; i < 260; i++) {
    const rr = r();
    const res = rr > 0.955 ? "error" : rr > 0.93 ? "denied" : rr > 0.915 ? "quota_exceeded" : "ok";
    const min = i * 5 + Math.floor(r() * 4);
    const d = new Date(2026, 7, 23, 19, 0, 0);
    d.setMinutes(d.getMinutes() - min);
    out.push({
      id: 91240 - i,
      ts: d,
      ator: ATORES[Math.floor(r() * ATORES.length)],
      ep: ENDPOINTS[Math.floor(r() * ENDPOINTS.length)],
      alvo: ALVOS[Math.floor(r() * ALVOS.length)],
      res,
      ms: res === "error" ? 1800 + Math.floor(r() * 1600) : 8 + Math.floor(r() * 300),
    });
  }
  return out;
}

// ── Drift de escopo (SCOPE.md declarado × filesystem real) ──
const DRIFT = [
  { mod: "Financeiro", total: 19, undeclared: ["ConciliacaoLoteController", "BoletoRemessaController"] },
  { mod: "Oficina", total: 12, undeclared: ["VistoriaDigitalController"] },
  { mod: "Jana", total: 14, undeclared: ["AlertasConfigController", "FontesController"] },
  { mod: "Compras", total: 11, undeclared: ["GradeMatrixController"] },
];
const SEM_SCOPE = ["Spreadsheet", "Woocommerce", "TeamMcp", "AssetManagement", "Cms"];
const MODULOS_TOTAL = 34;
const YAML_ILEGIVEL = [{ mod: "Essentials", erro: "linha 12: indentação inconsistente em contains[]" }];

// ── Notas dos módulos (rubrica module-grade-v3, ADR 0155) ──
// D6–D9 em null = ainda não avaliado na v3. Travessão nunca é zero (achado A7).
const DIMS = ["D1", "D2", "D3", "D4", "D5", "D6", "D7", "D8", "D9"];
const DIM_NOME = { D1: "Domínio", D2: "Testes", D3: "Charter", D4: "Interface", D5: "Rotas", D6: "Desempenho", D7: "LGPD", D8: "Segurança", D9: "Observabilidade" };
const NOTAS = [
  { m: "Atendimento", nota: 94, d: [10, 10, 10, 10, 10, 9, 9, 9, 8] },
  { m: "Crm", nota: 91, d: [10, 9, 10, 10, 10, 9, 9, 8, 8] },
  { m: "Financeiro", nota: 88, d: [10, 9, 9, 10, 9, 8, 9, 8, 7] },
  { m: "Governance", nota: 86, d: [9, 9, 10, 9, 9, 8, 8, 9, 7] },
  { m: "Ponto", nota: 84, d: [9, 9, 9, 9, 9, 8, 9, 8, 6] },
  { m: "Produtos", nota: 81, d: [9, 8, 9, 9, 9, 7, 8, 7, 6] },
  { m: "Fiscal", nota: 79, d: [9, 8, 8, 8, 9, 7, 8, 8, 6] },
  { m: "Estoque", nota: 74, d: [8, 7, 8, 8, 8, 7, 7, 7, 5] },
  { m: "Oficina", nota: 71, d: [8, 7, 8, 8, 7, null, null, null, null] },
  { m: "Compras", nota: 68, d: [8, 6, 7, 7, 8, null, null, null, null] },
  { m: "Forja", nota: 66, d: [7, 6, 7, 8, 7, 6, 7, 6, 5] },
  { m: "Jana", nota: 61, d: [7, 5, 7, 6, 7, 6, 6, 6, 4] },
  { m: "Cms", nota: 54, d: [6, 5, 6, 6, 6, null, null, null, null] },
  { m: "Connector", nota: 49, d: [6, 4, 6, 5, 5, null, null, null, null] },
  { m: "Auditoria", nota: 44, d: [5, 4, 5, 5, 5, 4, 6, 5, 3] },
  { m: "AssetManagement", nota: 31, d: [4, 2, 3, 4, 4, null, null, null, null] },
  { m: "Woocommerce", nota: 22, d: [3, 1, 2, 3, 3, null, null, null, null] },
  { m: "Spreadsheet", nota: 14, d: [2, 0, 2, 2, 2, null, null, null, null] },
];
const FAIXAS = [
  { id: "excelente", l: "Excelente", min: 85, tone: "ok" },
  { id: "bom", l: "Bom", min: 70, tone: "info" },
  { id: "medio", l: "Médio", min: 50, tone: "warn" },
  { id: "critico", l: "Crítico", min: 30, tone: "danger" },
  { id: "embriao", l: "Embrião", min: 0, tone: "mute" },
];
const faixaDe = (n) => FAIXAS.find((f) => n >= f.min) || FAIXAS[FAIXAS.length - 1];

window.GovernanceData = {
  ARTIGOS, KPIS_CONST, ADRS, OCORRENCIAS, SAUDE, NARRATIVAS, SDD, MCP, PERIODOS,
  POLITICAS, RES_LABEL, ENDPOINTS, ATORES, auditoria,
  DRIFT, SEM_SCOPE, MODULOS_TOTAL, YAML_ILEGIVEL,
  DIMS, DIM_NOME, NOTAS, FAIXAS, faixaDe,
};
})();
