// crm-blade-forms.jsx — ONDA 1: os formulários e telas do módulo Crm que o primeiro import
// resolveu com atalho. Tradução 1:1 dos blades:
//   lead/show.blade.php (+ partial/lead_info, partial/lead_schedule) ...... FichaLead
//   campaign/create.blade.php + edit + show ............................... CampanhaForm
//   schedule/edit.blade.php .............................................. AcompanhamentoForm (modo editar)
//   schedule_log/{index,create,edit,show}.blade.php ...................... LogsAcompanhamento
//   schedule/create_advance_follow_up.blade.php .......................... AntecipadoForm
//   schedule/create_recursive_follow_up.blade.php ........................ RecorrenteForm
// Consome window.CBD (domínio) + window.CBUI (peças) + window.PBUI. Expõe window.CrmBladeForms.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const D = () => window.CBD || {};
const U = () => window.CBUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };

// Faturas em aberto (getInvoicesForFollowUp) — alimenta o acompanhamento antecipado por pagamento.
const FATURAS = [
  { id: "POS-2026-0482", cli: "Rota Livre Transportes", status: "partial", saldo: 1620, venc: "há 3 dias" },
  { id: "POS-2026-0479", cli: "Agência Norte", status: "due", saldo: 1875.4, venc: "vence amanhã" },
  { id: "POS-2026-0475", cli: "Supermercado Bom Dia", status: "overdue", saldo: 3490, venc: "há 12 dias" },
  { id: "POS-2026-0470", cli: "Prefeitura de Jaú", status: "due", saldo: 12480, venc: "vence em 6 dias" },
];
const PGTO_FILTRO = [{ id: "all", name: "Todos" }, { id: "due", name: "Devido" }, { id: "partial", name: "Parcial" }, { id: "overdue", name: "Vencido" }];
const PEDIDO_FILTRO = [{ id: "has_transactions", name: "Tem transações" }, { id: "has_no_transactions", name: "Não tem transações" }];
const LOG_TIPOS = [{ id: "call", name: "Ligação" }, { id: "sms", name: "SMS" }, { id: "meeting", name: "Encontro" }, { id: "email", name: "E-mail" }];
// followup_tags do ScheduleController — as tags que o título/descrição aceitam.
const TAGS = { invoice: ["{invoice_no}", "{due_amount}", "{due_date}"], trans_days: ["{days}", "{last_transaction_date}"], contact_name: ["{contact_name}", "{business_name}"] };
const TAGS_CAMPANHA = ["{contact_name}", "{business_name}", "{invoice_no}", "{due_amount}", "{ledger_link}"];

// Logs por acompanhamento (crm_schedule_logs) — semeados pra a lista não nascer vazia.
const LOGS_INI = {
  103: [{ id: 1, assunto: "Cliente pediu 6% de desconto", tipo: "call", inicio: "11:02", fim: "11:19", desc: "Aceita fechar hoje se aprovarmos 6%. Encaminhado ao Wagner.", status: "completed", por: "Larissa Prado" }],
  107: [{ id: 2, assunto: "Boleto reenviado por WhatsApp", tipo: "sms", inicio: "08:35", fim: "08:36", desc: "Confirmou recebimento, paga na sexta.", status: "open", por: "Eliana Souza" }],
};

// ─────────── Peça: bloco de notificação (allow_notification dos 3 blades) ───────────
function Notificacao({ v, set }) {
  const { Fld, Sel } = UI();
  const { NOTIFICAR } = D();
  return (
    <>
      <label className="pb-chk" style={{ marginTop: 12 }}>
        <input type="checkbox" checked={!!v.notificar} onChange={(e) => set({ ...v, notificar: e.target.checked })} />
        <b>Enviar notificação<small>A notificação automática sai no tempo escolhido antes do início do acompanhamento.</small></b>
      </label>
      {v.notificar &&
        <div className="pb-grid c3" style={{ marginTop: 10 }}>
          <Fld label="Notificar via" req>
            <div style={{ display: "flex", gap: 14 }}>
              <label className="pb-chk"><input type="checkbox" checked={!!v.sms} onChange={(e) => set({ ...v, sms: e.target.checked })} /><b>SMS</b></label>
              <label className="pb-chk"><input type="checkbox" checked={v.mail !== false} onChange={(e) => set({ ...v, mail: e.target.checked })} /><b>E-mail</b></label>
            </div>
          </Fld>
          <Fld label="Notificar antes" req><input className="num" value={v.antes ?? 1} onChange={(e) => set({ ...v, antes: e.target.value })} /></Fld>
          <Fld label="Unidade"><Sel value={v.unidade || "hour"} onChange={(x) => set({ ...v, unidade: x })} options={NOTIFICAR} vazio="Selecione" /></Fld>
        </div>}
    </>
  );
}
const Tags = ({ lista, ajuda = "Tags disponíveis" }) => (
  <p className="pb-help" style={{ marginTop: 8 }}><b>{ajuda}:</b> <span style={{ color: "var(--accent)", fontFamily: "var(--font-mono)" }}>{lista.join(", ")}</span></p>
);

