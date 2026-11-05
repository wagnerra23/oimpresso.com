// fisio-page.jsx — o módulo Fisio (Modules/Fisio) dentro do shell do protótipo.
// As telas são as do canvas Prescreve Pro, versionadas em prototipos/fisio/*.dc.html. Elas usam o
// runtime .dc.html (support.js), que não roda numa shadow root como o ProtoHtmlPage; por isso
// entram por iframe. Cada tela da clínica desenha o próprio menu lateral (aside.sb): ao carregar,
// ele é escondido, para a tela aparecer debaixo do menu real do ERP. As telas do paciente são de
// celular (390 px) e aparecem num quadro de celular.
// O arquivo .dc.html continua sendo a fonte; esta página só o mostra. Expõe window.FisioPage.
(() => {
const { useEffect, useRef, useState } = React;

const BASE = "prototipos/fisio/";

// [rota, arquivo, rótulo]. A ordem da clínica segue a ADR 0437 §3.3 (Painel · Pacientes ·
// Prescrever · Biblioteca · Agenda); o canvas não desenha a lista de pacientes.
const CLINICA = [
  ["fisio",               "Main",                    "Painel"],
  ["fisio-prescrever",    "Prescricao",              "Prescrever"],
  ["fisio-biblioteca",    "Biblioteca",              "Biblioteca"],
  ["fisio-busca",         "BuscaBiblioteca",         "Busca na biblioteca"],
  ["fisio-agenda",        "Agenda",                  "Agenda"],
  ["fisio-protocolos",    "Protocolos",              "Protocolos"],
  ["fisio-questionarios", "Questionarios",           "Questionários"],
  ["fisio-novo",          "PacienteNovo",            "Novo paciente"],
  ["fisio-avaliacao",     "Avaliacao",               "Avaliação"],
  ["fisio-evolucao",      "Evolucao",                "Evolução"],
  ["fisio-ditada",        "EvolucaoDitada",          "Evolução ditada"],
  ["fisio-contra",        "Contraindicacao",         "Contraindicação"],
  ["fisio-tele",          "Teleconsulta",            "Teleconsulta"],
  ["fisio-laudo",         "Laudo",                   "Laudo"],
  ["fisio-camera",        "CameraLiberacao",         "Liberar câmera"],
  ["fisio-financeiro",    "Financeiro",              "Financeiro"],
  ["fisio-fiscal",        "FiscalFila",              "Fila fiscal"],
  ["fisio-planos",        "Planos",                  "Planos"],
  ["fisio-agendamento",   "ConfigAgendamentoOnline", "Agendamento online"],
  ["fisio-whatsapp",      "ConfigWhatsApp",          "WhatsApp"],
  ["fisio-onboarding",    "Onboarding",              "Primeira configuração"],
];
const PACIENTE = [
  ["fisio-p-acesso",      "PacienteAcesso",          "Entrada"],
  ["fisio-p-hoje",        "PacienteHoje",            "Hoje"],
  ["fisio-p-checkin",     "PacienteCheckin",         "Check-in"],
  ["fisio-p-crise",       "PacienteCrise",           "Crise"],
  ["fisio-p-nota",        "PacienteNota",            "Recado"],
  ["fisio-p-jornada",     "PacienteJornada",         "Jornada"],
  ["fisio-p-progresso",   "PacienteProgresso",       "Progresso"],
  ["fisio-p-consultas",   "PacienteConsultas",       "Consultas"],
  ["fisio-p-agendar",     "PacienteAgendar",         "Agendar"],
  ["fisio-p-confirmar",   "PacienteAgendaConfirmar", "Confirmar consulta"],
  ["fisio-p-serie",       "PacienteSerieConfirmar",  "Confirmar série"],
  ["fisio-p-questionario","PacienteQuestionario",    "Questionário"],
  ["fisio-p-anamnese",    "PacientePreAnamnese",     "Pré-anamnese"],
  ["fisio-p-camera",      "PacienteCamera",          "Câmera"],
  ["fisio-p-preparo",     "PacienteCameraPreparo",   "Preparar câmera"],
  ["fisio-p-classe2",     "PacienteCameraClasse2",   "Câmera classe 2"],
  ["fisio-p-config",      "PacienteConfig",          "Configurações"],
  ["fisio-p-instalar",    "PacienteInstalar",        "Instalar"],
];
const TELAS = {};
CLINICA.forEach(([r, f, l]) => { TELAS[r] = { f, l, celular: false }; });
PACIENTE.forEach(([r, f, l]) => { TELAS[r] = { f, l, celular: true }; });

if (!document.getElementById("fisio-page-css")) {
  const s = document.createElement("style");
  s.id = "fisio-page-css";
  s.textContent = `
.fisio-page { height: 100%; overflow: auto; background: var(--bg); }
.fisio-telas { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 8px 16px;
  border-bottom: 1px solid var(--border); background: var(--surface); font-size: 12.5px; }
.fisio-telas-l { color: var(--text-dim); margin-right: 4px; }
.fisio-telas select { font: inherit; min-height: 32px; padding: 0 8px; border-radius: 6px;
  border: 1px solid var(--border); background: var(--bg); color: var(--text); }
.fisio-telas-fonte { margin-left: auto; color: var(--text-dim); font-family: var(--font-mono, monospace); font-size: 11.5px; }
.fisio-quadro { display: block; width: 100%; border: 0; background: var(--bg); }
.fisio-celular { display: flex; justify-content: center; padding: 24px 16px; }
.fisio-celular .fisio-quadro { width: 390px; border-radius: 28px; box-shadow: 0 0 0 10px var(--text), 0 12px 32px rgba(0,0,0,.18); }`;
  document.head.appendChild(s);
}

// Esconde o menu próprio da tela e ajusta a altura do iframe ao conteúdo.
function ajustar(iframe) {
  let doc;
  try { doc = iframe.contentDocument; } catch (e) { return null; }
  if (!doc || !doc.head) return null;
  if (!doc.getElementById("fisio-no-shell")) {
    const st = doc.createElement("style");
    st.id = "fisio-no-shell";
    st.textContent = "aside.sb{display:none!important}";
    doc.head.appendChild(st);
  }
  const medir = () => {
    const h = Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0);
    if (h) iframe.style.height = h + "px";
  };
  medir();
  const ro = new ResizeObserver(medir);
  if (doc.body) ro.observe(doc.body);
  return ro;
}

function FisioPage({ view }) {
  const rota = TELAS[view] ? view : "fisio";
  const tela = TELAS[rota];
  const ref = useRef(null);
  const [erro, setErro] = useState(false);
  useEffect(() => { setErro(false); }, [rota]);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    let ro = null;
    const onLoad = () => { if (ro) ro.disconnect(); ro = ajustar(el); };
    el.addEventListener("load", onLoad);
    return () => { el.removeEventListener("load", onLoad); if (ro) ro.disconnect(); };
  }, [rota]);
  const ir = (r) => window.dispatchEvent(new CustomEvent("pg:goto-route", { detail: r }));
  const src = BASE + tela.f + ".dc.html";
  const quadro = (
    <iframe ref={ref} key={rota} className="fisio-quadro" src={src} title={"Fisio · " + tela.l}
      onError={() => setErro(true)} style={{ height: tela.celular ? 844 : 900 }} />
  );
  return (
    <div className="fisio-page">
      <div className="fisio-telas">
        <label className="fisio-telas-l" htmlFor="fisio-tela">Tela</label>
        <select id="fisio-tela" value={rota} onChange={(e) => ir(e.target.value)}>
          <optgroup label="Clínica">{CLINICA.map(([r, , l]) => <option key={r} value={r}>{l}</option>)}</optgroup>
          <optgroup label="Paciente (celular)">{PACIENTE.map(([r, , l]) => <option key={r} value={r}>{l}</option>)}</optgroup>
        </select>
        <span className="fisio-telas-fonte">{src}</span>
      </div>
      {erro ? <div className="proto-html-erro">Não abriu {src}.</div>
        : tela.celular ? <div className="fisio-celular">{quadro}</div> : quadro}
    </div>
  );
}

window.FisioPage = FisioPage;
})();
