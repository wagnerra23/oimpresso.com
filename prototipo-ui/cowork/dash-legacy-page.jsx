// dash-legacy-page.jsx — reconstrução React do dashboard Blade legado (`/dashboard-legacy?legacy=1`).
// Fonte: resources/views/home/index.blade.php + home/partials/* + public/js/home.js + HomeController@indexLegacy.
// Ficha: cowork-inbox/FICHA-BL-home-index.md · arquétipo PT-05 · persona Wagner.
// Decisões aplicadas: hero = Líquido · período default = mês corrente · 4 grades no topo, resto em abas.
(() => {
const { useState, useMemo } = React;
const NS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// métricas onde subir é ruim ficam sem o delta colorido do DS (a cor sai do sinal, não do sentido)
const sinal = (n) => (n >= 0 ? "+" : "") + n + "%";
const brlK = (n) => "R$ " + (n / 1000).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + "k";

// ── KPIs por período (getTotals) — NET = vendas − a receber − despesas ──
const TOTAIS = {
  hoje:   { total_sell: 14820, invoice_due: 3240, total_expense: 1180, total_purchase: 6420, purchase_due: 2100, total_sell_return: 380, total_purchase_return: 0 },
  semana: { total_sell: 78450, invoice_due: 19870, total_expense: 8240, total_purchase: 31200, purchase_due: 12480, total_sell_return: 1920, total_purchase_return: 640 },
  mes:    { total_sell: 312480, invoice_due: 68420, total_expense: 41870, total_purchase: 128340, purchase_due: 37600, total_sell_return: 7480, total_purchase_return: 2140 },
};
const kpiSet = (p) => { const t = TOTAIS[p] || TOTAIS.mes; return { ...t, net: t.total_sell - t.invoice_due - t.total_expense }; };

const SERIE_30 = [8.2,11.4,9.6,14.2,12.8,6.1,0,10.4,13.6,12.2,15.8,14.1,7.4,0,11.9,16.2,14.8,13.1,17.4,15.2,8.8,0,12.6,18.1,16.4,15.9,19.2,17.8,9.4,14.8];
const SERIE_FY = [186,204,241,228,262,254,289,276,312,298,334,312].map((v, i) => ({ label: ["jan","fev","mar","abr","mai","jun","jul","ago","set","out","nov","dez"][i], value: v }));

// ── Grades (9 fontes do Blade) ──
const COL_VENC_VENDA = [
  { key: "nf", label: "Nota", mono: true, sortable: true, width: 110 },
  { key: "cliente", label: "Cliente", sortable: true },
  { key: "venc", label: "Vencimento", mono: true, sortable: true, width: 120 },
  { key: "status", label: "Situação", width: 120 },
  { key: "devido", label: "Devido", align: "right", sortable: true, width: 130 },
];
const COL_VENC_COMPRA = [
  { key: "nf", label: "Referência", mono: true, sortable: true, width: 120 },
  { key: "fornecedor", label: "Fornecedor", sortable: true },
  { key: "venc", label: "Vencimento", mono: true, sortable: true, width: 120 },
  { key: "status", label: "Situação", width: 120 },
  { key: "devido", label: "Devido", align: "right", sortable: true, width: 130 },
];
const COL_ESTOQUE = [
  { key: "produto", label: "Produto", sortable: true },
  { key: "loja", label: "Loja", width: 140 },
  { key: "atual", label: "Estoque", align: "right", mono: true, sortable: true, width: 110 },
  { key: "minimo", label: "Mínimo", align: "right", mono: true, width: 100 },
];
const COL_VALIDADE = [
  { key: "produto", label: "Produto", sortable: true },
  { key: "lote", label: "Lote", mono: true, width: 110 },
  { key: "saldo", label: "Saldo", align: "right", mono: true, width: 100 },
  { key: "vence", label: "Vence em", mono: true, sortable: true, width: 130 },
];
const COL_PEDIDOS = [
  { key: "num", label: "Pedido", mono: true, sortable: true, width: 120 },
  { key: "cliente", label: "Cliente", sortable: true },
  { key: "data", label: "Data", mono: true, width: 110 },
  { key: "status", label: "Situação", width: 130 },
  { key: "total", label: "Total", align: "right", sortable: true, width: 130 },
];
const COL_OC = [
  { key: "num", label: "Ordem", mono: true, sortable: true, width: 120 },
  { key: "fornecedor", label: "Fornecedor", sortable: true },
  { key: "data", label: "Data", mono: true, width: 110 },
  { key: "status", label: "Situação", width: 130 },
  { key: "total", label: "Total", align: "right", sortable: true, width: 130 },
];
const COL_REQ = [
  { key: "num", label: "Requisição", mono: true, sortable: true, width: 120 },
  { key: "setor", label: "Setor solicitante", sortable: true },
  { key: "data", label: "Data", mono: true, width: 110 },
  { key: "status", label: "Situação", width: 130 },
  { key: "total", label: "Estimado", align: "right", sortable: true, width: 130 },
];
const COL_EXPEDICAO = [
  { key: "nf", label: "Nota", mono: true, width: 110 },
  { key: "cliente", label: "Cliente" },
  { key: "entrega", label: "Entrega", mono: true, width: 120 },
  { key: "status", label: "Situação", width: 140 },
];
const COL_CAIXA = [
  { key: "data", label: "Data", mono: true, sortable: true, width: 110 },
  { key: "conta", label: "Conta" },
  { key: "descricao", label: "Descrição" },
  { key: "valor", label: "Crédito", align: "right", sortable: true, width: 130 },
];

const B = (kind, value) => { const S = NS().StatusBadge; return S ? React.createElement(S, { kind, value }) : value; };

const GRADES = {
  "venc-venda": { label: "Vencimentos de venda", count: 7, cols: COL_VENC_VENDA, perm: "sell.view",
    rows: () => [
      { id: "vv-1240", nf: "NF 1240", cliente: "Acme Comércio", venc: "19/08/2026", status: B("payment", "overdue"), devido: brl(12480), state: "urgent" },
      { id: "vv-1236", nf: "NF 1236", cliente: "Padaria Estrela", venc: "20/08/2026", status: B("payment", "partial"), devido: brl(3240) },
      { id: "vv-1231", nf: "NF 1231", cliente: "TechPro Sistemas", venc: "21/08/2026", status: B("payment", "pending"), devido: brl(8760) },
      { id: "vv-1229", nf: "NF 1229", cliente: "Mercado União", venc: "22/08/2026", status: B("payment", "pending"), devido: brl(2180) },
      { id: "vv-1224", nf: "NF 1224", cliente: "Posto BR Centro", venc: "23/08/2026", status: B("payment", "partial"), devido: brl(15900) },
      { id: "vv-1220", nf: "NF 1220", cliente: "Clínica Vida", venc: "24/08/2026", status: B("payment", "pending"), devido: brl(1340) },
      { id: "vv-1218", nf: "NF 1218", cliente: "Auto Center RS", venc: "25/08/2026", status: B("payment", "pending"), devido: brl(24520) },
    ] },
  "venc-compra": { label: "Vencimentos de compra", count: 5, cols: COL_VENC_COMPRA, perm: "purchase.view",
    rows: () => [
      { id: "vc-4521", nf: "NF-e 4521", fornecedor: "Lonas & Vinis Ltda", venc: "19/08/2026", status: B("payment", "overdue"), devido: brl(8420), state: "urgent" },
      { id: "vc-0998", nf: "NF-e 0998", fornecedor: "Tintas Coral", venc: "21/08/2026", status: B("payment", "pending"), devido: brl(3240) },
      { id: "vc-7711", nf: "NF-e 7711", fornecedor: "Papel & Cia Atacado", venc: "22/08/2026", status: B("payment", "pending"), devido: brl(1180) },
      { id: "vc-0234", nf: "NF-e 0234", fornecedor: "Lonas & Vinis Ltda", venc: "24/08/2026", status: B("payment", "partial"), devido: brl(4620) },
      { id: "vc-1102", nf: "NF-e 1102", fornecedor: "Importadora Têxtil RH", venc: "25/08/2026", status: B("payment", "pending"), devido: brl(12450) },
    ] },
  "estoque": { label: "Estoque mínimo", count: 12, cols: COL_ESTOQUE, perm: "stock_report.view",
    rows: () => [
      { id: "est-200", produto: "Lona 380gr brilho (PROD-200)", loja: "Matriz", atual: "18 m²", minimo: "120 m²", state: "urgent" },
      { id: "est-022", produto: "Vinil adesivo brilho (PROD-022)", loja: "Matriz", atual: "42 m²", minimo: "150 m²" },
      { id: "est-i022", produto: "Tinta solvente CMYK 5L (INS-022)", loja: "Matriz", atual: "1 un", minimo: "4 un", state: "urgent" },
      { id: "est-i014", produto: "Ilhós metálico nº 12 (INS-014)", loja: "Filial Norte", atual: "820 un", minimo: "2.000 un" },
      { id: "est-201", produto: "Lona blackout 440gr (PROD-201)", loja: "Matriz", atual: "26 m²", minimo: "80 m²" },
      { id: "est-118", produto: "Papel fotográfico 260g (PROD-118)", loja: "Filial Norte", atual: "9 fl", minimo: "50 fl" },
    ] },
  "validade": { label: "Lotes a vencer", count: 4, cols: COL_VALIDADE, perm: "stock_report.view",
    rows: () => [
      { id: "val-2851", produto: "Tinta solvente ciano", lote: "L2851", saldo: "2 un", vence: "27/08/2026", state: "urgent" },
      { id: "val-2790", produto: "Cola de contato 1L", lote: "L2790", saldo: "6 un", vence: "04/09/2026" },
      { id: "val-2744", produto: "Verniz UV brilho", lote: "L2744", saldo: "3 un", vence: "12/09/2026" },
      { id: "val-2712", produto: "Fita dupla-face 3M", lote: "L2712", saldo: "14 un", vence: "16/09/2026" },
    ] },
  "pedidos": { label: "Pedidos de venda", count: 6, cols: COL_PEDIDOS, perm: "so.view",
    rows: () => [
      { id: "pv-2291", num: "PV-2291", cliente: "Acme Comércio", data: "17/08", status: B("documento", "aprovado"), total: brl(18400) },
      { id: "pv-2290", num: "PV-2290", cliente: "TechPro Sistemas", data: "17/08", status: B("documento", "rascunho"), total: brl(6240) },
      { id: "pv-2288", num: "PV-2288", cliente: "Mercado União", data: "16/08", status: B("documento", "aprovado"), total: brl(3180) },
      { id: "pv-2284", num: "PV-2284", cliente: "Posto BR Centro", data: "15/08", status: B("documento", "pendente"), total: brl(22750) },
      { id: "pv-2281", num: "PV-2281", cliente: "Clínica Vida", data: "15/08", status: B("documento", "aprovado"), total: brl(1490) },
      { id: "pv-2279", num: "PV-2279", cliente: "Auto Center RS", data: "14/08", status: B("documento", "pendente"), total: brl(9860) },
    ] },
  "compras-abertas": { label: "Ordens de compra", count: 3, cols: COL_OC, perm: "purchase.view",
    rows: () => [
      { id: "oc-882", num: "OC-882", fornecedor: "Lonas & Vinis Ltda", data: "16/08", status: B("documento", "pendente"), total: brl(12450) },
      { id: "oc-879", num: "OC-879", fornecedor: "Tintas Coral", data: "14/08", status: B("documento", "aprovado"), total: brl(4820) },
      { id: "oc-874", num: "OC-874", fornecedor: "Papel & Cia Atacado", data: "12/08", status: B("documento", "rascunho"), total: brl(2310) },
    ] },
  "requisicoes": { label: "Requisições", count: 2, cols: COL_REQ, perm: "purchase.view",
    rows: () => [
      { id: "rq-114", num: "RQ-114", setor: "Produção — acabamento", data: "17/08", status: B("documento", "rascunho"), total: brl(1840) },
      { id: "rq-112", num: "RQ-112", setor: "Impressão — bobinas", data: "15/08", status: B("documento", "aprovado"), total: brl(7620) },
    ] },
  "expedicao": { label: "Expedições pendentes", count: 8, cols: COL_EXPEDICAO, perm: "access_shipping",
    rows: () => [
      { id: "exp-1240", nf: "NF 1240", cliente: "Acme Comércio", entrega: "19/08", status: B("os", "em_producao"), state: "urgent" },
      { id: "exp-1238", nf: "NF 1238", cliente: "Padaria Estrela", entrega: "19/08", status: B("os", "concluida") },
      { id: "exp-1236", nf: "NF 1236", cliente: "TechPro Sistemas", entrega: "20/08", status: B("os", "em_producao") },
      { id: "exp-1233", nf: "NF 1233", cliente: "Mercado União", entrega: "20/08", status: B("os", "concluida") },
      { id: "exp-1230", nf: "NF 1230", cliente: "Posto BR Centro", entrega: "21/08", status: B("os", "em_producao") },
    ] },
  "caixa": { label: "Fluxo de caixa", count: 9, cols: COL_CAIXA, perm: "account.access",
    rows: () => [
      { id: "cx-1", data: "18/08", conta: "Banco Inter", descricao: "Recebimento NF 1236", valor: brl(3240) },
      { id: "cx-2", data: "18/08", conta: "Caixa loja", descricao: "Venda balcão · dinheiro", valor: brl(480) },
      { id: "cx-3", data: "17/08", conta: "Banco Inter", descricao: "Pix Acme Comércio", valor: brl(12480) },
      { id: "cx-4", data: "17/08", conta: "Banco do Brasil", descricao: "Boleto TechPro", valor: brl(8760) },
      { id: "cx-5", data: "16/08", conta: "Caixa loja", descricao: "Venda balcão · cartão", valor: brl(1920) },
    ] },
};

// Delta vs período anterior por preset (o Blade não tinha comparativo — entra como refino)
const DELTAS = { hoje: { net: -3, sell: 4, due: 9, exp: -2 }, semana: { net: 6, sell: 8, due: 5, exp: 3 }, mes: { net: 12, sell: 9, due: -4, exp: 6 } };

const PENDENCIAS = [
  { aba: "venc-venda", texto: "1 título de venda vencido", kind: "payment", valor: "overdue" },
  { aba: "venc-compra", texto: "1 título de compra vencido", kind: "payment", valor: "overdue" },
  { aba: "estoque", texto: "2 produtos abaixo do mínimo", kind: "prioridade", valor: "urgente" },
  { aba: "validade", texto: "1 lote vence em 9 dias", kind: "sla", valor: "aging" },
  { aba: "expedicao", texto: "1 expedição atrasada", kind: "os", valor: "atrasada" },
];

const PAPEIS = {
  admin:     { label: "Administrador", perms: "all" },
  eliana:    { label: "Financeiro (Eliana)", perms: ["dashboard.data", "sell.view", "purchase.view", "account.access"] },
  larissa:   { label: "Balcão (Larissa)", perms: ["dashboard.data", "sell.view", "so.view"] },
  sem:       { label: "Sem dashboard.data", perms: [] },
};

const PANEL = { background: "var(--surface)", border: "1px solid var(--border)", borderRadius: "var(--radius-lg)", boxShadow: "0 1px 2px rgba(0,0,0,.04)", padding: 14 };
const H3 = { margin: 0, fontSize: "13.5px", fontWeight: 600 };
const META = { font: "10.5px/1 var(--font-mono)", color: "var(--text-mute)" };

function DashLegacyPage() {
  const DS = NS();
  const [papel, setPapel] = useState("admin");
  const [loja, setLoja] = useState("todas");
  const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
  const [periodo, setPeriodo] = useState({ from: new Date(hoje.getTime() - 29 * 86400000), to: hoje, preset: "mes" });
  const [aba, setAba] = useState("venc-venda");
  const [detalhe, setDetalhe] = useState(null);
  const [falha, setFalha] = useState(false);

  if (!DS.KpiCard) return <div style={{ padding: 24, color: "var(--text-dim)" }}>Carregando o design system…</div>;
  const { KpiCard, Chart, DataTablePro, PageHeader, TabBar, Button, Select, Drawer, DrawerSection, EmptyState, Alert, PeriodBar, StatusBadge, Switch } = DS;

  const can = (p) => { const cfg = PAPEIS[papel]; return cfg.perms === "all" || cfg.perms.indexOf(p) >= 0; };
  const dashboard = can("dashboard.data");
  // presets do DS (dia/semana/mes) mapeiam nas 3 faixas de TOTAIS; intervalo livre cai no conjunto do mês
  const preset = periodo.preset === "dia" ? "hoje" : periodo.preset === "semana" ? "semana" : "mes";
  const k = useMemo(() => kpiSet(preset), [preset]);
  const D = DELTAS[preset];
  const escala = loja === "todas" ? 1 : loja === "matriz" ? 0.72 : 0.28;
  const v = (n) => n * escala;

  const abas = Object.keys(GRADES).filter((id) => can(GRADES[id].perm));
  const abaAtiva = abas.indexOf(aba) >= 0 ? aba : abas[0];
  const grade = abaAtiva ? GRADES[abaAtiva] : null;

  const controles = (
    <span style={{ ...META, whiteSpace: "nowrap" }}>{PAPEIS[papel].label}</span>
  );

  return (
    <div className="dash-legacy" style={{ flex: "1 1 auto", minHeight: 0, overflowY: "auto", padding: "0 14px 20px", color: "var(--text)", fontFamily: "var(--font-sans)" }}>
      <PageHeader
        title="Visão geral"
        stats={dashboard ? [{ value: brlK(v(k.total_sell)), label: "vendas" }, { value: brlK(v(k.invoice_due)), label: "a receber", tone: "warn" }, { value: brlK(v(k.total_expense)), label: "despesas" }] : []}
        actions={controles} />

      <div style={{ display: "flex", alignItems: "center", gap: 6, margin: "10px 0 12px", flexWrap: "wrap" }}>
        <PeriodBar value={periodo} onChange={setPeriodo} label="Período" />
        <span style={{ ...META, whiteSpace: "nowrap" }}>Substitui o antigo <b style={{ fontWeight: 600 }}>Home</b> (Blade) — <a href="/dashboard-legacy?legacy=1" title="resources/views/home/index.blade.php · name home.legacy" style={{ color: "var(--accent)", textDecoration: "none", borderBottom: "1px solid color-mix(in oklch, var(--accent) 40%, transparent)" }}>/dashboard-legacy?legacy=1</a> · atualizado agora</span>
        <div style={{ display: "flex", alignItems: "flex-end", gap: 8, marginLeft: "auto" }}>
          <div style={{ width: 150 }}>
            <Select label="Loja" value={loja} onChange={(e) => setLoja(e.target.value)}>
              <option value="todas">Todas as lojas</option>
              <option value="matriz">Matriz</option>
              <option value="norte">Filial Norte</option>
            </Select>
          </div>
          <div style={{ paddingBottom: 4 }}>
            <Switch checked={falha} onChange={setFalha} label="Simular falha" />
          </div>
          <div style={{ width: 175 }}>
            <Select label="Papel simulado" value={papel} onChange={(e) => setPapel(e.target.value)}>
              {Object.keys(PAPEIS).map((id) => <option key={id} value={id}>{PAPEIS[id].label}</option>)}
            </Select>
          </div>
        </div>
      </div>

      {!dashboard ? (
        <div style={{ ...PANEL, padding: 0 }}>
          <EmptyState variant="no-perm" title="Você não tem acesso aos dados do painel"
            description="A permissão dashboard.data não está atribuída ao seu papel. A tela abre sem erro — nenhum indicador é carregado."
            action={<Button variant="ghost" size="sm" onClick={() => setPapel("admin")}>Ver como administrador</Button>} />
        </div>
      ) : (
        <React.Fragment>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(230px, 1fr))", gap: 8 }}>
            <KpiCard hero label="Líquido no período" value={brl(v(k.net))} delta={D.net} deltaLabel="% vs anterior" spark={SERIE_30.slice(-12)} />
            <KpiCard tone="success" label="Vendas" value={brl(v(k.total_sell))} delta={D.sell} deltaLabel="% vs anterior" description="incluindo impostos" />
            <KpiCard tone="warning" label="A receber" value={brl(v(k.invoice_due))} description={sinal(D.due) + " vs anterior · líquido de descontos de razão"} />
            <KpiCard tone="info" label="Despesas" value={brl(v(k.total_expense))} description={sinal(D.exp) + " vs anterior · lançadas no período"} />
          </div>

          <div style={{ display: "grid", gridTemplateColumns: "minmax(0, 1.4fr) minmax(240px, 1fr)", gap: 10, marginTop: 10 }}>
            <section style={PANEL}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", gap: 10, marginBottom: 12 }}>
                <h3 style={H3}>Contrapartidas</h3><span style={{ ...META, whiteSpace: "nowrap" }}>mesmo período</span>
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(118px, 1fr))", gap: "12px 14px" }}>
                {[["Compras", v(k.total_purchase), "incluindo impostos"],
                  ["A pagar", v(k.purchase_due), "líquido de descontos"],
                  ["Devolução de venda", v(k.total_sell_return), "bruto " + brlK(v(k.total_sell_return) * 1.6)],
                  ["Devolução de compra", v(k.total_purchase_return), "devido ao fornecedor"]].map(([label, val, sub]) => (
                  <div key={label} style={{ display: "flex", flexDirection: "column", gap: 3, minWidth: 0 }}>
                    <span style={{ font: "600 9.5px/1.4 var(--font-sans)", letterSpacing: ".08em", textTransform: "uppercase", color: "var(--text-mute)" }}>{label}</span>
                    <span style={{ font: "600 15.5px/1.2 var(--font-mono)", fontVariantNumeric: "tabular-nums", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{brlK(val)}</span>
                    <span style={{ fontSize: "11.5px", color: "var(--text-dim)" }}>{sub}</span>
                  </div>
                ))}
              </div>
            </section>
            <section style={PANEL}>
              <h3 style={{ ...H3, marginBottom: 8 }}>Pendências</h3>
              <div style={{ display: "flex", flexDirection: "column" }}>
                {PENDENCIAS.filter((p) => can(GRADES[p.aba].perm)).map((p, i, arr) => (
                  <button key={p.aba} onClick={() => setAba(p.aba)} className="dl-pend" style={{
                    display: "flex", alignItems: "center", justifyContent: "space-between", gap: 10, width: "100%",
                    padding: "7px 0", background: "none", border: 0, cursor: "pointer", textAlign: "left",
                    borderBottom: i < arr.length - 1 ? "1px solid var(--border-2)" : "0", color: "var(--text)" }}>
                    <span style={{ fontSize: "12.5px" }}>{p.texto}</span>
                    <StatusBadge kind={p.kind} value={p.valor} />
                  </button>
                ))}
              </div>
            </section>
          </div>

          <div style={{ display: "grid", gridTemplateColumns: "minmax(0, 1.5fr) minmax(0, 1fr)", gap: 10, marginTop: 10 }}>
            <section style={PANEL}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", gap: 10, marginBottom: 12 }}>
                <h3 style={H3}>Vendas por dia</h3><span style={{ ...META, whiteSpace: "nowrap" }}>últimos 30 dias</span>
              </div>
              <Chart type="area" data={SERIE_30.map((n) => n * escala)} height={132} formatValue={(x) => "R$ " + x.toFixed(1) + "k"} />
            </section>
            <section style={PANEL}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", gap: 10, marginBottom: 12 }}>
                <h3 style={H3}>Vendas por mês</h3><span style={{ ...META, whiteSpace: "nowrap" }}>ano fiscal</span>
              </div>
              <Chart type="bar" data={SERIE_FY.map((d) => ({ ...d, value: d.value * escala }))} height={132} highlightLast formatValue={(x) => "R$ " + Math.round(x) + "k"} />
            </section>
          </div>

          <section style={{ ...PANEL, marginTop: 10, padding: 0, overflow: "hidden" }}>
            <div style={{ padding: "10px 14px 0" }}>
              <TabBar tabs={abas.map((id) => ({ key: id, label: GRADES[id].label, count: GRADES[id].count }))}
                active={abaAtiva} onChange={setAba} />
            </div>
            {falha ? (
              <EmptyState variant="error" title="Não foi possível carregar esta consulta"
                description="O endpoint respondeu com erro. No Blade legado a tela ficava com o loader girando para sempre — aqui a falha é dita."
                action={<Button variant="primary" size="sm" onClick={() => setFalha(false)}>Tentar de novo</Button>} />
            ) : grade ? (
              <React.Fragment>
                <DataTablePro columns={grade.cols} rows={grade.rows()} height={300} density="compact"
                  onRowClick={(row) => setDetalhe({ grade: grade.label, row })} />
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 10, padding: "8px 14px", borderTop: "1px solid var(--border)" }}>
                  <span style={META}>{grade.rows().length} de {grade.count} linhas · clique para abrir o detalhe</span>
                  <Button variant="ghost" size="sm">Exportar CSV</Button>
                </div>
              </React.Fragment>
            ) : null}
          </section>

          <div style={{ marginTop: 10 }}>
            <Alert tone="warn" title="Herança do Blade que não foi portada">
              Os widgets pluggable de módulo (4 posições) estão comentados no Blade legado e ficaram fora desta tela — decidir se voltam.
            </Alert>
          </div>
        </React.Fragment>
      )}

      <Drawer open={!!detalhe} onClose={() => setDetalhe(null)} title={detalhe ? (detalhe.row.nf || detalhe.row.num || detalhe.row.produto || detalhe.row.data) : ""}
        subtitle={detalhe ? detalhe.grade : ""} badge={detalhe && detalhe.row.state === "urgent" ? "urgente" : null}
        footer={<div style={{ display: "flex", gap: 8 }}>
          <Button variant="primary" size="sm">Lançar pagamento</Button>
          <Button variant="ghost" size="sm" onClick={() => setDetalhe(null)}>Fechar</Button>
        </div>}>
        {detalhe ? (
          <DrawerSection title="Linha selecionada">
            <div style={{ display: "grid", gridTemplateColumns: "auto 1fr", gap: "6px 14px", fontSize: "12.5px" }}>
              {Object.keys(detalhe.row).filter((key) => key !== "state" && key !== "id").map((key) => (
                <React.Fragment key={key}>
                  <span style={{ color: "var(--text-mute)", textTransform: "uppercase", font: "600 10px/1.6 var(--font-mono)" }}>{key}</span>
                  <span>{detalhe.row[key]}</span>
                </React.Fragment>
              ))}
            </div>
          </DrawerSection>
        ) : null}
        <DrawerSection title="No legado">
          <p style={{ margin: 0, fontSize: "12.5px", color: "var(--text-dim)", lineHeight: 1.5 }}>
            O Blade abria esta linha num modal (<code>.btn-modal → .view_modal</code>). O canon do Cockpit V2 é drawer lateral — PT-02.
          </p>
        </DrawerSection>
      </Drawer>
    </div>
  );
}

window.DashLegacyPage = DashLegacyPage;
})();
