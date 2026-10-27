// fiscal-transporte.jsx — Fiscal · sub-página "MDF-e · CT-e" (ADR 0444).
// Não há tela viva: este é o protótipo de entrada (ADR 0438). Dados fictícios, ambiente de homologação.
// Recorte: MDF-e de carga própria montado a partir das NF-e da entrega (US-COMVIS-016) + CT-e emitido (modelo 57).
// O CT-e tomado (frete que a empresa paga) NÃO entra aqui: chega por XML na aba Manifesto DF-e.
const { useState: useStateTx } = React;
const txU = () => window.FxUI;

const TX_MDFE = [
  { id: 231, num: "000231", serie: "1", emit: "22/10", ufIni: "SC", ufFim: "PR", rota: "Gravatal → Curitiba", placa: "ABC1D23", condutor: "Condutor 1", notas: 3, peso: "412 kg", st: "aberto", stLabel: "Autorizado", dias: 5 },
  { id: 226, num: "000226", serie: "1", emit: "30/09", ufIni: "SC", ufFim: "RS", rota: "Gravatal → Porto Alegre", placa: "EFG4H56", condutor: "Condutor 2", notas: 1, peso: "96 kg", st: "atraso", stLabel: "Autorizado · 27 dias", dias: 27 },
  { id: 225, num: "000225", serie: "1", emit: "28/09", ufIni: "SC", ufFim: "SP", rota: "Gravatal → Campinas", placa: "ABC1D23", condutor: "Condutor 1", notas: 5, peso: "1.020 kg", st: "ok", stLabel: "Encerrado 02/10" },
  { id: 224, num: "000224", serie: "1", emit: "27/09", ufIni: "SC", ufFim: "PR", rota: "Gravatal → Curitiba", placa: "ABC1D23", condutor: "Condutor 1", notas: 2, peso: "180 kg", st: "off", stLabel: "Cancelado" },
];
const TX_CTE = [
  { id: 58, num: "000058", serie: "1", emit: "24/10", tomador: "Remetente", partes: "Cliente A → Cliente B", rota: "Tubarão → Joinville", st: "ok", stLabel: "Autorizado" },
  { id: 57, num: "000057", serie: "1", emit: "23/10", tomador: "Destinatário", partes: "Cliente C → Cliente D", rota: "Tubarão → Lages", st: "bad", stLabel: "Rejeitado · 539" },
];
// NF-e autorizadas, com entrega fora do município do emitente e sem MDF-e aberto.
const TX_SEM_MDFE = [
  { venda: 1042, nfe: "1042", cliente: "Cliente E", destino: "Curitiba/PR", peso: 140 },
  { venda: 1047, nfe: "1047", cliente: "Cliente F", destino: "Curitiba/PR", peso: 210 },
  { venda: 1051, nfe: "1051", cliente: "Cliente G", destino: "São José dos Pinhais/PR", peso: 62 },
];

const TxSt = ({ s, children }) => <span className="tx-st" data-s={s}>{children}</span>;

