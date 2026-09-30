// Task viewers — per-origin component that resolves the action inline
const { Ic } = window;
const BRL = window.BRL;

function VwCard({ title, children, dense }) {
  return (
    <div className="oi-section" style={dense ? { paddingTop: 8 } : {}}>
      {title && <h3 className="oi-section-h">{title}</h3>}
      <div className="oi-card pad">{children}</div>
    </div>
  );
}

function OsAprovarArte({ t }) {
  const [decision, setDecision] = React.useState("");
  return (
    <>
      <VwCard title="Arte para aprovação">
        <div style={{ display: "flex", gap: 12, alignItems: "center", padding: "10px 0" }}>
          <div style={{ width: 56, height: 56, borderRadius: 10, background: "var(--bg-2)",
                        border: "1px solid var(--border)", display: "grid", placeItems: "center",
                        color: "var(--text-mute)" }}>
            <Ic.image size={26} />
          </div>
          <div style={{ flex: "1 1 auto", minWidth: 0 }}>
            <div style={{ fontWeight: 600, fontSize: 14 }}>{t.arte}</div>
            <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>v3 · 2.4 MB · CMYK 300dpi</div>
          </div>
          <button className="oi-btn sm" onClick={() => window.oiToast("Abrindo pré-visualização da arte…")}><Ic.eye size={14} /> Ver</button>
        </div>
      </VwCard>

      <VwCard title="Detalhes da OS">
        <dl className="oi-dl">
          <dt>OS</dt><dd className="oi-mono">{t.id.replace("t-os-", "OS-").toUpperCase()}</dd>
          <dt>Cliente</dt><dd>{t.cliente}</dd>
          <dt>Produto</dt><dd>1.000 cartões 9x5 4/4</dd>
          <dt>Prazo</dt><dd style={{ color: "var(--danger)", fontWeight: 600 }}>{t.prazo}</dd>
          <dt>Valor</dt><dd className="oi-money" style={{ fontWeight: 600 }}>{BRL(t.valor)}</dd>
        </dl>
      </VwCard>

      <VwCard title="Decisão">
        <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
          {[
            { v: "ok", l: "Aprovar e liberar produção", ic: "check-circle" },
            { v: "ajuste", l: "Pedir ajuste ao cliente", ic: "edit" },
            { v: "reprovar", l: "Reprovar arte", ic: "x" },
          ].map(o => (
            <button key={o.v} onClick={() => setDecision(o.v)}
                    className="oi-btn"
                    style={{ justifyContent: "flex-start", borderColor: decision === o.v ? "var(--accent)" : "var(--border)",
                             background: decision === o.v ? "var(--accent-soft)" : "var(--surface)",
                             color: decision === o.v ? "var(--accent)" : "var(--text)" }}>
              {React.createElement(Ic[o.ic], { size: 18 })}
              <span>{o.l}</span>
            </button>
          ))}
        </div>
      </VwCard>
    </>
  );
}

function FinBoleto({ t }) {
  return (
    <>
      <VwCard>
        <div style={{ textAlign: "center", padding: "12px 0 8px" }}>
          <small style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>
            Boleto vence hoje
          </small>
          <div className="oi-money" style={{ fontSize: 34, fontWeight: 600, letterSpacing: "-0.02em", color: "var(--danger)" }}>
            {BRL(t.valor)}
          </div>
        </div>
        <dl className="oi-dl">
          <dt>Sacado</dt><dd>{t.cliente}</dd>
          <dt>Vencimento</dt><dd style={{ color: "var(--danger)", fontWeight: 600 }}>{t.venc}</dd>
          <dt>Linha</dt><dd className="oi-mono" style={{ fontSize: 11 }}>34191.79001 01043.510047 91020.150008 1 91120000024800</dd>
        </dl>
      </VwCard>

      <VwCard title="Ações">
        <button className="oi-btn block primary" style={{ marginBottom: 8 }} onClick={() => window.oiToast("Boleto reenviado via WhatsApp", "ok")}>
          <Ic.whatsapp size={18} /> Reenviar via WhatsApp
        </button>
        <div className="oi-btn-row">
          <button className="oi-btn" onClick={() => window.oiToast("QR Code PIX gerado")}><Ic.qr size={14} /> PIX</button>
          <button className="oi-btn" onClick={() => window.oiToast("Vencimento prorrogado +3 dias", "ok")}><Ic.calendar size={14} /> Prorrogar</button>
        </div>
      </VwCard>
    </>
  );
}

