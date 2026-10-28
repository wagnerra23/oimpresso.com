// financeiro-contabil-page.jsx — /financeiro/contabilidade (US-FIN-077 exportar + US-FIN-034 importar).
// Proposta [CL] 2026-10-27 para o [W] avaliar (ADR 0438: protótipo no git, revisão por Artefato).
// Duas abas na mesma tela:
//  - Exportar: arquivo de lançamentos da competência no leiaute do contador. O arquivo só sai
//    com o de-para completo (backend: ExportacaoContabilService, 422 com as pendências).
//  - Importar: planilha de títulos a receber/pagar → prévia linha a linha → gravar só depois
//    de confirmar (regra mestre de valor). Linha que já existe não entra de novo.
// Valores e nomes são de exemplo.
const { useState: useStateFC, useMemo: useMemoFC } = React;

const FC_SISTEMAS = [
  { id: "dominio", label: "Domínio", nota: "Leiaute com separador · registros 0000/6000/6100" },
  { id: "alterdata", label: "Alterdata", nota: "Importação personalizada em texto · colunas A a J" },
  { id: "questor", label: "Questor", nota: "Leiaute definido pela instalação do contador", indisponivel: true },
];

const FC_DEPARA = [
  { id: "b1", grupo: "Conta bancária", nome: "Itaú PJ 1234-5", codigo: "5" },
  { id: "b2", grupo: "Conta bancária", nome: "Caixa da loja", codigo: "" },
  { id: "p1", grupo: "Plano de contas", nome: "3.1.01 Receita de vendas", codigo: "55" },
  { id: "p2", grupo: "Plano de contas", nome: "4.1.03 Fornecedores de papel", codigo: "120" },
  { id: "p3", grupo: "Plano de contas", nome: "4.2.01 Energia elétrica", codigo: "" },
  { id: "j", grupo: "Acréscimos", nome: "Juros e multa", codigo: "70" },
  { id: "d", grupo: "Acréscimos", nome: "Descontos", codigo: "80" },
];

const FC_LINHAS = [
  { n: 2, tipo: "Receber", contraparte: "Gráfica Alfa", doc: "NF 1021", venc: "10/11/2026", valor: 1840.0, status: "ok" },
  { n: 3, tipo: "Receber", contraparte: "Papelaria Beta", doc: "NF 1022", venc: "15/11/2026", valor: 612.5, status: "ok" },
  { n: 4, tipo: "Pagar", contraparte: "Distribuidora de Papel", doc: "Boleto 88", venc: "31/02/2026", valor: 2300.0, status: "erro", motivo: "Vencimento inválido (31/02/2026)" },
  { n: 5, tipo: "Pagar", contraparte: "Energia Sul", doc: "Fatura 10/26", venc: "20/11/2026", valor: 418.9, status: "ok" },
  { n: 6, tipo: "Receber", contraparte: "Gráfica Alfa", doc: "NF 1004", venc: "05/10/2026", valor: 990.0, status: "existe", motivo: "Já existe: título 2026-0412, mesmo documento, valor e vencimento" },
  { n: 7, tipo: "Receber", contraparte: "", doc: "NF 1023", venc: "18/11/2026", valor: 75.0, status: "erro", motivo: "Contraparte em branco" },
  { n: 8, tipo: "Pagar", contraparte: "Tintas Gama", doc: "NF 553", venc: "25/11/2026", valor: -120.0, status: "erro", motivo: "Valor negativo; use Tipo = Receber ou Pagar" },
  { n: 9, tipo: "Pagar", contraparte: "Tintas Gama", doc: "NF 554", venc: "25/11/2026", valor: 1265.4, status: "ok" },
];

const fcBRL = (v) => v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

function FinContabilPage({ abaInicial = "exportar" }) {
  const [aba, setAba] = useStateFC(abaInicial);
  return (
    <div className="os-page fc-page" data-page="Financeiro/Contabilidade">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Contabilidade</h1>
          <p>Leve os lançamentos do mês para o sistema do seu contador, ou traga títulos de uma planilha.</p>
        </div>
      </header>
      <div className="fc-tabs" role="tablist" aria-label="Contabilidade">
        {[["exportar", "Exportar para o contador"], ["importar", "Importar títulos"]].map(([id, label]) => (
          <button key={id} role="tab" id={"fc-tab-" + id} aria-selected={aba === id} aria-controls={"fc-painel-" + id}
            className={"fc-tab" + (aba === id ? " is-on" : "")} onClick={() => setAba(id)}>{label}</button>
        ))}
      </div>
      <div role="tabpanel" id={"fc-painel-" + aba} aria-labelledby={"fc-tab-" + aba}>
        {aba === "exportar" ? <FcExportar /> : <FcImportar />}
      </div>
    </div>
  );
}

