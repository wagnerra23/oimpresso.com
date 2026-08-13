// cliente-import.jsx — /contacts/import (paridade Pages/Cliente/Import.tsx + Import.charter.md).
// Wizard 2 passos: baixar o modelo → enviar o arquivo. Sem preview e sem mapeamento
// de colunas: o UPOS fixa as 27 colunas oficiais (Non-Goals do charter).
const { useState: useStateCI, useRef: useRefCI } = React;


function ClienteImportPage() {
  const I = window.I || {};
  const [arquivo, setArquivo] = useStateCI(null);
  const [enviando, setEnviando] = useStateCI(false);
  const [progresso, setProgresso] = useStateCI(0);
  const [resultado, setResultado] = useStateCI(null);
  const inputRef = useRefCI(null);

  const aceita = (f) => {
    if (!f) return;
    const ok = /\.(xlsx|xls|csv)$/i.test(f.name);
    if (!ok) { setResultado({ tom: "erro", titulo: "Formato não aceito", txt: `"${f.name}" não é XLSX, XLS nem CSV. Baixe o modelo e salve nele.` }); return; }
    setResultado(null);
    setArquivo(f);
  };

  const enviar = () => {
    if (!arquivo || enviando) return;
    setEnviando(true); setProgresso(0); setResultado(null);
    let p = 0;
    const t = setInterval(() => {
      p = Math.min(100, p + 9 + Math.random() * 11);
      setProgresso(Math.round(p));
      if (p >= 100) {
        clearInterval(t);
        setEnviando(false);
        // O servidor é quem valida linha a linha e devolve a contagem (charter).
        const total = 128, erros = 3;
        setResultado({
          tom: erros ? "parcial" : "ok",
          titulo: erros ? `${total - erros} cadastros importados · ${erros} linhas com erro` : `${total} cadastros importados`,
          txt: erros
            ? "As linhas com erro não entraram e continuam no arquivo de retorno, com o motivo em cada uma: CPF/CNPJ inválido (2) e grupo de cliente inexistente (1)."
            : "Todos os cadastros do arquivo entraram. Confira na lista de clientes.",
          erros,
        });
      }
    }, 260);
  };

  return (
    <div className="os-page ci-page">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <button className="ci-voltar" onClick={() => window.__go?.("clientes")}>← Clientes</button>
          <h1>Importar clientes</h1>
          <p>Traga a base de uma planilha. O arquivo manda: o que estiver nele vira cadastro.</p>
        </div>
      </header>

      <div className="ci-body">
        <section className="ci-step">
          <div className="ci-step-n">1</div>
          <div className="ci-step-c">
            <h2>Baixe o modelo</h2>
            <p>São 27 colunas fixas, na ordem que o sistema espera. Não dá pra renomear nem reordenar — preencha por cima do modelo, que já vem com os cabeçalhos certos.</p>
            <div className="ci-acoes">
              <button className="os-btn primary" onClick={() => setResultado({ tom: "ok", titulo: "Modelo baixado", txt: "modelo-clientes.xlsx — preencha e volte pro passo 2." })}>
                Baixar modelo XLSX
              </button>
            </div>
          </div>
        </section>

        <section className="ci-step">
          <div className="ci-step-n">2</div>
          <div className="ci-step-c">
            <h2>Envie o arquivo preenchido</h2>
            <div className={"ci-drop" + (arquivo ? " tem" : "")}
              onDragOver={(e) => e.preventDefault()}
              onDrop={(e) => { e.preventDefault(); aceita(e.dataTransfer.files[0]); }}
              onClick={() => inputRef.current?.click()}
              role="button" tabIndex={0}
              onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); inputRef.current?.click(); } }}>
              <input ref={inputRef} type="file" accept=".xlsx,.xls,.csv" hidden
                onChange={(e) => aceita(e.target.files[0])}/>
              {arquivo
                ? <><b>{arquivo.name}</b><span>{(arquivo.size / 1024).toFixed(0)} KB · clique pra trocar</span></>
                : <><b>Arraste a planilha aqui</b><span>ou clique pra escolher · XLSX, XLS ou CSV</span></>}
            </div>

            {enviando && (
              <div className="ci-prog" role="progressbar" aria-valuenow={progresso} aria-valuemin={0} aria-valuemax={100}>
                <i style={{ width: progresso + "%" }}/>
                <span>Importando… {progresso}%</span>
              </div>
            )}

            <div className="ci-acoes">
              <button className="os-btn primary" disabled={!arquivo || enviando} onClick={enviar}>
                {enviando ? "Importando…" : "Importar"}
              </button>
              {arquivo && !enviando && <button className="os-btn ghost" onClick={() => setArquivo(null)}>Tirar o arquivo</button>}
            </div>

            <p className="ci-nota">
              A conferência linha a linha é feita no servidor. Nada entra pela metade: linha com erro fica de fora e volta pra você com o motivo.
            </p>
          </div>
        </section>

        {resultado && (
          <div className={"ci-banner ci-banner--" + resultado.tom} role="status">
            <b>{resultado.titulo}</b>
            <span>{resultado.txt}</span>
            {resultado.erros > 0 && <button className="os-btn ghost">Baixar as linhas com erro</button>}
          </div>
        )}
      </div>
    </div>
  );
}

window.ClienteImportPage = ClienteImportPage;
