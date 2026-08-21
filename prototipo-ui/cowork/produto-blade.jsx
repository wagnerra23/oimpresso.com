// produto-blade.jsx — Módulo PRODUTO importado dos blades `resources/views/product/*`
// (index, create/edit, view-modal, stock_history, add-selling-prices, bulk-edit e partials).
// Nada de tela nova: cada bloco aqui é a tradução 1:1 de um blade pro Cockpit V2.
//   index.blade.php ................ tela "lista" (filtros + abas + tabela + rodapé de seleção)
//   partials/product_list .......... colunas e ações em massa da tabela
//   view-modal + *_product_details . drawer de detalhe (PT-02, nunca modal full-screen)
//   partials/quick_product_opening_stock .. modal "Estoque inicial"
//   partials/edit_product_location_modal .. modal "Localização do produto"
//   report.partials.stock_report_table .... aba "Relatório de estoque"
// As telas de formulário vivem em produto-blade-forms.jsx (window.ProdutoBladeForms).
// Expõe window.ProdutoBladePage + window.PBD (dados/mock compartilhado).
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const MP = () => window.ModuloPadrao || {};
// Ícones: o set do protótipo (icons.jsx → window.I), que aceita `size`.
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
// DS v6 compilado — usamos os primitivos do bundle em vez de reescrever (FilterChip, Pagination, EmptyState, Tooltip, Skeleton, Alert).
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// ─────────── Domínio (selects do blade: units, categories, brands, taxes, locations) ───────────
const UNITS = [
  { id: 1, name: "Unidade", short: "Un" }, { id: 2, name: "Metro quadrado", short: "m²" },
  { id: 3, name: "Metro linear", short: "m" }, { id: 4, name: "Quilograma", short: "kg" },
  { id: 5, name: "Caixa", short: "cx" }, { id: 6, name: "Peça", short: "pç" },
];
const CATEGORIES = ["Comunicação visual", "Impressos", "Adesivos", "Acabamento", "Insumos", "Serviços"];
const SUBCATEGORIES = {
  "Comunicação visual": ["Lonas", "Fachadas", "Placas"], "Impressos": ["Offset", "Digital"],
  "Adesivos": ["Vinil", "Recorte"], "Acabamento": ["Ilhós", "Bastão"], "Insumos": ["Tintas", "Mídias"], "Serviços": ["Instalação", "Arte"],
};
const BRANDS = ["Sem marca", "Vinilcor", "3M", "Avery", "Coral", "Suprema"];
const TAXES = [{ id: 0, name: "Nenhum", rate: 0 }, { id: 1, name: "ICMS 18%", rate: 18 }, { id: 2, name: "ICMS 12%", rate: 12 }, { id: 3, name: "ISS 5%", rate: 5 }];
const LOCATIONS = [{ id: 1, name: "Matriz" }, { id: 2, name: "Filial Centro" }];
const BARCODE_TYPES = ["C128", "C39", "EAN-13", "EAN-8", "UPC-A", "UPC-E"];
const PRICE_GROUPS = [{ id: 1, name: "Varejo" }, { id: 2, name: "Atacado" }, { id: 3, name: "Convênio" }, { id: 4, name: "Funcionário" }];
const WARRANTIES = ["Sem garantia", "3 meses", "6 meses", "12 meses"];
const TYPE_LABEL = { single: "Único", variable: "Variável", combo: "Composição" };
// Fiscal (Model Product + módulo Fiscal): NCM/CEST/CFOP e origem da mercadoria.
const ORIGENS = [
  { id: "0", name: "0 — Nacional" }, { id: "1", name: "1 — Importação direta" },
  { id: "2", name: "2 — Adquirida no mercado interno" }, { id: "3", name: "3 — Nacional, importação > 40%" },
  { id: "6", name: "6 — Importação direta, sem similar nacional" },
];
const CFOPS = [
  { id: "5101", name: "5101 — Venda de produção do estabelecimento" },
  { id: "5102", name: "5102 — Venda de mercadoria adquirida de terceiros" },
  { id: "5405", name: "5405 — Venda com ST, substituído" },
  { id: "5933", name: "5933 — Prestação de serviço tributado por ISS" },
];

const v = (name, sku, dpp, profit, dsp) => ({ name, sku, dpp, profit, dsp });
// enable_stock, alert_quantity, not_for_selling, weight, expiry, racks — todos campos reais do Model Product.
const PRODUCTS = [
  { id: 1, name: "Lona 380g brilho impressa", sku: "PRD-0001", type: "variable", cat: "Comunicação visual", sub: "Lonas", brand: "Vinilcor", unit: "m²", tax: "ICMS 18%", taxType: "exclusive", barcode: "C128", stockOn: true, alert: 50, notForSelling: false, active: true, weight: "0,45", expiry: "", expiryType: "", locs: [1, 2], desc: "Lona vinílica 380g com impressão digital solvente, acabamento em bastão e ilhós.", prep: 120, cf: { 1: "Fornecedor A" }, fiscal: { ncm: "3921.90.90", cest: "", cfop: "5101", origem: "0" },
    racks: { 1: { rack: "A2", row: "3", pos: "01" }, 2: { rack: "B1", row: "1", pos: "04" } },
    variations: [v("Acabamento - Bastão + ilhós", "PRD-0001-1", 7.5, 633, 55), v("Acabamento - Solda simples", "PRD-0001-2", 6.9, 622, 49.8), v("Acabamento - Sem acabamento", "PRD-0001-3", 6.2, 616, 44.4)], stock: 1240 },
  { id: 2, name: "Vinil adesivo brilho", sku: "PRD-0002", type: "single", cat: "Adesivos", sub: "Vinil", brand: "3M", unit: "m²", tax: "ICMS 18%", taxType: "exclusive", barcode: "C128", stockOn: true, alert: 80, notForSelling: false, active: true, weight: "0,18", locs: [1], desc: "Vinil adesivo monomérico brilho, impressão + laminação opcional.", fiscal: { ncm: "3919.10.00", cest: "", cfop: "5102", origem: "2" }, fiscal: { ncm: "3921.90.90", cest: "", cfop: "5101", origem: "0" }, racks: { 1: { rack: "C1", row: "2", pos: "07" } },
    variations: [v("Padrão", "PRD-0002", 5.8, 624, 42)], stock: 62 },
  { id: 3, name: "Placa ACM 3mm recortada", sku: "PRD-0003", type: "single", cat: "Comunicação visual", sub: "Placas", brand: "Sem marca", unit: "m²", tax: "ICMS 18%", taxType: "exclusive", barcode: "C128", stockOn: true, alert: 10, notForSelling: false, active: true, weight: "4,20", locs: [1, 2], desc: "Chapa ACM 3mm com recorte em router CNC.", fiscal: { ncm: "7606.12.10", cest: "", cfop: "5102", origem: "2" }, racks: { 1: { rack: "D4", row: "1", pos: "02" } },
    variations: [v("Padrão", "PRD-0003", 96, 68, 161.3)], stock: 34 },
  { id: 4, name: "Cartão de visita 4x4 — 1.000 un", sku: "PRD-0004", type: "single", cat: "Impressos", sub: "Offset", brand: "Suprema", unit: "cx", tax: "ICMS 12%", taxType: "inclusive", barcode: "EAN-13", stockOn: false, alert: null, notForSelling: false, active: true, weight: "1,10", locs: [1], desc: "Couché 300g, 4x4 cores, verniz total frente.", fiscal: { ncm: "4911.10.90", cest: "", cfop: "5101", origem: "0" }, racks: {},
    variations: [v("Padrão", "PRD-0004", 78, 92, 149.8)], stock: null },
  { id: 5, name: "Kit fachada completa 3x1m", sku: "PRD-0005", type: "combo", cat: "Comunicação visual", sub: "Fachadas", brand: "Sem marca", unit: "Un", tax: "ICMS 18%", taxType: "exclusive", barcode: "C128", stockOn: false, alert: null, notForSelling: false, active: true, weight: "12,00", locs: [1], desc: "Composição: lona + estrutura + instalação. Preço fechado de balcão.", fiscal: { ncm: "3921.90.90", cest: "", cfop: "5101", origem: "0" }, racks: {},
    variations: [v("Padrão", "PRD-0005", 486, 60, 777.6)],
    combo: [{ name: "Lona 380g brilho impressa", sku: "PRD-0001-1", qty: 3, unit: "m²", dpp: 7.5 }, { name: "Perfil de alumínio 30x30", sku: "PRD-0008", qty: 8, unit: "m", dpp: 22 }, { name: "Instalação em fachada", sku: "PRD-0011", qty: 1, unit: "Un", dpp: 220 }], stock: null },
  { id: 6, name: "Tinta solvente CMYK 5L", sku: "PRD-0006", type: "variable", cat: "Insumos", sub: "Tintas", brand: "Coral", unit: "Un", tax: "ICMS 18%", taxType: "exclusive", barcode: "C128", stockOn: true, alert: 4, notForSelling: true, active: true, weight: "5,30", expiry: 12, expiryType: "months", locs: [1], desc: "Tinta solvente para plotter — insumo, não vai pro balcão.", fiscal: { ncm: "3215.11.00", cest: "1701800", cfop: "5405", origem: "1" }, racks: { 1: { rack: "E1", row: "4", pos: "03" } },
    variations: [v("Cor - Ciano", "PRD-0006-1", 540, 44, 777.6), v("Cor - Magenta", "PRD-0006-2", 540, 44, 777.6), v("Cor - Amarelo", "PRD-0006-3", 540, 44, 777.6), v("Cor - Preto", "PRD-0006-4", 496, 44, 714.2)], stock: 11 },
  { id: 7, name: "Ilhós metálico nº 12 (mil)", sku: "PRD-0007", type: "single", cat: "Acabamento", sub: "Ilhós", brand: "Sem marca", unit: "cx", tax: "Nenhum", taxType: "exclusive", barcode: "C128", stockOn: true, alert: 5, notForSelling: true, active: true, weight: "2,40", locs: [1, 2], desc: "", fiscal: { ncm: "8308.10.00", cest: "", cfop: "5102", origem: "2" }, racks: { 1: { rack: "A1", row: "1", pos: "09" } },
    variations: [v("Padrão", "PRD-0007", 118, 90, 224.2)], stock: 3 },
  { id: 8, name: "Perfil de alumínio 30x30", sku: "PRD-0008", type: "single", cat: "Insumos", sub: "Mídias", brand: "Sem marca", unit: "m", tax: "ICMS 18%", taxType: "exclusive", barcode: "C128", stockOn: true, alert: 30, notForSelling: true, active: true, weight: "0,90", locs: [1], desc: "", fiscal: { ncm: "7604.21.00", cest: "", cfop: "5102", origem: "2" }, racks: {},
    variations: [v("Padrão", "PRD-0008", 22, 70, 37.4)], stock: 186 },
  { id: 9, name: "Adesivo de recorte — vinil colorido", sku: "PRD-0009", type: "variable", cat: "Adesivos", sub: "Recorte", brand: "Avery", unit: "m²", tax: "ICMS 18%", taxType: "exclusive", barcode: "C128", stockOn: true, alert: 20, notForSelling: false, active: true, weight: "0,16", locs: [1, 2], desc: "Vinil de recorte em 4 cores de estoque.", fiscal: { ncm: "3919.90.20", cest: "", cfop: "5102", origem: "2" }, racks: { 2: { rack: "C3", row: "2", pos: "05" } },
    variations: [v("Cor - Branco", "PRD-0009-1", 9.4, 320, 39.5), v("Cor - Preto", "PRD-0009-2", 9.4, 320, 39.5), v("Cor - Vermelho", "PRD-0009-3", 11.2, 300, 44.8), v("Cor - Azul", "PRD-0009-4", 11.2, 300, 44.8)], stock: 418 },
  { id: 10, name: "Banner 440g com bastão", sku: "PRD-0010", type: "single", cat: "Comunicação visual", sub: "Lonas", brand: "Vinilcor", unit: "m²", tax: "ICMS 18%", taxType: "exclusive", barcode: "C128", stockOn: true, alert: 25, notForSelling: false, active: false, weight: "0,52", locs: [1], desc: "Substituído pela lona 380g — mantido inativo pra histórico.", fiscal: { ncm: "3921.90.90", cest: "", cfop: "5101", origem: "0" }, racks: {},
    variations: [v("Padrão", "PRD-0010", 11.2, 570, 75)], stock: 0 },
  { id: 11, name: "Instalação em fachada (hora técnica)", sku: "PRD-0011", type: "single", cat: "Serviços", sub: "Instalação", brand: "Sem marca", unit: "Un", tax: "ISS 5%", taxType: "inclusive", barcode: "C128", stockOn: false, alert: null, notForSelling: false, active: true, weight: "", locs: [1, 2], desc: "Hora técnica de instalação em altura, com equipe de dois.", fiscal: { ncm: "", cest: "", cfop: "5933", origem: "0" }, racks: {},
    variations: [v("Padrão", "PRD-0011", 220, 45, 319)], stock: null },
  { id: 12, name: "Criação de arte final", sku: "PRD-0012", type: "single", cat: "Serviços", sub: "Arte", brand: "Sem marca", unit: "Un", tax: "ISS 5%", taxType: "inclusive", barcode: "C128", stockOn: false, alert: null, notForSelling: false, active: true, weight: "", locs: [1], desc: "", fiscal: { ncm: "", cest: "", cfop: "5933", origem: "0" }, racks: {},
    variations: [v("Padrão", "PRD-0012", 90, 66, 149.4)], stock: null },
];

