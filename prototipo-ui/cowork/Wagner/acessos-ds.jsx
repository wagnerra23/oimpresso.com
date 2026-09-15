// acessos-ds.jsx — ponte para o DS vivo (bundle compilado) nas telas do grupo Usuários.
// Mesmo idioma do superadmin-page.jsx: leitura em tempo de render (o script é defer) + fallback
// para as classes do shell quando o bundle não subiu. Tabela, botões e filtros seguem sendo do
// shell de propósito — trocar só nestas telas criaria dois padrões.
// Expõe window.AcessosDS.
(() => {
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// KPI — grid do shell + KpiCard do DS dentro
function Kpis({ children }) { return <div className="usr-kpis">{children}</div>; }
function Kpi({ v, l, sub, tone, unit }) {
  const { KpiCard } = ds();
  if (!KpiCard) return <div className="usr-kpi"><span className="usr-kpi-v">{v}</span><span className="usr-kpi-l">{l}</span></div>;
  return <KpiCard label={l} value={v} unit={unit} description={sub} tone={tone || "default"} />;
}

// Toggle
function Sw({ on, onToggle, label, sub, disabled }) {
  const { Switch } = ds();
  if (!Switch) return (
    <button type="button" className={`fnc-sw ${on ? "on" : ""}`} onClick={disabled ? undefined : onToggle}
      aria-pressed={on} disabled={disabled} aria-label={label}><i></i></button>
  );
  return <Switch checked={on} onChange={disabled ? undefined : onToggle} label={label} sublabel={sub} disabled={disabled} />;
}

// Caixa de seleção
function Chk({ on, onToggle, label }) {
  const { Checkbox } = ds();
  if (!Checkbox) return <input type="checkbox" checked={on} onChange={onToggle} aria-label={label} />;
  return <Checkbox checked={on} onChange={onToggle} />;
}

// Banner contextual
function Nota({ tone = "info", title, children }) {
  const { Alert } = ds();
  if (!Alert) return <div className={`fnc-lock ${tone === "warn" || tone === "danger" ? "warn" : ""}`}>{children}</div>;
  return <div className="acs-alert"><Alert tone={tone} title={title}>{children}</Alert></div>;
}

// Progresso (meta)
function Meta({ pct, label }) {
  const { Progress } = ds();
  if (!Progress) return (
    <div className="cms-meta">
      <span className="cms-meta-bar"><i className={pct >= 100 ? "ok" : ""} style={{ width: Math.min(pct, 100) + "%" }}></i></span>
      <span className="cms-meta-v">{pct}%</span>
    </div>
  );
  return <div className="acs-meta"><Progress value={Math.min(pct, 100)} tone={pct >= 100 ? "success" : "accent"} showValue formatValue={() => pct + "%"} size={5} label={label} /></div>;
}

// Vazio com motivo
function Vazio({ variant = "no-results", title, description, action }) {
  const { EmptyState } = ds();
  if (!EmptyState) return <div className="usr-empty">{title}</div>;
  return <EmptyState variant={variant} title={title} description={description} action={action} />;
}

// Confirmação (PT-04) — nunca modal full-screen pra detalhe
function Confirm({ open, title, children, cta, ctaTone = "danger", ctaDisabled, onConfirm, onClose, ctaAlt }) {
  const { Modal } = ds();
  if (!open) return null;
  const foot = (
    <>
      <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
      {ctaAlt}
      {cta && <button className={`os-btn ${ctaTone === "danger" ? "danger" : "primary"}`} disabled={ctaDisabled}
        onClick={() => { onConfirm?.(); onClose(); }}>{cta}</button>}
    </>
  );
  if (!Modal) return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="usr-modal"><h2>{title}</h2>{children}<div className="usr-modal-f">{foot}</div></div>
    </>
  );
  return <Modal open={open} onClose={onClose} title={title} footer={foot}>{children}</Modal>;
}

// Barra de seleção múltipla
function Bulk({ count, label, actions, onClose }) {
  const { BulkBar } = ds();
  if (!BulkBar) return (
    <div className="cmi-bulk">
      <span><b>{count}</b> {label}</span>
      {actions.map((a, i) => <button key={i} className={`os-btn ${a.tone === "danger" ? "danger" : "primary"}`} onClick={a.onClick}>{a.label}</button>)}
      <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
    </div>
  );
  return <div className="acs-bulk"><BulkBar count={count} label={label} actions={actions} onClose={onClose} /></div>;
}

// Relógios: servidor · empresa · navegador. Existe porque o fuso já causou erro de 3 h
// (config/app.php cai em Europe/London quando APP_TIMEZONE não está no .env).
function Relogios({ tzEmpresa }) {
  const [t, setT] = React.useState(new Date());
  React.useEffect(() => { const i = setInterval(() => setT(new Date()), 1000); return () => clearInterval(i); }, []);
  const fmt = (tz) => { try { return t.toLocaleTimeString("pt-BR", { timeZone: tz, hour12: false }); } catch { return "—"; } };
  const off = (tz) => {
    try {
      const s = new Intl.DateTimeFormat("en-US", { timeZone: tz, timeZoneName: "shortOffset" }).formatToParts(t).find((p) => p.type === "timeZoneName");
      return s ? s.value.replace("GMT", "UTC") : "";
    } catch { return ""; }
  };
  const local = Intl.DateTimeFormat().resolvedOptions().timeZone;
  const divergente = fmt(tzEmpresa) !== fmt(local);
  return (
    <div className="acs-clocks">
      <div><small>Empresa ({tzEmpresa})</small><b className="mono">{fmt(tzEmpresa)}</b><span>{off(tzEmpresa)}</span></div>
      <div><small>Servidor (UTC)</small><b className="mono">{fmt("UTC")}</b><span>UTC+0</span></div>
      <div className={divergente ? "acs-clock-warn" : ""}><small>Seu navegador ({local})</small><b className="mono">{fmt(local)}</b><span>{off(local)}</span></div>
    </div>
  );
}

window.AcessosDS = { Kpis, Kpi, Sw, Chk, Nota, Meta, Vazio, Confirm, Bulk, Relogios };
})();
