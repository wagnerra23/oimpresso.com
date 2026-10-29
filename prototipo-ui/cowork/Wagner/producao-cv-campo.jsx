// producao-cv-campo.jsx — as duas pontas de celular da Produção:
//   · Apontamento do operador: lê o QR da OS → etapa dele → cronômetro (iniciar/pausar/concluir) → baixa de
//     material e refugo. Append-only (pcp_appointments, SPEC do Pcp). Toque ≥ 44px.
//   · Instalação: agenda da equipe de campo + checklist com foto antes/depois e assinatura do cliente.
// Renderiza num quadro de 390px (como o cliente/operador vê). Expõe window.PcvCampo.
(() => {
const { useState, useEffect } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const P = () => window.PCVD;
const B = (p) => (window.PcvBadge ? <window.PcvBadge par={p} /> : null);
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <span aria-hidden="true" style={{ display: "inline-flex" }}><F size={size} /></span> : null; };
const hms = (s) => [Math.floor(s / 3600), Math.floor(s / 60) % 60, s % 60].map((n) => String(n).padStart(2, "0")).join(":");

function Celular({ titulo, children, rodape }) {
  return (
    <div className="pcv-phone" role="region" aria-label={titulo}>
      <div className="pcv-phone-top"><b>{titulo}</b><small>oimpresso · produção</small></div>
      <div className="pcv-phone-body">{children}</div>
      {rodape && <div className="pcv-phone-foot">{rodape}</div>}
    </div>
  );
}

function Apontamento() {
  const D = P(); const { Button, Alert, Select, Input } = DS();
  const [passo, setPasso] = useState("lista");
  const [rodando, setRodando] = useState(false);
  const [seg, setSeg] = useState(2 * 3600 + 14 * 60 + 8);
  const [m2, setM2] = useState("");
  const [log, setLog] = useState([]);
  const [pausa, setPausa] = useState(false); // Pausar pede o motivo antes de parar o relógio
  const os = D.osDe("OS-2611"); const et = D.atual(os); const maq = D.maqDe(et.maq);
  useEffect(() => { if (!rodando) return; const t = setInterval(() => setSeg((s) => s + 1), 1000); return () => clearInterval(t); }, [rodando]);
  const marca = (ev) => { setLog((l) => [{ ev, quando: new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }) }, ...l]); D.registrar({ os: os.id, quem: "Diego", tipo: /Refugo/.test(ev) ? "refugo" : "etapa", maq: et.maq, texto: ev + (/Refugo/.test(ev) ? "" : " " + et.nome) }); };
  return (
    <div className="pcv-body pcv-campo">
      <div className="pcv-campo-txt">
        <h2 className="pcv-h2">Apontamento no celular</h2>
        <p className="pcv-help">O operador aponta a câmera para o QR da etiqueta da OS (ou da máquina), vê só a etapa dele e marca início, pausa e fim. O tempo vira custo real; a baixa de material e o refugo saem dali mesmo.</p>
        <ul className="pcv-pontos">
          <li><b>Sem digitar OS</b> — o QR já diz qual OS e qual etapa.</li>
          <li><b>Arquivo certo</b> — abre a versão aprovada da arte, não uma prova antiga.</li>
          <li><b>Esqueceu de parar?</b> — no fim do turno da empresa ({D.TURNO.rotulo}) o sistema fecha o cronômetro e avisa o PCP.</li>
          <li><b>Sem internet</b> — guarda no celular e envia quando voltar.</li>
        </ul>
      </div>
      <Celular titulo={passo === "scan" ? "Ler QR" : passo === "lista" ? "Produção" : os.id}
        rodape={passo === "etapa" && pausa ? <div className="pcv-pausa" role="group" aria-label="Motivo da pausa">
          <b>Por que parou?</b>
          {["Falta material", "Máquina", "Aguardando cliente"].map((m) => <Button key={m} onClick={() => { setPausa(false); setRodando(false); marca("Pausou — " + m.toLowerCase()); }}>{m}</Button>)}
          <Button variant="ghost" onClick={() => setPausa(false)}>Voltar</Button>
        </div> : passo === "etapa" && <div className="pcv-bar" style={{ width: "100%" }}>
          {!rodando ? <Button variant="primary" onClick={() => { setRodando(true); marca("Iniciou"); }}>{seg > 0 ? "Retomar" : "Iniciar"}</Button> : <Button onClick={() => setPausa(true)}>Pausar</Button>}
          <Button onClick={() => { setRodando(false); marca("Concluiu a etapa"); setPasso("fim"); }}>Concluir etapa</Button>
        </div>}>
        {passo === "lista" && <div className="pcv-ap">
          <div><span className="pcv-eyebrow">Diego · impressão</span><b className="pcv-ap-t">Minhas etapas de hoje</b><small>na ordem da programação</small></div>
          <ul className="pcv-minhas">
            {[["OS-2611", "Impressão eco-solvente", "Roland VG3-640", "em andamento · 6,2 de 9,6 h"], ["OS-2619", "Impressão látex", "HP Latex 365", "10:00 · 0,6 h"], ["OS-2608", "Adesivo de aplicação", "Roland VG3-640", "14:30 · 1,1 h"]].map(([id, et, mq, q], i) => (
              <li key={id}><button onClick={() => id === "OS-2611" ? setPasso("etapa") : null} aria-label={id + " · " + et}><b className="mono">{id}</b><span>{et}</span><small>{mq} · {q}</small></button></li>))}
          </ul>
          {Button && <Button onClick={() => setPasso("scan")}>Ler QR de outra OS</Button>}
        </div>}
        {passo === "scan" && <div className="pcv-scan">
          <div className="pcv-scan-box" aria-hidden="true"><span>QR</span></div>
          <p className="pcv-help">Aponte para a etiqueta da OS ou da máquina.</p>
          {Button && <Button variant="primary" onClick={() => setPasso("etapa")}>Simular leitura da OS-2611</Button>}
        </div>}
        {passo === "etapa" && <div className="pcv-ap">
          <div><span className="pcv-eyebrow">{os.cliente}</span><b className="pcv-ap-t">{et.nome}</b><small>{maq ? maq.nome : ""} · previsto {D.h1(et.prev)} h</small></div>
          <div className={"pcv-crono" + (rodando ? " is-on" : "")} role="timer" aria-live="off" aria-label={"Tempo apontado " + hms(seg)}>{hms(seg)}</div>
          <div className="pcv-ap-arq"><Ic name="doc" size={16} /><div><b>arte-final-v3.pdf</b><small>versão aprovada pelo cliente</small></div></div>
          {Input && <Input label="Mídia usada até agora (m²)" value={m2} placeholder="ex.: 58" onChange={(e) => setM2(e.target.value)} help="Baixa no estoque: Vinil branco brilho 1,37 m" />}
          <button className="pcv-ap-ref" onClick={() => marca("Refugo: 1,2 m² — mídia com defeito")}>Registrar refugo</button>
          {log.length > 0 && <ol className="pcv-hist">{log.map((l, i) => <li key={i}><b>{l.ev}</b><small>{l.quando}</small></li>)}</ol>}
        </div>}
        {passo === "fim" && <div className="pcv-ap">
          {Alert && <Alert tone="success" title="Etapa concluída">{hms(seg)} apontados em {et.nome}. A OS foi para <b>Laminação</b> (Felipe) e ele foi avisado.</Alert>}
          {Button && <Button onClick={() => { setPasso("lista"); setSeg(0); setLog([]); }}>Voltar às minhas etapas</Button>}
        </div>}
      </Celular>
    </div>
  );
}

