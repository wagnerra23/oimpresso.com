/* manufacturing-app.jsx — CONTORNO DO PACOTE: shell mínimo + único ponto de mount.
   No app real quem monta é Inertia (`resources/js/app.tsx` → Pages/Manufacturing/*) dentro do
   AppShellV2; este arquivo não é portado. Ele existe só para o guia abrir com duplo-clique.

   Faz três coisas e nada além:
   1. `window.__go(rota)` — no cockpit é o roteador do shell (app.jsx). Aqui é um STUB que
      apenas anuncia o destino: os botões "Compras / Produtos / Fila / Financeiro" da tela
      são pontes entre módulos e não têm para onde ir num pacote de uma tela só.
   2. Troca de tema (claro/escuro) — necessária para conferir contraste nos DOIS temas, que é
      exigência do LAUDO. No app o tema vem do tweak do shell (localStorage `oimpresso.theme`).
   3. Mount de <ManufacturingPage/> na região de dados.
*/
(() => {
const { useState } = React;

function Guia() {
  const [tema, setTema] = useState("claro");
  const [ponte, setPonte] = useState(null);

  // 1 · stub das pontes entre módulos
  window.__go = (rota) => setPonte(rota);

  const trocarTema = (t) => {
    setTema(t);
    if (t === "escuro") document.documentElement.setAttribute("data-theme", "dark");
    else document.documentElement.removeAttribute("data-theme");
  };

  return (
    <>
      <div className="guia-bar">
        <b>Fabricação · guia de produção</b>
        <small>referência de design — não é código de produção</small>
        <span className="sp" />
        <small>Tema</small>
        <div className="guia-bar-seg" role="group" aria-label="Tema">
          <button aria-pressed={tema === "claro"} onClick={() => trocarTema("claro")}>Claro</button>
          <button aria-pressed={tema === "escuro"} onClick={() => trocarTema("escuro")}>Escuro</button>
        </div>
      </div>

      <div className="guia-aviso">
        {ponte
          ? <>Ponte entre módulos: no cockpit este botão chama <code>__go("{ponte}")</code> e abre o módulo <b>{ponte}</b>. No pacote de uma tela só, não há destino.</>
          : <>Atalhos: <code>/</code> foca a busca · <code>esc</code> fecha drawer e modal. Os botões que levam a outro módulo (Compras, Produtos, Fila, Financeiro) só anunciam o destino aqui.</>}
      </div>

      <div className="guia-conteudo">
        <window.ManufacturingPage initialView="receitas" />
      </div>
    </>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<Guia />);
})();
