// chat-jana.jsx — Cockpit do Analista IA (Jana).
// Conceito: a IA é uma analista (Jana) que entrega brief diário, monitora KPIs,
// detecta anomalias, sugere ações HITL e responde via chat single-thread.
// Conforma Cockpit.charter.md (8 refinos F1.5) + canon DS v6 (roxo, tokens, sem emoji).
const { useState: useStateJ, useRef: useRefJ, useEffect: useEffectJ } = React;

// ─── Ícones (line, currentColor — sem emoji, conforme proibições visuais) ───
function JcIcon({ name, className }) {
  const P = {
    settings:  <><circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2.5 12h3M18.5 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></>,
    download:  <><path d="M12 4v11M7 11l5 4 5-4"/><path d="M5 19h14"/></>,
    calendar:  <><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 9h16M8 3v4M16 3v4"/></>,
    play:      <path d="M7 5l11 7-11 7z"/>,
    target:    <><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/></>,
    mail:      <><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/></>,
    list:      <><path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></>,
    search:    <><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></>,
    help:      <><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.2 2.4c-.7.3-1.2.9-1.2 1.6v.4"/><circle cx="12" cy="17" r=".6"/></>,
    coins:     <><ellipse cx="9" cy="7" rx="6" ry="2.6"/><path d="M3 7v5c0 1.4 2.7 2.6 6 2.6s6-1.2 6-2.6"/><path d="M15 11.4c2.6.3 4.5 1.3 4.5 2.5 0 1.4-2.7 2.6-6 2.6-1.3 0-2.6-.2-3.5-.5"/></>,
    alert:     <><path d="M12 4l8.5 15h-17z"/><path d="M12 10v4"/><circle cx="12" cy="16.5" r=".6"/></>,
    trendUp:   <><path d="M3 16l5-5 4 4 8-8"/><path d="M16 7h4v4"/></>,
    trendDown: <><path d="M3 8l5 5 4-4 8 8"/><path d="M16 17h4v-4"/></>,
    truck:     <><rect x="2" y="7" width="11" height="9" rx="1"/><path d="M13 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></>,
    chart:     <><path d="M4 4v16h16"/><path d="M8 14v3M12 10v7M16 6v11"/></>,
    bulb:      <><path d="M9 17h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.5.4.5 1 .5 1.6h6c0-.6 0-1.2.5-1.6A6 6 0 0 0 12 3z"/></>,
    clock:     <><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></>,
    receipt:   <><path d="M5 3h14v18l-2.5-1.5L14 21l-2-1.5L10 21l-2.5-1.5L5 21z"/><path d="M9 8h6M9 12h6"/></>,
    heart:     <path d="M12 20s-7-4.4-7-9.3A3.7 3.7 0 0 1 12 7a3.7 3.7 0 0 1 7 3.7C19 15.6 12 20 12 20z"/>,
    trash:     <><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></>,
    database:  <><ellipse cx="12" cy="6" rx="7" ry="2.6"/><path d="M5 6v6c0 1.4 3.1 2.6 7 2.6s7-1.2 7-2.6V6"/><path d="M5 12v6c0 1.4 3.1 2.6 7 2.6s7-1.2 7-2.6v-6"/></>,
    check:     <path d="M5 12.5l4.5 4.5L19 7"/>,
    x:         <path d="M6 6l12 12M18 6L6 18"/>,
    shield:    <><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M9.5 12l1.8 1.8 3.2-3.6"/></>,
    send:      <path d="M5 12l15-7-5 15-3.5-5z"/>,
    compass:   <><circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/></>,
    sparkles:  <path d="M12 4l1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6z"/>,
    refresh:   <><path d="M4 11a8 8 0 0 1 14-4l2 2M20 13a8 8 0 0 1-14 4l-2-2"/><path d="M18 4v5h-5M6 20v-5h5"/></>,
    plus:      <path d="M12 5v14M5 12h14"/>,
  };
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor"
         strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {P[name] || <circle cx="12" cy="12" r="2"/>}
    </svg>
  );
}

