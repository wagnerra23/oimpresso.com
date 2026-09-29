// Dashboard widgets + order record blocks — replicação fiel do modelo.
// Depende de window.Ic (icons.jsx) e window.BRL. Exporta tudo p/ window.
const { Ic } = window;

// ──────────────────────────────────────────────
// Pipeline canônico de um pedido (5 etapas)
// ──────────────────────────────────────────────
const PIPELINE = [
  { key: "orc",     label: "Orçamento", ic: "file" },
  { key: "arte",    label: "Arte",      ic: "image" },
  { key: "aprov",   label: "Aprovação", ic: "checkCircle" },
  { key: "prod",    label: "Produção",  ic: "printer" },
  { key: "entrega", label: "Entrega",   ic: "truck" },
];
const STAGE_IDX = { orc: 0, arte: 1, aprov: 2, prod: 3, entrega: 4, done: 5 };
const STATUS_COLOR = {
  orc: "var(--text-mute)", arte: "var(--accent)", aprov: "var(--warn)",
  prod: "var(--accent)", entrega: "var(--info)", done: "var(--ok)",
};

// ──────────────────────────────────────────────
// Sparkline — usa currentColor (pai define a cor)
// ──────────────────────────────────────────────
function Sparkline({ data, area = false, sw = 2, w = 120, h = 40 }) {
  const min = Math.min(...data), max = Math.max(...data);
  const rng = (max - min) || 1;
  const stepX = w / (data.length - 1);
  const pad = sw + 1;
  const pts = data.map((d, i) => [i * stepX, h - ((d - min) / rng) * (h - pad * 2) - pad]);
  const line = pts.map((p, i) => (i ? "L" : "M") + p[0].toFixed(1) + " " + p[1].toFixed(1)).join(" ");
  const areaD = line + " L " + w + " " + h + " L 0 " + h + " Z";
  const gid = React.useMemo(() => "sg" + Math.random().toString(36).slice(2, 8), []);
  return (
    <svg viewBox={"0 0 " + w + " " + h} width="100%" height="100%"
         preserveAspectRatio="none" style={{ display: "block", overflow: "visible" }}>
      {area && (
        <>
          <defs>
            <linearGradient id={gid} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="currentColor" stopOpacity="0.34" />
              <stop offset="1" stopColor="currentColor" stopOpacity="0" />
            </linearGradient>
          </defs>
          <path d={areaD} fill={"url(#" + gid + ")"} stroke="none" />
        </>
      )}
      <path d={line} fill="none" stroke="currentColor" strokeWidth={sw}
            strokeLinejoin="round" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}
const TONE_VAR = { ok: "var(--ok)", danger: "var(--danger)", accent: "var(--accent-2)" };

// ──────────────────────────────────────────────
// Product thumbnail — tile com ícone do tipo
// ──────────────────────────────────────────────
function ProductThumb({ thumb = "file", size = 58 }) {
  const ico = { tag: "tag", file: "file", image: "image", printer: "printer", box: "box" }[thumb] || "file";
  return (
    <div className={"oi-thumb t-" + thumb} style={{ width: size, height: size }}>
      {React.createElement(Ic[ico] || Ic.file, { size: Math.round(size * 0.42), style: { position: "relative", zIndex: 1 } })}
    </div>
  );
}

// ──────────────────────────────────────────────
// Order stepper — espaçamento uniforme, conectores coloridos
// ──────────────────────────────────────────────
function OrderStepper({ etapaKey, size = "sm" }) {
  const cur = STAGE_IDX[etapaKey] != null ? STAGE_IDX[etapaKey] : 0;
  const statusC = STATUS_COLOR[etapaKey] || "var(--accent)";
  const lg = size === "lg";
  const NS = lg ? 30 : 18;          // node size
  const cY = NS / 2 - 1;            // connector y (centraliza no nó)
  const n = PIPELINE.length;

  // cor do conector que CHEGA no nó i
  const segColor = (i) => (i < cur ? "var(--ok)" : i === cur ? statusC : "var(--border)");

  return (
    <div className={"oi-stepper" + (lg ? " lg" : "")}>
      {PIPELINE.map((st, i) => {
        const done = i < cur;
        const isCur = i === cur;
        let nodeBg, nodeBorder, content;
        if (done) {
          nodeBg = "var(--ok)"; nodeBorder = "var(--ok)";
          content = <Ic.check size={lg ? 15 : 11} color="#fff" strokeWidth={3} />;
        } else if (isCur) {
          nodeBg = statusC; nodeBorder = statusC;
          content = lg
            ? React.createElement(Ic[st.ic], { size: 15, color: "#fff" })
            : <span style={{ width: 6, height: 6, borderRadius: 99, background: "#fff" }} />;
        } else {
          nodeBg = "var(--surface)"; nodeBorder = "var(--border)";
          content = lg
            ? React.createElement(Ic[st.ic], { size: 14, color: "var(--text-mute)" })
            : null;
        }
        return (
          <div key={st.key} className={"col " + (done ? "done" : isCur ? "cur" : "")}
               style={{ flex: 1, position: "relative" }}>
            {i > 0 && (
              <span style={{ position: "absolute", left: 0, right: "50%", top: cY, height: 2, background: segColor(i) }} />
            )}
            {i < n - 1 && (
              <span style={{ position: "absolute", left: "50%", right: 0, top: cY, height: 2, background: segColor(i + 1) }} />
            )}
            <div className="node" style={{
              width: NS, height: NS, position: "relative", zIndex: 1,
              background: nodeBg, border: "2px solid " + nodeBorder,
              boxShadow: isCur ? "0 0 0 3px color-mix(in oklch, " + statusC + " 22%, transparent)" : "none",
            }}>
              {content}
            </div>
            <div className="lab">{st.label}</div>
          </div>
        );
      })}
    </div>
  );
}

// ──────────────────────────────────────────────
// KPI cube tile
// ──────────────────────────────────────────────
function CubeTile({ tile, onClick }) {
  return (
    <div className={"oi-card oi-cubetile t-" + tile.tone} onClick={onClick} style={onClick ? { cursor: "pointer" } : null}>
      <span className="cube"><Ic.box size={18} /></span>
      <small>{tile.label}</small>
      <div className={"v" + (tile.money ? " money" : "")}>{tile.value}</div>
      <div className={"sub " + (tile.subTone || "mute")}>{tile.sub}</div>
    </div>
  );
}

// ──────────────────────────────────────────────
// Sparkline card (recebido / pendente)
// ──────────────────────────────────────────────
function SparkCard({ item }) {
  return (
    <div className="oi-card oi-sparkcard">
      <div className="lbl">{item.label}</div>
      <div className="v">{item.value}</div>
      <div className="sub">{item.sub}</div>
      <div className="spark" style={{ color: TONE_VAR[item.tone] || "var(--accent-2)" }}>
        <Sparkline data={item.data} area sw={2} />
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
// Mini weekly trend
// ──────────────────────────────────────────────
function MiniTrend({ item }) {
  return (
    <div className="oi-card oi-mini">
      <div className="lbl">{item.label}</div>
      <div className="v">{item.value}</div>
      <div className="delta"><Ic.trendingUp size={12} /> {item.delta}</div>
      <div className="spark" style={{ color: "var(--accent-2)" }}>
        <Sparkline data={item.data} sw={1.6} />
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
// Assistente Office
// ──────────────────────────────────────────────
function AssistCard({ items, nav }) {
  return (
    <div className="oi-card oi-assist">
      <div className="oi-assist-bot">
        <Ic.bot size={34} />
      </div>
      <div className="oi-assist-list">
        {items.map((it, i) => (
          <div key={i} className="oi-assist-row" onClick={() => it.tab && nav.gotoTab(it.tab)}>
            <span className="t">{it.text}</span>
            {it.time && <span className="when">{it.time}</span>}
            <Ic.chevR size={15} color="var(--text-mute)" />
          </div>
        ))}
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
// Order record card (bloco de registro de pedido)
// ──────────────────────────────────────────────
function OrderCard({ p, nav }) {
  const statusC = STATUS_COLOR[p.etapaKey] || "var(--accent)";
  return (
    <div className="oi-card oi-ordercard" onClick={() => nav.push("pedido", { id: p.id })}>
      {p.urgent && <span className="edge" style={{ background: "var(--danger)" }} />}
      <div className="o-top">
        <span className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)", fontWeight: 600 }}>{p.id}</span>
        <window.Screens.StageStatus etapaKey={p.etapaKey} label={p.etapa} />
        <span className="oi-money" style={{ marginLeft: "auto", fontSize: 14, fontWeight: 700 }}>{window.BRL(p.valor)}</span>
      </div>
      <div className="o-body" style={{ marginTop: 11 }}>
        <ProductThumb thumb={p.thumb} size={62} />
        <div style={{ flex: "1 1 auto", minWidth: 0 }}>
          <div className="o-name">{p.nome}</div>
          <div className="o-specs">{p.specs}</div>
          <div style={{ display: "flex", alignItems: "baseline", gap: 8, marginTop: 3 }}>
            <span className="o-cli" style={{ flex: "1 1 auto", minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
              Cliente: {p.cliente}
            </span>
            {p.prazo && p.prazo !== "—" && (
              <span style={{ fontSize: 11, fontWeight: 600, fontFamily: "var(--font-mono)",
                             color: p.urgent ? "var(--danger)" : "var(--text-mute)" }}>{p.prazo}</span>
            )}
          </div>
        </div>
      </div>
      <div style={{ marginTop: 13 }}>
        <OrderStepper etapaKey={p.etapaKey} size="sm" />
      </div>
    </div>
  );
}

window.Widgets = {
  Sparkline, ProductThumb, OrderStepper, CubeTile, SparkCard, MiniTrend, AssistCard, OrderCard,
  PIPELINE, STAGE_IDX, STATUS_COLOR,
};
