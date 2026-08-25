// crm-blade-telas.jsx — o resto do menu do módulo Crm (crm::layouts.nav), tradução 1:1 dos blades:
//   campaign/index + create/show ....... "Campanhas"
//   contact_login/all_contacts_login ... "Login de contatos"
//   contact_login/commissions .......... "Comissões"
//   call_logs/index .................... "Registro de chamadas" (+ exclusão em massa)
//   reports/index ...................... "Relatórios" (por usuário, por contato, conversão)
//   proposal_template/index ............ "Modelo de proposta"
//   proposal/index ..................... "Propostas"
//   marketplace/index .................. "Marketplace B2B" (Exporters India + importar leads)
//   order_request/index ................ "Pedido de ordem"
//   TaxonomyController ?type=source|life_stage|followup_category .. "Fontes e estágios"
//   settings/index ..................... "Configurações"
// Consome window.CBD (domínio) e window.CBUI (peças) de crm-blade.jsx. Expõe window.CrmBladeTelas.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const D = () => window.CBD || {};
const U = () => window.CBUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };

// ─────────── Campanhas (campaign/index.blade.php) ───────────
function Campanhas({ avisar, densa, setDensa, abrir }) {
  const { Widget, Kebab, Fld, Sel, Modal } = UI();
  const { Grade, Toolbar, Filtros, Mini, Pill } = U();
  const { CAMPANHAS, USUARIOS } = D();
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");
  const [ver, setVer] = useState(null);
  const [novo, setNovo] = useState(null);

  const rows = CAMPANHAS.filter((c) => (!f.tipo || c.tipo === f.tipo) && (!busca || c.nome.toLowerCase().includes(busca.toLowerCase())));
  const cols = [
    { key: "acao", label: "Ação", width: 92, resizable: false },
    { key: "nome", label: "Nome da campanha", width: 260, sortable: true },
    { key: "tipo", label: "Tipo de campanha", width: 150 },
    { key: "destinos", label: "Contatos", width: 108, align: "right", mono: true },
    { key: "por", label: "Criado por", width: 160 },
    { key: "criado", label: "Criado em", width: 160, mono: true },
  ];
  const grade = rows.map((c) => ({
    id: c.id, _s: c,
    acao: Kebab ? <Kebab acoes={[{ l: "Ver campanha", ic: "search", on: () => setVer(c) }, { l: "Editar", ic: "pencil", on: () => abrir && abrir("campanha", c) }, { l: "Enviar notificação", ic: "send", on: () => avisar("Campanha enviada para " + c.destinos + " contato(s).", "ok") }]} /> : <button className="os-btn sm ghost" onClick={() => setVer(c)}>Ver</button>,
    nome: c.nome, tipo: <Pill tom={c.tipo === "sms" ? "warn" : "info"}>{c.tipo === "sms" ? "SMS" : "E-mail"}</Pill>,
    destinos: c.destinos, por: c.por, criado: c.criado,
  }));

  return (
    <>
      <Filtros nota="Tipo de campanha — o único filtro do blade." f={f} setF={setF} campos={[{ k: "tipo", l: "Tipo de campanha", op: [{ id: "sms", name: "SMS" }, { id: "email", name: "E-mail" }] }]} />
      <Widget contrato="crm-campanhas" titulo="Todas as campanhas" nota={rows.length + " de " + CAMPANHAS.length} flush>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar campanha" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm primary" onClick={() => abrir ? abrir("campanha") : setNovo({ tipo: "email" })}><Ic name="plus" size={12} /> Adicionar</button>
        </Toolbar>
        <Grade columns={cols} rows={grade} densa={densa} altura={330} onRowClick={(r) => setVer(r._s)} />
      </Widget>
      {Modal && novo &&
        <Modal titulo="Criar campanha" onClose={() => setNovo(null)}
          acoes={<><button className="os-btn" onClick={() => setNovo(null)}>Fechar</button><button className="os-btn primary" onClick={() => { setNovo(null); avisar("Campanha criada.", "ok"); }}>Salvar</button></>}>
          <div className="pb-grid c2">
            <Fld label="Nome da campanha" req><input value={novo.nome || ""} onChange={(e) => setNovo({ ...novo, nome: e.target.value })} placeholder="Aniversariantes de setembro" /></Fld>
            <Fld label="Tipo de campanha" req><Sel value={novo.tipo} onChange={(v) => setNovo({ ...novo, tipo: v })} options={[{ id: "sms", name: "SMS" }, { id: "email", name: "E-mail" }]} vazio="Selecione" /></Fld>
            <Fld label="Leads e clientes" req span={2}><Sel value={novo.grupo || ""} onChange={(v) => setNovo({ ...novo, grupo: v })} options={[{ id: "leads", name: "Todos os leads" }, { id: "clientes", name: "Todos os clientes" }, { id: "aniver", name: "Aniversariantes do mês" }]} vazio="Selecione" /></Fld>
            <Fld label="Atribuído a"><Sel value={novo.quem || ""} onChange={(v) => setNovo({ ...novo, quem: v })} options={USUARIOS.map((u) => ({ id: u, name: u }))} vazio="Selecione" /></Fld>
            <Fld label="Assunto" req><input value={novo.assunto || ""} onChange={(e) => setNovo({ ...novo, assunto: e.target.value })} /></Fld>
            <Fld label={novo.tipo === "sms" ? "Corpo do SMS" : "Corpo do e-mail"} req span={2}><textarea value={novo.corpo || ""} onChange={(e) => setNovo({ ...novo, corpo: e.target.value })} /></Fld>
          </div>
        </Modal>}
      {Modal && ver &&
        <Modal titulo={ver.nome} onClose={() => setVer(null)}
          acoes={<><button className="os-btn" onClick={() => setVer(null)}>Fechar</button><button className="os-btn primary" onClick={() => { avisar("Campanha reenviada para " + ver.destinos + " contato(s).", "ok"); setVer(null); }}>Enviar notificação</button></>}>
          <Mini rows={[["Tipo", ver.tipo === "sms" ? "SMS" : "E-mail"], ["Assunto", ver.assunto], ["Contatos", ver.destinos], ["Criada por", ver.por], ["Criada em", ver.criado], ["Enviada", ver.enviado]]} />
          <p className="pb-help" style={{ marginTop: 12 }}><b>{ver.tipo === "sms" ? "Corpo do SMS" : "Corpo do e-mail"}:</b> {ver.corpo}</p>
        </Modal>}
    </>
  );
}

