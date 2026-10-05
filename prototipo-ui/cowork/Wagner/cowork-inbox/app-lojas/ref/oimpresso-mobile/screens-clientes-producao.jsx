// Clientes (CRM) + Produção (MFG) screens + Mais hub
const { Ic } = window;
const { CLIENTES, PRODUCAO_ESTACOES, PRODUCAO_JOBS } = window.MOCK;
const { ScreenHeader, DetailHeader, HeaderTopRight, OriginBadge } = window.Screens;
const BRL = window.BRL;

// ────────────────────────────────────────────────
// MAIS — hub de módulos secundários
// ────────────────────────────────────────────────
function MaisScreen({ nav, ctx }) {
  const modulos = [
    { id: "produtos",  label: "Produtos",    ic: "box",    color: "var(--accent)", desc: window.MOCK.PRODUTOS.length + " no catálogo" },
    { id: "clientes",  label: "Pessoas",     ic: "user",   color: "oklch(0.62 0.12 220)", desc: CLIENTES.length + " cadastros" },
    { id: "financas",  label: "Finanças",    ic: "dollar", color: "var(--ok)",     desc: "Saldo, contas e transações" },
    { id: "relatorios",label: "Relatórios",  ic: "chart",  color: "oklch(0.62 0.12 295)", desc: "Vendas, fluxo, margem" },
    { id: "equipe",    label: "Equipe",      ic: "user",   color: "oklch(0.62 0.12 75)",  desc: "Ponto, escala, justificativas" },
    { id: "fornece",   label: "Fornecedores",ic: "truck",  color: "var(--text-dim)",      desc: "Cadastro e compras" },
  ];
  const ferramentas = [
    { id: "venda-rapida",label: "Venda rápida",     ic: "scan",    featured: true, desc: "Scanner + carrinho" },
    { id: "pix-cobrar",  label: "Cobrar PIX",       ic: "qr" },
    { id: "etiqueta",    label: "Imprimir etiqueta",ic: "printer" },
    { id: "calc",        label: "Calculadora gráfica", ic: "chart" },
  ];

  return (
    <>
      <ScreenHeader
        nav={nav}
        title="Mais"
        eyebrow="Módulos · ferramentas · conta"
        actions={<HeaderTopRight nav={nav} tenant={ctx.tenant} />}
      />
      <div className="oi-scroll">
        {/* Featured: Venda rápida — destaque pq é o uso recorrente do celular */}
        <div className="oi-section">
          <button onClick={() => nav.push("venda-rapida")}
                  style={{
                    appearance: "none", cursor: "pointer",
                    width: "100%", display: "flex", alignItems: "center", gap: 14,
                    padding: 16, borderRadius: 14,
                    background: "linear-gradient(135deg, var(--accent), oklch(from var(--accent) calc(l - .06) c calc(h + 20)))",
                    border: 0, color: "#fff", textAlign: "left",
                    boxShadow: "0 8px 24px -10px color-mix(in oklch, var(--accent) 60%, transparent)",
                  }}>
            <div style={{ width: 52, height: 52, borderRadius: 14,
                          background: "rgba(255,255,255,.18)",
                          display: "grid", placeItems: "center", flex: "0 0 auto" }}>
              <Ic.scan size={26} color="#fff" />
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".08em",
                            textTransform: "uppercase", opacity: .8 }}>
                PDV móvel
              </div>
              <div style={{ fontSize: 17, fontWeight: 600, letterSpacing: "-0.01em", marginTop: 2 }}>
                Venda rápida
              </div>
              <div style={{ fontSize: 12, opacity: .85, marginTop: 2 }}>
                Bipa o código de barras e cobra em segundos
              </div>
            </div>
            <Ic.chevR size={20} color="rgba(255,255,255,.7)" />
          </button>
        </div>
        <div className="oi-section">
          <h3 className="oi-section-h">Módulos</h3>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
            {modulos.map(m => (
              <button key={m.id} className="oi-card tight"
                      onClick={() => nav.push(m.id)}
                      style={{ alignItems: "flex-start", textAlign: "left", padding: 12,
                               gap: 8, cursor: "pointer", background: "var(--surface)" }}>
                <div style={{ width: 36, height: 36, borderRadius: 10,
                              background: "color-mix(in oklch, " + m.color + " 18%, transparent)",
                              color: m.color, display: "grid", placeItems: "center" }}>
                  {React.createElement(Ic[m.ic], { size: 18 })}
                </div>
                <div style={{ fontSize: 13.5, fontWeight: 600, letterSpacing: "-0.005em" }}>{m.label}</div>
                <div style={{ fontSize: 11, color: "var(--text-mute)", lineHeight: 1.35 }}>{m.desc}</div>
              </button>
            ))}
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Ferramentas</h3>
          <div className="oi-list card">
            {ferramentas.filter(t => !t.featured).map((t, i, arr) => (
              <div key={t.id} className="oi-list-row"
                   onClick={() => t.id === "venda-rapida" ? nav.push("venda-rapida") : null}
                   style={i === arr.length - 1 ? { borderBottom: 0 } : {}}>
                <div style={{ width: 32, height: 32, borderRadius: 8, background: "var(--bg-2)",
                              border: "1px solid var(--border)", display: "grid", placeItems: "center",
                              color: "var(--text-dim)" }}>
                  {React.createElement(Ic[t.ic], { size: 16 })}
                </div>
                <div style={{ flex: 1, fontSize: 13.5, fontWeight: 500 }}>{t.label}</div>
                <Ic.chevR color="var(--text-mute)" size={16} />
              </div>
            ))}
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Conta</h3>
          <div className="oi-list card">
            <div className="oi-list-row" onClick={() => nav.push("perfil")}>
              <Ic.user size={18} color="var(--text-mute)" />
              <span style={{ flex: 1, fontSize: 13.5, fontWeight: 500 }}>Perfil e preferências</span>
              <Ic.chevR color="var(--text-mute)" size={16} />
            </div>
            <div className="oi-list-row" onClick={() => nav.push("empresa")}>
              <Ic.shield size={18} color="var(--text-mute)" />
              <span style={{ flex: 1, fontSize: 13.5, fontWeight: 500 }}>Empresa ativa</span>
              <span style={{ fontSize: 11.5, color: "var(--text-mute)" }}>
                {(window.MOCK.TENANTS.find(t => t.id === ctx.tenant) || window.MOCK.TENANTS[0]).short}
              </span>
              <Ic.chevR color="var(--text-mute)" size={16} />
            </div>
            <div className="oi-list-row" onClick={() => nav.push("notificacoes")}
                 style={{ borderBottom: 0 }}>
              <Ic.bell size={18} color="var(--text-mute)" />
              <span style={{ flex: 1, fontSize: 13.5, fontWeight: 500 }}>Notificações</span>
              <Ic.chevR color="var(--text-mute)" size={16} />
            </div>
          </div>
        </div>

        {/* Lockup da marca */}
        <div className="oi-section" style={{ paddingTop: 18, paddingBottom: 8 }}>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 10, opacity: .9 }}>
            <img src="assets/oimpresso-logo.png" alt="Oimpresso" style={{ width: 26, height: "auto" }} />
            <div style={{ lineHeight: 1.1 }}>
              <div style={{ fontSize: 15, fontWeight: 700, letterSpacing: "-0.01em", color: "var(--text)" }}>Oimpresso</div>
              <div style={{ fontSize: 10, color: "var(--text-mute)" }}>Gestão para comunicação visual</div>
            </div>
          </div>
          <div style={{ textAlign: "center", fontSize: 11, color: "var(--text-mute)", marginTop: 8 }}>
            v0.4.1 · build 2026.05
          </div>
        </div>

        <div style={{ height: 90 }} />
      </div>
    </>
  );
}

