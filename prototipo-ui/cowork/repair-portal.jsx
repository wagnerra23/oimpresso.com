// repair-portal.jsx — O5 do refino: portal público de consulta de OS, portado de
// customer_repair/{index,repair_details,repair_activities}.blade.php + rota /repair-status
// (fora do auth, throttle:30,1 por IP — booster D8.a anti-scraping R-REPA-008).
// Renderiza a tela COMO O CLIENTE VÊ, dentro de um quadro, porque ela não mora no shell:
// é layout próprio (repair::layouts.repair_status). Expõe window.RepPortal.
(() => {
const { useState } = React;
const R = () => window.RepData;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const val = (e) => (e && e.target ? e.target.value : e);

// O que o cliente pode ver: status, datas, equipamento. Nunca senha, custo interno,
// comentário do técnico ou nome do técnico (o legado só devolve o bloco de status).
function Resultado({ f }) {
  const D = R(); const m = D.modeloDe(f.modelo);
  const st = D.statusDe(f.status);
  const atividades = (D.ATIVIDADES[f.id] || []).slice().reverse();
  return (
    <div className="rp-res">
      <div className="rp-res-h">
        <div>
          <b className="mono">{f.os}</b>
          <small>{m.marca} {m.nome} · série {f.serie}</small>
        </div>
        <span className="rp-res-st" style={{ "--st": st.cor }}><i />{st.nome}</span>
      </div>
      <div className="rp-res-grid">
        <div><label>Recebido em</label><span>{D.d2(f.criado)}</span></div>
        <div><label>Entrega prevista</label><span>{D.d2(f.entrega)}</span></div>
        <div><label>Orçamento</label><span>{f.custo ? D.fmt(f.custo) : "em avaliação"}</span></div>
        <div><label>Garantia do serviço</label><span>90 dias</span></div>
      </div>
      <h4>Andamento</h4>
      <ol className="rp-tl">
        {atividades.length ? atividades.map((a, i) =>
          <li key={i}><span className="d" /><b>{a.ev}</b><small>{D.d2(a.dia)} · {a.hora}</small></li>)
          : <li><span className="d" /><b>Equipamento recebido</b><small>{D.d2(f.criado)}</small></li>}
      </ol>
      <p className="rp-nota">Dúvida no andamento? Fale com a loja informando o número {f.os}.</p>
    </div>
  );
}

function PortalConsulta({ folhas, avisar }) {
  const D = R();
  const { Alert, Button, Input, Select, EmptyState, Switch } = DS();
  const [tipo, setTipo] = useState("job_sheet_no");
  const [num, setNum] = useState("");
  const [serie, setSerie] = useState("");
  const [porCelular, setPorCelular] = useState(false);
  const [res, setRes] = useState(null);
  const [erro, setErro] = useState(null);
  const [tentativas, setTentativas] = useState(0);

  const buscar = () => {
    setTentativas((n) => n + 1);
    if (tentativas >= 30) { setErro("Limite de 30 consultas por minuto atingido (throttle por IP). Aguarde e tente de novo."); setRes(null); return; }
    if (!num.trim()) { setErro("Informe o número da folha, da fatura ou o celular."); setRes(null); return; }
    const q = num.trim().toLowerCase();
    const achou = folhas.find((f) => {
      const alvo = tipo === "invoice_no"
        ? (D.REPAROS.find((r) => r.folha === f.id) || {}).fatura || ""
        : f.os;
      return String(alvo).toLowerCase() === q || String(alvo).toLowerCase().endsWith(q);
    });
    if (!achou) { setErro("Informação de reparo inválida! Confira o número e o número de série."); setRes(null); return; }
    if (serie.trim() && achou.serie.toLowerCase() !== serie.trim().toLowerCase()) {
      setErro("Informação de reparo inválida! O número de série não confere com esta OS."); setRes(null); return;
    }
    setErro(null); setRes(achou);
  };

  return (
    <div className="rp-wrap">
      {Alert && <Alert tone="info" title="Esta tela é pública — não passa pelo login">
        Rota <b className="mono">/repair-status</b> com <b className="mono">throttle:30,1</b> por IP (anti-scraping R-REPA-008). O cliente precisa do número da OS <b>e</b> do número de série: um só não abre o cadastro de ninguém.
      </Alert>}
      <div className="rp-frame">
        <div className="rp-frame-bar"><span className="mono">oimpresso.com/repair-status</span></div>
        <div className="rp-page">
          <div className="rp-card">
            <h3>Consultar reparo</h3>
            <p>Acompanhe seu equipamento sem ligar pra loja.</p>
            <div className="rp-form">
              {Select && <Select label="Buscar por" value={tipo} onChange={(e) => setTipo(val(e))}
                options={[
                  { value: "job_sheet_no", label: "Nº da folha de OS" },
                  { value: "invoice_no", label: "Nº da fatura" },
                  ...(porCelular ? [{ value: "mobile_num", label: "Celular cadastrado" }] : []),
                ]} />}
              {Input && <Input label="Número" value={num} onChange={(e) => setNum(val(e))}
                placeholder={tipo === "invoice_no" ? "FAT-9921" : "JS-2026-0412"} />}
              {Input && <Input label="Número de série" value={serie} onChange={(e) => setSerie(val(e))}
                placeholder="Como está na etiqueta" help="Confere que a OS é sua" />}
              {Button && <Button variant="primary" onClick={buscar}>Consultar</Button>}
            </div>
            {erro && Alert && <Alert tone="danger" title="Não encontramos">{erro}</Alert>}
            {res && <Resultado f={res} />}
            {!res && !erro && EmptyState &&
              <EmptyState variant="first" title="Nada consultado ainda"
                description="Digite o número da OS que está no seu recibo e o número de série do equipamento." />}
          </div>
        </div>
      </div>
      <div className="rp-cfg">
        {Switch && <Switch checked={porCelular} onChange={setPorCelular}
          label="Permitir consulta por celular"
          sublabel="config('repair.enable_repair_check_using_mobile_num') — desligado por padrão: celular sozinho é dado pessoal exposto (LGPD Art. 7º)" />}
        {Button && <Button variant="ghost" onClick={() => avisar && avisar("Link do portal copiado pra colar no WhatsApp da loja.", "ok")}>Copiar link do portal</Button>}
      </div>
    </div>
  );
}

window.RepPortal = { PortalConsulta };
})();