// ─── Dados mock (estrutura do Martinho Caçambas — adaptável por empresa) ───
function getJanaData(company) {
  return {
    person: { name: "Jana", role: "Analista IA", initial: "J" },
    biz: { code: "biz=164", version: "v1404 legacy migrado" },
    updatedAt: "09:42",
    today: "14/maio/2026",
    brief: {
      greeting: "Bom dia, Wagner.",
      paragraphs: [
        { kind:"text", body:[
          ["normal", "Maio até hoje somou "],
          ["strong", "R$ 47.150"],
          ["normal", " (vs R$ 145k em maio/25 — "],
          ["danger", "-68%"],
          ["normal", " · investigar sazonalidade ou causa estrutural)."]
        ]},
        { kind:"text", body:[
          ["danger", "R$ 4.535.636"],
          ["normal", " em 4.255 títulos vencidos (excluído lixo de saldos virtuais e parcelas agrupadas). "],
          ["strong", "Top 20 clientes concentram R$ 2.142k (~47%)"],
          ["normal", " da inadimplência."]
        ]},
        { kind:"action", icon:"target", body:[
          ["strong", "Ação sugerida HOJE: "],
          ["normal", "8 clientes \"ouro\" (LTV >R$ 50k) estão >90d sem comprar — score reativação alto. Posso disparar régua HITL?"]
        ]},
        { kind:"anomaly", body:[
          ["normal", "Anomalia detectada: ticket médio caiu de R$ 2.430 para R$ 1.890 (-22%) — 4 meses consecutivos. Margem mantida (preço por m³ estável) → indica mix de produto mudando (mais caçambas pequenas/curtas)."]
        ]},
      ],
      chips: [
        { tone:"primary", icon:"mail",   label:"Disparar régua 8 clientes" },
        { tone:"ghost",   icon:"list",   label:"Ver top 20 devedores" },
        { tone:"ghost",   icon:"search", label:"Investigar queda ticket médio" },
        { tone:"ghost",   icon:"help",   label:"Por que -68% MoM?" },
      ],
    },
    kpis: [
      { label:"Receita mês",       value:"R$ 47k",   delta:"-68% vs mai/25", deltaCls:"down", icon:"coins" },
      { label:"A receber vencido", value:"R$ 4,5M",  deltaCls:"red big",     icon:"alert",
        sub:"4.255 títulos · 76% inadimplência", emphasize:true },
      { label:"Ticket médio",      value:"R$ 1.890", delta:"-22% em 4m",     deltaCls:"down", icon:"trendDown" },
      { label:"Frota utilização",  value:"33%",      deltaCls:"info",        icon:"truck",
        sub:"30/91 · 8 paradas >7d" },
    ],
    analises: [
      { id:"inad", title:"Inadimplência", sub:"Top 20 devedores", pill:{ tone:"crit", label:"CRÍTICO" }, icon:"alert",
        kind:"buckets",
        big:{ value:"R$ 4.535.636", color:"danger" },
        buckets:[
          { label:"0–30d",   bar:18, val:"R$ 1.1M", color:"var(--warn)" },
          { label:"30–90d",  bar:14, val:"R$ 818k", color:"color-mix(in oklch, var(--warn) 70%, var(--neg))" },
          { label:"90–365d", bar:31, val:"R$ 1.8M", color:"var(--neg)" },
          { label:">365d",   bar:13, val:"R$ 770k", color:"var(--text-3)" },
        ],
        footer:"Top 1: VARGAS LEANDRO R$ 385k (246 parcelas)" },
      { id:"fat", title:"Faturamento", sub:"Curva 24 meses", pill:{ tone:"warn", label:"QUEDA" }, icon:"chart",
        kind:"sparkline",
        big:{ value:"R$ 107M", color:"ok" },
        spark:[1.0, 0.95, 1.05, 1.10, 1.15, 1.18, 1.16, 1.12, 1.08, 1.05, 1.10, 1.15, 1.18, 1.19, 1.14, 1.08, 1.02, 0.95, 0.92, 0.87, 0.82, 0.74, 0.62, 0.55],
        sparkRange:["mai/24","mai/26"],
        footer:"Melhor mês: nov/24 R$ 1.19M · Pico sazonal: out-fev" },
      { id:"conc", title:"Concentração", sub:"Top clientes Pareto", pill:{ tone:"ok", label:"OK" }, icon:"target",
        kind:"bars",
        big:{ value:"8.856 clientes" },
        bars:[
          { label:"Top 10",  bar:24, pct:"24%" },
          { label:"Top 50",  bar:55, pct:"55%" },
          { label:"Top 100", bar:73, pct:"73%" },
        ],
        footer:"4.500 one-shot (~51%) · saudável caçamba avulsa" },
      { id:"churn", title:"Churn ouro", sub:"LTV alto inativos", pill:{ tone:"react", label:"REATIVAR" }, icon:"clock",
        kind:"list",
        big:{ value:"8 clientes" },
        list:[
          { left:"CONSTRUFERRO IND.", right:"LTV R$ 87k · 124d" },
          { left:"EXTREMA SOLDAS",    right:"LTV R$ 71k · 98d"  },
          { left:"CAPITAL CARGAS",    right:"LTV R$ 62k · 112d" },
        ],
        footer:"Cohort 2024: retenção 35% (target 60%) · drift alto" },
      { id:"frota", title:"Frota", sub:"91 caçambas avulsas", pill:{ tone:"warn", label:"PARADAS" }, icon:"truck",
        kind:"donut",
        donut:{ pct:33, segs:[
          { color:"var(--accent)", pct:33 },
          { color:"var(--pos)",    pct:58 },
          { color:"var(--warn)",   pct:9 },
        ]},
        legend:[
          { color:"var(--accent)", label:"Locadas",     val:"30" },
          { color:"var(--pos)",    label:"Disponíveis", val:"61" },
          { color:"var(--warn)",   label:"Paradas >7d", val:"8", danger:true },
        ],
        footer:"3 overdue HOJE · target util 70%" },
      { id:"cheq", title:"Cheques previsão", sub:"Na mão / a depositar", icon:"receipt",
        kind:"text",
        big:{ value:"4.421 cheques" },
        text:[
          "Total circulou histórico: R$ 7.022.176",
          "Quitados: 4.420 (99,9%)",
          "Ativos hoje: 1 (R$ 8 — teste)",
        ],
        footnote:"Atalho HITL: Jana lembra Larissa qual dia depositar cada cheque" },
    ],
    acoes: [
      { id:"a1", icon:"mail",  tone:"rose", title:"Régua de cobrança · 8 clientes >90d sem contato",
        sub:"Potencial recuperação: R$ 287k · HITL aprovação a cada mensagem",
        cta:{ label:"Disparar", tone:"danger" } },
      { id:"a2", icon:"heart", tone:"violet", title:"Reativação · 8 clientes \"ouro\" inativos",
        sub:"LTV combinado R$ 612k · oferta de retorno personalizada",
        cta:{ label:"Preparar", tone:"violet" } },
      { id:"a3", icon:"truck", tone:"peach", title:"Outbound · 8 caçambas paradas há >7d",
        sub:"Top 3 últimos clientes da mesma região · ligar HOJE",
        cta:{ label:"Listar", tone:"orange" } },
      { id:"a4", icon:"trash", tone:"grey", title:"Limpeza · 2.470 títulos candidatos a baixa",
        sub:"R$ 770k incobráveis >365d · liberar dashboard",
        cta:{ label:"Revisar", tone:"dark" } },
    ],
    // Empty state — prompts iniciais
    prompts: [
      { icon:"trendDown", title:"Por que a receita caiu?", sub:"Diagnóstico do -68% em maio" },
      { icon:"coins",     title:"Quem deve mais agora?",   sub:"Top devedores ativos" },
      { icon:"clock",     title:"Clientes ouro inativos",  sub:"Quem reativar esta semana" },
      { icon:"truck",     title:"Frota parada",            sub:"Caçambas ociosas >7d" },
    ],
    // Conversa demonstrando os 4 kinds (markdown/tool_use/data_table/action_card)
    seed: [
      { from:"user", kind:"text", text:"Por que a receita caiu 68% em maio?" },
      { from:"jana", kind:"tool_use", tool:"faturamento_mensal", detail:"24 meses · biz=164" },
      { from:"jana", kind:"markdown", body:[
        "A queda é **estrutural, não sazonal**. Três fatores explicam ~85% do -68%:",
        "• **Mix de produto** mudou — caçambas pequenas/curtas substituíram as grandes, derrubando o ticket de R$ 2.430 → R$ 1.890 [1].",
        "• **Evasão de clientes ouro** — 8 contas de LTV >R$ 50k pararam de comprar nos últimos 90d [2].",
        "• Maio sempre é fraco, mas o histórico mostra **-22%**, não -68% — então ~46pp são causa nova.",
      ], sources:[
        { n:1, label:"Curva de ticket médio · 4 meses", href:"#ticket" },
        { n:2, label:"Cohort 2024 · retenção 35%", href:"#cohort" },
      ]},
      { from:"jana", kind:"data_table",
        title:"Decomposição da queda (R$ vs maio/25)",
        cols:[ {k:"causa", label:"Causa"}, {k:"valor", label:"Impacto", num:true}, {k:"peso", label:"Peso", num:true} ],
        rows:[
          { causa:"Evasão clientes ouro", valor:"−R$ 44k", peso:"45%" },
          { causa:"Queda de ticket médio", valor:"−R$ 31k", peso:"32%" },
          { causa:"Sazonalidade maio",     valor:"−R$ 13k", peso:"13%" },
          { causa:"Não explicado",         valor:"−R$ 10k", peso:"10%" },
        ] },
      { from:"jana", kind:"action_card", state:"confirm",
        title:"Disparar régua de reativação para os 8 clientes ouro?",
        sub:"LTV combinado R$ 612k · cada mensagem passa pela sua aprovação (HITL) antes de enviar." },
    ],
    suggestions:[
      { icon:"coins",   label:"Quem deve mais?" },
      { icon:"compass", label:"Onde estou perdendo?" },
      { icon:"target",  label:"Quais ações hoje?" },
      { icon:"truck",   label:"Caçambas paradas" },
    ],
  };
}