// ─────────── 1. Ficha do lead (lead/show.blade.php) ───────────
function FichaLead({ lead, onVoltar, avisar, onConverter, onNovoAcompanhamento }) {
  const { Widget, Fld, Sel } = UI();
  const { Mini, Pill, Badge, Grade } = U();
  const { LEADS, FOLLOWUPS, FONTES, FASES, STATUS, TIPOS, CATEGORIAS, rotulo, TOM } = { ...D(), TOM: U().TOM || {} };
  const [aba, setAba] = useState("acompanhamentos");
  const [atual, setAtual] = useState(lead);
  const [notas, setNotas] = useState([
    { id: 1, tipo: "nota", titulo: "Medidas conferidas no local", quando: "há 2 dias", por: "Larissa Prado", texto: "Fachada 3,20 × 1,10 m. Cliente quer lona translúcida com iluminação." },
    { id: 2, tipo: "documento", titulo: "foto-fachada-atual.jpg", quando: "há 2 dias", por: "Larissa Prado", texto: "1,4 MB · JPEG" },
  ]);
  const [nova, setNova] = useState(null);

  const daLista = FOLLOWUPS.filter((s) => s.contato === atual.nome);
  const cols = [
    { key: "titulo", label: "Título", width: 220 },
    { key: "inicio", label: "Início", width: 150, mono: true },
    { key: "status", label: "Status", width: 126 },
    { key: "tipo", label: "Tipo", width: 130 },
    { key: "cat", label: "Categoria", width: 140 },
    { key: "quem", label: "Atribuído a", width: 170 },
  ];
  const rows = daLista.map((s) => ({
    id: s.id, titulo: s.titulo, inicio: s.inicio,
    status: <Pill tom={TOM[s.status]}>{rotulo(STATUS, s.status)}</Pill>,
    tipo: rotulo(TIPOS, s.tipo), cat: rotulo(CATEGORIAS, s.cat), quem: s.quem.join(", "),
  }));

  return (
    <>
      <div className="pb-filters-h">
        <button className="os-btn sm ghost" onClick={onVoltar}>← Todos os leads</button>
        <div className="sp" />
        <div style={{ minWidth: 260 }}>
          <Fld label="Trocar de lead">
            <Sel value={atual.nome} onChange={(v) => setAtual(LEADS.find((l) => l.nome === v) || atual)} options={LEADS.map((l) => ({ id: l.nome, name: l.nome }))} />
          </Fld>
        </div>
      </div>
      <Widget contrato="crm-lead-info" titulo="Informação do lead"
        nota={rotulo(FONTES, atual.fonte) + " · " + rotulo(FASES, atual.fase)}>
        <div className="pb-grid c2">
          <Mini rows={[["Nome", <b>{atual.nome}</b>], ["ID do contato", atual.cod], ["Tipo", <Badge kind="tipo" value={atual.tipo} />], ["CNPJ / CPF", atual.doc], ["Celular", atual.cel], ["E-mail", atual.email || "—"]]} />
          <Mini rows={[["Endereço", atual.end], ["Fonte", rotulo(FONTES, atual.fonte)], ["Estágio de vida", <Pill tom={TOM[atual.fase]}>{rotulo(FASES, atual.fase)}</Pill>], ["Atribuído a", atual.quem], ["Último acompanhamento", <Badge kind="frescor" value={atual.ultTom} rel={atual.ult} />], ["Adicionado em", atual.criado]]} />
        </div>
        <div className="pb-filters-h" style={{ marginTop: 14 }}>
          <div className="sp" />
          <button className="os-btn sm" onClick={() => onNovoAcompanhamento && onNovoAcompanhamento(atual)}><Ic name="plus" size={12} /> Adicionar acompanhamento</button>
          <button className="os-btn sm primary" onClick={() => onConverter && onConverter(atual)}>Converter para cliente</button>
        </div>
      </Widget>
      <Widget contrato="crm-lead-abas" titulo={atual.nome} flush>
        <nav className="cli-moduletopnav" aria-label="Abas da ficha do lead" style={{ padding: "0 12px" }}>
          <button className={"cli-moduletopnav-tab " + (aba === "acompanhamentos" ? "active" : "")} onClick={() => setAba("acompanhamentos")}>Acompanhamento</button>
          <button className={"cli-moduletopnav-tab " + (aba === "docs" ? "active" : "")} onClick={() => setAba("docs")}>Documentos e notas</button>
          <button className={"cli-moduletopnav-tab " + (aba === "pessoas" ? "active" : "")} onClick={() => setAba("pessoas")}>Pessoas de contato</button>
        </nav>
        {aba === "acompanhamentos" && <Grade columns={cols} rows={rows} altura={240} />}
        {aba === "docs" &&
          <div className="pb-widget-b">
            <div className="pb-filters-h" style={{ marginBottom: 10 }}>
              <span className="pb-help">Notas e arquivos ficam no contato — visíveis para quem atender depois.</span>
              <button className="os-btn sm primary" onClick={() => setNova({ tipo: "nota" })}><Ic name="plus" size={12} /> Adicionar nota</button>
            </div>
            {notas.map((n) => (
              <div key={n.id} style={{ borderTop: "1px solid var(--border-2)", padding: "10px 0" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                  <Ic name={n.tipo === "nota" ? "quote" : "paperclip"} size={13} />
                  <b style={{ fontSize: 12.5 }}>{n.titulo}</b>
                  <span className="pb-help" style={{ marginLeft: "auto" }}>{n.por} · {n.quando}</span>
                </div>
                <p className="pb-help" style={{ marginTop: 4 }}>{n.texto}</p>
              </div>
            ))}
            {nova &&
              <div className="pb-grid" style={{ marginTop: 12 }}>
                <Fld label="Título" req span={3}><input value={nova.titulo || ""} onChange={(e) => setNova({ ...nova, titulo: e.target.value })} /></Fld>
                <Fld label="Nota" span={3}><textarea value={nova.texto || ""} onChange={(e) => setNova({ ...nova, texto: e.target.value })} /></Fld>
                <div className="pb-filters-h" style={{ gridColumn: "span 3" }}>
                  <div className="sp" />
                  <button className="os-btn sm" onClick={() => setNova(null)}>Cancelar</button>
                  <button className="os-btn sm primary" onClick={() => {
                    setNotas((ns) => [{ id: Date.now(), tipo: "nota", titulo: nova.titulo || "Nota sem título", texto: nova.texto || "", quando: "agora", por: "Wagner Ramos" }, ...ns]);
                    setNova(null); avisar("Nota salva no contato.", "ok");
                  }}>Salvar</button>
                </div>
              </div>}
          </div>}
        {aba === "pessoas" &&
          <div className="pb-widget-b">
            <Mini head={["Pessoa", "Papel"]} rows={[[atual.cf1 || "—", "Contato principal"], ["Financeiro", "Recebe boleto e NF-e"]]} />
            <div className="pb-filters-h" style={{ marginTop: 12 }}>
              <div className="sp" />
              <button className="os-btn sm" onClick={() => avisar("Cadastro de pessoa de contato aberto.", "ok")}><Ic name="plus" size={12} /> Adicionar pessoa de contato</button>
            </div>
          </div>}
      </Widget>
    </>
  );
}

// ─────────── 2. Campanha (campaign/create + edit) ───────────
function CampanhaForm({ campanha, onVoltar, avisar }) {
  const { Widget, Fld, Sel } = UI();
  const { LEADS, USUARIOS } = D();
  const editar = !!campanha;
  const [v, setV] = useState(campanha
    ? { ...campanha, para: "contact", alvos: [] }
    : { tipo: "email", para: "", alvos: [], dias: "", atividade: "" });
  const alvoLista = v.para === "lead" ? LEADS.filter((l) => l.fase !== "cliente") : v.para === "customer" ? LEADS.filter((l) => l.fase === "cliente") : LEADS;
  const marcar = (nome) => setV({ ...v, alvos: v.alvos.includes(nome) ? v.alvos.filter((x) => x !== nome) : [...v.alvos, nome] });

  return (
    <>
      <div className="pb-filters-h">
        <button className="os-btn sm ghost" onClick={onVoltar}>← Todas as campanhas</button>
      </div>
      <Widget contrato="crm-campanha-form" titulo={editar ? "Editar campanha" : "Criar campanha"}>
        <div className="pb-grid c3">
          <Fld label="Nome da campanha" req span={2}><input value={v.nome || ""} onChange={(e) => setV({ ...v, nome: e.target.value })} placeholder="Aniversariantes de setembro" /></Fld>
          <Fld label="Tipo de campanha" req><Sel value={v.tipo} onChange={(x) => setV({ ...v, tipo: x })} options={[{ id: "sms", name: "SMS" }, { id: "email", name: "E-mail" }]} vazio="Selecione" /></Fld>
          <Fld label="Para" req><Sel value={v.para} onChange={(x) => setV({ ...v, para: x, alvos: [] })} options={[{ id: "customer", name: "Clientes" }, { id: "lead", name: "Leads" }, { id: "transaction_activity", name: "Atividade de transação" }, { id: "contact", name: "Contato" }]} vazio="Selecione" /></Fld>
          {v.para === "transaction_activity" &&
            <>
              <Fld label="Atividade de transação" req><Sel value={v.atividade} onChange={(x) => setV({ ...v, atividade: x })} options={[{ id: "has_transactions", name: "Tem transações" }, { id: "has_no_transactions", name: "Não tem transações" }]} vazio="Selecione" /></Fld>
              <Fld label="No período (dias)" req><input className="num" value={v.dias} onChange={(e) => setV({ ...v, dias: e.target.value })} placeholder="0" /></Fld>
            </>}
          {(v.para === "customer" || v.para === "lead" || v.para === "contact") &&
            <Fld label={v.para === "lead" ? "Leads" : v.para === "customer" ? "Clientes" : "Contatos"} req span={2}>
              <div className="pb-tags" style={{ maxHeight: 118, overflow: "auto" }}>
                {alvoLista.map((l) => (
                  <label key={l.id} className="pb-chk" style={{ flex: "0 0 46%" }}>
                    <input type="checkbox" checked={v.alvos.includes(l.nome)} onChange={() => marcar(l.nome)} /><b>{l.nome}</b>
                  </label>
                ))}
              </div>
            </Fld>}
        </div>
        <div className="pb-filters-h" style={{ marginTop: 6 }}>
          <span className="pb-help">{v.alvos.length ? v.alvos.length + " destinatário(s) marcado(s)" : "Nenhum destinatário marcado."}</span>
          <div className="sp" />
          <button className="os-btn sm ghost" onClick={() => setV({ ...v, alvos: alvoLista.map((l) => l.nome) })}>Selecionar todos</button>
          <button className="os-btn sm ghost" onClick={() => setV({ ...v, alvos: [] })}>Limpar seleção</button>
        </div>
      </Widget>
      <Widget contrato="crm-campanha-corpo" titulo={v.tipo === "sms" ? "Corpo do SMS" : "Assunto e corpo do e-mail"}>
        <div className="pb-grid">
          {v.tipo !== "sms" && <Fld label="Assunto" req span={3}><input value={v.assunto || ""} onChange={(e) => setV({ ...v, assunto: e.target.value })} /></Fld>}
          <Fld label={v.tipo === "sms" ? "Corpo do SMS" : "Corpo do e-mail"} req span={3}><textarea value={v.corpo || ""} onChange={(e) => setV({ ...v, corpo: e.target.value })} /></Fld>
        </div>
        <Tags lista={TAGS_CAMPANHA} />
        <div className="pb-filters-h" style={{ marginTop: 12 }}>
          <div className="sp" />
          <button className="os-btn sm" onClick={() => { avisar("Campanha salva como rascunho.", "ok"); onVoltar(); }}>Rascunho</button>
          <button className="os-btn sm primary" onClick={() => {
            if (!v.nome || !v.para) return avisar("Preencha nome e para quem vai a campanha.", "warn");
            avisar("Campanha salva e notificação enviada" + (v.alvos.length ? " para " + v.alvos.length + " contato(s)." : "."), "ok");
            onVoltar();
          }}><Ic name="send" size={12} /> Enviar notificação</button>
        </div>
      </Widget>
    </>
  );
}

// ─────────── 3. Acompanhamento — modal de editar (schedule/edit.blade.php) ───────────
function AcompanhamentoForm({ valor, onFechar, onSalvar, avisar }) {
  const { Fld, Sel, Modal } = UI();
  const { LEADS, STATUS, TIPOS, CATEGORIAS, USUARIOS } = D();
  const [v, setV] = useState({ ...valor, quem: (valor.quem || [])[0] || "" });
  if (!Modal) return null;
  return (
    <Modal titulo="Editar acompanhamento" onClose={onFechar}
      acoes={<>
        <button className="os-btn" onClick={onFechar}>Fechar</button>
        <button className="os-btn primary" onClick={() => { onSalvar && onSalvar(v); avisar("Acompanhamento atualizado.", "ok"); onFechar(); }}>Salvar</button>
      </>}>
      <div className="pb-grid c3">
        <Fld label="Título" req span={2}><input value={v.titulo || ""} onChange={(e) => setV({ ...v, titulo: e.target.value })} /></Fld>
        <Fld label="Cliente / lead" req><Sel value={v.contato || ""} onChange={(x) => setV({ ...v, contato: x })} options={LEADS.map((l) => ({ id: l.nome, name: l.nome }))} vazio="Selecione" /></Fld>
        <Fld label="Status"><Sel value={v.status} onChange={(x) => setV({ ...v, status: x })} options={STATUS} vazio="Selecione" /></Fld>
        <Fld label="Início" req><input value={v.inicio || ""} onChange={(e) => setV({ ...v, inicio: e.target.value })} /></Fld>
        <Fld label="Fim" req><input value={v.fim || ""} onChange={(e) => setV({ ...v, fim: e.target.value })} /></Fld>
        <Fld label="Descrição" span={3}><textarea value={v.desc || ""} onChange={(e) => setV({ ...v, desc: e.target.value })} /></Fld>
        <Fld label="Informação adicional" span={3}><textarea value={v.extra || ""} onChange={(e) => setV({ ...v, extra: e.target.value })} /></Fld>
        <Fld label="Tipo de acompanhamento" req><Sel value={v.tipo} onChange={(x) => setV({ ...v, tipo: x })} options={TIPOS} vazio="Selecione" /></Fld>
        <Fld label="Categoria" req><Sel value={v.cat} onChange={(x) => setV({ ...v, cat: x })} options={CATEGORIAS} vazio="Selecione" /></Fld>
        <Fld label="Atribuído" req><Sel value={v.quem} onChange={(x) => setV({ ...v, quem: x })} options={USUARIOS.map((u) => ({ id: u, name: u }))} vazio="Selecione" /></Fld>
      </div>
      <Notificacao v={v} set={setV} />
    </Modal>
  );
}

// ─────────── 4. Logs do acompanhamento (schedule_log/*) ───────────
function LogsAcompanhamento({ followup, onFechar, avisar }) {
  const { Fld, Sel, Modal } = UI();
  const { Mini, Pill, rotulo } = { ...U(), rotulo: D().rotulo };
  const { STATUS } = D();
  const TOM = U().TOM || {};
  const [logs, setLogs] = useState(LOGS_INI[followup.id] || []);
  const [ed, setEd] = useState(null);
  if (!Modal) return null;

  return (
    <Modal titulo={"Log de acompanhamento — " + followup.titulo} onClose={onFechar}
      acoes={<>
        <button className="os-btn" onClick={onFechar}>Fechar</button>
        <button className="os-btn primary" onClick={() => setEd({ tipo: followup.tipo, status: followup.status })}><Ic name="plus" size={12} /> Adicionar registro</button>
      </>}>
      {logs.length === 0 && !ed && <p className="pb-help">Nenhum registro encontrado! Cada contato feito entra aqui — é o histórico que o próximo atendente lê.</p>}
      {logs.map((l) => (
        <div key={l.id} style={{ borderTop: "1px solid var(--border-2)", padding: "10px 0" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <b style={{ fontSize: 12.5 }}>{l.assunto}</b>
            <Pill tom={TOM[l.status]}>{rotulo(STATUS, l.status)}</Pill>
            <span className="pb-help" style={{ marginLeft: "auto", fontFamily: "var(--font-mono)" }}>{l.inicio}–{l.fim}</span>
          </div>
          <p className="pb-help" style={{ marginTop: 4 }}>{rotulo(LOG_TIPOS, l.tipo)} · {l.por} — {l.desc}</p>
          <div className="pb-filters-h" style={{ marginTop: 4 }}>
            <div className="sp" />
            <button className="os-btn sm ghost" onClick={() => setEd(l)}>Editar</button>
            <button className="os-btn sm ghost danger" onClick={() => { setLogs((ls) => ls.filter((x) => x.id !== l.id)); avisar("Registro excluído.", "ok"); }}>Excluir</button>
          </div>
        </div>
      ))}
      {ed &&
        <div className="pb-grid c3" style={{ marginTop: 12, borderTop: "1px solid var(--border)", paddingTop: 12 }}>
          <Fld label="Assunto" req span={3}><input value={ed.assunto || ""} onChange={(e) => setEd({ ...ed, assunto: e.target.value })} /></Fld>
          <Fld label="Tipo de log" req><Sel value={ed.tipo} onChange={(x) => setEd({ ...ed, tipo: x })} options={LOG_TIPOS} vazio="Selecione" /></Fld>
          <Fld label="Início" req><input value={ed.inicio || ""} onChange={(e) => setEd({ ...ed, inicio: e.target.value })} placeholder="hh:mm" /></Fld>
          <Fld label="Fim" req><input value={ed.fim || ""} onChange={(e) => setEd({ ...ed, fim: e.target.value })} placeholder="hh:mm" /></Fld>
          <Fld label="Descrição" span={3}><textarea value={ed.desc || ""} onChange={(e) => setEd({ ...ed, desc: e.target.value })} /></Fld>
          <Fld label="Status do acompanhamento"><Sel value={ed.status} onChange={(x) => setEd({ ...ed, status: x })} options={STATUS} vazio="Selecione" /></Fld>
          <div className="pb-filters-h" style={{ gridColumn: "span 3" }}>
            <div className="sp" />
            <button className="os-btn sm" onClick={() => setEd(null)}>Cancelar</button>
            <button className="os-btn sm primary" onClick={() => {
              if (!ed.assunto) return avisar("O assunto do registro é obrigatório.", "warn");
              setLogs((ls) => ed.id ? ls.map((x) => x.id === ed.id ? ed : x) : [{ ...ed, id: Date.now(), por: "Wagner Ramos" }, ...ls]);
              setEd(null); avisar("Registro de acompanhamento salvo.", "ok");
            }}>Salvar</button>
          </div>
        </div>}
    </Modal>
  );
}

// ─────────── 5. Acompanhamento antecipado (create_advance_follow_up.blade.php) ───────────
function AntecipadoForm({ onVoltar, avisar }) {
  const { Widget, Fld, Sel } = UI();
  const { Mini, Pill } = U();
  const { CATEGORIAS, STATUS, TIPOS, LEADS, fmtBRL } = D();
  const [v, setV] = useState({ status: "scheduled", mail: true });
  const [grupo, setGrupo] = useState(null); // linhas geradas pelo "Próximo"
  const cat = v.por ? (["all", "due", "partial", "overdue"].includes(v.por) ? "payment_status" : v.por === "contact_name" ? "contact_name" : "orders") : null;

  const gerar = () => {
    if (cat === "payment_status") {
      const sel = v.faturas && v.faturas.length ? v.faturas : FATURAS.filter((f) => v.por === "all" || f.status === v.por).map((f) => f.id);
      if (!sel.length) return avisar("Selecione as faturas.", "warn");
      setGrupo(FATURAS.filter((f) => sel.includes(f.id)).map((f) => ({ id: f.id, quem: f.cli, ref: f.id, extra: fmtBRL(f.saldo) + " · " + f.venc })));
      setV({ ...v, titulo: v.titulo || "Cobrança: {invoice_no} — {due_amount}" });
    } else if (cat === "orders") {
      if (!v.dias) return avisar("Insira os dias.", "warn");
      setGrupo(LEADS.filter((l) => l.fase === "cliente" || l.fase === "negociacao").map((l) => ({ id: l.id, quem: l.nome, ref: "sem pedido há " + v.dias + " dias", extra: l.quem })));
      setV({ ...v, titulo: v.titulo || "Reativação: {contact_name} — {days} dias" });
    } else {
      const sel = v.clientes || [];
      if (!sel.length) return avisar("Selecione os clientes.", "warn");
      setGrupo(LEADS.filter((l) => sel.includes(l.nome)).map((l) => ({ id: l.id, quem: l.nome, ref: l.cel, extra: l.quem })));
      setV({ ...v, titulo: v.titulo || "Acompanhamento: {contact_name}" });
    }
  };
  const tagsDoCaso = cat === "payment_status" ? TAGS.invoice : cat === "orders" ? TAGS.trans_days : TAGS.contact_name;

  return (
    <>
      <div className="pb-filters-h">
        <button className="os-btn sm ghost" onClick={onVoltar}>← Acompanhamentos</button>
      </div>
      <Widget contrato="crm-antecipado-base" titulo="Acompanhamento antecipado" nota="vários acompanhamentos de uma vez">
        <div className="pb-grid c3">
          <Fld label="Categoria" req><Sel value={v.cat || ""} onChange={(x) => setV({ ...v, cat: x })} options={CATEGORIAS} vazio="Selecione" /></Fld>
          <Fld label="Acompanhamento por" req dica="Status do pagamento: gera por fatura em aberto. Pedidos: gera por cliente sem pedido nos dias informados. Nome: você escolhe os clientes.">
            <select value={v.por || ""} onChange={(e) => { setV({ ...v, por: e.target.value, faturas: [], clientes: [] }); setGrupo(null); }}>
              <option value="">Selecione</option>
              <optgroup label="Status do pagamento">{PGTO_FILTRO.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</optgroup>
              <optgroup label="Pedidos">{PEDIDO_FILTRO.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</optgroup>
              <optgroup label="Contato"><option value="contact_name">Nome</option></optgroup>
            </select>
          </Fld>
          {cat === "orders" && <Fld label="Em dias" req><input className="num" value={v.dias || ""} onChange={(e) => setV({ ...v, dias: e.target.value })} placeholder="Insira os dias" /></Fld>}
          {cat === "payment_status" &&
            <Fld label="Faturas" span={1}>
              <div className="pb-tags" style={{ maxHeight: 96, overflow: "auto" }}>
                {FATURAS.filter((f) => v.por === "all" || f.status === v.por).map((f) => (
                  <label key={f.id} className="pb-chk" style={{ flex: "0 0 100%" }}>
                    <input type="checkbox" checked={(v.faturas || []).includes(f.id)} onChange={() => setV({ ...v, faturas: (v.faturas || []).includes(f.id) ? v.faturas.filter((x) => x !== f.id) : [...(v.faturas || []), f.id] })} />
                    <b>{f.id}<small>{f.cli} · {fmtBRL(f.saldo)}</small></b>
                  </label>
                ))}
              </div>
            </Fld>}
          {cat === "contact_name" &&
            <Fld label="Clientes" span={1}>
              <div className="pb-tags" style={{ maxHeight: 96, overflow: "auto" }}>
                {LEADS.map((l) => (
                  <label key={l.id} className="pb-chk" style={{ flex: "0 0 100%" }}>
                    <input type="checkbox" checked={(v.clientes || []).includes(l.nome)} onChange={() => setV({ ...v, clientes: (v.clientes || []).includes(l.nome) ? v.clientes.filter((x) => x !== l.nome) : [...(v.clientes || []), l.nome] })} />
                    <b>{l.nome}</b>
                  </label>
                ))}
              </div>
            </Fld>}
        </div>
        {cat &&
          <div className="pb-filters-h" style={{ marginTop: 12 }}>
            <div className="sp" />
            <button className="os-btn sm primary" onClick={gerar}>Próximo →</button>
          </div>}
      </Widget>
      {grupo &&
        <>
          <Widget contrato="crm-antecipado-grupo" titulo="Quem vai receber" nota={grupo.length + " acompanhamento(s)"}>
            <table className="pb-tbl" style={{ width: "100%" }}>
              <thead><tr><th>Cliente</th><th>Referência</th><th>Detalhe</th><th style={{ width: 60 }}></th></tr></thead>
              <tbody>
                {grupo.map((g) => (
                  <tr key={g.id}>
                    <td>{g.quem}</td><td className="mono">{g.ref}</td><td>{g.extra}</td>
                    <td><button className="os-btn sm ghost danger" onClick={() => setGrupo(grupo.filter((x) => x.id !== g.id))}>remover</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Widget>
          <Widget contrato="crm-antecipado-form" titulo="Conteúdo dos acompanhamentos">
            <div className="pb-grid c3">
              <Fld label="Título" req span={3}><input value={v.titulo || ""} onChange={(e) => setV({ ...v, titulo: e.target.value })} /></Fld>
              <div style={{ gridColumn: "span 3" }}><Tags lista={tagsDoCaso} /></div>
              <Fld label="Status"><Sel value={v.status} onChange={(x) => setV({ ...v, status: x })} options={STATUS} vazio="Selecione" /></Fld>
              <Fld label="Início" req><input value={v.inicio || ""} onChange={(e) => setV({ ...v, inicio: e.target.value })} placeholder="dd/mm/aaaa hh:mm" /></Fld>
              <Fld label="Fim" req><input value={v.fim || ""} onChange={(e) => setV({ ...v, fim: e.target.value })} placeholder="dd/mm/aaaa hh:mm" /></Fld>
              <Fld label="Descrição" span={3}><textarea value={v.desc || ""} onChange={(e) => setV({ ...v, desc: e.target.value })} /></Fld>
              <Fld label="Tipo de acompanhamento" req span={2}><Sel value={v.tipo || ""} onChange={(x) => setV({ ...v, tipo: x })} options={TIPOS} vazio="Selecione" /></Fld>
            </div>
            <Notificacao v={v} set={setV} />
            <div className="pb-filters-h" style={{ marginTop: 14 }}>
              <span className="pb-help">Vários acompanhamentos serão criados — um por linha da lista acima.</span>
              <div className="sp" />
              <button className="os-btn sm primary" onClick={() => {
                if (!grupo.length) return avisar("Não há nenhum cliente para adicionar acompanhamento.", "warn");
                if (!v.cat || !v.titulo || !v.tipo) return avisar("Categoria, título e tipo são obrigatórios.", "warn");
                avisar(grupo.length + " acompanhamentos criados.", "ok"); onVoltar();
              }}>Salvar</button>
            </div>
          </Widget>
        </>}
    </>
  );
}

// ─────────── 6. Acompanhamento recorrente (create_recursive_follow_up.blade.php) ───────────
function RecorrenteForm({ onVoltar, avisar }) {
  const { Widget, Fld, Sel } = UI();
  const { CATEGORIAS, STATUS, TIPOS, USUARIOS } = D();
  const [v, setV] = useState({ status: "open", mail: true });
  const cat = v.por ? (["all", "due", "partial", "overdue"].includes(v.por) ? "payment_status" : "orders") : null;

  return (
    <>
      <div className="pb-filters-h">
        <button className="os-btn sm ghost" onClick={onVoltar}>← Acompanhamentos</button>
      </div>
      <Widget contrato="crm-recorrente-base" titulo="Acompanhamento recorrente" nota="a regra roda todo dia">
        <div className="pb-grid c4">
          <Fld label="Categoria" req><Sel value={v.cat || ""} onChange={(x) => setV({ ...v, cat: x })} options={CATEGORIAS} vazio="Selecione" /></Fld>
          <Fld label="Acompanhamento por" req dica="Status do pagamento: acompanhamento automático se o pagamento estiver devido/parcial/vencido nos dias informados. Pedidos: se não houver pedido nos dias informados.">
            <select value={v.por || ""} onChange={(e) => setV({ ...v, por: e.target.value })}>
              <option value="">Selecione</option>
              <optgroup label="Status do pagamento">{PGTO_FILTRO.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</optgroup>
              <optgroup label="Pedidos"><option value="has_no_transactions">Não tem transações</option></optgroup>
            </select>
          </Fld>
          <Fld label="Em dias" req><input className="num" value={v.dias || ""} onChange={(e) => setV({ ...v, dias: e.target.value })} placeholder="Insira os dias" /></Fld>
          <Fld label="Atribuído" req><Sel value={v.quem || ""} onChange={(x) => setV({ ...v, quem: x })} options={USUARIOS.map((u) => ({ id: u, name: u }))} vazio="Selecione" /></Fld>
        </div>
      </Widget>
      <Widget contrato="crm-recorrente-form" titulo="Conteúdo gerado a cada rodada">
        <div className="pb-grid c2">
          <Fld label="Título" req span={2}><input value={v.titulo || ""} onChange={(e) => setV({ ...v, titulo: e.target.value })} /></Fld>
          <div style={{ gridColumn: "span 2" }}><Tags lista={cat === "orders" ? TAGS.trans_days : TAGS.invoice} /></div>
          <Fld label="Descrição" span={2}><textarea value={v.desc || ""} onChange={(e) => setV({ ...v, desc: e.target.value })} /></Fld>
          <Fld label="Status"><Sel value={v.status} onChange={(x) => setV({ ...v, status: x })} options={STATUS} vazio="Selecione" /></Fld>
          <Fld label="Tipo de acompanhamento" req><Sel value={v.tipo || ""} onChange={(x) => setV({ ...v, tipo: x })} options={TIPOS} vazio="Selecione" /></Fld>
        </div>
        <Notificacao v={v} set={setV} />
        <div className="pb-filters-h" style={{ marginTop: 14 }}>
          <div className="sp" />
          <button className="os-btn sm primary" onClick={() => {
            if (!v.cat || !v.por || !v.dias || !v.quem || !v.titulo || !v.tipo) return avisar("Categoria, acompanhamento por, dias, atribuído, título e tipo são obrigatórios.", "warn");
            avisar("Acompanhamento recorrente criado — gera a cada " + v.dias + " dias.", "ok"); onVoltar();
          }}>Salvar</button>
        </div>
      </Widget>
    </>
  );
}

window.CrmBladeForms = { FichaLead, CampanhaForm, AcompanhamentoForm, LogsAcompanhamento, AntecipadoForm, RecorrenteForm, FATURAS, LOG_TIPOS };
})();
