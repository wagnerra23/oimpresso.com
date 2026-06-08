// chat-jana.jsx — Cockpit do Analista IA (substitui o chat tradicional).
// Conceito: a IA é uma analista (Jana) que entrega um brief diário,
// monitora KPIs, detecta anomalias, sugere ações com HITL e responde via chat.
const { useState: useStateJ, useRef: useRefJ, useEffect: useEffectJ } = React;

// ─── Dados mock (estrutura do Martinho Caçambas — adaptável por empresa) ───
function getJanaData(company) {
  // Por enquanto retorna o mesmo dataset; futuramente plugar por company.id
  return {
    person: { name: "Jana", role: "Analista IA", avatar: "🤖" },
    biz: { code: "biz=164", version: "v1404 legacy migrado" },
    updatedAt: "09:42",
    today: "14/maio/2026",
    brief: {
      greeting: "Bom dia, Wagner.",
      paragraphs: [
        { kind:"text", body:[
          ["normal", "Maio até hoje somou "],
          ["strong", "R$ [redacted Tier 0]"],
          ["normal", " (vs R$ [redacted Tier 0]k em maio/25 — "],
          ["danger", "-68%"],
          ["normal", " · investigar sazonalidade ou causa estrutural)."]
        ]},
        { kind:"text", body:[
          ["danger", "R$ [redacted Tier 0]"],
          ["normal", " em 4.255 títulos vencidos (excluído lixo de saldos virtuais e parcelas agrupadas). "],
          ["strong", "Top 20 clientes concentram R$ [redacted Tier 0]k (~47%)"],
          ["normal", " da inadimplência."]
        ]},
        { kind:"action", icon:"🎯", body:[
          ["strong", "Ação sugerida HOJE: "],
          ["normal", "8 clientes \"ouro\" (LTV >R$ [redacted Tier 0]k) estão >90d sem comprar — score reativação alto. Posso disparar régua WhatsApp HITL?"]
        ]},
        { kind:"anomaly", body:[
          ["normal", "Anomalia detectada: ticket médio caiu de R$ [redacted Tier 0] para R$ [redacted Tier 0] (-22%) — 4 meses consecutivos. Margem mantida (preço por m³ estável) → indica mix de produto mudando (mais caçambas pequenas/curtas)."]
        ]},
      ],
      chips: [
        { tone:"primary", icon:"📨", label:"Disparar régua 8 clientes" },
        { tone:"ghost",   icon:"📋", label:"Ver top 20 devedores" },
        { tone:"ghost",   icon:"🔍", label:"Investigar queda ticket médio" },
        { tone:"ghost",   icon:"💡", label:"Por que -68% MoM?" },
      ],
    },
    kpis: [
      { label:"Receita mês",       value:"R$ [redacted Tier 0]k",  delta:"↓ -68% vs mai/25",    deltaCls:"down", icon:"💰" },
      { label:"A receber vencido", value:"R$ [redacted Tier 0]M", deltaCls:"red big",          icon:"🚨",
        sub:"4.255 títulos · 76% inadimplência", emphasize:true },
      { label:"Ticket médio",      value:"R$ [redacted Tier 0]",delta:"↓ -22% 4m",           deltaCls:"down", icon:"📈" },
      { label:"Frota utilização",  value:"33%",     deltaCls:"info",             icon:"🚚",
        sub:"30/91 · 8 paradas >7d" },
    ],
    analises: [
      { id:"inad", title:"Inadimplência", sub:"Top 20 devedores", pill:{ tone:"crit", label:"CRÍTICO" }, icon:"🚨",
        kind:"buckets",
        big:{ value:"R$ [redacted Tier 0]", color:"danger" },
        buckets:[
          { label:"0–30d",   bar:18, val:"R$ [redacted Tier 0]M",  color:"#d4910f" },
          { label:"30–90d",  bar:14, val:"R$ [redacted Tier 0]k",  color:"#e0791a" },
          { label:"90–365d", bar:31, val:"R$ [redacted Tier 0]M",  color:"#d65a3a" },
          { label:">365d",   bar:13, val:"R$ [redacted Tier 0]k",  color:"#2a2a2a" },
        ],
        footer:"Top 1: VARGAS LEANDRO R$ [redacted Tier 0]k (246 parcelas)" },
      { id:"fat", title:"Faturamento", sub:"Curva 24 meses", pill:{ tone:"warn", label:"QUEDA" }, icon:"📈",
        kind:"sparkline",
        big:{ value:"R$ [redacted Tier 0]M", color:"ok" },
        spark:[1.0, 0.95, 1.05, 1.10, 1.15, 1.18, 1.16, 1.12, 1.08, 1.05, 1.10, 1.15, 1.18, 1.19, 1.14, 1.08, 1.02, 0.95, 0.92, 0.87, 0.82, 0.74, 0.62, 0.55],
        sparkRange:["mai/24","mai/26"],
        footer:"Melhor mês: nov/24 R$ [redacted Tier 0]M · Pico sazonal: out-fev" },
      { id:"conc", title:"Concentração", sub:"Top clientes Pareto", pill:{ tone:"ok", label:"OK" }, icon:"🎯",
        kind:"bars",
        big:{ value:"8.856 clientes" },
        bars:[
          { label:"Top 10",  bar:24,  pct:"24%" },
          { label:"Top 50",  bar:55,  pct:"55%" },
          { label:"Top 100", bar:73,  pct:"73%" },
        ],
        footer:"4.500 one-shot (~51%) · saudável caçamba avulsa" },
      { id:"churn", title:"Churn ouro", sub:"LTV alto inativos", pill:{ tone:"react", label:"REATIVAR" }, icon:"⏰",
        kind:"list",
        big:{ value:"8 clientes" },
        list:[
          { left:"CONSTRUFERRO IND.", right:"LTV R$ [redacted Tier 0]k · 124d" },
          { left:"EXTREMA SOLDAS",    right:"LTV R$ [redacted Tier 0]k · 98d"  },
          { left:"CAPITAL CARGAS",    right:"LTV R$ [redacted Tier 0]k · 112d" },
        ],
        footer:"Cohort 2024: retenção 35% (target 60%) · drift alto" },
      { id:"frota", title:"Frota", sub:"91 caçambas avulsas", pill:{ tone:"warn", label:"PARADAS" }, icon:"🚛",
        kind:"donut",
        donut:{ pct:33, segs:[
          { color:"#2563eb", pct:33 },   // locadas
          { color:"#22c55e", pct:67 - 9 },// disponíveis
          { color:"#e0791a", pct:9 },    // paradas
        ]},
        legend:[
          { color:"#2563eb", label:"Locadas",     val:"30" },
          { color:"#22c55e", label:"Disponíveis", val:"61" },
          { color:"#e0791a", label:"Paradas >7d", val:"8", danger:true },
        ],
        footer:"3 overdue HOJE · target util 70%" },
      { id:"cheq", title:"Cheques previsão", sub:"Na mão / a depositar", icon:"🧾",
        kind:"text",
        big:{ value:"4.421 cheques" },
        text:[
          "Total circulou histórico: R$ [redacted Tier 0]",
          "Quitados: 4.420 (99,9%)",
          "Ativos hoje: 1 (R$ [redacted Tier 0] — teste)",
        ],
        footnote:"Atalho HITL: Jana lembra Larissa qual dia depositar cada cheque" },
    ],
    acoes: [
      { id:"a1", icon:"📨", tone:"rose", title:"Régua WhatsApp · 8 clientes >90d sem contato",
        sub:"Potencial recuperação: R$ [redacted Tier 0]k · HITL aprovação cada msg",
        cta:{ label:"Disparar", tone:"danger" } },
      { id:"a2", icon:"❤️", tone:"violet", title:"Reativação · 8 clientes \"ouro\" inativos",
        sub:"LTV combinado R$ [redacted Tier 0]k · oferta retorno personalizada",
        cta:{ label:"Preparar", tone:"violet" } },
      { id:"a3", icon:"🚛", tone:"peach", title:"Outbound · 8 caçambas paradas há >7d",
        sub:"Top 3 últimos clientes mesma região · ligar HOJE",
        cta:{ label:"Listar", tone:"orange" } },
      { id:"a4", icon:"🗑️", tone:"grey", title:"Cleanup · 2.470 títulos write-off candidatos",
        sub:"R$ [redacted Tier 0]k incobráveis >365d · liberar dashboard",
        cta:{ label:"Revisar", tone:"dark" } },
    ],
    chat: {
      messages: [
        { from:"user", text:"Quais os top 5 devedores Martinho agora?" },
        { from:"jana", kind:"list-card", title:"Top 5 devedores ativos (sem agrupados duplicados):",
          items:[
            "1. **VARGAS LEANDRO COM. VAREJISTA** — R$ [redacted Tier 0] (229 parcelas)",
            "2. **TORK COMERCIO DE PECAS AUTO** — R$ [redacted Tier 0] (167 parcelas)",
            "3. **AMS SOLDAS E MAQUINAS** — R$ [redacted Tier 0] (71 parcelas)",
            "4. **BUSSOLO E PRUDENCIO** — R$ [redacted Tier 0] (43 parcelas)",
            "5. **FAN COM. DE PECAS E IMPLEMENTOS** — R$ [redacted Tier 0] (166 parcelas)",
          ],
          footnote:"Total top 5: R$ [redacted Tier 0]k (~20% inadimplência). VARGAS sozinho concentra 8,5% — risco alto, mas é cliente recorrente (229 parcelas) então tem relacionamento.",
          actions:[
            { label:"Régua VARGAS", icon:"📨", tone:"primary" },
            { label:"Histórico contatos", icon:"📞", tone:"ghost" },
          ] },
        { from:"user", text:"Por que receita caiu 68% em maio?" },
      ],
      suggestions:[
        { icon:"🤔", label:"Quem deve mais?" },
        { icon:"💸", label:"Vendi ontem?" },
        { icon:"🧭", label:"Onde estou perdendo?" },
        { icon:"🎯", label:"Quais ações HOJE?" },
        { icon:"🚛", label:"Caçambas paradas" },
      ],
    },
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

function JanaHeader({ company, person, biz, updatedAt }) {
  return (
    <header className="jc-header">
      <div className="jc-header-l">
        <div className="jc-avatar">{person.avatar}</div>
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
        <button className="jc-btn ghost"><span>⚙</span> Configurar</button>
        <button className="jc-btn dark"><span>⬇</span> Exportar</button>
      </div>
    </header>
  );
}

function BriefDiario({ today, brief }) {
  return (
    <section className="jc-brief">
      <div className="jc-brief-h">
        <span className="jc-brief-h-l"><span className="ic">📅</span> <b>Brief diário</b> <span className="sep">·</span> {today}</span>
        <span className="jc-pill ia">IA</span>
        <button className="jc-audio">▶ Ouvir áudio</button>
      </div>
      <p className="jc-brief-greet"><strong>{brief.greeting}</strong> <RichSpan runs={brief.paragraphs[0].body}/></p>
      <p>{<RichSpan runs={brief.paragraphs[1].body}/>}</p>
      <p className="jc-brief-action">
        <span className="ic">{brief.paragraphs[2].icon}</span> <RichSpan runs={brief.paragraphs[2].body}/>
      </p>
      <p className="jc-brief-anom"><em><RichSpan runs={brief.paragraphs[3].body}/></em></p>
      <div className="jc-brief-sep"/>
      <div className="jc-brief-chips">
        {brief.chips.map((c, i) => (
          <button key={i} className={"jc-chip " + c.tone}>
            <span className="ic">{c.icon}</span> {c.label}
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
        <span className="jc-kpi-ic">{kpi.icon}</span>
      </div>
      <b className={"jc-kpi-v " + (kpi.deltaCls === "red big" ? "red" : "")}>{kpi.value}</b>
      {kpi.delta && <small className={"jc-kpi-d " + (kpi.deltaCls || "")}>{kpi.delta}</small>}
      {kpi.sub && <small className="jc-kpi-d">{kpi.sub}</small>}
    </div>
  );
}

// Spark line SVG (curva suave)
function Sparkline({ data, w = 280, h = 60 }) {
  if (!data?.length) return null;
  const min = Math.min(...data);
  const max = Math.max(...data);
  const norm = (v) => (max === min ? 0.5 : (v - min) / (max - min));
  const xStep = w / (data.length - 1);
  const pts = data.map((v, i) => [i * xStep, h - 4 - norm(v) * (h - 10)]);
  // path com curva smooth
  let d = `M ${pts[0][0]} ${pts[0][1]}`;
  for (let i = 1; i < pts.length; i++) {
    const [x0, y0] = pts[i-1];
    const [x1, y1] = pts[i];
    const cx = (x0 + x1) / 2;
    d += ` Q ${cx} ${y0}, ${cx} ${(y0+y1)/2} T ${x1} ${y1}`;
  }
  // área embaixo
  let area = d + ` L ${w} ${h} L 0 ${h} Z`;
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="jc-spark">
      <defs>
        <linearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stopColor="oklch(0.62 0.16 145)" stopOpacity="0.28"/>
          <stop offset="100%" stopColor="oklch(0.62 0.16 145)" stopOpacity="0"/>
        </linearGradient>
      </defs>
      <path d={area} fill="url(#sparkGrad)"/>
      <path d={d} fill="none" stroke="oklch(0.55 0.16 145)" strokeWidth="2"/>
    </svg>
  );
}

// Donut SVG simples (segmentos coloridos + centro)
function Donut({ segs, centerLabel }) {
  const R = 32, sw = 10, C = 2 * Math.PI * R;
  let offset = 0;
  return (
    <div className="jc-donut">
      <svg viewBox="0 0 80 80" width="80" height="80">
        <circle cx="40" cy="40" r={R} fill="none" stroke="#efeae0" strokeWidth={sw}/>
        {segs.map((s, i) => {
          const len = (s.pct / 100) * C;
          const dash = `${len} ${C - len}`;
          const el = (
            <circle key={i} cx="40" cy="40" r={R}
              fill="none" stroke={s.color} strokeWidth={sw}
              strokeDasharray={dash} strokeDashoffset={-offset}
              transform="rotate(-90 40 40)"
              strokeLinecap="butt"/>
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
  const pillCls = a.pill ? "jc-pill " + a.pill.tone : "";
  return (
    <div className={"jc-an " + a.kind}>
      <div className="jc-an-h">
        <div className="jc-an-h-l">
          <span className="jc-an-ic" data-kind={a.id}>{a.icon}</span>
          <div>
            <b>{a.title}</b>
            <small>{a.sub}</small>
          </div>
        </div>
        {a.pill && <span className={pillCls}>{a.pill.label}</span>}
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
          <div className="jc-spark-range">
            <span>{a.sparkRange[0]}</span>
            <span>{a.sparkRange[1]}</span>
          </div>
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
            <div key={i} className="jc-li">
              <span className="jc-li-l">{it.left}</span>
              <span className="jc-li-r">{it.right}</span>
            </div>
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
            // primeiro caractere é o emoji do marker
            const map = ["📜","✅","🟡"];
            return <li key={i}><span className="m">{map[i] || "·"}</span> {t}</li>;
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
      <span className="jc-acao-ic">{a.icon}</span>
      <div className="jc-acao-text">
        <b>{a.title}</b>
        <small>{a.sub}</small>
      </div>
      <button className={"jc-cta " + a.cta.tone}>{a.cta.label}</button>
    </div>
  );
}

function ConverseComJana({ chat, person, variant = "compact" }) {
  const [draft, setDraft] = useStateJ("");
  const [msgs, setMsgs] = useStateJ(chat.messages);
  const onSend = () => {
    if (!draft.trim()) return;
    setMsgs(m => [...m, { from:"user", text: draft.trim() }]);
    setDraft("");
  };
  return (
    <section className={"jc-converse " + (variant === "full" ? "jc-converse--full" : "")}>
      <div className="jc-converse-h">
        <b>💬 CONVERSE COM {person.name.toUpperCase()}</b>
        <small>Chat com contexto do seu negócio</small>
      </div>
      <div className="jc-thread">
        {msgs.map((m, i) =>
          m.from === "user"
            ? <div key={i} className="jc-bub jc-bub-user">{m.text}</div>
            : <div key={i} className="jc-bub jc-bub-jana">
                {m.title && <b className="jc-bub-title">{m.title}</b>}
                {m.kind === "list-card" && (
                  <>
                    <ul className="jc-bub-list">
                      {m.items.map((it, j) => {
                        // marca **negrito**
                        const parts = it.split(/\*\*(.+?)\*\*/);
                        return <li key={j}>{parts.map((p, k) => k % 2 === 1 ? <b key={k}>{p}</b> : p)}</li>;
                      })}
                    </ul>
                    {m.footnote && <p className="jc-bub-foot"><em>{m.footnote}</em></p>}
                    {m.actions && (
                      <div className="jc-bub-actions">
                        {m.actions.map((act, k) => (
                          <button key={k} className={"jc-cta sm " + act.tone}>
                            <span>{act.icon}</span> {act.label}
                          </button>
                        ))}
                      </div>
                    )}
                  </>
                )}
              </div>
        )}
      </div>
      <div className="jc-composer">
        <input
          value={draft}
          onChange={e => setDraft(e.target.value)}
          onKeyDown={e => e.key === "Enter" && !e.shiftKey && onSend()}
          placeholder={`Pergunte algo sobre o ${(/* fallback */ "Martinho")}…`}/>
        <button className="jc-cta primary" onClick={onSend}>Enviar</button>
      </div>
      <div className="jc-sugg">
        {chat.suggestions.map((s, i) => (
          <button key={i} className="jc-sugg-chip" onClick={() => setDraft(s.label)}>
            <span className="ic">{s.icon}</span> {s.label}
          </button>
        ))}
      </div>
    </section>
  );
}

// ─── Componente principal ───
function JanaCockpit({ company, tab = "dashboard" }) {
  const data = getJanaData(company);
  const isChat = tab === "ia";

  if (isChat) {
    return (
      <div className="jc-page jc-page--ia" data-screen-label="Jana — Analista IA · Chat">
        <JanaHeader company={company} person={data.person} biz={data.biz} updatedAt={data.updatedAt}/>
        <ConverseComJana chat={data.chat} person={data.person} variant="full"/>
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

      <h2 className="jc-h2"><span className="ic">📊</span> ANÁLISES PRINCIPAIS</h2>
      <div className="jc-grid">
        {data.analises.map(a => <AnaliseCard key={a.id} a={a}/>)}
      </div>

      <h2 className="jc-h2"><span className="ic">💡</span> AÇÕES QUE {data.person.name.toUpperCase()} SUGERE</h2>
      <div className="jc-acoes">
        {data.acoes.map(a => <AcaoRow key={a.id} a={a}/>)}
      </div>
    </div>
  );
}

window.JanaCockpit = JanaCockpit;
