// ────────────────────────────────────────────────
// CAMADA 1 — UI KIT COMPARTILHADO  (window.OIUi)
// Primitivos de layout reusados por TODAS as telas.
// Não conhece nenhuma tela específica — depende só de:
//   window.Ic (icons), window.MOCK (TENANTS/USER/NOTIFS), window.useStore, window.BRL*
// Regra de camadas: tela depende daqui; daqui NÃO se importa nenhuma screens-*.
// ────────────────────────────────────────────────
const { Ic } = window;
const { TENANTS, USER, NOTIFS } = window.MOCK;

// Header de tela (título + ações + busca opcional). Mostra "voltar" se houver pilha.
function ScreenHeader({ eyebrow, title, actions, search, sticky = true, nav }) {
  const canBack = nav && typeof nav.canPop === "function" && nav.canPop();
  return (
    <div className="oi-head" style={{ position: sticky ? "sticky" : "static", top: 0, zIndex: 5 }}>
      <div className="oi-head-row">
        {canBack && (
          <button className="oi-iconbtn" onClick={() => nav.pop()}
                  style={{ marginLeft: -8, color: "var(--accent)" }}>
            <Ic.chevL size={24} />
          </button>
        )}
        <div style={{ flex: "1 1 auto", minWidth: 0 }}>
          {eyebrow && <div className="oi-head-eyebrow">{eyebrow}</div>}
          <div className="oi-head-title">{title}</div>
        </div>
        {actions}
      </div>
      {search}
    </div>
  );
}

// Selo de origem do item (OS / FIN / CRM / MFG / PNT…)
function OriginBadge({ origin, size = "sm" }) {
  return <span className={"oi-origin o-" + origin + (size === "lg" ? " lg" : "")}>{origin}</span>;
}

// Status de etapa do pipeline de pedido (dot + texto, sem fill)
function StageStatus({ etapaKey, label }) {
  const map = {
    aprov:  { cls: "warn", txt: label || "Aguardando aprovação" },
    prod:   { cls: "accent", txt: label || "Em produção" },
    entrega:{ cls: "info", txt: label || "Saiu para entrega" },
    done:   { cls: "ok",   txt: label || "Concluído" },
    orc:    { cls: "",     txt: label || "Orçamento" },
    cancel: { cls: "danger", txt: label || "Cancelado" },
  };
  const s = map[etapaKey] || { cls: "", txt: label };
  return <span className={"oi-status " + s.cls}><span className="dot" />{s.txt}</span>;
}

// Sino de notificações com badge (interno ao HeaderTopRight)
function NotifBell({ onClick }) {
  const [read] = window.useStore("notifs.read", []);
  const unread = NOTIFS.filter(n => n.unread && !read.includes(n.id)).length;
  return (
    <div className="oi-iconbtn-wrap">
      <button className="oi-iconbtn" onClick={onClick}>
        <Ic.bell />
        {unread > 0 && <span className="badge">{unread}</span>}
      </button>
    </div>
  );
}

// Bloco padrão do canto superior direito: sino + avatar do usuário
function HeaderTopRight({ nav, tenant }) {
  const t = TENANTS.find(x => x.id === tenant) || TENANTS[0];
  return (
    <>
      <NotifBell onClick={() => nav.push("notificacoes")} />
      <button className="oi-iconbtn" onClick={() => nav.push("perfil")}>
        <div className={"oi-av " + t.color}
             style={{ width: 30, height: 30, borderRadius: "50%", fontSize: 11 }}>
          {USER.initials}
        </div>
      </button>
    </>
  );
}

// Header de tela de detalhe (seta de voltar)
function DetailHeader({ nav, title, eyebrow, actions }) {
  return (
    <div className="oi-head" style={{ paddingTop: 6 }}>
      <div className="oi-head-row" style={{ minHeight: 32 }}>
        <button className="oi-iconbtn" onClick={() => nav.pop()} style={{ marginLeft: -8, color: "var(--accent)" }}>
          <Ic.chevL size={24} />
        </button>
        <div style={{ flex: "1 1 auto", minWidth: 0 }}>
          {eyebrow && <div className="oi-head-eyebrow">{eyebrow}</div>}
          <div className="oi-head-title" style={{ fontSize: 18 }}>{title}</div>
        </div>
        {actions}
      </div>
    </div>
  );
}

window.OIUi = window.OIUi || {};
Object.assign(window.OIUi, { ScreenHeader, DetailHeader, HeaderTopRight, OriginBadge, StageStatus });
