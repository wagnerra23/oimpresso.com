// PageHeader — operational page header
function PageHeader({ title, description, icon, action }) {
  return (
    <div className="page-header">
      <div className="page-header__main">
        {icon && <div className="page-header__ico" aria-hidden><Icon name={icon} size={20}/></div>}
        <div style={{minWidth: 0}}>
          <h1 className="page-header__title">{title}</h1>
          {description && <p className="page-header__desc">{description}</p>}
        </div>
      </div>
      {action && <div className="page-header__action">{action}</div>}
    </div>
  );
}
window.PageHeader = PageHeader;

// StatusBadge — minimal mapping
const STATUS_MAP = {
  intercorrencia: {
    rascunho: { label: 'Rascunho', cls: 'b-outline' },
    pendente: { label: 'Pendente', cls: 'b-secondary' },
    aprovada: { label: 'Aprovada', cls: 'b-emerald' },
    rejeitada: { label: 'Rejeitada', cls: 'b-rose' },
    aplicada: { label: 'Aplicada', cls: 'b-blue' },
    cancelada: { label: 'Cancelada', cls: 'b-outline' },
  },
  prioridade: {
    baixa: { label: 'Baixa', cls: 'b-outline' },
    normal: { label: 'Normal', cls: 'b-secondary' },
    alta: { label: 'Alta', cls: 'b-rose' },
    urgente: { label: 'Urgente', cls: 'b-rose b-pulse' },
  },
  payment: {
    pending: { label: 'Pendente', cls: 'b-secondary' },
    partial: { label: 'Parcial', cls: 'b-amber' },
    paid: { label: 'Pago', cls: 'b-emerald' },
    overdue: { label: 'Atrasado', cls: 'b-rose' },
  },
};
function StatusBadge({ kind, value, label }) {
  const dict = STATUS_MAP[kind] || {};
  const e = dict[value?.toLowerCase?.() ?? value];
  if (!e) return <span className="badge b-outline">{label || value}</span>;
  return <span className={"badge " + e.cls}>{label || e.label}</span>;
}
window.StatusBadge = StatusBadge;

// KpiCard
function KpiCard({ label, value, icon, description, delta, deltaLabel, tone = 'default' }) {
  const goodUp = delta != null && delta >= 0;
  return (
    <div className={"kpi-card kpi-card--" + tone}>
      <div className="kpi-card__head">
        <span className="kpi-card__lbl">{label}</span>
        {icon && <div className={"kpi-card__ico kpi-card__ico--" + tone}><Icon name={icon} size={16}/></div>}
      </div>
      <div className="kpi-card__row">
        <span className="kpi-card__v">{value}</span>
        {delta != null && (
          <span className={"kpi-card__delta " + (goodUp ? 'up' : 'dn')}>
            <Icon name={goodUp ? 'arrow-up-right' : 'arrow-down-right'} size={12}/>
            {goodUp ? '+' : ''}{delta}{deltaLabel && <span style={{marginLeft:4, color:'var(--fg-2)'}}>{deltaLabel}</span>}
          </span>
        )}
      </div>
      {description && <p className="kpi-card__desc">{description}</p>}
    </div>
  );
}
window.KpiCard = KpiCard;