function CrmContato({ t }) {
  return (
    <>
      <VwCard>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <div className="oi-av oi-av-3" style={{ width: 48, height: 48, borderRadius: "50%", fontSize: 16 }}>
            {(t.cliente || "?").split(" ").slice(0, 2).map(w => w[0]).join("")}
          </div>
          <div style={{ flex: "1 1 auto" }}>
            <div style={{ fontSize: 16, fontWeight: 600 }}>{t.cliente}</div>
            <div className="oi-mono" style={{ fontSize: 12, color: "var(--text-dim)" }}>{t.fone}</div>
            <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 2 }}>Último contato há 4 dias</div>
          </div>
        </div>
      </VwCard>

      <VwCard title="Histórico recente">
        <ul style={{ margin: 0, paddingLeft: 18, fontSize: 12.5, color: "var(--text-dim)", lineHeight: 1.6 }}>
          <li>02/05 — Orçamento enviado: 500 folders A4 4/4</li>
          <li>28/04 — Pediu lembrete pós-feriado</li>
          <li>20/04 — Visitou loja, levou amostra</li>
        </ul>
      </VwCard>

      <VwCard title="Como contatar">
        <div className="oi-btn-row">
          <button className="oi-btn primary" onClick={() => window.oiToast("Abrindo conversa no WhatsApp…")}><Ic.whatsapp size={16} /> WhatsApp</button>
          <button className="oi-btn" onClick={() => window.oiToast("Ligando para " + (t.cliente || "cliente") + "…")}><Ic.phone size={16} /> Ligar</button>
        </div>
      </VwCard>
    </>
  );
}

function MfgLiberar({ t }) {
  return (
    <>
      <VwCard title="Resumo da OS">
        <dl className="oi-dl">
          <dt>Cliente</dt><dd>{t.cliente}</dd>
          <dt>Produto</dt><dd>Banner 3x1m vinil</dd>
          <dt>Acabamento</dt><dd>Ilhós nas 4 pontas</dd>
          <dt>Prazo</dt><dd className="oi-mono">{t.when}</dd>
          <dt>Valor</dt><dd className="oi-money" style={{ fontWeight: 600 }}>{BRL(t.valor)}</dd>
        </dl>
      </VwCard>

      <VwCard title="Fila de produção">
        <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
          {[
            { l: "Plotter 1 — Lona", carga: 0.4, ok: true },
            { l: "Plotter 2 — Vinil", carga: 0.78, ok: true },
            { l: "Bancada acabamento", carga: 0.92, ok: false },
          ].map((q, i) => (
            <div key={i} style={{ display: "flex", flexDirection: "column", gap: 4 }}>
              <div style={{ display: "flex", fontSize: 12.5 }}>
                <span>{q.l}</span>
                <span style={{ marginLeft: "auto", color: "var(--text-mute)", fontVariantNumeric: "tabular-nums" }}>
                  {(q.carga * 100).toFixed(0)}%
                </span>
              </div>
              <div className={"oi-progress " + (q.carga > 0.85 ? "warn" : "")}>
                <i style={{ width: (q.carga * 100) + "%" }} />
              </div>
            </div>
          ))}
        </div>
      </VwCard>
    </>
  );
}

function OsEntrega({ t }) {
  return (
    <>
      <VwCard title="Rota">
        <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
          <Ic.truck size={24} color="var(--accent)" />
          <div>
            <div style={{ fontWeight: 600 }}>Motoboy — Lucas</div>
            <div style={{ fontSize: 11.5, color: "var(--text-dim)" }}>Saída 14:30 · 3 paradas restantes</div>
          </div>
        </div>
      </VwCard>
      <VwCard title="Endereço">
        <dl className="oi-dl">
          <dt>Cliente</dt><dd>{t.cliente}</dd>
          <dt>Endereço</dt><dd>R. Alameda 1.220 — Jd. Paulista</dd>
          <dt>Janela</dt><dd>09:00–11:00</dd>
        </dl>
      </VwCard>
      <VwCard>
        <button className="oi-btn block primary" onClick={() => window.oiToast("Abrindo rota no mapa…")}><Ic.location size={18} /> Abrir no mapa</button>
      </VwCard>
    </>
  );
}

