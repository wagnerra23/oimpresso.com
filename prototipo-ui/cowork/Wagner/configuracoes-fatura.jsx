// configuracoes-fatura.jsx — onda C4: o layout da fatura em tela própria, com folha de prova ao lado.
// Espelha invoice_layout/create.blade.php (~1130 linhas, ~90 campos) — o blade mais longo do grupo
// Configurações. Seções na mesma ordem do legado; a prévia à direita é a folha impressa em tempo real,
// no idioma print-craft do DS (marcas de corte + grid de prova).
// Expõe window.ConfigFatura = { LayoutEditor, LAYOUT_PADRAO }.
(() => {
const { useState, useMemo } = React;
const U = () => window.HrmUI || {};
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const ADS = () => window.AcessosDS || {};

const SECOES = [
  { g:"Folha", itens:[
    { id:"ident",   label:"Identificação" },
    { id:"cabec",   label:"Cabeçalho" },
    { id:"titulos", label:"Títulos do documento" },
  ]},
  { g:"Quem", itens:[
    { id:"negocio", label:"Negócio e vendedor" },
    { id:"cliente", label:"Cliente" },
    { id:"endereco", label:"Endereço e contato" },
  ]},
  { g:"Conteúdo", itens:[
    { id:"tabela",  label:"Tabela de itens" },
    { id:"produto", label:"Detalhes do produto" },
    { id:"totais",  label:"Totais e pagamento" },
  ]},
  { g:"Fim da folha", itens:[
    { id:"qr",      label:"QR code" },
    { id:"credito", label:"Nota de crédito" },
    { id:"rodape",  label:"Rodapé" },
  ]},
];

const LAYOUT_PADRAO = {
  nome:"Nota A4", design:"Clássico", padrao:true,
  // Cabeçalho
  papelTimbrado:false, mostraLogo:true, textoCabecalho:"", sub1:"Comunicação visual · impressão digital · montagem",
  sub2:"Av. Brasil, 1.204 — Cuiabá/MT", sub3:"(65) 3025-4180 · contato@rotalivre.com.br", sub4:"", sub5:"",
  // Títulos
  tFatura:"Fatura", tPago:"Recibo", tNaoPago:"Fatura em aberto", tProforma:"Proforma",
  tOrcamento:"Orçamento", tPedido:"Pedido de venda", prefFatura:"", prefOrcamento:"ORC",
  rotData:"Data", rotVencimento:"Vencimento", mostraVencimento:true, formatoData:"dd/mm/aaaa HH:mm",
  // Negócio e vendedor
  mostraNegocio:false, mostraLocal:true, mostraVendedor:true, mostraComissionado:false,
  rotVendedor:"Atendente", rotComissionado:"Representante",
  camposVenda:{ c1:true, c2:false, c3:false, c4:false },
  // Cliente
  mostraCliente:true, rotCliente:"Cliente", mostraCodCliente:false, rotCodCliente:"Código",
  rotImpostoCliente:"CNPJ / CPF", mostraPontos:false, camposContato:{ c1:true, c2:false, c3:false, c4:false },
  // Endereço e contato
  endMarco:true, endCidade:true, endUf:true, endPais:false, endCep:true,
  camposLocal:{ c1:false, c2:false, c3:false, c4:false },
  mostraCelular:true, mostraTel2:false, mostraEmail:true, mostraImposto1:true, mostraImposto2:true,
  // Tabela de itens
  rotProduto:"Produto / serviço", rotQtd:"Qtd", rotUnit:"Unitário", rotSubtotal:"Subtotal",
  rotCodCat:"Código", rotQtdTotal:"Total de itens", rotDescontoItem:"Desconto", rotUnitComDesconto:"Unitário c/ desconto",
  // Detalhes do produto
  pMarca:false, pSku:true, pCodCat:false, pDescricaoVenda:true, pDescricaoProduto:false,
  pValidade:false, pLote:false, pImagem:false, pGarantiaNome:false, pGarantiaVenc:false, pUnidadeBase:true,
  camposProduto:{ c1:true, c2:false, c3:false, c4:false },
  // Totais
  rotSubtotalGeral:"Subtotal", rotDesconto:"Desconto", rotImposto:"Imposto", rotTotal:"Total",
  rotTotalItens:"Itens", rotArredonda:"Arredondamento", rotDevido:"Em aberto", rotPago:"Pago",
  rotSaldoAnterior:"Saldo anterior", rotTroco:"Troco", mostraPagamentos:true, mostraSaldoAnterior:false,
  mostraCodigoBarras:false, escondePrecos:false, totalEmPalavras:true, formatoPalavras:"Português (Brasil)",
  rotResumoImposto:"Resumo de impostos", corDestaque:"#7c3aed",
  // QR
  mostraQr:true, rotulosQr:false, zatca:false,
  qr:{ negocio:true, endereco:false, imposto1:true, imposto2:false, numero:true, dataHora:true,
    subtotal:false, total:true, impostoTotal:false, cliente:false, url:true },
  // Nota de crédito
  cnTitulo:"Nota de crédito", cnRotNumero:"Número", cnRotValor:"Valor do crédito",
  // Rodapé
  textoRodape:"Obrigado pela preferência. Material conferido na retirada.",
  termos:"Prazo de produção a partir da aprovação da arte. Garantia de 90 dias contra defeito de aplicação.",
};

function Ck({ label, on, onToggle, ajuda }) {
  return (
    <label className={`cfg-ck ${on ? "on" : ""}`}>
      <input type="checkbox" checked={!!on} onChange={onToggle} />
      <span>{label}{ajuda && <small>{ajuda}</small>}</span>
    </label>
  );
}
function Cks({ children }) { return <div className="cfg-cks">{children}</div>; }
function Txt({ label, valor, onChange, ajuda, mono, largo }) {
  return (
    <div className={`cms-field ${largo ? "pf-largo" : ""}`}>
      <label>{label}</label>
      <input className={mono ? "mono" : ""} value={valor} onChange={(e) => onChange(e.target.value)} />
      {ajuda && <small>{ajuda}</small>}
    </div>
  );
}
function Sel({ label, valor, onChange, opcoes, ajuda }) {
  return (
    <div className="cms-field">
      <label>{label}</label>
      <select value={valor} onChange={(e) => onChange(e.target.value)}>{opcoes.map((o) => <option key={o} value={o}>{o}</option>)}</select>
      {ajuda && <small>{ajuda}</small>}
    </div>
  );
}
function Duas({ children }) { return <div className="cms-f-row two">{children}</div>; }
function H({ t, s }) { return <div className="pf-h"><h2>{t}</h2><p>{s}</p></div>; }

// ── Folha de prova: o layout impresso, do jeito que os campos mandam ──
function Prova({ f, rotulos }) {
  const { ProofFrame, RegistrationMark } = ds();
  const itens = [
    { p:"Banner lona 440g — 3,00 × 1,00 m", sku:"BAN-440", d:"Bastão + corda · acabamento reforçado", q:"3,00 m²", u:"78,00", s:"234,00" },
    { p:"Adesivo vinil recorte — logotipo", sku:"ADS-VNL", d:"Aplicação inclusa", q:"1,20 m²", u:"120,00", s:"144,00" },
    { p:"Placa ACM 2 mm — 0,60 × 0,40 m", sku:"ACM-002", d:"", q:"1 un", u:"186,00", s:"186,00" },
  ];
  const folha = (
    <div className="cfg-folha" style={{ "--destaque":f.corDestaque }}>
      <header className="cfg-folha-h">
        <div className="cfg-folha-marca">
          {f.mostraLogo && <span className="cfg-folha-logo">OI</span>}
          <div>
            {f.mostraNegocio && <b className="cfg-folha-neg">Office Impresso</b>}
            {f.mostraLocal && <b className="cfg-folha-loc">ROTA LIVRE — Matriz</b>}
            {[f.sub1, f.sub2, f.sub3, f.sub4, f.sub5].filter(Boolean).map((s, i) => <span key={i} className="cfg-folha-sub">{s}</span>)}
            {f.mostraImposto1 && <span className="cfg-folha-sub mono">CNPJ 41.882.334/0001-07</span>}
            {f.mostraImposto2 && <span className="cfg-folha-sub mono">IE 0018773340092</span>}
          </div>
        </div>
        <div className="cfg-folha-doc">
          <b>{f.tFatura}</b>
          <span className="mono">{f.prefFatura}0002318</span>
          <span>{f.rotData}: 22/08/2026 10:12</span>
          {f.mostraVencimento && <span>{f.rotVencimento}: 05/09/2026</span>}
        </div>
      </header>

      {f.textoCabecalho && <p className="cfg-folha-cab">{f.textoCabecalho}</p>}

      <div className="cfg-folha-partes">
        {f.mostraCliente && (
          <div>
            <span className="cfg-folha-rot">{f.rotCliente}</span>
            <b>Construtora Andrade Ltda</b>
            {f.mostraCodCliente && <span className="mono">{f.rotCodCliente} CT0148</span>}
            <span className="mono">{f.rotImpostoCliente} 08.914.220/0001-31</span>
            {[f.endMarco && "Ao lado do posto Ipiranga", f.endCidade && "Cuiabá", f.endUf && "MT",
              f.endCep && "78065-000", f.endPais && "Brasil"].filter(Boolean).length > 0 && (
              <span>{[f.endMarco && "Ao lado do posto Ipiranga", f.endCidade && "Cuiabá", f.endUf && "MT",
                f.endCep && "78065-000", f.endPais && "Brasil"].filter(Boolean).join(" · ")}</span>)}
            {f.mostraCelular && <span className="mono">(65) 99120-4471</span>}
            {f.mostraTel2 && <span className="mono">(65) 3025-9088</span>}
            {f.mostraEmail && <span>compras@andrade.com.br</span>}
            {f.camposContato.c1 && <span>{rotulos.contato1}: Marcelo Andrade</span>}
            {f.camposContato.c2 && <span>{rotulos.contato2}: Obras</span>}
            {f.mostraPontos && <span>Pontos: 340</span>}
          </div>)}
        <div>
          {f.mostraVendedor && <span>{f.rotVendedor}: <b>Larissa Prado</b></span>}
          {f.mostraComissionado && <span>{f.rotComissionado}: <b>Diego Ramos</b></span>}
          {f.camposVenda.c1 && <span>{rotulos.venda1}: no local, 25/08</span>}
          {f.camposLocal.c1 && <span>{rotulos.local1}: turno da tarde</span>}
        </div>
      </div>

      <table className="cfg-folha-t">
        <thead>
          <tr>
            <th>#</th>
            <th>{f.rotProduto}</th>
            {f.pCodCat && <th>{f.rotCodCat}</th>}
            <th className="num">{f.rotQtd}</th>
            {!f.escondePrecos && <th className="num">{f.rotUnit}</th>}
            {f.rotDescontoItem && !f.escondePrecos && <th className="num">{f.rotDescontoItem}</th>}
            {!f.escondePrecos && <th className="num">{f.rotSubtotal}</th>}
          </tr>
        </thead>
        <tbody>
          {itens.map((it, i) => (
            <tr key={it.sku}>
              <td className="mono">{i + 1}</td>
              <td>
                <b>{it.p}</b>
                {f.pSku && <span className="cfg-folha-min mono">SKU {it.sku}</span>}
                {f.pMarca && <span className="cfg-folha-min">Marca: Vipal</span>}
                {f.pDescricaoVenda && it.d && <span className="cfg-folha-min">{it.d}</span>}
                {f.pDescricaoProduto && <span className="cfg-folha-min">Impressão em alta resolução, tinta UV.</span>}
                {f.pUnidadeBase && <span className="cfg-folha-min">Unidade base: m²</span>}
                {f.camposProduto.c1 && <span className="cfg-folha-min">{rotulos.produto1}: lona brilho</span>}
                {f.pGarantiaNome && <span className="cfg-folha-min">Garantia: 90 dias</span>}
                {f.pValidade && <span className="cfg-folha-min">Validade: —</span>}
                {f.pLote && <span className="cfg-folha-min mono">Lote 2026-08-A</span>}
              </td>
              {f.pCodCat && <td className="mono">C-{200 + i}</td>}
              <td className="num mono">{it.q}</td>
              {!f.escondePrecos && <td className="num mono">{it.u}</td>}
              {f.rotDescontoItem && !f.escondePrecos && <td className="num mono">0,00</td>}
              {!f.escondePrecos && <td className="num mono">{it.s}</td>}
            </tr>))}
        </tbody>
      </table>

      {!f.escondePrecos && (
        <div className="cfg-folha-tot">
          <dl>
            <dt>{f.rotTotalItens}</dt><dd className="mono">3</dd>
            <dt>{f.rotSubtotalGeral}</dt><dd className="mono">564,00</dd>
            <dt>{f.rotDesconto}</dt><dd className="mono">-14,00</dd>
            <dt>{f.rotImposto}</dt><dd className="mono">27,50</dd>
            {f.rotArredonda && <><dt>{f.rotArredonda}</dt><dd className="mono">0,50</dd></>}
            <dt className="forte">{f.rotTotal}</dt><dd className="mono forte">578,00</dd>
            {f.mostraPagamentos && <><dt>{f.rotPago}</dt><dd className="mono">300,00 (Pix)</dd></>}
            <dt>{f.rotDevido}</dt><dd className="mono">278,00</dd>
            {f.mostraSaldoAnterior && <><dt>{f.rotSaldoAnterior}</dt><dd className="mono">1.120,00</dd></>}
            {f.rotTroco && <><dt>{f.rotTroco}</dt><dd className="mono">0,00</dd></>}
          </dl>
          {f.totalEmPalavras && <p className="cfg-folha-palavras">quinhentos e setenta e oito reais</p>}
        </div>)}

      {f.rotResumoImposto && !f.escondePrecos && (
        <div className="cfg-folha-imp">
          <span className="cfg-folha-rot">{f.rotResumoImposto}</span>
          <span className="mono">ICMS 18% — 27,50</span>
        </div>)}

      <footer className="cfg-folha-f">
        <div className="cfg-folha-f-t">
          {f.textoRodape && <p>{f.textoRodape}</p>}
          {f.termos && <p className="cfg-folha-termos">{f.termos}</p>}
        </div>
        <div className="cfg-folha-selos">
          {f.mostraCodigoBarras && <span className="cfg-folha-barras" aria-hidden="true"></span>}
          {f.mostraQr && (
            <span className="cfg-folha-qr">
              <span className="cfg-folha-qr-q" aria-hidden="true"></span>
              {f.rotulosQr && <small>{[f.qr.numero && "nº", f.qr.total && "total", f.qr.url && "link"].filter(Boolean).join(" · ")}</small>}
              {f.zatca && <small className="mono">ZATCA TLV</small>}
            </span>)}
          {RegistrationMark && <RegistrationMark size={22} />}
        </div>
      </footer>
    </div>
  );
  return (
    <aside className="cfg-prova">
      <div className="cfg-prova-h">
        <b>Folha de prova</b>
        <span>{f.design} · {f.nome || "sem nome"}</span>
      </div>
      {ProofFrame ? <ProofFrame cropMarks grid padding={18}>{folha}</ProofFrame> : <div className="cfg-prova-fb">{folha}</div>}
      <p className="cfg-prova-n">Prévia do desenho, não da diagramação final: o legado renderiza <code>sale_pos/receipts/{"{design}"}.blade.php</code> com os mesmos campos.</p>
    </aside>
  );
}

function LayoutEditor({ inicial, rotulos, somenteLeitura, onFechar, onSalvar }) {
  const [sec, setSec] = useState("ident");
  const [f, setF] = useState({ ...LAYOUT_PADRAO, ...(inicial || {}) });
  const [sujo, setSujo] = useState(false);
  const { Nota } = U();
  const set = (k) => (v) => { if (somenteLeitura) return; setF((s) => ({ ...s, [k]:v })); setSujo(true); };
  const liga = (k) => () => { if (somenteLeitura) return; setF((s) => ({ ...s, [k]:!s[k] })); setSujo(true); };
  const ligaSub = (g, k) => () => { if (somenteLeitura) return; setF((s) => ({ ...s, [g]:{ ...s[g], [k]:!s[g][k] } })); setSujo(true); };
  const R = rotulos || {};
  const ligados = useMemo(() => Object.values(f).filter((v) => v === true).length, [f]);

  return (
    <div className="cfg-lay">
      <div className="cfg-lay-h">
        <div>
          <h2>{inicial ? `Layout ${inicial.nome}` : "Novo layout da fatura"}</h2>
          <p>{ligados} campos ligados · <code>invoice_layout/create.blade.php</code></p>
        </div>
        <div className="cfg-lay-h-a">
          <button className="os-btn ghost" onClick={onFechar}>Voltar</button>
          <button className="os-btn primary" disabled={somenteLeitura || !sujo} onClick={() => { onSalvar(f); setSujo(false); }}>Salvar layout</button>
        </div>
      </div>

      {somenteLeitura && Nota && (
        <div className="hrm-note-ds"><Nota tone="warn" title="Somente leitura">
          Layout da fatura é do administrador — você pode ver o desenho e imprimir uma prova, mas não alterar.
        </Nota></div>)}

      <div className="cfg-lay-body">
        <nav className="fnc-rail pf-rail">
          {SECOES.map((g) => (
            <div key={g.g} className="fnc-rail-dom">
              <span className="fnc-rail-dom-l">{g.g}</span>
              {g.itens.map((i) => (
                <button key={i.id} className={`fnc-rail-g ${sec === i.id ? "on" : ""}`} onClick={() => setSec(i.id)}>
                  <span className="fnc-rail-g-l">{i.label}</span>
                </button>))}
            </div>))}
        </nav>

        <section className="fnc-pane pf-pane cfg-lay-pane">
          {sec === "ident" && (
            <>
              <H t="Identificação" s="Nome e desenho base. O desenho decide a diagramação; os campos decidem o conteúdo." />
              <div className="pf-form">
                <Duas>
                  <Txt label="Nome do layout" valor={f.nome} onChange={set("nome")} />
                  <Sel label="Desenho" valor={f.design} onChange={set("design")}
                    opcoes={["Clássico", "Enxuto (impressora térmica)", "Detalhado", "Elegante", "Elegante modificado", "Colunas de imposto"]} />
                </Duas>
                <Cks>
                  <Ck label="Definir como padrão" on={f.padrao} onToggle={liga("padrao")} />
                  <Ck label="Papel timbrado" ajuda="Deixa o topo em branco pro papel já impresso." on={f.papelTimbrado} onToggle={liga("papelTimbrado")} />
                  <Ck label="Mostrar logo" on={f.mostraLogo} onToggle={liga("mostraLogo")} />
                  <Ck label="Esconder todos os preços" ajuda="Vira romaneio de entrega." on={f.escondePrecos} onToggle={liga("escondePrecos")} />
                </Cks>
                <Duas>
                  <Txt label="Cor de destaque" valor={f.corDestaque} onChange={set("corDestaque")} mono
                    ajuda="O legado aceita qualquer hex — o sistema usa o roxo da marca." />
                  <span />
                </Duas>
              </div>
            </>)}

          {sec === "cabec" && (
            <>
              <H t="Cabeçalho" s="Cinco linhas livres embaixo do nome — endereço, telefone, redes." />
              <div className="pf-form">
                <Txt label="Texto do cabeçalho" valor={f.textoCabecalho} onChange={set("textoCabecalho")} largo />
                {[1, 2, 3, 4, 5].map((n) => (
                  <Txt key={n} label={`Linha ${n}`} valor={f["sub" + n]} onChange={set("sub" + n)} largo />))}
              </div>
            </>)}

          {sec === "titulos" && (
            <>
              <H t="Títulos do documento" s="O mesmo layout imprime fatura, recibo, orçamento e pedido — cada um com seu título." />
              <div className="pf-form">
                <Duas>
                  <Txt label="Fatura" valor={f.tFatura} onChange={set("tFatura")} />
                  <Txt label="Quando está pago" valor={f.tPago} onChange={set("tPago")} />
                </Duas>
                <Duas>
                  <Txt label="Quando está em aberto" valor={f.tNaoPago} onChange={set("tNaoPago")} />
                  <Txt label="Proforma" valor={f.tProforma} onChange={set("tProforma")} />
                </Duas>
                <Duas>
                  <Txt label="Orçamento" valor={f.tOrcamento} onChange={set("tOrcamento")} />
                  <Txt label="Pedido de venda" valor={f.tPedido} onChange={set("tPedido")} />
                </Duas>
                <Duas>
                  <Txt label="Prefixo do número da fatura" valor={f.prefFatura} onChange={set("prefFatura")} mono />
                  <Txt label="Prefixo do número do orçamento" valor={f.prefOrcamento} onChange={set("prefOrcamento")} mono />
                </Duas>
                <Duas>
                  <Txt label="Rótulo da data" valor={f.rotData} onChange={set("rotData")} />
                  <Txt label="Rótulo do vencimento" valor={f.rotVencimento} onChange={set("rotVencimento")} />
                </Duas>
                <Duas>
                  <Sel label="Formato de data e hora" valor={f.formatoData} onChange={set("formatoData")}
                    opcoes={["dd/mm/aaaa HH:mm", "dd/mm/aaaa", "aaaa-mm-dd HH:mm", "dd de mmmm de aaaa"]} />
                  <span />
                </Duas>
                <Cks><Ck label="Mostrar vencimento" on={f.mostraVencimento} onToggle={liga("mostraVencimento")} /></Cks>
              </div>
            </>)}

          {sec === "negocio" && (
            <>
              <H t="Negócio e vendedor" s="Quem emitiu e quem atendeu." />
              <div className="pf-form">
                <Cks>
                  <Ck label="Nome do negócio" on={f.mostraNegocio} onToggle={liga("mostraNegocio")} />
                  <Ck label="Nome do local" on={f.mostraLocal} onToggle={liga("mostraLocal")} />
                  <Ck label="Vendedor" on={f.mostraVendedor} onToggle={liga("mostraVendedor")} />
                  <Ck label="Comissionado" on={f.mostraComissionado} onToggle={liga("mostraComissionado")} />
                </Cks>
                <Duas>
                  <Txt label="Rótulo do vendedor" valor={f.rotVendedor} onChange={set("rotVendedor")} />
                  <Txt label="Rótulo do comissionado" valor={f.rotComissionado} onChange={set("rotComissionado")} />
                </Duas>
                <Bloco titulo="Campos personalizados da venda">
                  <Cks>
                    {[1, 2, 3, 4].map((n) => (
                      <Ck key={n} label={R["venda" + n] || `Venda · campo ${n}`} on={f.camposVenda["c" + n]} onToggle={ligaSub("camposVenda", "c" + n)} />))}
                  </Cks>
                </Bloco>
              </div>
            </>)}

          {sec === "cliente" && (
            <>
              <H t="Cliente" s="Como o comprador aparece impresso." />
              <div className="pf-form">
                <Cks>
                  <Ck label="Mostrar cliente" on={f.mostraCliente} onToggle={liga("mostraCliente")} />
                  <Ck label="Código do cliente" on={f.mostraCodCliente} onToggle={liga("mostraCodCliente")} />
                  <Ck label="Pontos de fidelidade" on={f.mostraPontos} onToggle={liga("mostraPontos")} />
                </Cks>
                <Duas>
                  <Txt label="Rótulo do cliente" valor={f.rotCliente} onChange={set("rotCliente")} />
                  <Txt label="Rótulo do código" valor={f.rotCodCliente} onChange={set("rotCodCliente")} />
                </Duas>
                <Duas>
                  <Txt label="Rótulo do documento do cliente" valor={f.rotImpostoCliente} onChange={set("rotImpostoCliente")}
                    ajuda="No Brasil vale CNPJ e CPF na mesma linha." />
                  <span />
                </Duas>
                <Bloco titulo="Campos personalizados do contato">
                  <Cks>
                    {[1, 2, 3, 4].map((n) => (
                      <Ck key={n} label={R["contato" + n] || `Contato · campo ${n}`} on={f.camposContato["c" + n]} onToggle={ligaSub("camposContato", "c" + n)} />))}
                  </Cks>
                </Bloco>
              </div>
            </>)}

          {sec === "endereco" && (
            <>
              <H t="Endereço e contato" s="Endereço do cliente, telefone e os dois números fiscais da empresa." />
              <div className="pf-form">
                <Bloco titulo="Endereço">
                  <Cks>
                    <Ck label="Ponto de referência" on={f.endMarco} onToggle={liga("endMarco")} />
                    <Ck label="Cidade" on={f.endCidade} onToggle={liga("endCidade")} />
                    <Ck label="Estado" on={f.endUf} onToggle={liga("endUf")} />
                    <Ck label="CEP" on={f.endCep} onToggle={liga("endCep")} />
                    <Ck label="País" on={f.endPais} onToggle={liga("endPais")} />
                  </Cks>
                </Bloco>
                <Bloco titulo="Contato">
                  <Cks>
                    <Ck label="Celular" on={f.mostraCelular} onToggle={liga("mostraCelular")} />
                    <Ck label="Telefone alternativo" on={f.mostraTel2} onToggle={liga("mostraTel2")} />
                    <Ck label="E-mail" on={f.mostraEmail} onToggle={liga("mostraEmail")} />
                  </Cks>
                </Bloco>
                <Bloco titulo="Impostos da empresa">
                  <Cks>
                    <Ck label={`Mostrar ${R.imp1 || "imposto 1"}`} on={f.mostraImposto1} onToggle={liga("mostraImposto1")} />
                    <Ck label={`Mostrar ${R.imp2 || "imposto 2"}`} on={f.mostraImposto2} onToggle={liga("mostraImposto2")} />
                  </Cks>
                </Bloco>
                <Bloco titulo="Campos personalizados do local">
                  <Cks>
                    {[1, 2, 3, 4].map((n) => (
                      <Ck key={n} label={R["local" + n] || `Local · campo ${n}`} on={f.camposLocal["c" + n]} onToggle={ligaSub("camposLocal", "c" + n)} />))}
                  </Cks>
                </Bloco>
              </div>
            </>)}

          {sec === "tabela" && (
            <>
              <H t="Tabela de itens" s="Os cabeçalhos das colunas — é aqui que “Produto” vira “Produto / serviço”." />
              <div className="pf-form">
                <Duas>
                  <Txt label="Produto" valor={f.rotProduto} onChange={set("rotProduto")} />
                  <Txt label="Quantidade" valor={f.rotQtd} onChange={set("rotQtd")} />
                </Duas>
                <Duas>
                  <Txt label="Preço unitário" valor={f.rotUnit} onChange={set("rotUnit")} />
                  <Txt label="Subtotal" valor={f.rotSubtotal} onChange={set("rotSubtotal")} />
                </Duas>
                <Duas>
                  <Txt label="Código do produto" valor={f.rotCodCat} onChange={set("rotCodCat")} />
                  <Txt label="Total de itens" valor={f.rotQtdTotal} onChange={set("rotQtdTotal")} />
                </Duas>
                <Duas>
                  <Txt label="Desconto do item" valor={f.rotDescontoItem} onChange={set("rotDescontoItem")} ajuda="Em branco esconde a coluna." />
                  <Txt label="Unitário com desconto" valor={f.rotUnitComDesconto} onChange={set("rotUnitComDesconto")} />
                </Duas>
              </div>
            </>)}

          {sec === "produto" && (
            <>
              <H t="Detalhes do produto" s="O que sai embaixo do nome de cada item." />
              <div className="pf-form">
                <Cks>
                  <Ck label="Marca" on={f.pMarca} onToggle={liga("pMarca")} />
                  <Ck label="SKU" on={f.pSku} onToggle={liga("pSku")} />
                  <Ck label="Código do produto" on={f.pCodCat} onToggle={liga("pCodCat")} />
                  <Ck label="Descrição de venda" ajuda="No legado guarda IMEI e número de série." on={f.pDescricaoVenda} onToggle={liga("pDescricaoVenda")} />
                  <Ck label="Descrição do produto" on={f.pDescricaoProduto} onToggle={liga("pDescricaoProduto")} />
                  <Ck label="Unidade base" on={f.pUnidadeBase} onToggle={liga("pUnidadeBase")} />
                  <Ck label="Validade" on={f.pValidade} onToggle={liga("pValidade")} />
                  <Ck label="Lote" on={f.pLote} onToggle={liga("pLote")} />
                  <Ck label="Imagem" on={f.pImagem} onToggle={liga("pImagem")} />
                  <Ck label="Nome da garantia" on={f.pGarantiaNome} onToggle={liga("pGarantiaNome")} />
                  <Ck label="Vencimento da garantia" on={f.pGarantiaVenc} onToggle={liga("pGarantiaVenc")} />
                </Cks>
                <Bloco titulo="Campos personalizados do produto">
                  <Cks>
                    {[1, 2, 3, 4].map((n) => (
                      <Ck key={n} label={R["produto" + n] || `Produto · campo ${n}`} on={f.camposProduto["c" + n]} onToggle={ligaSub("camposProduto", "c" + n)} />))}
                  </Cks>
                </Bloco>
              </div>
            </>)}

          {sec === "totais" && (
            <>
              <H t="Totais e pagamento" s="O bloco de fechamento da folha." />
              <div className="pf-form">
                <Duas>
                  <Txt label="Subtotal" valor={f.rotSubtotalGeral} onChange={set("rotSubtotalGeral")} />
                  <Txt label="Desconto" valor={f.rotDesconto} onChange={set("rotDesconto")} />
                </Duas>
                <Duas>
                  <Txt label="Imposto" valor={f.rotImposto} onChange={set("rotImposto")} />
                  <Txt label="Total" valor={f.rotTotal} onChange={set("rotTotal")} />
                </Duas>
                <Duas>
                  <Txt label="Total de itens" valor={f.rotTotalItens} onChange={set("rotTotalItens")} />
                  <Txt label="Arredondamento" valor={f.rotArredonda} onChange={set("rotArredonda")} />
                </Duas>
                <Duas>
                  <Txt label="Em aberto (esta venda)" valor={f.rotDevido} onChange={set("rotDevido")} />
                  <Txt label="Pago" valor={f.rotPago} onChange={set("rotPago")} />
                </Duas>
                <Duas>
                  <Txt label="Em aberto (todas as vendas)" valor={f.rotSaldoAnterior} onChange={set("rotSaldoAnterior")} />
                  <Txt label="Troco" valor={f.rotTroco} onChange={set("rotTroco")} />
                </Duas>
                <Duas>
                  <Txt label="Resumo de impostos" valor={f.rotResumoImposto} onChange={set("rotResumoImposto")} />
                  <Sel label="Total em palavras" valor={f.formatoPalavras} onChange={set("formatoPalavras")} opcoes={["Português (Brasil)", "Inglês"]} />
                </Duas>
                <Cks>
                  <Ck label="Listar pagamentos recebidos" on={f.mostraPagamentos} onToggle={liga("mostraPagamentos")} />
                  <Ck label="Saldo anterior do cliente" on={f.mostraSaldoAnterior} onToggle={liga("mostraSaldoAnterior")} />
                  <Ck label="Código de barras do documento" on={f.mostraCodigoBarras} onToggle={liga("mostraCodigoBarras")} />
                  <Ck label="Total escrito em palavras" on={f.totalEmPalavras} onToggle={liga("totalEmPalavras")} />
                </Cks>
              </div>
            </>)}

          {sec === "qr" && (
            <>
              <H t="QR code" s="O que vai dentro do código impresso." />
              <div className="pf-form">
                <Cks>
                  <Ck label="Imprimir QR code" on={f.mostraQr} onToggle={liga("mostraQr")} />
                  <Ck label="Mostrar rótulos ao lado" on={f.rotulosQr} onToggle={liga("rotulosQr")} />
                  <Ck label="Formato ZATCA" ajuda="Exigência da Arábia Saudita — não se aplica ao Brasil." on={f.zatca} onToggle={liga("zatca")} />
                </Cks>
                <Bloco titulo="Campos dentro do QR">
                  <Cks>
                    <Ck label="Nome do negócio" on={f.qr.negocio} onToggle={ligaSub("qr", "negocio")} />
                    <Ck label="Endereço do local" on={f.qr.endereco} onToggle={ligaSub("qr", "endereco")} />
                    <Ck label={R.imp1 || "Imposto 1"} on={f.qr.imposto1} onToggle={ligaSub("qr", "imposto1")} />
                    <Ck label={R.imp2 || "Imposto 2"} on={f.qr.imposto2} onToggle={ligaSub("qr", "imposto2")} />
                    <Ck label="Número da fatura" on={f.qr.numero} onToggle={ligaSub("qr", "numero")} />
                    <Ck label="Data e hora" on={f.qr.dataHora} onToggle={ligaSub("qr", "dataHora")} />
                    <Ck label="Subtotal" on={f.qr.subtotal} onToggle={ligaSub("qr", "subtotal")} />
                    <Ck label="Total com imposto" on={f.qr.total} onToggle={ligaSub("qr", "total")} />
                    <Ck label="Imposto total" on={f.qr.impostoTotal} onToggle={ligaSub("qr", "impostoTotal")} />
                    <Ck label="Nome do cliente" on={f.qr.cliente} onToggle={ligaSub("qr", "cliente")} />
                    <Ck label="Link do documento" on={f.qr.url} onToggle={ligaSub("qr", "url")} />
                  </Cks>
                </Bloco>
                {Nota && <div className="hrm-note-ds"><Nota tone="info" title="QR da NF-e é outro">
                  Este QR é do documento interno. O da NFC-e vem assinado pela SEFAZ e é montado pelo módulo fiscal — não se configura aqui.
                </Nota></div>}
              </div>
            </>)}

          {sec === "credito" && (
            <>
              <H t="Nota de crédito" s="Usada na devolução de venda." />
              <div className="pf-form">
                <Duas>
                  <Txt label="Título" valor={f.cnTitulo} onChange={set("cnTitulo")} />
                  <Txt label="Rótulo do número" valor={f.cnRotNumero} onChange={set("cnRotNumero")} />
                </Duas>
                <Duas>
                  <Txt label="Rótulo do valor" valor={f.cnRotValor} onChange={set("cnRotValor")} />
                  <span />
                </Duas>
              </div>
            </>)}

          {sec === "rodape" && (
            <>
              <H t="Rodapé" s="A última coisa que o cliente lê." />
              <div className="pf-form">
                <div className="cms-field pf-largo">
                  <label>Texto do rodapé</label>
                  <textarea rows="3" value={f.textoRodape} onChange={(e) => set("textoRodape")(e.target.value)}></textarea>
                </div>
                <div className="cms-field pf-largo">
                  <label>Termos e condições</label>
                  <textarea rows="4" value={f.termos} onChange={(e) => set("termos")(e.target.value)}></textarea>
                  <small>Sai em corpo menor abaixo do rodapé.</small>
                </div>
              </div>
            </>)}
        </section>

        <Prova f={f} rotulos={R} />
      </div>
    </div>
  );
}

function Bloco({ titulo, children }) { return <div className="cfg-bloco"><h3>{titulo}</h3>{children}</div>; }

window.ConfigFatura = { LayoutEditor, LAYOUT_PADRAO };
})();
