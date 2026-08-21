// connector-page.jsx — módulo Conector (API) no app único. Espelha o que existe de UI no
// Modules/Connector do main: clients/index.blade.php (lista de clients OAuth + modal de criação +
// excluir + "regenerar") lido junto de ClientController, StoreOauthClientRequest, DataController
// (menu + permissão connector.access), Routes/web.php (throttle 60,1 e 30,1) e InstallController
// (passport:install --force pós-migração). Documentação e saúde vivem em connector-api.jsx.
// Views: clients · docs · saude · modulo — todas dentro desta rota, nenhum .html novo.
// DS vivo via acessos-ds.jsx (Kpi · Nota · Confirm · Vazio) + Modal/Input do bundle onde cabe;
// tabela, botões, busca e kebab seguem do shell de propósito.
// Expõe window.ConnectorPage.
(() => {
const { useState, useEffect, useMemo, useRef } = React;
const I = window.I;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const ADS = () => window.AcessosDS || {};
const API = () => window.ConnectorApi || {};

const VIEWS = [
  { id:"clients", label:"API clients" },
  { id:"docs", label:"Documentação" },
  { id:"saude", label:"Saúde" },
  { id:"modulo", label:"Módulo" },
];

const seg = (n) => Array.from({ length: n }, () => "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"[Math.floor(Math.random() * 62)]).join("");

// O segredo não vive no estado da tela: o banco guarda hash e a lista nunca o recebe.
const CLIENTS0 = [
  { id:3, name:"WR Comercial — balcão ROTA LIVRE", user:"Wagner", criado:"04/02/2026", tokens:11 },
  { id:5, name:"Delphi Oficina Martinho", user:"Wagner", criado:"18/03/2026", tokens:3 },
  { id:7, name:"App do técnico (Android)", user:"Wagner", criado:"09/06/2026", tokens:0 },
];

function Kebab({ items }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="cli-kebab-wrap" ref={ref}>
      <button className="cli-kebab-btn" onClick={(e) => { e.stopPropagation(); setOpen(!open); }} aria-expanded={open} title="Ações do client">
        <I.moreV size={14}/>
      </button>
      {open && (
        <div className="cli-kebab-menu" onClick={(e) => e.stopPropagation()}>
          {items.map((it, i) => it.sep
            ? <div key={i} className="cli-kebab-sep"></div>
            : <button key={i} className={it.danger ? "danger" : ""} onClick={() => { setOpen(false); it.action?.(); }}>{it.label}</button>)}
        </div>
      )}
    </div>
  );
}

// Segredo NUNCA é exibível depois da criação ([W] 2026-08-19): a tela não tem caminho de leitura.
// O valor continua guardado e válido — o Delphi em campo autentica pra sempre com a credencial dele.
function Segredo() {
  return (
    <span className="cnx-secret">
      <span className="cnx-hash mono">•••• guardado</span>
      <span className="cnx-secret-nota">não é exibível</span>
    </span>
  );
}

// ── Criar client (StoreOauthClientRequest: name obrigatório, até 191 caracteres) ──
function NovoClient({ open, onClose, onSalvar, nomes }) {
  const { Modal } = ds();
  const [nome, setNome] = useState("");
  const [tocado, setTocado] = useState(false);
  const [tentou, setTentou] = useState(false);
  useEffect(() => { if (open) { setNome(""); setTocado(false); setTentou(false); } }, [open]);
  if (!open) return null;
  const limpo = nome.trim();
  const erro = limpo.length === 0 ? "O nome do client OAuth é obrigatório."
    : limpo.length > 191 ? "O nome do client não pode ultrapassar 191 caracteres." : null;
  const dup = !erro && nomes.some((n) => n.toLowerCase() === limpo.toLowerCase());
  const corpo = (
    <div className="cnx-form" data-contract="novo-client-form">
      <label className="cnx-lbl" htmlFor="cnx-nome">Nome</label>
      <input id="cnx-nome" className="dsfa cnx-input" value={nome} maxLength={220} autoFocus
        onChange={(e) => { setNome(e.target.value); setTocado(true); }}
        placeholder="Onde essa credencial vai rodar" aria-invalid={(tocado || tentou) && !!erro ? "true" : undefined}/>
      <p className="cnx-help">Use o nome do lugar que consome a API — “WR Comercial — balcão”, “App do técnico”. É o que aparece na lista quando você precisar revogar.</p>
      {erro && (tocado || tentou) && <p className="cnx-err">{erro}</p>}
      {dup && <p className="cnx-warn-inline">Já existe um client com esse nome. O legado aceita duplicado — depois ninguém sabe qual revogar.</p>}
      <div className="cnx-fixed">
        <div><span className="cnx-fixed-l">Redirecionamento</span><code className="mono">http://localhost</code></div>
        <div><span className="cnx-fixed-l">Tipo</span><code className="mono">password_client</code></div>
      </div>
      <p className="cnx-help">Os dois campos são fixos no código — o client é de senha (o app troca usuário e senha por token), não de autorização por navegador.</p>
      <p className="cnx-help">Ao salvar, o segredo aparece <b>uma única vez</b>: depois disso nenhuma tela do sistema mostra esse valor — nem para você. A credencial continua valendo no app; o que deixa de existir é o caminho de leitura.</p>
    </div>
  );
  const foot = (
    <>
      <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
      <button className="os-btn primary" onClick={() => { if (erro) { setTentou(true); return; } onSalvar(limpo); onClose(); }}>Salvar</button>
    </>
  );
  if (!Modal) return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="usr-modal"><h2>Criar API client</h2>{corpo}<div className="usr-modal-f">{foot}</div></div>
    </>
  );
  return <Modal open={open} onClose={onClose} title="Criar API client" footer={foot}>{corpo}</Modal>;
}

