// produto-acoes.jsx — telas de ação do menu Produtos, importadas dos blades:
//   labels/show.blade.php (+ partials/show_table_rows) ....... "Imprimir etiquetas"
//   import_products/index.blade.php .......................... "Importar produtos"
//   import_opening_stock/index.blade.php ..................... "Importar estoque inicial"
//   selling_price_group/update_product_price.blade.php ....... "Atualizar preço"
// Tudo em componentes do DS vivo: Button · Input · Select · Checkbox · Switch · DataTable ·
// DataTablePro (instruções) · Pagination (folhas) · Alert · EmptyState · Skeleton ·
// Dimension · ProofFrame · ProofStrip.
// 🔴 Nenhuma destas rotas tem permissão no legado (achado A-P1) — a tela nomeia isso.
// Expõe window.ProdutoAcoes. Depende de window.PBD e window.PBUI.
(() => {
const { useState, useMemo } = React;
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const D = () => window.PBD || {};
const U = () => window.PBUI || {};

function EstadoTela({ estado, o }) {
  const { Widget } = U();
  const { Skeleton, EmptyState, Button } = DS();
  return (
    <Widget titulo={o}>
      {estado === "carregando"
        ? <Skeleton variant="card" count={2} />
        : <EmptyState variant="error" title="Não foi possível abrir a tela"
          description="O servidor recusou a leitura do catálogo. Nada foi enviado nem alterado — recarregue; se persistir, é permissão do papel."
          action={<Button onClick={() => window.location.reload()}>Recarregar</Button>} />}
    </Widget>
  );
}
// Aviso do buraco de permissão do legado, na tela onde ele acontece.
function SemGate({ controller }) {
  const { Alert } = DS();
  if (!Alert) return null;
  return <div style={{ marginBottom: 12 }}><Alert tone="warn" title="Tela sem permissão no legado (achado A-P1)">Qualquer usuário logado com acesso ao menu executa esta ação — o <b>{controller}</b> não tem <code>can()</code> e a rota só passa pela autenticação. Precisa de decisão de [W] antes do F3.</Alert></div>;
}

// ─────────── Leitura da planilha no navegador ───────────
const separar = (linha) => {
  const sep = (linha.match(/;/g) || []).length > (linha.match(/,/g) || []).length ? ";" : ",";
  return linha.split(sep).map((c) => c.trim().replace(/^"|"$/g, ""));
};
function lerPlanilha(file, colunas, onPronto) {
  const nome = file.name;
  if (/\.xlsx?$/i.test(nome)) { onPronto({ nome, binario: true, linhas: [], total: null, erros: [], cabecalho: [] }); return; }
  const fr = new FileReader();
  fr.onload = () => {
    const bruto = String(fr.result || "").split(/\r?\n/).filter((l) => l.trim().length);
    const cabecalho = bruto.length ? separar(bruto[0]) : [];
    const corpo = bruto.slice(1).map(separar);
    const erros = [];
    corpo.forEach((c, i) => {
      if (c.length !== colunas.length) erros.push({ linha: i + 2, msg: c.length + " coluna(s) — o modelo tem " + colunas.length });
      colunas.forEach((col, k) => { if (col[1] === "obrigatório" && !(c[k] || "").trim()) erros.push({ linha: i + 2, msg: "“" + col[0] + "” em branco" }); });
    });
    onPronto({ nome, binario: false, cabecalho, linhas: corpo.slice(0, 5), total: corpo.length, erros: erros.slice(0, 8), totalErros: erros.length });
  };
  fr.onerror = () => onPronto({ nome, erro: true, linhas: [], total: null, erros: [] });
  fr.readAsText(file);
}

// Instruções em DataTablePro: header fixo, coluna redimensionável, densidade compacta.
function TabelaInstrucoes({ linhas }) {
  const { DataTablePro } = DS();
  const rows = linhas.map((l, i) => ({ id: i + 1, cells: { n: i + 1, col: { primary: l[0], sub: l[1] }, ins: l[2] || "—" } }));
  return <DataTablePro height={360} density="compact"
    columns={[{ key: "n", label: "Nº", align: "right", width: 60, mono: true }, { key: "col", label: "Coluna", width: 280, sortable: true }, { key: "ins", label: "O que colocar" }]}
    rows={rows} />;
}

function TelaImportar({ titulo, glyph, aceita, modelo, cabecalho, linhas, avisar, estado, controller }) {
  const { Widget } = U();
  const { Alert, Button, Input, DataTable } = DS();
  const [lido, setLido] = useState(null);
  // O <input type="file"> fica FORA do label e é disparado por ref: label com <button> dentro
  // engole o clique (o label só encaminha quando o alvo não é interativo).
  const arquivoRef = React.useRef(null);
  if (estado === "carregando" || estado === "erro") return <EstadoTela estado={estado} o={titulo} />;
  const podeEnviar = lido && !lido.erro && (lido.binario || (lido.totalErros || 0) === 0);
  return (
    <>
      <Widget titulo={<>{glyph} {titulo}</>} nota={aceita}>
        <SemGate controller={controller} />
        <Alert tone="warn" title="A planilha grava direto no catálogo">Confira o modelo antes de enviar: uma coluna fora de ordem cria produto errado em lote. Erro em qualquer linha cancela a importação inteira — nada entra pela metade.</Alert>
        <div style={{ marginTop: 12, display: "flex", gap: 12, alignItems: "flex-end", flexWrap: "wrap" }}>
          <div style={{ display: "flex", gap: 8, alignItems: "flex-end", flex: "1 1 420px", minWidth: 320 }}>
            <Button onClick={() => arquivoRef.current?.click()}><Ic name="upload" size={13} /> Escolher arquivo</Button>
            <input ref={arquivoRef} type="file" accept={aceita} style={{ display: "none" }} onChange={(e) => { const f = e.target.files?.[0]; if (f) lerPlanilha(f, linhas, setLido); }} />
            <div style={{ flex: 1, minWidth: 200 }}><Input label="Arquivo para importar *" readOnly value={lido ? lido.nome : "Nenhum arquivo escolhido"} help={"Formatos aceitos: " + aceita} /></div>
          </div>
          <div style={{ display: "flex", gap: 8, whiteSpace: "nowrap", paddingBottom: 18 }}>
            <Button variant="primary" disabled={!podeEnviar} onClick={() => avisar("“" + lido.nome + "” enviado" + (lido.total ? " — " + lido.total + " linha(s)" : "") + ". O protótipo não grava.", "ok")}>Enviar planilha</Button>
            <Button onClick={() => avisar("Modelo “" + modelo + "” baixado.", "ok")}><Ic name="sheet" size={13} /> Baixar modelo</Button>
          </div>
        </div>
      </Widget>

      {lido &&
        <Widget titulo={<><Ic name="sheet" size={13} /> Conferência do arquivo</>}
          nota={lido.binario ? "planilha binária" : (lido.total + " linha(s) · " + (lido.totalErros || 0) + " erro(s)")}>
          {lido.erro && <Alert tone="danger" title="Arquivo ilegível">Não foi possível ler o arquivo no navegador. Envie e o servidor valida.</Alert>}
          {lido.binario &&
            <Alert tone="info" title=".xls/.xlsx é conferido no servidor">A checagem linha a linha acontece depois do envio. Pra conferir aqui antes, salve como <b>.csv</b>.</Alert>}
          {!lido.binario && !lido.erro && (
            (lido.totalErros || 0) > 0
              ? <>
                <Alert tone="danger" title={lido.totalErros + " problema(s) — o servidor vai recusar a planilha inteira"}>Corrija no arquivo e escolha de novo. Mostrando os primeiros {lido.erros.length}.</Alert>
                <div style={{ marginTop: 10 }}>
                  <DataTable columns={[{ key: "linha", label: "Linha", align: "right", width: 90 }, { key: "msg", label: "Problema" }]}
                    rows={lido.erros.map((e, i) => ({ id: i, state: "urgent", cells: { linha: e.linha, msg: e.msg } }))} />
                </div>
              </>
              : <>
                <Alert tone="success" title={lido.total + " linha(s) sem problema aparente"}>Conferência do navegador: contagem de colunas e obrigatórios. O servidor ainda valida unidade, imposto e local existentes.</Alert>
                <div style={{ marginTop: 10, overflowX: "auto" }}>
                  <DataTable columns={[{ key: "n", label: "Linha", align: "right", width: 70 }].concat((lido.cabecalho || []).slice(0, 6).map((c, i) => ({ key: "c" + i, label: c || (linhas[i] && linhas[i][0]) || "col " + (i + 1) })))}
                    rows={lido.linhas.map((l, i) => { const cells = { n: i + 2 }; l.slice(0, 6).forEach((c, k) => { cells["c" + k] = c || "—"; }); return { id: i, cells }; })} />
                </div>
                <p className="pb-help" style={{ marginTop: 8 }}>Primeiras {lido.linhas.length} linhas, 6 primeiras colunas.</p>
              </>
          )}
        </Widget>}

      <Widget titulo={<><Ic name="list" size={13} /> Instruções</>} nota={linhas.length + " colunas"}>
        <p className="pb-help" style={{ marginBottom: 10 }}><b>{cabecalho}</b><br />A primeira linha da planilha é cabeçalho e é ignorada. Mantenha a ordem das colunas do modelo — arraste a borda do cabeçalho pra alargar a coluna.</p>
        <TabelaInstrucoes linhas={linhas} />
      </Widget>
    </>
  );
}

const REQ = "obrigatório", OPC = "opcional";
const COLS_PRODUTO = [
  ["Nome do produto", REQ, "Nome como aparece no balcão e na nota."],
  ["Marca", OPC, "Se a marca não existir, é criada na hora."],
  ["Unidade", REQ, "Precisa já existir em Cadastros de apoio → Unidades."],
  ["Categoria", OPC, "Se não existir, é criada."],
  ["Subcategoria", OPC, "Só entra se a categoria pai estiver preenchida."],
  ["SKU", OPC, "Em branco, o sistema gera a partir do código da categoria."],
  ["Tipo de código de barras", OPC + ", padrão C128", "C128, C39, EAN-13, EAN-8, UPC-A, UPC-E, ITF-14."],
  ["Gerenciar estoque", REQ, "1 = sim · 0 = não."],
  ["Quantidade de alerta", OPC, "Abaixo disso o produto aparece em Análises → Reposição."],
  ["Vence em", OPC, "Número de dias ou meses de validade."],
  ["Unidade do prazo de validade", OPC, "days ou months."],
  ["Imposto aplicável", OPC, "Nome exato do imposto cadastrado (ex.: ICMS 18%)."],
  ["Tipo de imposto na venda", REQ, "inclusive (preço já com imposto) ou exclusive."],
  ["Tipo de produto", REQ, "single ou variable."],
  ["Nome da variação", "obrigatório se variable", "Ex.: Cor, Acabamento."],
  ["Valores da variação", "obrigatório se variable", "Separados por | — ex.: Branco|Preto|Azul."],
  ["SKU da variação", OPC, "Um por valor, na mesma ordem, separados por |."],
  ["Preço de compra com imposto", "um dos dois é obrigatório", "Custo unitário já com imposto."],
  ["Preço de compra sem imposto", "um dos dois é obrigatório", "Custo unitário antes do imposto."],
  ["Margem de lucro (%)", OPC, "Em branco, usa a margem padrão do negócio."],
  ["Preço de venda", OPC, "Em branco, é calculado pela margem."],
  ["Estoque inicial", OPC, "Só vale se Gerenciar estoque = 1."],
  ["Local do estoque inicial", OPC, "Nome do local (Matriz, Filial Centro). Em branco = todos."],
  ["Data de validade", OPC, "dd/mm/aaaa, no formato de data do negócio."],
  ["Controla IMEI / nº de série", OPC + ", padrão 0", "1 = sim · 0 = não."],
  ["Peso", OPC, "Usado no frete e no romaneio de entrega."],
  ["Prateleira", OPC, "Localização física no estoque."],
  ["Fileira", OPC, "Localização física no estoque."],
  ["Posição", OPC, "Localização física no estoque."],
  ["Imagem", OPC, "Nome do arquivo já enviado ou URL pública."],
  ["Descrição do produto", OPC, "Texto livre — sai na proposta."],
  ["Campo personalizado 1", OPC, "Campo livre do negócio."],
  ["Campo personalizado 2", OPC, "Campo livre do negócio."],
  ["Campo personalizado 3", OPC, "Campo livre do negócio."],
  ["Campo personalizado 4", OPC, "Campo livre do negócio."],
  ["Não para venda", OPC, "1 = insumo, não aparece no PDV · 0 = vende."],
  ["Locais do produto", OPC, "Nomes separados por | — em branco entra em todos."],
];
const COLS_ESTOQUE = [
  ["SKU", REQ, "Precisa existir no catálogo — é a chave da linha."],
  ["Local do negócio", OPC, "Nome do local. Em branco, entra no local padrão."],
  ["Quantidade", REQ, "Só numeral, sem unidade."],
  ["Custo unitário antes do imposto", REQ, "Base do custo médio e da margem."],
  ["Lote", OPC, "Se o negócio usa controle de lote."],
  ["Data de validade", OPC, "dd/mm/aaaa — ex.: " + new Date().toLocaleDateString("pt-BR") + "."],
];

function ImportarProdutos({ avisar, estado }) {
  return <TelaImportar titulo="Importar produtos" glyph={<Ic name="upload" size={13} />} aceita=".xls, .xlsx, .csv"
    modelo="modelo_importar_produtos.xls" cabecalho="Uma linha por produto — produto variável repete o nome com a variação na mesma linha."
    linhas={COLS_PRODUTO} avisar={avisar} estado={estado} controller="ImportProductsController" />;
}
function ImportarEstoque({ avisar, estado }) {
  return <TelaImportar titulo="Importar estoque inicial" glyph={<Ic name="archive" size={13} />} aceita=".xls, .csv"
    modelo="modelo_estoque_inicial.xls" cabecalho="Uma linha por SKU e local — o lançamento entra como “Estoque inicial” no histórico."
    linhas={COLS_ESTOQUE} avisar={avisar} estado={estado} controller="ImportOpeningStockController" />;
}

// ─────────── Atualizar preço ───────────
function AtualizarPreco({ avisar, onIr, estado }) {
  const { Widget } = U();
  const { PRICE_GROUPS, PRODUCTS } = D();
  const { Alert, Button, TagChip } = DS();
  const [lido, setLido] = useState(null);
  const planilhaRef = React.useRef(null);
  if (estado === "carregando" || estado === "erro") return <EstadoTela estado={estado} o="Atualizar preço" />;
  const grupos = PRICE_GROUPS || [];
  const COLS = [["SKU", "obrigatório", ""], ["Variação", "opcional", ""]].concat(grupos.map((g) => [g.name, "opcional", ""]));
  return (
    <>
      <Widget titulo={<><Ic name="cash" size={13} /> Atualizar preço por planilha</>} nota={(PRODUCTS || []).length + " produtos · " + grupos.length + " grupos"}>
        <SemGate controller="SellingPriceGroupController" />
        <Alert tone="info" title="Exporte, edite, devolva">A planilha sai com uma coluna por grupo de preço ativo. Não mexa nas colunas de SKU e variação — são elas que casam a linha com o produto.</Alert>
        <div className="pb-grid c2" style={{ marginTop: 12, alignItems: "start" }}>
          <div>
            <span style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Passo 1 — exportar</span>
            <div style={{ marginTop: 6 }}>
              <Button onClick={() => avisar("Planilha de preços exportada com " + grupos.length + " grupo(s).", "ok")}><Ic name="sheet" size={13} /> Exportar preços atuais</Button>
            </div>
          </div>
          <div>
            <span style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Passo 2 — devolver</span>
            <div style={{ marginTop: 6, display: "flex", gap: 8, whiteSpace: "nowrap" }}>
              <Button onClick={() => planilhaRef.current?.click()}><Ic name="upload" size={13} /> Escolher planilha</Button>
              <input ref={planilhaRef} type="file" accept=".xls,.xlsx,.csv" style={{ display: "none" }} onChange={(e) => { const f = e.target.files?.[0]; if (f) lerPlanilha(f, COLS, setLido); }} />
              <Button variant="primary" disabled={!lido || (lido.totalErros || 0) > 0} onClick={() => avisar("“" + lido.nome + "” aplicado nos grupos de preço — o protótipo não grava.", "ok")}>Aplicar preços</Button>
            </div>
            <p className="pb-help" style={{ marginTop: 6 }}>{lido ? lido.nome + (lido.binario ? " · conferido no servidor" : " · " + lido.total + " linha(s), " + (lido.totalErros || 0) + " erro(s)") : "Nenhum arquivo escolhido"}</p>
          </div>
        </div>
      </Widget>

      <Widget titulo={<><Ic name="list" size={13} /> Instruções</>} nota="4 regras">
        <ol className="pb-ol">
          <li>Exporte primeiro. A planilha já vem com todos os produtos, variações e os grupos de preço ativos — não monte uma planilha do zero.</li>
          <li>Altere só as colunas de preço. Mexer em SKU, nome ou variação faz a linha ser recusada.</li>
          <li>Valor em branco significa “usa o preço de venda padrão”. Zero significa preço zero — não é a mesma coisa.</li>
          <li>Grupo desativado não sai na exportação e não é atualizado. Ative em <b>Cadastros de apoio → Grupos de preço</b> antes.</li>
        </ol>
        <div style={{ marginTop: 12, display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
          {grupos.map((g) => TagChip ? <TagChip key={g.id} label={g.name.toLowerCase()} /> : <span className="pb-tag" key={g.id}>{g.name}</span>)}
          <Button size="sm" onClick={() => onIr("cadastros")}>Gerenciar grupos</Button>
          <Button size="sm" onClick={() => onIr("precos")}>Editar preço na tela</Button>
        </div>
      </Widget>
    </>
  );
}

// ─────────── Imprimir etiquetas ───────────
const CAMPOS_ETIQUETA = [
  { id: "name", l: "Nome do produto", size: 15 },
  { id: "variations", l: "Variação", size: 17 },
  { id: "price", l: "Preço de venda", size: 17 },
  { id: "business_name", l: "Nome do negócio", size: 20 },
  { id: "packing_date", l: "Data de embalagem", size: 12 },
  { id: "lot_number", l: "Lote", size: 12 },
  { id: "exp_date", l: "Validade", size: 12 },
];
const MODELOS_ETIQUETA = [
  { id: 1, name: "20 por folha — 38,1 × 21,2 mm", cols: 4, porFolha: 20, w: "38,1 mm", h: "21,2 mm" },
  { id: 2, name: "30 por folha — 25,4 × 66,7 mm", cols: 5, porFolha: 30, w: "25,4 mm", h: "66,7 mm" },
  { id: 3, name: "Rolo térmico — 60 × 30 mm", cols: 2, porFolha: 12, w: "60 mm", h: "30 mm" },
];

function Etiquetas({ avisar, produtosIniciais, estado }) {
  const { Widget } = U();
  const { PRODUCTS, PRICE_GROUPS, fmtBRL, incTax } = D();
  const { ProofFrame, ProofStrip, Dimension, EmptyState, Button, Input, Select, Checkbox, DataTable, Pagination } = DS();
  const [busca, setBusca] = useState("");
  const [itens, setItens] = useState(() => (produtosIniciais && produtosIniciais.length ? produtosIniciais : PRODUCTS.slice(0, 2)).map((p) => ({ id: p.id, qtd: "8", lote: "", validade: "", embalagem: "", grupo: PRICE_GROUPS[0].id })));
  const [campos, setCampos] = useState(() => { const o = {}; CAMPOS_ETIQUETA.forEach((c) => { o[c.id] = { on: ["name", "variations", "price", "business_name"].includes(c.id), size: String(c.size) }; }); return o; });
  const [tipoPreco, setTipoPreco] = useState("inclusive");
  const [modelo, setModelo] = useState(1);
  const [folhaAtual, setFolhaAtual] = useState(1);

  if (estado === "carregando" || estado === "erro") return <EstadoTela estado={estado} o="Imprimir etiquetas" />;

  const achados = useMemo(() => busca.trim().length < 2 ? [] : PRODUCTS.filter((p) => (p.name + " " + p.sku).toLowerCase().includes(busca.toLowerCase())).slice(0, 6), [busca]);
  const prod = (id) => PRODUCTS.find((p) => p.id === id);
  const setItem = (i, k, v) => setItens((s) => s.map((x, k2) => k2 === i ? { ...x, [k]: v } : x));
  const total = itens.reduce((s, it) => s + (parseInt(it.qtd, 10) || 0), 0);
  const M = MODELOS_ETIQUETA.find((m) => m.id === Number(modelo)) || MODELOS_ETIQUETA[0];
  const addItem = (p) => setItens((s) => s.some((x) => x.id === p.id) ? s : [...s, { id: p.id, qtd: "8", lote: "", validade: "", embalagem: "", grupo: PRICE_GROUPS[0].id }]);

  const folha = [];
  itens.forEach((it) => {
    const p = prod(it.id);
    if (!p) return;
    const n = Math.min(parseInt(it.qtd, 10) || 0, 200);
    for (let i = 0; i < n; i++) folha.push({ p, it, i });
  });
  const folhas = Math.max(1, Math.ceil(folha.length / M.porFolha));
  const pagina = Math.min(folhaAtual, folhas);
  const naFolha = folha.slice((pagina - 1) * M.porFolha, (pagina - 1) * M.porFolha + M.porFolha);

  const linhas = itens.map((it, i) => {
    const p = prod(it.id);
    if (!p) return null;
    return {
      id: it.id,
      cells: {
        produto: { primary: p.name, sub: p.sku + " · " + p.barcode },
        qtd: <Input value={it.qtd} onChange={(e) => setItem(i, "qtd", e.target.value)} />,
        lote: <Input value={it.lote} placeholder="—" onChange={(e) => setItem(i, "lote", e.target.value)} />,
        validade: <Input value={it.validade} placeholder="dd/mm/aaaa" onChange={(e) => setItem(i, "validade", e.target.value)} />,
        embalagem: <Input value={it.embalagem} placeholder="dd/mm/aaaa" onChange={(e) => setItem(i, "embalagem", e.target.value)} />,
        grupo: <Select value={String(it.grupo)} onChange={(e) => setItem(i, "grupo", Number(e.target.value))} options={PRICE_GROUPS.map((g) => ({ value: String(g.id), label: g.name }))} />,
        remover: <Button size="sm" onClick={() => setItens((s) => s.filter((_, k) => k !== i))}>✕</Button>,
      },
    };
  }).filter(Boolean);

  return (
    <>
      <Widget titulo={<><Ic name="print" size={13} /> Produtos para etiquetar</>} nota={total + " etiqueta(s) · " + folhas + " folha(s)"}>
        <SemGate controller="LabelsController" />
        <Input label="Buscar produto" help="Nome ou SKU — o produto entra na lista abaixo." value={busca}
          placeholder="Digite o nome do produto para imprimir etiquetas" onChange={(e) => setBusca(e.target.value)} />
        {achados.length > 0 &&
          <div style={{ display: "flex", flexWrap: "wrap", gap: 6, marginTop: 8 }}>
            {achados.map((p) => (
              <Button size="sm" key={p.id} onClick={() => { addItem(p); setBusca(""); }}><Ic name="plus" size={12} /> {p.name}</Button>
            ))}
          </div>}
        {itens.length === 0
          ? <div style={{ marginTop: 12 }}><EmptyState variant="first" title="Nenhum produto na folha" description="Busque acima pelo nome ou SKU. Da lista de produtos você também manda vários de uma vez: selecione e clique em Etiquetas." /></div>
          : <div style={{ overflowX: "auto", marginTop: 12 }}>
            <div style={{ minWidth: 1000 }}>
              <DataTable rows={linhas}
                columns={[{ key: "produto", label: "Produto", width: 240 }, { key: "qtd", label: "Nº de etiquetas", width: 120 }, { key: "lote", label: "Lote", width: 130 }, { key: "validade", label: "Validade", width: 150 }, { key: "embalagem", label: "Data de embalagem", width: 160 }, { key: "grupo", label: "Grupo de preço", width: 170 }, { key: "remover", label: "", width: 50 }]} />
            </div>
          </div>}
      </Widget>

      <Widget titulo={<><Ic name="grid" size={13} /> Informações na etiqueta</>} nota={CAMPOS_ETIQUETA.filter((c) => campos[c.id].on).length + " de " + CAMPOS_ETIQUETA.length}>
        <div className="pb-grid c4">
          {CAMPOS_ETIQUETA.map((c) => (
            <div key={c.id} style={{ display: "flex", flexDirection: "column", gap: 6 }}>
              <Checkbox checked={campos[c.id].on} label={c.l} onChange={(v) => setCampos({ ...campos, [c.id]: { ...campos[c.id], on: v } })} />
              <Input label="Corpo (pt)" value={campos[c.id].size} disabled={!campos[c.id].on}
                onChange={(e) => setCampos({ ...campos, [c.id]: { ...campos[c.id], size: e.target.value } })} />
            </div>
          ))}
        </div>
        <div className="pb-grid c2" style={{ marginTop: 14 }}>
          <Select label="Preço a imprimir" help="Como o preço sai na etiqueta do balcão." value={tipoPreco} onChange={(e) => setTipoPreco(e.target.value)}
            options={[{ value: "inclusive", label: "Com imposto" }, { value: "exclusive", label: "Sem imposto" }]} />
          <Select label="Modelo de etiqueta" value={String(modelo)} onChange={(e) => { setModelo(Number(e.target.value)); setFolhaAtual(1); }}
            options={MODELOS_ETIQUETA.map((m) => ({ value: String(m.id), label: m.name }))} />
        </div>
        <div style={{ marginTop: 14 }}>
          <Button variant="primary" disabled={!folha.length} onClick={() => { avisar(total + " etiqueta(s) em " + folhas + " folha(s) enviadas para a impressora.", "ok"); window.print(); }}>
            <Ic name="print" size={13} /> Imprimir {folhas} folha(s)
          </Button>
        </div>
      </Widget>

      <Widget titulo={<><Ic name="print" size={13} /> Prévia da folha</>} nota={M.w + " × " + M.h + " · folha " + pagina + " de " + folhas}>
        {folha.length === 0
          ? <p className="pb-help">Sem produtos na lista — nada a imprimir.</p>
          : <>
            {Dimension && <div style={{ marginBottom: 10, maxWidth: 260 }}><Dimension value={M.w} orientation="h" /></div>}
            {(() => {
              const grade = (
                <>
                  <div className="pb-etiquetas" style={{ gridTemplateColumns: "repeat(" + M.cols + ", minmax(0,1fr))" }}>
                    {naFolha.map(({ p, it, i }) => (
                      <div className="pb-etiqueta" key={p.id + "-" + i}>
                        {campos.business_name.on && <span className="m" style={{ fontSize: 9, letterSpacing: ".08em", textTransform: "uppercase" }}>Office Impresso</span>}
                        {campos.name.on && <b>{p.name}</b>}
                        {campos.variations.on && <span className="m">{p.variations[0].name}</span>}
                        <div className="pb-barras" aria-hidden="true">{Array.from({ length: 34 }, (_, k) => <i key={k} style={{ width: (k * 7 + p.id) % 3 === 0 ? 3 : 1 }} />)}</div>
                        <span className="m">{p.sku} · {p.barcode}</span>
                        {campos.lot_number.on && it.lote && <span className="m">Lote {it.lote}</span>}
                        {campos.packing_date.on && it.embalagem && <span className="m">Emb. {it.embalagem}</span>}
                        {campos.exp_date.on && it.validade && <span className="m">Val. {it.validade}</span>}
                        {campos.price.on && <strong>{tipoPreco === "inclusive" ? fmtBRL(incTax(p.variations[0].dsp, p.tax)) : fmtBRL(p.variations[0].dsp)}</strong>}
                      </div>
                    ))}
                  </div>
                  {ProofStrip && <div style={{ marginTop: 12 }}><ProofStrip kind="cmyk" height={10} /></div>}
                </>
              );
              return ProofFrame ? <ProofFrame cropMarks grid padding={16}>{grade}</ProofFrame> : grade;
            })()}
            {folhas > 1 && Pagination &&
              <div style={{ marginTop: 12 }}>
                <Pagination page={pagina} pageCount={folhas} total={folha.length} pageSize={M.porFolha} onChange={setFolhaAtual} />
              </div>}
          </>}
      </Widget>
    </>
  );
}

window.ProdutoAcoes = { Etiquetas, ImportarProdutos, ImportarEstoque, AtualizarPreco };
})();
