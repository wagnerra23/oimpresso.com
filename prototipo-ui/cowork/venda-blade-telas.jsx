// venda-blade-telas.jsx — Ondas 2 e 3 do menu Vendas: as telas e ações que os blades
// abrem a partir das listas, e que antes eram só toast no protótipo.
//   sale_pos/show.blade.php ................ VendaDetalhe (drawer PT-02, nunca modal full-screen)
//   import_sales/preview.blade.php ......... VendaImportPreview (mapeamento de colunas + validações)
//   sales_order/index + edit_status_modal .. VendaPedidos (item condicional enable_sales_order)
//   sells/create?status=draft|quotation .... VendaNova (faixa de contexto + o create V3 vivo)
// Expõe window.VendaDetalhe, window.VendaImportPreview, window.VendaPedidos, window.VendaNova.
(() => {
const { useState, useMemo } = React;const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const D = () => window.VBD || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Linhas da venda — determinísticas a partir do id (o show.blade.php lista sell_lines).
// Duas verdades que precisam valer ao mesmo tempo numa tela de ERP:
//   (1) qtd × preço unitário == subtotal da linha (o operador faz essa conta de cabeça);
//   (2) Σ subtotais == total da transação.
// Por isso a ÚLTIMA linha nasce com quantidade 1 (preço == subtotal, conta exata por
// construção) e é nela que o resto de centavos cai — sem reescrever quantidade depois
// nem deformar subtotal de linha nenhuma.
const linhasDe = (v) => {
  const cat = window.VENDA_POS_CAT || [];
  const n = Math.max(1, Math.min(v.itens || 3, 6));
  const base = Array.from({ length: n }, (_, i) => {
    const p = cat[(v.id * 3 + i) % cat.length] || { nome: "Item", sku: "—", un: "Un", preco: 100 };
    return { nome: p.nome, sku: p.sku, un: p.un, qtd: i === n - 1 ? 1 : ((v.id + i) % 4) + 1, preco: p.preco };
  });
  const bruto = base.reduce((a, l) => a + l.qtd * l.preco, 0) || 1;
  // Venda grande com poucas linhas: cresce a QUANTIDADE, não o preço unitário —
  // senão uma lona de R$ 55 apareceria a R$ 214 na tela do balcão.
  let fator = (v.total || 0) / bruto;
  if (fator > 1.6) {
    base.forEach((l, i) => { if (i < base.length - 1) l.qtd = Math.max(1, Math.round(l.qtd * fator)); });
    fator = (v.total || 0) / (base.reduce((a, l) => a + l.qtd * l.preco, 0) || 1);
  }
  const linhas = base.map((l) => {
    const preco = Math.round(l.preco * fator * 100) / 100;
    return { ...l, preco, subtotal: Math.round(l.qtd * preco * 100) / 100 };
  });
  const soma = Math.round(linhas.reduce((a, l) => a + l.subtotal, 0) * 100) / 100;
  const resto = Math.round(((v.total || 0) - soma) * 100) / 100;
  if (resto !== 0) {
    const ult = linhas[linhas.length - 1];
    const novo = Math.round((ult.preco + resto) * 100) / 100;
    linhas[linhas.length - 1] = { ...ult, preco: novo, subtotal: novo };
  }
  return linhas;
};
const pagamentosDe = (v) => {
  if (!v.pago) return [];
  return [{ ref: "PG-" + (4200 + v.id), data: v.data, metodo: v.forma, valor: v.pago, conta: "Caixa balcão" }];
};

// ─────────── Detalhe da venda ───────────
// Onda A2: espelha o `SaleSheet` do `Sells/Index.tsx` vivo (charter v9) — KV grid · itens ·
// pagamentos · mensagem de WhatsApp em 3 abas · seção fiscal · próxima ação do pipeline (FSM,
// ADR 0143) · ordem de serviço cross-módulo (ADR 0192) · histórico append-only.
const ETAPAS_FSM = ["Orçamento", "Aprovada", "Produção", "Acabamento", "Expedição", "Entregue"];
const PROXIMA = {
  1: { o: "Aprovar com o cliente", p: "Sem aprovação a produção não entra na fila." },
  2: { o: "Liberar para produção", p: "Material conferido, arte fechada." },
  3: { o: "Concluir a produção", p: "Depois vai pro acabamento." },
  4: { o: "Fechar acabamento", p: "Ilhós, corte e revisão final." },
  5: { o: "Despachar", p: "Gera romaneio e baixa a expedição." },
  6: { o: "Confirmar recebimento", p: "Fecha a venda no pós-venda." },
};
// Os 5 estados fiscais do vivo — mesmo vocabulário da lista. O nome do documento sai
// do dado: modelo 55 é NF-e (mercadoria), 65 é NFC-e (consumidor). `pendente` é transmissão
// em voo na SEFAZ: reemitir duplicaria a nota (constraint nfe_emissoes_biz_fx_unique).
const docFiscal = (v) => (v.fiscal_modelo === "65" ? "NFC-e 65" : "NF-e " + (v.fiscal_modelo || "55"));
const FISCAL_ROT = {
  autorizada: (v) => docFiscal(v) + " autorizada",
  pendente: (v) => docFiscal(v) + " em transmissão (processando)",
  rejeitada: (v) => docFiscal(v) + " rejeitada — corrija e reenvie",
  denegada: (v) => docFiscal(v) + " denegada pela SEFAZ",
  cancelada: (v) => docFiscal(v) + " cancelada",
};
const FISCAL_TRAVA = ["autorizada", "pendente", "denegada"];
const MOTIVO_TRAVA = {
  autorizada: (v) => "Esta venda já tem " + docFiscal(v) + " autorizada — o servidor recusa emissão duplicada.",
  pendente: () => "A transmissão está em andamento na SEFAZ — espere o retorno antes de tentar de novo.",
  denegada: () => "Nota denegada não se reemite para o mesmo destinatário — resolva a pendência fiscal do cliente.",
};
const MSG = {
  confirmacao: (v) => "Oi! Aqui é da Office Impresso. Confirmamos seu pedido " + v.inv + " no valor de " + brl(v.total) + ". Qualquer ajuste, é só responder por aqui.",
  retirada: (v) => "Seu pedido " + v.inv + " está pronto para retirada na " + (v.loc || "loja") + ". Funcionamos de segunda a sexta, das 8h às 18h.",
  cobranca: (v) => "Passando pra lembrar do saldo de " + brl(v.saldo) + " do pedido " + v.inv + ". Posso enviar o Pix ou o boleto?",
};
const historicoDe = (v) => [
  { q: v.data, o: v.quem, t: "Venda criada no balcão" },
  ...(v.pago ? [{ q: v.data, o: v.quem, t: "Pagamento de " + brl(v.pago) + " em " + (v.forma || "dinheiro") }] : []),
  ...(v.fiscal_status === "autorizada" || v.pg === "paid" ? [{ q: v.data, o: "Sistema", t: docFiscal(v) + " autorizada pela SEFAZ" }] : []),
  ...(v.saldo ? [{ q: v.data, o: "Sistema", t: "Saldo de " + brl(v.saldo) + " foi pro contas a receber" }] : []),
];

function VendaDetalhe({ venda, onClose, avisar, onAcao }) {
  const { Drawer, DrawerSection, StatusBadge } = DS();
  const [aba, setAba] = useState("confirmacao");
  if (!venda || !Drawer) return null;
  const linhas = linhasDe(venda);
  const sub = linhas.reduce((a, l) => a + l.subtotal, 0);
  const pagos = pagamentosDe(venda);
  const passo = venda.pipeline_step || (venda.saldo ? 2 : 5);
  const prox = PROXIMA[passo] || PROXIMA[1];
  return (
    <Drawer open onClose={onClose} title={venda.inv} subtitle={venda.cli + " · " + venda.loc}
      badge={StatusBadge ? <StatusBadge kind="payment" value={{ paid: "Pago", due: "Devido", partial: "Parcial", overdue: "Vencido" }[venda.pg] || venda.pg} /> : null}
      footer={
        <div className="vt-drawer-f">
          <button className="os-btn" onClick={() => avisar("Recibo de " + venda.inv + " na impressora térmica.", "ok")}><Ic name="print" size={13} /> Imprimir</button>
          <button className="os-btn" onClick={() => onAcao?.("devolver", venda)}>Devolver</button>
          <div className="sp" />
          <button className="os-btn primary" disabled={!venda.saldo} onClick={() => onAcao?.("pagamento", venda)}><Ic name="cash" size={13} /> Adicionar pagamento</button>
        </div>}>
      <DrawerSection title="Resumo">
        <div className="vt-resumo">
          <div><span>Data</span><b className="mono">{venda.data}</b></div>
          <div><span>Vendedor</span><b>{venda.quem}</b></div>
          <div><span>Contato</span><b className="mono">{venda.tel}</b></div>
          <div><span>Tipo de serviço</span><b>{venda.serv}</b></div>
          <div><span>Forma de pagamento</span><b>{venda.forma}</b></div>
          <div><span>Status de envio</span><b>{{ ordered: "Pedido", packed: "Embalado", shipped: "Enviado", delivered: "Entregue", cancelled: "Cancelado" }[venda.envio] || "—"}</b></div>
        </div>
      </DrawerSection>

      <DrawerSection title="Próxima ação">
        <div className="vt-fsm">
          <div className="vt-fsm-etapas">
            {ETAPAS_FSM.map((e, i) => (
              <span key={e} className={"vt-fsm-e" + (i + 1 < passo ? " feita" : i + 1 === passo ? " agora" : "")}>
                <i>{i + 1}</i>{e}
              </span>
            ))}
          </div>
          <div className="vt-fsm-cta">
            <div><b>{prox.o}</b><em>{prox.p}</em></div>
            <button className="os-btn sm primary" onClick={() => avisar(prox.o + " — " + venda.inv + " avançou no pipeline.", "ok")}>{prox.o}</button>
          </div>
        </div>
      </DrawerSection>

      <DrawerSection title="Itens">
        <table className="pb-tbl">
          <thead><tr><th>Produto</th><th className="r">Qtd</th><th className="r">Preço unit.</th><th className="r">Subtotal</th></tr></thead>
          <tbody>
            {linhas.map((l, i) => (
              <tr key={i}>
                <td><b>{l.nome}</b><div className="pb-help mono">{l.sku} · {l.un}</div></td>
                <td className="r mono">{l.qtd}</td><td className="r mono">{brl(l.preco)}</td><td className="r mono">{brl(l.subtotal)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="vt-totais">
          <div><span>Subtotal</span><b className="mono">{brl(sub)}</b></div>
          <div><span>Total da venda</span><b className="mono">{brl(venda.total)}</b></div>
          <div><span>Total pago</span><b className="mono">{brl(venda.pago)}</b></div>
          <div className={venda.saldo ? "warn" : ""}><span>Saldo devedor</span><b className="mono">{brl(venda.saldo)}</b></div>
        </div>
      </DrawerSection>

      <DrawerSection title="Pagamentos">
        {pagos.length
          ? <table className="pb-tbl">
              <thead><tr><th>Referência</th><th>Data</th><th>Forma</th><th>Conta</th><th className="r">Valor</th></tr></thead>
              <tbody>{pagos.map((p) => <tr key={p.ref}><td className="mono">{p.ref}</td><td className="mono">{p.data}</td><td>{p.metodo}</td><td>{p.conta}</td><td className="r mono">{brl(p.valor)}</td></tr>)}</tbody>
            </table>
          : <p className="pb-help">Nenhum pagamento registrado — a venda está inteira em aberto.</p>}
      </DrawerSection>

      <DrawerSection title="Mensagem para o cliente">
        <div className="vt-msg-abas">
          {[["confirmacao", "Confirmação"], ["retirada", "Retirada"], ["cobranca", "Cobrança"]].map(([k, l]) => (
            <button key={k} className={aba === k ? "on" : ""} onClick={() => setAba(k)} disabled={k === "cobranca" && !venda.saldo}>{l}</button>
          ))}
        </div>
        <p className="vt-msg">{MSG[aba](venda)}</p>
        <div className="vt-msg-f">
          <button className="os-btn sm" onClick={() => avisar("Mensagem copiada.", "ok")}>Copiar</button>
          <button className="os-btn sm primary" onClick={() => avisar("WhatsApp aberto com a mensagem — nada é enviado sem você confirmar.", "ok")}>Abrir no WhatsApp</button>
        </div>
      </DrawerSection>

      <DrawerSection title="Fiscal">
        <div className="vt-fiscal">
          <div><span>Documento</span><b>{FISCAL_ROT[venda.fiscal_status] ? FISCAL_ROT[venda.fiscal_status](venda) : "Sem nota emitida"}</b></div>
          <div><span>Cliente</span><b>{venda.cli === "Cliente balcão" ? "Sem CPF/CNPJ — bloqueia emissão" : "Documento cadastrado"}</b></div>
        </div>
        <div className="vt-msg-f">
          <button className="os-btn sm" disabled={venda.cli === "Cliente balcão" || FISCAL_TRAVA.includes(venda.fiscal_status)}
            title={FISCAL_TRAVA.includes(venda.fiscal_status) ? MOTIVO_TRAVA[venda.fiscal_status](venda) : undefined}
            onClick={() => onAcao?.("nfe", venda)}>Emitir NF-e</button>
          <button className="os-btn sm" onClick={() => avisar("NFS-e de serviço — usa a lista de serviços do município.", "ok")}>Emitir NFS-e</button>
        </div>
      </DrawerSection>

      <DrawerSection title="Ordem de serviço">
        {venda.os_ref
          ? <p className="pb-help">Esta venda nasceu da OS <b className="mono">{venda.os_ref}</b> na Oficina. <button className="os-btn sm" onClick={() => avisar("Abrindo " + venda.os_ref + ".", "ok")}>Abrir OS</button></p>
          : <p className="pb-help">Sem OS vinculada. <button className="os-btn sm" onClick={() => avisar("OS criada a partir de " + venda.inv + " — itens copiados.", "ok")}>Criar OS desta venda</button></p>}
      </DrawerSection>

      <DrawerSection title="Histórico">
        <ul className="vt-hist">
          {historicoDe(venda).map((h, i) => (
            <li key={i}><i /><div><b>{h.t}</b><em>{h.q} · {h.o}</em></div></li>
          ))}
        </ul>
        <p className="pb-help">O histórico só cresce — nada some daqui, nem quando a venda é cancelada.</p>
      </DrawerSection>

      {venda.obs &&
        <DrawerSection title="Observação da venda"><p className="pb-help">{venda.obs}</p></DrawerSection>}
    </Drawer>
  );
}

// ─────────── Prévia da importação (import_sales/preview.blade.php) ───────────
const CAMPOS_MAP = [
  { id: "", name: "Pular coluna" },
  { id: "invoice_no", name: "Nº da fatura" }, { id: "customer_name", name: "Nome do cliente" },
  { id: "customer_phone_number", name: "Telefone do cliente" }, { id: "customer_email", name: "E-mail do cliente" },
  { id: "product", name: "Produto" }, { id: "sku", name: "SKU do produto" },
  { id: "quantity", name: "Quantidade" }, { id: "unit_price", name: "Preço unitário" },
  { id: "sale_date", name: "Data da venda" }, { id: "payment_status", name: "Status do pagamento" },
  { id: "payment_method", name: "Forma de pagamento" }, { id: "discount", name: "Desconto" },
  { id: "order_tax", name: "Imposto do pedido" }, { id: "sell_note", name: "Observação da venda" },
];
const PLANILHA = [
  ["Fatura", "Cliente", "Telefone", "SKU", "Qtd", "Preço", "Data", "Pagamento"],
  ["IMP-1001", "Rota Livre Transportes", "14998124410", "PRD-0001", "12", "55,00", "2026-08-14 09:10:00", "pago"],
  ["IMP-1001", "Rota Livre Transportes", "14998124410", "PRD-0009", "4", "39,50", "2026-08-14 09:10:00", "pago"],
  ["IMP-1002", "Agência Norte", "1436221180", "PRD-0004", "2", "149,80", "2026-08-14 11:32:00", "devido"],
  ["IMP-1003", "Martinho Oficina", "14996402299", "PRD-0011", "1", "319,00", "2026-08-15 08:05:00", "parcial"],
];
const MAP_INICIAL = ["invoice_no", "customer_name", "customer_phone_number", "sku", "quantity", "unit_price", "sale_date", "payment_status"];

function VendaImportPreview({ arquivo, onVoltar, avisar }) {
  const { Widget, Fld, Sel } = UI();
  const { Alert } = DS();
  const [mapa, setMapa] = useState(MAP_INICIAL);
  const [grupo, setGrupo] = useState("Fatura");
  const [loc, setLoc] = useState("");
  const erros = useMemo(() => {
    const e = [];
    const tem = (k) => mapa.includes(k);
    if (!tem("customer_phone_number") && !tem("customer_email")) e.push("Mapeie telefone ou e-mail do cliente — um dos dois é obrigatório.");
    if (!tem("product") && !tem("sku")) e.push("Mapeie o produto ou o SKU.");
    if (!tem("quantity")) e.push("Mapeie a quantidade.");
    if (!tem("unit_price")) e.push("Mapeie o preço unitário.");
    const usados = mapa.filter(Boolean);
    if (new Set(usados).size !== usados.length) e.push("Um campo não pode ser usado em duas colunas.");
    if (!loc) e.push("Escolha o local do negócio.");
    return e;
  }, [mapa, loc]);

  if (!Widget) return null;
  return (
    <>
      <Widget titulo={<><Ic name="upload" size={13} /> Prévia da importação</>} nota={arquivo || "vendas.xlsx"}>
        <div className="pb-grid c2">
          <Fld label="Agrupar linhas da venda por" req dica="As linhas com o mesmo valor nessa coluna viram uma única venda.">
            <Sel value={grupo} onChange={setGrupo} options={PLANILHA[0]} />
          </Fld>
          <Fld label="Local do negócio" req><Sel value={loc} onChange={setLoc} options={(D().LOCAIS || []).map((l) => l.name)} vazio="Selecione" /></Fld>
        </div>
      </Widget>
      <Widget flush titulo={<><Ic name="sheet" size={13} /> Mapeamento das colunas</>} nota={PLANILHA.length - 1 + " linha(s) na prévia"}>
        <div className="vt-preview">
          <table className="pb-tbl">
            <thead>
              <tr><th>#</th>{PLANILHA[0].map((h) => <th key={h}>{h}</th>)}</tr>
              <tr className="vt-map">
                <td />
                {PLANILHA[0].map((h, i) => (
                  <td key={h}>
                    <select value={mapa[i] || ""} onChange={(e) => setMapa((m) => m.map((x, j) => j === i ? e.target.value : x))}>
                      {CAMPOS_MAP.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                  </td>
                ))}
              </tr>
            </thead>
            <tbody>
              {PLANILHA.slice(1).map((r, i) => (
                <tr key={i}><td className="mono">{i + 1}</td>{r.map((c, j) => <td key={j} className="mono">{c}</td>)}</tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="pb-pag">
          {erros.length && Alert
            ? <Alert tone="warn" title="Faltou mapear">{erros[0]}</Alert>
            : <span className="pb-help">Tudo mapeado — a importação cria {new Set(PLANILHA.slice(1).map((r) => r[PLANILHA[0].indexOf(grupo)])).size} venda(s).</span>}
          <div className="sp" />
          <button className="os-btn sm" onClick={onVoltar}>Voltar</button>
          <button className="os-btn sm primary" disabled={!!erros.length} onClick={() => { avisar("Importação enviada — as vendas do lote aparecem em “Todas as vendas”.", "ok"); onVoltar?.(); }}>Enviar</button>
        </div>
      </Widget>
    </>
  );
}

// ─────────── Pedido de venda (sales_order/index.blade.php — enable_sales_order) ───────────
const SO_STATUS = [{ id: "ordered", name: "Pedido" }, { id: "partial", name: "Parcial" }, { id: "completed", name: "Concluído" }];
const PEDIDOS = [
  { id: 1, data: "14/08/2026 09:22", num: "PV-2026-0071", cli: "Prefeitura de Jaú", tel: "(14) 3602-9000", loc: "Filial Centro", status: "ordered", envio: "ordered", restante: 24, quem: "Wagner Ramos", total: 23890 },
  { id: 2, data: "12/08/2026 15:10", num: "PV-2026-0070", cli: "Supermercado Bom Dia", tel: "(14) 3624-7788", loc: "Matriz", status: "partial", envio: "packed", restante: 6, quem: "Larissa Prado", total: 11450 },
  { id: 3, data: "08/08/2026 10:47", num: "PV-2026-0068", cli: "Rota Livre Transportes", tel: "(14) 99812-4410", loc: "Matriz", status: "completed", envio: "delivered", restante: 0, quem: "Marcos Vinícius", total: 4380 },
];

function VendaPedidos({ avisar, densa, setDensa, Grade, Toolbar, Filtros }) {
  const { Widget, Kebab, Modal, Fld, Sel } = UI();
  const { StatusBadge } = DS();
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");
  const [alvo, setAlvo] = useState(null);
  const rows = PEDIDOS.filter((p) => (!f.loc || p.loc === f.loc) && (!f.cli || p.cli === f.cli) && (!f.status || p.status === f.status) &&
    (!busca || (p.num + " " + p.cli).toLowerCase().includes(busca.toLowerCase())));
  const cols = [
    { key: "acao", label: "Ação", width: 96, resizable: false },
    { key: "data", label: "Data", width: 132, mono: true },
    { key: "num", label: "Nº do pedido", width: 150, mono: true, sortable: true },
    { key: "cli", label: "Cliente", width: 220, sortable: true },
    { key: "tel", label: "Contato", width: 140, mono: true },
    { key: "loc", label: "Local", width: 126 },
    { key: "status", label: "Status", width: 130 },
    { key: "envio", label: "Status de envio", width: 142 },
    { key: "restante", label: "Quantidade restante", width: 160, align: "right", mono: true },
    { key: "total", label: "Valor", width: 130, align: "right", mono: true },
    { key: "quem", label: "Adicionado por", width: 150 },
  ];
  const linhas = rows.map((p) => ({
    id: p.id, _p: p,
    acao: Kebab ? <Kebab acoes={[
      { l: "Ver pedido", ic: "search", on: () => avisar("Abrindo " + p.num + ".", "ok") },
      { l: "Editar status", ic: "pencil", on: () => setAlvo(p) },
      { l: "Gerar venda do pedido", ic: "cash", on: () => avisar("Venda gerada a partir de " + p.num + ".", "ok") },
      "-",
      { l: "Excluir", ic: "x", tone: "danger", on: () => avisar("Pedido " + p.num + " excluído.", "warn") },
    ]} /> : null,
    data: p.data, num: p.num, cli: p.cli, tel: p.tel, loc: p.loc,
    status: StatusBadge ? <StatusBadge kind="documento" value={(SO_STATUS.find((s) => s.id === p.status) || {}).name} /> : p.status,
    envio: (D().ENVIO || []).reduce((a, e) => e.id === p.envio ? e.name : a, p.envio),
    restante: p.restante, total: brl(p.total), quem: p.quem,
  }));
  if (!Widget) return null;
  return (
    <>
      <Filtros nota="Filtros do blade de pedidos: local, cliente, período, status, status de envio e usuário."
        f={f} setF={setF} campos={[
          { k: "loc", l: "Local do negócio", op: (D().LOCAIS || []).map((l) => l.name) },
          { k: "cli", l: "Cliente", op: D().CLIENTES || [] },
          { k: "status", l: "Status", op: SO_STATUS },
          { k: "envio", l: "Status de envio", op: D().ENVIO || [] },
        ]} />
      <Widget flush titulo={<><Ic name="orders" size={13} /> Pedidos de venda</>} nota={rows.length + " de " + PEDIDOS.length}>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar pedido ou cliente…" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm primary" onClick={() => avisar("Novo pedido de venda (sale_type=sales_order).", "ok")}><Ic name="plus" size={12} /> Adicionar pedido</button>
        </Toolbar>
        <Grade columns={cols} rows={linhas} densa={densa} altura={300} />
        <div className="pb-pag"><span className="pb-help">Item condicional do menu: só aparece com <span className="mono">enable_sales_order</span> ligado nas configurações do POS.</span></div>
      </Widget>
      {alvo && Modal &&
        <Modal titulo={"Editar status · " + alvo.num} onClose={() => setAlvo(null)} largura={520}
          acoes={<><button className="os-btn" onClick={() => setAlvo(null)}>Fechar</button><button className="os-btn primary" onClick={() => { avisar("Status de " + alvo.num + " atualizado.", "ok"); setAlvo(null); }}>Atualizar</button></>}>
          <Fld label="Status do pedido" req><Sel value={alvo.status} onChange={(v) => setAlvo({ ...alvo, status: v })} options={SO_STATUS} /></Fld>
        </Modal>}
    </>
  );
}

// ─────────── Adicionar venda / rascunho / cotação (sells/create?status=…) ───────────
// Não refaz o formulário: veste o create V3 vivo com a faixa de contexto do status,
// que é a única diferença entre os três itens do menu no legado.
const CTX = {
  final: { t: "Adicionar venda", d: "Venda direta: baixa estoque, entra no financeiro e pode emitir NF-e." },
  draft: { t: "Adicionar rascunho", d: "Rascunho não movimenta estoque nem financeiro — fica em “Lista de rascunhos” até virar venda." },
  quotation: { t: "Adicionar cotação", d: "Cotação é proposta ao cliente — vira venda quando ele aprova, em “Lista de compromissos”." },
};
function VendaNova({ status = "final", onVoltar, avisar }) {
  const c = CTX[status] || CTX.final;
  const { Alert } = DS();
  return (
    <div className="vt-nova">
      {status !== "final" && Alert && <Alert tone="info" title={c.t}>{c.d}</Alert>}
      {window.VendaV3Create
        ? <window.VendaV3Create onVoltar={onVoltar} />
        : (window.VendasCreatePage ? <window.VendasCreatePage onDone={onVoltar} /> : <p className="pb-help">O formulário de venda não carregou.</p>)}
      {status !== "final" &&
        <div className="vt-nova-f">
          <button className="os-btn" onClick={onVoltar}>Voltar</button>
          <button className="os-btn primary" onClick={() => { avisar(status === "draft" ? "Rascunho salvo." : "Cotação salva.", "ok"); onVoltar?.(); }}>
            {status === "draft" ? "Salvar rascunho" : "Salvar cotação"}
          </button>
        </div>}
    </div>
  );
}

Object.assign(window, { VendaDetalhe, VendaImportPreview, VendaPedidos, VendaNova, VENDA_LINHAS: linhasDe, VENDA_DOC_FISCAL: docFiscal });
})();