// ─── Sub-componentes ───
function RichSpan({ runs }) {
  return runs.map((r, i) => {
    const [kind, txt] = r;
    if (kind === "strong") return <strong key={i}>{txt}</strong>;
    if (kind === "danger") return <strong key={i} className="jc-danger">{txt}</strong>;
    return <React.Fragment key={i}>{txt}</React.Fragment>;
  });
}

function JanaHeader({ company, person, biz, updatedAt, onNew, isChat }) {
  return (
    <header className="jc-header">
      <div className="jc-header-l">
        <div className="jc-avatar">{person.initial}</div>
        <div className="jc-id">
          <h1>{person.name} <span className="dot">·</span> {person.role}</h1>
          <p>
            <span className="jc-tenant">{company?.name?.toUpperCase() || "OFFICEIMPRESSO"}</span>
            <span className="jc-sep">·</span>{biz.code}<span className="jc-sep">·</span>{biz.version}
          </p>
        </div>
      </div>
      <div className="jc-header-r">
        <span className="jc-updated"><span className="d"/>Atualizado {updatedAt}</span>
        {isChat
          ? <button className="jc-btn ghost" onClick={onNew}><JcIcon name="plus" className="ic"/><span>Nova conversa</span></button>
          : <button className="jc-btn ghost"><JcIcon name="settings" className="ic"/><span>Configurar</span></button>}
        <button className="jc-btn dark"><JcIcon name="download" className="ic"/><span>Exportar</span></button>
      </div>
    </header>
  );
}

