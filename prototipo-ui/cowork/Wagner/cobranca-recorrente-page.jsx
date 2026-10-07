// cobranca-recorrente-page.jsx — Cobrança Recorrente · F1 [CC] · thread 00 (puxar o vivo) [CL]
// Reescreve a RecurringBilling do git na linguagem do DS (Cockpit V2 warm):
//   stone + roxo var(--accent) · rounded-md · escala warm emerald/amber/rose ·
//   KPI hero warm (var(--text), NÃO bg-zinc-900) · DRAWER LATERAL (não modal/coluna).
// Sub-nav espelha o git RecurringBilling: Assinaturas · Planos · Faturas · Configurações.
// Uma rota por Index (vista fixa) — tabela em window.RbRotas. Planos Create/Edit = drawer
// aberto pelos botões "Novo plano" / "Editar" (sem rota própria: rota nova exige app.jsx).
// Reprodutível: todo "hoje" sai de HOJE (fixo, coerente com o mock de jun/2026).
// Expõe window.CobrancaRecorrentePage + window.RbRotas. Persona: Eliana [E] / Larissa (balcão 1280px).
(function () {
  const { useState, useEffect, useMemo, useRef } = React;

  // ── relógio fixo do mock (medida reprodutível) ─────────────────
  const HOJE = new Date("2026-06-01T12:00");
  const HOJE_ISO = "2026-06-01";

  // ── helpers ────────────────────────────────────────────────────
  const BRL = (n) => (n || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const hueFor = (name) => {let h = 0;for (let i = 0; i < name.length; i++) h = h * 31 + name.charCodeAt(i) >>> 0;return h % 360;};
  const initials = (name) => (name || "").split(/\s+/).slice(0, 2).map((w) => w[0] || "").join("").toUpperCase();
  const MES = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];
  function daysFromToday(iso) {if (!iso) return null;const d = new Date(iso + "T12:00");return Math.round((d - HOJE) / 86400000);}
  function nextLabel(iso) {const dd = daysFromToday(iso);if (dd === null) return "—";if (dd === 0) return "hoje";if (dd === 1) return "amanhã";if (dd < 0) return `há ${-dd} dias`;return `em ${dd} dias`;}
  function dueLabel(iso) {const dd = daysFromToday(iso);if (dd === null) return "—";if (dd === 0) return "hoje";if (dd < 0) return `há ${-dd}d`;return `em ${dd}d`;}
  function dateBR(iso) {if (!iso) return "—";const p = iso.split("-");return `${+p[2]} ${MES[+p[1] - 1]}`;}
  function dateFull(iso) {if (!iso) return "—";const p = iso.split("-");return `${p[2]}/${p[1]}/${p[0]}`;}
  function sinceLabel(iso) {const dd = daysFromToday(iso);if (dd === null) return "—";const m = Math.round(-dd / 30);if (m < 1) return "este mês";if (m < 12) return `há ${m}m`;return `há ${Math.round(m / 12)}a`;}

  const CYCLE = { mensal: "mensal", trimestral: "trimestral", semestral: "semestral", anual: "anual", custom: "customizado" };
  const CYCLE_DIV = { mensal: 1, trimestral: 3, semestral: 6, anual: 12 };

  // estilos de formulário/tabela — só tokens do DS (sem CSS novo)
  const FIELD = { width: "100%", boxSizing: "border-box", height: 32, padding: "0 10px", fontSize: 13, color: "var(--text)", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 6, outline: "none" };
  const AREA = { ...FIELD, height: 64, padding: "6px 10px", resize: "vertical", fontFamily: "inherit" };
  const LBL = { display: "block", fontSize: 11.5, fontWeight: 500, color: "var(--text-dim)", marginBottom: 4 };
  const HINT = { fontSize: 11, color: "var(--text-mute)", marginTop: 3 };
  const TH = { padding: "8px 14px", fontSize: 10.5, textTransform: "uppercase", letterSpacing: "0.06em", fontWeight: 500, color: "var(--text-mute)", textAlign: "left", borderBottom: "1px solid var(--border)", whiteSpace: "nowrap" };
  const TD = { padding: "10px 14px", fontSize: 13, color: "var(--text-dim)", borderBottom: "1px solid var(--border)", verticalAlign: "top" };
  const MONO = { fontFamily: "ui-monospace,monospace", fontSize: 12, fontVariantNumeric: "tabular-nums" };
  const SEC = { margin: "16px 24px 0" };
  const CHIP = (on) => ({ display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 10px", fontSize: 12, fontWeight: 500, borderRadius: 999, cursor: "pointer", border: "1px solid " + (on ? "var(--accent)" : "var(--border)"), background: on ? "var(--accent-soft)" : "var(--surface)", color: on ? "var(--accent)" : "var(--text-dim)" });
  const Req = () => <span style={{ color: "var(--accent)" }}> *</span>;

  // ── mock domínio gráfica (contratos recorrentes) ───────────────
  const SUBS = [
  { id: "AS-1042", client: "Padaria Pão Quente", cnpj: "12.448.770/0001-22", plan: "Sinalização Mensal", cycle: "mensal", since: "2024-08-12", method: "pix", status: "em_dia", retry: null, nextAt: "2026-06-10", nextValue: 450, paid: 22, missed: 0, ltv: 9900, fiscal: "nfse", nf: "NFS-e 1188", os: "OS-3307", fav: true, contact: { name: "Dona Marli", phone: "(11) 99632-1180", email: "marli@paoquente.com.br" }, note: null },
  { id: "AS-1051", client: "Auto Posto Trevo", cnpj: "08.911.332/0001-09", plan: "Adesivos Frota Mensal", cycle: "mensal", since: "2025-02-03", method: "boleto", status: "retentando", retry: 1, nextAt: "2026-06-05", nextValue: 680, paid: 15, missed: 1, ltv: 10200, fiscal: "nfe", nf: "NFe 4471", os: "OS-3312", fav: false, contact: { name: "Cláudio Reis", phone: "(11) 98115-4420", email: "financeiro@postotrevo.com.br" }, note: "Boleto venceu — cliente pediu 2ª via no WhatsApp." },
  { id: "AS-1063", client: "Restaurante Sabor Caseiro", cnpj: "21.005.118/0001-77", plan: "Impressão Trimestral", cycle: "trimestral", since: "2024-11-20", method: "card", status: "em_dia", retry: null, nextAt: "2026-07-20", nextValue: 1200, paid: 6, missed: 0, ltv: 7200, fiscal: "nfe", nf: "NFe 4402", os: null, fav: false, contact: { name: "Sr. Antônio", phone: "(11) 97744-3301", email: "antonio@saborcaseiro.com.br" }, note: null },
  { id: "AS-1070", client: "Academia CorpoForte", cnpj: "33.671.904/0001-50", plan: "Sinalização Mensal", cycle: "mensal", since: "2025-01-15", method: "boleto", status: "falhou", retry: 3, nextAt: "2026-05-28", nextValue: 450, paid: 13, missed: 3, ltv: 5850, fiscal: "nfse", nf: null, os: "OS-3290", fav: true, contact: { name: "Renata Lima", phone: "(11) 99280-7765", email: "renata@corpoforte.com.br" }, note: "3 tentativas sem sucesso. Avaliar suspensão." },
  { id: "AS-1088", client: "Clínica Vida", cnpj: "45.220.661/0001-18", plan: "Manutenção Fachada Anual", cycle: "anual", since: "2024-03-01", method: "pix", status: "em_dia", retry: null, nextAt: "2027-03-01", nextValue: 3600, paid: 2, missed: 0, ltv: 7200, fiscal: "nfse", nf: "NFS-e 1102", os: "OS-3155", fav: false, contact: { name: "Dra. Helena", phone: "(11) 98870-2244", email: "adm@clinicavida.com.br" }, note: null },
  { id: "AS-1094", client: "Mercado União", cnpj: "55.118.022/0001-31", plan: "Adesivos Frota Mensal", cycle: "mensal", since: "2024-09-09", method: "boleto", status: "retentando", retry: 2, nextAt: "2026-06-03", nextValue: 680, paid: 19, missed: 2, ltv: 12920, fiscal: "nfe", nf: "NFe 4468", os: null, fav: false, contact: { name: "Jair Souza", phone: "(11) 99044-1199", email: "jair@mercadouniao.com.br" }, note: null },
  { id: "AS-1101", client: "Escola Saber", cnpj: "60.337.481/0001-04", plan: "Impressão Trimestral", cycle: "trimestral", since: "2024-06-18", method: "boleto", status: "pausada", retry: null, nextAt: null, nextValue: 1200, paid: 7, missed: 0, ltv: 8400, fiscal: "nfe", nf: "NFe 4310", os: null, fav: false, contact: { name: "Coord. Paula", phone: "(11) 97331-8800", email: "paula@escolasaber.com.br" }, note: "Pausado nas férias escolares (jun–jul)." },
  { id: "AS-1109", client: "Farmácia Bem-Estar", cnpj: "71.992.150/0001-66", plan: "Sinalização Mensal", cycle: "mensal", since: "2025-03-22", method: "pix", status: "em_dia", retry: null, nextAt: "2026-06-02", nextValue: 450, paid: 11, missed: 0, ltv: 4950, fiscal: "nfse", nf: "NFS-e 1179", os: "OS-3321", fav: false, contact: { name: "Bruno Tavares", phone: "(11) 98220-5512", email: "bruno@bemestar.com.br" }, note: null },
  { id: "AS-1115", client: "Loja Bella Moda", cnpj: "82.004.773/0001-90", plan: "Sinalização Mensal", cycle: "mensal", since: "2024-04-10", method: "card", status: "cancelada", retry: null, nextAt: null, nextValue: 450, paid: 9, missed: 1, ltv: 4050, fiscal: "nfse", nf: "NFS-e 0980", os: null, fav: false, contact: { name: "Camila Dias", phone: "(11) 99500-3344", email: "camila@bellamoda.com.br" }, churn: "loja fechou", note: null }];


  const monthly = (s) => s.nextValue / (CYCLE_DIV[s.cycle] || 1);

  // Planos: os 4 do SUBS + 1 inativo. Contagem de assinaturas = ativas (não canceladas) do SUBS.
  const PLANOS_BASE = [
  { id: 11, name: "Sinalização Mensal", slug: "sinalizacao-mensal", curta: "Placas e banners trocados todo mês", completa: "", cycle: "mensal", dias: null, trial: 0, valor: 450, fiscal: "nfse", cfop: "", servico: "13.05", ativo: true },
  { id: 12, name: "Adesivos Frota Mensal", slug: "adesivos-frota-mensal", curta: "Reposição de adesivos de frota", completa: "", cycle: "mensal", dias: null, trial: 0, valor: 680, fiscal: "nfe", cfop: "5101", servico: "", ativo: true },
  { id: 13, name: "Impressão Trimestral", slug: "impressao-trimestral", curta: "Lote de impressos a cada 3 meses", completa: "", cycle: "trimestral", dias: null, trial: 0, valor: 1200, fiscal: "nfe", cfop: "5101", servico: "", ativo: true },
  { id: 14, name: "Manutenção Fachada Anual", slug: "manutencao-fachada-anual", curta: "Revisão anual de fachada e letreiro", completa: "", cycle: "anual", dias: null, trial: 0, valor: 3600, fiscal: "nfse", cfop: "", servico: "14.01", ativo: true },
  { id: 15, name: "Banner Promocional Semestral", slug: "banner-promocional-semestral", curta: "Campanha de vitrine a cada 6 meses", completa: "", cycle: "semestral", dias: null, trial: 7, valor: 900, fiscal: "none", cfop: "", servico: "", ativo: false }];

  const assinCount = (name) => SUBS.filter((s) => s.plan === name && s.status !== "cancelada").length;

  // Faturas (~10) coerentes com o SUBS. Vencimentos ao redor de HOJE.
  const FATURAS_BASE = [
  { id: 901, numero: "FAT-2026-0512", client: "Padaria Pão Quente", cnpj: "12.448.770/0001-22", plan: "Sinalização Mensal", valor: 450, venc: "2026-05-10", status: "paga", pagoEm: "2026-05-10", gateway: "inter" },
  { id: 902, numero: "FAT-2026-0518", client: "Auto Posto Trevo", cnpj: "08.911.332/0001-09", plan: "Adesivos Frota Mensal", valor: 680, venc: "2026-05-05", status: "atrasada", pagoEm: null, gateway: "inter" },
  { id: 903, numero: "FAT-2026-0521", client: "Academia CorpoForte", cnpj: "33.671.904/0001-50", plan: "Sinalização Mensal", valor: 450, venc: "2026-05-28", status: "atrasada", pagoEm: null, gateway: "asaas" },
  { id: 904, numero: "FAT-2026-0524", client: "Mercado União", cnpj: "55.118.022/0001-31", plan: "Adesivos Frota Mensal", valor: 680, venc: "2026-05-03", status: "atrasada", pagoEm: null, gateway: "c6" },
  { id: 905, numero: "FAT-2026-0527", client: "Farmácia Bem-Estar", cnpj: "71.992.150/0001-66", plan: "Sinalização Mensal", valor: 450, venc: "2026-05-22", status: "paga", pagoEm: "2026-05-22", gateway: "asaas" },
  { id: 906, numero: "FAT-2026-0530", client: "Restaurante Sabor Caseiro", cnpj: "21.005.118/0001-77", plan: "Impressão Trimestral", valor: 1200, venc: "2026-04-20", status: "paga", pagoEm: "2026-04-19", gateway: "inter" },
  { id: 907, numero: "FAT-2026-0601", client: "Farmácia Bem-Estar", cnpj: "71.992.150/0001-66", plan: "Sinalização Mensal", valor: 450, venc: "2026-06-01", status: "paga", pagoEm: "2026-06-01", gateway: "asaas" },
  { id: 908, numero: "FAT-2026-0602", client: "Padaria Pão Quente", cnpj: "12.448.770/0001-22", plan: "Sinalização Mensal", valor: 450, venc: "2026-06-10", status: "pendente", pagoEm: null, gateway: "inter" },
  { id: 909, numero: "FAT-2026-0603", client: "Clínica Vida", cnpj: "45.220.661/0001-18", plan: null, valor: 820, venc: "2026-06-15", status: "pendente", pagoEm: null, gateway: "inter" },
  { id: 910, numero: "FAT-2026-0604", client: "Restaurante Sabor Caseiro", cnpj: "21.005.118/0001-77", plan: "Impressão Trimestral", valor: 1200, venc: "2026-07-20", status: "pendente", pagoEm: null, gateway: "c6" },
  { id: 911, numero: "FAT-2026-0490", client: "Loja Bella Moda", cnpj: "82.004.773/0001-90", plan: "Sinalização Mensal", valor: 450, venc: "2026-04-10", status: "cancelada", pagoEm: null, gateway: "asaas" }];


  // ── átomos ─────────────────────────────────────────────────────
  function Avatar({ name, size = 30 }) {
    const h = hueFor(name);
    return <span className="cr-av" style={{ width: size, height: size, fontSize: size * 0.38, background: `linear-gradient(135deg, oklch(0.68 0.10 ${h}), oklch(0.50 0.13 ${h}))` }}>{initials(name)}</span>;
  }
  const ST_LABEL = { em_dia: "em dia", retentando: "retentando", falhou: "falhou", pausada: "pausada", cancelada: "cancelada" };
  function StatusPill({ status, retry }) {
    if (status === "retentando" && retry != null) {
      return <span className={"cr-pill " + status}>
        <span className="cr-retry-dots">{[0, 1, 2].map((i) => <i key={i} className={i < retry ? "on" : ""} />)}</span>
        retentando {retry}/3
      </span>;
    }
    if (status === "falhou" && retry != null) return <span className={"cr-pill " + status}>falhou {retry}×</span>;
    return <span className={"cr-pill " + status}>{ST_LABEL[status]}</span>;
  }
  const METHOD = { pix: "Pix", boleto: "Boleto", card: "Cartão" };
  const FISCAL = { nfe: { t: "NFe", l: "NFe · Nota Fiscal Eletrônica" }, nfse: { t: "NFS-e", l: "NFS-e · Nota de Serviços" }, none: { t: "Não emite", l: "Sem emissão fiscal" } };
  const GATEWAY = { inter: "Inter", c6: "C6", asaas: "Asaas" };

  // KPI simples (mesma casca do hero warm)
  function Kpi({ hero, label, value, hint, tone }) {
    return (
      <div className={"cr-stat" + (hero ? " cr-stat-hero" : "")}>
        <small>{label}</small>
        <b className={tone === "neg" ? "cr-num-neg" : ""}>{value}</b>
        {hint && <span className="cr-stat-hint">{hint}</span>}
      </div>);

  }

  // casca de drawer lateral reaproveitada pelos formulários
  function FormDrawer({ eyebrow, title, sub, onClose, foot, children }) {
    // listener registrado uma vez (onClose via ref): se ele re-registrasse a cada render, um
    // re-render no meio do mesmo Esc (outro listener mudando estado) o tiraria da fila do evento.
    const fechaRef = useRef(onClose);
    fechaRef.current = onClose;
    useEffect(() => {
      const k = (e) => {if (e.key === "Escape") fechaRef.current();};
      window.addEventListener("keydown", k);return () => window.removeEventListener("keydown", k);
    }, []);
    return (
      <>
        <div className="cr-drawer-ov" onClick={onClose} />
        <aside className="cr-drawer" role="dialog" aria-label={title}>
          <div className="cr-dwr-head">
            <div style={{ flex: 1, minWidth: 0 }}>
              {eyebrow && <div className="cr-eyebrow">{eyebrow}</div>}
              <h3>{title}</h3>
              {sub && <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 2 }}>{sub}</div>}
            </div>
            <button className="cr-x" onClick={onClose} aria-label="Fechar"><I.x size={16} /></button>
          </div>
          <div className="cr-dwr-body">{children}</div>
          <div className="cr-dwr-foot">{foot}</div>
        </aside>
      </>);

  }

  function Field({ label, req, hint, children }) {
    return <label style={{ display: "block" }}><span style={LBL}>{label}{req && <Req />}</span>{children}{hint && <div style={HINT}>{hint}</div>}</label>;
  }

  // diálogo de confirmação centrado (overlay do drawer + cartão)
  function Confirm({ title, children, onClose, foot }) {
    return (
      <>
        <div className="cr-drawer-ov" onClick={onClose} />
        <div role="alertdialog" aria-label={title} style={{ position: "fixed", zIndex: 60, top: "50%", left: "50%", transform: "translate(-50%,-50%)", width: 420, maxWidth: "92vw", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 10, boxShadow: "0 18px 48px rgba(40,30,20,.18)", padding: 18 }}>
          <h3 style={{ margin: 0, fontSize: 15, fontWeight: 600, color: "var(--text)" }}>{title}</h3>
          <div style={{ marginTop: 10, fontSize: 13, color: "var(--text-dim)", lineHeight: 1.5 }}>{children}</div>
          <div style={{ marginTop: 16, display: "flex", justifyContent: "flex-end", gap: 8 }}>{foot}</div>
        </div>
      </>);

  }

  // ══════════════════════════════════════════════════════════════
  // ASSINATURAS  (Page RecurringBilling/Index)
  // ══════════════════════════════════════════════════════════════
  function KpiStrip({ subs }) {
    const ativos = subs.filter((s) => s.status !== "cancelada");
    const mrr = ativos.reduce((a, s) => a + monthly(s), 0);
    const churn = subs.filter((s) => s.status === "cancelada").length;
    const churnRate = subs.length ? Math.round(churn / subs.length * 100) : 0;
    const prox = ativos.filter((s) => s.nextAt && daysFromToday(s.nextAt) >= 0).sort((a, b) => a.nextAt.localeCompare(b.nextAt));
    const primeira = prox[0];
    const doDia = primeira ? prox.filter((s) => s.nextAt === primeira.nextAt) : [];
    const falhas = subs.filter((s) => s.status === "falhou" || s.status === "retentando");
    const falhaVal = falhas.reduce((a, s) => a + s.nextValue, 0);
    return (
      <div className="cr-stats">
        <div className="cr-stat cr-stat-hero">
          <small>MRR · receita recorrente</small>
          <b>{BRL(mrr)}</b>
          <span className="cr-stat-hint"><b className="cr-num-pos">{ativos.length}</b> assinaturas ativas · ticket médio <b>{BRL(mrr / Math.max(ativos.length, 1))}</b></span>
        </div>
        <Kpi label="Churn este mês" value={`${churn} ${churn === 1 ? "cancelamento" : "cancelamentos"}`} hint={`taxa ${churnRate}%`} />
        <Kpi label="Próxima cobrança" value={primeira ? nextLabel(primeira.nextAt) : "—"} hint={`${BRL(doDia.reduce((a, s) => a + s.nextValue, 0))} · ${doDia.length} ${doDia.length === 1 ? "cobrança" : "cobranças"}`} />
        <Kpi label="Retentado falhos" value={falhas.length} tone={falhas.length ? "neg" : ""} hint={`requer ação · ${BRL(falhaVal)} a recuperar`} />
      </div>);

  }

  function SubRow({ sub, active, onOpen, fav, onFav }) {
    return (
      <div className={"cr-row" + (active ? " on" : "")} onClick={onOpen}>
        <button className={"cr-star" + (fav ? " on" : "")} title={fav ? "Desfavoritar" : "Favoritar"} onClick={(e) => {e.stopPropagation();onFav();}}>
          {fav ? <I.starFill size={14} /> : <I.star size={14} />}
        </button>
        <Avatar name={sub.client} />
        <div className="cr-row-main">
          <div className="cr-row-title">{sub.client}</div>
          <div className="cr-row-sub">{sub.plan} · {CYCLE[sub.cycle]} · desde {sinceLabel(sub.since)}{sub.nextAt ? ` · próx. ${dateBR(sub.nextAt)}` : ""}</div>
        </div>
        <div className="cr-row-right">
          <StatusPill status={sub.status} retry={sub.retry} />
          {sub.status !== "cancelada" && sub.status !== "pausada" &&
          <span className="cr-row-val">{METHOD[sub.method]} · {BRL(sub.nextValue)}</span>
          }
        </div>
      </div>);

  }

  function PaymentHeat({ paid, missed }) {
    const cells = useMemo(() => {
      return Array.from({ length: 12 }, (_, i) => {
        if (i === 11) return "future";
        const pr = (i + 1) / 11;
        if (i < 3 && missed > 0 && i + 1 <= Math.round(missed * pr * 3)) return "missed";
        return i + 1 <= Math.round(paid * pr / (paid / 11 || 1)) ? "paid" : i < 8 ? "paid" : "future";
      });
    }, [paid, missed]);
    const months = useMemo(() => {const out = [];for (let i = 11; i >= 0; i--) out.push(MES[(HOJE.getMonth() - i + 12) % 12]);return out;}, []);
    return (
      <div className="cr-card">
        <div className="cr-blk-label">Histórico de pagamentos</div>
        <div className="cr-heat">{cells.map((c, i) => <div className="cr-heat-c" key={i} title={months[i]}><i className={c} /><small>{months[i][0]}</small></div>)}</div>
        <div className="cr-heat-legend">
          <span><i style={{ background: "oklch(0.80 0.11 145)" }} /> pago ({paid})</span>
          <span><i style={{ background: "oklch(0.74 0.13 28)" }} /> falhou ({missed})</span>
          <span><i style={{ background: "color-mix(in oklab,var(--text) 8%,transparent)" }} /> futuro</span>
        </div>
      </div>);

  }

  function Drawer({ sub, onClose, onEdit }) {
    const [tab, setTab] = useState("detalhes");
    const [notas, setNotas] = useState([]);
    const [nota, setNota] = useState("");
    useEffect(() => {setTab("detalhes");setNotas([]);setNota("");}, [sub && sub.id]);
    if (!sub) return null;
    const inactive = sub.status === "cancelada" || sub.status === "pausada";
    const f = FISCAL[sub.fiscal] || FISCAL.none;
    const tlEvents = [
    ...notas,
    { kind: "cobrança", dot: "oklch(0.72 0.12 145)", by: "sistema", when: dateBR(sub.nextAt) || "—", body: sub.status === "falhou" ? "Cobrança recusada pelo banco" : "Próxima cobrança agendada" },
    { kind: "nf", dot: "oklch(0.62 0.13 295)", by: "sistema", when: "12 mai", body: `${f.t} emitida e enviada ao cliente` },
    { kind: "criou", dot: "var(--text-mute)", by: "Eliana", when: sinceLabel(sub.since), body: "Assinatura criada" }];
    const anotar = () => {
      const t = nota.trim();if (!t) return;
      setNotas((p) => [{ kind: "nota", dot: "var(--accent)", by: "você", when: "agora", body: t }, ...p]);setNota("");
    };

    return (
      <>
        <div className="cr-drawer-ov" onClick={onClose} />
        <aside className="cr-drawer">
          <div className="cr-dwr-head">
            <Avatar name={sub.client} size={38} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div className="cr-eyebrow">Assinatura · {sub.id}</div>
              <h3>{sub.client}</h3>
            </div>
            <StatusPill status={sub.status} retry={sub.retry} />
            <button className="cr-act" title="Extrato da assinatura em PDF"><I.doc size={12} /> PDF</button>
            <button className="cr-x" onClick={onClose}><I.x size={16} /></button>
          </div>

          <window.CliTabs className="cr-dwr-tabs" ariaLabel="Abas da assinatura" pad={18} size="sm"
            active={tab} onChange={setTab}
            tabs={[{ key: "detalhes", label: "Detalhes" }, { key: "ia", label: "✦ IA" }]} />

          <div className="cr-dwr-body">
            {tab === "detalhes" && <>
              {window.OiEtapaPainel && <window.OiEtapaPainel modo="leitura" proc="contrato" docId={sub.id}
                estado={{ em_dia: "Ativo", retentando: "Ativo", falhou: "Suspenso", pausada: "Suspenso", cancelada: "Cancelado" }[sub.status] || "Ativo"}
                bloqueios={sub.status === "falhou" ? { "Reativar": sub.missed + " cobranças falharam — regularize ou renegocie antes" } : {}} />}
              {!inactive &&
              <div className={"cr-next-card " + sub.status}>
                  <div className="cr-blk-label">{sub.status === "falhou" ? "Ação manual" : "Próxima cobrança"}</div>
                  <div className="cr-next-row">
                    <div>
                      <div className="cr-next-when">{nextLabel(sub.nextAt)}</div>
                      <div className="cr-next-meta">{dateBR(sub.nextAt)} · ciclo {CYCLE[sub.cycle]}</div>
                    </div>
                    <div>
                      <div className="cr-next-val">{BRL(sub.nextValue)}</div>
                      <div className="cr-next-method">{METHOD[sub.method]}</div>
                    </div>
                  </div>
                </div>
              }

              <dl className="cr-kv">
                <div><dt>Plano</dt><dd>{sub.plan}</dd></div>
                <div><dt>Ciclo</dt><dd>{CYCLE[sub.cycle]}</dd></div>
                <div><dt>Cliente desde</dt><dd>{sinceLabel(sub.since)}</dd></div>
                <div><dt>CNPJ</dt><dd className="mono">{sub.cnpj}</dd></div>
                <div><dt>Cobranças pagas</dt><dd className="mono">{sub.paid}</dd></div>
                <div><dt>Falhas</dt><dd className="mono" style={sub.missed ? { color: "oklch(0.50 0.16 25)", fontWeight: 600 } : null}>{sub.missed}</dd></div>
                <div><dt>LTV acumulado</dt><dd className="mono">{BRL(sub.ltv)}</dd></div>
                <div><dt>OS recente</dt><dd className="mono">{sub.os || "—"}</dd></div>
                <div style={{ gridColumn: "1/-1" }}><dt>Contato</dt><dd>{sub.contact.name} · <span style={{ fontFamily: "ui-monospace,monospace", fontSize: 12 }}>{sub.contact.phone}</span> · {sub.contact.email}</dd></div>
                {sub.churn && <><div className="cr-kv-sep" /><div style={{ gridColumn: "1/-1" }}><dt>Motivo do cancelamento</dt><dd>{sub.churn}</dd></div></>}
              </dl>

              {sub.note &&
              <div className="cr-card" style={{ background: "oklch(0.97 0.03 78)", borderColor: "oklch(0.89 0.06 78)" }}>
                  <div className="cr-blk-label" style={{ color: "oklch(0.47 0.10 65)" }}>Nota pinada</div>
                  <div style={{ fontSize: 12.5, color: "oklch(0.40 0.08 60)", marginTop: 5 }}>{sub.note}</div>
                </div>
              }

              <PaymentHeat paid={sub.paid} missed={sub.missed} />

              <div className="cr-card">
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                  <div>
                    <span className={"cr-fiscal-badge " + sub.fiscal}>{f.t}</span>
                    <div style={{ fontSize: 10.5, color: "var(--text-mute)", marginTop: 5 }}>{sub.nf ? `${f.l} · última ${sub.nf}` : f.l}</div>
                  </div>
                  {sub.nf && <button className="cr-act" title={`Reenviar ${sub.nf} por e-mail/WhatsApp`}><I.send size={12} /> Reenviar</button>}
                </div>
              </div>

              <div>
                <div className="cr-blk-label">Notas &amp; eventos</div>
                <div style={{ display: "flex", gap: 6, margin: "8px 0 4px" }}>
                  <input style={FIELD} value={nota} onChange={(e) => setNota(e.target.value)} onKeyDown={(e) => {if (e.key === "Enter") anotar();}} placeholder="Anotar internamente…" aria-label="Anotar internamente" />
                  <button className="cr-act" onClick={anotar} disabled={!nota.trim()}>Anotar</button>
                </div>
                <div className="cr-tl">
                  {tlEvents.map((e, i) =>
                  <div className="cr-tl-item" key={i}>
                      <span className="cr-tl-dot" style={{ background: e.dot }} />
                      <div style={{ flex: 1 }}>
                        <div className="cr-tl-meta"><b>{e.kind}</b> · {e.by} · {e.when}</div>
                        <div className="cr-tl-body">{e.body}</div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </>}

            {tab === "ia" &&
            <div className="cr-ai-card">
                <div className="cr-ai-head">✦ Jana sugere</div>
                <div className="cr-ai-body">
                  {sub.status === "falhou" ?
                `${sub.contact.name} já falhou ${sub.missed}× neste contrato. Antes de suspender, vale um lembrete de Pix com a 2ª via — clientes de ${sub.plan.toLowerCase()} costumam regularizar em 48h quando avisados.` :
                sub.status === "retentando" ?
                `Boleto na ${sub.retry}ª tentativa. ${sub.client} tem ${sub.paid} pagamentos em dia e LTV de ${BRL(sub.ltv)} — é um bom pagador. Reenviar a 2ª via costuma resolver.` :
                `${sub.client} está em dia há ${sub.paid} ciclos. Boa hora pra oferecer um upgrade do "${sub.plan}" pro pacote anual (desconto + reduz churn).`}
                </div>
                <div className="cr-ai-actions">
                  <button className="cr-ai-chip">Gerar lembrete</button>
                  <button className="cr-ai-chip">Resumir contrato</button>
                  <button className="cr-ai-chip">Prever churn</button>
                </div>
              </div>
            }
          </div>

          <div className="cr-dwr-foot">
            {sub.status === "em_dia" && <button className="cr-act"><I.clock size={13} /> Pausar</button>}
            {(sub.status === "retentando" || sub.status === "falhou") && <>
              <button className="cr-act primary"><I.refresh size={13} /> Diagnosticar</button>
              <button className="cr-act"><I.clock size={13} /> Pausar</button>
            </>}
            {sub.status === "pausada" && <button className="cr-act primary"><I.refresh size={13} /> Reativar</button>}
            {sub.status !== "cancelada" && <button className="cr-act" onClick={onEdit}><I.pencil size={13} /> Editar</button>}
            <span className="cr-act-spacer" />
            {!inactive && <button className="cr-act danger"><I.x size={13} /> Cancelar</button>}
          </div>
        </aside>
      </>);

  }

  function EditarCobranca({ sub, onClose }) {
    const [valor, setValor] = useState(String(sub.nextValue));
    const [ciclo, setCiclo] = useState(sub.cycle);
    const [forma, setForma] = useState(sub.method);
    return (
      <FormDrawer eyebrow={"Assinatura · " + sub.id} title="Editar cobrança" sub={`${sub.client} · atualizar valor, ciclo ou forma de pagamento`} onClose={onClose}
      foot={<><span className="cr-act-spacer" /><button className="cr-act" onClick={onClose}>Cancelar</button><button className="cr-act primary" onClick={onClose}>Salvar alterações</button></>}>
        <Field label="Valor (R$)" req><input style={FIELD} inputMode="decimal" value={valor} onChange={(e) => setValor(e.target.value)} /></Field>
        <Field label="Ciclo" req>
          <select style={FIELD} value={ciclo} onChange={(e) => setCiclo(e.target.value)}>
            <option value="mensal">Mensal</option><option value="trimestral">Trimestral</option><option value="semestral">Semestral</option><option value="anual">Anual</option>
          </select>
        </Field>
        <Field label="Forma de pagamento" req>
          <div style={{ display: "flex", gap: 6 }}>
            {[["boleto", "Boleto"], ["pix", "Pix"], ["card", "Cartão"]].map(([k, l]) => <button key={k} type="button" style={CHIP(forma === k)} onClick={() => setForma(k)}>{l}</button>)}
          </div>
        </Field>
      </FormDrawer>);

  }

  function NovaAssinatura({ onClose }) {
    const [cli, setCli] = useState("");
    const [plano, setPlano] = useState("");
    const [valor, setValor] = useState("");
    const [ciclo, setCiclo] = useState("mensal");
    const [prox, setProx] = useState("2026-06-10");
    const [gw, setGw] = useState("inter");
    const [forma, setForma] = useState("boleto");
    const [desc, setDesc] = useState("");
    const sugestoes = cli.trim() ? SUBS.map((s) => s.client).filter((n, i, a) => a.indexOf(n) === i && n.toLowerCase().includes(cli.toLowerCase())).slice(0, 4) : [];
    const pickPlano = (id) => {
      setPlano(id);const p = PLANOS_BASE.find((x) => String(x.id) === id);
      if (p) {setValor(String(p.valor));setCiclo(p.cycle);}
    };
    return (
      <FormDrawer eyebrow="Assinatura" title="Nova assinatura" sub="Cadastrar cobrança recorrente para um cliente" onClose={onClose}
      foot={<><span className="cr-act-spacer" /><button className="cr-act" onClick={onClose}>Cancelar</button><button className="cr-act primary" onClick={onClose} disabled={!cli.trim() || !valor}>Criar assinatura</button></>}>
        <Field label="Cliente" req>
          <div className="cr-search" style={{ padding: "5px 10px" }}>
            <I.search size={14} style={{ color: "var(--text-mute)" }} />
            <input value={cli} onChange={(e) => setCli(e.target.value)} placeholder="Buscar cliente por nome ou CNPJ" />
          </div>
          {sugestoes.length > 0 && <div style={{ display: "flex", flexWrap: "wrap", gap: 6, marginTop: 6 }}>{sugestoes.map((n) => <button key={n} type="button" style={CHIP(cli === n)} onClick={() => setCli(n)}>{n}</button>)}</div>}
        </Field>
        <Field label="Plano" hint="Opcional — escolher um plano preenche valor e ciclo.">
          <select style={FIELD} value={plano} onChange={(e) => pickPlano(e.target.value)}>
            <option value="">Sem plano (avulso)</option>
            {PLANOS_BASE.filter((p) => p.ativo).map((p) => <option key={p.id} value={p.id}>{p.name} · {BRL(p.valor)}</option>)}
          </select>
        </Field>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          <Field label="Valor (R$)" req><input style={FIELD} inputMode="decimal" value={valor} onChange={(e) => setValor(e.target.value)} placeholder="0,00" /></Field>
          <Field label="Ciclo" req>
            <select style={FIELD} value={ciclo} onChange={(e) => setCiclo(e.target.value)}>
              <option value="mensal">Mensal</option><option value="trimestral">Trimestral</option><option value="semestral">Semestral</option><option value="anual">Anual</option>
            </select>
          </Field>
          <Field label="Próxima cobrança" req><input style={FIELD} type="date" value={prox} onChange={(e) => setProx(e.target.value)} /></Field>
          <Field label="Gateway" req>
            <select style={FIELD} value={gw} onChange={(e) => setGw(e.target.value)}>
              <option value="inter">Banco Inter</option><option value="asaas">Asaas</option>
            </select>
          </Field>
        </div>
        <Field label="Forma de pagamento" req>
          <div style={{ display: "flex", gap: 6 }}>
            {[["boleto", "Boleto"], ["pix", "Pix"], ["card", "Cartão"]].map(([k, l]) => <button key={k} type="button" style={CHIP(forma === k)} onClick={() => setForma(k)}>{l}</button>)}
          </div>
        </Field>
        <Field label="Descrição" hint="Opcional — aparece no boleto/Pix."><textarea style={AREA} value={desc} onChange={(e) => setDesc(e.target.value)} /></Field>
      </FormDrawer>);

  }

  // Campos de data próprios das assinaturas (espelha o PeriodBar do Financeiro).
  const PB_FIELDS = [
  { id: "prox", label: "Próxima cobrança" },
  { id: "inicio", label: "Início" }];

  const pbDate = (s, field) => {
    const iso = field === "inicio" ? s.since : s.nextAt;
    return iso ? new Date(iso + "T12:00") : null;
  };
  const ST_FILTROS = [
  { key: "todas", label: "Todas" },
  { key: "em_dia", label: "Em dia" },
  { key: "retentando", label: "Retentando" },
  { key: "falhou", label: "Falharam" },
  { key: "pausada", label: "Pausadas" },
  { key: "cancelada", label: "Canceladas" }];

  const DT_FILTROS = [
  { key: "any", label: "Qualquer data" },
  { key: "today", label: "Hoje" },
  { key: "tomorrow", label: "Amanhã" },
  { key: "week", label: "Esta semana" },
  { key: "month", label: "Próx. 30 dias" },
  { key: "custom", label: "Personalizado" }];

  function passaData(s, mode, de, ate) {
    if (mode === "any") return true;
    if (!s.nextAt) return false;
    const dd = daysFromToday(s.nextAt);
    if (mode === "today") return dd === 0;
    if (mode === "tomorrow") return dd === 1;
    if (mode === "week") return dd >= 0 && dd <= 6;
    if (mode === "month") return dd >= 0 && dd <= 30;
    if (mode === "custom") return (!de || s.nextAt >= de) && (!ate || s.nextAt <= ate);
    return true;
  }

  function Assinaturas({ onNova }) {
    const [q, setQ] = useState("");
    const [openId, setOpenId] = useState(null);
    const [editId, setEditId] = useState(null);
    const [favs, setFavs] = useState(() => new Set(SUBS.filter((s) => s.fav).map((s) => s.id)));
    const [soFavs, setSoFavs] = useState(false);
    const [st, setSt] = useState("todas");
    const [planoF, setPlanoF] = useState(null);
    const [dt, setDt] = useState("any");
    const [de, setDe] = useState("");
    const [ate, setAte] = useState("");
    const [dateField, setDateField] = useState("prox");
    const [periodMode, setPeriodMode] = useState("tudo");
    const [anchor, setAnchor] = useState(() => new Date(HOJE));
    const searchRef = useRef(null);
    const PeriodBar = window.FinPeriodBar;
    const rows = useMemo(() => {
      const t = q.trim().toLowerCase();
      const win = window.finPeriodWindow ? window.finPeriodWindow(periodMode, anchor) : null;
      return SUBS.filter((s) => {
        if (t && !(s.client + " " + s.cnpj + " " + s.plan + " " + s.id + " " + (s.os || "")).toLowerCase().includes(t)) return false;
        if (st !== "todas" && s.status !== st) return false;
        if (soFavs && !favs.has(s.id)) return false;
        if (planoF && s.plan !== planoF) return false;
        if (!passaData(s, dt, de, ate)) return false;
        if (win) {const d = pbDate(s, dateField);if (!d || d < win[0] || d >= win[1]) return false;}
        return true;
      });
    }, [q, st, soFavs, favs, planoF, dt, de, ate, dateField, periodMode, anchor]);
    useEffect(() => {
      const onKey = (e) => {
        if (e.key === "/" && document.activeElement !== searchRef.current) {e.preventDefault();searchRef.current && searchRef.current.focus();} else
        if (e.key === "Escape") setOpenId(null);
      };
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, []);
    const open = rows.find((s) => s.id === openId) || null;
    const editing = SUBS.find((s) => s.id === editId) || null;
    const toggleFav = (id) => setFavs((p) => {const n = new Set(p);n.has(id) ? n.delete(id) : n.add(id);return n;});
    const ativosAll = SUBS.filter((s) => s.status !== "cancelada");
    const ativosF = rows.filter((s) => s.status !== "cancelada");
    const mrrF = ativosF.reduce((a, s) => a + monthly(s), 0);
    const porPlano = PLANOS_BASE.filter((p) => p.ativo).map((p) => ({ ...p, n: assinCount(p.name) }));
    const vazioTotal = q.trim() === "" && st === "todas" && !soFavs && !planoF && dt === "any";
    return (
      <>
        <KpiStrip subs={SUBS} />
        {PeriodBar && <PeriodBar dateField={dateField} setDateField={setDateField}
        period={periodMode} setPeriod={setPeriodMode}
        anchor={anchor} setAnchor={setAnchor}
        count={rows.length} fields={PB_FIELDS} countLabel="assinaturas" />}
        <div style={{ display: "grid", gridTemplateColumns: "220px minmax(0,1fr)", gap: 0, alignItems: "start" }}>
          <aside className="cr-card" style={{ margin: "16px 0 24px 24px", background: "var(--surface)", display: "flex", flexDirection: "column", gap: 14 }} aria-label="Filtros">
            <button type="button" style={{ ...CHIP(soFavs), borderRadius: 7, justifyContent: "space-between" }} onClick={() => setSoFavs((v) => !v)}>
              <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>{soFavs ? <I.starFill size={13} /> : <I.star size={13} />} {soFavs ? "Mostrando favoritos" : "Mostrar só favoritos"}</span>
              <span style={MONO}>{favs.size}</span>
            </button>
            <div>
              <div className="cr-blk-label" style={{ marginBottom: 6 }}>Status</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                {ST_FILTROS.map((f) => {
                  const n = f.key === "todas" ? SUBS.length : SUBS.filter((s) => s.status === f.key).length;
                  return <button key={f.key} type="button" onClick={() => setSt(f.key)} style={{ display: "flex", justifyContent: "space-between", padding: "5px 8px", fontSize: 12.5, borderRadius: 6, border: "none", cursor: "pointer", background: st === f.key ? "var(--accent-soft)" : "transparent", color: st === f.key ? "var(--accent)" : "var(--text-dim)", fontWeight: st === f.key ? 600 : 400 }}><span>{f.label}</span><span style={MONO}>{n}</span></button>;
                })}
              </div>
            </div>
            <div>
              <div className="cr-blk-label" style={{ marginBottom: 6 }}>Próxima cobrança</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                {DT_FILTROS.map((f) => <button key={f.key} type="button" onClick={() => setDt(f.key)} style={{ textAlign: "left", padding: "5px 8px", fontSize: 12.5, borderRadius: 6, border: "none", cursor: "pointer", background: dt === f.key ? "var(--accent-soft)" : "transparent", color: dt === f.key ? "var(--accent)" : "var(--text-dim)", fontWeight: dt === f.key ? 600 : 400 }}>{f.label}</button>)}
              </div>
              {dt === "custom" &&
              <div style={{ display: "grid", gap: 6, marginTop: 6 }}>
                  <Field label="De"><input style={FIELD} type="date" value={de} onChange={(e) => setDe(e.target.value)} aria-label="Próxima cobrança de" /></Field>
                  <Field label="Até"><input style={FIELD} type="date" value={ate} onChange={(e) => setAte(e.target.value)} aria-label="Próxima cobrança até" /></Field>
                </div>
              }
            </div>
            <div>
              <div className="cr-blk-label" style={{ marginBottom: 6 }}>Por plano</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                {porPlano.map((p) => <button key={p.id} type="button" onClick={() => setPlanoF(planoF === p.name ? null : p.name)} style={{ textAlign: "left", padding: "5px 8px", borderRadius: 6, border: "none", cursor: "pointer", background: planoF === p.name ? "var(--accent-soft)" : "transparent", color: planoF === p.name ? "var(--accent)" : "var(--text-dim)" }}>
                  <div style={{ fontSize: 12.5, fontWeight: 500 }}>{p.name}</div>
                  <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{BRL(p.valor)} · {p.n} {p.n === 1 ? "ativa" : "ativas"}</div>
                </button>)}
              </div>
            </div>
            <div style={{ borderTop: "1px solid var(--border)", paddingTop: 10 }}>
              <div className="cr-blk-label">MRR filtrado</div>
              <div style={{ fontSize: 16, fontWeight: 700, color: "var(--text)", marginTop: 2, fontVariantNumeric: "tabular-nums" }}>{BRL(mrrF)}</div>
              <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{ativosF.length} ativ. de {ativosAll.length}</div>
            </div>
          </aside>
          <div className="cr-list-wrap">
            <div className="cr-list-head">
              <div className="cr-search">
                <I.search size={14} style={{ color: "var(--text-mute)" }} />
                <input ref={searchRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar (/) — cliente, CNPJ, OS" />
                <kbd>/</kbd>
              </div>
              <span className="cr-list-count">{rows.length} de {SUBS.length}</span>
            </div>
            {rows.length === 0 ?
            <div style={{ padding: 40, textAlign: "center" }}>
                <div style={{ fontSize: 14, fontWeight: 600, color: "var(--text-dim)" }}>Nada por aqui.</div>
                <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 4 }}>{vazioTotal ? "Nenhuma assinatura cadastrada ainda." : "Nenhuma assinatura com este filtro + busca."}</div>
                {vazioTotal && <button className="cr-primary" style={{ marginTop: 12 }} onClick={onNova}><I.plus size={14} /> Nova assinatura</button>}
              </div> :
            rows.map((s) => <SubRow key={s.id} sub={s} active={openId === s.id} fav={favs.has(s.id)} onFav={() => toggleFav(s.id)} onOpen={() => setOpenId(s.id)} />)}
          </div>
        </div>
        {open && !editing && <Drawer sub={open} onClose={() => setOpenId(null)} onEdit={() => setEditId(open.id)} />}
        {editing && <EditarCobranca sub={editing} onClose={() => setEditId(null)} />}
      </>);

  }

  // ══════════════════════════════════════════════════════════════
  // PLANOS  (Pages RecurringBilling/Planos/Index · Create · Edit)
  // ══════════════════════════════════════════════════════════════
  const CICLO_OPC = [["mensal", "Mensal"], ["trimestral", "Trimestral"], ["semestral", "Semestral"], ["anual", "Anual"], ["custom", "Customizado (dias)"]];
  const FISCAL_OPC = [["none", "Não emite nota fiscal"], ["nfe", "NFe (produto)"], ["nfse", "NFS-e (serviço)"]];

  function PlanoForm({ plano, onClose }) {
    const novo = !plano;
    const [d, setD] = useState(() => plano ? { ...plano } : { name: "", slug: "", curta: "", completa: "", cycle: "mensal", dias: "", trial: 0, valor: "", fiscal: "none", cfop: "", servico: "", ativo: true });
    const set = (k, v) => setD((p) => ({ ...p, [k]: v }));
    const slugMudou = !novo && d.slug !== plano.slug;
    useEffect(() => {
      const k = (e) => {if ((e.metaKey || e.ctrlKey) && e.key === "Enter") onClose();};
      window.addEventListener("keydown", k);return () => window.removeEventListener("keydown", k);
    }, [onClose]);
    const SecT = ({ children }) => <div className="cr-blk-label" style={{ marginBottom: 8 }}>{children}</div>;
    return (
      <FormDrawer eyebrow="Plano" title={novo ? "Novo plano" : "Editar plano"} sub={novo ? "Cadastrar plano recorrente" : `${plano.name} · #${plano.id}`} onClose={onClose}
      foot={<><span className="cr-act-spacer" /><button className="cr-act" onClick={onClose}>Cancelar <kbd style={{ ...MONO, fontSize: 10 }}>Esc</kbd></button><button className="cr-act primary" onClick={onClose} disabled={!d.name || !d.valor}>{novo ? "Salvar plano" : "Salvar alterações"} <kbd style={{ ...MONO, fontSize: 10 }}>⌘↵</kbd></button></>}>
        <div className="cr-card" style={{ display: "grid", gap: 12 }}>
          <SecT>Identificação</SecT>
          <Field label="Nome do plano" req><input style={FIELD} value={d.name} onChange={(e) => set("name", e.target.value)} placeholder="Ex: Sinalização Mensal" /></Field>
          <Field label="Slug" hint={slugMudou ? "⚠️ alterar o slug pode quebrar links e integrações que usam o plano antigo" : "Gerado do nome se ficar vazio."}>
            <input style={{ ...FIELD, ...MONO, borderColor: slugMudou ? "oklch(0.78 0.12 70)" : "var(--border)" }} value={d.slug} onChange={(e) => set("slug", e.target.value.toLowerCase())} />
          </Field>
          <Field label="Descrição curta"><input style={FIELD} value={d.curta} onChange={(e) => set("curta", e.target.value)} /></Field>
          <Field label="Descrição completa"><textarea style={AREA} value={d.completa} onChange={(e) => set("completa", e.target.value)} /></Field>
        </div>
        <div className="cr-card" style={{ display: "grid", gap: 12 }}>
          <SecT>Cobrança</SecT>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
            <Field label="Valor (R$)" req><input style={FIELD} inputMode="decimal" value={d.valor} onChange={(e) => set("valor", e.target.value)} placeholder="0,00" /></Field>
            <Field label="Ciclo" req>
              <select style={FIELD} value={d.cycle} onChange={(e) => set("cycle", e.target.value)}>{CICLO_OPC.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
            </Field>
            {d.cycle === "custom" && <Field label="Ciclo (dias)" req hint="Entre 1 e 365."><input style={FIELD} type="number" min={1} max={365} value={d.dias || ""} onChange={(e) => set("dias", e.target.value)} /></Field>}
            <Field label="Trial (dias)" hint="Entre 0 e 90."><input style={FIELD} type="number" min={0} max={90} value={d.trial} onChange={(e) => set("trial", e.target.value)} /></Field>
          </div>
          <label style={{ display: "flex", alignItems: "center", gap: 8, fontSize: 13, color: "var(--text-dim)" }}>
            <input type="checkbox" checked={d.ativo} onChange={(e) => set("ativo", e.target.checked)} /> Plano ativo (disponível pra novas assinaturas)
          </label>
        </div>
        <div className="cr-card" style={{ display: "grid", gap: 12 }}>
          <SecT>Emissão fiscal</SecT>
          <Field label="Tipo"><select style={FIELD} value={d.fiscal} onChange={(e) => set("fiscal", e.target.value)}>{FISCAL_OPC.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></Field>
          {d.fiscal === "nfe" && <Field label="CFOP" hint="Ex: 5101 (venda de produção própria)."><input style={{ ...FIELD, ...MONO }} value={d.cfop} onChange={(e) => set("cfop", e.target.value)} /></Field>}
          {d.fiscal === "nfse" && <Field label="Código de serviço" hint="Item da lista de serviços (LC 116/2003)."><input style={{ ...FIELD, ...MONO }} value={d.servico} onChange={(e) => set("servico", e.target.value)} /></Field>}
        </div>
      </FormDrawer>);

  }

  function Planos({ formPlano, setFormPlano }) {
    const [q, setQ] = useState("");
    const [aplicada, setAplicada] = useState("");
    const [excluir, setExcluir] = useState(null);
    const searchRef = useRef(null);
    useEffect(() => {
      const onKey = (e) => {if (e.key === "/" && document.activeElement !== searchRef.current) {e.preventDefault();searchRef.current && searchRef.current.focus();}};
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, []);
    const planos = PLANOS_BASE.map((p) => ({ ...p, n: assinCount(p.name) }));
    const ativos = planos.filter((p) => p.ativo);
    const mrrPot = ativos.reduce((a, p) => a + p.valor / (CYCLE_DIV[p.cycle] || 1) * p.n, 0);
    const totalAssin = ativos.reduce((a, p) => a + p.n, 0);
    const top = [...planos].sort((a, b) => b.n - a.n)[0];
    const ciclos = [["mensal", "Mensal"], ["trimestral", "Trimestral"], ["semestral", "Semestral"], ["anual", "Anual"], ["custom", "Customizado"]].map(([k, l]) => ({ k, l, n: planos.filter((p) => p.cycle === k).length }));
    const rows = planos.filter((p) => {const t = aplicada.trim().toLowerCase();return !t || (p.name + " " + p.slug).toLowerCase().includes(t);});
    return (
      <>
        <div className="cr-stats">
          <div className="cr-stat cr-stat-hero">
            <small>Ticket médio · MRR potencial</small>
            <b>{BRL(mrrPot / Math.max(totalAssin, 1))}</b>
            <span className="cr-stat-hint">MRR total <b>{BRL(mrrPot)}</b></span>
          </div>
          <Kpi label="Total planos" value={planos.length} hint={`${planos.length - ativos.length} ${planos.length - ativos.length === 1 ? "inativo" : "inativos"}`} />
          <Kpi label="Total ativos" value={ativos.length} hint="disponíveis pra novas assinaturas" />
          <Kpi label="Plano top vendido" value={top && top.n ? top.name : "—"} hint={top && top.n ? `${top.n} assin. · ${BRL(top.valor)}` : "sem assinaturas ativas"} />
        </div>
        <div className="cr-card" style={{ ...SEC, background: "var(--surface)" }}>
          <div className="cr-blk-label" style={{ marginBottom: 8 }}>Distribuição por ciclo</div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(5,1fr)", gap: 8 }}>
            {ciclos.map((c) =>
            <div key={c.k} style={{ padding: "8px 10px", borderRadius: 7, background: "color-mix(in oklab,var(--text) 4%,transparent)" }}>
                <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{c.l}</div>
                <div style={{ fontSize: 16, fontWeight: 700, color: c.n ? "var(--text)" : "var(--text-mute)", fontVariantNumeric: "tabular-nums" }}>{c.n}</div>
              </div>
            )}
          </div>
        </div>
        <div className="cr-list-wrap">
          <form className="cr-list-head" onSubmit={(e) => {e.preventDefault();setAplicada(q);}}>
            <div className="cr-search">
              <I.search size={14} style={{ color: "var(--text-mute)" }} />
              <input ref={searchRef} value={q} onChange={(e) => {setQ(e.target.value);setAplicada(e.target.value);}} placeholder="Buscar (/) — nome ou slug" />
              <kbd>/</kbd>
            </div>
            <button type="submit" className="cr-act">Buscar</button>
          </form>
          {PLANOS_BASE.length === 0 || rows.length === 0 ?
          <div style={{ padding: 40, textAlign: "center" }}>
              <div style={{ fontSize: 15, fontWeight: 600, color: "var(--text-dim)" }}>Nenhum plano cadastrado.</div>
              <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 4 }}>Crie o primeiro plano pra começar a vincular assinaturas.</div>
              <button className="cr-primary" style={{ marginTop: 12 }} onClick={() => setFormPlano({ modo: "novo" })}><I.plus size={14} /> Criar primeiro plano</button>
            </div> :
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
              <thead><tr>
                <th style={TH}>Plano</th><th style={TH}>Ciclo</th><th style={{ ...TH, textAlign: "right" }}>Valor</th><th style={{ ...TH, textAlign: "center" }}>Assinaturas</th><th style={{ ...TH, textAlign: "center" }}>Fiscal</th><th style={{ ...TH, textAlign: "center" }}>Status</th><th style={{ ...TH, textAlign: "right" }}>Ações</th>
              </tr></thead>
              <tbody>
                {rows.map((p) =>
              <tr key={p.id}>
                    <td style={TD}>
                      <div style={{ fontWeight: 600, color: "var(--text)" }}>{p.name}</div>
                      <div style={{ ...MONO, fontSize: 11, color: "var(--text-mute)" }}>{p.slug}</div>
                      {p.curta && <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 2 }}>{p.curta}</div>}
                    </td>
                    <td style={TD}>{CYCLE[p.cycle]}{p.cycle === "custom" && p.dias ? ` (${p.dias}d)` : ""}</td>
                    <td style={{ ...TD, ...MONO, textAlign: "right" }}>{BRL(p.valor)}</td>
                    <td style={{ ...TD, ...MONO, textAlign: "center" }}>{p.n}</td>
                    <td style={{ ...TD, textAlign: "center" }}><span className={"cr-fiscal-badge " + p.fiscal}>{FISCAL[p.fiscal].t}</span></td>
                    <td style={{ ...TD, textAlign: "center" }}><span className={"cr-pill " + (p.ativo ? "em_dia" : "pausada")}>{p.ativo ? "ativo" : "inativo"}</span></td>
                    <td style={{ ...TD, textAlign: "right", whiteSpace: "nowrap" }}>
                      <button className="cr-act" style={{ padding: "4px 8px", fontSize: 12 }} onClick={() => setFormPlano({ modo: "editar", id: p.id })}><I.pencil size={12} /> Editar</button>{" "}
                      <button className="cr-act danger" style={{ padding: "4px 8px", fontSize: 12 }} onClick={() => setExcluir(p)}><I.x size={12} /> Excluir</button>
                    </td>
                  </tr>
              )}
              </tbody>
            </table>
          }
          {planos.length > 0 &&
          <div style={{ display: "flex", justifyContent: "space-between", padding: "8px 14px", fontSize: 12, color: "var(--text-mute)" }}>
              <span>{planos.length} {planos.length === 1 ? "plano" : "planos"} no total</span><span>Página 1 de 1</span>
            </div>
          }
        </div>
        {formPlano && <PlanoForm plano={formPlano.modo === "editar" ? PLANOS_BASE.find((x) => x.id === formPlano.id) : null} onClose={() => setFormPlano(null)} />}
        {excluir &&
        <Confirm title={`Excluir plano "${excluir.name}"?`} onClose={() => setExcluir(null)}
        foot={<><button className="cr-act" onClick={() => setExcluir(null)}>Cancelar</button><button className="cr-act danger" onClick={() => setExcluir(null)} disabled={excluir.n > 0}>Excluir</button></>}>
            {excluir.n > 0 ?
          <>Atenção: este plano tem <b>{excluir.n} {excluir.n === 1 ? "assinatura ativa" : "assinaturas ativas"}</b>. A exclusão fica bloqueada até elas mudarem de plano ou serem canceladas.</> :
          <>O plano sai da lista e não pode mais ser usado em novas assinaturas.</>}
          </Confirm>
        }
      </>);

  }

  // ══════════════════════════════════════════════════════════════
  // FATURAS  (Page RecurringBilling/Faturas/Index)
  // ══════════════════════════════════════════════════════════════
  const FAT_PILL = { paga: "em_dia", pendente: "retentando", atrasada: "falhou", cancelada: "cancelada" };
  const FAT_ST = [["todas", "Todas"], ["paga", "Pagas"], ["pendente", "Pendentes"], ["atrasada", "Atrasadas"], ["cancelada", "Canceladas"]];
  const FAT_GW = [["all", "Todos gateways"], ["inter", "Inter"], ["c6", "C6"], ["asaas", "Asaas"]];
  const FAT_PER = [["all", "Qualquer período"], ["mes_atual", "Mês atual"], ["proximo_mes", "Próximo mês"], ["atrasadas", "Apenas atrasadas"]];

  function Faturas() {
    const [fats, setFats] = useState(FATURAS_BASE);
    const [st, setSt] = useState("todas");
    const [gw, setGw] = useState("all");
    const [per, setPer] = useState("all");
    const [q, setQ] = useState("");
    const [cancel, setCancel] = useState(null);
    const [motivo, setMotivo] = useState("");
    const searchRef = useRef(null);
    useEffect(() => {
      const onKey = (e) => {if (e.key === "/" && document.activeElement !== searchRef.current) {e.preventDefault();searchRef.current && searchRef.current.focus();}};
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, []);
    const mes = HOJE_ISO.slice(0, 7);
    const proxMes = (() => {const d = new Date(HOJE);d.setMonth(d.getMonth() + 1);return d.toISOString().slice(0, 7);})();
    const pagoMes = fats.filter((f) => f.status === "paga" && f.pagoEm && f.pagoEm.slice(0, 7) === mes).reduce((a, f) => a + f.valor, 0);
    const pend = fats.filter((f) => f.status === "pendente").reduce((a, f) => a + f.valor, 0);
    const atr = fats.filter((f) => f.status === "atrasada");
    const rows = fats.filter((f) => {
      if (st !== "todas" && f.status !== st) return false;
      if (gw !== "all" && f.gateway !== gw) return false;
      if (per === "mes_atual" && f.venc.slice(0, 7) !== mes) return false;
      if (per === "proximo_mes" && f.venc.slice(0, 7) !== proxMes) return false;
      if (per === "atrasadas" && f.status !== "atrasada") return false;
      const t = q.trim().toLowerCase();
      if (t && !(f.client + " " + f.cnpj + " " + f.numero).toLowerCase().includes(t)) return false;
      return true;
    });
    const fechar = () => {setCancel(null);setMotivo("");};
    return (
      <>
        <div className="cr-stats">
          <div className="cr-stat cr-stat-hero">
            <small>Pago este mês</small>
            <b>{BRL(pagoMes)}</b>
            <span className="cr-stat-hint">faturas liquidadas no mês corrente</span>
          </div>
          <Kpi label="Pendente" value={BRL(pend)} hint="abertas com vencimento futuro" />
          <Kpi label="Atrasado" value={BRL(atr.reduce((a, f) => a + f.valor, 0))} tone={atr.length ? "neg" : ""} hint={`${atr.length} ${atr.length === 1 ? "fatura vencida" : "faturas vencidas"}`} />
          <Kpi label="Total de faturas" value={fats.length.toLocaleString("pt-BR")} hint="histórico completo" />
        </div>
        <div className="cr-card" style={{ ...SEC, background: "var(--surface)", display: "flex", flexWrap: "wrap", alignItems: "center", gap: 8 }}>
          <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
            {FAT_ST.map(([k, l]) => {
              const n = k === "todas" ? fats.length : fats.filter((f) => f.status === k).length;
              return <button key={k} type="button" style={CHIP(st === k)} onClick={() => setSt(k)}>{l} <span style={MONO}>{n}</span></button>;
            })}
          </div>
          <select style={{ ...FIELD, width: 150 }} value={gw} onChange={(e) => setGw(e.target.value)} aria-label="Gateway">{FAT_GW.map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
          <select style={{ ...FIELD, width: 160 }} value={per} onChange={(e) => setPer(e.target.value)} aria-label="Período">{FAT_PER.map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
        </div>
        <div className="cr-list-wrap">
          <div className="cr-list-head">
            <div className="cr-search">
              <I.search size={14} style={{ color: "var(--text-mute)" }} />
              <input ref={searchRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar (/) — cliente, CNPJ, número da fatura" />
              <kbd>/</kbd>
            </div>
            <span className="cr-list-count">{rows.length} / {fats.length}</span>
          </div>
          {rows.length === 0 ?
          <div style={{ padding: 40, textAlign: "center" }}>
              <div style={{ fontSize: 14, fontWeight: 600, color: "var(--text-dim)" }}>Nenhuma fatura encontrada.</div>
              <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 4 }}>Ajuste os filtros ou a busca.</div>
            </div> :
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
              <thead><tr>
                <th style={TH}>Número</th><th style={TH}>Cliente</th><th style={TH}>Plano</th><th style={{ ...TH, textAlign: "right" }}>Valor</th><th style={TH}>Vencimento</th><th style={TH}>Status</th><th style={TH}>Gateway</th><th style={{ ...TH, textAlign: "right" }}>Ações</th>
              </tr></thead>
              <tbody>
                {rows.map((f) => {
                const cancelavel = f.status === "pendente" || f.status === "atrasada";
                return (
                  <tr key={f.id}>
                      <td style={{ ...TD, ...MONO }}>{f.numero}</td>
                      <td style={TD}><div style={{ fontWeight: 600, color: "var(--text)" }}>{f.client}</div><div style={{ ...MONO, fontSize: 11, color: "var(--text-mute)" }}>{f.cnpj}</div></td>
                      <td style={{ ...TD, fontSize: 12 }}>{f.plan || <i style={{ color: "var(--text-mute)" }}>avulsa</i>}</td>
                      <td style={{ ...TD, ...MONO, textAlign: "right", fontWeight: 600 }}>{BRL(f.valor)}</td>
                      <td style={{ ...TD, fontSize: 12 }}><div>{dateFull(f.venc)}</div><div style={{ fontSize: 11, marginTop: 2, color: f.status === "atrasada" ? "oklch(0.50 0.16 25)" : "var(--text-mute)", fontWeight: f.status === "atrasada" ? 600 : 400 }}>{f.status === "paga" || f.status === "cancelada" ? "—" : dueLabel(f.venc)}</div></td>
                      <td style={TD}><span className={"cr-pill " + FAT_PILL[f.status]}>{f.status}</span></td>
                      <td style={TD}><span className="cr-fiscal-badge none">{GATEWAY[f.gateway]}</span></td>
                      <td style={{ ...TD, textAlign: "right" }}>{cancelavel ? <button className="cr-act danger" style={{ padding: "4px 8px", fontSize: 12 }} onClick={() => setCancel(f)} title="Cancelar fatura">Cancelar</button> : <span style={{ color: "var(--text-mute)" }}>—</span>}</td>
                    </tr>);
              })}
              </tbody>
            </table>
          }
        </div>
        {cancel &&
        <Confirm title="Cancelar fatura" onClose={fechar}
        foot={<><button className="cr-act" onClick={fechar}>Voltar</button><button className="cr-act danger" onClick={() => {setFats((p) => p.map((x) => x.id === cancel.id ? { ...x, status: "cancelada" } : x));fechar();}}>Confirmar cancelamento</button></>}>
            <div>{cancel.numero} · {cancel.client} · {BRL(cancel.valor)}. O boleto/Pix é cancelado no gateway e a fatura fica registrada como cancelada.</div>
            <div style={{ marginTop: 12 }}><Field label="Motivo (opcional)"><input style={FIELD} value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Ex: duplicidade, solicitação do cliente" /></Field></div>
          </Confirm>
        }
      </>);

  }

  // ══════════════════════════════════════════════════════════════
  // CONFIGURAÇÕES  (Page RecurringBilling/Configuracoes/Index)
  // ══════════════════════════════════════════════════════════════
  const GATEWAYS_CFG = [
  { id: 1, nome: "Banco Inter PJ", banco: "Inter", ambiente: "Produção", sandbox: false, ativo: true },
  { id: 2, nome: "Asaas", banco: "Asaas", ambiente: "Sandbox", sandbox: true, ativo: true }];

  const REGUA = [
  { ordem: 1, dias: 3, rotulo: "1ª retentativa", descricao: "Falha leve — provavelmente saldo insuficiente ou instabilidade do banco.", tone: "retentando" },
  { ordem: 2, dias: 7, rotulo: "2ª retentativa", descricao: "Cobrança insistente — reenvia boleto novo por e-mail e WhatsApp.", tone: "retentando" },
  { ordem: 3, dias: 15, rotulo: "3ª retentativa (final)", descricao: "Falha definitiva — a assinatura passa para “falhou” e pede ação manual no detalhe da assinatura.", tone: "falhou" }];

  const WEBHOOKS = [
  { gateway: "asaas", label: "Asaas", rotulo: "Webhook unificado (pagamento, assinatura, transferência)", metodo: "POST", url: "https://oimpresso.com/webhooks/asaas/1", auth: "Header X-Webhook-Token configurado no Asaas (Conta → Integrações → Webhooks)." },
  { gateway: "inter", label: "Banco Inter PJ", rotulo: "Webhook PIX recebido", metodo: "POST", url: "https://oimpresso.com/webhooks/inter/pix/1", auth: "Header X-Inter-Webhook-Secret, conferido com o segredo da credencial Inter ativa." }];


  function CfgCard({ titulo, sub, children }) {
    return (
      <section className="cr-card" style={{ background: "var(--surface)", padding: 16 }}>
        <h2 style={{ margin: 0, fontSize: 14, fontWeight: 600, color: "var(--text)" }}>{titulo}</h2>
        <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 2, marginBottom: 12 }}>{sub}</div>
        {children}
      </section>);

  }

  function WebhookCard({ w }) {
    const [copiado, setCopiado] = useState(false);
    const copiar = () => {
      try {navigator.clipboard && navigator.clipboard.writeText(w.url).catch(() => {});} catch (e) {/* sem clipboard no preview */}
      setCopiado(true);setTimeout(() => setCopiado(false), 1600);
    };
    return (
      <div style={{ border: "1px solid var(--border)", borderRadius: 8, padding: 12 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <span className="cr-fiscal-badge nfe">{w.metodo}</span>
          <b style={{ fontSize: 13, color: "var(--text)" }}>{w.label}</b>
          <span className="cr-act-spacer" />
          <a href="#" onClick={(e) => e.preventDefault()} style={{ fontSize: 12, color: "var(--accent)" }}>docs</a>
        </div>
        <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 4 }}>{w.rotulo}</div>
        <div style={{ display: "flex", gap: 6, marginTop: 8 }}>
          <code style={{ ...MONO, flex: 1, minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", padding: "6px 8px", borderRadius: 6, background: "color-mix(in oklab,var(--text) 5%,transparent)", color: "var(--text-dim)" }}>{w.url}</code>
          <button className="cr-act" style={{ padding: "4px 10px", fontSize: 12 }} onClick={copiar}><I.copy size={12} /> {copiado ? "Copiado!" : "Copiar"}</button>
        </div>
        <div style={{ fontSize: 11.5, color: "var(--text-mute)", marginTop: 8 }}><b style={{ color: "var(--text-dim)" }}>Autenticação:</b> {w.auth}</div>
      </div>);

  }

  function Configuracoes() {
    return (
      <div style={{ display: "grid", gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 16, margin: "16px 24px 24px" }}>
        <CfgCard titulo="Gateways de boleto/pix" sub="Credenciais ativas e sandbox cadastradas nesta empresa.">
          <div style={{ display: "grid", gap: 8 }}>
            {GATEWAYS_CFG.map((g) =>
            <div key={g.id} style={{ display: "flex", alignItems: "center", gap: 10, border: "1px solid var(--border)", borderRadius: 8, padding: "9px 12px" }}>
                <Avatar name={g.banco} size={30} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13, fontWeight: 600, color: "var(--text)" }}>{g.nome} {g.sandbox && <span className="cr-pill retentando" style={{ marginLeft: 4 }}>sandbox</span>}</div>
                  <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{g.banco} · {g.ambiente}</div>
                </div>
                <span className={"cr-pill " + (g.ativo ? "em_dia" : "pausada")}>{g.ativo ? "ativo" : "inativo"}</span>
              </div>
            )}
          </div>
          <button className="cr-act" style={{ marginTop: 12 }}><I.plus size={13} /> Adicionar gateway</button>
        </CfgCard>

        <CfgCard titulo="Régua de dunning (cobrança)" sub="Cronograma de retentativas quando uma fatura falha no pagamento.">
          <p style={{ margin: "0 0 10px", fontSize: 12.5, color: "var(--text-dim)", lineHeight: 1.5 }}>Régua padrão quando o pagamento falha. Cada retentativa gera boleto novo e avisa o cliente por e-mail e WhatsApp. Depois da 3ª, a assinatura exige ação manual.</p>
          <div style={{ display: "grid", gap: 8 }}>
            {REGUA.map((r) =>
            <div key={r.ordem} style={{ display: "flex", gap: 10, border: "1px solid var(--border)", borderRadius: 8, padding: "9px 12px" }}>
                <div style={{ flex: 1 }}>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 8 }}>
                    <b style={{ fontSize: 13, color: "var(--text)" }}>{r.rotulo}</b>
                    <span className={"cr-pill " + r.tone} style={MONO}>+{r.dias}d</span>
                  </div>
                  <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 3 }}>{r.descricao}</div>
                </div>
              </div>
            )}
          </div>
        </CfgCard>

        <CfgCard titulo="NFe-de-boleto-pago automática" sub="Emissão fiscal disparada automaticamente ao receber pagamento.">
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", border: "1px solid var(--border)", borderRadius: 8, padding: "10px 12px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <span role="switch" aria-checked="false" aria-label="NFe automática" style={{ display: "inline-flex", width: 40, height: 22, borderRadius: 999, padding: 2, boxSizing: "border-box", background: "color-mix(in oklab,var(--text) 18%,transparent)" }}>
                <span style={{ width: 18, height: 18, borderRadius: 999, background: "var(--surface)" }} />
              </span>
              <b style={{ fontSize: 13, color: "var(--text)" }}>Desativada</b>
            </div>
            <span className="cr-pill pausada">Em breve</span>
          </div>
          <p style={{ margin: "10px 0 0", fontSize: 12.5, color: "var(--text-dim)", lineHeight: 1.5 }}>Quando uma fatura é paga (aviso do Asaas ou do Banco Inter), o sistema emite a nota fiscal automaticamente. Vale para planos com emissão NFe ou NFS-e.</p>
        </CfgCard>

        <CfgCard titulo="Webhooks" sub="Endereços pra colar no painel do banco ou gateway.">
          <div style={{ display: "grid", gap: 10 }}>{WEBHOOKS.map((w) => <WebhookCard key={w.gateway} w={w} />)}</div>
        </CfgCard>
      </div>);

  }

  // ── rotas ↔ vista ↔ Page (uma rota por Index; vista fixa) ─────────
  const ROTAS = {
    "recurring": { vista: "assinaturas", page: "RecurringBilling/Index" },
    "rb-assinaturas": { vista: "assinaturas", page: "RecurringBilling/Index" },
    "rb-planos": { vista: "planos", page: "RecurringBilling/Planos/Index", drawers: { novo: "RecurringBilling/Planos/Create", editar: "RecurringBilling/Planos/Edit" } },
    "rb-faturas": { vista: "faturas", page: "RecurringBilling/Faturas/Index" },
    "rb-config": { vista: "config", page: "RecurringBilling/Configuracoes/Index" }
  };

  // ── página ─────────────────────────────────────────────────────
  function CobrancaRecorrentePage({ view }) {
    const tab = view || "assinaturas";
    const [novaAssin, setNovaAssin] = useState(false);
    const [formPlano, setFormPlano] = useState(null);
    useEffect(() => {setNovaAssin(false);setFormPlano(null);}, [tab]);
    useEffect(() => {
      const onKey = (e) => {
        const el = document.activeElement;
        if (el && (el.tagName === "INPUT" || el.tagName === "TEXTAREA" || el.tagName === "SELECT")) return;
        if ((e.key === "n" || e.key === "N") && !e.metaKey && !e.ctrlKey) {
          if (tab === "assinaturas") setNovaAssin(true);
          if (tab === "planos") setFormPlano({ modo: "novo" });
        }
      };
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, [tab]);

    const ativos = SUBS.filter((s) => s.status !== "cancelada");
    const mrr = ativos.reduce((a, s) => a + monthly(s), 0);
    const churnRate = Math.round(SUBS.filter((s) => s.status === "cancelada").length / SUBS.length * 100);
    const planosAtivos = PLANOS_BASE.filter((p) => p.ativo);
    const mrrPot = planosAtivos.reduce((a, p) => a + p.valor / (CYCLE_DIV[p.cycle] || 1) * assinCount(p.name), 0);
    const atrasadas = FATURAS_BASE.filter((f) => f.status === "atrasada").length;
    const HEAD = {
      assinaturas: { h: "Cobrança recorrente", linha: `${ativos.length} ATIVAS · MRR ${BRL(mrr)} · CHURN ${churnRate}%` },
      planos: { h: "Planos", sub: "· cobrança recorrente", linha: `${PLANOS_BASE.length} PLANOS · ${planosAtivos.length} ATIVOS · MRR potencial ${BRL(mrrPot)}` },
      faturas: { h: "Faturas", sub: "· cobrança recorrente", linha: `${FATURAS_BASE.length} FATURAS · ATRASADAS ${atrasadas}` },
      config: { h: "Configurações", sub: "· cobrança recorrente", linha: "Gateways de pagamento, régua de dunning, NFe automática e webhooks por gateway." }
    }[tab] || {};
    return (
      <div className="cr-root" data-screen-label="Cobrança Recorrente" data-rb-vista={tab}>
        <div className="cr-hero">
          <div className="cr-hero-top">
            <div className="cr-hero-title">
              <h1>{HEAD.h}{HEAD.sub && <span className="cr-sub"> {HEAD.sub}</span>}</h1>
              <p style={tab === "config" ? null : { ...MONO, fontSize: 11, textTransform: "uppercase", letterSpacing: "0.06em" }}>{HEAD.linha}</p>
            </div>
            <div className="cr-hero-actions">
              {tab === "assinaturas" && <button className="cr-primary" onClick={() => setNovaAssin(true)}><I.plus size={14} /> Nova assinatura <kbd>N</kbd></button>}
              {tab === "planos" && <button className="cr-primary" onClick={() => setFormPlano({ modo: "novo" })}><I.plus size={14} /> Novo plano <kbd>N</kbd></button>}
            </div>
          </div>
        </div>
        {window.PageHeaderNav && <window.PageHeaderNav route={window.__route} />}
        <div className="cr-body">
          {tab === "assinaturas" && <Assinaturas onNova={() => setNovaAssin(true)} />}
          {tab === "planos" && <Planos formPlano={formPlano} setFormPlano={setFormPlano} />}
          {tab === "faturas" && <Faturas />}
          {tab === "config" && <Configuracoes />}
        </div>
        {novaAssin && <NovaAssinatura onClose={() => setNovaAssin(false)} />}
      </div>);

  }

  window.CobrancaRecorrentePage = CobrancaRecorrentePage;
  window.RbRotas = ROTAS;
})();