// ────────────────────────────────────────────────
// PESSOAS (clientes / fornecedores / funcionários)
// ────────────────────────────────────────────────
function PapelBadges({ papeis, size = "sm" }) {
  const all = window.MOCK.PAPEIS;
  return (
    <span style={{ display: "inline-flex", gap: 4, flexWrap: "wrap" }}>
      {(papeis || []).map(pid => {
        const p = all.find(x => x.id === pid);
        if (!p) return null;
        return (
          <span key={pid} style={{
            fontSize: size === "lg" ? 10.5 : 9,
            fontWeight: 700, letterSpacing: ".04em",
            padding: size === "lg" ? "2px 8px" : "1px 5px",
            borderRadius: 4,
            fontFamily: "var(--font-mono)",
            background: "color-mix(in oklch, " + p.color + " 20%, transparent)",
            color: p.color,
          }}>
            {size === "lg" ? p.label : p.short}
          </span>
        );
      })}
    </span>
  );
}

function ClientesScreen({ nav, ctx }) {
  const [q, setQ] = React.useState("");
  const [tab, setTab] = React.useState("todos");
  const byPapel = (id) => CLIENTES.filter(c => (c.papeis || []).includes(id)).length;
  const filtered = CLIENTES.filter(c => {
    if (["cliente", "fornecedor", "funcionario", "transportadora"].includes(tab) && !(c.papeis || []).includes(tab)) return false;
    if (tab === "saldo" && (c.saldo || 0) >= 0) return false;
    if (q && !(c.nome + " " + c.doc).toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });
  const grouped = {};
  filtered.forEach(c => {
    const k = c.nome[0].toUpperCase();
    (grouped[k] = grouped[k] || []).push(c);
  });
  const keys = Object.keys(grouped).sort();

  return (
    <>
      <DetailHeader nav={nav} title="Pessoas"
                    eyebrow={CLIENTES.length + " cadastros · " + byPapel("cliente") + " clientes · " + byPapel("fornecedor") + " fornecedores"}
                    actions={<button className="oi-iconbtn"><Ic.filter /></button>} />
      <div className="oi-head" style={{ paddingTop: 0, borderBottom: "1px solid var(--border)" }}>
        <div className="oi-search">
          <Ic.search size={16} />
          <input placeholder="Nome, CNPJ, telefone…" value={q} onChange={e => setQ(e.target.value)} />
        </div>
        <div className="oi-chips" style={{ marginTop: 10 }}>
          {[
            { id: "todos", label: "Todos", c: CLIENTES.length },
            { id: "cliente", label: "Clientes", c: byPapel("cliente") },
            { id: "fornecedor", label: "Fornecedores", c: byPapel("fornecedor") },
            { id: "funcionario", label: "Funcionários", c: byPapel("funcionario") },
            { id: "transportadora", label: "Transportadoras", c: byPapel("transportadora") },
            { id: "saldo", label: "Em débito", c: CLIENTES.filter(c => (c.saldo || 0) < 0).length },
          ].map(c => (
            <button key={c.id} className={"oi-chip" + (tab === c.id ? " on" : "")} onClick={() => setTab(c.id)}>
              {c.label} <span className="c">{c.c}</span>
            </button>
          ))}
        </div>
      </div>
      <div className="oi-scroll">
        {filtered.length === 0 && (
          <div className="oi-empty" style={{ marginTop: 32 }}>
            <div className="oi-empty-ico"><Ic.user size={24} /></div>
            <b>Nenhuma pessoa</b>
            <small>Nenhum cadastro com esse filtro. Toque no + para criar.</small>
          </div>
        )}
        {keys.map(k => (
          <div key={k}>
            <div style={{ padding: "10px 16px 4px", fontSize: 11, fontWeight: 700,
                          letterSpacing: ".08em", color: "var(--text-mute)", background: "var(--bg-2)" }}>
              {k}
            </div>
            <div className="oi-list">
              {grouped[k].map(c => (
                <div key={c.id} className="oi-list-row" onClick={() => nav.push("cliente", { id: c.id })}>
                  <div className={"oi-av " + c.av}
                       style={{ width: 40, height: 40, borderRadius: "50%", fontSize: 13 }}>
                    {c.nome.split(" ").slice(0, 2).map(w => w[0]).join("")}
                  </div>
                  <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                      <span style={{ fontWeight: 600, fontSize: 13.5, letterSpacing: "-0.005em",
                                    whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                        {c.nome}
                      </span>
                      {!c.ativo && (
                        <span style={{ fontSize: 9, fontWeight: 700, background: "var(--bg-2)",
                                       color: "var(--text-mute)", padding: "1px 5px", borderRadius: 3 }}>INATIVO</span>
                      )}
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 6, marginTop: 3 }}>
                      <PapelBadges papeis={c.papeis} />
                      <span style={{ fontSize: 11, color: "var(--text-mute)" }} className="oi-mono">{c.tipo}</span>
                    </div>
                  </div>
                  <div style={{ textAlign: "right", flex: "0 0 auto" }}>
                    <div style={{ fontSize: 13, fontWeight: 600, color: c.saldo < 0 ? "var(--danger)" : "var(--text)" }}>
                      {c.saldo < 0 ? BRL(c.saldo).replace("-", "−") : (c.pedidos > 0 ? c.pedidos + " OS" : "—")}
                    </div>
                    <div style={{ fontSize: 10.5, color: "var(--text-mute)" }}>
                      {c.ultimo}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
        <div style={{ height: 90 }} />
      </div>
      <div className="oi-fab" onClick={() => nav.push("novo-cliente")}>
        <Ic.plus size={26} />
      </div>
    </>
  );
}

function ClienteDetalheScreen({ nav, ctx, params }) {
  const c = CLIENTES.find(x => x.id === params.id) || CLIENTES[0];
  // Pedidos deste cliente
  const pedidosCli = window.MOCK.PEDIDOS.filter(p => p.cliente === c.nome);
  return (
    <>
      <DetailHeader nav={nav} title={c.nome}
                    actions={<button className="oi-iconbtn" onClick={() => nav.push("editar-cliente", { id: c.id })}>
                      <Ic.edit size={20} />
                    </button>} />
      <div className="oi-scroll">
        {/* Hero */}
        <div className="oi-section">
          <div className="oi-card pad" style={{ alignItems: "center", textAlign: "center", padding: "20px 16px" }}>
            <div className={"oi-av " + c.av}
                 style={{ width: 72, height: 72, borderRadius: "50%", fontSize: 22 }}>
              {c.nome.split(" ").slice(0, 2).map(w => w[0]).join("")}
            </div>
            <div style={{ fontSize: 18, fontWeight: 600, letterSpacing: "-0.01em" }}>{c.nome}</div>
            <div className="oi-mono" style={{ fontSize: 12, color: "var(--text-mute)" }}>
              {c.tipo} · {c.doc}
            </div>
            <div style={{ marginTop: 6 }}>
              <PapelBadges papeis={c.papeis} size="lg" />
            </div>
            <div style={{ display: "flex", gap: 6, flexWrap: "wrap", justifyContent: "center", marginTop: 6 }}>
              {c.tags.map(t => (
                <span key={t} className="oi-status accent" style={{ fontSize: 10.5 }}>{t}</span>
              ))}
            </div>
            {/* Quick actions */}
            <div className="oi-btn-row" style={{ width: "100%", marginTop: 10 }}>
              <button className="oi-btn primary"><Ic.whatsapp size={16} /> WhatsApp</button>
              <button className="oi-btn"><Ic.phone size={16} /></button>
              <button className="oi-btn"><Ic.mail size={16} /></button>
            </div>
          </div>
        </div>

        {/* KPIs do cliente */}
        <div className="oi-section">
          <div className="oi-kpis">
            <div className="oi-kpi">
              <small>Pedidos</small>
              <b>{c.pedidos}</b>
              <span style={{ fontSize: 11, color: "var(--text-mute)" }}>histórico</span>
            </div>
            <div className="oi-kpi">
              <small>Ticket médio</small>
              <b className="oi-money" style={{ fontSize: 17 }}>{BRL(c.ticket)}</b>
            </div>
            <div className={"oi-kpi" + (c.saldo < 0 ? " warn" : "")}>
              <small>Saldo</small>
              <b className="oi-money" style={{ fontSize: 17, color: c.saldo < 0 ? "var(--danger)" : c.saldo > 0 ? "var(--ok)" : "var(--text-dim)" }}>
                {c.saldo === 0 ? "—" : (c.saldo < 0 ? BRL(c.saldo).replace("-", "−") : BRL(c.saldo))}
              </b>
            </div>
          </div>
        </div>

        {/* Acesso à ficha cadastral completa */}
        <div className="oi-section">
          <div className="oi-list card">
            <div className="oi-list-row" onClick={() => nav.push("cliente-dados", { id: c.id })}
                 style={{ borderBottom: 0 }}>
              <div style={{ width: 32, height: 32, borderRadius: 8, background: "var(--accent-soft)",
                            display: "grid", placeItems: "center", color: "var(--accent)" }}>
                <Ic.file size={16} />
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 13.5, fontWeight: 600 }}>Dados cadastrais</div>
                <div style={{ fontSize: 11.5, color: "var(--text-mute)" }}>Documento, endereço fiscal, comercial e LGPD</div>
              </div>
              <Ic.chevR color="var(--text-mute)" size={16} />
            </div>
          </div>
        </div>

        {/* Contato */}
        <div className="oi-section">
          <h3 className="oi-section-h">Contato</h3>
          <div className="oi-card pad" style={{ gap: 0 }}>
            <ContactInfoRow ic="phone"    label={c.fone} />
            <ContactInfoRow ic="mail"     label={c.email} />
            <ContactInfoRow ic="location" label={c.cidade} last />
          </div>
        </div>

        {/* Pedidos */}
        <div className="oi-section">
          <h3 className="oi-section-h">
            Pedidos recentes
            {pedidosCli.length > 3 && <a className="more">Ver todos ({pedidosCli.length})</a>}
          </h3>
          {pedidosCli.length === 0 ? (
            <div className="oi-card pad" style={{ alignItems: "center", color: "var(--text-mute)", padding: "20px 16px" }}>
              <Ic.inbox size={26} />
              <small style={{ fontSize: 12 }}>Sem pedidos ainda</small>
            </div>
          ) : (
            <div className="oi-list card">
              {pedidosCli.slice(0, 4).map(p => (
                <div key={p.id} className="oi-list-row" onClick={() => nav.push("pedido", { id: p.id })}>
                  <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                      <span className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)", fontWeight: 600 }}>{p.id}</span>
                      <span style={{ fontSize: 11.5, color: "var(--text-dim)" }}>{p.atualizado}</span>
                    </div>
                    <div style={{ fontSize: 13, fontWeight: 500, marginTop: 2 }}>{p.produto}</div>
                  </div>
                  <div style={{ textAlign: "right" }}>
                    <div className="oi-money" style={{ fontSize: 13, fontWeight: 600 }}>{BRL(p.valor)}</div>
                    <div style={{ fontSize: 10.5, color: p.etapaKey === "done" ? "var(--ok)" : "var(--text-mute)" }}>
                      {p.etapa}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* CTA criar pedido */}
        <div className="oi-section">
          <button className="oi-btn block primary" onClick={() => nav.push("novo-pedido")}>
            <Ic.plus size={16} /> Novo pedido para {c.nome.split(" ")[0]}
          </button>
        </div>

        <div style={{ height: 24 }} />
      </div>
    </>
  );
}

function ContactInfoRow({ ic, label, last }) {
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 12, padding: "10px 0",
                  borderBottom: last ? 0 : "1px solid var(--border-2)" }}>
      <div style={{ color: "var(--text-mute)" }}>{React.createElement(Ic[ic], { size: 18 })}</div>
      <div style={{ flex: 1, fontSize: 13.5 }}>{label}</div>
      <Ic.chevR color="var(--text-mute)" size={16} />
    </div>
  );
}

// ────────────────────────────────────────────────
// PRODUÇÃO — fila por estação
// ────────────────────────────────────────────────
function ProducaoScreen({ nav, ctx }) {
  const [estacao, setEstacao] = React.useState("todas");
  const jobs = PRODUCAO_JOBS.filter(j => estacao === "todas" || j.estacao === estacao);
  const ativos = PRODUCAO_JOBS.filter(j => j.etapa !== "Pronto").length;
  const prontos = PRODUCAO_JOBS.filter(j => j.etapa === "Pronto").length;

  return (
    <>
      <DetailHeader nav={nav} title="Produção"
                    eyebrow={ativos + " ativos · " + prontos + " prontos para expedição"} />
      <div className="oi-scroll">
        {/* Estações — carga */}
        <div className="oi-section">
          <h3 className="oi-section-h">Carga das estações</h3>
          <div className="oi-card pad" style={{ gap: 10 }}>
            {PRODUCAO_ESTACOES.map(e => (
              <div key={e.id} style={{ display: "flex", flexDirection: "column", gap: 4,
                                       cursor: "pointer" }}
                   onClick={() => setEstacao(estacao === e.id ? "todas" : e.id)}>
                <div style={{ display: "flex", fontSize: 13 }}>
                  <span style={{ fontWeight: estacao === e.id ? 600 : 500,
                                color: estacao === e.id ? "var(--accent)" : "var(--text)" }}>
                    {e.nome}
                  </span>
                  <span style={{ marginLeft: "auto", fontSize: 12, color: "var(--text-mute)" }}>
                    <span className="oi-mono">{e.jobsHoje}</span> jobs · {(e.carga * 100).toFixed(0)}%
                  </span>
                </div>
                <div className={"oi-progress " + (e.carga > 0.85 ? "danger" : e.carga > 0.7 ? "warn" : "")}>
                  <i style={{ width: (e.carga * 100) + "%" }} />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Filtro estação */}
        <div className="oi-section">
          <div className="oi-chips">
            <button className={"oi-chip" + (estacao === "todas" ? " on" : "")} onClick={() => setEstacao("todas")}>
              Todas <span className="c">{PRODUCAO_JOBS.length}</span>
            </button>
            {PRODUCAO_ESTACOES.map(e => (
              <button key={e.id} className={"oi-chip" + (estacao === e.id ? " on" : "")}
                      onClick={() => setEstacao(e.id)}>
                {e.nome.split(" ")[0]}
              </button>
            ))}
          </div>
        </div>

        {/* Jobs */}
        <div className="oi-section">
          <h3 className="oi-section-h">Fila de produção</h3>
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            {jobs.map(j => {
              const isProgress = typeof j.prog === "number" && j.prog > 0 && j.prog < 1;
              const isDone = j.etapa === "Pronto";
              return (
                <div key={j.id} className="oi-card tight" onClick={() => nav.push("producao-job", { id: j.id })}
                     style={{ cursor: "pointer", padding: 12,
                              borderLeft: j.prio === "alta" ? "3px solid var(--danger)" :
                                          j.prio === "media" ? "3px solid var(--warn)" : "1px solid var(--border)" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                    <span className="oi-origin o-MFG">MFG</span>
                    <span className="oi-mono" style={{ fontSize: 11.5, color: "var(--text-mute)", fontWeight: 600 }}>{j.id}</span>
                    <span className={"oi-status " + (isDone ? "ok" : isProgress ? "accent" : "warn")}>
                      <span className="dot" />{j.etapa}
                    </span>
                    <span style={{ marginLeft: "auto", fontSize: 11, color: j.prio === "alta" ? "var(--danger)" : "var(--text-mute)",
                                  fontFamily: "var(--font-mono)", fontWeight: j.prio === "alta" ? 600 : 400 }}>
                      {j.prazo}
                    </span>
                  </div>
                  <div style={{ fontSize: 13.5, fontWeight: 600, lineHeight: 1.35 }}>{j.produto}</div>
                  <div style={{ fontSize: 11.5, color: "var(--text-dim)", display: "flex", gap: 8 }}>
                    <span>{j.cliente}</span>
                    <span>·</span>
                    <span className="oi-mono">{j.os}</span>
                    {j.op && <><span>·</span><span>{j.op}</span></>}
                  </div>
                  {isProgress && (
                    <div className="oi-progress" style={{ height: 4, marginTop: 4 }}>
                      <i style={{ width: (j.prog * 100) + "%" }} />
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        <div style={{ height: 24 }} />
      </div>
    </>
  );
}

function ProducaoJobDetalheScreen({ nav, ctx, params }) {
  const j = PRODUCAO_JOBS.find(x => x.id === params.id) || PRODUCAO_JOBS[0];
  const isProgress = typeof j.prog === "number" && j.prog > 0 && j.prog < 1;
  const isDone = j.etapa === "Pronto";
  const stations = ["Em fila", "Imprimindo", "Acabamento", "Pronto"];
  const sIdx = Math.max(0, stations.indexOf(j.etapa));

  return (
    <>
      <DetailHeader nav={nav} title={j.id} eyebrow={"MFG · " + j.cliente} />
      <div className="oi-scroll">
        <div className="oi-section">
          <div className="oi-card pad">
            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
              <OriginBadge origin="MFG" size="lg" />
              <span className={"oi-status " + (isDone ? "ok" : isProgress ? "accent" : "warn")}>
                <span className="dot" />{j.etapa}
              </span>
              <span style={{ marginLeft: "auto", fontSize: 12, color: j.prio === "alta" ? "var(--danger)" : "var(--text-mute)",
                            fontWeight: j.prio === "alta" ? 600 : 400 }} className="oi-mono">
                {j.prazo}
              </span>
            </div>
            <div style={{ marginTop: 8, fontSize: 16, fontWeight: 600, lineHeight: 1.35 }}>{j.produto}</div>
            <div style={{ fontSize: 12.5, color: "var(--text-dim)" }}>{j.cliente} · {j.os}</div>
          </div>
        </div>

        {/* Stepper estação */}
        <div className="oi-section">
          <h3 className="oi-section-h">Etapas</h3>
          <div className="oi-card pad">
            {stations.map((s, i) => (
              <div key={s} style={{ display: "flex", alignItems: "center", gap: 12, padding: "8px 0",
                                    borderBottom: i < stations.length - 1 ? "1px solid var(--border-2)" : 0 }}>
                <div style={{
                  width: 26, height: 26, borderRadius: 99,
                  background: i < sIdx ? "var(--ok)" : i === sIdx ? "var(--accent)" : "var(--bg-2)",
                  border: "2px solid " + (i < sIdx ? "var(--ok)" : i === sIdx ? "var(--accent)" : "var(--border)"),
                  color: i <= sIdx ? "#fff" : "var(--text-mute)",
                  display: "grid", placeItems: "center", fontSize: 11, fontWeight: 700,
                }}>
                  {i < sIdx ? <Ic.check size={13} color="#fff" strokeWidth={3} /> : (i + 1)}
                </div>
                <div style={{ flex: 1, fontSize: 13.5, fontWeight: i === sIdx ? 600 : 500,
                              color: i === sIdx ? "var(--text)" : i < sIdx ? "var(--text-dim)" : "var(--text-mute)" }}>
                  {s}
                </div>
                {i === sIdx && isProgress && (
                  <span className="oi-mono" style={{ fontSize: 11, color: "var(--accent)", fontWeight: 600 }}>
                    {(j.prog * 100).toFixed(0)}%
                  </span>
                )}
              </div>
            ))}
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Detalhes</h3>
          <div className="oi-card pad">
            <dl className="oi-dl">
              <dt>Estação</dt>  <dd>{(PRODUCAO_ESTACOES.find(e => e.id === j.estacao) || {}).nome}</dd>
              <dt>Operador</dt> <dd>{j.op || "—"}</dd>
              <dt>Duração</dt>  <dd>{j.duracao}</dd>
              <dt>Prioridade</dt><dd style={{ textTransform: "capitalize",
                                              color: j.prio === "alta" ? "var(--danger)" : j.prio === "media" ? "var(--warn)" : "var(--text)" }}>
                {j.prio}
              </dd>
              <dt>OS vinculada</dt><dd className="oi-mono">{j.os}</dd>
            </dl>
          </div>
        </div>

        <div style={{ height: 100 }} />
      </div>

      {/* Action bar — depende da etapa */}
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)",
                    display: "flex", gap: 8 }}>
        {isDone ? (
          <button className="oi-btn primary block" onClick={() => nav.pop()}>
            <Ic.truck size={18} /> Enviar para expedição
          </button>
        ) : isProgress ? (
          <>
            <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }}>
              <Ic.alert size={16} />
            </button>
            <button className="oi-btn primary" style={{ flex: 1 }} onClick={() => nav.pop()}>
              <Ic.checkCircle size={18} /> Concluir etapa · {stations[sIdx]}
            </button>
          </>
        ) : (
          <>
            <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }}>
              <Ic.user size={16} />
            </button>
            <button className="oi-btn primary" style={{ flex: 1 }} onClick={() => nav.pop()}>
              <Ic.zap size={18} /> Iniciar {stations[sIdx]}
            </button>
          </>
        )}
      </div>
    </>
  );
}

// ────────────────────────────────────────────────
// VENDA RÁPIDA — scanner + carrinho
// ────────────────────────────────────────────────
function VendaRapidaScreen({ nav, ctx }) {
  const [cart, setCart] = React.useState([]); // [{ produto, qty }]
  const [sheet, setSheet] = React.useState(null); // null | 'scan' | 'pay'
  const [paymentMethod, setPaymentMethod] = React.useState("pix");
  const [done, setDone] = React.useState(false);

  const total = cart.reduce((s, i) => s + i.produto.preco * i.qty, 0);
  const qtdTotal = cart.reduce((s, i) => s + i.qty, 0);

  const addProduto = (p) => {
    setCart(c => {
      const idx = c.findIndex(i => i.produto.id === p.id);
      if (idx >= 0) {
        const next = [...c];
        next[idx] = { ...next[idx], qty: next[idx].qty + 1 };
        return next;
      }
      return [...c, { produto: p, qty: 1 }];
    });
    setSheet(null);
  };

  const changeQty = (id, delta) => {
    setCart(c => c.map(i => i.produto.id === id ? { ...i, qty: Math.max(0, i.qty + delta) } : i).filter(i => i.qty > 0));
  };

  if (done) {
    return (
      <>
        <DetailHeader nav={nav} title="Venda concluída" />
        <div className="oi-scroll">
          <div className="oi-section" style={{ paddingTop: 28, textAlign: "center" }}>
            <div style={{ width: 72, height: 72, borderRadius: 20, background: "color-mix(in oklch, var(--ok) 22%, transparent)",
                          color: "var(--ok)", display: "grid", placeItems: "center", margin: "0 auto 14px" }}>
              <Ic.checkCircle size={36} />
            </div>
            <div style={{ fontSize: 22, fontWeight: 600, letterSpacing: "-0.02em" }}>Venda registrada!</div>
            <div className="oi-money" style={{ fontSize: 34, fontWeight: 600, letterSpacing: "-0.02em", marginTop: 8 }}>
              {BRL(total)}
            </div>
            <div style={{ fontSize: 12, color: "var(--text-mute)" }}>
              {qtdTotal} {qtdTotal === 1 ? "item" : "itens"} · {paymentMethod.toUpperCase()}
            </div>
          </div>
          <div className="oi-section">
            <div className="oi-card pad" style={{ gap: 10 }}>
              <div className="oi-mono" style={{ fontSize: 12, color: "var(--text-mute)", textAlign: "center" }}>
                Recibo #V-{Math.floor(Math.random() * 9000 + 1000)} · 16/05 14:32
              </div>
              <div className="oi-btn-row">
                <button className="oi-btn"><Ic.printer size={16} /> Imprimir</button>
                <button className="oi-btn"><Ic.whatsapp size={16} /> Enviar</button>
              </div>
            </div>
          </div>
          <div className="oi-section">
            <button className="oi-btn block primary" onClick={() => { setCart([]); setDone(false); }}>
              <Ic.plus size={18} /> Nova venda
            </button>
            <button className="oi-btn block ghost" style={{ marginTop: 8 }} onClick={() => nav.pop()}>Fechar</button>
          </div>
        </div>
      </>
    );
  }

  return (
    <>
      <DetailHeader nav={nav} title="Venda rápida"
                    eyebrow={cart.length === 0 ? "PDV móvel · scanner" : qtdTotal + (qtdTotal === 1 ? " item · " : " itens · ") + BRL(total)}
                    actions={cart.length > 0 ? (
                      <button className="oi-iconbtn" onClick={() => setCart([])}>
                        <Ic.trash size={20} />
                      </button>
                    ) : null} />

      {/* Empty state — viewfinder convidando a scanear */}
      {cart.length === 0 && (
        <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
          <ScannerView onScan={addProduto} hint="Aponte a câmera para o código de barras" />
          <div style={{ padding: "16px 16px 12px", borderTop: "1px solid var(--border)", background: "var(--surface)" }}>
            <div style={{ display: "flex", gap: 8 }}>
              <button className="oi-btn action" style={{ flex: 1 }} onClick={() => setSheet("scan")}>
                <Ic.search size={16} /> Buscar produto
              </button>
              <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }}>
                <Ic.tag size={18} />
              </button>
            </div>
            <div style={{ fontSize: 11, color: "var(--text-mute)", textAlign: "center", marginTop: 8 }}>
              Toque no produto na lista de busca para adicionar
            </div>
          </div>
        </div>
      )}

      {/* Carrinho */}
      {cart.length > 0 && (
        <>
          <div className="oi-scroll">
            <div className="oi-section">
              <h3 className="oi-section-h">Carrinho <span style={{ marginLeft: 6, color: "var(--text-mute)", fontWeight: 500, textTransform: "none", letterSpacing: 0 }}>· {cart.length} produto{cart.length === 1 ? "" : "s"}</span></h3>
              <div className="oi-list card">
                {cart.map(i => (
                  <div key={i.produto.id} className="oi-list-row" style={{ alignItems: "center" }}>
                    <div style={{ width: 38, height: 38, borderRadius: 8, background: "var(--bg-2)",
                                  border: "1px solid var(--border)", display: "grid", placeItems: "center",
                                  color: "var(--text-mute)", flex: "0 0 auto" }}>
                      {React.createElement(Ic[i.produto.img] || Ic.box, { size: 18 })}
                    </div>
                    <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                      <div style={{ fontWeight: 600, fontSize: 13, lineHeight: 1.3,
                                    whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                        {i.produto.nome}
                      </div>
                      <div className="oi-money" style={{ fontSize: 11.5, color: "var(--text-mute)" }}>
                        {BRL(i.produto.preco)} /{i.produto.un}
                      </div>
                    </div>
                    {/* Stepper qty */}
                    <div style={{ display: "flex", alignItems: "center", gap: 4, flex: "0 0 auto" }}>
                      <button onClick={() => changeQty(i.produto.id, -1)}
                              style={{ appearance: "none", border: "1px solid var(--border)", background: "var(--bg-2)",
                                      width: 28, height: 28, borderRadius: 6, color: "var(--text)", cursor: "pointer",
                                      display: "grid", placeItems: "center" }}>
                        <Ic.x size={14} />
                      </button>
                      <span className="oi-mono" style={{ minWidth: 24, textAlign: "center", fontWeight: 600 }}>{i.qty}</span>
                      <button onClick={() => changeQty(i.produto.id, +1)}
                              style={{ appearance: "none", border: "1px solid var(--accent)", background: "var(--accent-soft)",
                                      width: 28, height: 28, borderRadius: 6, color: "var(--accent)", cursor: "pointer",
                                      display: "grid", placeItems: "center" }}>
                        <Ic.plus size={14} />
                      </button>
                    </div>
                    <div className="oi-money" style={{ minWidth: 70, textAlign: "right", fontWeight: 600, fontSize: 13.5 }}>
                      {BRL(i.produto.preco * i.qty)}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Sub-totais */}
            <div className="oi-section">
              <div className="oi-card pad" style={{ gap: 8 }}>
                <div style={{ display: "flex", fontSize: 13, color: "var(--text-dim)" }}>
                  <span>Subtotal</span>
                  <span className="oi-money" style={{ marginLeft: "auto" }}>{BRL(total)}</span>
                </div>
                <div style={{ display: "flex", fontSize: 13, color: "var(--text-dim)" }}>
                  <span>Desconto</span>
                  <span className="oi-money" style={{ marginLeft: "auto", color: "var(--accent)", cursor: "pointer" }}>+ aplicar</span>
                </div>
                <div style={{ height: 1, background: "var(--border-2)", margin: "4px 0" }} />
                <div style={{ display: "flex", alignItems: "baseline" }}>
                  <span style={{ fontSize: 13, fontWeight: 600 }}>Total</span>
                  <span className="oi-money" style={{ marginLeft: "auto", fontSize: 22, fontWeight: 600, letterSpacing: "-0.02em" }}>
                    {BRL(total)}
                  </span>
                </div>
              </div>
            </div>

            <div className="oi-section">
              <button className="oi-btn block" onClick={() => setSheet("scan")}>
                <Ic.plus size={16} /> Adicionar mais produtos
              </button>
            </div>

            <div style={{ height: 100 }} />
          </div>

          {/* Action bar — checkout */}
          <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)",
                        display: "flex", gap: 8 }}>
            <button className="oi-btn primary block" style={{ height: 50, fontSize: 15 }}
                    onClick={() => setSheet("pay")}>
              <Ic.dollar size={18} /> Cobrar {BRL(total)}
            </button>
          </div>
        </>
      )}

      {/* Sheet: buscar produto */}
      {sheet === "scan" && (
        <SearchSheet onClose={() => setSheet(null)} onPick={addProduto} />
      )}
      {/* Sheet: pagamento */}
      {sheet === "pay" && (
        <PaymentSheet total={total} method={paymentMethod} setMethod={setPaymentMethod}
                      onClose={() => setSheet(null)}
                      onConfirm={() => { setSheet(null); setDone(true); }} />
      )}
    </>
  );
}

// ─── Scanner viewfinder (mock) ───
function ScannerView({ onScan, hint }) {
  // Sample products available "via barcode"
  const sample = window.MOCK.PRODUTOS.slice(0, 4);
  const [recent, setRecent] = React.useState(null);

  // Simulate auto-scan after a moment (only if user hasn't picked)
  return (
    <div style={{
      flex: 1, position: "relative", overflow: "hidden",
      background: "radial-gradient(ellipse at center, oklch(0.22 0.01 240) 0%, oklch(0.10 0.005 240) 80%)",
      color: "#fff",
    }}>
      {/* fake camera viewfinder */}
      <div style={{
        position: "absolute", inset: 0,
        background: "repeating-linear-gradient(0deg, transparent 0 3px, rgba(255,255,255,0.012) 3px 4px)",
      }} />
      {/* corners */}
      <div style={{ position: "absolute", inset: "20% 14%" }}>
        {[
          { t: 0, l: 0, br: "8px 0 0 0", bs: "3px 0 0 3px" },
          { t: 0, r: 0, br: "0 8px 0 0", bs: "3px 3px 0 0" },
          { b: 0, l: 0, br: "0 0 0 8px", bs: "0 0 3px 3px" },
          { b: 0, r: 0, br: "0 0 8px 0", bs: "0 3px 3px 0" },
        ].map((c, i) => (
          <div key={i} style={{
            position: "absolute", width: 36, height: 36,
            top: c.t, left: c.l, right: c.r, bottom: c.b,
            borderTop: c.bs.split(" ")[0] !== "0" ? "3px solid var(--accent)" : "none",
            borderRight: c.bs.split(" ")[1] !== "0" ? "3px solid var(--accent)" : "none",
            borderBottom: c.bs.split(" ")[2] !== "0" ? "3px solid var(--accent)" : "none",
            borderLeft: c.bs.split(" ")[3] !== "0" ? "3px solid var(--accent)" : "none",
            borderRadius: c.br,
          }} />
        ))}
        {/* scan line */}
        <div className="oi-scanline" />
      </div>

      {/* hint */}
      <div style={{
        position: "absolute", left: 0, right: 0, top: "8%",
        textAlign: "center",
        fontSize: 13, fontWeight: 500,
        color: "rgba(255,255,255,.85)",
      }}>
        <Ic.scan size={26} style={{ verticalAlign: "middle", marginRight: 6 }} color="var(--accent)" />
        {hint}
      </div>

      {/* Bottom: tap any sample to "scan" */}
      <div style={{
        position: "absolute", left: 0, right: 0, bottom: 0,
        padding: "16px 12px 14px",
        background: "linear-gradient(180deg, transparent 0%, rgba(0,0,0,.7) 60%)",
      }}>
        <div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".08em", textTransform: "uppercase",
                      color: "rgba(255,255,255,.55)", padding: "0 4px 8px" }}>
          Toque pra simular scan
        </div>
        <div style={{ display: "flex", gap: 8, overflowX: "auto", padding: "2px 4px", scrollbarWidth: "none" }}>
          {sample.map(p => (
            <button key={p.id} onClick={() => { setRecent(p.id); onScan(p); }}
                    style={{
                      appearance: "none", border: 0, cursor: "pointer",
                      flex: "0 0 auto", width: 92,
                      background: recent === p.id ? "var(--accent)" : "rgba(255,255,255,.08)",
                      borderRadius: 10, padding: "10px 8px",
                      display: "flex", flexDirection: "column", gap: 6, alignItems: "flex-start",
                      color: "#fff",
                    }}>
              <div style={{ width: 30, height: 30, borderRadius: 6, background: "rgba(255,255,255,.12)",
                            display: "grid", placeItems: "center" }}>
                {React.createElement(Ic[p.img] || Ic.box, { size: 16, color: "#fff" })}
              </div>
              <div style={{ fontSize: 11, fontWeight: 600, lineHeight: 1.2,
                            whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", maxWidth: "100%" }}>
                {p.nome}
              </div>
              <div className="oi-mono" style={{ fontSize: 9.5, opacity: .7 }}>{p.sku}</div>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

// ─── Search sheet ───
function SearchSheet({ onClose, onPick }) {
  const [q, setQ] = React.useState("");
  const list = window.MOCK.PRODUTOS.filter(p => !q || (p.nome + " " + p.sku).toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="oi-sheet-backdrop" onClick={onClose}>
      <div className="oi-sheet" onClick={e => e.stopPropagation()}>
        <div className="oi-sheet-grip" />
        <div className="oi-sheet-h">
          <b>Buscar produto</b>
          <button className="oi-iconbtn close" onClick={onClose}><Ic.x /></button>
        </div>
        <div style={{ padding: "12px 16px 8px" }}>
          <div className="oi-search" autoFocus>
            <Ic.search size={16} />
            <input placeholder="Nome ou SKU" value={q} onChange={e => setQ(e.target.value)} autoFocus />
            <Ic.scan size={18} />
          </div>
        </div>
        <div className="oi-list" style={{ borderTop: 0, borderBottom: 0 }}>
          {list.map(p => (
            <div key={p.id} className="oi-list-row" onClick={() => onPick(p)}>
              <div style={{ width: 36, height: 36, borderRadius: 8, background: "var(--bg-2)",
                            border: "1px solid var(--border)", display: "grid", placeItems: "center",
                            color: "var(--text-mute)" }}>
                {React.createElement(Ic[p.img] || Ic.box, { size: 16 })}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 13 }}>{p.nome}</div>
                <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>{p.sku} · {p.estoque} {p.un}</div>
              </div>
              <div className="oi-money" style={{ fontWeight: 600, fontSize: 13 }}>{BRL(p.preco)}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

// ─── Payment sheet ───
function PaymentSheet({ total, method, setMethod, onClose, onConfirm }) {
  const methods = [
    { id: "pix",       label: "PIX",            ic: "qr",       desc: "QR Code instantâneo" },
    { id: "credito",   label: "Crédito",        ic: "dollar",   desc: "1× a 12×" },
    { id: "debito",    label: "Débito",         ic: "dollar",   desc: "Visa/Master" },
    { id: "dinheiro",  label: "Dinheiro",       ic: "dollar",   desc: "Sem comprovante" },
    { id: "boleto",    label: "Boleto",         ic: "file",     desc: "Cobrar em 7d" },
  ];
  return (
    <div className="oi-sheet-backdrop" onClick={onClose}>
      <div className="oi-sheet" onClick={e => e.stopPropagation()}>
        <div className="oi-sheet-grip" />
        <div className="oi-sheet-h">
          <b>Pagamento</b>
          <button className="oi-iconbtn close" onClick={onClose}><Ic.x /></button>
        </div>
        <div style={{ padding: "8px 16px 16px" }}>
          <div style={{ textAlign: "center", padding: "12px 0 8px" }}>
            <small style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".08em", textTransform: "uppercase", color: "var(--text-mute)" }}>
              Total a cobrar
            </small>
            <div className="oi-money" style={{ fontSize: 32, fontWeight: 600, letterSpacing: "-0.02em" }}>
              {BRL(total)}
            </div>
          </div>

          <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
            {methods.map(m => (
              <button key={m.id} onClick={() => setMethod(m.id)}
                      style={{
                        appearance: "none", textAlign: "left", cursor: "pointer",
                        display: "flex", alignItems: "center", gap: 12,
                        padding: 12,
                        background: method === m.id ? "var(--accent-soft)" : "var(--surface)",
                        border: "1px solid " + (method === m.id ? "var(--accent)" : "var(--border)"),
                        borderRadius: 10, color: "var(--text)",
                      }}>
                <div style={{ width: 36, height: 36, borderRadius: 8,
                              background: method === m.id ? "var(--accent)" : "var(--bg-2)",
                              color: method === m.id ? "#fff" : "var(--text-dim)",
                              display: "grid", placeItems: "center" }}>
                  {React.createElement(Ic[m.ic], { size: 18 })}
                </div>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 14, fontWeight: 600 }}>{m.label}</div>
                  <div style={{ fontSize: 11.5, color: "var(--text-mute)" }}>{m.desc}</div>
                </div>
                <div style={{
                  width: 18, height: 18, borderRadius: 99,
                  border: "2px solid " + (method === m.id ? "var(--accent)" : "var(--border)"),
                  background: method === m.id ? "var(--accent)" : "transparent",
                  display: "grid", placeItems: "center",
                }}>
                  {method === m.id && <div style={{ width: 8, height: 8, borderRadius: 99, background: "#fff" }} />}
                </div>
              </button>
            ))}
          </div>

          <button className="oi-btn primary block" style={{ height: 50, marginTop: 16, fontSize: 15 }}
                  onClick={onConfirm}>
            <Ic.check size={18} /> Confirmar pagamento
          </button>
        </div>
      </div>
    </div>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, {
  MaisScreen,
  ClientesScreen, ClienteDetalheScreen,
  ProducaoScreen, ProducaoJobDetalheScreen,
  VendaRapidaScreen,
  PapelBadges,
});
