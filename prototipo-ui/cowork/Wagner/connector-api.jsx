// connector-api.jsx — catálogo da API + saúde do módulo Conector.
// Fonte lida no main NESTE turno: Modules/Connector/Routes/api.php (grupos core · crm · fieldforce
// com throttle:120,1 + log.delphi + auth:api), Services/DelphiSyncService.php (3 formatos de body
// e os contratos literais de resposta) e Console/Commands/ConnectorHealthCommand.php (3 checks).
// Nada aqui é invenção de endpoint: cada linha tem rota + controller do arquivo de rotas.
// Expõe window.ConnectorApi = { GRUPOS, TOTAL, DocsView, SaudeView }.
(() => {
const { useState, useMemo } = React;
const I = window.I;
const ADS = () => window.AcessosDS || {};

const GRUPOS = [
  { id:"delphi", label:"Delphi · WR Comercial", desc:"Fluxo de licenciamento do sistema desktop. O Delphi parsa a resposta literal — mudar o formato quebra o cliente em campo (ADR 0021).", eps:[
    { m:"POST", p:"processa-dados-cliente", c:"LicencaComputadorController@ProcessaDadosCliente", o:"Body: array com NOME_TABELA EMPRESA + LICENCIAMENTO. Resposta texto: S;liberado ou N;motivo" },
    { m:"POST", p:"salvar-cliente", c:"BusinessController@saveBusiness", o:"Cria ou atualiza o negócio a partir do CNPJ" },
    { m:"POST", p:"salvar-equipamento/{business_id}", c:"LicencaComputadorController@saveEquipamento", o:"Vincula o serial do HD ao negócio informado na URL" },
    { m:"POST", p:"oimpresso/registrar", c:"OImpressoRegistroController@registrar", o:"WR Comercial atual: body JSON plano. Resposta JSON: autorizado · licenca_id · dias_restantes · data_expiracao" },
    { m:"POST", p:"check-update", c:"CheckUpdateController@check", o:"Body texto CNPJ;VersaoAtual. Resposta VersaoNova;VersaoMinObrigatoria (governada por business.versao_disponivel/obrigatoria)" },
  ]},
  { id:"cadastros", label:"Cadastros e catálogo", desc:"Leitura do catálogo e dos contatos. Escrita só em contatos.", eps:[
    { m:"GET", p:"business-details", c:"CommonResourceController@getBusinessDetails" },
    { m:"GET", p:"business-location", c:"BusinessLocationController", o:"index e show" },
    { m:"GET", p:"product", c:"ProductController", o:"index e show" },
    { m:"GET", p:"selling-price-group", c:"ProductController@getSellingPriceGroup" },
    { m:"GET", p:"variation/{id?}", c:"ProductController@listVariations" },
    { m:"GET", p:"taxonomy", c:"CategoryController", o:"categorias — index e show" },
    { m:"GET", p:"brand", c:"BrandController", o:"index e show" },
    { m:"GET", p:"unit", c:"UnitController", o:"index e show" },
    { m:"GET", p:"tax", c:"TaxController", o:"index e show" },
    { m:"GET", p:"table", c:"TableController", o:"mesas — index e show" },
    { m:"GET", p:"types-of-service", c:"TypesOfServiceController", o:"index e show" },
    { m:"GET·POST·PUT", p:"contactapi", c:"ContactController", o:"index · show · store · update (StoreContactApiRequest + ContactPayloadValidatorService)" },
    { m:"POST", p:"contactapi-payment", c:"ContactController@contactPay" },
    { m:"GET", p:"new_product · new_sell · new_contactapi", c:"ProductSellController", o:"payloads de criação usados pelo app externo" },
  ]},
  { id:"vendas", label:"Vendas e caixa", desc:"A única escrita pesada da API: venda, devolução e caixa.", eps:[
    { m:"GET·POST·PUT·DELETE", p:"sell", c:"SellController", o:"index · store · show · update · destroy (StoreSellPosApiRequest)" },
    { m:"POST", p:"sell-return", c:"SellController@addSellReturn" },
    { m:"GET", p:"list-sell-return", c:"SellController@listSellReturn" },
    { m:"POST", p:"update-shipping-status", c:"SellController@updateSellShippingStatus" },
    { m:"GET·POST·PUT", p:"cash-register", c:"CashRegisterController", o:"index · store · show · update (StoreCashRegisterApiRequest)" },
  ]},
  { id:"financeiro", label:"Financeiro e relatórios", eps:[
    { m:"GET·POST·PUT", p:"expense", c:"ExpenseController", o:"index · store · show · update (StoreExpenseApiRequest)" },
    { m:"GET", p:"expense-refund", c:"ExpenseController@listExpenseRefund" },
    { m:"GET", p:"expense-categories", c:"ExpenseController@listExpenseCategories" },
    { m:"GET", p:"payment-accounts", c:"CommonResourceController@getPaymentAccounts" },
    { m:"GET", p:"payment-methods", c:"CommonResourceController@getPaymentMethods" },
    { m:"GET", p:"profit-loss-report", c:"CommonResourceController@getProfitLoss" },
    { m:"GET", p:"product-stock-report", c:"CommonResourceController@getProductStock" },
  ]},
  { id:"pessoas", label:"Pessoas, ponto e conta", eps:[
    { m:"GET", p:"user", c:"UserController", o:"index e show" },
    { m:"GET", p:"user/loggedin", c:"UserController@loggedin" },
    { m:"POST", p:"user-registration", c:"UserController@registerUser", o:"RegisterUserRequest" },
    { m:"POST", p:"update-password · forget-password", c:"UserController", o:"forget dispara a notificação NewPassword" },
    { m:"GET", p:"get-attendance/{user_id}", c:"AttendanceController@getAttendance" },
    { m:"POST", p:"clock-in · clock-out", c:"AttendanceController", o:"marcação vinda do app (StoreAttendanceApiRequest)" },
    { m:"GET", p:"holidays", c:"AttendanceController@getHolidays" },
    { m:"GET", p:"get-location · notifications", c:"CommonResourceController" },
    { m:"GET", p:"active-subscription · packages", c:"SuperadminController" },
  ]},
  { id:"crm", label:"CRM", desc:"Prefixo próprio: connector/api/crm", eps:[
    { m:"GET·POST·PUT", p:"crm/follow-ups", c:"Crm/FollowUpController", o:"index · store · show · update" },
    { m:"GET", p:"crm/follow-up-resources", c:"Crm/FollowUpController@getFollowUpResources" },
    { m:"GET", p:"crm/leads", c:"Crm/FollowUpController@getLeads" },
    { m:"POST", p:"crm/call-logs", c:"Crm/CallLogsController@saveCallLogs" },
  ]},
  { id:"ff", label:"Field Force", eps:[
    { m:"GET", p:"field-force", c:"FieldForce/FieldForceController@index" },
    { m:"POST", p:"field-force/create", c:"FieldForce/FieldForceController@store" },
    { m:"POST", p:"field-force/update-visit-status/{id}", c:"FieldForce/FieldForceController@updateStatus" },
  ]},
];
const TOTAL = GRUPOS.reduce((n, g) => n + g.eps.length, 0);

const FORMATOS = [
  { k:"array_tabelas", t:"Delphi legado (3.7)", d:"JSON em array com NOME_TABELA = EMPRESA e LICENCIAMENTO. HD sai da linha LICENCIAMENTO, CNPJ da linha EMPRESA." },
  { k:"json_flat", t:"WR Comercial atual", d:"JSON plano com cnpj, serial_hd, versao. É o formato que Services.OImpresso.Registro.pas manda depois de autenticar." },
  { k:"pipe", t:"Fallback TThreadLicenca", d:"texto puro SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO — o serial é o primeiro campo." },
];

function Ep({ e }) {
  return (
    <tr>
      <td className="cnx-ep-m"><span className={`cnx-m ${e.m.startsWith("GET") ? "get" : "post"}`}>{e.m}</span></td>
      <td className="cnx-ep-p mono">connector/api/{e.p}</td>
      <td className="cnx-ep-c mono">{e.c}</td>
      <td className="cnx-ep-o">{e.o || "—"}</td>
    </tr>
  );
}

function DocsView() {
  const [q, setQ] = useState("");
  const busca = q.trim().toLowerCase();
  const grupos = useMemo(() => GRUPOS
    .map((g) => ({ ...g, eps: busca ? g.eps.filter((e) => (e.p + " " + e.c + " " + (e.o || "")).toLowerCase().includes(busca)) : g.eps }))
    .filter((g) => g.eps.length > 0), [busca]);
  const achados = grupos.reduce((n, g) => n + g.eps.length, 0);

  return (
    <>
      <div className="cnx-cards" data-contract="docs-como-entra">
        <section className="cnx-card">
          <h3>Como um app externo entra</h3>
          <ol className="cnx-steps">
            <li>Você cria um <b>API client</b> aqui na aba Clients — ele devolve <span className="mono">client_id</span> e <span className="mono">client_secret</span>.</li>
            <li>O app pede o token em <span className="mono">POST /oauth/token</span> (password grant do Passport: usuário e senha do colaborador + as duas credenciais).</li>
            <li>Toda chamada vai com <span className="mono">Authorization: Bearer …</span> — o middleware <span className="mono">auth:api</span> resolve o negócio pelo usuário do token.</li>
          </ol>
        </section>
        <section className="cnx-card">
          <h3>Regras que valem em todo endpoint</h3>
          <dl className="cnx-dl">
            <div><dt>Limite</dt><dd className="mono">120 req/min por token</dd></div>
            <div><dt>Autenticação</dt><dd className="mono">auth:api (OAuth Passport)</dd></div>
            <div><dt>Registro</dt><dd className="mono">log.delphi grava corpo + formato</dd></div>
            <div><dt>Fuso</dt><dd className="mono">middleware timezone</dd></div>
          </dl>
          <p className="cnx-note">O registro de payload existe pra achar endpoint que o desktop usa e ninguém documentou — é a rede de segurança da integração, não telemetria de cliente.</p>
        </section>
      </div>

      <div className="cnx-search-row">
        <div className="usr-search">
          <I.search size={15}/>
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar endpoint, controller ou observação…" aria-label="Buscar endpoint"/>
          {q && <button className="mod-search-x" onClick={() => setQ("")} aria-label="Limpar busca">×</button>}
        </div>
        <span className="usr-count">{busca ? `${achados} de ${TOTAL} endpoints` : `${TOTAL} endpoints em ${GRUPOS.length} grupos`}</span>
      </div>

      {grupos.map((g) => (
        <section key={g.id} className="cnx-group">
          <header className="cnx-group-h">
            <h3>{g.label}<span className="cnx-group-n mono">{g.eps.length}</span></h3>
            {g.desc && <p>{g.desc}</p>}
          </header>
          <div className="os-table-wrap">
            <table className="os-table cnx-table" data-contract="docs-catalogo">
              <thead><tr><th className="cnx-th-m">Método</th><th>Rota</th><th>Controller</th><th>Observação</th></tr></thead>
              <tbody>{g.eps.map((e, i) => <Ep key={i} e={e}/>)}</tbody>
            </table>
          </div>
        </section>
      ))}

      {grupos.length === 0 && (
        <div className="mod-empty"><b>Nenhum endpoint casa com “{q}”.</b><p>A busca cobre rota, controller e observação.</p>
          <button className="os-btn" onClick={() => setQ("")}>Limpar busca</button></div>
      )}

      <section className="cnx-group">
        <header className="cnx-group-h"><h3>Formatos de corpo que o desktop manda</h3>
          <p>O DelphiSyncService detecta o formato sozinho — os três convivem em campo.</p></header>
        <div className="cnx-fmts" data-contract="docs-formatos">
          {FORMATOS.map((f) => (
            <div key={f.k} className="cnx-fmt">
              <span className="cnx-fmt-k mono">{f.k}</span>
              <b>{f.t}</b>
              <p>{f.d}</p>
            </div>
          ))}
        </div>
      </section>
    </>
  );
}

// ── Saúde: os 3 checks do connector:health (limiares e agenda são do próprio comando) ──
const CHECKS = [
  { id:"tokens", label:"Tokens ativos em 24 h", valor:14, limite:"≥ 1", ok:true,
    fonte:"oauth_access_tokens sem revogação, sem vencimento passado, tocados nas últimas 24 h",
    falha:"Zero significa que nenhum app externo autenticou no dia." },
  { id:"licencas", label:"Licenças com acesso em 24 h", valor:9, limite:"≥ 1", ok:true,
    fonte:"licenca_computador.dt_ultimo_acesso nas últimas 24 h",
    falha:"Zero significa que nenhum WR Comercial abriu — sinal de licenciamento travado." },
  { id:"rotas", label:"Rotas registradas", valor:TOTAL, limite:"≥ 20", ok:TOTAL >= 20,
    fonte:"rotas cujo caminho começa com connector/api",
    falha:"Abaixo de 20 o provedor do módulo não subiu." },
];

function SaudeView() {
  const A = ADS();
  const falhas = CHECKS.filter((c) => !c.ok);
  return (
    <>
      {A.Nota && (
        <A.Nota tone={falhas.length ? "warn" : "info"} title={falhas.length ? "Um check abaixo do limiar" : "Última execução sem alerta"}>
          Os números vêm do registro do <span className="mono">connector:health</span> — esta tela não executa o comando. A rotina roda às 06:15 (Brasília), depois do health da Jana, e sai com falha se qualquer limiar não bater.
        </A.Nota>
      )}
      <div className="cnx-checks" data-contract="saude-checks">
        {CHECKS.map((c) => (
          <section key={c.id} className={`cnx-check ${c.ok ? "" : "bad"}`}>
            <header>
              <span className="cnx-check-l">{c.label}</span>
              <span className={`mod-badge ${c.ok ? "active" : "errored"}`}>{c.ok ? "Dentro do limiar" : "Abaixo do limiar"}</span>
            </header>
            <b className="cnx-check-v tabular">{c.valor}</b>
            <p className="cnx-check-lim mono">limiar {c.limite}</p>
            <p className="cnx-check-src">{c.fonte}</p>
            <p className="cnx-check-falha">{c.falha}</p>
          </section>
        ))}
      </div>
      <section className="cnx-card">
        <h3>O que a rotina também registra</h3>
        <p className="cnx-note">Todo desvio de sincronismo (HD não cadastrado, CNPJ sem negócio, corpo em formato desconhecido) vira aviso estruturado no registro, com o motivo. Taxa de desvio acima de zero merece olhada — é o jeito de descobrir cliente legado mandando formato novo sem avisar.</p>
      </section>
    </>
  );
}

window.ConnectorApi = { GRUPOS, TOTAL, DocsView, SaudeView };
})();