function Instalacao({ onAbrir }) {
  const D = P(); const { Button, Alert, Widget } = DS();
  const [sel, setSel] = useState("I-118");
  const [feitos, setFeitos] = useState({ 0: true, 1: true });
  const [assinado, setAssinado] = useState(false);
  const [feito, setFeito] = useState({});
  const inst = D.INSTALACOES.find((i) => i.id === sel);
  const EST = { agendada: ["Agendada", "info"], "a confirmar": ["A confirmar", "warning"], "concluída": ["Concluída", "success"] };
  return (
    <div className="pcv-body pcv-inst">
      {Widget && <Widget title="Agenda de instalação" note="equipe de campo · próximos dias" flush>
        <ul className="pcv-inst-l">
          {D.INSTALACOES.map((i) => (
            <li key={i.id}><button aria-current={sel === i.id ? "true" : undefined} onClick={() => { setSel(i.id); setFeitos(i.estado === "concluída" ? { 0: 1, 1: 1, 2: 1 } : {}); setAssinado(i.estado === "concluída"); }}>
              <div className="pcv-inst-d"><b>{D.d2(i.dia)}</b><small>{i.hora}</small></div>
              <div className="pcv-inst-m"><b>{i.cliente}</b><small>{i.os} · {i.equipe.join(" + ")} · {i.dur} h · {i.veiculo}</small></div>
              {B(EST[i.estado])}
            </button></li>))}
        </ul>
        <div className="pcv-bar" style={{ padding: "10px 14px" }}><Button size="sm" onClick={() => onAbrir(inst.os)}>Abrir {inst.os}</Button><span className="pcv-help">A OS só vai para Instalação quando todas as etapas de fábrica fecham.</span></div>
      </Widget>}
      <Celular titulo={"Instalação " + inst.id}
        rodape={inst.estado !== "concluída" && <Button variant="primary" disabled={!assinado || feito[inst.id]} onClick={() => { setFeito({ ...feito, [inst.id]: true }); D.registrar({ os: inst.os, quem: inst.equipe[0], tipo: "instalacao", texto: "Concluiu a instalação — protocolo com fotos e assinatura enviado ao cliente" }); }}>{feito[inst.id] ? "Enviado ao cliente" : "Concluir instalação"}</Button>}>
        <div className="pcv-ap">
          <div><span className="pcv-eyebrow">{inst.os} · {inst.dia.split("-").reverse().slice(0, 2).join("/")} às {inst.hora}</span><b className="pcv-ap-t">{inst.cliente}</b><small>{inst.end}</small></div>
          <a className="pcv-link" href={"https://maps.google.com/?q=" + encodeURIComponent(inst.end)} target="_blank" rel="noreferrer">Abrir no mapa</a>
          <ul className="pcv-check" aria-label="Checklist da instalação">
            {inst.checklist.map((c, i) => { const foto = /^Foto/.test(c), ass = /Assinatura/.test(c); const ok = ass ? assinado : !!feitos[i]; return (
              <li key={i}><button className={ok ? "is-ok" : ""} aria-pressed={ok} onClick={() => ass ? setAssinado(!assinado) : setFeitos({ ...feitos, [i]: !feitos[i] })}>
                <span className="pcv-ck" aria-hidden="true">{ok ? "✓" : ""}</span><span>{c}</span>{foto && <small>{ok ? "1 foto" : "tirar foto"}</small>}{ass && <small>{ok ? (inst.assinado || "Assinado na tela") : "assinar na tela"}</small>}
              </button></li>); })}
          </ul>
          {assinado && Alert && <Alert tone="success" title="Protocolo de instalação gerado">PDF com fotos antes/depois, checklist, horário e assinatura vai para o cliente por WhatsApp e fica na OS.</Alert>}
        </div>
      </Celular>
    </div>
  );
}

window.PcvCampo = { Apontamento, Instalacao };
})();