// ─────────── Login de contatos (contact_login/all_contacts_login.blade.php) ───────────
function Logins({ avisar, densa, setDensa }) {
  const { Widget, Kebab, Fld, Sel, Modal } = UI();
  const { Grade, Toolbar, Filtros } = U();
  const { LOGINS } = D();
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");
  const [novo, setNovo] = useState(null);

  const rows = LOGINS.filter((l) => (!f.contato || l.contato === f.contato) && (!busca || (l.nome + " " + l.user).toLowerCase().includes(busca.toLowerCase())));
  const cols = [
    { key: "acao", label: "Ação", width: 92, resizable: false },
    { key: "contato", label: "Contato", width: 220, sortable: true },
    { key: "user", label: "Nome de usuário", width: 160, mono: true },
    { key: "nome", label: "Nome", width: 180 },
    { key: "email", label: "E-mail", width: 240 },
    { key: "depto", label: "Departamento", width: 150 },
    { key: "cargo", label: "Designação", width: 160 },
  ];
  const grade = rows.map((l) => ({
    id: l.id, _s: l,
    acao: Kebab ? <Kebab acoes={[{ l: "Editar login", ic: "pencil", on: () => avisar("Editando login de " + l.nome + ".", "ok") }, { l: "Redefinir senha", ic: "shield", on: () => avisar("Link de nova senha enviado para " + l.email + ".", "ok") }]} /> : null,
    contato: l.contato, user: l.user, nome: l.nome, email: l.email, depto: l.depto, cargo: l.cargo,
  }));

  return (
    <>
      <Filtros nota="Contato — o filtro do blade." f={f} setF={setF} campos={[{ k: "contato", l: "Contato", op: LOGINS.map((l) => ({ id: l.contato, name: l.contato })) }]} />
      <Widget contrato="crm-logins" titulo="Login de todos os contatos" nota={rows.length + " de " + LOGINS.length} flush>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por nome ou usuário" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm primary" onClick={() => setNovo({})}><Ic name="plus" size={12} /> Adicionar</button>
        </Toolbar>
        <Grade columns={cols} rows={grade} densa={densa} altura={320} />
      </Widget>
      {Modal && novo &&
        <Modal titulo="Adicionar login" onClose={() => setNovo(null)}
          acoes={<><button className="os-btn" onClick={() => setNovo(null)}>Fechar</button><button className="os-btn primary" onClick={() => { setNovo(null); avisar("Login de contato criado.", "ok"); }}>Salvar</button></>}>
          <div className="pb-grid c2">
            <Fld label="Contato" req><Sel value={novo.contato || ""} onChange={(v) => setNovo({ ...novo, contato: v })} options={LOGINS.map((l) => ({ id: l.contato, name: l.contato }))} vazio="Selecione" /></Fld>
            <Fld label="Nome de usuário" req><input value={novo.user || ""} onChange={(e) => setNovo({ ...novo, user: e.target.value })} /></Fld>
            <Fld label="Nome" req><input value={novo.nome || ""} onChange={(e) => setNovo({ ...novo, nome: e.target.value })} /></Fld>
            <Fld label="E-mail" req><input value={novo.email || ""} onChange={(e) => setNovo({ ...novo, email: e.target.value })} /></Fld>
            <Fld label="Departamento"><input value={novo.depto || ""} onChange={(e) => setNovo({ ...novo, depto: e.target.value })} /></Fld>
            <Fld label="Designação"><input value={novo.cargo || ""} onChange={(e) => setNovo({ ...novo, cargo: e.target.value })} /></Fld>
            <Fld label="Senha" req><input type="password" value={novo.senha || ""} onChange={(e) => setNovo({ ...novo, senha: e.target.value })} /></Fld>
            <Fld label="Confirmar senha" req><input type="password" value={novo.senha2 || ""} onChange={(e) => setNovo({ ...novo, senha2: e.target.value })} /></Fld>
          </div>
        </Modal>}
    </>
  );
}

