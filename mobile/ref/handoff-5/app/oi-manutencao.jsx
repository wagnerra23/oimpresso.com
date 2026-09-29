// ────────────────────────────────────────────────
// CAMADA 2 — HELPERS DE DOMÍNIO: MANUTENÇÃO/OFICINA  (window.OIManut)
// Tokens, formatadores e primitivos compartilhados entre oficina, oficina-os
// e equipamentos. Depende só de window.MOCK (MANUT_STATUS).
// Componentes maiores (OsCard, MiniPipe) seguem locais em screens-oficina.jsx.
// ────────────────────────────────────────────────
const M = window.MOCK;

// Mapa tom → cor (status de manutenção)
const MANUT_TONE = { danger: "var(--danger)", warn: "var(--warn)", accent: "var(--accent)", ok: "var(--ok)", muted: "var(--text-mute)" };

// Estados de um item da OS (peça/serviço) + próxima ação possível
const ITEM_STATUS = {
  aplicado:   { label: "Aplicado",   tone: "ok",     next: null,        nextLabel: null },
  aprovado:   { label: "Aprovado",   tone: "accent", next: "aplicado",  nextLabel: "Aplicar" },
  aguardando: { label: "Aguardando aprovação", tone: "warn", next: "aprovado", nextLabel: "Aprovar" },
  comprar:    { label: "Sem estoque", tone: "danger", next: "aprovado",  nextLabel: "Marcar disponível" },
};

// Duração humana (min → "45min" / "3h20" / "2d")
function mDur(min) {
  min = Math.round(Math.abs(min));
  if (min < 60) return min + "min";
  const h = Math.floor(min / 60), m = min % 60;
  if (h < 24) return m ? h + "h" + String(m).padStart(2, "0") : h + "h";
  return Math.round(h / 24) + "d";
}

// Prazo da OS → { label, tone, icon }
function mDue(os) {
  if (os.status === "Pronto") return { label: os.prazo, tone: "muted", icon: "clock" };
  if (os.dueMin < 0) return { label: "Atrasado " + mDur(os.dueMin), tone: "danger", icon: "alert" };
  if (os.dueMin <= 120) return { label: "Vence em " + mDur(os.dueMin), tone: "warn", icon: "clock" };
  return { label: os.prazo, tone: "muted", icon: "clock" };
}

// Classe de status a partir do nome do status (lê MANUT_STATUS do mock)
function statusTone(status) { return (M.MANUT_STATUS[status] || {}).tone || ""; }

// Placa estilo Mercosul (mono, contornada)
function Placa({ placa, size = "sm" }) {
  return (
    <span className="oi-mono" style={{
      display: "inline-flex", alignItems: "center",
      fontSize: size === "lg" ? 14 : 11.5, fontWeight: 700, letterSpacing: ".06em",
      padding: size === "lg" ? "3px 10px" : "1px 7px", borderRadius: 5,
      border: "1px solid var(--border)", background: "var(--bg-2)", color: "var(--text)",
    }}>{placa}</span>
  );
}

window.OIManut = window.OIManut || {};
Object.assign(window.OIManut, { Placa, mDue, mDur, statusTone, MANUT_TONE, ITEM_STATUS });
