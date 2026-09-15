// suporte-page.jsx — Modo Suporte (ADR 0305/0308/0309 · /suporte/empresas · /suporte/empresas/{id}).
// Espelho do vivo resources/js/Pages/Suporte/{Empresas,Visao}.tsx. Três vistas:
//  · empresas — lista READ-ONLY das empresas-cliente acessíveis (a operadora biz=1 NUNCA aparece),
//    busca local por nome/ID, "Entrar (suporte)" por linha. PT-01 Lista variante lean.
//  · visao — contagens + todos os usuários, com "Acessar como" guardado (canImpersonate).
//  · log — support_access_logs (append-only): quem · em quem · qual empresa · quando (ONDA O5).
// Impersonando, a faixa "Voltar para mim" fica fixa no topo (vem do AppShellV2 no vivo).
// ONDA O2/O6: estado (dados/vazio/carregando/erro) · papel (agente/sem-acesso) · densidade.
// Expõe window.SuportePage.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const A = () => window.AcessosDS || {};

const EMPRESAS = [
  { id: 4, name: "ROTA LIVRE Comunicação Visual", plano: "Gold", desde: "03/2024", usuarios: 6, contatos: 312, produtos: 148, vendas: 2841, compras: 196 },
  { id: 164, name: "Martinho Oficina Mecânica", plano: "Gold", desde: "11/2025", usuarios: 4, contatos: 187, produtos: 96, vendas: 1204, compras: 88 },
  { id: 12, name: "Gráfica Vértice ME", plano: "Prata", desde: "08/2024", usuarios: 3, contatos: 94, produtos: 61, vendas: 733, compras: 51 },
  { id: 27, name: "WR Comunicação Visual", plano: "Gold", desde: "01/2025", usuarios: 5, contatos: 221, produtos: 133, vendas: 1608, compras: 122 },
  { id: 38, name: "Ateliê Bordado & Cia", plano: "Bronze", desde: "05/2025", usuarios: 2, contatos: 58, produtos: 44, vendas: 291, compras: 33 },
  { id: 71, name: "Placas Norte Sinalização", plano: "Prata", desde: "09/2025", usuarios: 3, contatos: 76, produtos: 52, vendas: 402, compras: 40 },
];

const USUARIOS = {
  4: [
    { id: 21, username: "larissa", nome: "Larissa Prado", papel: "Balcão", email: "larissa@rotalivre.com.br", pode: true },
    { id: 22, username: "eliana", nome: "Eliana Souza", papel: "Financeiro", email: "eliana@rotalivre.com.br", pode: true },
    { id: 23, username: "marcos", nome: "Marcos Vinícius", papel: "Produção", email: "marcos@rotalivre.com.br", pode: true },
    { id: 24, username: "rotalivre.admin", nome: "Rita Menezes", papel: "Admin", email: "rita@rotalivre.com.br", pode: true },
    { id: 25, username: "estagio.jp", nome: "João Pedro", papel: "Balcão", email: "jp@rotalivre.com.br", pode: false },
    { id: 2, username: "suporte.oi", nome: "Suporte Office Impresso", papel: "Superadmin", email: "suporte@oimpresso.com", pode: false },
  ],
};
const generico = (e) => [
  { id: e.id * 100 + 1, username: "admin", nome: "Titular da conta", papel: "Admin", email: "admin@empresa" + e.id + ".com.br", pode: true },
  { id: e.id * 100 + 2, username: "balcao", nome: "Atendimento", papel: "Balcão", email: "balcao@empresa" + e.id + ".com.br", pode: true },
  { id: e.id * 100 + 3, username: "financeiro", nome: "Financeiro", papel: "Financeiro", email: "fin@empresa" + e.id + ".com.br", pode: false },
];

const LOG = [
  { id: 4412, quando: "24/08/2026 09:58", agente: "suporte.ana", alvo: "larissa", empresa: "ROTA LIVRE Comunicação Visual", biz: 4, dur: "12 min", motivo: "venda não fechava com cliente trocado" },
  { id: 4411, quando: "24/08/2026 08:31", agente: "suporte.ana", alvo: "—", empresa: "Martinho Oficina Mecânica", biz: 164, dur: "3 min", motivo: "leitura da visão do cliente" },
  { id: 4409, quando: "23/08/2026 17:12", agente: "suporte.bruno", alvo: "eliana", empresa: "ROTA LIVRE Comunicação Visual", biz: 4, dur: "26 min", motivo: "conciliação sem baixa do boleto" },
  { id: 4404, quando: "23/08/2026 11:40", agente: "suporte.bruno", alvo: "admin", empresa: "Gráfica Vértice ME", biz: 12, dur: "8 min", motivo: "certificado A1 vencido" },
  { id: 4398, quando: "22/08/2026 15:03", agente: "suporte.ana", alvo: "balcao", empresa: "Placas Norte Sinalização", biz: 71, dur: "17 min", motivo: "etiqueta saindo cortada" },
];

