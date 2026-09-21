// vendas-shortcuts.jsx — Refino #1 KB-9.75 · cheat-sheet overlay
// Acionado por "?" no módulo Vendas. Não captura atalhos — vendas-page.jsx faz isso.
// Aqui só renderiza a folha de referência visual.

const { useEffect: useEffectVS } = React;

function VdCheatSheet({ onClose }) {
  useEffectVS(() => {
    const onKey = (e) => { if (e.key === "Escape") { e.preventDefault(); onClose(); } };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  return (
    <div className="vd-cheat-bd" onClick={onClose}>
      <div className="vd-cheat" onClick={e => e.stopPropagation()}>
        <header className="vd-cheat-h">
          <span className="vd-cheat-ic">⌨</span>
          <h2>Atalhos do balcão</h2>
          <p>Vendas opera sem mouse. Pressione qualquer combinação abaixo.</p>
          <button className="vd-cheat-x" onClick={onClose}><kbd>esc</kbd></button>
        </header>

        <div className="vd-cheat-grid">
          <section className="vd-cheat-sec">
            <h4>Navegar</h4>
            <VdCsRow keys={["J"]} lbl={<span>próxima venda <b>↓</b></span>}/>
            <VdCsRow keys={["K"]} lbl={<span>anterior venda <b>↑</b></span>}/>
            <VdCsRow keys={["↵"]} lbl="abrir drawer da venda focada"/>
            <VdCsRow keys={["/"]} lbl="focar campo de busca"/>
            <VdCsRow keys={["⌘","K"]} lbl="command palette (busca global + ações)"/>
          </section>

          <section className="vd-cheat-sec">
            <h4>Ações na venda focada</h4>
            <VdCsRow keys={["N"]} lbl="nova venda"/>
            <VdCsRow keys={["R"]} lbl="imprimir recibo (térmica/A4)"/>
            <VdCsRow keys={["F"]} lbl="faturar NF-e / NFS-e"/>
            <VdCsRow keys={["B"]} lbl="favoritar (pessoal — atalho B)"/>
            <VdCsRow keys={["X"]} lbl="selecionar pra ação em lote"/>
            <VdCsRow keys={["E"]} lbl="editar venda (drawer Create)"/>
          </section>

          <section className="vd-cheat-sec">
            <h4>⌘K prefixos</h4>
            <VdCsRow keys={["/"]} lbl={<span>filtra <b>ações</b> · ex: <code>/faturar lote</code></span>}/>
            <VdCsRow keys={["#"]} lbl={<span>busca por <b>ID</b> · ex: <code>#7825</code></span>}/>
            <VdCsRow keys={["@"]} lbl={<span>filtra por <b>vendedor</b> · ex: <code>@bruna</code></span>}/>
            <VdCsRow keys={["$"]} lbl={<span>valor <b>mínimo</b> · ex: <code>$2000</code></span>}/>
            <VdCsRow keys={["…"]} lbl={<span>44 dígitos = <b>chave SEFAZ</b></span>}/>
          </section>

          <section className="vd-cheat-sec">
            <h4>Sair / ajuda</h4>
            <VdCsRow keys={["Esc"]} lbl="fechar palette · drawer · cheat-sheet"/>
            <VdCsRow keys={["?"]} lbl="abrir este cheat-sheet"/>
          </section>
        </div>

        <footer className="vd-cheat-ft">
          <span>Atalhos persistem em toda sub-rota de Vendas (Lista · Caixa · Devoluções · Comissões · Relatórios).</span>
          <span>Refino #1 · KB-9.75 fundação · maio/2026</span>
        </footer>
      </div>
    </div>
  );
}

function VdCsRow({ keys, lbl }) {
  return (
    <div className="vd-cs-row">
      <div className="vd-cs-keys">
        {keys.map((k, i) => (
          <React.Fragment key={i}>
            {i > 0 && <span className="vd-cs-plus">+</span>}
            <kbd>{k}</kbd>
          </React.Fragment>
        ))}
      </div>
      <div className="vd-cs-lbl">{lbl}</div>
    </div>
  );
}

window.VdCheatSheet = VdCheatSheet;