function BriefDiario({ today, brief }) {
  return (
    <section className="jc-brief">
      <div className="jc-brief-h">
        <span className="jc-brief-h-l"><JcIcon name="calendar" className="ic"/> <b>Brief diário</b> <span className="sep">·</span> {today}</span>
        <span className="jc-pill ia">IA</span>
        <button className="jc-audio"><JcIcon name="play" className="ic"/> Ouvir áudio</button>
      </div>
      <p className="jc-brief-greet"><strong>{brief.greeting}</strong> <RichSpan runs={brief.paragraphs[0].body}/></p>
      <p><RichSpan runs={brief.paragraphs[1].body}/></p>
      <p className="jc-brief-action">
        <JcIcon name={brief.paragraphs[2].icon} className="ic"/> <RichSpan runs={brief.paragraphs[2].body}/>
      </p>
      <p className="jc-brief-anom"><em><RichSpan runs={brief.paragraphs[3].body}/></em></p>
      <div className="jc-brief-sep"/>
      <div className="jc-brief-chips">
        {brief.chips.map((c, i) => (
          <button key={i} className={"jc-chip " + c.tone}>
            <JcIcon name={c.icon} className="ic"/> {c.label}
          </button>
        ))}
      </div>
    </section>
  );
}

function KPICard({ kpi }) {
  return (
    <div className={"jc-kpi" + (kpi.emphasize ? " emph" : "")}>
      <div className="jc-kpi-h">
        <span>{kpi.label.toUpperCase()}</span>
        <JcIcon name={kpi.icon} className="jc-kpi-ic"/>
      </div>
      <b className={"jc-kpi-v " + (kpi.deltaCls === "red big" ? "red" : "")}>{kpi.value}</b>
      {kpi.delta && <small className={"jc-kpi-d " + (kpi.deltaCls || "")}>{kpi.delta}</small>}
      {kpi.sub && <small className="jc-kpi-d">{kpi.sub}</small>}
    </div>
  );
}