function TxDrawer({ tipo, onClose }) {
  const [sel, setSel] = useStateTx(() => new Set(TX_SEM_MDFE.map(n => n.nfe)));
  const marcadas = TX_SEM_MDFE.filter(n => sel.has(n.nfe));
  const peso = marcadas.reduce((s, n) => s + n.peso, 0);
  const alterna = (k) => setSel(p => { const x = new Set(p); x.has(k) ? x.delete(k) : x.add(k); return x; });
  const mdfe = tipo === "mdfe";
  return (
    <>
      <div className="fx-scrim" onClick={onClose}></div>
      <aside className="fx-drawer tx-drawer" role="dialog" aria-modal="true" aria-label={mdfe ? "Novo MDF-e" : "Emitir CT-e"}>
        <div className="fx-dr-h">
          <div><h2>{mdfe ? "Novo MDF-e" : "Emitir CT-e"}</h2><p>{mdfe ? "Manifesto da carga própria · modal rodoviário" : "Conhecimento de transporte · modelo 57 · rodoviário"}</p></div>
          <button className="fx-dr-x" onClick={onClose} aria-label="Fechar">×</button>
        </div>
        <div className="fx-dr-b">
          {mdfe ? (<>
            <section className="fx-sec"><h3>Notas da carga</h3>
              <div className="fx-list">
                {TX_SEM_MDFE.map(n => (
                  <label className="fx-list-i tx-chk" key={n.nfe}>
                    <input type="checkbox" checked={sel.has(n.nfe)} onChange={() => alterna(n.nfe)} />
                    <b>NF-e {n.nfe} · {n.cliente}</b><span>{n.destino}</span><span className="mono">{n.peso} kg</span>
                  </label>
                ))}
              </div>
              <small className="tx-nota">Só aparecem NF-e autorizadas, com entrega fora do município e sem MDF-e aberto.</small>
            </section>
            <section className="fx-sec"><h3>Percurso</h3>
              <div className="tx-grid">
                <label className="fx-field"><span>UF de carregamento</span><input defaultValue="SC" /></label>
                <label className="fx-field"><span>Município de carregamento</span><input defaultValue="Gravatal" /></label>
                <label className="fx-field"><span>UFs de passagem</span><input placeholder="nenhuma" /></label>
                <label className="fx-field"><span>UF de descarregamento</span><input defaultValue="PR" /></label>
              </div>
            </section>
            <section className="fx-sec"><h3>Veículo e condutor</h3>
              <div className="tx-grid">
                <label className="fx-field"><span>Placa</span><input defaultValue="ABC1D23" /></label>
                <label className="fx-field"><span>Tara (kg)</span><input defaultValue="3100" inputMode="numeric" /></label>
                <label className="fx-field"><span>Condutor</span><input defaultValue="Condutor 1" /></label>
                <label className="fx-field"><span>Tipo de emitente</span><input defaultValue="Carga própria" readOnly /></label>
              </div>
            </section>
            <dl className="fx-kv"><dt>Notas</dt><dd>{marcadas.length}</dd><dt>Peso bruto</dt><dd>{peso} kg</dd></dl>
          </>) : (<>
            <section className="fx-sec"><h3>Partes</h3>
              <div className="tx-grid">
                <label className="fx-field"><span>Remetente</span><input placeholder="buscar contato" /></label>
                <label className="fx-field"><span>Destinatário</span><input placeholder="buscar contato" /></label>
                <label className="fx-field"><span>Tomador do serviço</span><input defaultValue="Remetente" /></label>
                <label className="fx-field"><span>CFOP</span><input defaultValue="5353" /></label>
              </div>
            </section>
            <section className="fx-sec"><h3>Carga</h3>
              <div className="tx-grid">
                <label className="fx-field tx-full"><span>Produto predominante</span><input placeholder="Ex.: material gráfico impresso" /></label>
                <label className="fx-field"><span>Valor da carga</span><input inputMode="decimal" /></label>
                <label className="fx-field"><span>Chave da NF-e transportada</span><input placeholder="colar a chave de 44 dígitos" /></label>
              </div>
            </section>
          </>)}
        </div>
        <div className="fx-dr-f">
          <small className="tx-nota">Transmite para a SEFAZ em homologação, sem validade fiscal.</small>
          <button className="fx-btn" onClick={onClose}>Cancelar</button>
          <button className="fx-btn primary" disabled={mdfe && marcadas.length === 0}>Transmitir em homologação</button>
        </div>
      </aside>
    </>
  );
}

