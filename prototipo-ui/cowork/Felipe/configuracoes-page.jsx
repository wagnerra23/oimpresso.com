// configuracoes-page.jsx — grupo "Configurações" do menu legado (AdminLTE), importado do blade
// (espelho local do main): business/settings.blade.php + os 16 partials, e as 9 telas de cadastro.
// Esta tela é a "Configuração da empresa" (BusinessController::getBusinessSettings) — 16 abas, campo a campo.
// Onda C2 — fidelidade de campo: rótulos personalizados completos (7 pagamento · 10 contato · 20 produto ·
// 4 local · 4 usuário · 4+5 compra · 4+5 venda · 6 serviço), SMS com os 3+10 pares de cabeçalho/parâmetro,
// logo do negócio, ação na validade do produto.
// Onda C5 — papel e plano: o que cada papel altera, e o que o pacote libera.
// Copy em PT-BR sentence case (o legado mistura inglês do UltimatePOS — não serve como UI cliente-facing).
// Expõe window.ConfiguracoesPage.
(() => {
const { useState } = React;
const ADS = () => window.AcessosDS || {};
const U = () => window.HrmUI || {};
const C = () => window.ConfigCadastros || {};
const go = (id) => window.__selectRoute?.(id);

// ── Sessão do módulo: papel e plano sobrevivem à troca de rota (cada tela é rota do shell) ──
const SESS = window.__cfgSess || (window.__cfgSess = (() => {
  let papel = "admin", plano = "Profissional";
  try {
    papel = localStorage.getItem("oimpresso.cfg.papel") || papel;
    plano = localStorage.getItem("oimpresso.cfg.plano") || plano;
  } catch (e) {}
  return { papel, plano };
})());
function usePersist(chave, inicial) {
  const [v, setV] = useState(() => SESS[chave] !== undefined ? SESS[chave] : inicial);
  React.useEffect(() => {
    SESS[chave] = v;
    try { localStorage.setItem("oimpresso.cfg." + chave, v); } catch (e) {}
  }, [chave, v]);
  return [v, setV];
}

// Permissões com os nomes LITERAIS do main (lido neste turno): BusinessController 277/349 e
// BusinessLocationController 41/106/157/275/324/413 usam a MESMA `business_settings.access`;
// InvoiceScheme/InvoiceLayout usam `invoice_settings.access` (sem separar ver × editar);
// Barcode `barcode_settings.access`; Printer `access_printers`; TaxRate tem CRUD de verdade
// (`tax_rate.view|create|update|delete`); TypesOfService `access_types_of_service`;
// Restaurant\ModifierSetsController exige `product.create`.
const PAPEIS = {
  admin:{ l:"Administrador (Wagner)", curto:"o administrador", perms:"*" },
  gerente:{ l:"Gerente", curto:"o gerente", perms:[
    "access_printers", "barcode_settings.access", "access_types_of_service", "product.create",
    "tax_rate.view", "tax_rate.create", "tax_rate.update"] },
  balcao:{ l:"Balcão (Larissa)", curto:"o balcão", perms:["access_printers", "access_types_of_service"] },
};
// Pacote: no legado o limite de local não é constante de tela — vem de
// ModuleUtil::isSubscribed + isQuotaAvailable('locations') no create/store.
const PLANOS = {
  Essencial:{ nome:"Essencial", locais:1, usuarios:3, modificadores:false, integracoes:false },
  Profissional:{ nome:"Profissional", locais:5, usuarios:15, modificadores:true, integracoes:true },
};

// ── Entradas do menu Configurações (ordem literal do blade sidebar) ──
const TELAS = [
  { id:"cfg-empresa",       label:"Configuração da empresa",  rota:"/business/settings",        perm:"business_settings.access" },
  { id:"cfg-locais",        label:"Locais comerciais",        rota:"/business/location",        perm:"business_settings.access" },
  { id:"cfg-fatura",        label:"Configurações da fatura",  rota:"/invoice-schemes",          perm:"invoice_settings.access" },
  { id:"cfg-barras",        label:"Código de barras",         rota:"/barcodes",                 perm:"barcode_settings.access" },
  { id:"notificacoes",      label:"Modelos de notificação",   rota:"/notification-templates",   perm:"business_settings.access" },
  { id:"cfg-impressoras",   label:"Impressoras de recibos",   rota:"/printers",                 perm:"access_printers" },
  { id:"cfg-impostos",      label:"Taxas de imposto",         rota:"/tax-rates",                perm:"tax_rate.view" },
  { id:"cfg-modificadores", label:"Modificadores",            rota:"/modules/modifiers",        perm:"product.create" },
  { id:"cfg-servicos",      label:"Tipos de serviço",         rota:"/types-of-service",         perm:"access_types_of_service" },
  { id:"cfg-pacote",        label:"Assinatura de pacote",     rota:"/subscription",             perm:"business_settings.access" },
];

// ── Abas do settings.blade (list-group da esquerda, mesma ordem) ──
const ABAS = [
  { g:"Empresa", itens:[
    { id:"negocio",  label:"Negócio" }, { id:"imposto", label:"Impostos" },
    { id:"produto",  label:"Produtos" }, { id:"contato", label:"Contatos" },
  ]},
  { g:"Operação", itens:[
    { id:"venda", label:"Vendas" }, { id:"pdv", label:"Venda no PDV" },
    { id:"compra", label:"Compras" }, { id:"pagto", label:"Pagamento" },
  ]},
  { g:"Sistema", itens:[
    { id:"painel", label:"Painel" }, { id:"sistema", label:"Sistema" }, { id:"prefixos", label:"Prefixos" },
  ]},
  { g:"Integrações", itens:[
    { id:"email", label:"E-mail" }, { id:"sms", label:"SMS" },
    { id:"fidelidade", label:"Pontos de fidelidade" }, { id:"modulos", label:"Módulos" },
    { id:"rotulos", label:"Rótulos personalizados" },
  ]},
];

function Campo({ label, valor, onChange, ajuda, mono, largo, tipo = "text", travado }) {
  return (
    <div className={`cms-field ${largo ? "pf-largo" : ""}`}>
      <label>{label}</label>
      <input className={mono ? "mono" : ""} type={tipo} value={valor} disabled={travado} onChange={(e) => onChange(e.target.value)} />
      {ajuda && <small>{ajuda}</small>}
    </div>
  );
}
function Sel({ label, valor, onChange, opcoes, ajuda, travado }) {
  return (
    <div className="cms-field">
      <label>{label}</label>
      <select value={valor} disabled={travado} onChange={(e) => onChange(e.target.value)}>
        {opcoes.map((o) => <option key={o} value={o}>{o}</option>)}
      </select>
      {ajuda && <small>{ajuda}</small>}
    </div>
  );
}
function Liga({ label, sub, on, onToggle, travado }) {
  const { Sw } = ADS();
  if (Sw && !travado) return <div className="pf-liga pf-liga-ds"><Sw on={on} onToggle={onToggle} label={label} sub={sub} /></div>;
  return (
    <label className={`pf-liga cfg-liga-fb ${travado ? "travado" : ""}`}>
      <span className="pf-liga-l"><b>{label}</b>{sub && <small>{sub}</small>}</span>
      <input type="checkbox" checked={!!on} disabled={travado} onChange={onToggle} />
    </label>
  );
}
function Bloco({ titulo, acao, children }) {
  return <div className="cfg-bloco"><h3>{titulo}{acao}</h3>{children}</div>;
}
function Duas({ children }) { return <div className="cms-f-row two">{children}</div>; }

const PADRAO = {
  nome:"ROTA LIVRE Comunicação Visual", inicio:"2021-03-01", lucro:"25,00", moeda:"Real brasileiro (R$)",
  simbolo:"Antes do valor", fuso:"America/Sao_Paulo", exercicio:"janeiro", metodo:"FIFO (primeiro a entrar, primeiro a sair)",
  diasEdicao:"30", fmtData:"dd/mm/aaaa", fmtHora:"24 horas", precMoeda:"2", precQtd:"3", logo:"rota-livre-logo.png",
  rot1:"CNPJ", cod1:"41.882.334/0001-07", rot2:"Inscrição estadual", cod2:"0018773340092", exportar:true,
  imp1Nome:"CNPJ", imp1No:"41.882.334/0001-07", imp2Nome:"Inscrição estadual", imp2No:"0018773340092", impEmbutido:true,
  sku:"RL", validade:false, validadeAcao:"Manter vendendo", credito:"1.500,00",
  descontoPadrao:"0,00", impostoPadrao:"Nenhum", precoComImposto:"Sem imposto", adicaoItem:"Adiciona linha nova",
  arredonda:"Não arredondar", msp:false, oversell:true, pedidoVenda:true, prazoObrigatorio:false,
  comissao:"Nenhum", calcComissao:"Percentual da venda (sem imposto)", agenteObrigatorio:false, linkPagto:false,
  pdv:{ semPagar:false, semRascunho:false, semExpresso:false, semSugestao:false, semRecentes:false, semDesconto:false,
    semImposto:false, subtotalEditavel:true, semSuspender:false, dataTransacao:true, atendenteInline:true,
    atendenteObrigatorio:false, semVendaPrazo:false, balanca:false, mostraEsquema:true, mostraLayout:true,
    imprimeSuspenso:false, precoNaSugestao:true },
  moedaCompra:false, moedaCompraQual:"Real brasileiro (R$)", cambio:"1,000", editarProdutoNaCompra:true,
  statusCompra:true, lote:false, ordemCompra:true, requisicao:false,
  cedulas:"200,100,50,20,10,5,2", contagemEm:"Abertura e fechamento de caixa", contagemFormas:"Dinheiro", conferencia:false,
  alertaValidade:"30", tema:"Roxo (padrão)", linhasTabela:"25", ajudaVisivel:true,
  pre:{ compra:"CP", devolucaoCompra:"DVC", requisicao:"RQ", ordemCompra:"OC", transferencia:"TR", ajuste:"AJ",
    devolucaoVenda:"DVV", despesa:"DP", contatos:"CT", pagtoCompra:"PGC", pagtoVenda:"PGV", pagtoDespesa:"PGD",
    local:"LOC", usuario:"USR", assinatura:"ASS", rascunho:"RAS", pedidoVenda:"PV" },
  usaSuperadmin:true, mailDriver:"SMTP", mailHost:"smtp.oimpresso.com.br", mailPorta:"587",
  mailUser:"nao-responda@oimpresso.com.br", mailSenha:"••••••••", mailCripto:"TLS",
  mailDe:"nao-responda@oimpresso.com.br", mailNome:"ROTA LIVRE",
  smsServico:"Requisição própria (URL)", smsUrl:"", smsParamDest:"to", smsParamMsg:"message", smsMetodo:"POST",
  smsHead:[["Authorization", "Bearer •••"], ["Content-Type", "application/json"], ["", ""]],
  smsParam:[["from", "ROTALIVRE"], ["", ""], ["", ""], ["", ""], ["", ""], ["", ""], ["", ""], ["", ""], ["", ""], ["", ""]],
  fidelidade:false, rpNome:"Pontos", rpValorUnidade:"1,00", rpMinPedido:"0,00", rpMaxPorPedido:"",
  rpResgateUnidade:"0,10", rpMinResgate:"50", rpValidade:"12 meses",
  // Rótulos personalizados — inventário completo do settings_custom_labels
  pgto:["Pix", "Boleto", "Cartão da loja", "Crédito do cliente", "", "", ""],
  contato:["Comprador", "Setor", "Obra", "Condição especial", "", "", "", "", "", ""],
  produto:["Substrato", "Gramatura", "Acabamento padrão", "Fornecedor preferido", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
  local:["Turno", "Responsável", "", ""],
  usuario:["Matrícula", "Setor", "", ""],
  compra:["Pedido do fornecedor", "Nota do transporte", "", ""],
  freteCompra:["Transportadora", "Conhecimento", "", "", ""],
  venda:["Instalação", "Endereço da obra", "Contato no local", ""],
  freteVenda:["Veículo", "Motorista", "Placa", "", ""],
  servico:["Prazo de montagem", "Equipe", "Ferramenta", "", "", ""],
};

const MODULOS = [
  ["Compras", true, false], ["Adicionar venda", true, false], ["PDV", true, false], ["Estoque", true, false],
  ["Ajuste de estoque", true, false], ["Transferência de estoque", true, false], ["Despesas", true, false],
  ["Conta bancária", true, false], ["Tabela de preços", true, false], ["Kit de produtos", true, false],
  ["Fabricação", true, true], ["Tipos de serviço", true, false], ["Cobrança recorrente", true, true],
  ["Agenda / reservas", false, true], ["Pontos de fidelidade", false, false], ["Balança", false, false],
];

function Rotulos({ f, set, travado }) {
  const [todos, setTodos] = useState(false);
  const grupo = (chave, titulo, n, ajuda, nota) => (
    <Bloco titulo={titulo} key={chave}
      acao={chave === "produto" && n > 4
        ? <button className="cfg-mini-link" onClick={() => setTodos((v) => !v)}>{todos ? "mostrar 4" : `mostrar todos os ${n}`}</button>
        : null}>
      {nota && <p className="cfg-p">{nota}</p>}
      <div className="cfg-prefixos">
        {Array.from({ length:chave === "produto" && !todos ? 4 : n }).map((_, i) => (
          <Campo key={i} label={`${ajuda} ${i + 1}`} valor={f[chave][i] || ""} travado={travado}
            onChange={(v) => set(chave)(f[chave].map((x, j) => j === i ? v : x))} />))}
      </div>
    </Bloco>
  );
  return (
    <>
      {grupo("pgto", "Formas de pagamento personalizadas", 7, "Personalizada", "Aparecem no caixa junto de dinheiro, cartão e Pix.")}
      {grupo("contato", "Campos do contato", 10, "Contato · campo")}
      {grupo("produto", "Campos do produto", 20, "Produto · campo", "O legado abre 20; nomeie só os que a casa usa — campo sem nome não aparece na tela.")}
      {grupo("local", "Campos do local comercial", 4, "Local · campo")}
      {grupo("usuario", "Campos do usuário", 4, "Usuário · campo")}
      {grupo("compra", "Campos da compra", 4, "Compra · campo")}
      {grupo("freteCompra", "Campos do frete da compra", 5, "Frete da compra · campo")}
      {grupo("venda", "Campos da venda", 4, "Venda · campo")}
      {grupo("freteVenda", "Campos do frete da venda", 5, "Frete da venda · campo")}
      {grupo("servico", "Campos do tipo de serviço", 6, "Serviço · campo")}
    </>
  );
}

function Empresa({ A }) {
  const [aba, setAba] = useState("negocio");
  const [f, setF] = useState({ ...PADRAO, ...(A.rotulosSalvos || {}) });
  const [sujo, setSujo] = useState(false);
  const [mods, setMods] = useState(MODULOS.map((m) => m[1]));
  const { Nota } = U();
  const editavel = A.pode("business_settings.access");
  const set = (k) => (v) => { if (!editavel) return; setF((s) => ({ ...s, [k]:v })); setSujo(true); };
  const liga = (k) => () => { if (!editavel) return; setF((s) => ({ ...s, [k]:!s[k] })); setSujo(true); };
  const ligaPdv = (k) => () => { if (!editavel) return; setF((s) => ({ ...s, pdv:{ ...s.pdv, [k]:!s.pdv[k] } })); setSujo(true); };
  const setPre = (k) => (v) => { if (!editavel) return; setF((s) => ({ ...s, pre:{ ...s.pre, [k]:v } })); setSujo(true); };
  const setPar = (grupo, i, j) => (v) => {
    if (!editavel) return;
    setF((s) => ({ ...s, [grupo]:s[grupo].map((par, k) => k === i ? par.map((x, l) => l === j ? v : x) : par) }));
    setSujo(true);
  };
  const T = !editavel;

  return (
    <>
      {!editavel && Nota && (
        <div className="hrm-note-ds"><Nota tone="warn" title="Você está vendo, não editando">
          Configuração da empresa exige <code>business_settings.access</code>. Para {A.papelCurto}, os campos
          ficam visíveis pra conferência e o botão de salvar fica desligado.
        </Nota></div>)}

      <div className="pf-body cfg-body">
        <nav className="fnc-rail pf-rail">
          {ABAS.map((g) => (
            <div key={g.g} className="fnc-rail-dom">
              <span className="fnc-rail-dom-l">{g.g}</span>
              {g.itens.map((i) => (
                <button key={i.id} className={`fnc-rail-g ${aba === i.id ? "on" : ""}`} onClick={() => setAba(i.id)}>
                  <span className="fnc-rail-g-l">{i.label}</span>
                  {i.plano && !A.plano.integracoes && <span className="cfg-rail-plano" title="Precisa do pacote Profissional">plano</span>}
                </button>))}
            </div>))}
        </nav>

        <section className="fnc-pane pf-pane">

          {aba === "negocio" && (
            <>
              <div className="pf-h"><h2>Negócio</h2><p>O que identifica a empresa e como o sistema conta dinheiro, data e quantidade.</p></div>
              <div className="pf-form">
                <Duas>
                  <Campo label="Nome do negócio" valor={f.nome} onChange={set("nome")} travado={T} />
                  <Campo label="Data de início" valor={f.inicio} onChange={set("inicio")} tipo="date" travado={T} />
                </Duas>
                <Duas>
                  <Campo label="Margem de lucro padrão (%)" valor={f.lucro} onChange={set("lucro")} mono travado={T}
                    ajuda="Usada pra sugerir o preço de venda a partir do custo." />
                  <Sel label="Moeda" valor={f.moeda} onChange={set("moeda")} travado={T} opcoes={["Real brasileiro (R$)", "Dólar (US$)", "Euro (€)"]} />
                </Duas>
                <Duas>
                  <Sel label="Posição do símbolo" valor={f.simbolo} onChange={set("simbolo")} travado={T} opcoes={["Antes do valor", "Depois do valor"]} />
                  <Sel label="Fuso horário" valor={f.fuso} onChange={set("fuso")} travado={T} opcoes={["America/Sao_Paulo", "America/Manaus", "America/Belem"]} />
                </Duas>
                <Duas>
                  <Sel label="Mês que abre o exercício" valor={f.exercicio} onChange={set("exercicio")} travado={T} opcoes={["janeiro", "abril", "julho", "outubro"]} />
                  <Sel label="Método de custo" valor={f.metodo} onChange={set("metodo")} travado={T}
                    opcoes={["FIFO (primeiro a entrar, primeiro a sair)", "LIFO (último a entrar, primeiro a sair)"]} />
                </Duas>
                <Duas>
                  <Campo label="Dias para editar uma transação" valor={f.diasEdicao} onChange={set("diasEdicao")} mono travado={T}
                    ajuda="Depois disso, a venda ou compra só muda com estorno." />
                  <Sel label="Formato de data" valor={f.fmtData} onChange={set("fmtData")} travado={T} opcoes={["dd/mm/aaaa", "mm/dd/aaaa", "aaaa-mm-dd"]} />
                </Duas>
                <Duas>
                  <Sel label="Formato de hora" valor={f.fmtHora} onChange={set("fmtHora")} travado={T} opcoes={["24 horas", "12 horas"]} />
                  <Campo label="Casas decimais do valor" valor={f.precMoeda} onChange={set("precMoeda")} mono travado={T} />
                </Duas>
                <Duas>
                  <Campo label="Casas decimais da quantidade" valor={f.precQtd} onChange={set("precQtd")} mono travado={T}
                    ajuda="Cálculo por m² costuma pedir 3." />
                  <span />
                </Duas>

                <Bloco titulo="Logo do negócio">
                  <div className="cfg-logo">
                    <span className="cfg-logo-box" aria-hidden="true">OI</span>
                    <div className="cfg-logo-t">
                      <b>{f.logo || "Nenhum arquivo enviado"}</b>
                      <small>PNG ou JPG até 1 MB. Sai no cabeçalho da fatura quando o layout pede logo.</small>
                      <div className="cfg-acoes">
                        <button className="os-btn xs" disabled={T} onClick={() => set("logo")("rota-livre-logo.png")}>Enviar arquivo</button>
                        {f.logo && <button className="os-btn xs ghost" disabled={T} onClick={() => set("logo")("")}>Remover</button>}
                      </div>
                    </div>
                  </div>
                </Bloco>

                <Bloco titulo="Códigos da empresa na nota">
                  <Duas>
                    <Campo label="Nome do código 1" valor={f.rot1} onChange={set("rot1")} travado={T} />
                    <Campo label="Código 1" valor={f.cod1} onChange={set("cod1")} mono travado={T} />
                  </Duas>
                  <Duas>
                    <Campo label="Nome do código 2" valor={f.rot2} onChange={set("rot2")} travado={T} />
                    <Campo label="Código 2" valor={f.cod2} onChange={set("cod2")} mono travado={T} />
                  </Duas>
                </Bloco>
                <Liga label="Permitir exportar listas" sub="Botões de Excel, CSV e PDF nas tabelas." on={f.exportar} onToggle={liga("exportar")} travado={T} />
              </div>
            </>)}

          {aba === "imposto" && (
            <>
              <div className="pf-h"><h2>Impostos</h2><p>Dois números fiscais aparecem no cabeçalho de todo documento.</p></div>
              <div className="pf-form">
                <Duas>
                  <Campo label="Nome do imposto 1" valor={f.imp1Nome} onChange={set("imp1Nome")} travado={T} />
                  <Campo label="Número do imposto 1" valor={f.imp1No} onChange={set("imp1No")} mono travado={T} />
                </Duas>
                <Duas>
                  <Campo label="Nome do imposto 2" valor={f.imp2Nome} onChange={set("imp2Nome")} travado={T} />
                  <Campo label="Número do imposto 2" valor={f.imp2No} onChange={set("imp2No")} mono travado={T} />
                </Duas>
                <Liga label="Imposto embutido no preço" sub="O preço cadastrado já inclui o imposto." on={f.impEmbutido} onToggle={liga("impEmbutido")} travado={T} />
                <p className="pf-nota">Alíquota, CFOP, NCM e CST ficam em <b>NF-e Brasil</b> — aqui só os números que aparecem impressos.</p>
              </div>
            </>)}

          {aba === "produto" && (
            <>
              <div className="pf-h"><h2>Produtos</h2><p>Como o sistema gera código e trata validade.</p></div>
              <div className="pf-form">
                <Duas>
                  <Campo label="Prefixo do SKU" valor={f.sku} onChange={set("sku")} mono travado={T} ajuda="Entra antes do número quando o SKU é automático." />
                  <span />
                </Duas>
                <Liga label="Controlar validade do produto" sub="Liga data de validade na compra e no estoque." on={f.validade} onToggle={liga("validade")} travado={T} />
                {f.validade && (
                  <Duas>
                    <Sel label="Ao vencer" valor={f.validadeAcao} onChange={set("validadeAcao")} travado={T}
                      opcoes={["Manter vendendo", "Parar de vender"]} ajuda="“Parar de vender” bloqueia o item no caixa a partir da data." />
                    <span />
                  </Duas>)}
              </div>
            </>)}

          {aba === "contato" && (
            <>
              <div className="pf-h"><h2>Contatos</h2><p>Vale pra cliente novo que não tem limite próprio.</p></div>
              <div className="pf-form">
                <Duas>
                  <Campo label="Limite de crédito padrão (R$)" valor={f.credito} onChange={set("credito")} mono travado={T} ajuda="Em branco = sem limite." />
                  <span />
                </Duas>
              </div>
            </>)}

          {aba === "venda" && (
            <>
              <div className="pf-h"><h2>Vendas</h2><p>Padrões de desconto, imposto e comissão de toda venda.</p></div>
              <div className="pf-form">
                <Duas>
                  <Campo label="Desconto padrão (%)" valor={f.descontoPadrao} onChange={set("descontoPadrao")} mono travado={T} />
                  <Sel label="Imposto padrão da venda" valor={f.impostoPadrao} onChange={set("impostoPadrao")} travado={T}
                    opcoes={["Nenhum", "ICMS 18%", "ISS 5%", "Simples Nacional"]} />
                </Duas>
                <Duas>
                  <Sel label="Preço de venda" valor={f.precoComImposto} onChange={set("precoComImposto")} travado={T} opcoes={["Sem imposto", "Com imposto"]} />
                  <Sel label="Ao repetir um item" valor={f.adicaoItem} onChange={set("adicaoItem")} travado={T} opcoes={["Adiciona linha nova", "Aumenta a quantidade"]} />
                </Duas>
                <Duas>
                  <Sel label="Arredondamento do total" valor={f.arredonda} onChange={set("arredonda")} travado={T}
                    opcoes={["Não arredondar", "Arredondar para o inteiro mais próximo", "Arredondar para 0,05", "Arredondar para 0,50"]} />
                  <Sel label="Comissão" valor={f.comissao} onChange={set("comissao")} travado={T}
                    opcoes={["Nenhum", "Vendedor da venda", "Vendedor logado", "Comissionado escolhido"]} />
                </Duas>
                {f.comissao !== "Nenhum" && (
                  <Duas>
                    <Sel label="Cálculo da comissão" valor={f.calcComissao} onChange={set("calcComissao")} travado={T}
                      opcoes={["Percentual da venda (sem imposto)", "Percentual da venda (com imposto)", "Percentual do lucro"]} />
                    <span />
                  </Duas>)}
                <Bloco titulo="Regras da venda">
                  <Liga label="Respeitar preço mínimo de venda" sub="Bloqueia desconto abaixo do MSP do produto." on={f.msp} onToggle={liga("msp")} travado={T} />
                  <Liga label="Vender sem estoque" sub="Deixa faturar item com saldo negativo — comum em serviço." on={f.oversell} onToggle={liga("oversell")} travado={T} />
                  <Liga label="Usar pedido de venda" sub="Pedido antes da venda, com faturamento parcial." on={f.pedidoVenda} onToggle={liga("pedidoVenda")} travado={T} />
                  <Liga label="Exigir prazo de pagamento" sub="Não fecha venda a prazo sem prazo informado." on={f.prazoObrigatorio} onToggle={liga("prazoObrigatorio")} travado={T} />
                  <Liga label="Exigir comissionado" sub="Toda venda tem que ter um responsável pela comissão." on={f.agenteObrigatorio} onToggle={liga("agenteObrigatorio")} travado={T} />
                  <Liga label="Link de pagamento no documento" sub="No legado usa Razorpay/Stripe — aqui é Pix e boleto." on={f.linkPagto} onToggle={liga("linkPagto")} travado={T} />
                </Bloco>
              </div>
            </>)}

          {aba === "pdv" && (
            <>
              <div className="pf-h"><h2>Venda no PDV</h2><p>O que o balcão vê na tela de caixa. Larissa trabalha com tudo ligado.</p></div>
              <div className="pf-form">
                <Bloco titulo="Botões do caixa">
                  <Liga label="Esconder “Pagar e finalizar”" on={f.pdv.semPagar} onToggle={ligaPdv("semPagar")} travado={T} />
                  <Liga label="Esconder rascunho" on={f.pdv.semRascunho} onToggle={ligaPdv("semRascunho")} travado={T} />
                  <Liga label="Esconder venda expressa" on={f.pdv.semExpresso} onToggle={ligaPdv("semExpresso")} travado={T} />
                  <Liga label="Esconder suspender venda" on={f.pdv.semSuspender} onToggle={ligaPdv("semSuspender")} travado={T} />
                  <Liga label="Esconder venda a prazo" on={f.pdv.semVendaPrazo} onToggle={ligaPdv("semVendaPrazo")} travado={T} />
                </Bloco>
                <Bloco titulo="Campos e cálculo">
                  <Liga label="Esconder desconto" on={f.pdv.semDesconto} onToggle={ligaPdv("semDesconto")} travado={T} />
                  <Liga label="Esconder imposto do pedido" on={f.pdv.semImposto} onToggle={ligaPdv("semImposto")} travado={T} />
                  <Liga label="Subtotal editável" sub="Deixa digitar o total e recalcular o item." on={f.pdv.subtotalEditavel} onToggle={ligaPdv("subtotalEditavel")} travado={T} />
                  <Liga label="Permitir mudar a data da venda" on={f.pdv.dataTransacao} onToggle={ligaPdv("dataTransacao")} travado={T} />
                </Bloco>
                <Bloco titulo="Sugestões e atendimento">
                  <Liga label="Esconder sugestão de produto" on={f.pdv.semSugestao} onToggle={ligaPdv("semSugestao")} travado={T} />
                  <Liga label="Mostrar preço na sugestão" on={f.pdv.precoNaSugestao} onToggle={ligaPdv("precoNaSugestao")} travado={T} />
                  <Liga label="Esconder vendas recentes" on={f.pdv.semRecentes} onToggle={ligaPdv("semRecentes")} travado={T} />
                  <Liga label="Atendente na linha do item" on={f.pdv.atendenteInline} onToggle={ligaPdv("atendenteInline")} travado={T} />
                  <Liga label="Exigir atendente" on={f.pdv.atendenteObrigatorio} onToggle={ligaPdv("atendenteObrigatorio")} travado={T} />
                </Bloco>
                <Bloco titulo="Impressão e periféricos">
                  <Liga label="Mostrar esquema de numeração no caixa" on={f.pdv.mostraEsquema} onToggle={ligaPdv("mostraEsquema")} travado={T} />
                  <Liga label="Mostrar layout da fatura no caixa" on={f.pdv.mostraLayout} onToggle={ligaPdv("mostraLayout")} travado={T} />
                  <Liga label="Imprimir ao suspender" on={f.pdv.imprimeSuspenso} onToggle={ligaPdv("imprimeSuspenso")} travado={T} />
                  <Liga label="Usar balança" sub="Lê o código da etiqueta de peso." on={f.pdv.balanca} onToggle={ligaPdv("balanca")} travado={T} />
                </Bloco>
              </div>
            </>)}

          {aba === "compra" && (
            <>
              <div className="pf-h"><h2>Compras</h2><p>Como a nota do fornecedor entra no estoque.</p></div>
              <div className="pf-form">
                <Liga label="Comprar em outra moeda" on={f.moedaCompra} onToggle={liga("moedaCompra")} travado={T} />
                {f.moedaCompra && (
                  <Duas>
                    <Sel label="Moeda da compra" valor={f.moedaCompraQual} onChange={set("moedaCompraQual")} travado={T} opcoes={["Real brasileiro (R$)", "Dólar (US$)", "Euro (€)"]} />
                    <Campo label="Taxa de câmbio" valor={f.cambio} onChange={set("cambio")} mono travado={T} ajuda="1 unidade da moeda da compra em reais." />
                  </Duas>)}
                <Bloco titulo="Regras da compra">
                  <Liga label="Editar produto direto na compra" on={f.editarProdutoNaCompra} onToggle={liga("editarProdutoNaCompra")} travado={T} />
                  <Liga label="Usar situação da compra" sub="Pedida · recebida · parcial." on={f.statusCompra} onToggle={liga("statusCompra")} travado={T} />
                  <Liga label="Controlar número de lote" on={f.lote} onToggle={liga("lote")} travado={T} />
                  <Liga label="Usar ordem de compra" on={f.ordemCompra} onToggle={liga("ordemCompra")} travado={T} />
                  <Liga label="Usar requisição de compra" sub="Pedido interno antes da ordem." on={f.requisicao} onToggle={liga("requisicao")} travado={T} />
                </Bloco>
              </div>
            </>)}

          {aba === "pagto" && (
            <>
              <div className="pf-h"><h2>Pagamento</h2><p>Contagem de dinheiro na abertura e no fechamento do caixa.</p></div>
              <div className="pf-form">
                <Campo label="Cédulas e moedas" valor={f.cedulas} onChange={set("cedulas")} mono largo travado={T}
                  ajuda="Separe por vírgula, na ordem que aparece na contagem." />
                <Duas>
                  <Sel label="Contar dinheiro em" valor={f.contagemEm} onChange={set("contagemEm")} travado={T}
                    opcoes={["Abertura e fechamento de caixa", "Só no fechamento", "Nunca"]} />
                  <Sel label="Para quais formas" valor={f.contagemFormas} onChange={set("contagemFormas")} travado={T} opcoes={["Dinheiro", "Dinheiro e cartão", "Todas"]} />
                </Duas>
                <Liga label="Conferência estrita" sub="Não fecha o caixa se a contagem não bater com o sistema." on={f.conferencia} onToggle={liga("conferencia")} travado={T} />
              </div>
            </>)}

          {aba === "painel" && (
            <>
              <div className="pf-h"><h2>Painel</h2><p>O que a tela inicial avisa.</p></div>
              <div className="pf-form">
                <Duas>
                  <Campo label="Avisar validade com quantos dias" valor={f.alertaValidade} onChange={set("alertaValidade")} mono travado={T} />
                  <span />
                </Duas>
              </div>
            </>)}

          {aba === "sistema" && (
            <>
              <div className="pf-h"><h2>Sistema</h2><p>Aparência e densidade das listas.</p></div>
              <div className="pf-form">
                <Duas>
                  <Sel label="Cor do tema" valor={f.tema} onChange={set("tema")} travado={T} opcoes={["Roxo (padrão)", "Azul", "Escuro"]} />
                  <Sel label="Linhas por página nas tabelas" valor={f.linhasTabela} onChange={set("linhasTabela")} travado={T}
                    opcoes={["25", "50", "100", "200", "500", "1000", "Todas"]} />
                </Duas>
                <Liga label="Mostrar textos de ajuda" sub="As dicas de interrogação ao lado dos campos." on={f.ajudaVisivel} onToggle={liga("ajudaVisivel")} travado={T} />
              </div>
            </>)}

          {aba === "prefixos" && (
            <>
              <div className="pf-h"><h2>Prefixos</h2><p>O que vem antes do número de referência de cada documento.</p></div>
              <div className="pf-form">
                <div className="cfg-prefixos">
                  {[["compra", "Compra"], ["devolucaoCompra", "Devolução de compra"], ["requisicao", "Requisição de compra"],
                    ["ordemCompra", "Ordem de compra"], ["transferencia", "Transferência de estoque"], ["ajuste", "Ajuste de estoque"],
                    ["devolucaoVenda", "Devolução de venda"], ["despesa", "Despesa"], ["contatos", "Contatos"],
                    ["pagtoCompra", "Pagamento de compra"], ["pagtoVenda", "Pagamento de venda"], ["pagtoDespesa", "Pagamento de despesa"],
                    ["local", "Local comercial"], ["usuario", "Usuário"], ["assinatura", "Assinatura"],
                    ["rascunho", "Rascunho"], ["pedidoVenda", "Pedido de venda"]].map(([k, l]) => (
                    <Campo key={k} label={l} valor={f.pre[k]} onChange={setPre(k)} mono travado={T} />))}
                </div>
                <p className="pf-nota">Mudar prefixo não renumera o que já existe — a compra CP0148 continua CP0148.</p>
              </div>
            </>)}

          {aba === "email" && (
            <>
              <div className="pf-h"><h2>E-mail</h2><p>Por onde saem os documentos e as notificações.</p></div>
              <div className="pf-form">
                <Liga label="Usar o servidor do sistema" sub="Sem configurar nada: o envio sai pela infra do oimpresso."
                  on={f.usaSuperadmin} onToggle={liga("usaSuperadmin")} travado={T} />
                {!f.usaSuperadmin && (
                  <>
                    <Duas>
                      <Sel label="Driver" valor={f.mailDriver} onChange={set("mailDriver")} travado={T} opcoes={["SMTP", "Sendmail", "Log (não envia)"]} />
                      <Campo label="Servidor" valor={f.mailHost} onChange={set("mailHost")} mono travado={T} />
                    </Duas>
                    <Duas>
                      <Campo label="Porta" valor={f.mailPorta} onChange={set("mailPorta")} mono travado={T} />
                      <Sel label="Criptografia" valor={f.mailCripto} onChange={set("mailCripto")} travado={T} opcoes={["TLS", "SSL", "Nenhuma"]} />
                    </Duas>
                    <Duas>
                      <Campo label="Usuário" valor={f.mailUser} onChange={set("mailUser")} mono travado={T} />
                      <Campo label="Senha" valor={f.mailSenha} onChange={set("mailSenha")} tipo="password" travado={T} />
                    </Duas>
                    <Duas>
                      <Campo label="Enviar de" valor={f.mailDe} onChange={set("mailDe")} mono travado={T} />
                      <Campo label="Nome do remetente" valor={f.mailNome} onChange={set("mailNome")} travado={T} />
                    </Duas>
                  </>)}
                <p className="pf-nota">O corpo de cada mensagem fica em <button className="cfg-link" onClick={() => go("notificacoes")}>Modelos de notificação</button>.</p>
              </div>
            </>)}

          {aba === "sms" && (
            <>
              <div className="pf-h"><h2>SMS</h2><p>O legado traz Nexmo e Twilio; no Brasil o caminho é gateway próprio por URL.</p></div>
              <div className="pf-form">
                <Duas>
                  <Sel label="Serviço" valor={f.smsServico} onChange={set("smsServico")} travado={T}
                    opcoes={["Requisição própria (URL)", "Twilio", "Nexmo"]} />
                  <Sel label="Método" valor={f.smsMetodo} onChange={set("smsMetodo")} travado={T} opcoes={["POST", "GET"]} />
                </Duas>
                <Campo label="URL do gateway" valor={f.smsUrl} onChange={set("smsUrl")} mono largo travado={T} />
                <Duas>
                  <Campo label="Parâmetro do destinatário" valor={f.smsParamDest} onChange={set("smsParamDest")} mono travado={T} />
                  <Campo label="Parâmetro da mensagem" valor={f.smsParamMsg} onChange={set("smsParamMsg")} mono travado={T} />
                </Duas>
                <Bloco titulo="Cabeçalhos da requisição">
                  {f.smsHead.map((par, i) => (
                    <Duas key={i}>
                      <Campo label={`Cabeçalho ${i + 1} · chave`} valor={par[0]} onChange={setPar("smsHead", i, 0)} mono travado={T} />
                      <Campo label={`Cabeçalho ${i + 1} · valor`} valor={par[1]} onChange={setPar("smsHead", i, 1)} mono travado={T} />
                    </Duas>))}
                </Bloco>
                <Bloco titulo="Parâmetros extras">
                  <p className="cfg-p">O legado abre dez pares — a maioria dos gateways brasileiros usa dois ou três.</p>
                  {f.smsParam.map((par, i) => (
                    <Duas key={i}>
                      <Campo label={`Parâmetro ${i + 1} · chave`} valor={par[0]} onChange={setPar("smsParam", i, 0)} mono travado={T} />
                      <Campo label={`Parâmetro ${i + 1} · valor`} valor={par[1]} onChange={setPar("smsParam", i, 1)} mono travado={T} />
                    </Duas>))}
                </Bloco>
                <p className="pf-nota">O WhatsApp resolve a maior parte dos avisos — o SMS fica pra quem não tem app.</p>
              </div>
            </>)}

          {aba === "fidelidade" && (
            <>
              <div className="pf-h"><h2>Pontos de fidelidade</h2><p>Desligado por padrão. Faz sentido em varejo de balcão, não em obra.</p></div>
              <div className="pf-form">
                <Liga label="Usar pontos de fidelidade" on={f.fidelidade} onToggle={liga("fidelidade")} travado={T} />
                {f.fidelidade && (
                  <>
                    <Duas>
                      <Campo label="Nome do programa" valor={f.rpNome} onChange={set("rpNome")} travado={T} />
                      <Campo label="Valor que vale 1 ponto (R$)" valor={f.rpValorUnidade} onChange={set("rpValorUnidade")} mono travado={T} />
                    </Duas>
                    <Duas>
                      <Campo label="Mínimo do pedido pra pontuar (R$)" valor={f.rpMinPedido} onChange={set("rpMinPedido")} mono travado={T} />
                      <Campo label="Máximo de pontos por pedido" valor={f.rpMaxPorPedido} onChange={set("rpMaxPorPedido")} mono travado={T} ajuda="Em branco = sem teto." />
                    </Duas>
                    <Duas>
                      <Campo label="Quanto vale 1 ponto no resgate (R$)" valor={f.rpResgateUnidade} onChange={set("rpResgateUnidade")} mono travado={T} />
                      <Campo label="Mínimo de pontos pra resgatar" valor={f.rpMinResgate} onChange={set("rpMinResgate")} mono travado={T} />
                    </Duas>
                    <Duas>
                      <Sel label="Validade dos pontos" valor={f.rpValidade} onChange={set("rpValidade")} travado={T}
                        opcoes={["6 meses", "12 meses", "24 meses", "Sem validade"]} />
                      <span />
                    </Duas>
                  </>)}
              </div>
            </>)}

          {aba === "modulos" && (
            <>
              <div className="pf-h"><h2>Módulos</h2><p>Desligar um módulo esconde o menu e as telas dele — nada é apagado.</p></div>
              <div className="pf-form">
                <div className="cfg-mods">
                  {MODULOS.map(([label, , pago], i) => {
                    const bloqueado = pago && !A.plano.integracoes;
                    return (
                      <label key={label} className={`cfg-mod ${mods[i] && !bloqueado ? "on" : ""} ${bloqueado ? "bloq" : ""}`}
                        title={bloqueado ? `Incluso a partir do pacote Profissional` : undefined}>
                        <input type="checkbox" checked={mods[i] && !bloqueado} disabled={T || bloqueado}
                          onChange={() => { setMods((m) => m.map((v, j) => j === i ? !v : v)); setSujo(true); }} />
                        <span>{label}{bloqueado && <em>plano</em>}</span>
                      </label>);
                  })}
                </div>
                <p className="pf-nota">Quem contrata módulo por plano é a <button className="cfg-link" onClick={() => go("cfg-pacote")}>assinatura de pacote</button> — aqui só liga o que o plano já permite.</p>
              </div>
            </>)}

          {aba === "rotulos" && (
            <>
              <div className="pf-h"><h2>Rótulos personalizados</h2><p>Renomeia campo extra e forma de pagamento pro vocabulário da casa. O que você nomear aqui aparece na fatura e nos formulários.</p></div>
              <div className="pf-form">
                <Rotulos f={f} set={set} travado={T} />
                <p className="pf-nota">Campo sem nome não aparece em tela nenhuma — é assim que o legado esconde os 20 do produto.</p>
              </div>
            </>)}
        </section>
      </div>

      <div className="cfg-salvar">
        {Nota && <Nota tone="info" title="Uma tela, um botão">
          No legado o formulário inteiro (as 16 abas) salva de uma vez em <code>POST /business/update</code> — trocar de aba não perde o que você digitou.
        </Nota>}
        <button className="os-btn primary" disabled={!editavel || !sujo} onClick={() => { setSujo(false); A.guardarRotulos(f); }}>Atualizar configurações</button>
      </div>
    </>
  );
}

function ConfiguracoesPage({ view = "cfg-empresa" }) {
  const [papel, setPapel] = usePersist("papel", "admin");
  const [plano, setPlano] = usePersist("plano", "Profissional");
  const [rotulosSalvos, setRotulosSalvos] = useState(null);
  const { Vazio } = U();
  const P = PAPEIS[papel] || PAPEIS.admin;
  const pode = (perm) => P.perms === "*" || P.perms.includes(perm);
  const A = {
    papel, papelLabel:P.l, papelCurto:P.curto, plano:PLANOS[plano] || PLANOS.Profissional, pode,
    rotulos:{
      contato1:(rotulosSalvos?.contato || PADRAO.contato)[0], contato2:(rotulosSalvos?.contato || PADRAO.contato)[1],
      produto1:(rotulosSalvos?.produto || PADRAO.produto)[0], venda1:(rotulosSalvos?.venda || PADRAO.venda)[0],
      local1:(rotulosSalvos?.local || PADRAO.local)[0],
      imp1:(rotulosSalvos?.imp1Nome || PADRAO.imp1Nome), imp2:(rotulosSalvos?.imp2Nome || PADRAO.imp2Nome),
    },
    rotulosSalvos, guardarRotulos:setRotulosSalvos,
  };
  const atual = TELAS.find((t) => t.id === view) || TELAS[0];
  const cadastros = C();
  const podeVer = pode(atual.perm);

  const body = !podeVer
    ? <Vazio variante="no-perm" titulo="Acesso restrito"
        desc={`Esta tela pede uma permissão que ${P.curto} não tem. Fale com o administrador da conta.`} />
    : view === "cfg-empresa"
      ? <Empresa A={A} />
      : cadastros.Tela ? <cadastros.Tela view={view} A={A} /> : null;

  return (
    <div className="os-page hrm-page cfg-page" data-screen-label={`Configurações · ${atual.label}`}>
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Configurações</h1>
          <p>{atual.label} — o grupo “Configurações” do menu antigo, tela por tela</p>
        </div>
        <div className="os-page-h-r">
          <span className="hrm-sim" title="Afordância de protótipo — não vai pro F3">
            <select className="hrm-sel" value={papel} onChange={(e) => setPapel(e.target.value)} aria-label="Papel simulado">
              {Object.entries(PAPEIS).map(([k, v]) => <option key={k} value={k}>{v.l}</option>)}
            </select>
            <select className="hrm-sel" value={plano} onChange={(e) => setPlano(e.target.value)} aria-label="Pacote simulado">
              {Object.keys(PLANOS).map((k) => <option key={k} value={k}>Pacote {k}</option>)}
            </select>
          </span>
          <span className="hrm-scope">{atual.rota}</span>
          <button className="os-btn ghost" onClick={() => go("prefs")}>Preferências</button>
        </div>
      </header>

      <nav className="hrm-tabs" role="tablist">
        {TELAS.map((t) => (
          <button key={t.id} role="tab" aria-selected={t.id === view} className={`${t.id === view ? "on" : ""} ${pode(t.perm) ? "" : "cfg-tab-off"}`}
            onClick={() => go(t.id)}>{t.label}</button>))}
      </nav>

      <div className="hrm-body" key={papel + plano}>{body}</div>
    </div>
  );
}

window.ConfiguracoesPage = ConfiguracoesPage;
window.CONFIG_TELAS = TELAS;
})();