function ClientsView({ toast, cenario }) {
  const A = ADS();
  const [rows, setRows] = useState(CLIENTS0);
  const [q, setQ] = useState("");
  const [novo, setNovo] = useState(false);
  const [excluir, setExcluir] = useState(null);
  const [criado, setCriado] = useState(null);
  const buscaRef = useRef(null);

  useEffect(() => {
    const k = (e) => {
      const digitando = /^(INPUT|TEXTAREA)$/.test(e.target.tagName);
      if (e.key === "/" && !digitando) { e.preventDefault(); buscaRef.current?.focus(); }
      if (e.key === "n" && !digitando && cenario !== "demo") { e.preventDefault(); setNovo(true); }
    };
    document.addEventListener("keydown", k);
    return () => document.removeEventListener("keydown", k);
  }, [cenario]);

  const demo = cenario === "demo";
  const base = cenario === "vazio" || demo ? [] : rows;
  const busca = q.trim().toLowerCase();
  const lista = base.filter((r) => !busca || (r.name + " " + r.id).toLowerCase().includes(busca));
  const tokens = base.reduce((n, r) => n + r.tokens, 0);

  const copiar = (txt, msg) => { try { navigator.clipboard?.writeText(txt); } catch (e) {} toast(msg); };

  const salvar = (nome) => {
    const id = Math.max(...rows.map((r) => r.id)) + 2;
    const s = seg(40);
    setRows((rs) => [...rs, { id, name:nome, user:"Wagner", criado:new Date().toLocaleDateString("pt-BR"), tokens:0 }]);
    setCriado({ id, name:nome, secret:s });
    toast("API client criado.");
  };

  return (
    <>
      {A.Nota && demo && (
        <div data-contract="aviso-demo"><A.Nota tone="warn" title="Desligado na demonstração">
          Este ambiente é de demonstração: nenhuma credencial é listada e nada pode ser emitido. É a mesma recusa do servidor quando <span className="mono">APP_ENV=demo</span> — segredo de OAuth nunca sai numa base pública.
        </A.Nota></div>
      )}

      {A.Nota && !demo && (
        <div data-contract="aviso-permissao"><A.Nota tone="info" title="Só superadmin emite credencial de API">
          Decisão de [W] em 19/08/2026: emitir e revogar credencial da API não se delega. A permissão <span className="mono">connector.access</span>, que está no catálogo do módulo e nunca foi verificada, sai do código.
        </A.Nota></div>
      )}

      {A.Kpis && (<div data-contract="kpis">
        <A.Kpis>
          <A.Kpi l="Clients ativos" v={base.length} sub="somente deste negócio"/>
          <A.Kpi l="Tokens ativos em 24 h" v={tokens} sub="emitidos pelo Passport" tone="info"/>
          <A.Kpi l="Endpoints publicados" v={API().TOTAL || 0} sub="prefixo connector/api"/>
          <A.Kpi l="Limite por token" v="120" unit="req/min" sub="throttle da API externa"/>
        </A.Kpis></div>
      )}

      <div className="mod-toolbar" data-contract="toolbar">
        <div className="usr-search">
          <I.search size={15}/>
          <input ref={buscaRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar client por nome ou id…" aria-label="Buscar client"/>
          {q && <button className="mod-search-x" onClick={() => setQ("")} aria-label="Limpar busca">×</button>}
        </div>
        <div className="usr-filters">
          <span className="mod-kbd-hint"><kbd>n</kbd> novo client · <kbd>/</kbd> buscar</span>
          <button className="os-btn primary" disabled={demo} title={demo ? "Desligado na demonstração" : undefined} onClick={() => setNovo(true)}>Criar API client</button>
        </div>
      </div>

      {criado && (
        <div className="cnx-novo" data-contract="credencial-criada">
          <div className="cnx-novo-h"><b>Client “{criado.name}” criado</b>
            <button className="cnx-mini" onClick={() => setCriado(null)}>Fechar</button></div>
          <div className="cnx-novo-g">
            <div><span className="cnx-novo-l">client_id</span><code className="mono">{criado.id}</code>
              <button className="cnx-mini" onClick={() => copiar(String(criado.id), "client_id copiado.")}><I.copy size={12}/></button></div>
            <div><span className="cnx-novo-l">client_secret</span><code className="mono">{criado.secret}</code>
              <button className="cnx-mini" onClick={() => copiar(criado.secret, "Segredo copiado.")}><I.copy size={12}/></button></div>
          </div>
          <p className="cnx-warn-inline">Esta é a única vez que o segredo aparece. Copie e guarde no lugar seguro do app: nenhuma tela mostra esse valor de novo — nem para o administrador. Perdeu, emite outra e exclui esta.</p>
        </div>
      )}

      <div className="os-table-wrap">
        {lista.length > 0 && (
        <table className="os-table cnx-clients" data-contract="clients-table">
          <thead><tr>
            <th className="cnx-th-id">ID</th><th>Nome</th><th>Segredo</th>
            <th className="cnx-th-tk">Tokens 24 h</th><th className="cnx-th-cr">Criado</th><th className="cnx-th-act"></th>
          </tr></thead>
          <tbody>
            {lista.map((r) => (
              <tr key={r.id}>
                <td className="mono">{r.id}</td>
                <td><div className="cnx-name">{r.name}</div><div className="mod-meta">por {r.user} · redirecionamento http://localhost</div></td>
                <td><Segredo/></td>
                <td className="tabular">{r.tokens > 0 ? r.tokens : <span className="mod-mig-none">—</span>}</td>
                <td className="tabular">{r.criado}</td>
                <td className="mod-td-act">
                  <Kebab items={[
                    { label: "Copiar client_id", action: () => copiar(String(r.id), "client_id copiado.") },
                    { label: "Emitir credencial nova", action: () => { setNovo(true); toast("Emita a nova, ponha no app e só então exclua a antiga."); } },
                    { sep: true },
                    { label: "Excluir client", danger: true, action: () => setExcluir(r) },
                  ]}/>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        )}
        {lista.length === 0 && (
          <div className="cnx-vazio" data-contract="vazio">
            {demo ? (
              A.Vazio
                ? <A.Vazio variant="no-perm" title="Credenciais desligadas na demonstração"
                    description="Nesta base pública nenhuma credencial de API é listada nem emitida. Em produção, esta é a tela onde o superadmin cria e revoga o acesso dos apps externos."/>
                : <div className="mod-empty"><b>Credenciais desligadas na demonstração</b></div>
            ) : base.length === 0 ? (
              A.Vazio
                ? <A.Vazio variant="first" title="Nenhuma credencial emitida ainda"
                    description="Um API client é a credencial que um programa de fora usa pra entrar aqui — o WR Comercial no balcão, o aplicativo do técnico, uma planilha que puxa vendas. Cada um recebe o seu, pra você poder cortar um sem derrubar os outros."
                    action={<button className="os-btn primary" onClick={() => setNovo(true)}>Criar o primeiro API client</button>}/>
                : <div className="mod-empty"><b>Nenhuma credencial emitida ainda</b>
                    <button className="os-btn primary" onClick={() => setNovo(true)}>Criar o primeiro API client</button></div>
            ) : (
              A.Vazio
                ? <A.Vazio variant="no-results" title={`Nenhum client casa com “${q}”`}
                    description="A busca cobre nome e id."
                    action={<button className="os-btn" onClick={() => setQ("")}>Limpar busca</button>}/>
                : <div className="mod-empty"><b>Nenhum client casa com “{q}”.</b>
                    <button className="os-btn" onClick={() => setQ("")}>Limpar busca</button></div>
            )}
          </div>
        )}
      </div>

      <NovoClient open={novo} onClose={() => setNovo(false)} onSalvar={salvar} nomes={rows.map((r) => r.name)}/>

      {A.Confirm && (
        <A.Confirm open={!!excluir} title="Excluir este API client?" cta="Excluir client"
          onClose={() => setExcluir(null)}
          onConfirm={() => { setRows((rs) => rs.filter((r) => r.id !== excluir.id)); toast(`Client “${excluir.name}” excluído.`); }}>
          <p className="cnx-note">O client <b>{excluir?.name}</b> sai da lista e ninguém mais consegue pedir token novo com ele.</p>
          <p className="cnx-warn-inline">{excluir?.tokens > 0
            ? `Os ${excluir.tokens} acessos abertos com esta credencial caem na hora — a exclusão revoga os tokens junto (decisão de [W] em 19/08/2026).`
            : "Nenhum acesso aberto com esta credencial agora — nada cai junto."}</p>
        </A.Confirm>
      )}

    </>
  );
}

// ── Módulo: estado, instalação (com o passo do Passport) e os achados da leitura ──
const ACHADOS = [
  { t:"Segredo em texto puro na lista", d:"A tabela do legado imprime client_secret direto no HTML e o controller chama makeVisible('secret'). [W] decidiu que ninguém vê segredo — nem o administrador: makeVisible sai, a coluna vira selo e o valor só aparece na resposta da criação. Sem hashClientSecrets: as credenciais em campo (Delphi/WR Comercial) precisam continuar autenticando pra sempre." },
  { t:"Permissão órfã no catálogo", d:"DataController declara connector.access e nenhuma checagem usa. [W] decidiu manter tudo em superadmin — a permissão sai do catálogo pra não sugerir delegação que não existe." },
  { t:"Excluir não revoga token", d:"destroy apaga a linha de oauth_clients; oauth_access_tokens fica intacto. Token vivo continua entrando até vencer." },
  { t:"Regenerar chaves não é ação de tela", d:"O botão “Regenerate doc” executa passport:install --force (a geração de documentação está comentada) e derruba a integração de todos os negócios. [W] decidiu tirar da tela: passa a ser comando de operação, e a rota GET /connector/regenerate sai." },
  { t:"Sem confirmação de exclusão", d:"O formulário de excluir do Blade não tem confirmação nem aviso de consequência." },
  { t:"Rota de criação sem tela", d:"O menu do módulo aponta o primário “Novo API client” para /connector/client/create, e o create() do controller devolve uma view que não existe no módulo." },
  { t:"Segredo não se troca — se revoga", d:"Não existe rotação de segredo por client, e [W] decidiu que não vai existir: credencial comprometida é excluída e emitida de novo. O contrato do desktop em campo não pode ser alterado." },
];

function ModuloView({ toast }) {
  const A = ADS();
  const [instalado, setInstalado] = useState(true);
  const [conf, setConf] = useState(null);
  return (
    <>
      {A.Nota && (
        <A.Nota tone="info" title="Instalação tem um passo extra">
          Depois das migrações, o instalador do Conector roda <span className="mono">passport:install --force</span> — é isso que cria as chaves de OAuth da API. Instalar de novo em ambiente com apps em campo derruba as credenciais.
        </A.Nota>
      )}
      <div className="cnx-cards">
        <section className="cnx-card" data-contract="modulo-estado">
          <h3>Estado</h3>
          <dl className="cnx-dl">
            <div><dt>Módulo</dt><dd className="mono">Connector · connector</dd></div>
            <div><dt>Versão</dt><dd className="mono">2.0</dd></div>
            <div><dt>Migrações</dt><dd className="mono">2</dd></div>
            <div><dt>Situação</dt><dd><span className={`mod-badge ${instalado ? "active" : "inactive"}`}>{instalado ? "Instalado" : "Não instalado"}</span></dd></div>
            <div><dt>Endpoints</dt><dd className="mono">{API().TOTAL || 0}</dd></div>
            <div><dt>Área</dt><dd>Integrações</dd></div>
          </dl>
          <div className="cnx-acts">
            {instalado
              ? <>
                  <button className="os-btn" onClick={() => setConf("update")}>Atualizar</button>
                  <button className="os-btn danger" onClick={() => setConf("uninstall")}>Desinstalar</button>
                </>
              : <button className="os-btn primary" onClick={() => setConf("install")}>Instalar</button>}
          </div>
        </section>
        <section className="cnx-card">
          <h3>Onde o módulo aparece</h3>
          <ul className="cnx-ul">
            <li><b>API clients</b> — a lista desta tela, o único CRUD com interface no módulo.</li>
            <li><b>Documentação</b> — no legado, um link para <span className="mono">/docs</span>; aqui o catálogo é lido do arquivo de rotas.</li>
            <li><b>Pacote de assinatura</b> — <span className="mono">connector_module</span> é item de pacote no superadmin: sem ele, o negócio não vê o menu.</li>
            <li><b>Rotina diária</b> — <span className="mono">connector:health</span> às 06:15, com os três checks da aba Saúde.</li>
            <li><b>Chaves do Passport</b> — regenerar é operação de servidor (<span className="mono">passport:install --force</span>), fora do painel: derruba todo app externo, em todos os negócios.</li>
            <li><b>Segredo não se lê</b> — decisão de [W]: ninguém consulta segredo de client, nem o administrador. Aparece uma vez na criação; perdido, emite-se outro. O valor continua guardado e válido — credencial em campo não para de autenticar.</li>
          </ul>
        </section>
      </div>

      <section className="cnx-group">
        <header className="cnx-group-h" data-contract="modulo-achados"><h3>Achados da leitura do módulo<span className="cnx-group-n mono">{ACHADOS.length}</span></h3>
          <p>Cada item saiu do código lido no repositório, não de suposição. Viram regra de charter e pedido pro Code.</p></header>
        <ol className="cnx-achados">
          {ACHADOS.map((a, i) => (
            <li key={i}><b>{a.t}</b><p>{a.d}</p></li>
          ))}
        </ol>
      </section>

      {A.Confirm && (
        <A.Confirm open={!!conf} title={conf === "uninstall" ? "Desinstalar o Conector?" : conf === "update" ? "Atualizar o Conector?" : "Instalar o Conector?"}
          cta={conf === "uninstall" ? "Desinstalar" : conf === "update" ? "Atualizar" : "Instalar"}
          ctaTone={conf === "uninstall" ? "danger" : "primary"}
          onClose={() => setConf(null)}
          onConfirm={() => {
            if (conf === "uninstall") { setInstalado(false); toast("Conector desinstalado. A API externa para de responder."); }
            else if (conf === "install") { setInstalado(true); toast("Conector instalado. Chaves de OAuth geradas."); }
            else toast("Conector atualizado."); }}>
          {conf === "uninstall"
            ? <p className="cnx-warn-inline">Todos os endpoints <span className="mono">connector/api</span> deixam de responder — o WR Comercial em campo para de licenciar e de sincronizar.</p>
            : <p className="cnx-note">Roda as 2 migrações e, em seguida, gera as chaves de OAuth. Apps que já têm credencial precisam pegar credencial nova.</p>}
        </A.Confirm>
      )}
    </>
  );
}

const CENARIOS = [
  { id:"normal", label:"Com credenciais" },
  { id:"vazio", label:"Primeira vez" },
  { id:"demo", label:"Demonstração" },
];

function ConnectorPage({ view = "clients" }) {
  const [aba, setAba] = useState(view);
  const [cenario, setCenario] = useState("normal");
  useEffect(() => setAba(view), [view]);
  const [toast, setToast] = useState(null);
  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 2600);
    return () => clearTimeout(t);
  }, [toast]);
  const A = API();
  const titulo = { clients:"Conector — API clients", docs:"Conector — documentação da API", saude:"Conector — saúde", modulo:"Conector — módulo" }[aba];

  return (
    <div className="os-page cnx-page" data-screen-label={`Conector · ${VIEWS.find((v) => v.id === aba)?.label || aba}`}>
      <header className="os-page-h" data-contract="page-header">
        <div className="os-page-h-l">
          <h1>{titulo}</h1>
          <p className="tabular">{A.TOTAL || 0} endpoints · OAuth do Passport · 120 req/min por token</p>
        </div>
        <div className="os-page-h-r">
          {aba === "clients" && (
            <div className="cnx-seg" role="group" aria-label="Estado da tela">
              {CENARIOS.map((c) => (
                <button key={c.id} className={cenario === c.id ? "on" : ""} aria-pressed={cenario === c.id}
                  onClick={() => setCenario(c.id)}>{c.label}</button>
              ))}
            </div>
          )}
          <span className="mod-scope">superadmin · cross-tenant</span>
        </div>
      </header>

      <nav className="cnx-tabs" role="tablist" data-contract="tabs">
        {VIEWS.map((v) => (
          <button key={v.id} role="tab" aria-selected={aba === v.id}
            className={`cnx-tab ${aba === v.id ? "on" : ""}`} onClick={() => setAba(v.id)}>{v.label}</button>
        ))}
      </nav>

      <div className="cnx-body">
        {aba === "clients" && <ClientsView key={cenario} toast={setToast} cenario={cenario}/>}
        {aba === "docs" && A.DocsView && <A.DocsView/>}
        {aba === "saude" && A.SaudeView && <A.SaudeView/>}
        {aba === "modulo" && <ModuloView toast={setToast}/>}
      </div>

      {toast && <div className="mod-toast">{toast}</div>}
    </div>
  );
}

window.ConnectorPage = ConnectorPage;
})();
