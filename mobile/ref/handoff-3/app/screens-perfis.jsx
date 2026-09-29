// ────────────────────────────────────────────────
// PERFIS DE MENU — admin monta a barra de cada função
// Início e Mais são fixos; o miolo (até 3) é configurável.
// ────────────────────────────────────────────────
const { Ic: IcP } = window;
const MP = window.MOCK;
const { DetailHeader: DHp } = window.Screens;

// Mini-prévia da barra inferior
function BarPreview({ mods, active }) {
  const slots = [
    { id: "inicio", label: "Início", ic: "home", fix: true },
    ...mods.map(id => MP.menuModule(id)).filter(Boolean),
    { id: "mais", label: "Mais", ic: "layers", fix: true },
  ];
  return (
    <div style={{ display: "grid", gridTemplateColumns: "repeat(" + slots.length + ", 1fr)",
                  background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 12, padding: "8px 4px", gap: 2 }}>
      {slots.map(s => (
        <div key={s.id} style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 3,
                                 color: s.fix ? "var(--text-mute)" : "var(--accent)", padding: "2px 0" }}>
          <div style={{ width: 30, height: 22, borderRadius: 999, display: "grid", placeItems: "center",
                        background: s.fix ? "transparent" : "var(--accent-soft)" }}>
            {React.createElement(IcP[s.ic] || IcP.box, { size: 17 })}
          </div>
          <span style={{ fontSize: 9, fontWeight: 600, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", maxWidth: "100%" }}>{s.label}</span>
          {s.fix && <span style={{ fontSize: 7.5, color: "var(--text-mute)", letterSpacing: ".04em" }}>FIXO</span>}
        </div>
      ))}
    </div>
  );
}