const CARDS = [["usuarios", "Usuários"], ["contatos", "Contatos"], ["produtos", "Produtos"], ["vendas", "Vendas"], ["compras", "Compras"]];

const IcShield = () => <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l8 3v6c0 5-3.5 7.7-8 9-4.5-1.3-8-4-8-9V6z"/><path d="M12 3v18"/></svg>;
const IcBuilding = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h6"/></svg>;
const IcBack = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>;
const IcLogin = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/></svg>;
const IcLock = () => <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>;

function Btn({ children, ...p }) {
  const { Button } = DS();
  if (!Button) return <button className={"os-btn " + (p.variant === "primary" ? "primary" : "ghost")} disabled={p.disabled} onClick={p.onClick}>{children}</button>;
  return <Button {...p}>{children}</Button>;
}
function Busca({ value, onChange, placeholder, label }) {
  const { Input } = DS();
  if (Input) return <div className="sup-busca"><Input value={value} onChange={onChange} placeholder={placeholder} aria-label={label} /></div>;
  return <div className="sup-busca"><input value={value} onChange={onChange} placeholder={placeholder} aria-label={label} /></div>;
}

function ListaEmpresas({ estado, onEntrar, onLog }) {
  const { PageHeader, DataTable, StatusBadge, Skeleton } = DS();
  const { Vazio, Nota } = A();
  const [q, setQ] = useState("");
  const termo = q.trim().toLowerCase();
  const base = estado === "vazio" ? [] : EMPRESAS;
  const lista = useMemo(() => termo ? base.filter((e) => e.name.toLowerCase().includes(termo) || String(e.id).includes(termo)) : base, [termo, base]);

  const colunas = [
    { key: "empresa", label: "Empresa" },
    { key: "plano", label: "Plano", width: 110 },
    { key: "desde", label: "Cliente desde", width: 140, mono: true },
    { key: "id", label: "ID", width: 90, align: "right", mono: true },
    { key: "acao", label: "", width: 160, align: "right" },
  ];
  const linhas = lista.map((e) => ({
    id: e.id,
    cells: {
      empresa: <span className="sup-emp"><span className="sup-emp-ic" aria-hidden="true"><IcBuilding /></span><span className="sup-emp-m"><b>{e.name}</b><small>{e.usuarios} usuários · {e.contatos} contatos</small></span></span>,
      plano: StatusBadge ? <StatusBadge tone={e.plano === "Gold" ? "success" : "neutral"} label={e.plano} /> : <span>{e.plano}</span>,
      desde: e.desde,
      id: "#" + e.id,
      acao: <Btn variant="primary" size="sm" onClick={() => onEntrar(e)}><IcLogin /> Entrar (suporte)</Btn>,
    },
  }));

  const sub = "Empresas-cliente que você pode atender — a empresa operadora não aparece.";
  const acoes = <div className="sup-h-acts"><Btn onClick={onLog}>Log de acessos</Btn><Busca value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar empresa…" label="Buscar empresa" /></div>;
  return (
    <>
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Suporte · empresas" subtitle={sub} actions={acoes} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Suporte · empresas</h1><p>{sub}</p></div><div className="os-page-h-r">{acoes}</div></header>}
      </div>

      {estado === "erro"
        ? (Vazio && <Vazio variant="error" title="Não foi possível resolver suas empresas."
            description="O SupportAccessService não respondeu. Nenhum acesso foi aberto — recarregue; se persistir, sua concessão pode ter expirado." />)
        : estado === "carregando"
          ? <div className="sup-lista">{Skeleton ? <Skeleton variant="row" count={5} /> : <p>Carregando…</p>}</div>
          : <>
            {Nota &&
              <div className="sup-nota" data-contract="aviso-escopo">
                <Nota tone="warn" title="Somente leitura">
                  Esta lista existe pra você <b>escolher quem atender</b> — nada é editado aqui. A autorização e a auditoria ficam no middleware <code>EnsureSupportAccess</code>, e a operadora (<code>biz=1</code>) é bloqueada por desenho.
                </Nota>
              </div>}
            <div className="sup-lista" data-contract="lista">
              {base.length === 0
                ? (Vazio ? <Vazio variant="first" title="Nenhuma empresa-cliente acessível."
                    description="Sua conta é de agente, mas não há concessão ativa em support_agents. Quem libera é a operadora, empresa por empresa." /> : <p>Nenhuma empresa-cliente acessível.</p>)
                : lista.length === 0
                  ? (Vazio ? <Vazio variant="no-results" title={`Nada para “${q}”.`} description="Busque pelo nome da empresa ou pelo ID do negócio." /> : <p>Nada para “{q}”.</p>)
                  : DataTable
                    ? <DataTable columns={colunas} rows={linhas} onRowClick={(r) => onEntrar(base.find((e) => e.id === r.id))} />
                    : <div className="os-table-wrap"><table className="os-table"><tbody>{lista.map((e) => <tr key={e.id}><td>{e.name}</td><td className="mono">#{e.id}</td></tr>)}</tbody></table></div>}
            </div>
          </>}
    </>
  );
}

function Visao({ empresa, onVoltar, onLog, onImpersonar }) {
  const { PageHeader, DataTable, StatusBadge, Tooltip } = DS();
  const { Vazio, Confirm } = A();
  const [q, setQ] = useState("");
  const [confirmar, setConfirmar] = useState(null);
  const usuarios = USUARIOS[empresa.id] || generico(empresa);
  const termo = q.trim().toLowerCase();
  const lista = useMemo(() => termo ? usuarios.filter((u) => (u.username + u.nome + u.email).toLowerCase().includes(termo)) : usuarios, [termo, usuarios]);

  const colunas = [
    { key: "username", label: "Username", width: 190 },
    { key: "nome", label: "Nome" },
    { key: "papel", label: "Papel", width: 150 },
    { key: "email", label: "E-mail", width: 250 },
    { key: "acao", label: "", width: 170, align: "right" },
  ];
  const linhas = lista.map((u) => ({
    id: u.id,
    state: u.pode ? undefined : "archived",
    cells: {
      username: <b className="mono">{u.username}</b>,
      nome: u.nome || "—",
      papel: StatusBadge ? <StatusBadge tone="neutral" label={u.papel} /> : <span>{u.papel}</span>,
      email: <span className="sup-mail">{u.email}</span>,
      acao: u.pode
        ? <Btn variant="primary" size="sm" onClick={() => setConfirmar(u)}><IcLogin /> Acessar como</Btn>
        : (Tooltip
            ? <Tooltip content="Operador, superadmin ou usuário inativo — fora do alcance do Modo Suporte"><span className="sup-indisp"><IcLock /> indisponível</span></Tooltip>
            : <span className="sup-indisp"><IcLock /> indisponível</span>),
    },
  }));

  const sub = `Empresa-cliente #${empresa.id} · plano ${empresa.plano} · acesso auditado`;
  return (
    <>
      <div className="sup-navrow">
        <button className="sup-back" onClick={onVoltar}><IcBack /> Suporte · empresas</button>
        <button className="sup-back" onClick={onLog}>Log de acessos</button>
      </div>

      <div className="sup-faixa" data-contract="faixa-modo-suporte">
        <span className="sup-faixa-ic" aria-hidden="true"><IcShield /></span>
        <p><b>Modo Suporte.</b> A empresa operadora não aparece. <b>“Acessar como”</b> loga você como aquele usuário — você atua no lugar dele até clicar em “Voltar para mim”. Cada acesso é auditado.</p>
      </div>

      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title={empresa.name} subtitle={sub}
              actions={<Busca value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar usuário…" label="Buscar usuário" />} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>{empresa.name}</h1><p>{sub}</p></div></header>}
      </div>

      <div className="sup-cards" data-contract="contagens">
        {CARDS.map(([k, l]) => (
          <div className="sup-card" key={k}>
            <span className="sup-card-l">{l}</span>
            <b className="mono">{(empresa[k] || 0).toLocaleString("pt-BR")}</b>
          </div>
        ))}
      </div>

      <h2 className="sup-h2">Todos os usuários</h2>
      <div className="sup-lista" data-contract="usuarios">
        {lista.length === 0
          ? (Vazio ? <Vazio variant="no-results" title={`Nada para “${q}”.`} description="Busque por username, nome ou e-mail." /> : <p>Nada para “{q}”.</p>)
          : DataTable
            ? <DataTable columns={colunas} rows={linhas} />
            : <div className="os-table-wrap"><table className="os-table"><tbody>{lista.map((u) => <tr key={u.id}><td className="mono">{u.username}</td><td>{u.nome}</td></tr>)}</tbody></table></div>}
      </div>

      <p className="sup-fine"><IcLock /> Cada “Acessar como” grava em <code>support_access_logs</code> (append-only): quem · qual usuário · qual empresa · quando.</p>

      {Confirm &&
        <Confirm open={!!confirmar} title={confirmar ? `Acessar como ${confirmar.username}?` : ""} cta="Acessar como" ctaTone="primary"
          onClose={() => setConfirmar(null)}
          onConfirm={() => { const u = confirmar; setConfirmar(null); onImpersonar(u); }}>
          {confirmar && <>
            <p>Você vai operar <b>como {confirmar.nome || confirmar.username}</b> em <b>{empresa.name}</b> até clicar em “Voltar para mim”.</p>
            <p className="sup-modal-alt">A ação é registrada em <code>support_access_logs</code> — quem entrou, em quem, quando. Não dá pra apagar depois.</p>
          </>}
        </Confirm>}
    </>
  );
}

function LogAcessos({ onVoltar }) {
  const { PageHeader, DataTable, StatusBadge } = DS();
  const colunas = [
    { key: "quando", label: "Quando", width: 170, mono: true },
    { key: "agente", label: "Agente", width: 150 },
    { key: "alvo", label: "Acessou como", width: 150 },
    { key: "empresa", label: "Empresa" },
    { key: "motivo", label: "Motivo declarado" },
    { key: "dur", label: "Duração", width: 100, align: "right", mono: true },
  ];
  const linhas = LOG.map((l) => ({
    id: l.id,
    cells: {
      quando: l.quando,
      agente: <b className="mono">{l.agente}</b>,
      alvo: l.alvo === "—" ? <span className="sup-indisp">só leitura</span> : (StatusBadge ? <StatusBadge tone="warning" label={l.alvo} /> : <b>{l.alvo}</b>),
      empresa: <span className="sup-emp-m"><b>{l.empresa}</b><small className="mono">#{l.biz}</small></span>,
      motivo: l.motivo,
      dur: l.dur,
    },
  }));
  return (
    <>
      <div className="sup-navrow"><button className="sup-back" onClick={onVoltar}><IcBack /> Suporte · empresas</button></div>
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Log de acessos de suporte" subtitle="support_access_logs · append-only — não existe caminho de escrita nem de correção" />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Log de acessos de suporte</h1></div></header>}
      </div>
      <div className="sup-lista" data-contract="log">
        {DataTable ? <DataTable columns={colunas} rows={linhas} /> : <div className="os-table-wrap"><table className="os-table"><tbody>{LOG.map((l) => <tr key={l.id}><td className="mono">{l.quando}</td><td>{l.agente}</td><td>{l.empresa}</td></tr>)}</tbody></table></div>}
      </div>
      <p className="sup-fine"><IcLock /> Alterar uma linha daqui é incidente P0 (ADR 0084): a tabela é append-only por trigger. A tela nem oferece o botão.</p>
    </>
  );
}

function SuportePage({ view = "empresas", estado = "dados", papel = "agente", dense = false }) {
  const { PageHeader } = DS();
  const { Vazio } = A();
  const [empresa, setEmpresa] = useState(view === "visao" ? EMPRESAS[0] : null);
  const [tela, setTela] = useState(view === "log" ? "log" : "lista");
  const [comoUsuario, setComoUsuario] = useState(null);
  const cls = "os-page sup-page" + (dense ? " dense" : "");

  if (papel === "sem-acesso") {
    return (
      <div className={cls} data-screen-label="Sistema · Suporte">
        {PageHeader && <PageHeader title="Suporte" subtitle="acesso negado" />}
        {Vazio && <Vazio variant="no-perm" title="Você não é agente de suporte."
          description="O Modo Suporte é do time da operadora ou de quem tem concessão ativa em support_agents. Ser Admin da sua empresa não dá esse acesso (ADR 0309)." />}
      </div>
    );
  }

  return (
    <div className={cls} data-screen-label="Sistema · Suporte">
      {comoUsuario &&
        <div className="sup-imperso" data-contract="voltar-para-mim">
          <span className="sup-imperso-ic" aria-hidden="true"><IcShield /></span>
          <p>Você está operando como <b className="mono">{comoUsuario.username}</b> em <b>{empresa?.name}</b>. Tudo o que fizer sai no nome dele.</p>
          <Btn variant="primary" size="sm" onClick={() => setComoUsuario(null)}>Voltar para mim</Btn>
        </div>}

      {tela === "log"
        ? <LogAcessos onVoltar={() => setTela("lista")} />
        : empresa
          ? <Visao empresa={empresa} onVoltar={() => setEmpresa(null)} onLog={() => setTela("log")} onImpersonar={setComoUsuario} />
          : <ListaEmpresas estado={estado} onEntrar={setEmpresa} onLog={() => setTela("log")} />}
    </div>
  );
}

window.SuportePage = SuportePage;
})();
