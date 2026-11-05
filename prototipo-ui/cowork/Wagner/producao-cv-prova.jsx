// producao-cv-prova.jsx — a página que o cliente abre pelo link da prova (UC-PCV-04 · UC-PCV-11).
// Espelha a /arte/{token} do sistema (Modules/Producao/Resources/views/aprovacao-arte.blade.php, #9645,
// D-ARTE-PAGINA [W] 2026-10-21): sem login e sem dado do cliente — só a prova, o nome da etapa, a validade e
// os dois botões. O link vale UMA resposta: depois de aprovar ou pedir alteração, ele mostra só a mensagem.
// Aprovar libera a OS para a impressão; pedir alteração exige o comentário e volta a OS para a Arte.
// As frases são as do sistema (AprovacaoArteController / AprovacaoArteService), para o protótipo não inventar.
// Expõe window.PcvProva. Depende de window.PCVD.
(() => {
const { useState } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const P = () => window.PCVD;

const RESPONDIDA = {}; // "os.id:versão" → mensagem final; cada link é de uso único e cada troca de arquivo gera outro
const VALIDADE = "22/10/2026 17:30"; // 7 dias depois do envio da prova (14/10 17:30)

function PaginaProva({ os, onFechar }) {
  const D = P(); const { Button } = DS();
  const [coment, setComent] = useState("");
  const [erro, setErro] = useState(null);
  const [, setVer] = useState(0);
  const etapa = (D.etapas(os).find((e) => e.setor === "arte") || {}).nome;
  const u = D.arteEmProva(os) || D.versoesArte(os).slice(-1)[0]; // a versão que este link mostra
  const fim = RESPONDIDA[os.id + ":" + u.v];
  const responder = (msg) => { RESPONDIDA[os.id + ":" + u.v] = msg; setVer((v) => v + 1); };
  const aprovar = () => {
    os.arte = "aprovada"; os.aprovadaEm = D.HOJE + " 10:05"; u.decisao = "aprovada"; u.em = os.aprovadaEm;
    D.registrar({ os: os.id, quem: "Cliente", tipo: "arte", texto: "Aprovou a arte pelo link da prova" });
    responder("Arte aprovada. Obrigado! A produção segue para a impressão.");
  };
  const pedir = () => {
    if (!coment.trim()) { setErro("Diga o que precisa mudar na arte."); return; }
    os.arte = "ajuste"; u.decisao = "ajuste";
    D.registrar({ os: os.id, quem: "Cliente", tipo: "arte", texto: "Pediu alteração pelo link da prova: “" + coment.trim() + "”" });
    responder("Pedido de alteração enviado. A gráfica vai ajustar a arte e mandar uma nova prova.");
  };
  return (
    <div className="pcv-prova" role="region" aria-label="O que o cliente vê no link da prova">
      <div className="pcv-prova-url mono">oimpresso.com/arte/K7PM4QXR…</div>
      <div className="pcv-prova-card">
        <h3>{fim ? "Aprovação da arte" : "Aprovação da arte" + (etapa ? " — " + etapa : "")}</h3>
        {fim ? <p>{fim}</p> : <>
          <div className="pcv-prova-img" aria-label="Prova da arte">{D.nomeArq(u.arquivo)}</div>
          <p><a className="pcv-link" href="#prova" onClick={(e) => e.preventDefault()}>Abrir a prova</a></p>
          <p>Válido até {VALIDADE}.</p>
          {Button && <Button variant="primary" onClick={aprovar}>Aprovar a arte</Button>}
          <label className="pcv-prova-coment">Precisa mudar algo? Diga o quê:
            <textarea value={coment} maxLength={2000} onChange={(e) => { setComent(e.target.value); setErro(null); }} /></label>
          {erro && <p className="pcv-prova-erro" role="alert">{erro}</p>}
          {Button && <Button onClick={pedir}>Pedir alteração</Button>}
        </>}
      </div>
      <p className="pcv-help">Sem login e sem dado do cliente. O link vale uma resposta só e vence em 7 dias. {onFechar && <button className="pcv-link" onClick={onFechar}>Fechar</button>}</p>
    </div>
  );
}

window.PcvProva = { PaginaProva };
})();
