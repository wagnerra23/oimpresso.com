// oficina-aprovacao-publica.jsx — página pública /aprovar-os/{token} (fora do shell), 3 estados.
// US-OFICINA-014 · pagar pelo link (decisão [W] 2026-10-27, opção a): quando a OS fica pronta e a
// venda tem título em aberto, o MESMO link mostra "Pagar agora", que abre a página de pagamento do
// Financeiro (/pagar/{token}, US-FIN-081). Antes disso a página segue com o PIN de aprovar/recusar.
// Tela real: resources/js/Pages/OficinaAuto/AprovacaoPublica.tsx (espera OK de design do [W]).
// IIFE expõe window.OficinaAprovacaoPublica.
(() => {
const { useState } = React;
const BRL = (n) => "R$ " + (n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const OS = { numero: "8825", veiculo: "Renault Sandero 2017", placa: "QAB-3C10", empresa: "Oficina Exemplo", total: 2250, aberto: 2250 };

function Linha({ k, v }) {
  return <div className="oap-linha"><dt>{k}</dt><dd>{v}</dd></div>;
}

function OficinaAprovacaoPublica() {
  const [estado, setEstado] = useState("pronta");

  return (
    <div className="oap">
      <div className="oap-estados" role="group" aria-label="Estado da OS (só no protótipo)">
        {[["orcamento", "Aguardando aprovação"], ["aprovada", "Aprovada, em execução"], ["pronta", "Pronta, com valor em aberto"]].map(([k, l]) => (
          <button key={k} type="button" className={"os-btn sm " + (estado === k ? "primary" : "ghost")} aria-pressed={estado === k} onClick={() => setEstado(k)}>{l}</button>
        ))}
      </div>

      <main className="oap-cartao">
        <header>
          <p className="oap-empresa">{OS.empresa}</p>
          <h1>Ordem de serviço #{OS.numero}</h1>
        </header>
        <dl className="oap-dl">
          <Linha k="Veículo" v={OS.veiculo + " · " + OS.placa}/>
          <Linha k="Valor do orçamento" v={<span className="mono">{BRL(OS.total)}</span>}/>
        </dl>

        {estado === "orcamento" && (
          <form className="oap-form" onSubmit={e => e.preventDefault()}>
            <label htmlFor="oap-pin">PIN de 4 dígitos que você recebeu</label>
            <input id="oap-pin" inputMode="numeric" maxLength={4} autoComplete="one-time-code"/>
            <div className="oap-acoes">
              <button className="os-btn primary oap-grande" type="submit">Aprovar orçamento</button>
              <button className="os-btn ghost oap-grande" type="button">Recusar</button>
            </div>
          </form>
        )}

        {estado === "aprovada" && (
          <p className="oap-aviso" role="status">Orçamento aprovado. Quando o veículo estiver pronto, você poderá pagar por este mesmo link.</p>
        )}

        {estado === "pronta" && (
          <section className="oap-pagar" aria-labelledby="oap-pagar-h">
            <h2 id="oap-pagar-h">Seu veículo está pronto para retirada</h2>
            <dl className="oap-dl">
              <Linha k="Em aberto" v={<b className="mono">{BRL(OS.aberto)}</b>}/>
            </dl>
            <button type="button" className="os-btn primary oap-grande">Pagar agora · Pix ou boleto</button>
            <p className="oap-nota">Você vai para a página de pagamento da {OS.empresa}. Se preferir, pague na retirada.</p>
          </section>
        )}
      </main>
    </div>
  );
}

window.OficinaAprovacaoPublica = OficinaAprovacaoPublica;
})();
