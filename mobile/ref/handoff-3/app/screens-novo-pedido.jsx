// ────────────────────────────────────────────────
// NOVO PEDIDO — wizard real: Cliente → Itens → Prazo → Resumo
// Cria a OS no store compartilhado (aparece na lista de Pedidos).
// Rascunho persistido — refresh não perde o pedido em andamento.
// ────────────────────────────────────────────────
const { Ic } = window;

const NP_EMPTY = { step: 0, clienteId: null, itens: [], prazo: "amanha", hora: "18:00", pagamento: "PIX", obs: "" };
const NP_PRAZOS = [["hoje", "Hoje"], ["amanha", "Amanhã"], ["2dias", "Em 2 dias"], ["semana", "Esta semana"]];
const NP_PRAZO_LABEL = { hoje: "Hoje", amanha: "Amanhã", "2dias": "Em 2 dias", semana: "Sex" };
const NP_PAGAMENTOS = ["PIX", "Boleto 7 dias", "Crédito", "Dinheiro"];

function NovoPedidoScreen({ nav, ctx }) {
  const { DetailHeader } = window.Screens;
  const BRL = window.BRL;
  const [d, setD] = window.useStore("novo-pedido.draft", NP_EMPTY);
  const [mut, setMut] = window.useStore("pedidos.mut", window.OIPedidos.EMPTY_MUT);
  const [sheet, setSheet] = React.useState(null); // "produto"

  const cliente = window.MOCK.CLIENTES.find(c => c.id === d.clienteId);
  const total = d.itens.reduce((s, i) => s + i.preco * i.qtd, 0);
  const steps = ["Cliente", "Itens", "Prazo", "Resumo"];
  const canNext = [!!cliente, d.itens.length > 0, true, true][d.step];

  const set = (patch) => setD(prev => ({ ...prev, ...patch }));
  const cancelar = () => { setD(NP_EMPTY); nav.pop(); };

  const criar = () => {
    const n = 3047 + (mut.created || []).length;
    const first = d.itens[0];
    const os = {
      id: "OS-" + n,
      cliente: cliente.nome,
      nome: first.nome,
      produto: d.itens.map(i => i.qtd + "× " + i.nome).join(" · "),
      specs: d.itens.map(i => i.qtd + "× " + i.nome).join(" · "),
      thumb: first.img || "tag",
      valor: total,
      etapa: "Orçamento", etapaKey: "orc",
      prazo: NP_PRAZO_LABEL[d.prazo] + " " + d.hora,
      urgent: d.prazo === "hoje",
      atualizado: "agora", criado: "Agora",
      progresso: 0.1,
      pagamento: d.pagamento, obs: d.obs,
    };
    setMut(m => ({ ...m, created: [os, ...(m.created || [])] }));
    setD(NP_EMPTY);
    nav.pop();
    window.oiToast("OS-" + n + " criada", "ok");
  };

  return (
    <>
      <DetailHeader nav={nav} title="Novo pedido" eyebrow={"Etapa " + (d.step + 1) + " de 4"}
                    actions={<button className="oi-iconbtn" aria-label="Cancelar" onClick={cancelar}><Ic.x /></button>} />

      {/* chips de etapa */}
      <div style={{ padding: "8px 16px 4px" }}>
        <div className="oi-chips">
          {steps.map((s, i) => (
            <button key={s} className={"oi-chip" + (i === d.step ? " on" : "")}
                    disabled={i > d.step && !canNext}
                    onClick={() => { if (i < d.step) set({ step: i }); }}>
              {i < d.step ? "✓ " : ""}{s}
            </button>
          ))}
        </div>
      </div>

      <div className="oi-scroll">
        {d.step === 0 && <NpCliente d={d} set={set} nav={nav} />}
        {d.step === 1 && <NpItens d={d} set={set} openSearch={() => setSheet("produto")} />}
        {d.step === 2 && <NpPrazo d={d} set={set} />}
        {d.step === 3 && <NpResumo d={d} cliente={cliente} total={total} />}
        <div style={{ height: 90 }}></div>
      </div>

      {/* barra de avanço */}
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8, alignItems: "center" }}>
        {d.step > 0 && (
          <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }} onClick={() => set({ step: d.step - 1 })}>
            <Ic.chevL size={16} /> Voltar
          </button>
        )}
        {total > 0 && d.step < 3 && (
          <span className="oi-money" style={{ marginLeft: 4, fontWeight: 700, fontSize: 14 }}>{BRL(total)}</span>
        )}
        {d.step < 3 ? (
          <button className="oi-btn primary" style={{ flex: "1 1 auto" }} disabled={!canNext}
                  onClick={() => set({ step: d.step + 1 })}>
            Avançar <Ic.chevR size={16} />
          </button>
        ) : (
          <button className="oi-btn primary" style={{ flex: "1 1 auto" }} onClick={criar}>
            <Ic.check size={18} /> Criar OS · {BRL(total)}
          </button>
        )}
      </div>

      {sheet === "produto" && (
        <NpProdutoSheet onClose={() => setSheet(null)}
          onPick={(p) => {
            setD(prev => {
              const ex = prev.itens.find(i => i.id === p.id);
              const itens = ex
                ? prev.itens.map(i => i.id === p.id ? { ...i, qtd: i.qtd + 1 } : i)
                : [...prev.itens, { id: p.id, nome: p.nome, preco: p.preco, un: p.un, img: p.img, qtd: 1 }];
              return { ...prev, itens };
            });
            setSheet(null);
          }} />
      )}
    </>
  );
}

