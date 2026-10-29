// producao-cv-tarefas.jsx — tarefas da OS com responsável e prazo (P-099, comparação com concorrentes de 2026-10-23,
// fonte: Printavo). Tarefas paralelas da pré-produção, como "separar as chapas" e "fechar o arquivo de corte".
// Regras do [W] que valem aqui:
//   · D-AVISO: quem recebe a tarefa é avisado dentro do sistema (app). WhatsApp é só para o cliente;
//   · falta de material NÃO vira tarefa: segue o fluxo de Aguardando material (US-PCP-032), que abre a compra;
//   · a OS guarda duas datas (decisão de 2026-10-20): a da produção e a combinada com o cliente.
// Casos: cowork-inbox/producao/Producao.casos.md, UC-PCV-51. Expõe window.PcvTarefas. Depende de window.PCVD.
(() => {
const { useState } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const P = () => window.PCVD;
const B = (p) => (window.PcvBadge ? <window.PcvBadge par={p} /> : null);

// Tarefas por OS. `prazo` em ISO; `feita` guarda quem concluiu e quando.
const TAREFAS = {
  "OS-2614": [
    { id: "T-41", titulo: "Separar as 3 chapas de ACM no estoque", resp: "Marcos", prazo: "2026-10-16", feita: null },
    { id: "T-42", titulo: "Fechar o arquivo de corte das placas", resp: "Ana", prazo: "2026-10-14", feita: null },
  ],
  "OS-2608": [
    { id: "T-37", titulo: "Confirmar com o cliente o horário da instalação", resp: "Larissa", prazo: "2026-10-15", feita: { quem: "Larissa", quando: "15/10 08:30" } },
  ],
  "OS-2617": [
    { id: "T-44", titulo: "Conferir o ponto elétrico na academia", resp: "Rafa", prazo: "2026-10-20", feita: null },
  ],
};
// Data da produção (quando a fábrica termina). A data combinada com o cliente é o `prazo` da OS.
const PRAZO_PRODUCAO = { "OS-2614": "2026-10-19", "OS-2608": "2026-10-15", "OS-2617": "2026-10-22", "OS-2611": "2026-10-16" };

let seq = 45;
const tarefasDe = (id) => TAREFAS[id] || [];
const situacao = (t) => { const D = P(); if (t.feita) return "feita"; if (t.prazo < D.HOJE) return "atrasada"; if (t.prazo === D.HOJE) return "vence hoje"; return "no prazo"; };
const abertas = (id) => tarefasDe(id).filter((t) => !t.feita);
const atrasadas = () => Object.keys(TAREFAS).flatMap((id) => abertas(id).filter((t) => situacao(t) !== "no prazo").map((t) => ({ ...t, os: id })));
const SIT = { atrasada: ["Atrasada", "danger"], "vence hoje": ["Vence hoje", "warning"], "no prazo": ["No prazo", "neutral"], feita: ["Feita", "success"] };

// Aba "Tarefas" da ficha da OS.
function AbaTarefas({ os }) {
  const D = P(); const { Button, Input, Select } = DS();
  const [nova, setNova] = useState(null);
  const lista = tarefasDe(os.id);
  const pessoas = Object.keys(D.PESSOAS);
  const concluir = (t) => { t.feita = { quem: "Você", quando: "agora" }; D.registrar({ os: os.id, tipo: "pcp", texto: "Concluiu a tarefa: " + t.titulo }); };
  const criar = () => {
    (TAREFAS[os.id] = TAREFAS[os.id] || []).push({ id: "T-" + seq++, titulo: nova.titulo.trim(), resp: nova.resp, prazo: nova.prazo, feita: null });
    D.registrar({ os: os.id, tipo: "pcp", texto: "Criou a tarefa \"" + nova.titulo.trim() + "\" para " + nova.resp + ", prazo " + D.d2(nova.prazo) + ". " + nova.resp + " foi avisado no app." });
    setNova(null);
  };
  const prod = PRAZO_PRODUCAO[os.id];
  return (
    <div className="pcv-sec">
      <div className="pcv-tf-datas">
        <span><small className="pcv-eyebrow">Produção termina</small><b className="mono">{prod ? D.d2(prod) : "—"}</b></span>
        <span><small className="pcv-eyebrow">Combinado com o cliente</small><b className="mono">{D.d2(os.prazo)}</b></span>
      </div>
      {lista.length ? <ul className="pcv-refugo" aria-label={"Tarefas da " + os.id}>{lista.map((t) => { const s = situacao(t); return (
        <li key={t.id}>
          <input className="pcv-tf-ck" type="checkbox" checked={!!t.feita} disabled={!!t.feita} onChange={() => concluir(t)} aria-label={"Concluir " + t.titulo} />
          <div><b className={t.feita ? "pcv-tf-feita" : ""}>{t.titulo}</b><small>{t.resp} · prazo {D.d2(t.prazo)}{t.feita ? " · concluída por " + t.feita.quem + " " + t.feita.quando : ""}</small></div>
          {B(SIT[s])}
        </li>); })}</ul>
        : <p className="pcv-help">Nenhuma tarefa nesta OS.</p>}
      {nova ? <div className="pcv-form">
        {Input && <Input label="O que precisa ser feito" value={nova.titulo} placeholder="Ex.: separar as chapas, fechar a arte, confirmar a medida" onChange={(e) => setNova({ ...nova, titulo: e.target.value })} />}
        {Select && <Select label="Responsável" value={nova.resp} onChange={(e) => setNova({ ...nova, resp: e.target.value })} options={pessoas.map((p) => ({ value: p, label: p + " · " + D.PESSOAS[p] }))} />}
        {Input && <Input label="Prazo" type="date" value={nova.prazo} onChange={(e) => setNova({ ...nova, prazo: e.target.value })} />}
        <div className="pcv-bar"><Button onClick={() => setNova(null)}>Cancelar</Button><Button variant="primary" disabled={nova.titulo.trim().length < 3 || !nova.prazo} onClick={criar}>Criar tarefa</Button></div>
      </div> : Button && <Button onClick={() => setNova({ titulo: "", resp: pessoas[0], prazo: D.HOJE })}>Nova tarefa</Button>}
      <p className="pcv-help">Quem recebe a tarefa é avisado no app; o WhatsApp fica só para o cliente. Falta de material não é tarefa: a OS vai para Aguardando material e a compra sai de lá.</p>
    </div>
  );
}

// Painel: tarefas atrasadas ou vencendo hoje. Sem nenhuma, o widget não aparece (nada decorativo).
function TarefasPainel({ onAbrir }) {
  const D = P(); const { Widget } = DS();
  const lst = atrasadas();
  if (!lst.length || !Widget) return null;
  return (
    <Widget title="Tarefas atrasadas ou vencendo hoje" note="da pré-produção — cada responsável já foi avisado no app" flush>
      <ul className="pcv-fila-arte">{lst.map((t) => (
        <li key={t.id}><div><b className="mono">{t.os}</b> · {t.titulo}<small>{t.resp} · prazo {D.d2(t.prazo)}</small></div>
          {B(SIT[situacao(t)])}<button className="pcv-link" onClick={() => onAbrir(t.os)}>Abrir</button></li>))}</ul>
    </Widget>
  );
}

// Selo do cartão do quadro: quantas tarefas faltam; vermelho se alguma atrasou.
function SeloCartao({ id }) {
  const a = abertas(id); if (!a.length) return null;
  const atr = a.some((t) => situacao(t) === "atrasada");
  return B([a.length === 1 ? "1 tarefa" : a.length + " tarefas", atr ? "danger" : "neutral"]);
}

window.PcvTarefas = { AbaTarefas, TarefasPainel, SeloCartao, abertas };
})();
