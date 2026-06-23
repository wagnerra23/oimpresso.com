// cobranca-recorrente-page.jsx — Cobrança Recorrente (Assinaturas) · F1 [CC]
// Reescreve a RecurringBilling do git na linguagem do DS (Cockpit V2 warm):
//   stone + roxo var(--accent) · rounded-md · escala warm emerald/amber/rose ·
//   KPI hero warm (var(--text), NÃO bg-zinc-900) · DRAWER LATERAL (não modal/coluna).
// Sub-nav espelha o git RecurringBilling: Assinaturas · Planos · Faturas · Configurações.
// Expõe window.CobrancaRecorrentePage. Persona: Eliana [E] / Larissa (balcão 1280px).
(function () {
  const { useState, useEffect, useMemo, useRef } = React;

  // ── helpers ────────────────────────────────────────────────────
  const BRL = (n) => (n || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const hueFor = (name) => {let h = 0;for (let i = 0; i < name.length; i++) h = h * 31 + name.charCodeAt(i) >>> 0;return h % 360;};
  const initials = (name) => (name || "").split(/\s+/).slice(0, 2).map((w) => w[0] || "").join("").toUpperCase();
  const MES = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];
  function daysFromToday(iso) {if (!iso) return null;const d = new Date(iso + "T12:00");return Math.round((d - new Date(new Date().toDateString())) / 86400000);}
  function nextLabel(iso) {const dd = daysFromToday(iso);if (dd === null) return "—";if (dd === 0) return "hoje";if (dd === 1) return "amanhã";if (dd < 0) return `há ${-dd} dias`;return `em ${dd} dias`;}
  function dateBR(iso) {if (!iso) return "—";const p = iso.split("-");return `${+p[2]} ${MES[+p[1] - 1]}`;}
  function sinceLabel(iso) {const dd = daysFromToday(iso);if (dd === null) return "—";const m = Math.round(-dd / 30);if (m < 1) return "este mês";if (m < 12) return `há ${m}m`;return `há ${Math.round(m / 12)}a`;}

  const CYCLE = { mensal: "mensal", trimestral: "trimestral", semestral: "semestral", anual: "anual" };
  const CYCLE_DIV = { mensal: 1, trimestral: 3, semestral: 6, anual: 12 };

  // ── mock domínio gráfica (contratos recorrentes) ───────────────
  const SUBS = [
  { id: "AS-1042", client: "Padaria Pão Quente", cnpj: "12.448.770/0001-22", plan: "Sinalização Mensal", cycle: "mensal", since: "2024-08-12", method: "pix", status: "em_dia", retry: null, nextAt: "2026-06-10", nextValue: 450, paid: 22, missed: 0, ltv: 9900, fiscal: "nfse", contact: { name: "Dona Marli", phone: "(11) 99632-1180" }, note: null },
  { id: "AS-1051", client: "Auto Posto Trevo", cnpj: "08.911.332/0001-09", plan: "Adesivos Frota Mensal", cycle: "mensal", since: "2025-02-03", method: "boleto", status: "retentando", retry: 1, nextAt: "2026-06-05", nextValue: 680, paid: 15, missed: 1, ltv: 10200, fiscal: "nfe", contact: { name: "Cláudio Reis", phone: "(11) 98115-4420" }, note: "Boleto venceu — cliente pediu 2ª via no WhatsApp." },
  { id: "AS-1063", client: "Restaurante Sabor Caseiro", cnpj: "21.005.118/0001-77", plan: "Impressão Trimestral", cycle: "trimestral", since: "2024-11-20", method: "card", status: "em_dia", retry: null, nextAt: "2026-07-20", nextValue: 1200, paid: 6, missed: 0, ltv: 7200, fiscal: "nfe", contact: { name: "Sr. Antônio", phone: "(11) 97744-3301" }, note: null },
  { id: "AS-1070", client: "Academia CorpoForte", cnpj: "33.671.904/0001-50", plan: "Sinalização Mensal", cycle: "mensal", since: "2025-01-15", method: "boleto", status: "falhou", retry: 3, nextAt: "2026-05-28", nextValue: 450, paid: 13, missed: 3, ltv: 5850, fiscal: "nfse", contact: { name: "Renata Lima", phone: "(11) 99280-7765" }, note: "3 tentativas sem sucesso. Avaliar suspensão." },
  { id: "AS-1088", client: "Clínica Vida", cnpj: "45.220.661/0001-18", plan: "Manutenção Fachada Anual", cycle: "anual", since: "2024-03-01", method: "pix", status: "em_dia", retry: null, nextAt: "2027-03-01", nextValue: 3600, paid: 2, missed: 0, ltv: 7200, fiscal: "nfse", contact: { name: "Dra. Helena", phone: "(11) 98870-2244" }, note: null },
  { id: "AS-1094", client: "Mercado União", cnpj: "55.118.022/0001-31", plan: "Adesivos Frota Mensal", cycle: "mensal", since: "2024-09-09", method: "boleto", status: "retentando", retry: 2, nextAt: "2026-06-03", nextValue: 680, paid: 19, missed: 2, ltv: 12920, fiscal: "nfe", contact: { name: "Jair Souza", phone: "(11) 99044-1199" }, note: null },
  { id: "AS-1101", client: "Escola Saber", cnpj: "60.337.481/0001-04", plan: "Impressão Trimestral", cycle: "trimestral", since: "2024-06-18", method: "boleto", status: "pausada", retry: null, nextAt: null, nextValue: 1200, paid: 7, missed: 0, ltv: 8400, fiscal: "nfe", contact: { name: "Coord. Paula", phone: "(11) 97331-8800" }, note: "Pausado nas férias escolares (jun–jul)." },
  { id: "AS-1109", client: "Farmácia Bem-Estar", cnpj: "71.992.150/0001-66", plan: "Sinalização Mensal", cycle: "mensal", since: "2025-03-22", method: "pix", status: "em_dia", retry: null, nextAt: "2026-06-22", nextValue: 450, paid: 11, missed: 0, ltv: 4950, fiscal: "nfse", contact: { name: "Bruno Tavares", phone: "(11) 98220-5512" }, note: null },
  { id: "AS-1115", client: "Loja Bella Moda", cnpj: "82.004.773/0001-90", plan: "Sinalização Mensal", cycle: "mensal", since: "2024-04-10", method: "card", status: "cancelada", retry: null, nextAt: null, nextValue: 450, paid: 9, missed: 1, ltv: 4050, fiscal: "nfse", contact: { name: "Camila Dias", phone: "(11) 99500-3344" }, churn: "loja fechou", note: null }];


  const monthly = (s) => s.nextValue / (CYCLE_DIV[s.cycle] || 1);

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

  // ── KPI strip (warm hero) ──────────────────────────────────────
  function KpiStrip({ subs }) {
    const ativos = subs.filter((s) => s.status !== "cancelada");
    const mrr = ativos.reduce((a, s) => a + monthly(s), 0);
    const churn = subs.filter((s) => s.status === "cancelada").length;
    const churnRate = subs.length ? Math.round(churn / subs.length * 100) : 0;
    const prox = ativos.filter((s) => s.nextAt && daysFromToday(s.nextAt) >= 0).sort((a, b) => a.nextAt.localeCompare(b.nextAt));
    const proxVal = prox.slice(0, 5).reduce((a, s) => a + s.nextValue, 0);
    const falhas = subs.filter((s) => s.status === "falhou" || s.status === "retentando");
    const falhaVal = falhas.reduce((a, s) => a + s.nextValue, 0);
    return (
      <div className="cr-stats">
        <div className="cr-stat cr-stat-hero">
          <small>MRR · receita recorrente</small>
          <b>{BRL(mrr)}</b>
          <span className="cr-stat-hint"><b className="cr-num-pos">{ativos.length}</b> assinaturas ativas · ticket médio <b>{BRL(mrr / Math.max(ativos.length, 1))}</b></span>
        </div>
        <div className="cr-stat">
          <small>Churn este mês</small>
          <b>{churn} {churn === 1 ? "saída" : "saídas"}</b>
          <span className="cr-stat-hint">taxa {churnRate}%</span>
        </div>
        <div className="cr-stat">
          <small>Próximas cobranças</small>
          <b>{prox.length}</b>
          <span className="cr-stat-hint">{BRL(proxVal)} a vencer</span>
        </div>
        <div className="cr-stat">
          <small>A recuperar</small>
          <b className={falhas.length ? "cr-num-neg" : ""}>{BRL(falhaVal)}</b>
          <span className="cr-stat-hint">{falhas.length} {falhas.length === 1 ? "cobrança falha" : "cobranças falhas"}</span>
        </div>
      </div>);

  }

  // ── linha da lista ─────────────────────────────────────────────
  function SubRow({ sub, active, onOpen, fav, onFav }) {
    return (
      <div className={"cr-row" + (active ? " on" : "")} onClick={onOpen}>
        <button className={"cr-star" + (fav ? " on" : "")} title={fav ? "Desfavoritar" : "Favoritar"} onClick={(e) => {e.stopPropagation();onFav();}}>
          {fav ? <I.starFill size={14} /> : <I.star size={14} />}
        </button>
        <Avatar name={sub.client} />
        <div className="cr-row-main">
          <div className="cr-row-title">{sub.client}</div>
          <div className="cr-row-sub">{sub.plan} · {CYCLE[sub.cycle]} · desde {sinceLabel(sub.since)}</div>
        </div>
        <div className="cr-row-right">
          <StatusPill status={sub.status} retry={sub.retry} />
          {sub.status !== "cancelada" && sub.status !== "pausada" &&
          <span className="cr-row-val">{METHOD[sub.method]} · {BRL(sub.nextValue)}</span>
          }
        </div>
      </div>);

  }

  // ── DRAWER lateral (o molde do Financeiro) ─────────────────────
  function PaymentHeat({ paid, missed }) {
    const cells = useMemo(() => {
      return Array.from({ length: 12 }, (_, i) => {
        if (i === 11) return "future";
        const pr = (i + 1) / 11;
        if (i < 3 && missed > 0 && i + 1 <= Math.round(missed * pr * 3)) return "missed";
        return i + 1 <= Math.round(paid * pr / (paid / 11 || 1)) ? "paid" : i < 8 ? "paid" : "future";
      });
    }, [paid, missed]);
    const months = useMemo(() => {const out = [];const n = new Date();for (let i = 11; i >= 0; i--) out.push(MES[(n.getMonth() - i + 12) % 12]);return out;}, []);
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

  const FISCAL = { nfe: { t: "NFe", l: "NFe · Nota Fiscal Eletrônica" }, nfse: { t: "NFS-e", l: "NFS-e · Nota de Serviços" }, none: { t: "Não emite", l: "Sem emissão fiscal" } };

  function Drawer({ sub, onClose }) {
    const [tab, setTab] = useState("detalhes");
    useEffect(() => {setTab("detalhes");}, [sub && sub.id]);
    if (!sub) return null;
    const inactive = sub.status === "cancelada" || sub.status === "pausada";
    const f = FISCAL[sub.fiscal] || FISCAL.none;
    const tlEvents = [
    { kind: "cobrança", dot: "oklch(0.72 0.12 145)", by: "sistema", when: dateBR(sub.nextAt) || "—", body: sub.status === "falhou" ? "Cobrança recusada pelo banco" : "Próxima cobrança agendada" },
    { kind: "nf", dot: "oklch(0.62 0.13 295)", by: "sistema", when: "12 mai", body: `${f.t} emitida e enviada ao cliente` },
    { kind: "criou", dot: "var(--text-mute)", by: "Eliana", when: sinceLabel(sub.since), body: "Assinatura criada" }];

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
            <button className="cr-x" onClick={onClose}><I.x size={16} /></button>
          </div>

          <div className="cr-dwr-tabs">
            <button className={"cr-dwr-tab" + (tab === "detalhes" ? " on" : "")} onClick={() => setTab("detalhes")}>Detalhes</button>
            <button className={"cr-dwr-tab" + (tab === "ia" ? " on" : "")} onClick={() => setTab("ia")}>✦ IA</button>
          </div>

          <div className="cr-dwr-body">
            {tab === "detalhes" && <>
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
                <div><dt>Contato</dt><dd>{sub.contact.name} · <span style={{ fontFamily: "ui-monospace,monospace", fontSize: 12 }}>{sub.contact.phone}</span></dd></div>
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
                    <div style={{ fontSize: 10.5, color: "var(--text-mute)", marginTop: 5 }}>{f.l}</div>
                  </div>
                  <button className="cr-act"><I.send size={12} /> Reenviar nota</button>
                </div>
              </div>

              <div>
                <div className="cr-blk-label">Notas &amp; eventos</div>
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
            <span className="cr-act-spacer" />
            {!inactive && <button className="cr-act danger"><I.x size={13} /> Cancelar</button>}
          </div>
        </aside>
      </>);

  }

  // ── view: Assinaturas (hub completo) ───────────────────────────
  // Campos de data próprios das assinaturas (espelha o PeriodBar do Financeiro).
  const PB_FIELDS = [
  { id: "prox", label: "Próxima cobrança" },
  { id: "inicio", label: "Início" }];

  const pbDate = (s, field) => {
    const iso = field === "inicio" ? s.since : s.nextAt;
    return iso ? new Date(iso + "T12:00") : null;
  };

  function Assinaturas() {
    const [q, setQ] = useState("");
    const [openId, setOpenId] = useState(null);
    const [favs, setFavs] = useState(() => new Set());
    const [dateField, setDateField] = useState("prox");
    const [periodMode, setPeriodMode] = useState("tudo");
    const [anchor, setAnchor] = useState(() => new Date());
    const searchRef = useRef(null);
    const PeriodBar = window.FinPeriodBar;
    const rows = useMemo(() => {
      const t = q.trim().toLowerCase();
      const win = window.finPeriodWindow ? window.finPeriodWindow(periodMode, anchor) : null;
      return SUBS.filter((s) => {
        if (t && !(s.client + " " + s.cnpj + " " + s.plan + " " + s.id).toLowerCase().includes(t)) return false;
        if (win) {const d = pbDate(s, dateField);if (!d || d < win[0] || d >= win[1]) return false;}
        return true;
      });
    }, [q, dateField, periodMode, anchor]);
    useEffect(() => {
      const onKey = (e) => {
        if (e.key === "/" && document.activeElement !== searchRef.current) {e.preventDefault();searchRef.current && searchRef.current.focus();} else
        if (e.key === "Escape") setOpenId(null);
      };
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, []);
    const open = rows.find((s) => s.id === openId) || null;
    const toggleFav = (id) => setFavs((p) => {const n = new Set(p);n.has(id) ? n.delete(id) : n.add(id);return n;});
    return (
      <>
        <KpiStrip subs={SUBS} />
        {PeriodBar && <PeriodBar dateField={dateField} setDateField={setDateField}
        period={periodMode} setPeriod={setPeriodMode}
        anchor={anchor} setAnchor={setAnchor}
        count={rows.length} fields={PB_FIELDS} countLabel="assinaturas" />}
        <div className="cr-list-wrap">
          <div className="cr-list-head">
            <div className="cr-search">
              <I.search size={14} style={{ color: "var(--text-mute)" }} />
              <input ref={searchRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar (/) — cliente, CNPJ, plano" />
              <kbd>/</kbd>
            </div>
            <span className="cr-list-count">{rows.length} de {SUBS.length}</span>
          </div>
          {rows.length === 0 ?
          <div style={{ padding: 40, textAlign: "center", color: "var(--text-mute)", fontSize: 13 }}>Nenhuma assinatura com esse filtro.</div> :
          rows.map((s) => <SubRow key={s.id} sub={s} active={openId === s.id} fav={favs.has(s.id)} onFav={() => toggleFav(s.id)} onOpen={() => setOpenId(s.id)} />)}
        </div>
        {open && <Drawer sub={open} onClose={() => setOpenId(null)} />}
      </>);

  }

  // ── views placeholder (honestas — não fingir pronto) ───────────
  function Placeholder({ title, desc }) {
    return (
      <div className="cr-placeholder">
        <h2>{title}</h2>
        <p>{desc}</p>
        <button className="cr-primary cr-ph-back" onClick={() => window.__selectRoute && window.__selectRoute("recurring")}>← Voltar pras assinaturas</button>
      </div>);

  }

  // ── página ─────────────────────────────────────────────────────
  const TABS = [
  { key: "assinaturas", route: "recurring", label: "Assinaturas", ct: SUBS.filter((s) => s.status !== "cancelada").length },
  { key: "planos", route: "rb-planos", label: "Planos", ct: 4 },
  { key: "faturas", route: "rb-faturas", label: "Faturas" },
  { key: "config", route: "rb-config", label: "Configurações" }];


  function CobrancaRecorrentePage({ view }) {
    const tab = view || "assinaturas";
    const go = (route) => window.__selectRoute && window.__selectRoute(route);
    return (
      <div className="cr-root" data-screen-label="Cobrança Recorrente">
        <div className="cr-hero">
          <div className="cr-hero-top">
            <div className="cr-hero-title">
              <h1>Assinaturas <span className="cr-sub">· Cobrança Recorrente</span></h1>
              <p>Junho 2026 · WR2 Sistemas · assinaturas &amp; contratos</p>
            </div>
            <div className="cr-hero-actions">
              <button className="cr-primary"><I.plus size={14} /> Nova assinatura <kbd>N</kbd></button>
            </div>
          </div>
        </div>
        {window.PageHeaderNav && <window.PageHeaderNav route={window.__route} />}
        <div className="cr-body">
          {tab === "assinaturas" && <Assinaturas />}
          {tab === "planos" && <Placeholder title="Planos" desc="CRUD dos planos recorrentes (nome, ciclo, valor, tipo fiscal, dias de trial) + distribuição por ciclo. No molde da lista de assinaturas — drawer lateral pra criar/editar. Espelha /recurring-billing/planos do git." />}
          {tab === "faturas" && <Placeholder title="Faturas" desc="Lista de faturas emitidas (paga / pendente / atrasada / cancelada) por gateway (Inter · C6 · Asaas), com KPI de pago no mês e cancelamento. Espelha /recurring-billing/faturas do git — a tela que hoje está em zinc/violet vai entrar neste mesmo molde warm." />}
          {tab === "config" && <Placeholder title="Configurações" desc="Gateways de pagamento, régua de cobrança (dunning), emissão automática de NF-e/NFS-e e webhooks. Espelha /recurring-billing/configuracoes do git." />}
        </div>
      </div>);

  }

  window.CobrancaRecorrentePage = CobrancaRecorrentePage;
})();