// ─────────── Comissões (contact_login/commissions.blade.php) ───────────
function Comissoes({ densa, setDensa }) {
  const { Widget } = UI();
  const { Grade, Toolbar, Filtros, Rodape } = U();
  const { COMISSOES, LOCAIS, fmtBRL } = D();
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");

  const rows = COMISSOES.filter((c) => (!f.contato || c.contato === f.contato) && (!f.loc || c.loc === f.loc) && (!busca || (c.nome + " " + c.inv).toLowerCase().includes(busca.toLowerCase())));
  const total = rows.reduce((a, c) => a + c.valor, 0);
  const cols = [
    { key: "data", label: "Data", width: 118, mono: true, sortable: true },
    { key: "contato", label: "Contato", width: 220 },
    { key: "nome", label: "Nome", width: 170 },
    { key: "cel", label: "Celular", width: 140, mono: true },
    { key: "inv", label: "Nº da fatura", width: 150, mono: true },
    { key: "loc", label: "Local", width: 130 },
    { key: "valor", label: "Comissão total", width: 150, align: "right", mono: true },
  ];
  const grade = rows.map((c) => ({ ...c, valor: fmtBRL(c.valor) }));

  return (
    <>
      <Filtros nota="Contato, pessoa de contato, local e intervalo de datas." f={f} setF={setF} campos={[
        { k: "contato", l: "Contato", op: COMISSOES.map((c) => ({ id: c.contato, name: c.contato })) },
        { k: "pessoa", l: "Pessoa de contato", op: COMISSOES.map((c) => ({ id: c.nome, name: c.nome })) },
        { k: "loc", l: "Local", op: LOCAIS.map((l) => ({ id: l.name, name: l.name })) },
        { k: "periodo", l: "Intervalo de datas", op: [{ id: "7", name: "Últimos 7 dias" }, { id: "30", name: "Últimos 30 dias" }] },
      ]} />
      <Widget contrato="crm-comissoes" titulo="Comissões" nota={rows.length + " lançamento(s)"} flush>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por nome ou fatura" densa={densa} setDensa={setDensa} />
        <Grade columns={cols} rows={grade} densa={densa} altura={300} />
        <Rodape><span className="pb-help">Total</span><div className="sp" /><b className="mono">{fmtBRL(total)}</b></Rodape>
      </Widget>
    </>
  );
}