// ─────────── Helpers ───────────
const fmtBRL = (n) => n == null ? "—" : "R$ " + Number(n).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtQty = (n) => n == null ? "—" : Number(n).toLocaleString("pt-BR", { maximumFractionDigits: 2 });
const taxRate = (name) => (TAXES.find((t) => t.name === name) || TAXES[0]).rate;
const incTax = (val, name) => val * (1 + taxRate(name) / 100);
const priceSpan = (p, key) => {
  const arr = p.variations.map((x) => x[key]);
  const min = Math.min(...arr), max = Math.max(...arr);
  return min === max ? fmtBRL(min) : fmtBRL(min) + " – " + fmtBRL(max);
};
// Histórico de estoque (stock_history_details.blade.php) — determinístico a partir do id.
const stockHistory = (p) => {
  const kinds = [["Compra", 1], ["Venda", -1], ["Estoque inicial", 1], ["Ajuste de estoque", -1], ["Transferência (entrada)", 1], ["Devolução de venda", 1], ["Venda", -1]];
  let saldo = 0;
  return kinds.map((k, i) => {
    const q = k[1] * ((p.id * 7 + i * 13) % 40 + 5);
    saldo += q;
    const d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() - i * 4);
    const dia = String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0") + "/" + d.getFullYear();
    return { type: k[0], change: q, stock: saldo, date: `${dia} ${String(8 + i).padStart(2, "0")}:${(i * 7 + 12) % 60 < 10 ? "0" : ""}${(i * 7 + 12) % 60}`, ref: k[0] === "Compra" ? "COMP-28" + (40 + i) : k[0] === "Venda" ? "VD-9" + (100 + i * 3) : "—", who: k[0] === "Compra" ? "Lonas & Vinis Ltda" : k[0] === "Venda" ? "Rota Livre Transportes" : "—" };
  }).reverse();
};

const PBD = { ORIGENS, CFOPS, UNITS, CATEGORIES, SUBCATEGORIES, BRANDS, TAXES, LOCATIONS, BARCODE_TYPES, PRICE_GROUPS, WARRANTIES, TYPE_LABEL, PRODUCTS, fmtBRL, fmtQty, taxRate, incTax, priceSpan, stockHistory };
window.PBD = PBD;

// Ponte pro resto do ERP — navegar pro módulo vizinho levando o produto no contexto.
// window.__selectRoute é o roteador do shell (app.jsx); o contexto fica em window.__PBCtx
// pra a tela de destino ler ("veio do Produto X").
const irPara = (rota, ctx) => {
  window.__PBCtx = { origem: "produto", em: Date.now(), ...ctx };
  if (window.__selectRoute) window.__selectRoute(rota);
};
window.PBIr = irPara;

// ─────────── Peças ───────────
function Widget({ titulo, nota, flush, contrato, children }) {
  return (
    <section className="pb-widget" data-contract={contrato}>
      {titulo && <h3>{titulo}{nota && <span className="n">{nota}</span>}</h3>}
      <div className={"pb-widget-b" + (flush ? " flush" : "")}>{children}</div>
    </section>
  );
}
function Fld({ label, req, dica, span, erro, children }) {
  const { Tooltip } = DS();
  const marca = <span style={{ color: "var(--text-mute)", cursor: "help" }} tabIndex={0}>ⓘ</span>;
  return (
    <label className={"pb-fld" + (span ? " span" + span : "") + (erro ? " erro" : "")}>
      <span>{label}{req && <b className="req">*</b>}{dica && (Tooltip ? <Tooltip content={dica}>{marca}</Tooltip> : <span title={dica}>ⓘ</span>)}</span>
      {children}
      {erro && <em className="pb-erro">{erro}</em>}
    </label>
  );
}
function Sel({ value, onChange, options, vazio }) {
  return (
    <select value={value} onChange={(e) => onChange?.(e.target.value)}>
      {vazio && <option value="">{vazio}</option>}
      {options.map((o) => typeof o === "string" ? <option key={o} value={o}>{o}</option> : <option key={o.id} value={o.id}>{o.name}</option>)}
    </select>
  );
}
// Ações da linha — o painel vai por PORTAL pro body com position:fixed.
// Motivo: dentro da grade do DS o `td` tem overflow:hidden, e qualquer popover
// ancorado na célula (inclusive o DropdownMenu do DS, que não usa portal) é
// recortado a uns 2px. Fecha em esc, clique-fora, scroll e resize.
function Kebab({ acoes }) {
  const [pos, setPos] = useState(null);
  const botao = useRef(null);
  const painel = useRef(null);

  const abrir = () => {
    if (pos) return setPos(null);
    const r = botao.current.getBoundingClientRect();
    // Referência = o corpo rolável da tela (.pb-body), não a janela: dentro do shell
    // a janela não descreve a área visível, e o painel acabava fora dela.
    const cx = botao.current.closest(".pb-body");
    const q = cx ? cx.getBoundingClientRect() : { top: 0, bottom: window.innerHeight, right: window.innerWidth };
    const ideal = acoes.length * 32 + 16;
    const abaixo = q.bottom - r.bottom - 10;
    const acima = r.top - q.top - 10;
    const paraCima = abaixo < 200 && acima > abaixo;
    const alt = Math.max(140, Math.min(ideal, 340, paraCima ? acima : abaixo));
    setPos({
      left: Math.max(8, Math.min(r.left, q.right - 262)),
      top: paraCima ? r.top - alt - 4 : r.bottom + 4,
      alt,
    });
  };

  useEffect(() => {
    if (!pos) return;
    const fora = (e) => { if (!painel.current?.contains(e.target) && !botao.current?.contains(e.target)) setPos(null); };
    const fecha = () => setPos(null);
    const tecla = (e) => { if (e.key === "Escape") { e.stopPropagation(); setPos(null); } };
    document.addEventListener("mousedown", fora);
    document.addEventListener("keydown", tecla, true);
    window.addEventListener("resize", fecha);
    window.addEventListener("scroll", fecha, true);
    return () => {
      document.removeEventListener("mousedown", fora);
      document.removeEventListener("keydown", tecla, true);
      window.removeEventListener("resize", fecha);
      window.removeEventListener("scroll", fecha, true);
    };
  }, [pos]);

  return (
    <div className="pb-kebab">
      <button ref={botao} className="os-btn sm" onClick={abrir} aria-haspopup="menu" aria-expanded={!!pos} aria-label="Ações do produto">
        Ações <Ic name="chev" size={12} />
      </button>
      {pos && ReactDOM.createPortal(
        <div ref={painel} className="pb-menu pb-menu-fixo" role="menu"
          style={{ left: pos.left, top: pos.top, maxHeight: pos.alt }}>
          {acoes.map((a, i) => a === "-" ? <hr key={i} /> :
            <button key={i} className={a.tone || ""} role="menuitem"
              onClick={() => { setPos(null); a.on?.(); }}>{a.ic && <Ic name={a.ic} size={13} />}{a.l}</button>)}
        </div>, document.body)}
    </div>
  );
}
// Modal = primitivo do DS (PT-04: foco preso, esc, scrim), com a assinatura local por cima.
function Modal({ titulo, onClose, children, acoes, largura = 720 }) {
  const { Modal: DsModal } = DS();
  if (DsModal) {
    return (
      <DsModal open onClose={onClose} title={titulo} width={largura}
        footer={<div className="pb-modal-f-in">{acoes}</div>}>
        <div className="pb-modal-b">{children}</div>
      </DsModal>
    );
  }
  return (
    <div className="pb-modal-back" onClick={onClose}>
      <div className="pb-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-label={titulo}>
        <div className="pb-modal-h"><h3>{titulo}</h3><button className="icon-btn" onClick={onClose}>✕</button></div>
        <div className="pb-modal-b">{children}</div>
        <div className="pb-modal-f">{acoes}</div>
      </div>
    </div>
  );
}