// ─── Lista de perfis ───
function PerfisScreen({ nav, ctx, setTweak }) {
  const [, force] = React.useReducer(x => x + 1, 0);
  const perfis = MP.MENU_PERFIS;
  const ativo = ctx.perfil;

  return (
    <>
      <DHp nav={nav} title="Perfis de menu" eyebrow="Barra personalizada por função" />
      <div className="oi-scroll">
        <div className="oi-section">
          <p style={{ margin: "0 0 12px", fontSize: 12.5, color: "var(--text-dim)", lineHeight: 1.5 }}>
            Monte a barra inferior de cada função. O administrador escolhe quais módulos aparecem —
            ex.: a oficina troca <b>Produção</b> por <b>Oficina</b>; o faturamento prioriza <b>Vendas</b>,
            <b> Financeiro</b> e <b>Relatórios</b>. <b>Início</b> e <b>Mais</b> são sempre fixos.
          </p>
        </div>

        <div className="oi-section" style={{ paddingTop: 0 }}>
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {perfis.map(p => {
              const on = p.id === ativo;
              return (
                <div key={p.id} className="oi-card" style={{ padding: 14, gap: 12,
                     borderColor: on ? "var(--accent)" : "var(--border)",
                     boxShadow: on ? "0 0 0 1px var(--accent)" : "none" }}>
                  <div style={{ display: "flex", alignItems: "flex-start", gap: 8 }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <span style={{ fontSize: 15, fontWeight: 600 }}>{p.nome}</span>
                        {on && <span className="oi-status accent" style={{ fontSize: 10 }}><span className="dot" />Ativo</span>}
                      </div>
                      <div style={{ fontSize: 11.5, color: "var(--text-mute)", marginTop: 1 }}>{p.funcao}</div>
                    </div>
                    <button className="oi-iconbtn" onClick={() => nav.push("perfil-edit", { id: p.id })} style={{ width: 34, height: 34 }}>
                      <IcP.edit size={16} />
                    </button>
                  </div>

                  <BarPreview mods={p.mods} />

                  {!on && (
                    <button className="oi-btn primary block" onClick={() => { setTweak("perfil", p.id); force(); }}>
                      <IcP.check size={16} /> Aplicar este perfil
                    </button>
                  )}
                </div>
              );
            })}
          </div>

          <button className="oi-btn block action" style={{ marginTop: 12 }} onClick={() => nav.push("perfil-edit", { id: null })}>
            <IcP.plus size={18} /> Novo perfil de menu
          </button>
        </div>
        <div style={{ height: 24 }} />
      </div>
    </>
  );
}

// ─── Editor de perfil ───
function PerfilEditScreen({ nav, ctx, params, setTweak }) {
  const editing = params && params.id ? MP.MENU_PERFIS.find(p => p.id === params.id) : null;
  const [nome, setNome] = React.useState(editing ? editing.nome : "");
  const [funcao, setFuncao] = React.useState(editing ? editing.funcao : "");
  const [mods, setMods] = React.useState(editing ? [...editing.mods] : []);

  const disponiveis = MP.MENU_MODULES.filter(m => !mods.includes(m.id));
  const cheio = mods.length >= 3;

  const addMod = (id) => { if (!cheio) setMods(m => [...m, id]); };
  const rmMod = (id) => setMods(m => m.filter(x => x !== id));
  const move = (i, dir) => setMods(m => {
    const j = i + dir;
    if (j < 0 || j >= m.length) return m;
    const n = [...m]; [n[i], n[j]] = [n[j], n[i]]; return n;
  });

  const salvar = () => {
    const nomeF = nome.trim() || "Novo perfil";
    if (editing) {
      editing.nome = nomeF; editing.funcao = funcao.trim() || "—"; editing.mods = mods;
    } else {
      const novo = { id: "perf-" + Date.now(), nome: nomeF, funcao: funcao.trim() || "—", mods, sys: false };
      MP.MENU_PERFIS.push(novo);
      setTweak("perfil", novo.id);
      nav.pop(); return;
    }
    setTweak("perfil", editing.id);
    nav.pop();
  };
  const excluir = () => {
    const i = MP.MENU_PERFIS.findIndex(p => p.id === editing.id);
    if (i >= 0) MP.MENU_PERFIS.splice(i, 1);
    if (ctx.perfil === editing.id) setTweak("perfil", MP.MENU_PERFIS[0].id);
    nav.pop();
  };

  return (
    <>
      <DHp nav={nav} title={editing ? "Editar perfil" : "Novo perfil"} eyebrow="Configuração da barra" />
      <div className="oi-scroll">
        <div className="oi-section">
          <div className="oi-field">
            <label>Nome do perfil <span className="req">*</span></label>
            <input className="oi-input" placeholder="Ex: Faturamento" value={nome} onChange={e => setNome(e.target.value)} />
          </div>
          <div className="oi-field">
            <label>Função / setor</label>
            <input className="oi-input" placeholder="Ex: Financeiro / fiscal" value={funcao} onChange={e => setFuncao(e.target.value)} />
          </div>
        </div>

        {/* Prévia */}
        <div className="oi-section" style={{ paddingTop: 0 }}>
          <h3 className="oi-section-h">Prévia da barra</h3>
          <BarPreview mods={mods} />
        </div>

        {/* Módulos selecionados */}
        <div className="oi-section">
          <h3 className="oi-section-h">
            Módulos na barra
            <span style={{ marginLeft: "auto", color: cheio ? "var(--warn)" : "var(--text-mute)", fontWeight: 600 }}>{mods.length}/3</span>
          </h3>
          {mods.length === 0 ? (
            <div className="oi-card pad" style={{ alignItems: "center", color: "var(--text-mute)", padding: "18px 16px", fontSize: 12.5 }}>
              Nenhum módulo ainda — adicione abaixo (Início e Mais já são fixos).
            </div>
          ) : (
            <div className="oi-list card">
              {mods.map((id, i) => {
                const m = MP.menuModule(id);
                return (
                  <div key={id} className="oi-list-row">
                    <div style={{ width: 32, height: 32, borderRadius: 8, background: "var(--accent-soft)", color: "var(--accent)",
                                  display: "grid", placeItems: "center", flex: "0 0 auto" }}>
                      {React.createElement(IcP[m.ic] || IcP.box, { size: 16 })}
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 13.5, fontWeight: 600 }}>{m.label}</div>
                      <div style={{ fontSize: 11, color: "var(--text-mute)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{m.desc}</div>
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 2, flex: "0 0 auto" }}>
                      <button className="oi-iconbtn" style={{ width: 30, height: 30, background: "transparent", opacity: i === 0 ? 0.3 : 1 }}
                              onClick={() => move(i, -1)}><IcP.chevU size={16} /></button>
                      <button className="oi-iconbtn" style={{ width: 30, height: 30, background: "transparent", opacity: i === mods.length - 1 ? 0.3 : 1 }}
                              onClick={() => move(i, 1)}><IcP.chevD size={16} /></button>
                      <button className="oi-iconbtn" style={{ width: 30, height: 30, background: "transparent", color: "var(--danger)" }}
                              onClick={() => rmMod(id)}><IcP.x size={16} /></button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Módulos disponíveis */}
        {disponiveis.length > 0 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Adicionar módulo {cheio && <span style={{ marginLeft: "auto", color: "var(--warn)", fontWeight: 600, textTransform: "none", letterSpacing: 0 }}>Barra cheia — remova um para trocar</span>}</h3>
            <div className="oi-list card" style={{ opacity: cheio ? 0.5 : 1, pointerEvents: cheio ? "none" : "auto" }}>
              {disponiveis.map(m => (
                <div key={m.id} className="oi-list-row" onClick={() => addMod(m.id)}>
                  <div style={{ width: 32, height: 32, borderRadius: 8, background: "var(--bg-2)", border: "1px solid var(--border)",
                                color: "var(--text-dim)", display: "grid", placeItems: "center", flex: "0 0 auto" }}>
                    {React.createElement(IcP[m.ic] || IcP.box, { size: 16 })}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 13.5, fontWeight: 600 }}>{m.label}</div>
                    <div style={{ fontSize: 11, color: "var(--text-mute)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{m.desc}</div>
                  </div>
                  <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11.5, color: "var(--action)", fontWeight: 600 }}>
                    <IcP.plus size={14} /> Adicionar
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}

        {editing && !editing.sys && (
          <div className="oi-section">
            <button className="oi-btn block ghost" style={{ color: "var(--danger)" }} onClick={excluir}>
              <IcP.trash size={16} /> Excluir perfil
            </button>
          </div>
        )}
        <div style={{ height: 100 }} />
      </div>

      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        <button className="oi-btn action block" onClick={salvar} disabled={!nome.trim()}
                style={{ opacity: nome.trim() ? 1 : 0.5 }}>
          <IcP.check size={18} /> {editing ? "Salvar e aplicar" : "Criar e aplicar"}
        </button>
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { PerfisScreen, PerfilEditScreen });