// ─────────── Registro de chamadas (call_logs/index.blade.php) ───────────
function Chamadas({ avisar, densa, setDensa }) {
  const { Widget } = UI();
  const { Grade, Toolbar, Filtros, Rodape, Pill } = U();
  const { CHAMADAS, TIPO_CHAMADA, USUARIOS, TOM } = { ...D(), TOM: (U().TOM || {}) };
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");
  const [sel, setSel] = useState([]);

  const rows = CHAMADAS.filter((c) => (!f.contato || c.contato === f.contato) && (!f.user || c.criador === f.user) && (!busca || (c.contato + " " + c.numero).toLowerCase().includes(busca.toLowerCase())));
  const cols = [
    { key: "inicio", label: "Hora de início", width: 150, mono: true, sortable: true },
    { key: "fim", label: "Hora de término", width: 150, mono: true },
    { key: "dur", label: "Duração da chamada", width: 156, mono: true, align: "right" },
    { key: "tipo", label: "Tipo de chamada", width: 140 },
    { key: "numero", label: "Nº do contato", width: 148, mono: true },
    { key: "contato", label: "Contato", width: 210 },
    { key: "user", label: "Usuário", width: 160 },
    { key: "criador", label: "Registro criado por", width: 170 },
  ];
  const grade = rows.map((c) => ({ ...c, tipo: <Pill tom={TOM[c.tipo] || "mute"}>{TIPO_CHAMADA[c.tipo]}</Pill> }));

  return (
    <>
      <Filtros nota="Contato, autor do registro e intervalo de datas." f={f} setF={setF} campos={[
        { k: "contato", l: "Contato", op: CHAMADAS.map((c) => ({ id: c.contato, name: c.contato })) },
        { k: "user", l: "Registro criado por", op: USUARIOS.map((u) => ({ id: u, name: u })) },
        { k: "periodo", l: "Intervalo de datas", op: [{ id: "hoje", name: "Hoje" }, { id: "7", name: "Últimos 7 dias" }, { id: "30", name: "Últimos 30 dias" }] },
      ]} />
      <Widget contrato="crm-chamadas" titulo="Registro de chamadas" nota={rows.length + " de " + CHAMADAS.length} flush>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por contato ou número" densa={densa} setDensa={setDensa} />
        <Grade columns={cols} rows={grade} densa={densa} altura={330} selectable onSelectionChange={setSel} />
        <Rodape>
          <span className="pb-help">{sel.length ? sel.length + " registro(s) selecionado(s)" : "Selecione linhas para excluir em massa."}</span>
          <div className="sp" />
          <button className="os-btn sm ghost danger" disabled={!sel.length} onClick={() => avisar(sel.length + " registro(s) de chamada excluídos.", "ok")}>Excluir selecionados</button>
        </Rodape>
      </Widget>
    </>
  );
}