// ─────────── Filtros (components.filters do index.blade.php) ───────────
function Filtros({ f, setF }) {
  const [aberto, setAberto] = useState(true);
  const set = (k) => (val) => setF({ ...f, [k]: val });
  return (
    <Widget contrato="produto-filtros" titulo={<><Ic name="search" size={13} /> Filtros</>} nota={aberto ? null : "recolhidos"}>
      <div className="pb-filters-h" style={{ marginBottom: aberto ? 12 : 0 }}>
        <span className="pb-help">Mesmos filtros do índice de produtos: tipo, categoria, unidade, imposto, marca, local e situação.</span>
        <button className="os-btn sm ghost" onClick={() => setAberto((a) => !a)}>{aberto ? "Recolher" : "Expandir"}</button>
      </div>
      {aberto &&
        <div className="pb-grid c4">
          <Fld label="Tipo de produto"><Sel value={f.type} onChange={set("type")} options={[{ id: "single", name: "Único" }, { id: "variable", name: "Variável" }, { id: "combo", name: "Composição" }]} vazio="Todos" /></Fld>
          <Fld label="Categoria"><Sel value={f.cat} onChange={set("cat")} options={CATEGORIES} vazio="Todas" /></Fld>
          <Fld label="Unidade"><Sel value={f.unit} onChange={set("unit")} options={UNITS.map((u) => u.short)} vazio="Todas" /></Fld>
          <Fld label="Imposto"><Sel value={f.tax} onChange={set("tax")} options={TAXES.map((t) => t.name)} vazio="Todos" /></Fld>
          <Fld label="Marca"><Sel value={f.brand} onChange={set("brand")} options={BRANDS} vazio="Todas" /></Fld>
          <Fld label="Local do negócio"><Sel value={f.loc} onChange={set("loc")} options={LOCATIONS} vazio="Todos" /></Fld>
          <Fld label="Situação"><Sel value={f.active} onChange={set("active")} options={[{ id: "active", name: "Ativo" }, { id: "inactive", name: "Inativo" }]} vazio="Todos" /></Fld>
          <div className="pb-fld" style={{ justifyContent: "flex-end" }}>
            <label className="pb-chk"><input type="checkbox" checked={f.nfs} onChange={(e) => setF({ ...f, nfs: e.target.checked })} /><b>Não para venda</b></label>
          </div>
        </div>}
    </Widget>
  );
}

// ─────────── Tabela de produtos (partials/product_list.blade.php) ───────────
// Colunas configuráveis — o `columnDefs`/`visible` do DataTable virou menu de colunas.
const PB_COLS = [
  { id: "imagem", l: "Imagem" }, { id: "locais", l: "Locais" }, { id: "compra", l: "Preço de compra un." },
  { id: "venda", l: "Preço de venda" }, { id: "estoque", l: "Estoque atual" }, { id: "tipo", l: "Tipo" },
  { id: "categoria", l: "Categoria" }, { id: "marca", l: "Marca" }, { id: "imposto", l: "Imposto" },
  { id: "sku", l: "SKU" }, { id: "serie", l: "IMEI / série" }, { id: "cf1", l: "Campo personalizado 1" },
];
const PB_COLS_PADRAO = { imagem: true, locais: true, compra: true, venda: true, estoque: true, tipo: true, categoria: true, marca: true, imposto: true, sku: true, serie: false, cf1: false };

// A lista roda no DataTablePro do DS: header fixo, resize de coluna por arrasto,
// ordenação e seleção internas, densidade. As colunas do blade viram `columns`.
function ListaProdutos({ rows, sel, setSel, onAcao, densa, cols, selSeq }) {
  const { DataTablePro, EmptyState } = DS();
  // Altura da grade: cabe o conteúdo em lista curta (sem rolagem dupla) e limita
  // a ~72% do corpo rolável em lista longa, onde a rolagem interna com header fixo compensa.
  const caixa = useRef(null);
  const [altura, setAltura] = useState(440);
  useEffect(() => {
    const medir = () => {
      const corpo = caixa.current?.closest(".pb-body");
      const disp = corpo ? corpo.getBoundingClientRect().height : window.innerHeight;
      const linha = densa ? 33 : 48;
      const conteudo = rows.length * linha + 42;
      setAltura(Math.max(260, Math.min(conteudo, Math.round(disp * 0.72))));
    };
    medir();
    window.addEventListener("resize", medir);
    return () => window.removeEventListener("resize", medir);
  }, [rows.length, densa]);
  const acoesDe = (p) => [
    { l: "Ver", ic: "search", on: () => onAcao("ver", p) },
    { l: "Editar", ic: "pencil", on: () => onAcao("editar", p) },
    { l: "Adicionar ou editar estoque inicial", ic: "archive", on: () => onAcao("inicial", p) },
    { l: "Histórico de estoque do produto", ic: "clock", on: () => onAcao("historico", p) },
    "-",
    { l: "Duplicar produto", ic: "copy", on: () => onAcao("duplicar", p) },
    { l: "Gerar código de barras", ic: "print", on: () => onAcao("barras", p) },
    { l: "Adicionar ou editar preços por grupo", ic: "cash", on: () => onAcao("precos", p) },
    "-",
    { l: "Comprar este produto", ic: "archive", on: () => onAcao("comprar", p) },
    { l: "Transferir entre locais", ic: "layers", on: () => onAcao("transferir", p) },
    { l: "Usar em uma OS", ic: "orders", on: () => onAcao("os", p) },
    { l: "Dados fiscais (NCM/CFOP)", ic: "quote", on: () => onAcao("fiscal", p) },
    "-",
    { l: p.active ? "Desativar" : "Ativar", ic: "cog", on: () => onAcao("toggle", p) },
    { l: "Excluir", ic: "x", tone: "danger", on: () => onAcao("excluir", p) },
  ];

  if (!DataTablePro) return <div className="pb-widget-b"><p className="pb-help">A grade do DS não carregou.</p></div>;

  const TODAS = [
    { key: "acao", label: "Ação", width: 96, resizable: false },
    { key: "imagem", label: "Imagem", width: 74, resizable: false, col: "imagem" },
    { key: "prod", label: "Produto", sortable: true, width: 300, sortValue: (r) => r._p.name.toLowerCase() },
    { key: "locais", label: "Locais", col: "locais", width: 150 },
    { key: "compra", label: "Preço de compra un.", align: "right", sortable: true, col: "compra", width: 150, sortValue: (r) => r._p.variations[0].dpp },
    { key: "venda", label: "Preço de venda", align: "right", sortable: true, col: "venda", width: 150, sortValue: (r) => r._p.variations[0].dsp },
    { key: "estoque", label: "Estoque atual", align: "right", sortable: true, col: "estoque", width: 130, sortValue: (r) => (r._p.stockOn ? r._p.stock : -1) },
    { key: "tipo", label: "Tipo", sortable: true, col: "tipo", width: 110, sortValue: (r) => TYPE_LABEL[r._p.type] },
    { key: "categoria", label: "Categoria", sortable: true, col: "categoria", width: 160, sortValue: (r) => r._p.cat },
    { key: "marca", label: "Marca", sortable: true, col: "marca", width: 120, sortValue: (r) => r._p.brand },
    { key: "imposto", label: "Imposto", col: "imposto", width: 110 },
    { key: "sku", label: "SKU", mono: true, sortable: true, col: "sku", width: 120, sortValue: (r) => r._p.sku },
    { key: "serie", label: "IMEI / série", col: "serie", width: 110 },
    { key: "cf1", label: "Campo personalizado 1", col: "cf1", width: 170 },
  ];
  const columns = TODAS.filter((c) => !c.col || cols[c.col]);

  const linhas = rows.map((p) => ({
    id: p.id,
    state: !p.active ? "archived" : (p.stockOn && p.alert != null && p.stock <= p.alert ? "urgent" : undefined),
    _p: p,
    cells: {
      acao: <span onClick={(e) => e.stopPropagation()}><Kebab acoes={acoesDe(p)} /></span>,
      imagem: <div className="pb-thumb">IMG</div>,
      prod: {
        primary: p.name,
        sub: [p.stockOn ? "Estoque gerenciado" : "Sem controle de estoque",
          p.notForSelling ? "não para venda" : null,
          !p.active ? "inativo" : null,
          p.type === "variable" ? p.variations.length + " variações" : null].filter(Boolean).join(" · "),
      },
      locais: p.locs.map((id) => LOCATIONS.find((l) => l.id === id).name).join(", ") || "—",
      compra: priceSpan(p, "dpp"),
      venda: priceSpan(p, "dsp"),
      estoque: p.stockOn
        ? <span className={"pb-stock" + (p.alert != null && p.stock <= p.alert ? " low" : "")}>{fmtQty(p.stock)} {p.unit}</span>
        : <span className="pb-stock na">—</span>,
      tipo: <span className={"pb-pill " + p.type}>{TYPE_LABEL[p.type]}</span>,
      categoria: { primary: p.cat, sub: p.sub },
      marca: p.brand,
      imposto: p.tax,
      sku: p.sku,
      serie: p.srNo ? "Sim" : "—",
      cf1: (p.cf || {})[1] || "—",
    },
  }));

  if (rows.length === 0) {
    return (
      <div style={{ padding: 16 }} data-contract="produto-tabela">
        {EmptyState
          ? <EmptyState variant="no-results" icon={<Ic name="search" size={16} />}
              title="Nenhum produto com esses filtros"
              description="Os filtros ativos não deixaram nada de pé. Remova um filtro pelos chips acima — ou cadastre o produto que está faltando." />
          : <div className="pb-vazio"><b>Nenhum produto com esses filtros</b></div>}
      </div>
    );
  }
  return (
    <div data-contract="produto-tabela" className="pb-grid-pro" ref={caixa}>
      <DataTablePro key={"pro-" + selSeq} columns={columns} rows={linhas} height={altura} selectable
        density={densa ? "compact" : "comfortable"}
        defaultSort={{ key: "prod", dir: "asc" }}
        onRowClick={(r) => onAcao("ver", r._p)}
        onSelectionChange={(ids) => setSel(ids.map(Number))} />
    </div>
  );
}