function FcExportar() {
  const [competencia, setCompetencia] = useStateFC("2026-09");
  const [sistema, setSistema] = useStateFC("dominio");
  const [depara, setDepara] = useStateFC(FC_DEPARA);
  const [gerado, setGerado] = useStateFC(false);
  const faltam = depara.filter((d) => !d.codigo.trim());
  const sis = FC_SISTEMAS.find((s) => s.id === sistema);
  const nomeArquivo = "lancamentos_" + sistema + "_" + competencia.replace("-", "") + ".txt";
  const amostra = sistema === "dominio"
    ? ["|0000|12345678000190|", "|6000|X||||", "|6100|03/09/2026|5|55|1840,00||Recebimento 2026-0398 Gráfica Alfa||||", "|6000|X||||", "|6100|08/09/2026|120|5|2300,00||Pagamento 2026-0401 Distribuidora de Papel||||"]
    : ['"","5","55","03/09/2026","1840,00","","Recebimento 2026-0398 Gráfica Alfa","","","2026-0398"', '"","120","5","08/09/2026","2300,00","","Pagamento 2026-0401 Distribuidora de Papel","","","2026-0401"'];

  const setCodigo = (id, v) => { setGerado(false); setDepara((xs) => xs.map((d) => (d.id === id ? { ...d, codigo: v.replace(/\D/g, "") } : d))); };

  return (
    <div className="fc-body">
      <section className="fc-card fc-filtros" aria-label="O que exportar">
        <label className="fc-campo" htmlFor="fc-competencia">
          <span>Competência</span>
          <select id="fc-competencia" value={competencia} onChange={(e) => { setCompetencia(e.target.value); setGerado(false); }}>
            <option value="2026-10">Outubro de 2026</option>
            <option value="2026-09">Setembro de 2026</option>
            <option value="2026-08">Agosto de 2026</option>
          </select>
        </label>
        <fieldset className="fc-campo">
          <legend>Sistema do contador</legend>
          <div className="fc-seg">
            {FC_SISTEMAS.map((s) => (
              <button key={s.id} type="button" aria-pressed={sistema === s.id} disabled={s.indisponivel}
                title={s.indisponivel ? "Em definição: o Questor importa pelo leiaute que o contador configura" : s.nota}
                className={"fc-seg-b" + (sistema === s.id ? " is-on" : "")} onClick={() => { setSistema(s.id); setGerado(false); }}>
                {s.label}{s.indisponivel && <small> em breve</small>}
              </button>
            ))}
          </div>
          <span className="fc-hint">{sis.nota}</span>
        </fieldset>
        <dl className="fc-resumo">
          <div><dt>Baixas no mês</dt><dd>31</dd></div>
          <div><dt>Lançamentos</dt><dd>42</dd></div>
          <div><dt>Movimento no banco</dt><dd>{fcBRL(18432.75)}</dd></div>
        </dl>
      </section>

      <section className="fc-card" aria-labelledby="fc-depara-t">
        <div className="fc-card-h">
          <div>
            <h2 id="fc-depara-t">Códigos no sistema do contador</h2>
            <p>O código reduzido de cada conta, como aparece no plano de contas do contador. Vale para os três sistemas.</p>
          </div>
          {faltam.length > 0
            ? <span className="fc-chip fc-chip--warn">{faltam.length} sem código</span>
            : <span className="fc-chip fc-chip--ok">Completo</span>}
        </div>
        <div className="fc-tabela-wrap">
          <table className="fc-tabela">
            <thead><tr><th>Origem</th><th>Conta no oimpresso</th><th className="fc-col-cod">Código no contador</th></tr></thead>
            <tbody>
              {depara.map((d) => (
                <tr key={d.id} className={d.codigo ? "" : "is-falta"}>
                  <td className="fc-mute">{d.grupo}</td>
                  <td>{d.nome}</td>
                  <td className="fc-col-cod">
                    <input id={"fc-cod-" + d.id} aria-label={"Código contábil de " + d.nome} inputMode="numeric"
                      value={d.codigo} placeholder="Falta código" onChange={(e) => setCodigo(d.id, e.target.value)} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="fc-card fc-gerar" aria-label="Gerar arquivo">
        {faltam.length > 0 ? (
          <p className="fc-aviso" role="status">O arquivo só sai completo. Falta código para: {faltam.map((f) => f.nome).join(", ")}.</p>
        ) : (
          <p className="fc-mute">42 lançamentos de {competencia.split("-").reverse().join("/")} · débitos e créditos somam {fcBRL(19210.15)} cada.</p>
        )}
        <div className="fc-acoes">
          <button className="os-btn primary" disabled={faltam.length > 0} onClick={() => setGerado(true)}>Gerar arquivo do {sis.label}</button>
        </div>
        {gerado && (
          <div className="fc-saida">
            <div className="fc-saida-h"><b>{nomeArquivo}</b><span>{sistema === "dominio" ? "ISO-8859-1 · CRLF" : "UTF-8 · CRLF"} · baixado</span></div>
            <pre aria-label="Primeiras linhas do arquivo">{amostra.join("\n")}</pre>
            <p className="fc-mute">No {sis.label}: {sistema === "dominio" ? "Utilitários › Importação › Lançamentos Contábeis em Lote." : "Lançamentos › Importar Lançamentos › Importação Personalizada – Texto."}</p>
          </div>
        )}
      </section>
    </div>
  );
}

function FcImportar() {
  const [etapa, setEtapa] = useStateFC("previa"); // modelo | previa | confirmar | gravado
  const [filtro, setFiltro] = useStateFC("todas");
  const validas = FC_LINHAS.filter((l) => l.status === "ok");
  const conta = (s) => FC_LINHAS.filter((l) => l.status === s).length;
  const soma = (tipo) => validas.filter((l) => l.tipo === tipo).reduce((t, l) => t + l.valor, 0);
  const visiveis = useMemoFC(() => FC_LINHAS.filter((l) => filtro === "todas" || l.status === filtro), [filtro]);
  const rotulo = { ok: "Válida", erro: "Com erro", existe: "Já existe" };

  return (
    <div className="fc-body">
      <section className="fc-card fc-passos" aria-label="Arquivo">
        <div className="fc-passo">
          <h2>1. Baixe o modelo</h2>
          <p>Colunas: Tipo (Receber ou Pagar), Contraparte, Documento, Emissão, Vencimento, Valor, Plano de contas, Observação.</p>
          <button className="os-btn ghost">Baixar modelo .xlsx</button>
        </div>
        <div className="fc-passo">
          <h2>2. Envie a planilha</h2>
          <div className="fc-arquivo"><b>titulos-novembro.xlsx</b><span>8 linhas · 24 KB</span></div>
          <button className="os-btn ghost">Trocar arquivo</button>
        </div>
      </section>

      <section className="fc-card" aria-labelledby="fc-previa-t">
        <div className="fc-card-h">
          <div>
            <h2 id="fc-previa-t">3. Confira antes de gravar</h2>
            <p>Nada foi gravado ainda. Linhas com erro e linhas que já existem ficam de fora.</p>
          </div>
        </div>
        <div className="fc-filtro" role="group" aria-label="Filtrar linhas">
          {[["todas", "Todas", FC_LINHAS.length], ["ok", "Válidas", conta("ok")], ["erro", "Com erro", conta("erro")], ["existe", "Já existem", conta("existe")]].map(([id, label, n]) => (
            <button key={id} aria-pressed={filtro === id} className={"fc-chip fc-chip--btn" + (filtro === id ? " is-on" : "")} onClick={() => setFiltro(id)}>{label} <b>{n}</b></button>
          ))}
        </div>
        <div className="fc-tabela-wrap">
          <table className="fc-tabela">
            <thead><tr><th>Linha</th><th>Tipo</th><th>Contraparte</th><th>Documento</th><th>Vencimento</th><th className="fc-num">Valor</th><th>Situação</th></tr></thead>
            <tbody>
              {visiveis.map((l) => (
                <tr key={l.n} className={"is-" + l.status}>
                  <td className="fc-mute fc-num">{l.n}</td>
                  <td>{l.tipo}</td>
                  <td>{l.contraparte || <span className="fc-mute">em branco</span>}</td>
                  <td>{l.doc}</td>
                  <td className="fc-num">{l.venc}</td>
                  <td className="fc-num">{fcBRL(l.valor)}</td>
                  <td><span className={"fc-chip fc-chip--" + (l.status === "ok" ? "ok" : l.status === "erro" ? "neg" : "warn")}>{rotulo[l.status]}</span>
                    {l.motivo && <div className="fc-motivo">{l.motivo}</div>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="fc-card fc-gerar" aria-label="Gravar">
        {etapa === "gravado" ? (
          <p className="fc-ok" role="status">{validas.length} títulos gravados. Eles já aparecem na Visão Unificada.</p>
        ) : etapa === "confirmar" ? (
          <div className="fc-confirma" role="alertdialog" aria-labelledby="fc-conf-t">
            <b id="fc-conf-t">Gravar {validas.length} títulos?</b>
            <span>A receber {fcBRL(soma("Receber"))} · A pagar {fcBRL(soma("Pagar"))}. As {conta("erro")} linhas com erro e a que já existe não entram.</span>
            <div className="fc-acoes">
              <button className="os-btn primary" onClick={() => setEtapa("gravado")}>Gravar {validas.length} títulos</button>
              <button className="os-btn ghost" onClick={() => setEtapa("previa")}>Voltar</button>
            </div>
          </div>
        ) : (
          <>
            <p className="fc-mute">{validas.length} válidas: a receber {fcBRL(soma("Receber"))}, a pagar {fcBRL(soma("Pagar"))}.</p>
            <div className="fc-acoes">
              <button className="os-btn primary" onClick={() => setEtapa("confirmar")}>Gravar {validas.length} títulos</button>
              <button className="os-btn ghost">Baixar as linhas com erro</button>
            </div>
          </>
        )}
      </section>
    </div>
  );
}

window.FinContabilPage = FinContabilPage;