// ─── Etapa 1: cliente ───
function NpCliente({ d, set, nav }) {
  const [q, setQ] = React.useState("");
  const list = window.MOCK.CLIENTES
    .filter(c => (c.papeis || []).includes("cliente"))
    .filter(c => !q || c.nome.toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="oi-section">
      <div className="oi-search" style={{ marginBottom: 10 }}>
        <Ic.search size={16} />
        <input placeholder="Buscar cliente" value={q} onChange={e => setQ(e.target.value)} />
      </div>
      <div className="oi-list card">
        {list.map(c => {
          const on = d.clienteId === c.id;
          return (
            <div key={c.id} className="oi-list-row" onClick={() => set({ clienteId: c.id })}
                 style={on ? { background: "var(--accent-soft)" } : {}}>
              <div className={"oi-av " + c.av} style={{ width: 34, height: 34, borderRadius: "50%", fontSize: 12, flex: "0 0 auto" }}>
                {c.nome.split(" ").map(w => w[0]).slice(0, 2).join("")}
              </div>
              <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 13.5 }}>{c.nome}</div>
                <div style={{ fontSize: 11.5, color: "var(--text-dim)" }}>{c.pedidos} pedidos · {c.cidade}</div>
              </div>
              {on ? <Ic.checkCircle size={20} color="var(--accent)" /> : <Ic.chevR color="var(--text-mute)" />}
            </div>
          );
        })}
      </div>
      <button className="oi-btn block" style={{ marginTop: 10 }} onClick={() => nav.push("novo-cliente")}>
        <Ic.plus size={16} /> Cadastrar novo cliente
      </button>
    </div>
  );
}

// ─── Etapa 2: itens ───
function NpItens({ d, set, openSearch }) {
  const BRL = window.BRL;
  const setQtd = (id, delta) => set({
    itens: d.itens
      .map(i => i.id === id ? { ...i, qtd: i.qtd + delta } : i)
      .filter(i => i.qtd > 0),
  });
  return (
    <div className="oi-section">
      {d.itens.length === 0 ? (
        <div className="oi-empty" style={{ marginTop: 20 }}>
          <div className="oi-empty-ico"><Ic.box size={26} /></div>
          <b>Nenhum item ainda</b>
          <small>Adicione produtos do catálogo ao pedido.</small>
        </div>
      ) : (
        <div className="oi-list card">
          {d.itens.map(i => (
            <div key={i.id} className="oi-list-row" style={{ gap: 10 }}>
              <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 13.5 }}>{i.nome}</div>
                <div className="oi-mono" style={{ fontSize: 11.5, color: "var(--text-dim)" }}>{BRL(i.preco)} / {i.un}</div>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: 8, flex: "0 0 auto" }}>
                <button className="oi-iconbtn" aria-label="Diminuir" style={{ width: 30, height: 30, background: "var(--bg-2)", borderRadius: 8 }} onClick={() => setQtd(i.id, -1)}><Ic.minus size={15} /></button>
                <span className="oi-mono" style={{ minWidth: 22, textAlign: "center", fontWeight: 600 }}>{i.qtd}</span>
                <button className="oi-iconbtn" aria-label="Aumentar" style={{ width: 30, height: 30, background: "var(--bg-2)", borderRadius: 8 }} onClick={() => setQtd(i.id, 1)}><Ic.plus size={15} /></button>
              </div>
            </div>
          ))}
        </div>
      )}
      <button className="oi-btn block" style={{ marginTop: 10 }} onClick={openSearch}>
        <Ic.search size={16} /> Adicionar produto
      </button>
    </div>
  );
}

