// cliente-grupos.jsx — /customer-group
// Grupos de cliente: a FK `customer_group_id` que a aba Comercial do drawer e o
// campo "Grupo de cliente" do cadastro consomem. Cada grupo carrega o ajuste de
// preço aplicado na venda.
const { useState: useStateCG, useMemo: useMemoCG } = React;

function cgSpgNome(id) {
  const l = window.CLI_SPG || [];
  const s = l.find((x) => x.id === String(id));
  return s ? s.nome : "";
}

// Abre a lista de clientes já filtrada por este grupo — pelo ID, que é o vínculo.
function cgVerCadastros(id) {
  window.__CLI_GRUPO_INICIAL = id;
  window.__PESSOAS_ROLE_INITIAL = "customer";
  window.__go?.("clientes");
}

function ClienteGruposPage() {
  const I = window.I || {};
  const [grupos, setGruposState] = useStateCG(() => (window.cliGruposLer ? window.cliGruposLer() : []));
  const setGrupos = (fn) => setGruposState((gs) => { const n = typeof fn === "function" ? fn(gs) : fn; window.cliGruposGravar?.(n); return n; });
  const [edicao, setEdicao] = useStateCG(null); // {id?, nome, ajuste, desc}

  // Contagem vem do MESMO grupo que a lista de clientes usa (derived.grupo),
  // senão a tela promete um número e a lista entrega outro.
  const uso = useMemoCG(() => {
    const cls = (window.OS_DATA && window.OS_DATA.OS_CLIENTS) || [];
    const osList = (window.OS_DATA && window.OS_DATA.OS_LIST) || [];
    const m = {};
    cls.forEach((c) => {
      const stats = window.cliClientStats ? window.cliClientStats(c, osList) : {};
      const d = window.cliDeriveCli ? window.cliDeriveCli(c, stats) : {};
      if (d.grupoId) m[d.grupoId] = (m[d.grupoId] || 0) + 1;
    });
    return m;
  }, [grupos]);

  const salvar = () => {
    if (!edicao || !edicao.nome.trim()) return;
    const dados = {
      nome: edicao.nome.trim(),
      calc: edicao.calc,
      amount: edicao.calc === "percentage" ? (Number(edicao.amount) || 0) : 0,
      spg: edicao.calc === "selling_price_group" ? (edicao.spg || null) : null,
    };
    setGrupos((gs) => edicao.id
      ? gs.map((x) => x.id === edicao.id ? { ...x, ...dados } : x)
      : [...gs, { id: "g" + Date.now(), ...dados }]);
    setEdicao(null);
  };

  return (
    <div className="os-page cg-page">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <button className="ci-voltar" onClick={() => window.__go?.("clientes")}>← Clientes</button>
          <h1>Grupos de cliente</h1>
          <p><strong>{grupos.length}</strong> grupos · definem a tabela de preço aplicada na venda</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn primary" onClick={() => setEdicao({ nome: "", calc: "percentage", amount: "0", spg: null })}>
            <I.plus size={13}/> Novo grupo
          </button>
        </div>
      </header>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <th>Grupo</th><th>Cálculo do preço</th>
            <th className="num">Desconto</th><th>Tabela de preço</th>
            <th className="num">Cadastros</th><th></th>
          </tr></thead>
          <tbody>
            {grupos.map((g) => (
              <tr key={g.id} className="cli-row">
                <td><b>{g.nome}</b>{g.id === "1" && <span className="cg-padrao">padrão</span>}</td>
                <td className="cli-cell-muted">{g.calc === "percentage" ? "Desconto percentual" : "Tabela de preço própria"}</td>
                <td className="num">
                  {g.calc !== "percentage" ? <span className="cli-cell-muted">—</span>
                    : g.amount > 0 ? <span className="cg-desc">−{g.amount}%</span>
                    : <span className="cli-cell-muted">sem desconto</span>}
                </td>
                <td>{g.calc === "selling_price_group" ? (cgSpgNome(g.spg) || <span className="cli-cell-muted">—</span>) : <span className="cli-cell-muted">—</span>}</td>
                <td className="num tabular">{uso[g.id] || 0}</td>
                <td className="cli-td-kebab">
                  <window.CliRowKebab items={[
                    { label: "Editar grupo", icon: I.pencil, action: () => setEdicao({ id: g.id, nome: g.nome, calc: g.calc, amount: String(g.amount || 0), spg: g.spg }) },
                    { label: "Ver cadastros do grupo", icon: I.users,
                      disabled: !(uso[g.id] > 0), motivo: "Nenhum cadastro neste grupo ainda.",
                      action: () => cgVerCadastros(g.id) },
                    ...(g.id === "1" ? [] : [{ sep: true }, { label: "Excluir grupo", icon: I.close, danger: true,
                      action: () => setGrupos((gs) => gs.filter((x) => x.id !== g.id)) }]),
                  ]}/>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {edicao && (
        <div className="cli-modal-scrim" onClick={() => setEdicao(null)}>
          <div className="cli-modal" role="dialog" aria-label={edicao.id ? "Editar grupo de cliente" : "Novo grupo de cliente"} onClick={(e) => e.stopPropagation()}>
            <header>
              <b>{edicao.id ? "Editar grupo" : "Novo grupo"}</b>
              <span>O ajuste vale pra toda venda de quem estiver no grupo.</span>
            </header>
            <div className="cli-modal-body">
              <label className="cli-modal-f">
                <span>Nome</span>
                <input className="cli-modal-in" autoFocus value={edicao.nome} onChange={(e) => setEdicao({ ...edicao, nome: e.target.value })} placeholder="Ex.: Corporativo"/>
              </label>
              <label className="cli-modal-f">
                <span>Cálculo do preço</span>
                <select className="cli-modal-in" value={edicao.calc} onChange={(e) => setEdicao({ ...edicao, calc: e.target.value })}>
                  <option value="percentage">Desconto percentual</option>
                  <option value="selling_price_group">Tabela de preço própria</option>
                </select>
              </label>
              {edicao.calc === "percentage" ? (
                <label className="cli-modal-f cli-modal-f-full">
                  <span>Desconto (%)</span>
                  <input className="cli-modal-in" inputMode="numeric" value={edicao.amount}
                    onChange={(e) => setEdicao({ ...edicao, amount: e.target.value.replace(/\D/g, "") })} placeholder="10"/>
                </label>
              ) : (
                <label className="cli-modal-f cli-modal-f-full">
                  <span>Tabela de preço</span>
                  <select className="cli-modal-in" value={edicao.spg || ""} onChange={(e) => setEdicao({ ...edicao, spg: e.target.value || null })}>
                    <option value="">— Selecione —</option>
                    {(window.CLI_SPG || []).map((s2) => <option key={s2.id} value={s2.id}>{s2.nome}</option>)}
                  </select>
                </label>
              )}
              <p className="cli-modal-nota">
                Ou o grupo desconta um percentual do preço de tabela, ou usa uma tabela de preço própria — nunca os dois.
                Quem não tem grupo paga o preço de tabela.
              </p>
            </div>
            <footer>
              <button className="os-btn ghost" onClick={() => setEdicao(null)}>Cancelar</button>
              <button className="os-btn primary"
                disabled={!edicao.nome.trim() || (edicao.calc === "selling_price_group" && !edicao.spg)}
                onClick={salvar}>{edicao.id ? "Salvar grupo" : "Criar grupo"}</button>
            </footer>
          </div>
        </div>
      )}
    </div>
  );
}

window.ClienteGruposPage = ClienteGruposPage;
