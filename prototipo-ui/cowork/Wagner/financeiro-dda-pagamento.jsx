// Financeiro — DDA e pagamento de contas (US-FIN-078 / US-FIN-079). Protótipo para OK do [W].
// Duas peças, expostas para o shell integrar:
//   window.FinDdaSheet({ open, onClose })   — item "Boletos DDA" do menu ··· da Visão Unificada.
//   window.FinPagarBanco({ row })            — bloco na gaveta do título A PAGAR em aberto.
//   window.FinDdaPagamentoDemo()             — as duas lado a lado, para revisar sem o shell.
// Regras que a tela mostra (vêm do backend, não são enfeite):
//   · DDA: nada vira conta a pagar sem o usuário aceitar.
//   · Pagar: o botão só libera quando o usuário digita o MESMO valor do aberto; a baixa só
//     acontece quando o banco confirma — até lá o título segue "a pagar".
(() => {
  const { useState } = React;
  const fmtBRL = (n) => n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const fmtData = (iso) => iso.split("-").reverse().join("/");

  const DDA_MOCK = [
  { id: 1, fornecedor: "Papelaria Central Ltda", cadastrado: true, valor: 1234.56, venc: "2026-11-05" },
  { id: 2, fornecedor: "Gráfica Ponto Azul", cadastrado: true, valor: 389.9, venc: "2026-11-08" },
  { id: 3, fornecedor: "TINTAS SUL COMERCIO", cadastrado: false, valor: 2150, venc: "2026-11-12" }];

  const box = { border: "1px solid var(--border)", borderRadius: 8, background: "var(--surface)" };
  const sub = { fontSize: "var(--fs-1)", color: "var(--text-mute)" };

  const FinDdaSheet = ({ open, onClose }) => {
    const [itens, setItens] = useState(DDA_MOCK);
    const [aviso, setAviso] = useState(null);
    if (!open) return null;
    const decidir = (id, acao) => {
      const b = itens.find((x) => x.id === id);
      setItens(itens.filter((x) => x.id !== id));
      setAviso(acao === "aceitar" ? `Conta a pagar P-00042 criada — ${b.fornecedor}, ${fmtBRL(b.valor)}.` : "Boleto DDA descartado.");
    };
    return (
      <div role="dialog" aria-label="Boletos DDA" style={{ ...box, width: 460, padding: 20, display: "flex", flexDirection: "column", gap: 14 }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline" }}>
          <div style={{ fontSize: "var(--fs-4)", fontWeight: 600, color: "var(--text)" }}>Boletos DDA</div>
          {onClose && <button className="os-btn ghost" onClick={onClose}>Fechar</button>}
        </div>
        <div style={sub}>Boletos emitidos contra o CNPJ da empresa. Aceite para virar conta a pagar ou descarte se não for seu.</div>
        {aviso && <div style={{ fontSize: "var(--fs-2)", color: "var(--pos)" }}>{aviso}</div>}
        {itens.length === 0 ?
        <div style={sub}>Nenhum boleto DDA aguardando decisão.</div> :
        <div>
            {itens.map((b) =>
          <div key={b.id} style={{ display: "flex", justifyContent: "space-between", gap: 12, padding: "12px 0", borderTop: "1px solid var(--border)" }}>
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontSize: "var(--fs-2)", fontWeight: 500, color: "var(--text)" }}>{b.fornecedor}</div>
                  <div style={sub}>{b.cadastrado ? "Fornecedor cadastrado" : "Fornecedor não cadastrado"} · vence {fmtData(b.venc)}</div>
                </div>
                <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 6 }}>
                  <span style={{ fontSize: "var(--fs-2)", fontWeight: 600, fontVariantNumeric: "tabular-nums" }}>{fmtBRL(b.valor)}</span>
                  <div style={{ display: "flex", gap: 6 }}>
                    <button className="os-btn ghost" onClick={() => decidir(b.id, "descartar")}>Descartar</button>
                    <button className="os-btn" onClick={() => decidir(b.id, "aceitar")}>Aceitar</button>
                  </div>
                </div>
              </div>
          )}
          </div>
        }
      </div>);

  };

  const FinPagarBanco = ({ row }) => {
    const r = row || { aberto: 1234.56, party: "Papelaria Central Ltda", linha: "03399.12345 67890.123456 78901.234567 8 90120000123456" };
    const [meio, setMeio] = useState("boleto");
    const [destino, setDestino] = useState(r.linha || "");
    const [confirma, setConfirma] = useState("");
    const [etapa, setEtapa] = useState("form"); // form | aguardando | pago
    const alvo = r.aberto.toFixed(2);
    const confere = confirma.trim().replace(",", ".") === alvo;

    return (
      <div style={{ borderTop: "1px solid var(--border)", paddingTop: 16, display: "flex", flexDirection: "column", gap: 10 }}>
        <div style={{ fontSize: "var(--fs-1)", textTransform: "uppercase", letterSpacing: ".08em", color: "var(--text-mute)", fontWeight: 500 }}>Pagar pelo banco</div>

        {etapa === "form" && <>
          <div style={{ display: "flex", gap: 6 }}>
            {["boleto", "pix"].map((m) =>
            <button key={m} className={"os-btn" + (meio === m ? "" : " ghost")} onClick={() => {setMeio(m);setDestino(m === "boleto" ? r.linha || "" : "");}}>
                {m === "boleto" ? "Boleto" : "Pix"}
              </button>
            )}
          </div>
          <label style={sub}>{meio === "boleto" ? "Linha digitável" : "Chave Pix ou copia e cola"}
            <input value={destino} onChange={(e) => setDestino(e.target.value)}
            style={{ display: "block", width: "100%", marginTop: 4, padding: "6px 8px", border: "1px solid var(--border)", borderRadius: 6, fontFamily: "var(--font-mono)", fontSize: "var(--fs-1)" }} />
          </label>
          <div style={sub}>Debita: Inter · ag. 0001 · c/c 123456-7</div>
          <label style={sub}>Para confirmar, digite o valor: <b style={{ color: "var(--text)" }}>{fmtBRL(r.aberto)}</b>
            <input value={confirma} onChange={(e) => setConfirma(e.target.value)} inputMode="decimal" placeholder={alvo.replace(".", ",")}
            style={{ display: "block", width: 160, marginTop: 4, padding: "6px 8px", border: "1px solid var(--border)", borderRadius: 6, fontVariantNumeric: "tabular-nums" }} />
          </label>
          <button className="os-btn primary" disabled={!confere || !destino} onClick={() => setEtapa("aguardando")}
          style={{ alignSelf: "flex-start", opacity: confere && destino ? 1 : 0.5 }}>
            Pagar {fmtBRL(r.aberto)} pelo banco
          </button>
          <div style={sub}>A baixa só acontece quando o banco confirmar o pagamento.</div>
        </>}

        {etapa === "aguardando" &&
        <div style={{ ...box, padding: 12 }}>
            <div style={{ fontSize: "var(--fs-2)", color: "var(--warn)", fontWeight: 500 }}>Aguardando o banco</div>
            <div style={sub}>Pedido enviado por Eliana às 10:42 · {meio === "boleto" ? "boleto" : "Pix"} · {fmtBRL(r.aberto)}. O título segue a pagar até a confirmação.</div>
            <button className="os-btn ghost" style={{ marginTop: 8 }} onClick={() => setEtapa("pago")}>(demo) banco confirmou</button>
          </div>
        }

        {etapa === "pago" &&
        <div style={{ ...box, padding: 12 }}>
            <div style={{ fontSize: "var(--fs-2)", color: "var(--pos)", fontWeight: 500 }}>Pago pelo banco</div>
            <div style={sub}>Banco confirmou às 10:58 · baixa de {fmtBRL(r.aberto)} registrada · título quitado.</div>
          </div>
        }
      </div>);

  };

  const FinDdaPagamentoDemo = () =>
  <div style={{ display: "flex", gap: 24, alignItems: "flex-start", padding: 24, background: "var(--bg-2)" }}>
      <FinDdaSheet open={true} />
      <div style={{ ...box, width: 420, padding: 20 }}>
        <div style={{ fontSize: "var(--fs-3)", fontWeight: 600 }}>P-00041 · Papelaria Central Ltda</div>
        <div style={sub}>A pagar · vence 05/11</div>
        <div style={{ height: 12 }} />
        <FinPagarBanco />
      </div>
    </div>;


  window.FinDdaSheet = FinDdaSheet;
  window.FinPagarBanco = FinPagarBanco;
  window.FinDdaPagamentoDemo = FinDdaPagamentoDemo;
})();