// ─────────── Relatórios (reports/index.blade.php) ───────────
function Relatorios({ densa, avisar }) {
  const { Widget } = UI();
  const { Grade, Filtros, Mini } = U();
  const { Drawer, DrawerSection } = DS();
  const { PAINEL, STATUS, LEADS } = D();
  const [f, setF] = useState({});
  const [drill, setDrill] = useState(null);

  const colsUser = [
    { key: "user", label: "Usuário", width: 200 },
    ...STATUS.map((s) => ({ key: s.id, label: s.name, width: 118, align: "right", mono: true })),
    { key: "nenhum", label: "Outros", width: 100, align: "right", mono: true },
    { key: "total", label: "Acompanhamentos totais", width: 190, align: "right", mono: true },
  ];
  // Acompanhamentos por contato — mesma matriz, agrupada por contato em vez de usuário.
  const porContato = LEADS.slice(0, 6).map((l, i) => ({
    contato: l.nome, scheduled: [2, 1, 0, 1, 2, 0][i], open: [1, 0, 1, 0, 1, 1][i],
    completed: [1, 2, 0, 3, 1, 0][i], canceled: [0, 0, 1, 0, 0, 1][i], nenhum: [0, 1, 0, 0, 0, 0][i],
    total: [4, 4, 2, 4, 4, 2][i], id: l.id,
  }));
  const colsContato = [{ key: "contato", label: "Contato", width: 220 }, ...colsUser.slice(1)];

  return (
    <>
      <Filtros nota="Intervalo de datas e categoria — os filtros dos relatórios do módulo." f={f} setF={setF} campos={[
        { k: "periodo", l: "Intervalo de datas", op: [{ id: "7", name: "Últimos 7 dias" }, { id: "30", name: "Últimos 30 dias" }, { id: "mes", name: "Este mês" }] },
        { k: "cat", l: "Categoria de acompanhamento", op: D().CATEGORIAS },
      ]} />
      <Widget contrato="crm-rel-usuario" titulo="Acompanhamentos por usuário" flush>
        <Grade columns={colsUser} rows={PAINEL.porUsuario.map((r) => ({ ...r, id: r.user }))} densa={densa} altura={220} />
      </Widget>
      <Widget contrato="crm-rel-contato" titulo="Acompanhamentos por contatos" flush>
        <Grade columns={colsContato} rows={porContato} densa={densa} altura={260} />
      </Widget>
      <Widget contrato="crm-rel-conversao" titulo="Leads convertidos em cliente" nota="clique numa linha para ver quem foi">
        <table className="pb-tbl" style={{ width: "100%" }}>
          <thead><tr><th>Convertido por</th><th style={{ textAlign: "right" }}>Total</th></tr></thead>
          <tbody>
            {PAINEL.conversao.map((r) => (
              <tr key={r.quem} onClick={() => setDrill(r)} style={{ cursor: "default" }}>
                <td><button className="pb-uso" style={{ font: "inherit" }}>{r.quem}</button></td>
                <td className="mono" style={{ textAlign: "right" }}>{r.total}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Widget>
      {Drawer && drill &&
        <Drawer open={!!drill} onClose={() => setDrill(null)} badge="Conversão" title={drill.quem} subtitle={drill.total + " lead(s) convertidos em cliente"}>
          <DrawerSection title="Quem foi convertido">
            <Mini head={["Cliente", "Convertido em"]} rows={LEADS.filter((l) => l.quem === drill.quem).slice(0, drill.total).map((l) => [l.nome, l.criado])} vazio="Nenhuma conversão no período" />
          </DrawerSection>
          <DrawerSection title="De onde vem">
            <p className="pb-help">Contatos que saíram de lead para cliente com este usuário como responsável — o mesmo recorte do relatório lead-to-customer-details do módulo.</p>
          </DrawerSection>
        </Drawer>}
    </>
  );
}

// ─────────── Modelo de proposta (proposal_template/index.blade.php) ───────────
function Modelo({ avisar, onIr }) {
  const { Widget, Fld } = UI();
  const { Alert } = DS();
  const { MODELO } = D();
  const [modelo, setModelo] = useState(MODELO);
  const [edit, setEdit] = useState(false);

  return (
    <>
      {Alert && <Alert tone="info" title="Um modelo por empresa">O módulo aceita um único modelo de proposta — é ele que alimenta o envio em “Propostas”.</Alert>}
      <Widget contrato="crm-modelo" titulo="Modelo de proposta" nota={"atualizado em " + modelo.atualizado}>
        {edit
          ? <div className="pb-grid">
              <Fld label="Assunto" req span={3}><input value={modelo.assunto} onChange={(e) => setModelo({ ...modelo, assunto: e.target.value })} /></Fld>
              <Fld label="Corpo da proposta" span={3}><textarea defaultValue={"Olá, {{contato}}.\n\nSegue nossa proposta comercial para {{servico}}. Valor: {{valor}}.\nValidade de 7 dias. Qualquer dúvida, é só responder este e-mail.\n\nOffice Impresso — comunicação visual"} /></Fld>
              <Fld label="Anexos" span={3}>
                <div className="pb-tags">{modelo.anexos.map((a) => <span key={a} className="pb-tag"><Ic name="paperclip" size={11} /> {a}<button onClick={() => setModelo({ ...modelo, anexos: modelo.anexos.filter((x) => x !== a) })}>✕</button></span>)}</div>
              </Fld>
            </div>
          : <div className="pb-grid c2">
              <div>
                <p className="pb-help" style={{ fontWeight: 700, marginBottom: 4 }}>Assunto</p>
                <p style={{ margin: 0, fontSize: 13.5 }}>{modelo.assunto}</p>
              </div>
              <div>
                <p className="pb-help" style={{ fontWeight: 700, marginBottom: 4 }}>Anexos</p>
                <div className="pb-tags">{modelo.anexos.map((a) => <span key={a} className="pb-tag"><Ic name="paperclip" size={11} /> {a}</span>)}</div>
              </div>
            </div>}
        <div className="pb-filters-h" style={{ marginTop: 14 }}>
          <div className="sp" />
          <button className="os-btn sm" onClick={() => setEdit((e) => !e)}>{edit ? "Cancelar" : "Editar"}</button>
          {edit
            ? <button className="os-btn sm primary" onClick={() => { setEdit(false); avisar("Modelo de proposta atualizado.", "ok"); }}>Salvar</button>
            : <>
                <button className="os-btn sm" onClick={() => avisar("Pré-visualização do modelo aberta.", "ok")}>Exibir</button>
                <button className="os-btn sm primary" onClick={() => onIr && onIr("propostas")}><Ic name="send" size={12} /> Mandar</button>
              </>}
        </div>
      </Widget>
    </>
  );
}

// ─────────── Propostas (proposal/index.blade.php) ───────────
function Propostas({ avisar, densa, setDensa, onIr }) {
  const { Widget, Kebab, Fld, Sel, Modal } = UI();
  const { Grade, Toolbar } = U();
  const { PROPOSTAS, LEADS, MODELO } = D();
  const [busca, setBusca] = useState("");
  const [enviar, setEnviar] = useState(null);

  const rows = PROPOSTAS.filter((p) => !busca || (p.contato + " " + p.assunto).toLowerCase().includes(busca.toLowerCase()));
  const cols = [
    { key: "contato", label: "Contato", width: 220, sortable: true },
    { key: "assunto", label: "Assunto", width: 340 },
    { key: "por", label: "Enviado por", width: 160 },
    { key: "data", label: "Data", width: 160, mono: true, sortable: true },
    { key: "anexos", label: "Anexos", width: 100, align: "right", mono: true },
    { key: "acao", label: "Ação", width: 92, resizable: false },
  ];
  const grade = rows.map((p) => ({
    ...p,
    acao: Kebab ? <Kebab acoes={[{ l: "Ver proposta", ic: "search", on: () => avisar("Proposta de " + p.contato + " aberta.", "ok") }, { l: "Reenviar", ic: "send", on: () => avisar("Proposta reenviada para " + p.contato + ".", "ok") }, { l: "Excluir anexo", ic: "paperclip", tone: "danger", on: () => avisar("Anexo excluído.", "ok") }]} /> : null,
  }));

  return (
    <>
      <Widget contrato="crm-propostas" titulo="Propostas" nota={rows.length + " enviada(s)"} flush>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por contato ou assunto" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm" onClick={() => onIr && onIr("modelo")}>Modelo de proposta</button>
          <button className="os-btn sm primary" onClick={() => setEnviar({ assunto: MODELO.assunto })}><Ic name="send" size={12} /> Mandar</button>
        </Toolbar>
        <Grade columns={cols} rows={grade} densa={densa} altura={320} />
      </Widget>
      {Modal && enviar &&
        <Modal titulo="Enviar proposta" onClose={() => setEnviar(null)}
          acoes={<><button className="os-btn" onClick={() => setEnviar(null)}>Fechar</button><button className="os-btn primary" onClick={() => { setEnviar(null); avisar("Proposta enviada.", "ok"); }}>Mandar</button></>}>
          <div className="pb-grid c2">
            <Fld label="Enviar para" req><Sel value={enviar.para || ""} onChange={(v) => setEnviar({ ...enviar, para: v })} options={LEADS.map((l) => ({ id: l.nome, name: l.nome }))} vazio="Selecione" /></Fld>
            <Fld label="Assunto" req><input value={enviar.assunto} onChange={(e) => setEnviar({ ...enviar, assunto: e.target.value })} /></Fld>
            <Fld label="Cc"><input value={enviar.cc || ""} onChange={(e) => setEnviar({ ...enviar, cc: e.target.value })} /></Fld>
            <Fld label="Bcc"><input value={enviar.bcc || ""} onChange={(e) => setEnviar({ ...enviar, bcc: e.target.value })} /></Fld>
            <Fld label="Anexos" span={2}><div className="pb-tags">{MODELO.anexos.map((a) => <span key={a} className="pb-tag"><Ic name="paperclip" size={11} /> {a}</span>)}</div></Fld>
          </div>
        </Modal>}
    </>
  );
}

// ─────────── Marketplace B2B (marketplace/index.blade.php — Exporters India) ───────────
function Marketplace({ avisar }) {
  const { Widget, Fld, Sel } = UI();
  const { Alert } = DS();
  const { FONTES, USUARIOS } = D();
  const [m, setM] = useState({ chave: "", email: "", quem: "Wagner Ramos", fonte: "site" });

  return (
    <>
      {Alert && <Alert tone="warn" title="Integração externa">A chave e o e-mail vêm do painel do marketplace. Os leads importados entram com a fonte escolhida e já atribuídos.</Alert>}
      <Widget contrato="crm-marketplace" titulo="Exporters India">
        <div className="pb-grid c2">
          <Fld label="Chave" req><input value={m.chave} onChange={(e) => setM({ ...m, chave: e.target.value })} placeholder="site_key" /></Fld>
          <Fld label="E-mail" req><input value={m.email} onChange={(e) => setM({ ...m, email: e.target.value })} placeholder="conta@exportersindia.com" /></Fld>
          <Fld label="Acompanhamento atribuído a" req><Sel value={m.quem} onChange={(v) => setM({ ...m, quem: v })} options={USUARIOS.map((u) => ({ id: u, name: u }))} vazio="Selecione" /></Fld>
          <Fld label="Fonte" req><Sel value={m.fonte} onChange={(v) => setM({ ...m, fonte: v })} options={FONTES} vazio="Selecione" /></Fld>
        </div>
        <div className="pb-filters-h" style={{ marginTop: 14 }}>
          <div className="sp" />
          <button className="os-btn sm" onClick={() => avisar("Importação de leads iniciada.", "ok")}>Importar leads</button>
          <button className="os-btn sm primary" onClick={() => m.chave && m.email ? avisar("Marketplace salvo.", "ok") : avisar("Preencha a chave e o e-mail.", "warn")}>Enviar</button>
        </div>
      </Widget>
    </>
  );
}

// ─────────── Pedido de ordem (order_request/index.blade.php) ───────────
function Pedidos({ avisar, densa, setDensa }) {
  const { Widget } = UI();
  const { Grade, Toolbar, Filtros, Rodape, Pill } = U();
  const { PEDIDOS, STATUS_PEDIDO, LOCAIS, fmtBRL, rotulo } = D();
  const TOM = U().TOM || {};
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");

  const rows = PEDIDOS.filter((p) => (!f.loc || p.loc === f.loc) && (!f.status || p.status === f.status) && (!busca || (p.num + " " + p.contato).toLowerCase().includes(busca.toLowerCase())));
  const cols = [
    { key: "data", label: "Data", width: 150, mono: true, sortable: true },
    { key: "num", label: "Nº do pedido", width: 150, mono: true, sortable: true },
    { key: "contato", label: "Contato", width: 220 },
    { key: "loc", label: "Local", width: 140 },
    { key: "status", label: "Status", width: 180 },
    { key: "restante", label: "Quantidade restante", width: 170, align: "right", mono: true },
    { key: "total", label: "Total", width: 140, align: "right", mono: true },
  ];
  const grade = rows.map((p) => ({
    ...p,
    status: <Pill tom={p.status === "completed" ? "ok" : TOM[p.status] || "mute"}>{rotulo(STATUS_PEDIDO, p.status)}</Pill>,
    total: fmtBRL(p.total),
  }));

  return (
    <>
      <Filtros nota="Local, status e intervalo de datas — os filtros do blade." f={f} setF={setF} campos={[
        { k: "loc", l: "Local do negócio", op: LOCAIS.map((l) => ({ id: l.name, name: l.name })) },
        { k: "status", l: "Status", op: STATUS_PEDIDO },
        { k: "periodo", l: "Intervalo de datas", op: [{ id: "7", name: "Últimos 7 dias" }, { id: "30", name: "Últimos 30 dias" }] },
      ]} />
      <Widget contrato="crm-pedidos" titulo="Pedido de ordem" nota={rows.length + " de " + PEDIDOS.length} flush>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por nº ou contato" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm primary" onClick={() => avisar("Formulário de solicitação de pedido aberto.", "ok")}><Ic name="plus" size={12} /> Adicionar solicitação de pedido</button>
        </Toolbar>
        <Grade columns={cols} rows={grade} densa={densa} altura={280} />
        <Rodape><span className="pb-help">Total dos pedidos filtrados</span><div className="sp" /><b className="mono">{fmtBRL(rows.reduce((a, p) => a + p.total, 0))}</b></Rodape>
      </Widget>
    </>
  );
}

// ─────────── Fontes, estágios de vida e categorias (TaxonomyController ?type=…) ───────────
function Taxonomias({ avisar }) {
  const { Widget } = UI();
  const { FONTES, FASES, CATEGORIAS, LEADS, FOLLOWUPS } = D();
  const grupos = [
    { titulo: "Fontes", nota: "usadas ao adicionar leads", itens: FONTES.map((x) => ({ nome: x.name, uso: LEADS.filter((l) => l.fonte === x.id).length })) },
    { titulo: "Estágios de vida", nota: "fase de vida dos leads", itens: FASES.map((x) => ({ nome: x.name, uso: LEADS.filter((l) => l.fase === x.id).length })) },
    { titulo: "Categorias de acompanhamento", nota: "classificam cada acompanhamento", itens: CATEGORIAS.map((x) => ({ nome: x.name, uso: FOLLOWUPS.filter((s) => s.cat === x.id).length })) },
  ];
  return (
    <div className="pb-grid">
      {grupos.map((g) => (
        <Widget key={g.titulo} contrato={"crm-tax-" + g.titulo} titulo={g.titulo} nota={g.nota}>
          <table className="pb-tbl" style={{ width: "100%" }}>
            <thead><tr><th>Nome</th><th style={{ textAlign: "right" }}>Em uso</th></tr></thead>
            <tbody>{g.itens.map((i) => <tr key={i.nome}><td>{i.nome}</td><td className="mono" style={{ textAlign: "right" }}>{i.uso}</td></tr>)}</tbody>
          </table>
          <div className="pb-filters-h" style={{ marginTop: 12 }}>
            <div className="sp" />
            <button className="os-btn sm" onClick={() => avisar("Cadastro de " + g.titulo.toLowerCase() + " aberto.", "ok")}><Ic name="plus" size={12} /> Adicionar</button>
          </div>
        </Widget>
      ))}
    </div>
  );
}

// ─────────── Configurações (settings/index.blade.php) ───────────
function Config({ avisar }) {
  const { Widget, Fld } = UI();
  const [cfg, setCfg] = useState({ pedidos: true, prefixo: "SO-" });
  return (
    <Widget contrato="crm-config" titulo="Configurações do CRM">
      <div className="pb-grid c2">
        <label className="pb-chk">
          <input type="checkbox" checked={cfg.pedidos} onChange={(e) => setCfg({ ...cfg, pedidos: e.target.checked })} />
          <b>Ativar solicitação de pedido<small>Se ativado, o usuário de contato pode fazer uma solicitação de pedido no portal.</small></b>
        </label>
        <Fld label="Prefixo da solicitação de pedido"><input value={cfg.prefixo} onChange={(e) => setCfg({ ...cfg, prefixo: e.target.value })} placeholder="SO-" /></Fld>
      </div>
      <div className="pb-filters-h" style={{ marginTop: 14 }}>
        <div className="sp" />
        <button className="os-btn sm primary" onClick={() => avisar("Configurações do CRM atualizadas.", "ok")}>Atualizar</button>
      </div>
    </Widget>
  );
}

window.CrmBladeTelas = { Campanhas, Logins, Comissoes, Chamadas, Relatorios, Modelo, Propostas, Marketplace, Pedidos, Taxonomias, Config };
})();
