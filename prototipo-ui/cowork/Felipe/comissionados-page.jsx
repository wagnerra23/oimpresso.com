// comissionados-page.jsx — Comissionados (legado /sales-commission-agents: SalesCommissionAgentController).
// Agentes de venda: % de comissão, vendas do período, comissão apurada e a pagar. Expõe window.ComissionadosPage.
(() => {
const { useState, useMemo } = React;
const { Kpis, Kpi, Meta, Vazio, Confirm } = window.AcessosDS;

const AGENTES = [
  { id:1, nome:"Larissa Souza",   email:"larissa@rotalivre.com", tel:"(31) 99812-4477", usuario:"larissa", pct:2.5,  vendas:48200, pedidos:37, meta:45000, status:"active", pago:false },
  { id:2, nome:"Bruno Carvalho",  email:"bruno@rotalivre.com",   tel:"(31) 99674-1120", usuario:"bruno",   pct:3,    vendas:71450, pedidos:29, meta:60000, status:"active", pago:false },
  { id:3, nome:"Patrícia Gomes",  email:"patricia@wr2.com.br",   tel:"(31) 98450-2233", usuario:"patricia",pct:3.5,  vendas:39900, pedidos:22, meta:50000, status:"active", pago:true },
  { id:4, nome:"Joana Lima",      email:"joana@rotalivre.com",   tel:"(31) 99101-8865", usuario:"joana",   pct:2,    vendas:18300, pedidos:14, meta:25000, status:"active", pago:false },
  { id:5, nome:"Ricardo Neves",   email:"ricardo@parceiro.com",  tel:"(31) 98877-3010", usuario:null,      pct:5,    vendas:26700, pedidos:6,  meta:20000, status:"active", pago:true, externo:true },
  { id:6, nome:"Marcos Antunes",  email:"marcos@wr2.com.br",     tel:"(31) 99233-7788", usuario:"marcos",  pct:2,    vendas:0,     pedidos:0,  meta:20000, status:"inactive", pago:false },
];

const PERIODOS = [
  { id:"mes",     label:"Este mês" },
  { id:"anterior",label:"Mês anterior" },
  { id:"tri",     label:"Trimestre" },
];

const brl = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const brl0 = (n) => "R$ " + n.toLocaleString("pt-BR", { maximumFractionDigits: 0 });
function initials(n){ return n.split(/\s+/).slice(0,2).map(w=>w[0]).join("").toUpperCase(); }
function avColor(n){ const h=[...n].reduce((a,c)=>a+c.charCodeAt(0),0)%360; return { bg:`oklch(0.92 0.04 ${h})`, fg:`oklch(0.42 0.13 ${h})` }; }

function Kebab({ items }) {
  const [open, setOpen] = useState(false);
  const ref = React.useRef(null);
  React.useEffect(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="cli-kebab-wrap" ref={ref}>
      <button className="cli-kebab-btn" onClick={(e) => { e.stopPropagation(); setOpen(!open); }} aria-expanded={open} title="Mais ações">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
      </button>
      {open && (
        <div className="cli-kebab-menu" onClick={(e) => e.stopPropagation()}>
          {items.map((it, i) => it.sep
            ? <div key={i} className="cli-kebab-sep"></div>
            : <button key={i} className={it.danger ? "danger" : ""} onClick={() => { setOpen(false); it.action?.(); }}>{it.label}</button>)}
        </div>
      )}
    </div>
  );
}

function Campo({ label, req, valor, onChange, mono, ajuda, placeholder }) {
  return (
    <div className="cms-field">
      <label>{label}{req && <i> *</i>}</label>
      <input className={mono ? "mono" : ""} value={valor} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
      {ajuda && <small>{ajuda}</small>}
    </div>
  );
}

// Cadastro — campos reais do legado (prefixo, primeiro nome, sobrenome, e-mail, contato, endereço, % de comissão)
// + a regra de cálculo que o legado não tem.
function CadastroDrawer({ agente, onClose }) {
  const novo = !agente;
  const [f, setF] = useState(() => {
    const nome = (agente?.nome || "").split(" ");
    return {
      prefixo: agente?.prefixo || "", primeiro: nome[0] || "", sobrenome: nome.slice(1).join(" "),
      email: agente?.email || "", contato: agente?.tel || "", endereco: agente?.endereco || "",
      pct: agente ? String(agente.pct).replace(".", ",") : "", pct2: "", meta: agente ? String(agente.meta) : "",
      regra: agente && agente.pct >= 5 ? "margem" : "fixa",
      login: agente?.usuario ? "sim" : "nao",
    };
  });
  const set = (k) => (v) => setF((s) => ({ ...s, [k]: v }));

  return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="os-drawer cms-drawer">
        <div className="os-drawer-head">
          <div className="os-drawer-head-l">
            <div className="os-drawer-id">Comissionado</div>
            <h2>{novo ? "Novo comissionado" : f.primeiro + " " + f.sobrenome}</h2>
            <p>{novo ? "Agente de venda que recebe comissão sobre o faturado." : "Cadastro e regra de comissão."}</p>
          </div>
          <div className="os-drawer-head-r">
            <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          </div>
        </div>

        <div className="os-drawer-body">
          <div className="os-drawer-section">
            <h3>Identificação</h3>
            <div className="cms-form">
              <div className="cms-f-row">
                <Campo label="Prefixo" valor={f.prefixo} onChange={set("prefixo")} mono placeholder="48" ajuda="coluna surname" />
                <Campo label="Primeiro nome" req valor={f.primeiro} onChange={set("primeiro")} />
                <Campo label="Sobrenome" valor={f.sobrenome} onChange={set("sobrenome")} />
              </div>
              <div className="cms-f-row two">
                <Campo label="E-mail" valor={f.email} onChange={set("email")} />
                <Campo label="Contato" valor={f.contato} onChange={set("contato")} placeholder="(31) 99999-0000" />
              </div>
              <div className="cms-f-row one">
                <div className="cms-field">
                  <label>Endereço</label>
                  <textarea value={f.endereco} onChange={(e) => set("endereco")(e.target.value)} />
                </div>
              </div>
            </div>
          </div>

          <div className="os-drawer-section">
            <h3>Regra de comissão</h3>
            <div className="cms-regra">
              <label className={`cms-regra-opt ${f.regra === "fixa" ? "on" : ""}`}>
                <input type="radio" checked={f.regra === "fixa"} onChange={() => set("regra")("fixa")} />
                <span>
                  <b>Percentual fixo sobre o faturado</b>
                  <small>É o que o legado faz hoje — um único campo de %.</small>
                </span>
              </label>
              <label className={`cms-regra-opt ${f.regra === "faixa" ? "on" : ""}`}>
                <input type="radio" checked={f.regra === "faixa"} onChange={() => set("regra")("faixa")} />
                <span>
                  <b>Percentual por faixa de meta</b>
                  <small>Um % até a meta, outro acima dela.</small>
                </span>
              </label>
              <label className={`cms-regra-opt ${f.regra === "margem" ? "on" : ""}`}>
                <input type="radio" checked={f.regra === "margem"} onChange={() => set("regra")("margem")} />
                <span>
                  <b>Percentual sobre a margem</b>
                  <small>Para gráfica, onde o m² varia muito — comissiona o lucro, não o faturamento.</small>
                </span>
              </label>
            </div>
          </div>

          <div className="os-drawer-section">
            <h3>Valores</h3>
            <div className="cms-form">
              <div className="cms-f-row two">
                <Campo label={f.regra === "faixa" ? "% até a meta" : "% de comissão"} req valor={f.pct} onChange={set("pct")} mono placeholder="2,50"
                  ajuda={f.regra === "margem" ? "Aplicado sobre a margem do pedido." : null} />
                {f.regra === "faixa"
                  ? <Campo label="% acima da meta" valor={f.pct2} onChange={set("pct2")} mono placeholder="3,50" />
                  : <Campo label="Meta do período" valor={f.meta} onChange={set("meta")} mono ajuda="Só para acompanhamento." />}
              </div>
              {f.regra === "faixa" && (
                <div className="cms-f-row one">
                  <Campo label="Meta do período" valor={f.meta} onChange={set("meta")} mono ajuda="Divide as duas faixas." />
                </div>
              )}
            </div>
          </div>

          <div className="os-drawer-section">
            <h3>Login e vínculo</h3>
            <div className="cms-form">
              <div className="cms-f-row one">
                <div className="cms-field">
                  <label>Como o banco guarda</label>
                  <small>
                    No <code>main</code> um comissionado é uma linha de <code>users</code> com <code>is_cmmsn_agnt = 1</code> e
                    <code> allow_login = 0</code> — não é um vínculo com usuário, é um usuário sem senha.
                    Para o parceiro externo isso serve; para a Larissa (que também vende) gera cadastro dobrado.
                  </small>
                </div>
              </div>
              <div className="cms-regra">
                <label className={`cms-regra-opt ${f.login === "nao" ? "on" : ""}`}>
                  <input type="radio" checked={f.login === "nao"} onChange={() => set("login")("nao")} />
                  <span>
                    <b>Só comissionado, sem acesso ao sistema</b>
                    <small>É o comportamento atual do legado (<code>allow_login = 0</code>).</small>
                  </span>
                </label>
                <label className={`cms-regra-opt ${f.login === "sim" ? "on" : ""}`}>
                  <input type="radio" checked={f.login === "sim"} onChange={() => set("login")("sim")} />
                  <span>
                    <b>É também usuário do sistema{agente?.usuario ? " — @" + agente.usuario : ""}</b>
                    <small>Marca <code>is_cmmsn_agnt</code> no usuário que já existe, em vez de criar outra linha. Com a permissão “Comissionado vê as vendas dele”, ele acompanha a própria comissão.</small>
                  </span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div className="os-drawer-actions">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" onClick={onClose}>{novo ? "Cadastrar" : "Salvar"}</button>
        </div>
      </div>
    </>
  );
}

function ComissionadosPage() {
  const [q, setQ] = useState("");
  const [del, setDel] = useState(null);
  const [cad, setCad] = useState(null);
  const [periodo, setPeriodo] = useState("mes");
  const [soAtivos, setSoAtivos] = useState(true);

  const lista = useMemo(() => AGENTES.map((a) => ({ ...a, comissao: a.vendas * a.pct / 100 })), []);
  const filtered = lista.filter((a) => {
    if (soAtivos && a.status !== "active") return false;
    if (q) { const s = q.toLowerCase(); if (![a.nome, a.email].some((v) => v.toLowerCase().includes(s))) return false; }
    return true;
  });

  const tot = {
    vendas: filtered.reduce((s, a) => s + a.vendas, 0),
    comissao: filtered.reduce((s, a) => s + a.comissao, 0),
    aPagar: filtered.filter((a) => !a.pago).reduce((s, a) => s + a.comissao, 0),
    agentes: filtered.length,
  };

  return (
    <div className="os-page usr-page cms-page" data-screen-label="Usuários · Comissionados">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Comissionados</h1>
          <p>{tot.agentes} agentes de venda · {PERIODOS.find((p) => p.id === periodo).label.toLowerCase()}</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => window.__selectRoute?.("financeiro")}>Ver no financeiro</button>
          <button className="os-btn primary" onClick={() => setCad({ novo: true })}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Novo comissionado
          </button>
        </div>
      </header>

      <Kpis>
        <Kpi v={brl0(tot.vendas)} l="Vendas atribuídas" />
        <Kpi v={brl0(tot.comissao)} l="Comissão apurada" tone="info" />
        <Kpi v={brl0(tot.aPagar)} l="A pagar" tone="warning" />
        <Kpi v={tot.agentes} l="Comissionados ativos" />
      </Kpis>

      <div className="usr-toolbar">
        <div className="usr-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar comissionado…" />
        </div>
        <div className="usr-filters">
          {PERIODOS.map((p) => (
            <button key={p.id} className={`os-btn sm ${periodo === p.id ? "primary" : ""}`} onClick={() => setPeriodo(p.id)}>{p.label}</button>
          ))}
          <button className="usr-clear" onClick={() => setSoAtivos(!soAtivos)}>{soAtivos ? "Mostrar inativos" : "Só ativos"}</button>
          <span className="usr-count">{filtered.length} de {AGENTES.length}</span>
        </div>
      </div>

      <div className="os-table-wrap">
        <table className="os-table cms-table">
          <thead><tr>
            <th>Comissionado</th><th>Comissão</th><th className="cms-th-num">Vendas</th><th className="cms-th-num">Pedidos</th>
            <th>Meta</th><th className="cms-th-num">A receber</th><th>Pagamento</th><th className="usr-th-act"></th>
          </tr></thead>
          <tbody>
            {filtered.map((a) => {
              const c = avColor(a.nome);
              const pctMeta = a.meta ? Math.min(Math.round(a.vendas / a.meta * 100), 999) : 0;
              return (
                <tr key={a.id}>
                  <td>
                    <div className="usr-id">
                      <div className="usr-avatar" style={{ background: c.bg, color: c.fg }}>{initials(a.nome)}</div>
                      <div className="usr-id-meta">
                        <b>{a.nome}{a.externo && <span className="usr-you">parceiro</span>}</b>
                        <small>{a.usuario ? "@" + a.usuario : a.email}</small>
                      </div>
                    </div>
                  </td>
                  <td><span className={`cms-pct ${a.pct <= 2 ? "base" : ""}`}>{String(a.pct).replace(".", ",")}%</span></td>
                  <td className="cms-td-num"><span className="cms-num">{brl0(a.vendas)}</span></td>
                  <td className="cms-td-num"><span className="cms-num dim">{a.pedidos}</span></td>
                  <td>
                    <Meta pct={pctMeta} />
                  </td>
                  <td className="cms-td-num"><span className={`cms-num ${a.pago ? "dim" : "pos"}`}>{brl(a.comissao)}</span></td>
                  <td>
                    <span className={`cms-pay ${a.pago ? "pago" : "aberto"}`}>
                      <span className="cms-pay-dot"></span>{a.pago ? "Pago" : "Em aberto"}
                    </span>
                  </td>
                  <td className="usr-td-act">
                    <Kebab items={[
                      { label: "Editar comissionado", action: () => setCad({ agente: a }) },
                      { label: "Ver vendas atribuídas", action: () => {} },
                      { label: "Extrato de comissão", action: () => {} },
                      { sep: true },
                      { label: a.pago ? "Reabrir pagamento" : "Lançar pagamento", action: () => {} },
                      { label: "Excluir", danger: true, action: () => setDel(a) },
                    ]}/>
                  </td>
                </tr>
              );
            })}
          </tbody>
          <tfoot><tr>
            <td><span className="cms-total-l">Total do período</span></td>
            <td></td>
            <td className="cms-td-num"><span className="cms-num">{brl0(tot.vendas)}</span></td>
            <td className="cms-td-num"><span className="cms-num dim">{filtered.reduce((s, a) => s + a.pedidos, 0)}</span></td>
            <td></td>
            <td className="cms-td-num"><span className="cms-num pos">{brl(tot.comissao)}</span></td>
            <td colSpan="2"><span className="cms-total-l">{brl(tot.aPagar)} a pagar</span></td>
          </tr></tfoot>
        </table>
        {filtered.length === 0 && <Vazio title="Nenhum comissionado encontrado." description="Ajuste a busca ou cadastre um agente de venda." />}
      </div>

      <p className="cms-note">A comissão é apurada sobre vendas faturadas do período. O lançamento gera um título a pagar no Financeiro.</p>
      {cad && <CadastroDrawer agente={cad.agente} onClose={() => setCad(null)} />}
      {del && (
        <Confirm open={!!del} onClose={() => setDel(null)}
          title={del.pedidos > 0 ? "Este comissionado não pode ser excluído" : "Excluir " + del.nome + "?"}
          cta={del.pedidos > 0 ? null : "Excluir definitivamente"}
          ctaAlt={del.pedidos > 0 ? <button className="os-btn primary" onClick={() => setDel(null)}>Inativar em vez de excluir</button> : null}>
          {del.pedidos > 0 ? (
            <>
              <p>
                Tem <b>{del.pedidos} {del.pedidos === 1 ? "venda" : "vendas"}</b> apontando para ele.
                No legado a exclusão apaga a linha de <code>users</code> — essas vendas ficariam sem comissionado e o
                relatório de comissão passaria a mentir.
              </p>
              <p className="usr-modal-alt">Inativar tira ele das novas vendas e preserva o histórico.</p>
            </>
          ) : (
            <p>Nenhuma venda aponta para ele — a exclusão não deixa histórico órfão. A ação não tem volta.</p>
          )}
        </Confirm>
      )}
    </div>
  );
}

window.ComissionadosPage = ComissionadosPage;
})();
