// ponto-mobile.jsx — Onda 4: o REP-P do bolso (Portaria MTP 671/2021 reconhece registrador
// eletrônico por programa). Espelha o contrato real de Modules/Ponto/Http/Controllers/Api/
// MobileMarcacaoController + MobileMarcacaoService: tipos ENTRADA/SAIDA/ALMOCO_INICIO/ALMOCO_FIM,
// selfie obrigatória (só o SHA-256 é guardado — base64 nunca persiste, LGPD), accuracy GPS ≤ 500m,
// drift de relógio ≤ 30s, geofence que NÃO bloqueia (marca revisão humana), device_uuid,
// resposta com NSR + hash truncado. Persona: Técnico Repair, alvos ≥ 44px.
// Expõe window.PontoMobile.
(() => {
const { useState, useEffect, useRef } = React;
const P = () => window.PONTO;
const U = () => window.PontoUI;

const TIPOS = [
  { id: "ENTRADA", label: "Entrada", hint: "início da jornada" },
  { id: "ALMOCO_INICIO", label: "Saída almoço", hint: "intervalo" },
  { id: "ALMOCO_FIM", label: "Retorno almoço", hint: "volta do intervalo" },
  { id: "SAIDA", label: "Saída", hint: "fim da jornada" },
];
const LIMITES = { accuracy_max: 500, drift_max: 30, selfie_min_kb: 100, geofence_raio: 1000 };

// Colaborador logado no app: o técnico externo (marcação em obra é o caso difícil).
const EU = 6;
// Relógio fictício da tela: 20/08/2026 09:30 (mesma hora da status bar do aparelho) — nunca a
// hora da máquina, senão a jornada do mock sai fora de ordem.
const BASE_SEG = 9 * 3600 + 30 * 60;
const hhmmss = (seg) => [Math.floor(seg / 3600), Math.floor(seg / 60) % 60, seg % 60].map((n) => String(n).padStart(2, "0")).join(":");

const hashFake = (seed) => {
  let h = "";
  let x = (seed || 1) * 7919;
  while (h.length < 40) { x = (x * 1103515245 + 12345) % 2147483648; h += (x >>> 5).toString(16); }
  return h.slice(0, 40);
};

// ═════════════ Tela 1: bater ponto ═════════════
function BaterPonto({ estadoGps, marcacoes, onMarcar, avisar }) {
  const D = P();
  const [selfie, setSelfie] = useState(false);
  const [tipo, setTipo] = useState(() => {
    const n = marcacoes.length;
    return TIPOS[Math.min(n, 3)].id;
  });
  const [seg, setSeg] = useState(BASE_SEG + 7);
  useEffect(() => {
    const t = setInterval(() => setSeg((s) => s + 1), 1000);
    return () => clearInterval(t);
  }, []);
  const relogio = hhmmss(seg);

  const bloqueio = estadoGps.accuracy > LIMITES.accuracy_max
    ? "GPS com precisão de " + estadoGps.accuracy + "m (limite " + LIMITES.accuracy_max + "m) — aguarde sinal melhor."
    : Math.abs(estadoGps.drift) > LIMITES.drift_max
      ? "Relógio do aparelho fora de sincronia (" + estadoGps.drift + "s) — ajuste a hora automática."
      : !selfie ? "Tire a selfie para registrar." : null;

  return (
    <div className="ptm-screen">
      <div className="ptm-hero">
        <span className="ptm-hero-l">agora</span>
        <b className="ptm-clock">{relogio}</b>
        <span className="ptm-hero-l">20 de agosto de 2026 · quinta</span>
      </div>

      <div className="ptm-gps" data-contract="repp-gps">
        <span className={"ptm-dot " + (estadoGps.accuracy > LIMITES.accuracy_max ? "bad" : estadoGps.dentroGeofence ? "ok" : "warn")} />
        <div>
          <b>{estadoGps.local}</b>
          <small>GPS ±{estadoGps.accuracy}m · {estadoGps.dentroGeofence ? "dentro da área da empresa" : "fora da área — vai para revisão do RH"}</small>
        </div>
      </div>

      <button className={"ptm-selfie" + (selfie ? " feita" : "")} onClick={() => setSelfie((v) => !v)}>
        <span className="ptm-selfie-ic">{selfie ? "✓" : "▢"}</span>
        <span>
          <b>{selfie ? "Selfie capturada" : "Tirar selfie"}</b>
          <small>{selfie ? "guardamos só o código da imagem, nunca a foto" : "obrigatória — mín. " + LIMITES.selfie_min_kb + " KB"}</small>
        </span>
      </button>

      <div className="ptm-tipos" data-contract="repp-tipos">
        {TIPOS.map((t) => (
          <button key={t.id} className={"ptm-tipo" + (tipo === t.id ? " on" : "")} onClick={() => setTipo(t.id)}>
            <b>{t.label}</b><small>{t.hint}</small>
          </button>
        ))}
      </div>

      <button className="ptm-cta" disabled={!!bloqueio} title={bloqueio || ""}
        onClick={() => { onMarcar(tipo); setSelfie(false); }}>
        Bater ponto — {TIPOS.find((t) => t.id === tipo).label}
      </button>
      {bloqueio && <p className="ptm-bloqueio">{bloqueio}</p>}

      <div className="ptm-hoje">
        <span className="ptm-h">Hoje</span>
        {marcacoes.length === 0 && <p className="ptm-vazio">Nenhuma marcação registrada hoje.</p>}
        {marcacoes.map((m, i) => (
          <div className="ptm-linha" key={i}>
            <b>{m.hora}</b>
            <span>{TIPOS.find((t) => t.id === m.tipo)?.label || m.tipo}</span>
            <small>NSR {m.nsr}{m.foraGeofence ? " · fora da área" : ""}</small>
          </div>
        ))}
      </div>
      <p className="ptm-legal">Marcação imutável (Portaria MTP 671/2021). Correção só por intercorrência.</p>
    </div>
  );
}

// ═════════════ Tela 2: meu espelho ═════════════
function MeuEspelho() {
  const D = P();
  const dias = D.dias(D.MES, EU);
  const t = D.totaisEspelho(dias);
  return (
    <div className="ptm-screen">
      <div className="ptm-tot">
        <div><small>Trabalhado</small><b>{D.fmtMin(t.trabalhado)}</b></div>
        <div><small>Hora extra</small><b>{D.fmtMin(t.he_diurna + t.he_noturna)}</b></div>
        <div><small>Faltas</small><b>{D.fmtMin(t.falta)}</b></div>
        <div><small>Atrasos</small><b>{D.fmtMin(t.atraso)}</b></div>
      </div>
      <span className="ptm-h">Agosto/2026 · dia a dia</span>
      {dias.filter((d) => !d.folga).slice().reverse().map((d) => (
        <div className={"ptm-dia" + (d.estado === "DIVERGENCIA" ? " diverg" : "")} key={d.dia}>
          <span className="ptm-dia-d"><b>{String(d.dia).padStart(2, "0")}</b><small>{D.DIA_SEMANA[d.dow]}</small></span>
          <span className="ptm-dia-h">
            {d.marcacoes.length ? d.marcacoes.map((m, i) => <i key={i}>{m.hora}</i>) : <i className="off">sem marcação</i>}
          </span>
          <span className="ptm-dia-t">{D.fmtMin(d.trabalhado)}{d.estado === "DIVERGENCIA" && <small>conferir</small>}</span>
        </div>
      ))}
      <p className="ptm-legal">Espelho do mês corrente. O oficial, assinado, sai no fechamento.</p>
    </div>
  );
}

// ═════════════ Tela 3: justificar ═════════════
function Justificar({ onEnviar }) {
  const D = P();
  const [f, setF] = useState({ tipo: "", data: "2026-08-20", dia_todo: false, ini: "", fim: "", just: "" });
  const set = (k) => (e) => setF((o) => ({ ...o, [k]: e.target.type === "checkbox" ? e.target.checked : e.target.value }));
  const erro = !f.tipo ? "Escolha o motivo."
    : (!f.dia_todo && (!f.ini || !f.fim)) ? "Informe o horário (das/às) ou marque Dia todo — o gestor decide pela janela."
    : (!f.dia_todo && f.fim <= f.ini) ? "O fim precisa ser depois do início."
    : (f.just || "").trim().length < 10 ? "Descreva com pelo menos 10 caracteres." : null;
  return (
    <div className="ptm-screen">
      <span className="ptm-h">O que aconteceu</span>
      <div className="ptm-motivos">
        {Object.entries(D.TIPOS_INTERC).map(([k, v]) => (
          <button key={k} className={"ptm-motivo" + (f.tipo === k ? " on" : "")} onClick={() => setF((o) => ({ ...o, tipo: k }))}>{v}</button>
        ))}
      </div>
      <label className="ptm-lbl">Dia</label>
      <input className="ptm-in" type="date" max="2026-08-20" value={f.data} onChange={set("data")} />
      <label className="ptm-check"><input type="checkbox" checked={f.dia_todo} onChange={set("dia_todo")} />Dia todo</label>
      {!f.dia_todo &&
        <div className="ptm-2col">
          <span><label className="ptm-lbl">Das</label><input className="ptm-in" type="time" value={f.ini} onChange={set("ini")} /></span>
          <span><label className="ptm-lbl">Às</label><input className="ptm-in" type="time" value={f.fim} onChange={set("fim")} /></span>
        </div>}
      <label className="ptm-lbl">Justificativa</label>
      <textarea className="ptm-in ptm-ta" rows={4} maxLength={2000} value={f.just} onChange={set("just")}
        placeholder="Ex.: obra do Mercado União sem sinal, marquei ao chegar no galpão…" />
      <button className="ptm-cta" disabled={!!erro} title={erro || ""} onClick={() => { onEnviar(f); setF({ tipo: "", data: "2026-08-20", dia_todo: false, ini: "", fim: "", just: "" }); }}>
        Enviar para aprovação
      </button>
      {erro && <p className="ptm-bloqueio">{erro}</p>}
      <p className="ptm-legal">Vai como rascunho submetido ao gestor. A marcação original não muda.</p>
    </div>
  );
}

// ═════════════ Fila do gestor: marcações mobile a validar ═════════════
function ValidacaoMobile({ pendentes, onDecidir }) {
  const { Card, Tabela, Vazio, Pill, Nota } = U();
  return (
    <>
      <Nota tom="info" titulo="Por que uma fila">
        Fora do geofence a marcação <b>não é recusada</b> — ela entra e fica sinalizada para revisão humana (é o que o serviço faz hoje). Recusar automaticamente puniria o técnico que trabalha na rua.
      </Nota>
      <Card contrato="repp-fila-validacao" icon="shield" titulo="Marcações mobile a validar" sub={"(" + pendentes.filter((p) => p.estado === "PENDENTE").length + " pendentes · últimos 7 dias)"}>
        <Tabela cols={[{ l: "Quando" }, { l: "Colaborador" }, { l: "Tipo" }, { l: "Local" }, { l: "GPS" }, { l: "Selfie (hash)" }, { l: "Estado" }, { l: "Ação", num: true, w: "168px" }]}>
          {pendentes.length === 0 && <Vazio icon="check" colSpan={8}>Nenhuma marcação mobile aguardando validação.</Vazio>}
          {pendentes.map((m) => (
            <tr key={m.id}>
              <td className="mono">{m.quando}<small>NSR {m.nsr}</small></td>
              <td><b>{m.nome}</b><small>{m.device}</small></td>
              <td>{TIPOS.find((t) => t.id === m.tipo)?.label || m.tipo}</td>
              <td><small style={{ color: "var(--text-dim)" }}>{m.local}</small><small className="mono">{m.lat}, {m.lng}</small></td>
              <td><Pill tom={m.accuracy > LIMITES.accuracy_max ? "danger" : m.dentro ? "ok" : "warn"} mono>±{m.accuracy}m</Pill></td>
              <td><span className="pt-hash">{m.hash.slice(0, 16)}…</span></td>
              <td><Pill tom={m.estado === "VALIDADA" ? "ok" : m.estado === "RECUSADA" ? "danger" : "warn"}>{m.estado === "VALIDADA" ? "Validada" : m.estado === "RECUSADA" ? "Recusada" : "A validar"}</Pill></td>
              <td className="num">
                {m.estado === "PENDENTE"
                  ? <span style={{ display: "inline-flex", gap: 6 }}>
                      <button className="pt-btn primary" onClick={() => onDecidir(m.id, "VALIDADA")}>Validar</button>
                      <button className="pt-btn danger" onClick={() => onDecidir(m.id, "RECUSADA")}>Recusar</button>
                    </span>
                  : <span className="pt-dim">—</span>}
              </td>
            </tr>
          ))}
        </Tabela>
      </Card>
    </>
  );
}

// ═════════════ Aba: aparelho + fila lado a lado ═════════════
const PENDENTES0 = [
  { id: 1, nsr: 348802, quando: "19/08 07:58", nome: "Marcos Teixeira", device: "mobile:ad91f2… · Android 15", tipo: "ENTRADA", local: "Obra Mercado União", lat: "-28.3361", lng: "-48.9264", accuracy: 38, hash: hashFake(31), dentro: false, estado: "PENDENTE" },
  { id: 2, nsr: 348809, quando: "19/08 17:12", nome: "Marcos Teixeira", device: "mobile:ad91f2… · Android 15", tipo: "SAIDA", local: "Obra Mercado União", lat: "-28.3359", lng: "-48.9271", accuracy: 44, hash: hashFake(57), dentro: false, estado: "PENDENTE" },
  { id: 3, nsr: 348715, quando: "18/08 08:04", nome: "Joana Lima", device: "mobile:7c02be… · iOS 19", tipo: "ENTRADA", local: "Acme Comércio (visita)", lat: "-28.3402", lng: "-48.9188", accuracy: 512, hash: hashFake(83), dentro: false, estado: "PENDENTE" },
  { id: 4, nsr: 348690, quando: "17/08 21:31", nome: "Felipe Andrade", device: "mobile:b4410a… · Android 14", tipo: "SAIDA", local: "Posto BR — fachada", lat: "-28.3298", lng: "-48.9310", accuracy: 22, hash: hashFake(109), dentro: false, estado: "VALIDADA" },
];

function Mobile({ avisar, rows, setRows }) {
  const D = P();
  const { Card, Nota, Ic } = U();
  const [tela, setTela] = useState("bater");
  const [pendentes, setPendentes] = useState(PENDENTES0);
  const [marcacoes, setMarcacoes] = useState([
    { hora: "07:02", tipo: "ENTRADA", nsr: 348821, foraGeofence: true },
  ]);
  const [gps, setGps] = useState({ local: "Obra Mercado União — Palhoça/SC", accuracy: 38, drift: 4, dentroGeofence: false });

  const marcar = (tipo) => {
    // NSR é sequencial — o próprio módulo audita buraco de sequência (Anexo I).
    const nsr = marcacoes.reduce((mx, m) => Math.max(mx, m.nsr), 348820) + 1;
    const agora = hhmmss(BASE_SEG + 7 + marcacoes.length * 137).slice(0, 5);
    setMarcacoes((ms) => [...ms, { hora: agora, tipo, nsr, foraGeofence: !gps.dentroGeofence }]);
    setPendentes((ps) => !gps.dentroGeofence
      ? [{ id: Date.now(), nsr, quando: "20/08 " + agora, nome: D.colab(EU).nome, device: "mobile:ad91f2… · Android 15", tipo, local: gps.local, lat: "-28.3361", lng: "-48.9264", accuracy: gps.accuracy, hash: hashFake(nsr), dentro: false, estado: "PENDENTE" }, ...ps]
      : ps);
    avisar("Marcação registrada · NSR " + nsr + " · hash " + hashFake(nsr).slice(0, 8) + (gps.dentroGeofence ? "" : " · fora da área, foi para revisão"), gps.dentroGeofence ? "ok" : "warn");
  };

  // Justificar cria intercorrência DE VERDADE no estado do shell — aparece na fila do gestor.
  const justificar = (f) => {
    if (!setRows) { avisar("Justificativa registrada localmente.", "ok"); return; }
    const id = "m" + Date.now().toString(16).slice(-7);
    setRows((rs) => [{
      id, codigo: null, colaborador_config_id: EU, tipo: f.tipo,
      data: f.data.split("-").reverse().join("/"), dia_todo: !!f.dia_todo,
      intervalo_inicio: f.dia_todo ? null : f.ini || null, intervalo_fim: f.dia_todo ? null : f.fim || null,
      estado: "PENDENTE", prioridade: "NORMAL", impacta_apuracao: true, descontar_banco_horas: false,
      justificativa: f.just.trim(), anexo_path: null, solicitante: D.colab(EU).nome,
      created_at: "20/08/2026 " + hhmmss(BASE_SEG).slice(0, 5), aprovador: null, aprovado_em: null, motivo_rejeicao: null,
    }, ...rs]);
    avisar("Justificativa enviada — está na fila de Aprovações como pendente.", "ok");
  };

  const TELAS = [{ id: "bater", label: "Bater ponto" }, { id: "espelho", label: "Meu espelho" }, { id: "justificar", label: "Justificar" }];
  const titulo = tela === "bater" ? "Ponto" : tela === "espelho" ? "Meu espelho" : "Justificar";

  return (
    <>
      <Nota contrato="repp-nota-regras" tom="info" titulo="REP-P — o aparelho do colaborador">
        Mesma regra do balcão: a marcação nasce imutável, com NSR e hash. O que muda é o contexto — <b>selfie</b> (guardamos só o hash), <b>GPS</b> com precisão máxima de {LIMITES.accuracy_max}m, <b>relógio</b> do aparelho conferido contra o servidor ({LIMITES.drift_max}s) e <b>geofence</b> que sinaliza em vez de recusar.
      </Nota>

      <div className="ptm-wrap">
        <div className="ptm-device-col">
          <div className="ptm-seg">
            {TELAS.map((t) => <button key={t.id} className={tela === t.id ? "on" : ""} onClick={() => setTela(t.id)}>{t.label}</button>)}
          </div>
          {window.AndroidDevice
            ? <window.AndroidDevice dark>
                <div className="ptm-top">
                  <div><b>{titulo}</b><small>{D.colab(EU).nome} · matrícula {D.colab(EU).matricula}</small></div>
                  <span className="ptm-badge">REP-P</span>
                </div>
                {tela === "bater" ? <BaterPonto estadoGps={gps} marcacoes={marcacoes} onMarcar={marcar} avisar={avisar} />
                  : tela === "espelho" ? <MeuEspelho />
                  : <Justificar onEnviar={justificar} />}
              </window.AndroidDevice>
            : <div className="ptm-fallback">Frame do aparelho não carregou.</div>}
          <div className="ptm-sim">
            <span className="ptm-sim-h">Simular condição de campo</span>
            <div className="ptm-sim-row">
              <button className="pt-btn" onClick={() => setGps({ local: "Matriz — Rua Osvaldo Cruz, 812", accuracy: 12, drift: 2, dentroGeofence: true })}>Na matriz</button>
              <button className="pt-btn" onClick={() => setGps({ local: "Obra Mercado União — Palhoça/SC", accuracy: 38, drift: 4, dentroGeofence: false })}>Em obra</button>
              <button className="pt-btn" onClick={() => setGps({ local: "Galpão sem sinal", accuracy: 780, drift: 6, dentroGeofence: false })}>GPS ruim</button>
              <button className="pt-btn" onClick={() => setGps((g) => ({ ...g, drift: 96 }))}>Relógio errado</button>
            </div>
          </div>
        </div>

        <div className="ptm-fila-col">
          <ValidacaoMobile pendentes={pendentes} onDecidir={(id, estado) => {
            setPendentes((ps) => ps.map((p) => p.id === id ? { ...p, estado } : p));
            avisar(estado === "VALIDADA" ? "Marcação validada — entra na apuração do dia." : "Marcação recusada — o dia fica em divergência para tratativa.", estado === "VALIDADA" ? "ok" : "warn");
          }} />
        </div>
      </div>
    </>
  );
}

window.PontoMobile = { Mobile };
})();
