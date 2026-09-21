// cliente-extrato.jsx — /contacts/ledger (paridade Pages/Cliente/Ledger.tsx + Ledger.charter.md).
// Extrato financeiro do cliente: 3 KPIs · filtros data/formato/local · tabela
// débito/crédito/saldo acumulado · PDF, Excel e envio por e-mail.
const { useState: useStateCE, useMemo: useMemoCE } = React;

const CE_FORMATOS = [
  { value: "padrao", label: "Padrão — uma linha por lançamento" },
  { value: "resumido", label: "Resumido — só os totais por mês" },
  { value: "detalhado", label: "Detalhado — com itens de cada documento" }];

function ceBRL(v) { return (v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" }); }
function ceData(d) { return d.toLocaleDateString("pt-BR"); }

function ClienteExtratoPage({ clientId }) {
  const OS_DATA = window.OS_DATA || {};
  const clientes = OS_DATA.OS_CLIENTS || [];
  const cliente = clientes.find((c) => String(c.id) === String(clientId)) || clientes[0] || { name: "—", doc: "—", id: "0" };
  const hoje = window.FIN_TODAY instanceof Date ? new Date(window.FIN_TODAY) : new Date();

  const [formato, setFormato] = useStateCE("padrao");
  const [local, setLocal] = useStateCE("all");
  const [de, setDe] = useStateCE(() => { const d = new Date(hoje); d.setMonth(d.getMonth() - 3); return d.toISOString().slice(0, 10); });
  const [ate, setAte] = useStateCE(() => hoje.toISOString().slice(0, 10));

  // Lançamentos derivados das OS do cliente + os pagamentos correspondentes.
  const lancamentos = useMemoCE(() => {
    const osList = (OS_DATA.OS_LIST || []).filter((o) => o.client === cliente.name);
    const linhas = [];
    osList.forEach((o, i) => {
      const valor = parseFloat(String(o.value || "0").replace(/[^\d,]/g, "").replace(",", ".")) || 0;
      const base = new Date(hoje); base.setDate(base.getDate() - (osList.length - i) * 11);
      linhas.push({ data: new Date(base), doc: "OS #" + o.id, desc: o.product, local: i % 3 === 0 ? "Matriz" : "Filial centro", debito: valor, credito: 0 });
      if (i % 2 === 0) {
        const pag = new Date(base); pag.setDate(pag.getDate() + 6);
        linhas.push({ data: pag, doc: "REC-" + o.id, desc: "Pagamento recebido · " + (i % 4 === 0 ? "PIX" : "boleto"), local: "Matriz", debito: 0, credito: valor });
      }
    });
    return linhas.sort((a, b) => a.data - b.data);
  }, [cliente.name]);

  const dentro = lancamentos.filter((l) => {
    const iso = l.data.toISOString().slice(0, 10);
    if (de && iso < de) return false;
    if (ate && iso > ate) return false;
    if (local !== "all" && l.local !== local) return false;
    return true;
  });

  // Saldo acumulado corre na ordem da tabela — é o que faz um extrato ser extrato.
  let acumulado = 0;
  const linhas = dentro.map((l) => { acumulado += l.debito - l.credito; return { ...l, saldo: acumulado }; });
  const totalDeb = dentro.reduce((s, l) => s + l.debito, 0);
  const totalCred = dentro.reduce((s, l) => s + l.credito, 0);
  const saldo = totalDeb - totalCred;

  // Resumido agrupa por mês; detalhado abre os itens do documento.
  const porMes = useMemoCE(() => {
    const m = {};
    dentro.forEach((l) => {
      const k = l.data.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
      m[k] = m[k] || { mes: k, debito: 0, credito: 0 };
      m[k].debito += l.debito; m[k].credito += l.credito;
    });
    return Object.values(m);
  }, [dentro]);

  const locais = ["Matriz", "Filial centro"];

  return (
    <div className="os-page ce-page">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <button className="ci-voltar" onClick={() => window.__go?.("clientes")}>← Clientes</button>
          <h1>Extrato do cliente</h1>
          <p><strong>{cliente.name}</strong> · <span className="ce-doc">{cliente.doc}</span></p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => window.print()}>PDF</button>
          <button className="os-btn ghost">Excel</button>
          <button className="os-btn primary">Enviar por e-mail</button>
        </div>
      </header>

      <div className="ce-kpis">
        <div className="ce-kpi"><small>Débitos no período</small><b className="ce-deb">{ceBRL(totalDeb)}</b><span>o que foi faturado</span></div>
        <div className="ce-kpi"><small>Créditos no período</small><b className="ce-cred">{ceBRL(totalCred)}</b><span>o que o cliente pagou</span></div>
        <div className="ce-kpi"><small>Saldo</small><b className={saldo > 0 ? "ce-deb" : "ce-cred"}>{ceBRL(Math.abs(saldo))}</b>
          <span>{saldo > 0 ? "o cliente deve" : saldo < 0 ? "crédito a favor do cliente" : "conta zerada"}</span></div>
      </div>

      <div className="ce-filtros">
        <label>De<input type="date" value={de} onChange={(e) => setDe(e.target.value)}/></label>
        <label>Até<input type="date" value={ate} onChange={(e) => setAte(e.target.value)}/></label>
        <label>Formato
          <select value={formato} onChange={(e) => setFormato(e.target.value)}>
            {CE_FORMATOS.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
          </select>
        </label>
        <label>Local
          <select value={local} onChange={(e) => setLocal(e.target.value)}>
            <option value="all">Todos</option>
            {locais.map((l) => <option key={l} value={l}>{l}</option>)}
          </select>
        </label>
        <span className="ce-count">{linhas.length} {linhas.length === 1 ? "lançamento" : "lançamentos"}</span>
      </div>

      <div className="os-table-wrap">
        {formato === "resumido" ? (
          <table className="os-table ce-table">
            <thead><tr><th>Mês</th><th className="num">Débitos</th><th className="num">Créditos</th><th className="num">Resultado</th></tr></thead>
            <tbody>
              {porMes.map((m) => (
                <tr key={m.mes}>
                  <td>{m.mes}</td>
                  <td className="num ce-deb">{m.debito ? ceBRL(m.debito) : "—"}</td>
                  <td className="num ce-cred">{m.credito ? ceBRL(m.credito) : "—"}</td>
                  <td className="num">{ceBRL(m.debito - m.credito)}</td>
                </tr>
              ))}
              {porMes.length === 0 && <tr><td colSpan={4} className="os-empty">Nenhum lançamento no período escolhido.</td></tr>}
            </tbody>
          </table>
        ) : (
          <table className="os-table ce-table">
            <thead><tr>
              <th>Data</th><th>Documento</th><th>Descrição</th><th>Local</th>
              <th className="num">Débito</th><th className="num">Crédito</th><th className="num">Saldo</th>
            </tr></thead>
            <tbody>
              {linhas.map((l, i) => (
                <React.Fragment key={i}>
                  <tr>
                    <td className="tabular">{ceData(l.data)}</td>
                    <td className="ce-doc">{l.doc}</td>
                    <td>{l.desc}</td>
                    <td className="ce-muted">{l.local}</td>
                    <td className="num ce-deb">{l.debito ? ceBRL(l.debito) : "—"}</td>
                    <td className="num ce-cred">{l.credito ? ceBRL(l.credito) : "—"}</td>
                    <td className="num ce-saldo">{ceBRL(l.saldo)}</td>
                  </tr>
                  {formato === "detalhado" && l.debito > 0 && (
                    <tr className="ce-item">
                      <td></td>
                      <td colSpan={6}>
                        <span>Item 1 · {l.desc} · 1 un × {ceBRL(l.debito * 0.72)}</span>
                        <span>Item 2 · acabamento e instalação · {ceBRL(l.debito * 0.28)}</span>
                      </td>
                    </tr>
                  )}
                </React.Fragment>
              ))}
              {linhas.length === 0 && <tr><td colSpan={7} className="os-empty">Nenhum lançamento no período escolhido.</td></tr>}
            </tbody>
            {linhas.length > 0 && (
              <tfoot><tr>
                <td colSpan={4}>Total do período</td>
                <td className="num ce-deb">{ceBRL(totalDeb)}</td>
                <td className="num ce-cred">{ceBRL(totalCred)}</td>
                <td className="num ce-saldo">{ceBRL(saldo)}</td>
              </tr></tfoot>
            )}
          </table>
        )}
      </div>
    </div>
  );
}

window.ClienteExtratoPage = ClienteExtratoPage;