// ─────────── Aba "Relatório de estoque" (report.partials.stock_report_table) ───────────
function RelatorioEstoque({ rows }) {
  const { Pagination } = DS();
  const [page, setPage] = useState(1);
  const [porPagina, setPorPagina] = useState(25);
  const lin = useMemo(() => {
    const out = [];
    rows.filter((p) => p.stockOn).forEach((p) => {
      p.variations.forEach((va, i) => {
        const locId = p.locs[i % p.locs.length] || p.locs[0];
        const qtd = Math.round((p.stock || 0) / p.variations.length);
        out.push({ key: p.id + "-" + i, sku: va.sku, prod: p.name, varName: va.name, cat: p.cat, loc: LOCATIONS.find((l) => l.id === locId).name, unit: p.unit,
          preco: incTax(va.dsp, p.tax), qtd, custo: qtd * va.dpp, venda: qtd * va.dsp, vendido: (p.id * 11 + i * 7) % 90, transf: (p.id * 3 + i) % 12, ajust: (p.id + i) % 6 });
      });
    });
    return out;
  }, [rows]);
  const tot = lin.reduce((a, r) => ({ qtd: a.qtd + r.qtd, custo: a.custo + r.custo, venda: a.venda + r.venda, vendido: a.vendido + r.vendido, transf: a.transf + r.transf, ajust: a.ajust + r.ajust }), { qtd: 0, custo: 0, venda: 0, vendido: 0, transf: 0, ajust: 0 });
  const pageCount = Math.max(1, Math.ceil(lin.length / porPagina));
  const pagina = Math.min(page, pageCount);
  const visiveis = lin.slice((pagina - 1) * porPagina, pagina * porPagina);
  return (
    <>
    <div className="pb-tblwrap">
      <table className="pb-tbl">
        <thead>
          <tr>
            <th>SKU</th><th>Produto</th><th>Variação</th><th>Categoria</th><th>Local</th>
            <th className="r">Preço unitário</th><th className="r">Estoque atual</th><th className="r">Valor pelo custo</th>
            <th className="r">Valor pela venda</th><th className="r">Lucro potencial</th><th className="r">Total vendido</th><th className="r">Total transferido</th><th className="r">Total ajustado</th>
          </tr>
        </thead>
        <tbody>
          {visiveis.map((r) => (
            <tr key={r.key}>
              <td className="m">{r.sku}</td><td><b>{r.prod}</b></td><td>{r.varName}</td><td>{r.cat}</td><td>{r.loc}</td>
              <td className="r">{fmtBRL(r.preco)}</td><td className="r">{fmtQty(r.qtd)} {r.unit}</td><td className="r">{fmtBRL(r.custo)}</td>
              <td className="r">{fmtBRL(r.venda)}</td><td className="r" style={{ color: "var(--pos)" }}>{fmtBRL(r.venda - r.custo)}</td>
              <td className="r">{fmtQty(r.vendido)}</td><td className="r">{fmtQty(r.transf)}</td><td className="r">{fmtQty(r.ajust)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr>
            <td colSpan={6}><b>Totais (todas as {lin.length} linhas)</b></td>
            <td className="r"><b>{fmtQty(tot.qtd)}</b></td><td className="r"><b>{fmtBRL(tot.custo)}</b></td><td className="r"><b>{fmtBRL(tot.venda)}</b></td>
            <td className="r"><b>{fmtBRL(tot.venda - tot.custo)}</b></td><td className="r"><b>{fmtQty(tot.vendido)}</b></td><td className="r"><b>{fmtQty(tot.transf)}</b></td><td className="r"><b>{fmtQty(tot.ajust)}</b></td>
          </tr>
        </tfoot>
      </table>
    </div>
    {Pagination &&
      <div className="pb-pag">
        <div className="sp" />
        <Pagination page={pagina} pageCount={pageCount} onChange={setPage} total={lin.length} pageSize={porPagina}
          onPageSize={(n) => { setPorPagina(n); setPage(1); }} pageSizeOptions={[10, 25, 50, 100]} />
      </div>}
    </>
  );
}

// ─────────── Drawer de detalhe (view-modal.blade.php + *_product_details) ───────────
function DetalheDrawer({ p, onClose, onIr }) {
  const [aba, setAba] = useState("dados");
  const abas = [{ id: "dados", l: "Dados" }, { id: p.type === "combo" ? "composicao" : "variacoes", l: p.type === "combo" ? "Composição" : "Variações", n: p.type === "combo" ? p.combo.length : p.variations.length }, { id: "estoque", l: "Estoque" }, { id: "fiscal", l: "Fiscal" }, { id: "prateleira", l: "Prateleira" }];
  const fs = p.fiscal || {};
  const { Drawer } = DS();
  const dt = (label, val) => <div className="pb-dt"><span>{label}</span><b>{val || "—"}</b></div>;
  const Casca = Drawer
    ? ({ children }) => (
        <Drawer open onClose={onClose} width={820} title={p.name}
          subtitle={p.cat + " · " + p.sub + " · " + p.brand + " · unidade " + p.unit}
          badge={<span style={{ display: "flex", alignItems: "center", gap: 6 }}>
            <span className="os-drawer-id">{p.sku}</span>
            <span className={"pb-pill " + p.type}>{TYPE_LABEL[p.type]}</span>
            {!p.active && <span className="pb-pill off">Inativo</span>}
          </span>}
          footer={<>
            <button className="os-btn primary" onClick={() => onIr("editar", p)}><Ic name="pencil" size={13} /> Editar produto</button>
            <button className="os-btn" onClick={() => onIr("comprar", p)}><Ic name="archive" size={13} /> Comprar</button>
            {p.type === "combo" && <button className="os-btn" onClick={() => onIr("producao", p)}><Ic name="layers" size={13} /> Gerar OP</button>}
            <button className="os-btn" onClick={() => onIr("precos", p)}>Preços por grupo</button>
            <button className="os-btn" onClick={() => window.print()}><Ic name="print" size={13} /> Imprimir</button>
            <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          </>}>
          {children}
        </Drawer>)
    : ({ children }) => <div className="os-drawer-back" onClick={onClose}><aside className="os-drawer wide" onClick={(e) => e.stopPropagation()}>{children}</aside></div>;
  return (
    <Casca>
      <div className="pb-drawer-in">
        <nav className="cli-moduletopnav" aria-label="Detalhe do produto" style={{ padding: "0 12px" }}>
          {abas.map((a) => <button key={a.id} className={"cli-moduletopnav-tab " + (aba === a.id ? "active" : "")} onClick={() => setAba(a.id)}>{a.l}{a.n != null && <span className="cli-moduletopnav-n">{a.n}</span>}</button>)}
        </nav>
        <div className="os-drawer-body">
          {aba === "dados" &&
            <div className="os-drawer-section">
              <div style={{ display: "grid", gridTemplateColumns: "1fr 168px", gap: 16 }}>
                <div className="pb-drawer-grid">
                  {dt("SKU", p.sku)}{dt("Tipo de código de barras", p.barcode)}{dt("Unidade", p.unit)}
                  {dt("Marca", p.brand)}{dt("Categoria", p.cat)}{dt("Subcategoria", p.sub)}
                  {dt("Imposto aplicável", p.tax)}{dt("Tipo de imposto na venda", p.taxType === "inclusive" ? "Inclusivo" : "Exclusivo")}{dt("Peso", p.weight)}
                  {dt("Gerenciar estoque", p.stockOn ? "Sim" : "Não")}{dt("Quantidade de alerta", p.alert != null ? fmtQty(p.alert) : "—")}{dt("Expira em", p.expiry ? p.expiry + (p.expiryType === "days" ? " dias" : " meses") : "Não aplicável")}
                  {dt("Disponível nos locais", p.locs.map((id) => LOCATIONS.find((l) => l.id === id).name).join(", "))}
                  {dt("Não para venda", p.notForSelling ? "Sim" : "Não")}
                  {dt("Tempo de preparo", p.prep ? p.prep + " min" : "—")}
                </div>
                <div className="pb-img">Imagem do produto<br />(1:1, até 5 MB)</div>
              </div>
              {p.desc && <p style={{ marginTop: 14, fontSize: 12.5, color: "var(--text-dim)", lineHeight: 1.5 }}>{p.desc}</p>}
            </div>}

          {aba === "variacoes" &&
            <div className="os-drawer-section">
              <h3>Variações</h3>
              <table className="pb-tbl">
                <thead><tr><th>Variação</th><th>SKU</th><th className="r">Compra (excl.)</th><th className="r">Compra (incl.)</th><th className="r">% lucro</th><th className="r">Venda (excl.)</th><th className="r">Venda (incl.)</th></tr></thead>
                <tbody>{p.variations.map((va) => (
                  <tr key={va.sku}><td>{va.name}</td><td className="m">{va.sku}</td>
                    <td className="r">{fmtBRL(va.dpp)}</td><td className="r">{fmtBRL(incTax(va.dpp, p.tax))}</td>
                    <td className="r">{fmtQty(va.profit)} %</td><td className="r">{fmtBRL(va.dsp)}</td><td className="r">{fmtBRL(incTax(va.dsp, p.tax))}</td></tr>))}
                </tbody>
              </table>
              <div style={{ marginTop: 10 }}>
                <h3>Preços por grupo</h3>
                <table className="pb-tbl">
                  <thead><tr><th>Variação</th>{PRICE_GROUPS.map((g) => <th key={g.id} className="r">{g.name}</th>)}</tr></thead>
                  <tbody>{p.variations.map((va) => (
                    <tr key={va.sku}><td>{va.name}</td>
                      {PRICE_GROUPS.map((g, i) => <td key={g.id} className="r">{fmtBRL(va.dsp * [1, 0.9, 0.85, 0.75][i])}</td>)}</tr>))}
                  </tbody>
                </table>
              </div>
            </div>}

          {aba === "composicao" &&
            <div className="os-drawer-section">
              <h3>Composição</h3>
              <table className="pb-tbl">
                <thead><tr><th>Produto</th><th>SKU</th><th className="r">Qtd</th><th className="r">Compra (excl.)</th><th className="r">Total (excl.)</th></tr></thead>
                <tbody>{p.combo.map((c) => (
                  <tr key={c.sku}><td><b>{c.name}</b></td><td className="m">{c.sku}</td><td className="r">{fmtQty(c.qty)} {c.unit}</td><td className="r">{fmtBRL(c.dpp)}</td><td className="r">{fmtBRL(c.qty * c.dpp)}</td></tr>))}
                </tbody>
                <tfoot><tr><td colSpan={4}><b>Total líquido</b></td><td className="r"><b>{fmtBRL(p.combo.reduce((s, c) => s + c.qty * c.dpp, 0))}</b></td></tr></tfoot>
              </table>
              <p className="pb-help" style={{ marginTop: 8 }}>Preço de venda padrão da composição: <b>{fmtBRL(p.variations[0].dsp)}</b> · % de lucro {fmtQty(p.variations[0].profit)} %.</p>
            </div>}

          {aba === "estoque" &&
            <div className="os-drawer-section">
              <h3>Detalhes de estoque</h3>
              {p.stockOn ? <>
                <div className="pb-kpis" style={{ gridTemplateColumns: "repeat(3,1fr)" }}>
                  <div className="pb-kpi"><small>Estoque atual</small><b>{fmtQty(p.stock)}</b><div className="ln">{p.unit} · alerta em {fmtQty(p.alert)}</div></div>
                  <div className="pb-kpi"><small>Valor pelo custo</small><b>{fmtBRL(p.stock * p.variations[0].dpp)}</b><div className="ln">preço de compra padrão</div></div>
                  <div className="pb-kpi"><small>Valor pela venda</small><b>{fmtBRL(p.stock * p.variations[0].dsp)}</b><div className="ln">lucro potencial {fmtBRL(p.stock * (p.variations[0].dsp - p.variations[0].dpp))}</div></div>
                </div>
                <table className="pb-tbl" style={{ marginTop: 12 }}>
                  <thead><tr><th>Local</th><th className="r">Saldo</th><th className="r">Alerta</th><th>Situação</th><th /></tr></thead>
                  <tbody>
                    {LOCATIONS.filter((l) => p.locs.includes(l.id)).map((l, i, arr) => {
                      const saldo = Math.round(p.stock / arr.length);
                      const baixo = p.alert != null && saldo <= p.alert;
                      return (
                        <tr key={l.id}>
                          <td>{l.name}</td>
                          <td className="r"><span className={"pb-stock" + (baixo ? " low" : "")}>{fmtQty(saldo)} {p.unit}</span></td>
                          <td className="r">{p.alert != null ? fmtQty(p.alert) : "—"}</td>
                          <td>{baixo ? <span className="pb-pill off">Repor</span> : <span className="pb-pill combo">Suficiente</span>}</td>
                          <td><button className="os-btn sm" onClick={() => onIr("transferir", p)}>Transferir</button></td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
                <p className="pb-help" style={{ marginTop: 10 }}>Saldo por local; a transferência é lançada no módulo Estoque. O histórico completo fica na tela <b>Histórico de estoque</b>.</p>
                <button className="os-btn sm" style={{ marginTop: 8 }} onClick={() => onIr("historico", p)}>Abrir histórico de estoque</button>
              </> : <p className="pb-help">Este produto não gerencia estoque (<b>enable_stock = 0</b>) — serviços e composições entram assim.</p>}
            </div>}

          {aba === "fiscal" &&
            <div className="os-drawer-section">
              <h3>Dados fiscais</h3>
              <div className="pb-drawer-grid">
                {dt("NCM", fs.ncm ? fs.ncm : "não aplicável (serviço)")}
                {dt("CEST", fs.cest || "—")}
                {dt("CFOP padrão de venda", (CFOPS.find((c) => c.id === fs.cfop) || {}).name || "—")}
                {dt("Origem da mercadoria", (ORIGENS.find((o) => o.id === fs.origem) || {}).name || "—")}
                {dt("Imposto aplicável", p.tax)}
                {dt("Tipo de imposto na venda", p.taxType === "inclusive" ? "Inclusivo" : "Exclusivo")}
              </div>
              <p className="pb-help" style={{ marginTop: 10 }}>
                {fs.cest
                  ? "Produto com substituição tributária (CEST preenchido) — a NF-e sai com CFOP de substituído."
                  : fs.ncm
                    ? "Sem substituição tributária. O NCM manda na tributação; conferir antes de emitir NF-e."
                    : "Serviço: sai em NFS-e por ISS, não tem NCM."}
              </p>
              <button className="os-btn sm" style={{ marginTop: 8 }} onClick={() => onIr("fiscal-modulo", p)}>Abrir no módulo Fiscal</button>
            </div>}

          {aba === "prateleira" &&
            <div className="os-drawer-section">
              <h3>Detalhes de prateleira</h3>
              <table className="pb-tbl">
                <thead><tr><th>Local</th><th>Prateleira</th><th>Fileira</th><th>Posição</th></tr></thead>
                <tbody>
                  {LOCATIONS.filter((l) => p.locs.includes(l.id)).map((l) => {
                    const r = p.racks[l.id] || {};
                    return <tr key={l.id}><td>{l.name}</td><td className="m">{r.rack || "—"}</td><td className="m">{r.row || "—"}</td><td className="m">{r.pos || "—"}</td></tr>;
                  })}
                </tbody>
              </table>
            </div>}
        </div>
      </div>
    </Casca>
  );
}

// ─────────── Modais ───────────
function ModalEstoqueInicial({ p, onClose, avisar }) {
  const { DatePicker } = DS();
  const [q, setQ] = useState({});
  const linhas = LOCATIONS.filter((l) => p.locs.includes(l.id));
  return (
    <Modal titulo={"Estoque inicial · " + p.name} onClose={onClose}
      acoes={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button><button className="os-btn primary" onClick={() => { onClose(); avisar("Estoque inicial lançado — o protótipo não grava no banco.", "ok"); }}>Salvar</button></>}>
      <p className="pb-help">Uma linha por local do negócio, como no <b>quick_product_opening_stock</b>: quantidade, custo unitário antes do imposto, validade e lote.</p>
      <table className="pb-tbl">
        <thead><tr><th>Local</th><th className="r">Quantidade</th><th className="r">Custo un. sem imposto</th><th>Validade</th><th>Lote</th><th className="r">Subtotal sem imposto</th></tr></thead>
        <tbody>
          {linhas.map((l) => {
            const row = q[l.id] || { qtd: "0", custo: String(p.variations[0].dpp) };
            const sub = (parseFloat(row.qtd.replace(",", ".")) || 0) * (parseFloat(String(row.custo).replace(",", ".")) || 0);
            return (
              <tr key={l.id}>
                <td>{l.name}</td>
                <td><input className="cell num" value={row.qtd} onChange={(e) => setQ({ ...q, [l.id]: { ...row, qtd: e.target.value } })} /></td>
                <td><input className="cell num" value={row.custo} onChange={(e) => setQ({ ...q, [l.id]: { ...row, custo: e.target.value } })} /></td>
                <td>{DatePicker
                  ? <DatePicker value={row.val || null} onChange={(d) => setQ({ ...q, [l.id]: { ...row, val: d } })} placeholder="dd/mm/aaaa" />
                  : <input className="cell" placeholder="dd/mm/aaaa" />}</td>
                <td><input className="cell" placeholder="Lote" /></td>
                <td className="r">{fmtBRL(sub)}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </Modal>
  );
}
function ModalLocalizacao({ modo, n, onClose, avisar }) {
  const [loc, setLoc] = useState("");
  const add = modo === "add";
  return (
    <Modal titulo={add ? "Adicionar ao local" : "Remover do local"} onClose={onClose}
      acoes={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn primary" disabled={!loc} onClick={() => { onClose(); avisar(`${n} produto(s) ${add ? "adicionados ao" : "removidos do"} local.`, "ok"); }}>Atualizar</button></>}>
      <p className="pb-help">{n} produto(s) selecionado(s). {add ? "O local passa a vender e estocar estes produtos." : "Os produtos deixam de aparecer no local — o estoque já lançado não é apagado."}</p>
      <Fld label="Local do negócio" req><Sel value={loc} onChange={setLoc} options={LOCATIONS} vazio="Selecione…" /></Fld>
    </Modal>
  );
}
function ModalCadastroRapido({ onClose, avisar }) {
  return (
    <Modal titulo="Cadastro rápido de produto" onClose={onClose}
      acoes={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button><button className="os-btn primary" onClick={() => { onClose(); avisar("Produto criado pelo cadastro rápido.", "ok"); }}>Salvar</button></>}>
      <p className="pb-help">O <b>quick_add_product</b> do blade: o mínimo pra faturar agora e completar depois.</p>
      <div className="pb-grid c2">
        <Fld label="Nome do produto" req><input placeholder="Nome do produto" /></Fld>
        <Fld label="SKU" dica="Em branco, o sistema gera."><input placeholder="Gerado automaticamente" /></Fld>
        <Fld label="Unidade" req><Sel value="Un" options={UNITS.map((u) => u.short)} /></Fld>
        <Fld label="Categoria"><Sel value="" options={CATEGORIES} vazio="Selecione…" /></Fld>
        <Fld label="Preço de compra sem imposto" req><input className="num" defaultValue="0,00" /></Fld>
        <Fld label="Preço de venda sem imposto" req><input className="num" defaultValue="0,00" /></Fld>
      </div>
    </Modal>
  );
}

// Confirmação de verdade (PT-04): ação destrutiva não passa só com toast.
function ModalConfirmar({ pedido, onClose }) {
  return (
    <Modal titulo={pedido.titulo} onClose={onClose}
      acoes={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className={"os-btn " + (pedido.perigo ? "ghost danger" : "primary")} onClick={() => { onClose(); pedido.on(); }}>{pedido.cta}</button></>}>
      <p className="pb-help" style={{ fontSize: 12.5, color: "var(--text)" }}>{pedido.corpo}</p>
      {pedido.aviso && <p className="pb-help" style={{ color: "var(--warn)" }}>{pedido.aviso}</p>}
    </Modal>
  );
}

// Etiquetas / código de barras — sai no papel. ProofFrame + ProofStrip do DS (print-craft):
// a folha de etiqueta é uma prova de impressão, com marcas de corte e tira de controle CMYK.
function ModalEtiquetas({ produtos, onClose }) {
  const { ProofFrame, ProofStrip } = DS();
  const [copias, setCopias] = useState("1");
  const [comPreco, setComPreco] = useState(true);
  const n = Math.max(1, parseInt(copias, 10) || 1);
  const etiquetas = [];
  produtos.forEach((p) => { for (let i = 0; i < n; i++) etiquetas.push({ p, i }); });
  const folha = (
    <>
      <div className="pb-etiquetas">
        {etiquetas.slice(0, 24).map(({ p, i }) => (
          <div className="pb-etiqueta" key={p.id + "-" + i}>
            <b>{p.name}</b>
            <div className="pb-barras" aria-hidden="true">{Array.from({ length: 34 }, (_, k) => <i key={k} style={{ width: (k * 7 + p.id) % 3 === 0 ? 3 : 1 }} />)}</div>
            <span className="m">{p.sku} · {p.barcode}</span>
            {comPreco && <strong>{fmtBRL(incTax(p.variations[0].dsp, p.tax))}</strong>}
          </div>
        ))}
      </div>
      {ProofStrip && <div style={{ marginTop: 12 }}><ProofStrip kind="cmyk" height={10} /></div>}
    </>
  );
  return (
    <Modal titulo="Etiquetas e código de barras" onClose={onClose}
      acoes={<><button className="os-btn ghost" onClick={onClose}>Fechar</button>
        <button className="os-btn primary" onClick={() => window.print()}><Ic name="print" size={13} /> Imprimir</button></>}>
      <div className="pb-grid c2">
        <Fld label="Cópias por produto"><input className="num" value={copias} onChange={(e) => setCopias(e.target.value)} /></Fld>
        <div className="pb-fld" style={{ justifyContent: "flex-end" }}>
          <label className="pb-chk"><input type="checkbox" checked={comPreco} onChange={(e) => setComPreco(e.target.checked)} /><b>Imprimir o preço de venda</b></label>
        </div>
      </div>
      {ProofFrame ? <ProofFrame cropMarks grid padding={16}>{folha}</ProofFrame> : folha}
      {etiquetas.length > 24 && <p className="pb-help">Mostrando 24 de {etiquetas.length} etiquetas — a impressão sai completa.</p>}
    </Modal>
  );
}

// Menu de colunas (o `visible` do DataTable, agora visível pro operador).
function MenuColunas({ cols, setCols }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const off = (e) => { if (!ref.current?.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", off);
    return () => document.removeEventListener("mousedown", off);
  }, [open]);
  const n = PB_COLS.filter((c) => cols[c.id]).length;
  return (
    <div className="pb-kebab" ref={ref}>
      <button className="os-btn sm" onClick={() => setOpen((o) => !o)}><Ic name="grid" size={12} /> Colunas <span className="m" style={{ opacity: .6 }}>{n}</span></button>
      {open &&
        <div className="pb-menu pb-menu-dir" role="menu">
          {PB_COLS.map((c) => (
            <button key={c.id} onClick={() => setCols({ ...cols, [c.id]: !cols[c.id] })}>
              <span style={{ width: 12, color: "var(--accent)" }}>{cols[c.id] ? "✓" : ""}</span>{c.l}
            </button>
          ))}
          <hr />
          <button onClick={() => setCols({ ...PB_COLS_PADRAO })}>Voltar ao padrão</button>
        </div>}
    </div>
  );
}

// Exporta o que está na tela (o `downloadExcel` do controller) — CSV com ; pra abrir no Excel PT-BR.
function exportarCsv(lista) {
  const cab = ["Produto", "SKU", "Tipo", "Categoria", "Subcategoria", "Marca", "Unidade", "Imposto", "Compra", "Venda", "Estoque", "Locais", "Situação"];
  const linhas = lista.map((p) => [p.name, p.sku, TYPE_LABEL[p.type], p.cat, p.sub, p.brand, p.unit, p.tax,
    String(p.variations[0].dpp).replace(".", ","), String(p.variations[0].dsp).replace(".", ","),
    p.stockOn ? String(p.stock) : "", p.locs.map((id) => LOCATIONS.find((l) => l.id === id).name).join(" / "), p.active ? "Ativo" : "Inativo"]);
  const csv = "\uFEFF" + [cab, ...linhas].map((r) => r.map((c) => '"' + String(c == null ? "" : c).replace(/"/g, '""') + '"').join(";")).join("\r\n");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
  a.download = "produtos.csv";
  a.click();
  URL.revokeObjectURL(a.href);
}

// ─────────── Tela lista (index.blade.php) ───────────
function TelaLista({ aba, setAba, onIr, avisar }) {
  const [f, setF] = useState({ type: "", cat: "", unit: "", tax: "", brand: "", loc: "", active: "", nfs: false });
  const [busca, setBusca] = useState("");
  const [sel, setSel] = useState([]);
  const [ver, setVer] = useState(null);
  const [inicial, setInicial] = useState(null);
  const [locModal, setLocModal] = useState(null);
  const [rapido, setRapido] = useState(false);
  const [confirmar, setConfirmar] = useState(null);
  const [etiquetas, setEtiquetas] = useState(null);
  const [cols, setCols] = useState(() => {
    try { return { ...PB_COLS_PADRAO, ...JSON.parse(localStorage.getItem("oimpresso.prod.cols") || "{}") }; } catch (e) { return { ...PB_COLS_PADRAO }; }
  });
  const [carregando, setCarregando] = useState(true);
  const [selSeq, setSelSeq] = useState(0);
  const { FilterChip, Skeleton } = DS();
  // A grade do DS é dona da seleção: pra limpar de fora, remontamos com uma key nova.
  const limparSel = () => { setSel([]); setSelSeq((n) => n + 1); };
  const limparOuSetar = (v) => { const arr = typeof v === "function" ? v(sel) : v; arr.length === 0 && sel.length > 0 ? limparSel() : setSel(arr); };
  const [densa, setDensa] = useState(() => { try { return localStorage.getItem("oimpresso.prod.densa") === "1"; } catch (e) { return false; } });
  const buscaRef = useRef(null);
  useEffect(() => { try { localStorage.setItem("oimpresso.prod.densa", densa ? "1" : "0"); } catch (e) {} }, [densa]);
  useEffect(() => { try { localStorage.setItem("oimpresso.prod.cols", JSON.stringify(cols)); } catch (e) {} }, [cols]);
  // Carregamento: o DataTable é server-side — a lista chega, não nasce pronta.
  useEffect(() => { const t = setTimeout(() => setCarregando(false), 420); return () => clearTimeout(t); }, []);

  // Atalhos do balcão (Larissa): / busca · n novo · esc fecha o que está aberto.
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "Escape") { setVer(null); setInicial(null); setLocModal(null); setRapido(false); return; }
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || "")) return;
      if (e.key === "/") { e.preventDefault(); buscaRef.current?.focus(); }
      if (e.key === "n" || e.key === "N") { e.preventDefault(); onIr("form", null); }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [onIr]);

  const filtrados = useMemo(() => PRODUCTS.filter((p) => {
    if (f.type && p.type !== f.type) return false;
    if (f.cat && p.cat !== f.cat) return false;
    if (f.unit && p.unit !== f.unit) return false;
    if (f.tax && p.tax !== f.tax) return false;
    if (f.brand && p.brand !== f.brand) return false;
    if (f.loc && !p.locs.includes(Number(f.loc))) return false;
    if (f.active === "active" && !p.active) return false;
    if (f.active === "inactive" && p.active) return false;
    if (f.nfs && !p.notForSelling) return false;
    if (busca && !(p.name + p.sku).toLowerCase().includes(busca.toLowerCase())) return false;
    return true;
  }), [f, busca]);

  // A grade do DS ordena internamente; aqui só garantimos a ordem inicial (nome A→Z).
  const ordenados = useMemo(() => [...filtrados].sort((a, b) => a.name.toLowerCase() < b.name.toLowerCase() ? -1 : 1), [filtrados]);
  const rows = ordenados;

  // Chips do que está filtrando agora — nada de filtro invisível.
  const chips = [];
  if (f.type) chips.push({ k: "type", l: "Tipo", v: TYPE_LABEL[f.type] });
  if (f.cat) chips.push({ k: "cat", l: "Categoria", v: f.cat });
  if (f.unit) chips.push({ k: "unit", l: "Unidade", v: f.unit });
  if (f.tax) chips.push({ k: "tax", l: "Imposto", v: f.tax });
  if (f.brand) chips.push({ k: "brand", l: "Marca", v: f.brand });
  if (f.loc) chips.push({ k: "loc", l: "Local", v: (LOCATIONS.find((l) => l.id === Number(f.loc)) || {}).name });
  if (f.active) chips.push({ k: "active", l: "Situação", v: f.active === "active" ? "Ativo" : "Inativo" });
  if (f.nfs) chips.push({ k: "nfs", l: "", v: "Não para venda" });
  if (busca) chips.push({ k: "__busca", l: "Busca", v: busca });
  const tirarChip = (k) => k === "__busca" ? setBusca("") : setF({ ...f, [k]: k === "nfs" ? false : "" });

  const kpi = useMemo(() => ({
    total: PRODUCTS.length,
    baixo: PRODUCTS.filter((p) => p.stockOn && p.alert != null && p.stock <= p.alert).length,
    valor: PRODUCTS.filter((p) => p.stockOn).reduce((s, p) => s + p.stock * p.variations[0].dpp, 0),
    inativos: PRODUCTS.filter((p) => !p.active).length,
  }), []);

  const onAcao = (a, p) => {
    if (a === "ver") return setVer(p);
    if (a === "inicial") return setInicial(p);
    if (a === "editar" || a === "historico" || a === "precos") return onIr(a, p);
    if (a === "duplicar") return onIr("editar", { ...p, name: p.name + " (cópia)", sku: "" });
    if (a === "barras") return setEtiquetas([p]);
    if (a === "comprar") { irPara("compras", { produto: p.sku, nome: p.name, acao: "novo-item" }); return avisar("Abrindo Compras com “" + p.name + "” no item novo.", "ok"); }
    if (a === "transferir") { irPara("compras", { produto: p.sku, nome: p.name, acao: "transferencia" }); return avisar("Transferência entre locais: " + p.name + ".", "ok"); }
    if (a === "os") { irPara("os", { produto: p.sku, nome: p.name, acao: "novo-item" }); return avisar("Abrindo Ordens de Serviço com o produto no orçamento.", "ok"); }
    if (a === "fiscal") return setVer(p);
    if (a === "toggle") return setConfirmar({
      titulo: p.active ? "Desativar produto" : "Ativar produto",
      corpo: p.active
        ? `“${p.name}” sai da busca do PDV e dos orçamentos novos. O histórico, o estoque e as vendas antigas ficam intactos.`
        : `“${p.name}” volta a aparecer na venda e no orçamento.`,
      cta: p.active ? "Desativar" : "Ativar", perigo: p.active,
      on: () => avisar(p.active ? "Produto desativado — sai da venda, fica no histórico." : "Produto ativado.", "ok"),
    });
    if (a === "excluir") return setConfirmar({
      titulo: "Excluir produto", perigo: true, cta: "Excluir",
      corpo: `Excluir “${p.name}” (${p.sku}) apaga o cadastro, as variações e os preços por grupo.`,
      aviso: p.stockOn && p.stock > 0 ? "Este produto tem estoque e movimento — o servidor vai recusar a exclusão. Desative em vez de excluir." : "Esta ação não tem volta.",
      on: () => avisar(p.stockOn && p.stock > 0 ? "Exclusão recusada: produto com movimento de estoque." : "Produto excluído.", p.stockOn && p.stock > 0 ? "danger" : "warn"),
    });
  };

  return (
    <>
      <div className="pb-kpis" data-contract="produto-kpis">
        <div className="pb-kpi"><small>Produtos cadastrados</small><b>{kpi.total}</b><div className="ln">{PRODUCTS.filter((p) => p.type === "variable").length} variáveis · {PRODUCTS.filter((p) => p.type === "combo").length} composições</div></div>
        <div className={"pb-kpi" + (kpi.baixo ? " neg" : "")}><small>Abaixo do alerta</small><b>{kpi.baixo}</b><div className="ln">quantidade de alerta atingida</div></div>
        <div className="pb-kpi"><small>Valor em estoque (custo)</small><b style={{ fontSize: 17 }}>{fmtBRL(kpi.valor)}</b><div className="ln">só produtos com estoque gerenciado</div></div>
        <div className="pb-kpi warn"><small>Inativos</small><b>{kpi.inativos}</b><div className="ln">fora da venda, dentro do histórico</div></div>
      </div>

      <Filtros f={f} setF={setF} />

      <Widget flush titulo={<><Ic name="product" size={13} /> {aba === "lista" ? "Todos os produtos" : "Relatório de estoque"}</>} nota={ordenados.length + " de " + PRODUCTS.length}>
        <nav className="cli-moduletopnav" data-contract="produto-abas" aria-label="Abas do índice" style={{ padding: "0 12px" }}>
          <button className={"cli-moduletopnav-tab " + (aba === "lista" ? "active" : "")} onClick={() => setAba("lista")}>Todos os produtos<span className="cli-moduletopnav-n">{PRODUCTS.length}</span></button>
          <button className={"cli-moduletopnav-tab " + (aba === "estoque" ? "active" : "")} onClick={() => setAba("estoque")}>Relatório de estoque</button>
        </nav>

        <div className="pb-toolbar" data-contract="produto-toolbar">
          <div className="pb-busca">
            <Ic name="search" size={12} />
            <input ref={buscaRef} value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar nome ou SKU…" />
            <kbd>/</kbd>
          </div>
          <div className="sp" />
          <div className="pb-seg" role="group" aria-label="Densidade da tabela">
            <button className={densa ? "" : "on"} onClick={() => setDensa(false)}>Confortável</button>
            <button className={densa ? "on" : ""} onClick={() => setDensa(true)}>Compacto</button>
          </div>
          <MenuColunas cols={cols} setCols={setCols} />
          <button className="os-btn sm" onClick={() => setRapido(true)}><Ic name="plus" size={12} /> Cadastro rápido</button>
        </div>

        {aba === "lista" && chips.length > 0 &&
          <div className="pb-chips" data-contract="produto-chips">
            <span className="pb-chips-l">Filtrando por</span>
            {chips.map((c) => (FilterChip
              ? <FilterChip key={c.k} label={c.l || c.v} value={c.l ? c.v : undefined} onRemove={() => tirarChip(c.k)} />
              : <button key={c.k} className="pb-chip" onClick={() => tirarChip(c.k)}>{c.v} ✕</button>))}
            <button className="pb-chip limpar" onClick={() => { setF({ type: "", cat: "", unit: "", tax: "", brand: "", loc: "", active: "", nfs: false }); setBusca(""); }}>Limpar tudo</button>
          </div>}

        {aba === "lista"
          ? (carregando
            ? <div style={{ padding: "14px 16px" }}>{Skeleton
                ? <Skeleton variant="row" count={8} />
                : <p className="pb-help">Carregando produtos…</p>}</div>
            : <ListaProdutos rows={rows} sel={sel} setSel={limparOuSetar} onAcao={onAcao} densa={densa} cols={cols} selSeq={selSeq} />)
          : <RelatorioEstoque rows={filtrados} />}

        {aba === "lista" &&
          <div className="pb-pag" data-contract="produto-rodape-lista">
            <span>{ordenados.length} produto(s) na grade · clique no cabeçalho pra ordenar, arraste a borda pra redimensionar</span>
            <div className="sp" />
            <button className="os-btn sm" onClick={() => { exportarCsv(ordenados); avisar(ordenados.length + " produto(s) exportados com os filtros atuais.", "ok"); }}><Ic name="sheet" size={12} /> Exportar filtrados</button>
          </div>}
      </Widget>

      {aba === "lista" && sel.length > 0 &&
        <div className="pb-bulk" data-contract="produto-rodape-selecao">
          <b>{sel.length}</b> selecionado(s)
          <button className="os-btn sm" disabled={!sel.length} onClick={() => onIr("massa")}><Ic name="pencil" size={12} /> Edição em massa</button>
          <button className="os-btn sm" disabled={!sel.length} onClick={() => setLocModal("add")}>Adicionar ao local</button>
          <button className="os-btn sm" disabled={!sel.length} onClick={() => setLocModal("remove")}>Remover do local</button>
          <button className="os-btn sm" onClick={() => setEtiquetas(PRODUCTS.filter((p) => sel.includes(p.id)))}><Ic name="print" size={12} /> Etiquetas</button>
          <button className="os-btn sm" onClick={() => setConfirmar({ titulo: "Desativar selecionados", perigo: true, cta: "Desativar " + sel.length, corpo: sel.length + " produto(s) saem da venda e do orçamento novo, mantendo histórico e estoque.", on: () => { avisar(sel.length + " produto(s) desativados.", "warn"); limparSel(); } })}>Desativar selecionados</button>
          <button className="os-btn sm ghost danger" onClick={() => setConfirmar({ titulo: "Excluir selecionados", perigo: true, cta: "Excluir " + sel.length, corpo: "Vai apagar " + sel.length + " cadastro(s), com variações e preços por grupo.", aviso: "Produtos com movimento de estoque serão recusados pelo servidor.", on: () => { avisar("Exclusão enviada — itens com movimento foram recusados.", "danger"); limparSel(); } })}>Excluir selecionados</button>
          <div className="sp" />
          <button className="icon-btn" title="Limpar seleção" onClick={limparSel}>✕</button>
        </div>}

      {ver && <DetalheDrawer p={ver} onClose={() => setVer(null)} onIr={(a, p) => { setVer(null); onIr(a, p); }} />}
      {inicial && <ModalEstoqueInicial p={inicial} onClose={() => setInicial(null)} avisar={avisar} />}
      {locModal && <ModalLocalizacao modo={locModal} n={sel.length} onClose={() => setLocModal(null)} avisar={avisar} />}
      {rapido && <ModalCadastroRapido onClose={() => setRapido(false)} avisar={avisar} />}
      {confirmar && <ModalConfirmar pedido={confirmar} onClose={() => setConfirmar(null)} />}
      {etiquetas && <ModalEtiquetas produtos={etiquetas} onClose={() => setEtiquetas(null)} />}
    </>
  );
}

// ─────────── Página do módulo ───────────
function ProdutoBladePage({ view = "lista" }) {
  const M = MP();
  const [tela, setTela] = useState(view === "estoque" ? "lista" : view);
  const [aba, setAba] = useState(view === "estoque" ? "estoque" : "lista");
  const [alvo, setAlvo] = useState(view === "form" ? null : PRODUCTS[0]);
  const [hora, setHora] = useState(() => new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }));
  // Chegou de outro módulo? (OS/orçamento pedindo um produto — window.__PBCtx)
  const [entrada, setEntrada] = useState(() => { const c = window.__PBCtx; return c && c.origem && c.origem !== "produto" && Date.now() - c.em < 60000 ? c : null; });
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  useEffect(() => { setTela(view === "estoque" ? "lista" : view); setAba(view === "estoque" ? "estoque" : "lista"); }, [view]);

  const CROSS = {
    comprar:        { rota: "compras", acao: "novo-item",     msg: (p) => "Compras aberto com “" + p.name + "” no item novo." },
    transferir:     { rota: "compras", acao: "transferencia", msg: (p) => "Transferência de “" + p.name + "” entre locais." },
    producao:       { rota: "cv",      acao: "nova-op",       msg: (p) => "OP gerada a partir da composição “" + p.name + "”." },
    os:             { rota: "os",      acao: "novo-item",     msg: (p) => "OS aberta com “" + p.name + "” no orçamento." },
    "fiscal-modulo":{ rota: "fiscal",  acao: "produto",       msg: (p) => "Dados fiscais de “" + p.name + "” no módulo Fiscal." },
  };
  const onIr = (destino, p) => {
    const x = CROSS[destino];
    if (x) { avisar(x.msg(p || { name: "produto" }), "ok"); return irPara(x.rota, { produto: (p || {}).sku, nome: (p || {}).name, acao: x.acao }); }
    setAlvo(p || null);
    setTela(destino === "editar" ? "form" : destino);
  };
  const F = window.ProdutoBladeForms || {};
  const TITULOS = { lista: "Produtos", form: "Produto — cadastro", historico: "Histórico de estoque", precos: "Preços por grupo de venda", massa: "Edição em massa", analises: "Análises do catálogo" };

  return (
    <div className="pb-root" data-screen-label={"01 Produto · " + (TITULOS[tela] || tela)}>
      {M.Header &&
        <M.Header modulo="Produtos" papel={tela === "lista" ? "Catálogo" : TITULOS[tela]}
          contexto={["OFFICEIMPRESSO", "matriz", PRODUCTS.length + " produtos · " + PRICE_GROUPS.length + " grupos de preço"]}
          atualizadoAs={hora}
          onRefresh={() => { setHora(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })); avisar("Catálogo reapurado agora.", "ok"); }}
          glyph={<Ic name="product" />}
          acoes={<>
            {tela !== "lista" && <button className="os-btn" onClick={() => setTela("lista")}>← Voltar à lista</button>}
            <button className="os-btn" onClick={() => { exportarCsv(PRODUCTS); avisar("Catálogo exportado em CSV (abre no Excel).", "ok"); }}><Ic name="sheet" size={13} /> Baixar Excel</button>
            <button className="os-btn primary" onClick={() => { setAlvo(null); setTela("form"); }}><Ic name="plus" size={13} /> Novo produto</button>
          </>} />}

      <div className="pb-body">
        {entrada &&
          <div className="pb-volta">
            <Ic name="chev" size={13} />
            <span>Você veio de <b>{entrada.origemLabel || entrada.origem}</b>{entrada.nome ? " procurando um produto para “" + entrada.nome + "”" : ""}.</span>
            <div style={{ flex: 1 }} />
            <button className="os-btn sm" onClick={() => irPara(entrada.origem, { volta: true })}>Voltar e usar</button>
            <button className="icon-btn" aria-label="Dispensar" onClick={() => setEntrada(null)}>✕</button>
          </div>}
        {tela === "lista" && <TelaLista aba={aba} setAba={setAba} onIr={onIr} avisar={avisar} />}
        {tela === "form" && (F.FormProduto ? <F.FormProduto produto={alvo} onSair={() => setTela("lista")} onIr={onIr} avisar={avisar} /> : null)}
        {tela === "historico" && (F.Historico ? <F.Historico produto={alvo || PRODUCTS[0]} avisar={avisar} /> : null)}
        {tela === "precos" && (F.Precos ? <F.Precos produto={alvo || PRODUCTS[0]} onSair={() => setTela("lista")} avisar={avisar} /> : null)}
        {tela === "massa" && (F.Massa ? <F.Massa onSair={() => setTela("lista")} avisar={avisar} /> : null)}
        {tela === "analises" && (window.ProdutoAnalises ? <window.ProdutoAnalises onIr={onIr} avisar={avisar} /> : null)}
      </div>
      {avisoNode}
    </div>
  );
}

window.ProdutoBladePage = ProdutoBladePage;
window.PBUI = { Widget, Fld, Sel, Modal, Kebab };
})();