function Sparkline({ data, w = 280, h = 60 }) {
  if (!data?.length) return null;
  const min = Math.min(...data), max = Math.max(...data);
  const norm = (v) => (max === min ? 0.5 : (v - min) / (max - min));
  const xStep = w / (data.length - 1);
  const pts = data.map((v, i) => [i * xStep, h - 4 - norm(v) * (h - 10)]);
  let d = `M ${pts[0][0]} ${pts[0][1]}`;
  for (let i = 1; i < pts.length; i++) {
    const [x0, y0] = pts[i-1], [x1, y1] = pts[i];
    const cx = (x0 + x1) / 2;
    d += ` Q ${cx} ${y0}, ${cx} ${(y0+y1)/2} T ${x1} ${y1}`;
  }
  const area = d + ` L ${w} ${h} L 0 ${h} Z`;
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="jc-spark" preserveAspectRatio="none">
      <defs>
        <linearGradient id="jcSparkGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stopColor="var(--pos)" stopOpacity="0.26"/>
          <stop offset="100%" stopColor="var(--pos)" stopOpacity="0"/>
        </linearGradient>
      </defs>
      <path d={area} fill="url(#jcSparkGrad)"/>
      <path d={d} fill="none" stroke="var(--pos)" strokeWidth="2" vectorEffect="non-scaling-stroke"/>
    </svg>
  );
}

function Donut({ segs, centerLabel }) {
  const R = 32, sw = 10, C = 2 * Math.PI * R;
  let offset = 0;
  return (
    <div className="jc-donut">
      <svg viewBox="0 0 80 80" width="80" height="80">
        <circle cx="40" cy="40" r={R} fill="none" stroke="var(--sunken)" strokeWidth={sw}/>
        {segs.map((s, i) => {
          const len = (s.pct / 100) * C;
          const el = (
            <circle key={i} cx="40" cy="40" r={R} fill="none" stroke={s.color} strokeWidth={sw}
              strokeDasharray={`${len} ${C - len}`} strokeDashoffset={-offset}
              transform="rotate(-90 40 40)" strokeLinecap="butt"/>
          );
          offset += len;
          return el;
        })}
        <text x="40" y="44" textAnchor="middle" className="jc-donut-c">{centerLabel}</text>
      </svg>
    </div>
  );
}

