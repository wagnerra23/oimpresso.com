// produto-blade-forms.jsx — telas de FORMULÁRIO do módulo Produto, traduzidas dos blades:
//   create.blade.php / edit.blade.php + partials/single|variable|combo_product_form_part → FormProduto
//   stock_history.blade.php + stock_history_details.blade.php ....................... → Historico
//   add-selling-prices.blade.php ................................................... → Precos
//   bulk-edit.blade.php + partials/bulk_edit_product_row ........................... → Massa
// Expõe window.ProdutoBladeForms. Depende de window.PBD (dados) e window.PBUI (peças).
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const D = () => window.PBD;
const U = () => window.PBUI;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// ─────────────────────────────── Formulário do produto ───────────────────────────────
const RASCUNHO = "oimpresso.prod.rascunho";

function FormProduto({ produto, onSair, onIr, avisar }) {
  const { UNITS, CATEGORIES, SUBCATEGORIES, BRANDS, TAXES, LOCATIONS, BARCODE_TYPES, WARRANTIES, PRODUCTS, ORIGENS, CFOPS, fmtBRL, taxRate } = D();
  const { Widget, Fld, Sel } = U();
  const edit = !!produto;
  const [f, setF] = useState(() => ({
    name: produto?.name || "", sku: produto?.sku || "", barcode: produto?.barcode || "C128",
    unit: produto?.unit || "Un", subUnits: [], unit2: "", unit2Mult: "1", brand: produto?.brand || "Sem marca",
    cat: produto?.cat || "", sub: produto?.sub || "", locs: produto?.locs || [1],
    stockOn: produto ? produto.stockOn : true, alert: produto?.alert != null ? String(produto.alert) : "",
    warranty: "Sem garantia", desc: produto?.desc || "",
    expiry: produto?.expiry ? String(produto.expiry) : "", expiryType: produto?.expiryType || "months",
    srNo: false, notForSelling: produto?.notForSelling || false, weight: produto?.weight || "",
    racks: produto?.racks || {}, prep: produto?.prep ? String(produto.prep) : "",
    cf1: produto?.cf?.[1] || "", cf2: "", cf3: "", cf4: "", cf5: "", cf6: "", cf7: "",
    tax: produto?.tax || "Nenhum", taxType: produto?.taxType || "exclusive", type: produto?.type || "single",
    ncm: produto?.fiscal?.ncm || "", cest: produto?.fiscal?.cest || "", cfop: produto?.fiscal?.cfop || "5102", origem: produto?.fiscal?.origem || "0",
    skuType: "with_out_variation",
    dpp: produto ? String(produto.variations[0].dpp) : "", profit: produto ? String(produto.variations[0].profit) : "25", dsp: produto ? String(produto.variations[0].dsp) : "",
    variacoes: produto?.type === "variable"
      ? [{ nome: (produto.variations[0].name.split(" - ")[0] || "Variação"), valores: produto.variations.map((va) => ({ v: va.name.split(" - ")[1] || va.name, dpp: String(va.dpp), profit: String(va.profit), dsp: String(va.dsp) })) }]
      : [{ nome: "", valores: [{ v: "", dpp: "", profit: "25", dsp: "" }] }],
    combo: produto?.combo ? produto.combo.map((c) => ({ ...c })) : [],
    comboProfit: produto?.type === "combo" ? String(produto.variations[0].profit) : "25",
  }));
  const set = (k) => (val) => setF((s) => ({ ...s, [k]: val }));
  const num = (x) => parseFloat(String(x).replace(/\./g, "").replace(",", ".")) || 0;
  const rate = taxRate(f.tax);
  const { Alert } = DS();
  const [tocado, setTocado] = useState({});
  const [salvo, setSalvo] = useState(null);
  const skuRef = useRef(null);

  // Rascunho: o cadastro é longo — recarregar a página não pode perder o trabalho.
  useEffect(() => {
    if (edit) return;
    try {
      const bruto = localStorage.getItem(RASCUNHO);
      if (bruto) { const d = JSON.parse(bruto); if (d && d.name) { setF((s) => ({ ...s, ...d })); avisar("Rascunho recuperado — você parou em “" + d.name + "”.", "ok"); } }
    } catch (e) {}
  }, []);
  useEffect(() => {
    if (edit) return;
    const t = setTimeout(() => {
      try { localStorage.setItem(RASCUNHO, JSON.stringify(f)); setSalvo(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })); } catch (e) {}
    }, 900);
    return () => clearTimeout(t);
  }, [f, edit]);

  // Aviso de saída com alteração pendente (__page_leave_confirmation do blade).
  useEffect(() => {
    const sujo = !!(f.name || f.dpp || f.dsp);
    const onBefore = (e) => { if (!sujo) return; e.preventDefault(); e.returnValue = ""; };
    window.addEventListener("beforeunload", onBefore);
    return () => window.removeEventListener("beforeunload", onBefore);
  }, [f.name, f.dpp, f.dsp]);

  // Validação inline — mesma regra do ProductController (obrigatório, número, SKU único).
  const erros = {};
  if (!f.name.trim()) erros.name = "O nome do produto é obrigatório.";
  if (f.sku && PRODUCTS.some((p) => p.sku.toLowerCase() === f.sku.trim().toLowerCase() && p.name !== (produto || {}).name)) erros.sku = "Já existe um produto com este SKU.";
  if (f.stockOn && f.alert && num(f.alert) < 0) erros.alert = "A quantidade de alerta não pode ser negativa.";
  if (f.type === "single") {
    if (!f.dpp) erros.dpp = "Informe o preço de compra sem imposto.";
    else if (num(f.dpp) <= 0) erros.dpp = "O preço de compra precisa ser maior que zero.";
    if (num(f.profit) < 0) erros.profit = "Margem negativa — confira.";
  }
  if (f.type === "variable") {
    if (!f.variacoes[0].nome.trim()) erros.varNome = "Dê um nome à variação (ex.: Acabamento, Cor).";
    if (!f.variacoes[0].valores.some((x) => x.v.trim())) erros.varValor = "Pelo menos um valor de variação.";
  }
  if (f.type === "combo" && f.combo.length === 0) erros.combo = "Uma composição precisa de ao menos um item.";
  if (f.stockOn && f.ncm && !/^\d{4}\.?\d{2}\.?\d{2}$/.test(f.ncm.replace(/\s/g, ""))) erros.ncm = "NCM tem 8 dígitos (ex.: 3921.90.90).";
  const err = (k) => tocado[k] && erros[k] ? erros[k] : null;
  const marcarTudo = () => setTocado({ name: 1, sku: 1, alert: 1, dpp: 1, profit: 1, varNome: 1, varValor: 1, combo: 1, ncm: 1 });

  // Sub-SKU gerado ao vivo (sku_type do variable_product_form_part).
  const subSku = (i) => {
    const base = (f.sku || "NOVO").trim().toUpperCase();
    return f.skuType === "with_variation"
      ? base + "-" + ((f.variacoes[0].valores[i] || {}).v || "VAR").slice(0, 3).toUpperCase()
      : base + "-" + (i + 1);
  };

  // Preço: compra excl. → % lucro → venda excl. → +imposto (product.js do UltimatePOS).
  const dppInc = num(f.dpp) * (1 + rate / 100);
  const dspCalc = num(f.dpp) * (1 + num(f.profit) / 100);
  const dspInc = num(f.dsp || dspCalc) * (1 + rate / 100);
  const comboTotal = f.combo.reduce((s, c) => s + c.qty * c.dpp, 0);
  const comboVenda = comboTotal * (1 + num(f.comboProfit) / 100);

  const salvar = (modo) => {
    marcarTudo();
    const chaves = Object.keys(erros);
    if (chaves.length) return avisar(chaves.length === 1 ? erros[chaves[0]] : "Corrija " + chaves.length + " campos antes de salvar.", "danger");
    try { localStorage.removeItem(RASCUNHO); } catch (e) {}
    if (modo === "estoque") { avisar("Produto salvo — abrindo o lançamento de estoque inicial.", "ok"); onSair(); return; }
    if (modo === "precos") { avisar("Produto salvo — abrindo os preços por grupo.", "ok"); onIr("precos", produto || PRODUCTS[0]); return; }
    avisar(modo === "outro" ? "Produto salvo. Formulário limpo pro próximo." : "Produto salvo.", "ok");
    if (modo !== "outro") onSair();
  };

  const toggleLoc = (id) => setF((s) => ({ ...s, locs: s.locs.includes(id) ? s.locs.filter((x) => x !== id) : [...s.locs, id] }));
  const setVal = (vi, k, val) => setF((s) => {
    const variacoes = s.variacoes.map((g, i) => i !== 0 ? g : { ...g, valores: g.valores.map((x, j) => j !== vi ? x : { ...x, [k]: val }) });
    return { ...s, variacoes };
  });

  return (
    <>
      {Object.keys(erros).length > 0 && Object.keys(tocado).length > 0 && Alert &&
        <Alert tone="danger" title={Object.keys(erros).length === 1 ? "Um campo precisa de ajuste" : Object.keys(erros).length + " campos precisam de ajuste"}>
          {Object.keys(erros).map((k) => erros[k]).join(" · ")}
        </Alert>}
      <Widget titulo={<><Ic name="product" size={13} /> {edit ? "Editar produto" : "Novo produto"}</>} nota="dados do produto">
        <div className="pb-grid">
          <Fld label="Nome do produto" req erro={err("name")}><input value={f.name} onChange={(e) => set("name")(e.target.value)} onBlur={() => setTocado((t) => ({ ...t, name: 1 }))} placeholder="Nome do produto" aria-invalid={!!err("name")} /></Fld>
          <Fld label="SKU" dica="Em branco, o sistema gera. O leitor de código de barras escreve aqui." erro={err("sku")}>
            <div className="pb-inputgroup">
              <input ref={skuRef} value={f.sku} onChange={(e) => set("sku")(e.target.value)} onBlur={() => setTocado((t) => ({ ...t, sku: 1 }))} placeholder="Gerado automaticamente" aria-invalid={!!err("sku")} />
              <button className="os-btn sm" title="Ler código de barras (o leitor digita no campo)"
                onClick={() => { skuRef.current?.focus(); avisar("Leitor pronto — bipe o produto que o código cai no SKU.", "ok"); }}>Bipar</button>
            </div>
          </Fld>
          <Fld label="Tipo de código de barras" req><Sel value={f.barcode} onChange={set("barcode")} options={BARCODE_TYPES} /></Fld>

          <Fld label="Unidade" req>
            <div className="pb-inputgroup">
              <Sel value={f.unit} onChange={set("unit")} options={UNITS.map((u) => u.short)} />
              <button className="os-btn sm" title="Cadastrar unidade" onClick={() => avisar("Cadastro rápido de unidade abre em modal.")}>+</button>
            </div>
          </Fld>
          <Fld label="Sub-unidades relacionadas" dica="Ex.: caixa ↔ unidade, para venda fracionada.">
            <div className="pb-inputgroup">
              <Sel value={f.unit2} onChange={set("unit2")} options={UNITS.map((u) => u.short)} vazio="Nenhuma" />
              <input className="num" style={{ width: 74 }} value={f.unit2Mult} onChange={(e) => set("unit2Mult")(e.target.value)} title="Multiplicador" disabled={!f.unit2} />
            </div>
            {f.unit2 && <em className="pb-help">1 {f.unit} = {f.unit2Mult || "1"} {f.unit2}</em>}
          </Fld>
          <Fld label="Marca">
            <div className="pb-inputgroup">
              <Sel value={f.brand} onChange={set("brand")} options={BRANDS} />
              <button className="os-btn sm" title="Cadastrar marca" onClick={() => avisar("Cadastro rápido de marca abre em modal.")}>+</button>
            </div>
          </Fld>

          <Fld label="Categoria"><Sel value={f.cat} onChange={(v) => setF((s) => ({ ...s, cat: v, sub: "" }))} options={CATEGORIES} vazio="Selecione…" /></Fld>
          <Fld label="Subcategoria"><Sel value={f.sub} onChange={set("sub")} options={SUBCATEGORIES[f.cat] || []} vazio="Selecione…" /></Fld>
          <Fld label="Locais do negócio" dica="Em branco, o produto aparece em todos os locais.">
            <div className="pb-tags">
              {LOCATIONS.map((l) => (
                <button key={l.id} className="pb-tag" style={{ opacity: f.locs.includes(l.id) ? 1 : 0.4 }} onClick={() => toggleLoc(l.id)}>
                  {l.name}<span>{f.locs.includes(l.id) ? "✕" : "+"}</span>
                </button>
              ))}
            </div>
          </Fld>

          <div className="pb-fld">
            <label className="pb-chk"><input type="checkbox" checked={f.stockOn} onChange={(e) => set("stockOn")(e.target.checked)} />
              <span><b>Gerenciar estoque</b><small>Habilite pra controlar a quantidade. Serviços ficam sem estoque.</small></span></label>
          </div>
          <Fld label="Quantidade de alerta" dica="Abaixo dela o produto entra na lista de reposição." erro={err("alert")}>
            <input className="num" disabled={!f.stockOn} value={f.alert} onChange={(e) => set("alert")(e.target.value)} onBlur={() => setTocado((t) => ({ ...t, alert: 1 }))} placeholder="0" />
          </Fld>
          <Fld label="Garantia"><Sel value={f.warranty} onChange={set("warranty")} options={WARRANTIES} /></Fld>

          <Fld label="Descrição do produto" span={2}><textarea value={f.desc} onChange={(e) => set("desc")(e.target.value)} placeholder="O que é, como é produzido, o que está incluso." /></Fld>
          <div className="pb-fld">
            <span>Imagem e folheto</span>
            <div className="pb-img" style={{ aspectRatio: "auto", minHeight: 78 }}>Arraste a imagem (1:1, até 5 MB)<br /><small>+ folheto em PDF</small></div>
          </div>
        </div>
      </Widget>

      <Widget titulo="Configurações" nota="validade, série, prateleira, campos personalizados">
        <div className="pb-grid c4">
          <Fld label="Expira em">
            <div className="pb-inputgroup">
              <input className="num" value={f.expiry} onChange={(e) => set("expiry")(e.target.value)} placeholder="12" />
              <Sel value={f.expiryType} onChange={set("expiryType")} options={[{ id: "months", name: "meses" }, { id: "days", name: "dias" }, { id: "", name: "não aplicável" }]} />
            </div>
          </Fld>
          <div className="pb-fld"><label className="pb-chk"><input type="checkbox" checked={f.srNo} onChange={(e) => set("srNo")(e.target.checked)} /><span><b>Habilitar IMEI / nº de série</b><small>Cada peça é rastreada individualmente na venda.</small></span></label></div>
          <div className="pb-fld"><label className="pb-chk"><input type="checkbox" checked={f.notForSelling} onChange={(e) => set("notForSelling")(e.target.checked)} /><span><b>Não para venda</b><small>Insumo: entra em compra e produção, não no balcão.</small></span></label></div>
          <Fld label="Peso"><input value={f.weight} onChange={(e) => set("weight")(e.target.value)} placeholder="0,00" /></Fld>
        </div>

        <h4 style={{ margin: "16px 0 8px", fontSize: 11, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Detalhes de prateleira</h4>
        <table className="pb-tbl">
          <thead><tr><th>Local</th><th>Prateleira</th><th>Fileira</th><th>Posição</th></tr></thead>
          <tbody>
            {LOCATIONS.map((l) => {
              const r = f.racks[l.id] || {};
              const upd = (k) => (e) => setF((s) => ({ ...s, racks: { ...s.racks, [l.id]: { ...r, [k]: e.target.value } } }));
              return (
                <tr key={l.id}>
                  <td>{l.name}</td>
                  <td><input className="cell" value={r.rack || ""} onChange={upd("rack")} placeholder="Prateleira" /></td>
                  <td><input className="cell" value={r.row || ""} onChange={upd("row")} placeholder="Fileira" /></td>
                  <td><input className="cell" value={r.pos || ""} onChange={upd("pos")} placeholder="Posição" /></td>
                </tr>
              );
            })}
          </tbody>
        </table>

        <div className="pb-grid c4" style={{ marginTop: 14 }}>
          <Fld label="Campo personalizado 1"><input value={f.cf1} onChange={(e) => set("cf1")(e.target.value)} /></Fld>
          <Fld label="Campo personalizado 2"><input value={f.cf2} onChange={(e) => set("cf2")(e.target.value)} /></Fld>
          <Fld label="Campo personalizado 3"><input value={f.cf3} onChange={(e) => set("cf3")(e.target.value)} /></Fld>
          <Fld label="Campo personalizado 4"><input value={f.cf4} onChange={(e) => set("cf4")(e.target.value)} /></Fld>
          <Fld label="Campo personalizado 5"><input value={f.cf5} onChange={(e) => set("cf5")(e.target.value)} /></Fld>
          <Fld label="Campo personalizado 6"><input value={f.cf6} onChange={(e) => set("cf6")(e.target.value)} /></Fld>
          <Fld label="Campo personalizado 7"><input value={f.cf7} onChange={(e) => set("cf7")(e.target.value)} /></Fld>
          <Fld label="Tempo de preparo (minutos)"><input className="num" value={f.prep} onChange={(e) => set("prep")(e.target.value)} placeholder="0" /></Fld>
        </div>
      </Widget>

      <Widget titulo="Fiscal" nota="NCM · CEST · CFOP · origem">
        <div className="pb-grid c4">
          <Fld label="NCM" req={f.stockOn} dica="Nomenclatura Comum do Mercosul — manda na tributação da NF-e. Serviço não tem NCM." erro={err("ncm")}>
            <input value={f.ncm} onChange={(e) => set("ncm")(e.target.value)} onBlur={() => setTocado((t) => ({ ...t, ncm: 1 }))} placeholder="0000.00.00" />
          </Fld>
          <Fld label="CEST" dica="Só para mercadoria com substituição tributária."><input value={f.cest} onChange={(e) => set("cest")(e.target.value)} placeholder="0000000" /></Fld>
          <Fld label="CFOP padrão de venda"><Sel value={f.cfop} onChange={set("cfop")} options={CFOPS} /></Fld>
          <Fld label="Origem da mercadoria"><Sel value={f.origem} onChange={set("origem")} options={ORIGENS} /></Fld>
        </div>
        <p className="pb-help" style={{ marginTop: 8 }}>
          {f.cest ? "Com CEST preenchido a NF-e sai como substituído — confira o CFOP 5405." : "Sem substituição tributária. O módulo Fiscal valida NCM × CFOP antes de emitir."}
        </p>
      </Widget>

      <Widget titulo="Impostos e preços" nota={"imposto " + f.tax}>
        <div className="pb-grid">
          <Fld label="Imposto aplicável"><Sel value={f.tax} onChange={set("tax")} options={TAXES.map((t) => t.name)} /></Fld>
          <Fld label="Tipo de imposto no preço de venda" req><Sel value={f.taxType} onChange={set("taxType")} options={[{ id: "exclusive", name: "Exclusivo" }, { id: "inclusive", name: "Inclusivo" }]} /></Fld>
          <Fld label="Tipo de produto" req dica="Único, variável (grade) ou composição (kit).">
            <Sel value={f.type} onChange={set("type")} options={[{ id: "single", name: "Único" }, { id: "variable", name: "Variável" }, { id: "combo", name: "Composição" }]} />
          </Fld>
        </div>

        {f.type === "single" &&
          <table className="pb-tbl" style={{ marginTop: 12 }}>
            <thead><tr><th>Preço de compra padrão</th><th style={{ width: 130 }}>% de lucro</th><th>Preço de venda padrão</th><th style={{ width: 190 }}>Imagem da variação</th></tr></thead>
            <tbody>
              <tr>
                <td>
                  <div className="pb-grid c2" style={{ gap: 8 }}>
                    <Fld label="Sem imposto" req erro={err("dpp")}><input className="num" value={f.dpp} onChange={(e) => set("dpp")(e.target.value)} onBlur={() => setTocado((t) => ({ ...t, dpp: 1 }))} placeholder="0,00" /></Fld>
                    <Fld label="Com imposto" req><input className="num" readOnly value={dppInc ? dppInc.toFixed(2).replace(".", ",") : ""} /></Fld>
                  </div>
                </td>
                <td><input className="cell num" value={f.profit} onChange={(e) => set("profit")(e.target.value)} /></td>
                <td>
                  <div className="pb-grid c2" style={{ gap: 8 }}>
                    <Fld label="Sem imposto" req><input className="num" value={f.dsp || (dspCalc ? dspCalc.toFixed(2).replace(".", ",") : "")} onChange={(e) => set("dsp")(e.target.value)} placeholder="0,00" /></Fld>
                    <Fld label="Com imposto"><input className="num" readOnly value={dspInc ? dspInc.toFixed(2).replace(".", ",") : ""} /></Fld>
                  </div>
                </td>
                <td><div className="pb-img" style={{ aspectRatio: "auto", minHeight: 54, fontSize: 10.5 }}>Imagens da variação</div></td>
              </tr>
            </tbody>
          </table>}

        {f.type === "variable" && <>
          <div style={{ marginTop: 12 }}>
            <span className="pb-help" style={{ display: "block", marginBottom: 6 }}>Formato do SKU da variação</span>
            <div className="pb-radios">
              <label><input type="radio" checked={f.skuType === "with_out_variation"} onChange={() => set("skuType")("with_out_variation")} /> Número do SKU</label>
              <label><input type="radio" checked={f.skuType === "with_variation"} onChange={() => set("skuType")("with_variation")} /> SKU + número da variação</label>
            </div>
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 8, margin: "14px 0 8px" }}>
            <h4 style={{ margin: 0, fontSize: 11, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Variações</h4>
            <button className="os-btn sm" onClick={() => setF((s) => ({ ...s, variacoes: [{ ...s.variacoes[0], valores: [...s.variacoes[0].valores, { v: "", dpp: "", profit: "25", dsp: "" }] }] }))}><Ic name="plus" size={12} /> Adicionar valor</button>
          </div>
          <div className="pb-grid c2" style={{ marginBottom: 10 }}>
            <Fld label="Nome da variação" req erro={err("varNome")}><input value={f.variacoes[0].nome} onChange={(e) => setF((s) => ({ ...s, variacoes: [{ ...s.variacoes[0], nome: e.target.value }] }))} onBlur={() => setTocado((t) => ({ ...t, varNome: 1 }))} placeholder="Ex.: Acabamento, Cor, Tamanho" /></Fld>
          </div>
          {err("varValor") && <p className="pb-erro" style={{ margin: "0 0 8px" }}>{err("varValor")}</p>}
          <table className="pb-tbl">
            <thead><tr><th>Valor da variação</th><th>Sub-SKU</th><th className="r">Compra sem imposto</th><th className="r">Compra com imposto</th><th className="r">% lucro</th><th className="r">Venda sem imposto</th><th className="r">Venda com imposto</th><th style={{ width: 40 }} /></tr></thead>
            <tbody>
              {f.variacoes[0].valores.map((x, i) => {
                const dpp = num(x.dpp), venda = num(x.dsp) || dpp * (1 + num(x.profit) / 100);
                const novaLinha = (e) => {
                  if (e.key !== "Enter") return;
                  e.preventDefault();
                  setF((s) => ({ ...s, variacoes: [{ ...s.variacoes[0], valores: [...s.variacoes[0].valores, { v: "", dpp: "", profit: "25", dsp: "" }] }] }));
                };
                return (
                  <tr key={i}>
                    <td><input className="cell" value={x.v} onChange={(e) => setVal(i, "v", e.target.value)} onKeyDown={novaLinha} placeholder="Ex.: Bastão + ilhós" /></td>
                    <td className="m" style={{ color: "var(--text-dim)" }}>{subSku(i)}</td>
                    <td><input className="cell num" value={x.dpp} onChange={(e) => setVal(i, "dpp", e.target.value)} onKeyDown={novaLinha} placeholder="0,00" /></td>
                    <td className="r">{fmtBRL(dpp * (1 + rate / 100))}</td>
                    <td><input className="cell num" value={x.profit} onChange={(e) => setVal(i, "profit", e.target.value)} /></td>
                    <td><input className="cell num" value={x.dsp} onChange={(e) => setVal(i, "dsp", e.target.value)} placeholder={venda ? venda.toFixed(2) : "0,00"} /></td>
                    <td className="r">{fmtBRL(venda * (1 + rate / 100))}</td>
                    <td><button className="icon-btn" title="Remover valor" aria-label="Remover valor" onClick={() => setF((s) => ({ ...s, variacoes: [{ ...s.variacoes[0], valores: s.variacoes[0].valores.filter((_, j) => j !== i) }] }))}>✕</button></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          <p className="pb-help" style={{ marginTop: 6 }}>Enter em qualquer campo abre a linha seguinte — grade inteira sem tocar no mouse.</p>
        </>}

        {f.type === "combo" && <>
          <div style={{ display: "flex", gap: 8, alignItems: "center", margin: "14px 0 8px" }}>
            <div style={{ flex: "0 1 380px" }}>
              <Fld label="Buscar produto"><input placeholder="Digite pelo menos 2 letras…" onKeyDown={(e) => {
                if (e.key !== "Enter" || e.target.value.length < 2) return;
                const achado = D().PRODUCTS.find((p) => p.name.toLowerCase().includes(e.target.value.toLowerCase()));
                if (!achado) return avisar("Nenhum produto encontrado.", "warn");
                setF((s) => ({ ...s, combo: [...s.combo, { name: achado.name, sku: achado.sku, qty: 1, unit: achado.unit, dpp: achado.variations[0].dpp }] }));
                e.target.value = "";
              }} /></Fld>
            </div>
            <span className="pb-help">Enter adiciona o item à composição.</span>
          </div>
          <table className="pb-tbl">
            <thead><tr><th>Produto</th><th>SKU</th><th className="r">Qtd</th><th className="r">Preço de compra sem imposto</th><th className="r">Total sem imposto</th><th style={{ width: 40 }} /></tr></thead>
            <tbody>
              {f.combo.map((c, i) => (
                <tr key={i}>
                  <td><b>{c.name}</b></td><td className="m">{c.sku}</td>
                  <td><input className="cell num" value={c.qty} onChange={(e) => setF((s) => ({ ...s, combo: s.combo.map((x, j) => j === i ? { ...x, qty: num(e.target.value) } : x) }))} /></td>
                  <td className="r">{fmtBRL(c.dpp)}</td><td className="r">{fmtBRL(c.qty * c.dpp)}</td>
                  <td><button className="icon-btn" onClick={() => setF((s) => ({ ...s, combo: s.combo.filter((_, j) => j !== i) }))}>✕</button></td>
                </tr>
              ))}
              {f.combo.length === 0 && <tr><td colSpan={6} style={{ padding: 20, textAlign: "center", color: err("combo") ? "var(--neg)" : "var(--text-mute)" }}>{err("combo") || "Nenhum item na composição. Busque um produto acima."}</td></tr>}
            </tbody>
            <tfoot><tr><td colSpan={4}><b>Total líquido</b></td><td className="r"><b>{fmtBRL(comboTotal)}</b></td><td /></tr></tfoot>
          </table>
          <div className="pb-grid c2" style={{ marginTop: 12, maxWidth: 460 }}>
            <Fld label="% de lucro"><input className="num" value={f.comboProfit} onChange={(e) => set("comboProfit")(e.target.value)} /></Fld>
            <Fld label="Preço de venda padrão"><input className="num" readOnly value={comboVenda.toFixed(2).replace(".", ",")} /></Fld>
          </div>
        </>}
      </Widget>

      <div className="pb-formactions">
        {!edit && salvo && <span className="pb-help" style={{ marginRight: "auto" }}>Rascunho salvo automaticamente às {salvo}</span>}
        {Object.keys(erros).length > 0 && <span className="pb-erro" style={{ alignSelf: "center" }}>{Object.keys(erros).length} campo(s) a corrigir</span>}
        <button className="os-btn ghost" onClick={onSair}>Cancelar</button>
        <button className="os-btn" onClick={() => salvar("precos")}>Salvar e adicionar preços por grupo</button>
        <button className="os-btn" disabled={!f.stockOn} onClick={() => salvar("estoque")}>Salvar e adicionar estoque inicial</button>
        <button className="os-btn" onClick={() => salvar("outro")}>Salvar e adicionar outro</button>
        <button className="os-btn primary" onClick={() => salvar("salvar")}>Salvar</button>
      </div>
    </>
  );
}

// ─────────────────────────────── Histórico de estoque ───────────────────────────────
function Historico({ produto, avisar }) {
  const { PRODUCTS, LOCATIONS, fmtQty, stockHistory } = D();
  const { Widget, Fld, Sel } = U();
  const { PeriodBar, Progress } = DS();
  const meiaNoite = (d) => { const x = new Date(d); x.setHours(0, 0, 0, 0); return x; };
  const [periodo, setPeriodo] = useState(() => {
    const hoje = meiaNoite(new Date());
    return { preset: "mes", from: meiaNoite(new Date(hoje.getTime() - 29 * 86400000)), to: hoje };
  });
  const [pid, setPid] = useState(produto.id);
  const [loc, setLoc] = useState(1);
  const p = PRODUCTS.find((x) => x.id === Number(pid)) || produto;
  const [varSku, setVarSku] = useState(p.variations[0].sku);
  const todo = useMemo(() => stockHistory(p), [p]);
  const dataDaLinha = (s) => { const [d, m, y] = String(s).split(" ")[0].split("/"); return meiaNoite(new Date(+y, +m - 1, +d)); };
  const hist = useMemo(() => {
    const de = periodo.from ? meiaNoite(periodo.from) : null, ate = periodo.to ? meiaNoite(periodo.to) : null;
    if (!de && !ate) return todo;
    return todo.filter((h) => { const t = dataDaLinha(h.date).getTime(); return (!de || t >= de.getTime()) && (!ate || t <= ate.getTime()); });
  }, [todo, periodo]);
  const fmtDia = (d) => d ? d.toLocaleDateString("pt-BR") : "—";
  const va = p.variations.find((x) => x.sku === varSku) || p.variations[0];
  const entra = hist.filter((h) => h.change > 0).reduce((s, h) => s + h.change, 0);
  const sai = hist.filter((h) => h.change < 0).reduce((s, h) => s + h.change, 0);

  const linha = (label, val) => <tr><th style={{ textAlign: "left" }}>{label}</th><td className="r">{fmtQty(val)} {p.unit}</td></tr>;
  return (
    <>
      <Widget titulo={<><Ic name="clock" size={13} /> Histórico de estoque</>} nota={p.name}>
        <div className="pb-grid">
          <Fld label="Produto"><Sel value={pid} onChange={(v) => { setPid(v); const np = PRODUCTS.find((x) => x.id === Number(v)); setVarSku(np.variations[0].sku); }} options={PRODUCTS.map((x) => ({ id: x.id, name: x.name + " — " + x.sku }))} /></Fld>
          <Fld label="Local do negócio"><Sel value={loc} onChange={setLoc} options={LOCATIONS} /></Fld>
          <Fld label="Variação"><Sel value={varSku} onChange={setVarSku} options={p.variations.map((x) => ({ id: x.sku, name: x.name + " (" + x.sku + ")" }))} /></Fld>
        </div>
        {PeriodBar && <div style={{ marginTop: 12 }}><PeriodBar label="Período" value={periodo} onChange={setPeriodo} /></div>}
      </Widget>

      <Widget titulo={va.name + " · " + va.sku}
        nota={LOCATIONS.find((l) => l.id === Number(loc)).name + " · " + hist.length + " de " + todo.length + " movimentos · " + fmtDia(periodo.from) + " a " + fmtDia(periodo.to)}>
        {!p.stockOn
          ? <p className="pb-help">Este produto não gerencia estoque — nada a mostrar. Ligue <b>Gerenciar estoque</b> no cadastro pra passar a ter histórico.</p>
          : <>
            <div className="pb-grid">
              <div>
                <h4 style={{ margin: "0 0 6px", fontSize: 11, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Quantidades que entraram</h4>
                <table className="pb-tbl"><tbody>
                  {linha("Total de compras", Math.round(entra * 0.6))}
                  {linha("Estoque inicial", Math.round(entra * 0.25))}
                  {linha("Devolução de venda", Math.round(entra * 0.1))}
                  {linha("Transferências (entrada)", Math.round(entra * 0.05))}
                </tbody></table>
              </div>
              <div>
                <h4 style={{ margin: "0 0 6px", fontSize: 11, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Quantidades que saíram</h4>
                <table className="pb-tbl"><tbody>
                  {linha("Total vendido", Math.abs(Math.round(sai * 0.7)))}
                  {linha("Ajuste de estoque", Math.abs(Math.round(sai * 0.2)))}
                  {linha("Devolução de compra", Math.abs(Math.round(sai * 0.05)))}
                  {linha("Transferências (saída)", Math.abs(Math.round(sai * 0.05)))}
                </tbody></table>
              </div>
              <div>
                <h4 style={{ margin: "0 0 6px", fontSize: 11, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Totais</h4>
                <table className="pb-tbl"><tbody>{linha("Estoque atual", p.stock)}</tbody></table>
                {Progress && p.alert > 0 &&
                  <div style={{ marginTop: 10 }}>
                    <Progress variant="bar" value={Math.min(p.stock, p.alert * 3)} max={p.alert * 3}
                      tone={p.stock <= p.alert ? "danger" : p.stock <= p.alert * 1.5 ? "warn" : "success"}
                      label={"Cobertura sobre o alerta (" + fmtQty(p.alert) + " " + p.unit + ")"} showValue
                      formatValue={() => fmtQty(p.stock) + " " + p.unit} />
                  </div>}
                <button className="os-btn sm" style={{ marginTop: 8 }} onClick={() => avisar("Ajuste de estoque abre a tela de ajuste do módulo Estoque.")}>Lançar ajuste</button>
              </div>
            </div>

            <table className="pb-tbl" style={{ marginTop: 14 }}>
              <thead><tr><th>Tipo</th><th className="r">Variação da quantidade</th><th className="r">Nova quantidade</th><th>Data</th><th>Nº de referência</th><th>Cliente / fornecedor</th></tr></thead>
              <tbody>
                {hist.length === 0 &&
                  <tr><td colSpan={6} style={{ padding: 24, textAlign: "center", color: "var(--text-mute)" }}>Nenhum movimento entre {fmtDia(periodo.from)} e {fmtDia(periodo.to)}. Amplie o período acima.</td></tr>}
                {hist.map((h, i) => (
                  <tr key={i}>
                    <td>{h.type}</td>
                    <td className="r" style={{ color: h.change > 0 ? "var(--pos)" : "var(--neg)", fontWeight: 600 }}>{h.change > 0 ? "+" : ""}{fmtQty(h.change)}</td>
                    <td className="r">{fmtQty(h.stock)}</td>
                    <td className="m">{h.date}</td><td className="m">{h.ref}</td><td>{h.who}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </>}
      </Widget>
    </>
  );
}

// ─────────────────────────────── Preços por grupo de venda ───────────────────────────────
function Precos({ produto, onSair, avisar }) {
  const { PRICE_GROUPS, fmtBRL, incTax } = D();
  const { Widget } = U();
  const mult = [1, 0.9, 0.85, 0.75];
  const [tab, setTab] = useState(() => {
    const o = {};
    produto.variations.forEach((va) => { o[va.sku] = {}; PRICE_GROUPS.forEach((g, i) => { o[va.sku][g.id] = { price: (va.dsp * mult[i]).toFixed(2).replace(".", ","), type: "fixed" }; }); });
    return o;
  });
  const upd = (sku, gid, k, val) => setTab((s) => ({ ...s, [sku]: { ...s[sku], [gid]: { ...s[sku][gid], [k]: val } } }));
  return (
    <>
      <Widget titulo={<><Ic name="cash" size={13} /> Preços por grupo de venda</>} nota={produto.name + " (" + produto.sku + ")"}>
        <p className="pb-help" style={{ marginBottom: 10 }}>Valor <b>fixo</b> em reais ou <b>percentual</b> sobre o preço de venda padrão. Vazio ou zero = o grupo usa o preço padrão.</p>
        <table className="pb-tbl pb-precos">
          <thead>
            <tr>
              {produto.type === "variable" && <th>Variação</th>}
              <th className="r">Preço de venda padrão (com imposto)</th>
              {PRICE_GROUPS.map((g) => <th key={g.id} className="r">{g.name}</th>)}
            </tr>
          </thead>
          <tbody>
            {produto.variations.map((va) => (
              <tr key={va.sku}>
                {produto.type === "variable" && <td>{va.name}<small>{va.sku}</small></td>}
                <td className="r">{fmtBRL(incTax(va.dsp, produto.tax))}</td>
                {PRICE_GROUPS.map((g) => {
                  const cell = tab[va.sku][g.id];
                  return (
                    <td key={g.id}>
                      <input className="cell num" value={cell.price} onChange={(e) => upd(va.sku, g.id, "price", e.target.value)} />
                      <select className="cell" style={{ marginTop: 4 }} value={cell.type} onChange={(e) => upd(va.sku, g.id, "type", e.target.value)}>
                        <option value="fixed">Fixo</option>
                        <option value="percentage">Percentual</option>
                      </select>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </Widget>
      <div className="pb-formactions">
        <button className="os-btn ghost" onClick={onSair}>Cancelar</button>
        <button className="os-btn" disabled={!produto.stockOn} onClick={() => { avisar("Preços salvos — abrindo estoque inicial.", "ok"); onSair(); }}>Salvar e adicionar estoque inicial</button>
        <button className="os-btn" onClick={() => avisar("Preços salvos. Próximo produto.", "ok")}>Salvar e adicionar outro</button>
        <button className="os-btn primary" onClick={() => { avisar("Preços por grupo salvos.", "ok"); onSair(); }}>Salvar</button>
      </div>
    </>
  );
}

// ─────────────────────────────── Edição em massa ───────────────────────────────
function Massa({ onSair, avisar }) {
  const { PRODUCTS, CATEGORIES, SUBCATEGORIES, BRANDS, TAXES, LOCATIONS, fmtBRL, taxRate } = D();
  const { Widget, Sel } = U();
  const [linhas, setLinhas] = useState(() => PRODUCTS.slice(0, 4).map((p) => ({
    id: p.id, name: p.name, sku: p.sku, cat: p.cat, sub: p.sub, brand: p.brand, tax: p.tax, locs: p.locs,
    variations: p.variations.map((va) => ({ ...va, dpp: String(va.dpp), profit: String(va.profit), dsp: String(va.dsp) })),
  })));
  const num = (x) => parseFloat(String(x).replace(",", ".")) || 0;
  const setL = (id, k, val) => setLinhas((s) => s.map((l) => l.id === id ? { ...l, [k]: val } : l));
  const setV = (id, sku, k, val) => setLinhas((s) => s.map((l) => l.id !== id ? l : { ...l, variations: l.variations.map((va) => va.sku === sku ? { ...va, [k]: val } : va) }));

  return (
    <>
      <Widget titulo={<><Ic name="pencil" size={13} /> Edição em massa</>} nota={linhas.length + " produtos"}>
        <p className="pb-help" style={{ marginBottom: 10 }}>Categoria, subcategoria, marca, imposto e locais mudam por produto; preços por variação. Busque outro produto pra somar à lista.</p>
        <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 12 }}>
          <input placeholder="Buscar produto pra editar…" style={{ font: "inherit", fontSize: 12.5, height: 30, padding: "0 9px", border: "1px solid var(--border)", borderRadius: 6, background: "var(--surface)", color: "var(--text)", width: 320 }}
            onKeyDown={(e) => {
              if (e.key !== "Enter" || e.target.value.length < 2) return;
              const p = PRODUCTS.find((x) => x.name.toLowerCase().includes(e.target.value.toLowerCase()) && !linhas.some((l) => l.id === x.id));
              if (!p) return avisar("Nenhum produto novo encontrado.", "warn");
              setLinhas((s) => [...s, { id: p.id, name: p.name, sku: p.sku, cat: p.cat, sub: p.sub, brand: p.brand, tax: p.tax, locs: p.locs, variations: p.variations.map((va) => ({ ...va, dpp: String(va.dpp), profit: String(va.profit), dsp: String(va.dsp) })) }]);
              e.target.value = "";
            }} />
          <span className="pb-help">Enter adiciona.</span>
        </div>

        {linhas.map((l) => (
          <div key={l.id} style={{ border: "1px solid var(--border)", borderRadius: 10, marginBottom: 12, overflowX: "auto" }}>
            <table className="pb-tbl">
              <thead><tr><th>Produto</th><th>Categoria</th><th>Subcategoria</th><th>Marca</th><th>Imposto</th><th>Locais</th></tr></thead>
              <tbody>
                <tr>
                  <td><b>{l.name}</b><small>{l.sku}</small></td>
                  <td><select className="cell" value={l.cat} onChange={(e) => setL(l.id, "cat", e.target.value)}>{CATEGORIES.map((c) => <option key={c}>{c}</option>)}</select></td>
                  <td><select className="cell" value={l.sub} onChange={(e) => setL(l.id, "sub", e.target.value)}>{(SUBCATEGORIES[l.cat] || []).map((c) => <option key={c}>{c}</option>)}</select></td>
                  <td><select className="cell" value={l.brand} onChange={(e) => setL(l.id, "brand", e.target.value)}>{BRANDS.map((c) => <option key={c}>{c}</option>)}</select></td>
                  <td><select className="cell" value={l.tax} onChange={(e) => setL(l.id, "tax", e.target.value)}>{TAXES.map((t) => <option key={t.id}>{t.name}</option>)}</select></td>
                  <td>{LOCATIONS.map((lo) => (
                    <label key={lo.id} className="pb-chk" style={{ fontSize: 11.5 }}>
                      <input type="checkbox" checked={l.locs.includes(lo.id)} onChange={(e) => setL(l.id, "locs", e.target.checked ? [...l.locs, lo.id] : l.locs.filter((x) => x !== lo.id))} />{lo.name}
                    </label>))}
                  </td>
                </tr>
              </tbody>
            </table>
            <table className="pb-tbl">
              <thead><tr><th>Variação</th><th className="r">Compra sem imposto</th><th className="r">Compra com imposto</th><th className="r">% lucro</th><th className="r">Venda sem imposto</th><th className="r">Venda com imposto</th></tr></thead>
              <tbody>
                {l.variations.map((va) => {
                  const rate = taxRate(l.tax), dpp = num(va.dpp), venda = num(va.dsp) || dpp * (1 + num(va.profit) / 100);
                  return (
                    <tr key={va.sku}>
                      <td>{va.name}<small>{va.sku}</small></td>
                      <td><input className="cell num" value={va.dpp} onChange={(e) => setV(l.id, va.sku, "dpp", e.target.value)} /></td>
                      <td className="r">{fmtBRL(dpp * (1 + rate / 100))}</td>
                      <td><input className="cell num" value={va.profit} onChange={(e) => setV(l.id, va.sku, "profit", e.target.value)} /></td>
                      <td><input className="cell num" value={va.dsp} onChange={(e) => setV(l.id, va.sku, "dsp", e.target.value)} /></td>
                      <td className="r">{fmtBRL(venda * (1 + rate / 100))}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ))}
      </Widget>
      <div className="pb-formactions">
        <button className="os-btn ghost" onClick={onSair}>Cancelar</button>
        <button className="os-btn primary" onClick={() => { avisar(linhas.length + " produtos atualizados.", "ok"); onSair(); }}>Atualizar</button>
      </div>
    </>
  );
}

window.ProdutoBladeForms = { FormProduto, Historico, Precos, Massa };
})();