window.FxTransportePage = function FxTransportePage() {
  const U = txU();
  const [doc, setDoc] = useStateTx("mdfe");
  const [drawer, setDrawer] = useStateTx(null);
  const abertos = TX_MDFE.filter(m => m.st === "aberto" || m.st === "atraso");
  return (
    <div className="fx-page">
      <U.Header title="MDF-e e CT-e" crumb="Manifesto da carga que sai para entrega e conhecimento de transporte · homologação">
        <button className="fx-btn" onClick={() => setDrawer("cte")}>Emitir CT-e</button>
        <button className="fx-btn primary" onClick={() => setDrawer("mdfe")}>Novo MDF-e</button>
      </U.Header>
      <U.Subnav current="fiscal-transporte" />
      <div className="fx-alert" data-level="warn" role="status">
        <div className="fx-alert-t"><b>Ambiente de homologação</b><small>Os documentos não têm validade fiscal. A emissão em produção depende do certificado A1.</small></div>
      </div>
      <section className="fx-ribbon" aria-label="Resumo do mês">
        <div className="fx-ri"><small>MDF-e abertos</small><b>{abertos.length}</b><em>em viagem, sem encerrar</em></div>
        <div className="fx-ri"><small>Para encerrar</small><b className="bad">{abertos.filter(m => m.dias > 25).length}</b><em>abertos há mais de 25 dias</em></div>
        <div className="fx-ri"><small>MDF-e no mês</small><b>9</b><em>7 encerrados</em></div>
        <div className="fx-ri"><small>CT-e no mês</small><b>4</b><em>1 rejeitado</em></div>
      </section>
      <div className="fx-alert" data-level="warn">
        <div className="fx-alert-t"><b>{TX_SEM_MDFE.length} notas com entrega fora do município sem MDF-e</b><small>Vendas {TX_SEM_MDFE.map(n => n.venda).join(", ")} · todas para PR</small></div>
        <button className="fx-btn" onClick={() => setDrawer("mdfe")}>Montar MDF-e com elas</button>
      </div>
      <div className="fx-chips" role="group" aria-label="Documento">
        <button className="fx-chip" aria-pressed={doc === "mdfe"} onClick={() => setDoc("mdfe")}>MDF-e <b>{TX_MDFE.length}</b></button>
        <button className="fx-chip" aria-pressed={doc === "cte"} onClick={() => setDoc("cte")}>CT-e <b>{TX_CTE.length}</b></button>
      </div>
      {doc === "mdfe" ? (
        <div className="fx-table tx-tbl"><table>
          <thead><tr><th>Nº · série</th><th>Percurso</th><th>Veículo · condutor</th><th>Notas</th><th>Peso bruto</th><th>Situação</th><th aria-label="Ações"></th></tr></thead>
          <tbody>{TX_MDFE.map(m => (
            <tr key={m.id}>
              <td className="num"><b>{m.num} · {m.serie}</b><small>emitido {m.emit}</small></td>
              <td className="cli"><b>{m.ufIni} → {m.ufFim}</b><div>{m.rota}</div></td>
              <td className="cli"><b>{m.placa}</b><div>{m.condutor}</div></td>
              <td className="val">{m.notas}</td><td className="val">{m.peso}</td>
              <td><TxSt s={m.st}>{m.stLabel}</TxSt></td>
              <td><div className="tx-acts">
                {(m.st === "aberto" || m.st === "atraso") && <><button className="fx-btn">Encerrar</button><button className="fx-btn">Incluir condutor</button></>}
                {m.st !== "off" && <button className="fx-btn">DAMDFE</button>}
                {m.st === "aberto" && <button className="fx-btn danger">Cancelar</button>}
                {m.st === "off" && <button className="fx-btn">XML</button>}
              </div></td>
            </tr>))}</tbody>
        </table></div>
      ) : (<>
        <div className="fx-table tx-tbl"><table>
          <thead><tr><th>Nº · série</th><th>Tomador</th><th>Remetente → destinatário</th><th>Situação</th><th aria-label="Ações"></th></tr></thead>
          <tbody>{TX_CTE.map(c => (
            <tr key={c.id}>
              <td className="num"><b>{c.num} · {c.serie}</b><small>emitido {c.emit}</small></td>
              <td>{c.tomador}</td>
              <td className="cli"><b>{c.partes}</b><div>{c.rota}</div></td>
              <td><TxSt s={c.st}>{c.stLabel}</TxSt></td>
              <td><div className="tx-acts">
                {c.st === "ok" ? <><button className="fx-btn">DACTE</button><button className="fx-btn">Carta de correção</button><button className="fx-btn danger">Cancelar</button></>
                  : <><button className="fx-btn">Ver motivo</button><button className="fx-btn">Corrigir e reenviar</button></>}
              </div></td>
            </tr>))}</tbody>
        </table></div>
        <small className="tx-nota">CT-e é para quem presta o transporte. O frete que a empresa paga chega como XML na aba Manifesto DF-e.</small>
      </>)}
      {drawer && <TxDrawer tipo={drawer} onClose={() => setDrawer(null)} />}
    </div>
  );
};