function AnaliseCard({ a }) {
  return (
    <div className={"jc-an " + a.kind}>
      <div className="jc-an-h">
        <div className="jc-an-h-l">
          <span className="jc-an-ic"><JcIcon name={a.icon}/></span>
          <div><b>{a.title}</b><small>{a.sub}</small></div>
        </div>
        {a.pill && <span className={"jc-pill " + a.pill.tone}>{a.pill.label}</span>}
      </div>

      {a.big && <div className={"jc-an-big " + (a.big.color || "")}>{a.big.value}</div>}

      {a.kind === "buckets" && (
        <div className="jc-an-buckets">
          {a.buckets.map((b, i) => (
            <div key={i} className="jc-bk">
              <span className="jc-bk-l">{b.label}</span>
              <div className="jc-bk-bar"><div style={{width: b.bar+"%", background:b.color}}/></div>
              <span className="jc-bk-v">{b.val}</span>
            </div>
          ))}
        </div>
      )}

      {a.kind === "sparkline" && (
        <>
          <Sparkline data={a.spark}/>
          <div className="jc-spark-range"><span>{a.sparkRange[0]}</span><span>{a.sparkRange[1]}</span></div>
        </>
      )}

      {a.kind === "bars" && (
        <div className="jc-an-bars">
          {a.bars.map((b, i) => (
            <div key={i} className="jc-bar">
              <span className="jc-bar-l">{b.label}</span>
              <div className="jc-bar-track"><div style={{width:b.bar+"%"}}/></div>
              <span className="jc-bar-v">{b.pct}</span>
            </div>
          ))}
        </div>
      )}

      {a.kind === "list" && (
        <div className="jc-an-list">
          {a.list.map((it, i) => (
            <div key={i} className="jc-li"><span className="jc-li-l">{it.left}</span><span className="jc-li-r">{it.right}</span></div>
          ))}
        </div>
      )}

      {a.kind === "donut" && (
        <div className="jc-an-frota">
          <Donut segs={a.donut.segs} centerLabel={a.donut.pct + "%"}/>
          <div className="jc-an-legend">
            {a.legend.map((l, i) => (
              <div key={i} className="jc-leg">
                <span className="jc-leg-dot" style={{background:l.color}}/>
                <span className="jc-leg-l">{l.label}</span>
                <span className={"jc-leg-v" + (l.danger ? " danger":"")}>{l.val}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {a.kind === "text" && a.text && (
        <ul className="jc-an-text">
          {a.text.map((t, i) => {
            const map = ["receipt","check","clock"];
            return <li key={i}><JcIcon name={map[i] || "check"} className="m"/> {t}</li>;
          })}
        </ul>
      )}

      {a.footer && <div className="jc-an-foot">{a.footer}</div>}
      {a.footnote && <div className="jc-an-footnote">{a.footnote}</div>}
    </div>
  );
}

function AcaoRow({ a }) {
  return (
    <div className={"jc-acao tone-" + a.tone}>
      <span className="jc-acao-ic"><JcIcon name={a.icon}/></span>
      <div className="jc-acao-text"><b>{a.title}</b><small>{a.sub}</small></div>
      <button className={"jc-cta " + a.cta.tone}>{a.cta.label}</button>
    </div>
  );
}

// ─── Render de markdown leve (negrito + linhas + citações [n]) ───
function renderMarkdownLine(line, sources, onCite, key) {
  // quebra **negrito** e [n] citações
  const parts = [];
  const re = /(\*\*[^*]+\*\*|\[\d+\]|`[^`]+`)/g;
  let last = 0, m;
  let idx = 0;
  while ((m = re.exec(line)) !== null) {
    if (m.index > last) parts.push(line.slice(last, m.index));
    const tk = m[0];
    if (tk.startsWith("**")) parts.push(<strong key={"b"+idx}>{tk.slice(2,-2)}</strong>);
    else if (tk.startsWith("`")) parts.push(<code key={"c"+idx}>{tk.slice(1,-1)}</code>);
    else { const n = parseInt(tk.slice(1,-1),10); parts.push(<button key={"q"+idx} className="jc-cite" onClick={() => onCite(n)}>{n}</button>); }
    last = m.index + tk.length; idx++;
  }
  if (last < line.length) parts.push(line.slice(last));
  return <p key={key}>{parts}</p>;
}

function JanaBubble({ m, onCite }) {
  if (m.kind === "tool_use") {
    return (
      <div className="jc-tool"><JcIcon name="database"/> Consultou <code>{m.tool}</code> · {m.detail}</div>
    );
  }
  if (m.kind === "markdown") {
    return (
      <div className="jc-msg">
        <div className="jc-bub jana jc-md">
          {m.body.map((ln, i) => renderMarkdownLine(ln, m.sources, onCite, i))}
        </div>
        {m.sources && (
          <div className="jc-sources">
            {m.sources.map((s) => (
              <div key={s.n} id={"jc-src-"+s.n} className="jc-source"><span className="n">[{s.n}]</span> <a href={s.href}>{s.label}</a></div>
            ))}
          </div>
        )}
      </div>
    );
  }
  if (m.kind === "data_table") {
    return (
      <div className="jc-msg">
        <div className="jc-dtable">
          <table>
            <thead><tr>{m.cols.map(c => <th key={c.k} className={c.num ? "num":""}>{c.label}</th>)}</tr></thead>
            <tbody>
              {m.rows.map((r, i) => (
                <tr key={i}>{m.cols.map(c => <td key={c.k} className={c.num ? "num":""}>{c.k==="causa" ? <b>{r[c.k]}</b> : r[c.k]}</td>)}</tr>
              ))}
            </tbody>
          </table>
        </div>
        {m.title && <div className="jc-source" style={{marginTop:"-2px"}}><span className="n">▦</span> {m.title}</div>}
      </div>
    );
  }
  if (m.kind === "action_card") {
    const st = m.state || "confirm";
    return (
      <div className={"jc-action " + (st !== "confirm" ? st : "")}>
        <JcIcon name={st === "done" ? "check" : st === "error" ? "x" : "shield"} className="aic"/>
        <div className="jc-action-bd">
          <b>{st === "done" ? "Régua aprovada — Jana vai enviar com HITL" : st === "error" ? "Ação cancelada" : m.title}</b>
          {st === "confirm" && <small>{m.sub}</small>}
          {st === "confirm" && (
            <div className="jc-action-btns">
              <button className="jc-cta primary sm" onClick={m.onConfirm}><JcIcon name="check" className="ic"/> Confirmar</button>
              <button className="jc-cta ghost sm" onClick={m.onCancel}>Cancelar</button>
            </div>
          )}
        </div>
      </div>
    );
  }
  return null;
}

// ─── PII (CPF/CNPJ/cartão) ───
function hasPII(s) {
  if (!s) return false;
  const cpf = /\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/;
  const cnpj = /\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/;
  const card = /\b(?:\d[ -]?){16}\b/;
  return cpf.test(s) || cnpj.test(s) || card.test(s);
}

// ─── Modo Analista IA (chat single-thread) ───
function ConverseComJana({ data }) {
  const [msgs, setMsgs] = useStateJ(() => data.seed);
  const [draft, setDraft] = useStateJ("");
  const [typing, setTyping] = useStateJ(false);
  const threadRef = useRefJ(null);
  const taRef = useRefJ(null);
  const streamRef = useRefJ(null);

  const pii = hasPII(draft);

  const scrollDown = () => {
    const el = threadRef.current;
    if (el) el.scrollTop = el.scrollHeight;
  };
  useEffectJ(() => { scrollDown(); }, [msgs, typing]);

  // auto-resize textarea
  useEffectJ(() => {
    const ta = taRef.current;
    if (!ta) return;
    ta.style.height = "auto";
    const h = Math.min(ta.scrollHeight, 160);
    ta.style.height = h + "px";
    ta.style.overflowY = ta.scrollHeight > 160 ? "auto" : "hidden";
  }, [draft]);

  // atalhos globais: "/" foca · Esc desfoca · ⌘/Ctrl+Enter envia
  useEffectJ(() => {
    const onKey = (e) => {
      const el = document.activeElement;
      const inField = el && (el.tagName === "INPUT" || el.tagName === "TEXTAREA" || el.isContentEditable);
      if (e.key === "/" && !inField) { e.preventDefault(); taRef.current?.focus(); }
      else if (e.key === "Escape" && el === taRef.current) { taRef.current?.blur(); }
      else if (e.key === "Enter" && (e.metaKey || e.ctrlKey) && el === taRef.current) { e.preventDefault(); send(); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  });

  // limpa stream pendente no unmount
  useEffectJ(() => () => { if (streamRef.current) clearInterval(streamRef.current); }, []);

  function streamReply(fullLines, sources) {
    setTyping(true);
    setTimeout(() => {
      setTyping(false);
      // adiciona bubble vazia e revela linha-a-linha
      setMsgs(m => [...m, { from:"jana", kind:"markdown", body:[""], sources:null }]);
      let li = 0;
      streamRef.current = setInterval(() => {
        li++;
        setMsgs(m => {
          const copy = m.slice();
          const idx = copy.length - 1;
          copy[idx] = { ...copy[idx], body: fullLines.slice(0, li), sources: li >= fullLines.length ? sources : null };
          return copy;
        });
        if (li >= fullLines.length) { clearInterval(streamRef.current); streamRef.current = null; }
      }, 320);
    }, 650);
  }

  function send(text) {
    const q = (typeof text === "string" ? text : draft).trim();
    if (!q || typing) return;
    setMsgs(m => [...m, { from:"user", kind:"text", text:q }]);
    setDraft("");
    // resposta mock contextual
    streamReply(
      [
        "Boa pergunta. Puxei os números do período e o padrão é claro:",
        "• O movimento se concentra em **poucas contas** — risco de dependência.",
        "• Sugiro priorizar **relacionamento** com o topo da curva antes de prospectar [1].",
      ],
      [{ n:1, label:"Pareto de clientes · base atual", href:"#pareto" }]
    );
  }

  function answerAction(state) {
    setMsgs(m => m.map(x => x.kind === "action_card" ? { ...x, state } : x));
  }

  const onCite = (n) => {
    // destaca a fonte correspondente (sem scrollIntoView — regra do app)
    const src = document.getElementById("jc-src-" + n);
    if (src) { src.dataset.flash = "1"; setTimeout(() => { delete src.dataset.flash; }, 900); }
  };

  const empty = msgs.length === 0;

  return (
    <div className="jc-chat">
      <div className="jc-thread" ref={threadRef}>
        {empty ? (
          <div className="jc-empty">
            <div className="jc-avatar lg jc-empty-av">{data.person.initial}</div>
            <h3>Como posso ajudar hoje?</h3>
            <p>Pergunte sobre vendas, inadimplência, frota ou financeiro — com o contexto do seu negócio.</p>
            <div className="jc-prompts">
              {data.prompts.map((p, i) => (
                <button key={i} className="jc-prompt" onClick={() => send(p.title)}>
                  <span className="pic"><JcIcon name={p.icon}/></span>
                  <span className="ptx"><b>{p.title}</b><small>{p.sub}</small></span>
                </button>
              ))}
            </div>
            <div className="jc-empty-keys">
              <span><span className="kbd">/</span>focar</span>
              <span><span className="kbd">⌘ ↵</span>enviar</span>
              <span><span className="kbd">Esc</span>sair</span>
            </div>
          </div>
        ) : (
          <>
            {msgs.map((m, i) =>
              m.from === "user"
                ? <div key={i} className="jc-msg me"><div className="jc-bub me">{m.text}</div></div>
                : <JanaBubble key={i} m={{ ...m, onConfirm:() => answerAction("done"), onCancel:() => answerAction("error") }} onCite={onCite}/>
            )}
            {typing && (
              <div className="jc-typing"><span className="jc-dots"><i/><i/><i/></span> Jana está pensando…</div>
            )}
          </>
        )}
      </div>

      <div className="jc-composer-wrap">
        {pii && (
          <div className="jc-pii"><JcIcon name="shield"/> Conteúdo sensível detectado — Jana registra sem o dado no log de auditoria.</div>
        )}
        <div className="jc-composer">
          <textarea
            ref={taRef}
            className={pii ? "pii" : ""}
            value={draft}
            rows={1}
            onChange={e => setDraft(e.target.value)}
            onKeyDown={e => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); send(); } }}
            placeholder="Pergunte algo à Jana sobre vendas, OS, financeiro…  ( / para focar )"/>
          <button className="jc-send" onClick={() => send()} disabled={!draft.trim() || typing} aria-label="Enviar">
            <JcIcon name="send"/>
          </button>
        </div>
        {!empty && (
          <div className="jc-sugg">
            {data.suggestions.map((s, i) => (
              <button key={i} className="jc-sugg-chip" onClick={() => send(s.label)}>
                <JcIcon name={s.icon}/> {s.label}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

// ─── Componente principal ───
function JanaCockpit({ company, tab = "dashboard" }) {
  const data = getJanaData(company);
  const [chatKey, setChatKey] = useStateJ(0);
  const isChat = tab === "ia";

  if (isChat) {
    return (
      <div className="jc-page jc-page--ia" data-screen-label="Jana — Analista IA · Chat">
        <JanaHeader company={company} person={data.person} biz={data.biz} updatedAt={data.updatedAt}
                    isChat onNew={() => setChatKey(k => k + 1)}/>
        <ConverseComJana key={chatKey} data={chatKey === 0 ? data : { ...data, seed: [] }}/>
      </div>
    );
  }

  return (
    <div className="jc-page" data-screen-label="Jana — Dashboard">
      <JanaHeader company={company} person={data.person} biz={data.biz} updatedAt={data.updatedAt}/>
      <BriefDiario today={data.today} brief={data.brief}/>

      <div className="jc-kpis">
        {data.kpis.map((k, i) => <KPICard key={i} kpi={k}/>)}
      </div>

      <h2 className="jc-h2"><JcIcon name="chart" className="ic"/> ANÁLISES PRINCIPAIS</h2>
      <div className="jc-grid">
        {data.analises.map(a => <AnaliseCard key={a.id} a={a}/>)}
      </div>

      <h2 className="jc-h2"><JcIcon name="bulb" className="ic"/> AÇÕES QUE {data.person.name.toUpperCase()} SUGERE</h2>
      <div className="jc-acoes">
        {data.acoes.map(a => <AcaoRow key={a.id} a={a}/>)}
      </div>
    </div>
  );
}

window.JanaCockpit = JanaCockpit;
