// os-acompanhamento.jsx — UMA peça para "acompanhar uma OS", usada nas duas portas do cliente:
//   · página pública do link com código (repair-portal.jsx, ConsultaOs /os/{codigo})
//   · portal do contato logado, aba Assistência (crm-portal.jsx)
// Antes eram duas cópias. A Page real também deve ter um componente só (Crm 25 + Repair 06).
// Mostra só a whitelist do ConsultaOs (UC-COS-04) + loja (D-LOJA). Orçamento: só a SITUAÇÃO, nunca o
// valor (ADR ARQ-0002). Aprovar pelo link: D-APROVAR [W] 2026-10-15 — aparece com `aprovar` (config da loja, ligado por padrão).
// os = { os, eq, status:{nome,cor}, fase, fases[], prev, recebido, serie, orc:'avaliacao'|'aguardando'|'aprovado'|'recusado',
//        andamento:[{ev,quando,nota?}], codigo, prontoHa (dias, se pronto), retirarAte? }
// Expõe window.OsAcompanhamento.
(() => {
const { useState } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <span aria-hidden="true" style={{ display: "inline-flex" }}><F size={size} /></span> : null; };
const ORC = { avaliacao: "em avaliação", aguardando: "aguardando sua aprovação", aprovado: "aprovado", recusado: "recusado — devolução sem reparo" };

function OsAcompanhamento({ os, variante = "publico", aprovar = false, onAprovar, onCopiar, rodape }) {
  const { FsmStepper, Alert, Button } = DS();
  const [orc, setOrc] = useState(os.orc);
  const [conf, setConf] = useState(null);
  const pronto = os.fase >= os.fases.length - 1;
  const steps = os.fases.map((l, i) => ({ label: l, state: i < os.fase ? "done" : i === os.fase ? (pronto ? "term" : "current") : "todo" }));
  const decidir = (sim) => { setOrc(sim ? "aprovado" : "recusado"); setConf(null); onAprovar && onAprovar(sim); };
  const H = variante === "publico" ? "h1" : "h2";
  return (
    <article className={"osc osc--" + variante} aria-labelledby={"osc-" + os.os} data-contract="os-acompanhamento">
      <header className="osc-h">
        <div>
          <div className="osc-eyebrow">Ordem de serviço</div>
          <H className="osc-n" id={"osc-" + os.os}>{os.os}</H>
          <div className="osc-eq">{os.eq}</div>
        </div>
        <span className="osc-st" style={{ "--st": os.status.cor || "var(--accent)" }}><i aria-hidden="true" />{os.status.nome}</span>
      </header>

      {os.entregue && Alert && <Alert tone="info" title={"Entregue em " + os.entregue.em}>Garantia do serviço até {os.entregue.garantiaAte}. Se o defeito voltar, fale com a loja e informe o código abaixo.</Alert>}
      {pronto && !os.entregue && Alert && <Alert tone={os.prontoHa >= 7 ? "warn" : "success"} title={os.prontoHa ? `Pronto para retirada há ${os.prontoHa} ${os.prontoHa === 1 ? "dia" : "dias"}` : "Pronto para retirada"}>
        Retire no balcão, de segunda a sexta, das 8h às 18h. Traga o recibo ou este link.{os.retirarAte ? ` A loja guarda o equipamento até ${os.retirarAte}.` : ""}
      </Alert>}
      {!pronto && <div className="osc-prev"><span className="osc-eyebrow">Previsão de entrega</span><b>{os.prev}</b></div>}

      {orc === "aguardando" && (
        <section className="osc-orc" aria-label="Orçamento" data-contract="os-orcamento">
          <div><b>Orçamento aguardando sua aprovação</b><span>O valor e o laudo foram enviados pela loja no WhatsApp e no e-mail.</span></div>
          {aprovar && Button
            ? (conf
              ? <div className="osc-bar" role="group" aria-label="Confirmar decisão"><span>{conf === "sim" ? "Aprovar e liberar o reparo?" : "Recusar e receber o equipamento sem reparo?"}</span><Button onClick={() => setConf(null)}>Voltar</Button><Button variant={conf === "sim" ? "primary" : "danger"} onClick={() => decidir(conf === "sim")}>{conf === "sim" ? "Sim, aprovar" : "Sim, recusar"}</Button></div>
              : <div className="osc-bar"><Button onClick={() => setConf("nao")}>Recusar</Button><Button variant="primary" onClick={() => setConf("sim")}>Aprovar orçamento</Button></div>)
            : <span className="osc-mute">Para aprovar, responda a mensagem da loja ou ligue para o balcão.</span>}
        </section>
      )}

      {FsmStepper && <div className="osc-fsm" aria-label={"Etapa: " + os.fases[os.fase]}>
        <div className="osc-full"><FsmStepper variant="full" hue={295} steps={steps} /></div>
        <div className="osc-inline"><FsmStepper variant="inline" hue={295} steps={steps} /></div>
      </div>}

      <dl className="osc-kv">
        <div><dt>Recebido em</dt><dd>{os.recebido}</dd></div>
        {os.serie && <div><dt>Nº de série</dt><dd className="mono">{os.serie}</dd></div>}
        <div><dt>Orçamento</dt><dd>{ORC[orc] || "—"}</dd></div>
      </dl>

      <section aria-label="Andamento">
        <h3 className="osc-h3">Andamento</h3>
        <ol className="osc-tl">
          {os.andamento.map((a, i) => <li key={i}><b>{a.ev}</b><time>{a.quando}</time>{a.nota && <span>{a.nota}</span>}</li>)}
        </ol>
      </section>

      <footer className="osc-foot">
        <span>Código de acompanhamento <b className="mono">{os.codigo}</b></span>
        {onCopiar && Button && <Button size="sm" onClick={onCopiar}><Ic name="copy" size={13} /> Copiar link</Button>}
      </footer>
      {rodape}
    </article>
  );
}

window.OsAcompanhamento = OsAcompanhamento;
})();