// ─── Etapa 3: prazo + pagamento ───
function NpPrazo({ d, set }) {
  return (
    <>
      <div className="oi-section">
        <h3 className="oi-section-h">Prazo de entrega</h3>
        <div className="oi-chips">
          {NP_PRAZOS.map(([id, l]) => (
            <button key={id} className={"oi-chip" + (d.prazo === id ? " on" : "")} onClick={() => set({ prazo: id })}>{l}</button>
          ))}
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 10, marginTop: 12 }}>
          <span style={{ fontSize: 12.5, color: "var(--text-dim)" }}>Horário</span>
          <input className="oi-input oi-mono" type="time" value={d.hora} onChange={e => set({ hora: e.target.value })} style={{ width: 110 }} />
        </div>
      </div>
      <div className="oi-section">
        <h3 className="oi-section-h">Pagamento</h3>
        <div className="oi-chips">
          {NP_PAGAMENTOS.map(p => (
            <button key={p} className={"oi-chip" + (d.pagamento === p ? " on" : "")} onClick={() => set({ pagamento: p })}>{p}</button>
          ))}
        </div>
      </div>
      <div className="oi-section">
        <h3 className="oi-section-h">Observações</h3>
        <textarea className="oi-input" style={{ width: "100%", minHeight: 72, paddingTop: 10 }} placeholder="Ex.: cliente busca na loja"
                  value={d.obs} onChange={e => set({ obs: e.target.value })}></textarea>
      </div>
    </>
  );
}

// ─── Etapa 4: resumo ───
function NpResumo({ d, cliente, total }) {
  const BRL = window.BRL;
  return (
    <>
      <div className="oi-section">
        <h3 className="oi-section-h">Resumo do pedido</h3>
        <div className="oi-card pad">
          <dl className="oi-dl">
            <dt>Cliente</dt> <dd>{cliente ? cliente.nome : "—"}</dd>
            <dt>Itens</dt>
            <dd>
              {d.itens.map(i => (
                <div key={i.id} style={{ display: "flex", gap: 8 }}>
                  <span style={{ flex: 1 }}>{i.qtd}× {i.nome}</span>
                  <span className="oi-mono">{BRL(i.preco * i.qtd)}</span>
                </div>
              ))}
            </dd>
            <dt>Prazo</dt> <dd className="oi-mono">{NP_PRAZO_LABEL[d.prazo]} {d.hora}</dd>
            <dt>Pagamento</dt> <dd>{d.pagamento}</dd>
            {d.obs && <><dt>Obs.</dt> <dd style={{ color: "var(--text-dim)" }}>{d.obs}</dd></>}
            <dt>Total</dt> <dd className="oi-mono" style={{ fontWeight: 700, fontSize: 15 }}>{BRL(total)}</dd>
          </dl>
        </div>
        <p style={{ fontSize: 12, color: "var(--text-mute)", margin: "10px 2px 0" }}>
          A OS entra na etapa <b>Orçamento</b> — avance pelo detalhe do pedido após a aprovação do cliente.
        </p>
      </div>
    </>
  );
}

// ─── Sheet: buscar produto do catálogo ───
function NpProdutoSheet({ onClose, onPick }) {
  const BRL = window.BRL;
  const [q, setQ] = React.useState("");
  const list = window.MOCK.PRODUTOS.filter(p => p.cat !== "Insumos")
    .filter(p => !q || (p.nome + " " + p.sku).toLowerCase().includes(q.toLowerCase()));
  return (
    <window.OISheet title="Adicionar produto" onClose={onClose}>
      <div className="oi-search" style={{ marginBottom: 10 }}>
        <Ic.search size={16} />
        <input autoFocus placeholder="Nome ou SKU" value={q} onChange={e => setQ(e.target.value)} />
      </div>
      <div className="oi-list" style={{ borderTop: 0, borderBottom: 0, maxHeight: 320, overflowY: "auto" }}>
        {list.map(p => (
          <div key={p.id} className="oi-list-row" onClick={() => onPick(p)}>
            <div style={{ flex: "1 1 auto", minWidth: 0 }}>
              <div style={{ fontWeight: 600, fontSize: 13.5 }}>{p.nome}</div>
              <div className="oi-mono" style={{ fontSize: 11.5, color: "var(--text-dim)" }}>{p.sku} · {BRL(p.preco)} / {p.un}</div>
            </div>
            <Ic.plus size={18} color="var(--accent)" />
          </div>
        ))}
      </div>
    </window.OISheet>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { NovoPedidoScreen });