function PntJustificar({ t }) {
  const [motivo, setMotivo] = React.useState("");
  return (
    <>
      <VwCard title="Marcações de ontem">
        <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 6 }}>
          {[
            { l: "Entrada", v: "07:58", ok: true },
            { l: "Almoço", v: "12:01", ok: true },
            { l: "Retorno", v: "13:02", ok: true },
            { l: "Saída", v: "—", ok: false },
          ].map((m, i) => (
            <div key={i} style={{
              background: m.ok ? "var(--bg-2)" : "color-mix(in oklch, var(--danger) 14%, transparent)",
              border: "1px solid " + (m.ok ? "var(--border)" : "color-mix(in oklch, var(--danger) 35%, transparent)"),
              borderStyle: m.ok ? "solid" : "dashed",
              borderRadius: 8, padding: "10px 6px", textAlign: "center",
            }}>
              <small style={{ fontSize: 9.5, color: "var(--text-mute)", fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase" }}>{m.l}</small>
              <div className="oi-mono" style={{ fontSize: 15, fontWeight: 600, marginTop: 4,
                                                color: m.ok ? "var(--text)" : "var(--danger)" }}>{m.v}</div>
            </div>
          ))}
        </div>
      </VwCard>

      <VwCard title="Justificativa">
        <div style={{ display: "flex", flexWrap: "wrap", gap: 6, marginBottom: 8 }}>
          {["Esquecimento", "Reunião externa", "Saída antecipada", "Outro"].map(m => (
            <button key={m} onClick={() => setMotivo(m)}
                    className={"oi-chip" + (motivo === m ? " on" : "")}>{m}</button>
          ))}
        </div>
        <textarea className="oi-search" style={{ width: "100%", minHeight: 70, padding: 10, alignItems: "flex-start" }}
                  placeholder="Descreva o motivo (opcional)" />
      </VwCard>
    </>
  );
}

function CrmOrcamento({ t }) {
  return (
    <>
      <VwCard title="Pedido do cliente">
        <p style={{ margin: 0, fontSize: 13, color: "var(--text-dim)", lineHeight: 1.5 }}>
          200 cardápios A3 dobrados, papel couchê 250g, brilho UV total. Entrega no Bistrô do Forno.
          Quer 3 opções de papel pra ver amostra primeiro.
        </p>
      </VwCard>
      <VwCard title="Modelos rápidos">
        <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
          {["Cardápio A3 — couchê 250", "Cardápio A3 — couchê 300", "Cardápio A3 — reciclato 240"].map((m, i) => (
            <button key={i} className="oi-list-row" onClick={() => window.oiToast("“" + m + "” adicionado ao orçamento", "ok")}
                    style={{ padding: 10, border: "1px solid var(--border)", borderRadius: 8, background: "var(--bg-2)",
                             appearance: "none", textAlign: "left", cursor: "pointer", width: "100%", font: "inherit", color: "inherit" }}>
              <Ic.file size={18} color="var(--text-mute)" />
              <span style={{ flex: 1, fontSize: 13 }}>{m}</span>
              <Ic.plus size={16} color="var(--accent)" />
            </button>
          ))}
        </div>
      </VwCard>
    </>
  );
}

function FinConciliar({ t }) {
  return (
    <>
      <VwCard>
        <div style={{ textAlign: "center" }}>
          <small style={{ fontSize: 11, color: "var(--text-mute)", fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase" }}>
            PIX recebido — sem cliente
          </small>
          <div className="oi-money" style={{ fontSize: 32, fontWeight: 600, color: "var(--ok)" }}>+ {BRL(1420)}</div>
          <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-dim)" }}>Chave: 11.998.412.001/0001-90</div>
        </div>
      </VwCard>
      <VwCard title="Sugestões de vínculo">
        {[
          { c: "Studio Verde Lima", os: "OS-3029", v: 180.0 },
          { c: "Bistrô do Forno",   os: "OS-3028", v: 320.0 },
          { c: "Marília Costa",     os: "OS-3041", v: 248.0 },
        ].map((s, i) => (
          <div key={i} className="oi-list-row" style={{ padding: "10px 0", borderBottom: i < 2 ? "1px solid var(--border-2)" : 0 }}>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 13, fontWeight: 600 }}>{s.c}</div>
              <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>{s.os} · {BRL(s.v)}</div>
            </div>
            <button className="oi-btn sm" onClick={() => window.oiToast("PIX vinculado a " + s.c, "ok")}>Vincular</button>
          </div>
        ))}
      </VwCard>
    </>
  );
}

const VIEWERS = {
  OsAprovarArte, FinBoleto, CrmContato, MfgLiberar, OsEntrega,
  PntJustificar, CrmOrcamento, FinConciliar,
};

function TaskViewer({ task, nav }) {
  const V = VIEWERS[task.viewer];
  if (!V) {
    return (
      <div className="oi-section">
        <div className="oi-card pad" style={{ alignItems: "center", textAlign: "center", color: "var(--text-mute)" }}>
          <Ic.more size={32} />
          <div style={{ fontSize: 13 }}>Visualizador para "{task.viewer}" ainda não implementado</div>
        </div>
      </div>
    );
  }
  return <V t={task} nav={nav} />;
}

window.TaskViewer = TaskViewer;
