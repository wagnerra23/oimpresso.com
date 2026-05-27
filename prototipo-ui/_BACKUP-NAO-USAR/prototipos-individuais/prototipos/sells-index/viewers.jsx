// Viewers — componentes inline para resolver tarefas sem sair da tela
const { useState: useStateV } = React;

// ─── OS: Aprovar arte ───
function TaskViewerOS({ task, onAction }) {
  const d = task.data;
  return (
    <div className="vw">
      <div className="vw-grid">
        <section className="vw-card">
          <h3>Arte enviada</h3>
          <div className="vw-art">
            <div className="vw-art-thumb">
              <I.folder size={28}/>
            </div>
            <div className="vw-art-meta">
              <b>{d.art.filename}</b>
              <small>{d.art.size} · v{d.art.version}</small>
              <div className="vw-art-actions">
                <button className="vw-btn ghost"><I.search size={13}/> Visualizar</button>
                <button className="vw-btn ghost">Baixar</button>
              </div>
            </div>
          </div>
        </section>

        <section className="vw-card">
          <h3>Pedido</h3>
          <dl className="vw-dl">
            <dt>OS</dt><dd className="mono">{d.os}</dd>
            <dt>Cliente</dt><dd>{d.client}</dd>
            <dt>Contato</dt><dd>{d.contact}</dd>
            <dt>Produto</dt><dd>{d.product}</dd>
            <dt>Etapa</dt><dd><span className="vw-stage">{d.stage}</span></dd>
            <dt>Prazo</dt><dd className={task.urgent ? "urgent" : ""}>{d.deadline}</dd>
          </dl>
        </section>
      </div>

      {d.history && d.history.length > 0 && (
        <section className="vw-card">
          <h3>Histórico</h3>
          <ul className="vw-hist">
            {d.history.map((h, i) => (
              <li key={i}><b>{h.who}</b> <span className="t">{h.when}</span> — {h.what}</li>
            ))}
          </ul>
        </section>
      )}

      <section className="vw-card">
        <h3>Conversa vinculada</h3>
        <div className="vw-thread-mini">
          <div className="mini-msg them">
            <span className="mini-av av-1">JL</span>
            <div className="mini-bub">Subi a v3 com sangramento ajustado. Pode conferir?</div>
          </div>
          <div className="mini-msg me">
            <div className="mini-bub me">Recebi, vou validar agora.</div>
          </div>
          <input className="mini-input" placeholder="Responder na conversa…"/>
        </div>
      </section>

      <div className="vw-actions">
        <button className="vw-btn primary" onClick={() => onAction("approve")}>
          <I.check size={14}/> Aprovar arte
        </button>
        <button className="vw-btn danger" onClick={() => onAction("reject")}>Reprovar</button>
        <button className="vw-btn ghost" onClick={() => onAction("snooze")}>Adiar</button>
        <span className="vw-spacer"/>
        <button className="vw-btn ghost">Abrir no módulo →</button>
      </div>
    </div>
  );
}

// ─── CRM: Ligar para lead ───
function TaskViewerCRM({ task, onAction }) {
  const d = task.data;
  return (
    <div className="vw">
      <div className="vw-grid">
        <section className="vw-card">
          <h3>Contato</h3>
          <div className="vw-contact">
            <span className="vw-contact-av av-2">{d.lead.split(" ").map(p=>p[0]).slice(0,2).join("")}</span>
            <div>
              <b>{d.lead}</b>
              <small>{d.company}</small>
            </div>
          </div>
          <div className="vw-contact-row">
            <I.phone size={14}/>
            <a className="mono">{d.phone}</a>
            <button className="vw-btn primary sm">Ligar</button>
          </div>
          <div className="vw-contact-row">
            <I.chat size={14}/>
            <span className="mono">{d.whatsapp}</span>
            <button className="vw-btn ghost sm">WhatsApp</button>
          </div>
        </section>

        <section className="vw-card">
          <h3>Última interação</h3>
          <p className="vw-p">{d.lastTouch}</p>
          {d.notes?.length > 0 && (
            <>
              <h4 className="vw-sub">Notas</h4>
              <ul className="vw-notes">{d.notes.map((n,i) => <li key={i}>{n}</li>)}</ul>
            </>
          )}
        </section>
      </div>

      <section className="vw-card">
        <h3>Registrar resultado da chamada</h3>
        <div className="vw-radio-group">
          {["Atendeu — falamos", "Atendeu — pediu retorno", "Não atendeu", "Caixa postal"].map(o => (
            <label key={o} className="vw-radio">
              <input type="radio" name="callresult"/> {o}
            </label>
          ))}
        </div>
        <textarea className="vw-textarea" placeholder="Observação rápida…" rows={2}/>
      </section>

      <div className="vw-actions">
        <button className="vw-btn primary" onClick={() => onAction("done")}>
          <I.check size={14}/> Concluir tarefa
        </button>
        <button className="vw-btn ghost" onClick={() => onAction("snooze")}>Adiar p/ amanhã</button>
        <span className="vw-spacer"/>
        <button className="vw-btn ghost">Abrir lead no CRM →</button>
      </div>
    </div>
  );
}

