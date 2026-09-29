// ────────────────────────────────────────────────
// EQUIPE — ponto, mecânicos e administrativo
// (Relatórios foram para screens-relatorios.jsx)
// ────────────────────────────────────────────────
const { Ic: IcR } = window;
const MR = window.MOCK;
const { DetailHeader: DHr } = window.OIUi;
const BRLr = window.BRL;

// ─── EQUIPE ───
function EquipeScreen({ nav, ctx }) {
  const mecanicos = MR.OFICINA_MECANICOS.map(m => {
    const ativas = MR.OFICINA_OS.filter(o => o.mecanico === m.id && o.status !== "Pronto");
    return { ...m, ativas: ativas.length, emServico: ativas.length > 0 };
  });
  const funcionarios = MR.CLIENTES.filter(c => (c.papeis || []).includes("funcionario"));
  const presentes = mecanicos.length + funcionarios.length - 1;
  const total = mecanicos.length + funcionarios.length;

  return (
    <>
      <DHr nav={nav} title="Equipe" eyebrow={total + " pessoas · " + mecanicos.filter(m => m.emServico).length + " em serviço"} />
      <div className="oi-scroll">
        {/* Ponto de hoje */}
        <div className="oi-section">
          <div className="oi-card pad" style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
            <div style={{ width: 46, height: 46, borderRadius: 12, background: "color-mix(in oklch, var(--ok) 18%, transparent)",
                          color: "var(--ok)", display: "grid", placeItems: "center", flex: "0 0 auto" }}>
              <IcR.user size={22} />
            </div>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Ponto de hoje</div>
              <div style={{ fontSize: 15, fontWeight: 600 }}><span className="oi-mono">{presentes}</span> de <span className="oi-mono">{total}</span> presentes</div>
            </div>
            <button className="oi-btn sm" onClick={() => window.oiToast("Abrindo escala da equipe…")}>Ver escala</button>
          </div>
        </div>

        {/* Mecânicos / produção */}
        <div className="oi-section">
          <h3 className="oi-section-h">Oficina · mecânicos</h3>
          <div className="oi-list card">
            {mecanicos.map(m => (
              <div key={m.id} className="oi-list-row" onClick={() => window.oiToast(m.nome + " — " + (m.emServico ? m.ativas + " OS em andamento" : "disponível"))}>
                <span className={"oi-av " + m.av} style={{ width: 40, height: 40, borderRadius: "50%", fontSize: 13 }}>{m.nome.slice(0, 2).toUpperCase()}</span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13.5, fontWeight: 600 }}>{m.nome}</div>
                  <div style={{ fontSize: 11.5, color: "var(--text-mute)" }}>{m.esp}</div>
                </div>
                {m.emServico ? (
                  <span className="oi-status accent" style={{ fontSize: 10.5 }}><span className="dot" />{m.ativas} OS ativa{m.ativas > 1 ? "s" : ""}</span>
                ) : (
                  <span className="oi-status ok" style={{ fontSize: 10.5 }}><span className="dot" />Disponível</span>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Administrativo / vendas */}
        <div className="oi-section">
          <h3 className="oi-section-h">Administrativo · vendas</h3>
          <div className="oi-list card">
            {funcionarios.map(c => (
              <div key={c.id} className="oi-list-row" onClick={() => nav.push("cliente", { id: c.id })}>
                <span className={"oi-av " + c.av} style={{ width: 40, height: 40, borderRadius: "50%", fontSize: 13 }}>
                  {c.nome.split(" ").slice(0, 2).map(w => w[0]).join("")}
                </span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13.5, fontWeight: 600 }}>{c.nome}</div>
                  <div style={{ fontSize: 11.5, color: "var(--text-mute)" }}>{(c.tags || [])[0] || "Funcionário"}</div>
                </div>
                <IcR.chevR size={16} color="var(--text-mute)" />
              </div>
            ))}
          </div>
        </div>
        <div style={{ height: 24 }} />
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { EquipeScreen });