// ─── PNT: Justificar marcação ───
function TaskViewerPonto({ task, onAction }) {
  const d = task.data;
  const [reason, setReason] = useStateV("");
  return (
    <div className="vw">
      <section className="vw-card">
        <h3>{d.date}</h3>
        <div className="vw-pnt-grid">
          {d.recorded.map((r, i) => (
            <div key={i} className={`vw-pnt-cell ${r.missing ? "miss" : ""}`}>
              <small>{r.label}</small>
              <b>{r.time}</b>
            </div>
          ))}
        </div>
      </section>

      <section className="vw-card">
        <h3>Justificativa</h3>
        <div className="vw-chips">
          {d.suggestions.map(s => (
            <button key={s} className={`vw-chip ${reason===s?"on":""}`} onClick={() => setReason(s)}>{s}</button>
          ))}
        </div>
        <textarea className="vw-textarea" placeholder="Detalhe (opcional)" rows={3} defaultValue={reason}/>
        <label className="vw-check">
          <input type="checkbox"/> Anexar foto ou documento de comprovação
        </label>
      </section>

      <div className="vw-actions">
        <button className="vw-btn primary" onClick={() => onAction("submit")}>
          <I.check size={14}/> Enviar justificativa
        </button>
        <button className="vw-btn ghost" onClick={() => onAction("snooze")}>Lembrar mais tarde</button>
        <span className="vw-spacer"/>
        <button className="vw-btn ghost">Abrir Ponto WR2 →</button>
      </div>
    </div>
  );
}

// ─── FIN: Aprovar boleto ───
function TaskViewerFinanceiro({ task, onAction }) {
  const d = task.data;
  return (
    <div className="vw">
      <section className="vw-card hi">
        <div className="vw-money">
          <small>VALOR A PAGAR</small>
          <b>{d.amount}</b>
          <span className={`vw-due ${task.urgent?"urgent":""}`}>vence {d.due}</span>
        </div>
      </section>

      <div className="vw-grid">
        <section className="vw-card">
          <h3>Detalhes</h3>
          <dl className="vw-dl">
            <dt>NF</dt><dd className="mono">{d.nf}</dd>
            <dt>Fornecedor</dt><dd>{d.supplier}</dd>
            <dt>Categoria</dt><dd>{d.category}</dd>
            <dt>Conta</dt><dd>{d.account}</dd>
            <dt>Referência</dt><dd>{d.ref}</dd>
          </dl>
        </section>

        <section className="vw-card">
          <h3>Anexo</h3>
          <div className="vw-art">
            <div className="vw-art-thumb"><I.folder size={28}/></div>
            <div className="vw-art-meta">
              <b>{d.attached}</b>
              <div className="vw-art-actions">
                <button className="vw-btn ghost"><I.search size={13}/> Visualizar</button>
                <button className="vw-btn ghost">Baixar</button>
              </div>
            </div>
          </div>
        </section>
      </div>

      <div className="vw-actions">
        <button className="vw-btn primary" onClick={() => onAction("approve")}>
          <I.check size={14}/> Aprovar pagamento
        </button>
        <button className="vw-btn danger" onClick={() => onAction("reject")}>Recusar</button>
        <button className="vw-btn ghost" onClick={() => onAction("snooze")}>Adiar</button>
        <span className="vw-spacer"/>
        <button className="vw-btn ghost">Abrir no Financeiro →</button>
      </div>
    </div>
  );
}

const VIEWERS = {
  os_aprovar_arte:    TaskViewerOS,
  crm_ligar:          TaskViewerCRM,
  pnt_justificar:     TaskViewerPonto,
  fin_aprovar_boleto: TaskViewerFinanceiro,
};

window.VIEWERS = VIEWERS;
