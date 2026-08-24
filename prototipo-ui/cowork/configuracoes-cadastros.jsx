// configuracoes-cadastros.jsx — as 9 telas de lista do grupo "Configurações".
// Espelha o blade: business_location/{index,create,edit}, invoice_scheme/{index,create}, invoice_layout/create,
// barcode/{index,create}, printer/{index,create}, tax_rate/index + tax_group/create, restaurant/modifier_sets/index,
// types_of_service/{index,create} e /subscription (Superadmin).
// Onda C3 — mecânica: busca, ordenação, paginação, seleção em lote, editar/excluir de verdade, carregando e vazio.
// Onda C5 — papel e plano: cada ação é gated por permissão; formas de pagamento por local (default_payment_accounts).
// Expõe window.ConfigCadastros.
(() => {
const { useState, useMemo, useRef } = React;
const U = () => window.HrmUI || {};
const go = (id) => window.__selectRoute?.(id);

// ═══════════ Dados de protótipo ═══════════
const FORMAS = ["Dinheiro", "Cartão de crédito", "Cartão de débito", "Pix", "Boleto", "Transferência", "Cartão da loja", "Crédito do cliente"];
const CONTAS = ["Caixa da loja", "Banco do Brasil 1234-5", "Sicredi 4821-0", "Mercado Pago"];

const L0 = [
  { id:1, nome:"Matriz — Av. Brasil", ref:"BL0001", marco:"Em frente ao Detran", cidade:"Cuiabá", cep:"78065-000", uf:"MT",
    pais:"Brasil", tabela:"Varejo", esquema:"Padrão", layoutPdv:"Cupom 80mm", layoutVenda:"Nota A4",
    cnpj:"41.882.334/0001-07", ie:"0018773340092", im:"1187733", razao:"ROTA LIVRE Comunicação Visual Ltda",
    fantasia:"ROTA LIVRE", cel:"(65) 3025-4180", email:"contato@rotalivre.com.br", ativo:true,
    pagamentos:{ Dinheiro:"Caixa da loja", Pix:"Sicredi 4821-0", "Cartão de crédito":"Sicredi 4821-0", Boleto:"Banco do Brasil 1234-5" } },
  { id:2, nome:"Oficina — Distrito Industrial", ref:"BL0002", marco:"Galpão 4", cidade:"Várzea Grande", cep:"78110-400", uf:"MT",
    pais:"Brasil", tabela:"Atacado", esquema:"Oficina", layoutPdv:"Cupom 80mm", layoutVenda:"OS A4",
    cnpj:"41.882.334/0002-88", ie:"0018773340173", im:"1187744", razao:"ROTA LIVRE Comunicação Visual Ltda",
    fantasia:"ROTA LIVRE Oficina", cel:"(65) 3025-4188", email:"oficina@rotalivre.com.br", ativo:true,
    pagamentos:{ Dinheiro:"Caixa da loja", Pix:"Sicredi 4821-0" } },
  { id:3, nome:"Loja Centro (desativada)", ref:"BL0003", marco:"Galeria Central, sala 12", cidade:"Cuiabá", cep:"78005-100", uf:"MT",
    pais:"Brasil", tabela:"Varejo", esquema:"Padrão", layoutPdv:"Cupom 80mm", layoutVenda:"Nota A4",
    cnpj:"41.882.334/0003-69", ie:"0018773340254", im:"1187755", razao:"ROTA LIVRE Comunicação Visual Ltda",
    fantasia:"ROTA LIVRE Centro", cel:"", email:"", ativo:false, pagamentos:{ Dinheiro:"Caixa da loja" } },
];
const E0 = [
  { id:1, nome:"Padrão", prefixo:"", tipo:"Sequencial", inicio:"1", emitidas:"2.318", digitos:"4", padrao:true },
  { id:2, nome:"Oficina", prefixo:"OS", tipo:"Sequencial", inicio:"1", emitidas:"612", digitos:"5", padrao:false },
  { id:3, nome:"Orçamento", prefixo:"ORC", tipo:"Ano + sequencial", inicio:"1", emitidas:"1.940", digitos:"4", padrao:false },
  { id:4, nome:"Devolução", prefixo:"DEV", tipo:"Sequencial", inicio:"1", emitidas:"38", digitos:"4", padrao:false },
];
const LAY0 = [
  { id:1, nome:"Nota A4", design:"Clássico", locais:"Matriz · Oficina", padrao:true },
  { id:2, nome:"Cupom 80mm", design:"Enxuto (impressora térmica)", locais:"Matriz · Oficina", padrao:false },
  { id:3, nome:"OS A4", design:"Detalhado", locais:"Oficina", padrao:false },
];
const B0 = [
  { id:1, nome:"Etiqueta 3 colunas (33×22 mm)", desc:"Folha A4, 3 por linha, 24 linhas", padrao:true, papel:"Folha (várias etiquetas)" },
  { id:2, nome:"Rolo contínuo 50×30 mm", desc:"Impressora Argox, uma etiqueta por vez", padrao:false, papel:"Rolo contínuo" },
  { id:3, nome:"Etiqueta de bobina 100×50 mm", desc:"Placa e lona — código grande", padrao:false, papel:"Rolo contínuo" },
];
const P0 = [
  { id:1, nome:"Caixa 1 (balcão)", conexao:"Rede", perfil:"Padrão simples", cpl:"48", ip:"192.168.0.31", porta:"9100", caminho:"—" },
  { id:2, nome:"Caixa 2 (retirada)", conexao:"Windows", perfil:"Padrão simples", cpl:"48", ip:"—", porta:"—", caminho:"LPT1" },
  { id:3, nome:"Oficina (ordem de serviço)", conexao:"Linux", perfil:"Simples", cpl:"42", ip:"—", porta:"—", caminho:"/dev/usb/lp1" },
  { id:4, nome:"Expedição (etiqueta)", conexao:"Rede", perfil:"Star", cpl:"32", ip:"192.168.0.44", porta:"9100", caminho:"—" },
];
const T0 = [
  { id:1, nome:"ICMS 18%", aliquota:"18,00", grupoSo:false },
  { id:2, nome:"ICMS 12%", aliquota:"12,00", grupoSo:false },
  { id:3, nome:"ISS 5%", aliquota:"5,00", grupoSo:false },
  { id:4, nome:"PIS 1,65%", aliquota:"1,65", grupoSo:true },
  { id:5, nome:"COFINS 7,6%", aliquota:"7,60", grupoSo:true },
  { id:6, nome:"Isento", aliquota:"0,00", grupoSo:false },
];
const G0 = [
  { id:1, nome:"PIS + COFINS", aliquota:"9,25", subs:"PIS 1,65% · COFINS 7,6%" },
  { id:2, nome:"ICMS 18% + PIS/COFINS", aliquota:"27,25", subs:"ICMS 18% · PIS 1,65% · COFINS 7,6%" },
];
const M0 = [
  { id:1, nome:"Acabamento de lona", itens:"Bastão + corda (R$ 18,00) · Ilhós a cada 50 cm (R$ 12,00) · Bainha soldada (R$ 9,00)", produtos:"Lona 440g · Lona blackout" },
  { id:2, nome:"Instalação", itens:"No local até 10 km (R$ 120,00) · Altura acima de 3 m (R$ 180,00)", produtos:"Fachada · Painel ACM" },
  { id:3, nome:"Corte especial", itens:"Corte reto (R$ 0,00) · Corte de contorno (R$ 25,00)", produtos:"Adesivo vinil · PS 1mm" },
];
const S0 = [
  { id:1, nome:"Balcão — retirada", desc:"Cliente busca na loja", taxa:"R$ 0,00", tipo:"Fixo" },
  { id:2, nome:"Entrega na cidade", desc:"Até 15 km da matriz", taxa:"R$ 35,00", tipo:"Fixo" },
  { id:3, nome:"Montagem no local", desc:"Equipe leva material e instala", taxa:"8,00%", tipo:"Percentual" },
  { id:4, nome:"Envio por transportadora", desc:"Frete por conta do cliente", taxa:"R$ 0,00", tipo:"Fixo" },
];

// ═══════════ Mecânica compartilhada ═══════════
function useGrade(inicial, { campos, ordens }) {
  const [itens, setItens] = useState(inicial);
  const [q, setQ] = useState("");
  const [ordem, setOrdem] = useState(ordens[0].v);
  const [sel, setSel] = useState([]);
  const busca = useRef(null);
  const filtrados = useMemo(() => {
    const alvo = q.trim().toLowerCase();
    const base = alvo ? itens.filter((i) => campos.map((c) => String(i[c] ?? "")).join(" ").toLowerCase().includes(alvo)) : itens;
    const cmp = ordens.find((o) => o.v === ordem)?.cmp;
    return cmp ? [...base].sort(cmp) : base;
  }, [itens, q, ordem]);
  return { itens, setItens, q, setQ, ordem, setOrdem, sel, setSel, filtrados, busca };
}

function Ordem({ valor, onChange, opcoes }) {
  return (
    <label className="cfg-ordem">Ordenar
      <select className="hrm-sel" value={valor} onChange={(e) => onChange(e.target.value)}>
        {opcoes.map((o) => <option key={o.v} value={o.v}>{o.l}</option>)}
      </select>
    </label>
  );
}

function Grade({ g, cols, linhas, porPagina = 8, altura = 320, novo, novoLabel, placeholder, ordens,
  loteAcoes, vazioTitulo, vazioDesc, onLinha, carregando }) {
  const { Tabela, Busca, Paginacao, Skel, Vazio, Bulk, usePagina } = U();
  const pg = usePagina(g.filtrados, porPagina);
  const fatia = pg.fatia;
  return (
    <>
      <div className="hrm-toolbar">
        <Busca value={g.q} onChange={g.setQ} placeholder={placeholder} inputRef={g.busca} />
        {ordens && <Ordem valor={g.ordem} onChange={g.setOrdem} opcoes={ordens} />}
        {novo && <button className="os-btn primary" onClick={novo}>{novoLabel}</button>}
      </div>
      {carregando
        ? <Skel n={6} />
        : g.filtrados.length === 0
          ? <Vazio variante={g.q ? "no-results" : "first"} titulo={g.q ? "Nada com esse termo" : vazioTitulo}
              desc={g.q ? `Nenhum registro casa com “${g.q}”. Limpe a busca pra ver a lista inteira.` : vazioDesc}
              acao={g.q
                ? <button className="os-btn" onClick={() => g.setQ("")}>Limpar busca</button>
                : novo ? <button className="os-btn primary" onClick={novo}>{novoLabel}</button> : null} />
          : <>
              <Tabela cols={cols} rows={linhas(fatia)} altura={altura} selecionavel={!!loteAcoes}
                onSelecao={loteAcoes ? g.setSel : undefined} onLinha={onLinha} />
              <div className="hrm-pag">
                <Paginacao pagina={pg.pagina} paginas={pg.paginas} onMudar={pg.setPagina} total={pg.total} porPagina={pg.porPagina} />
              </div>
            </>}
      {loteAcoes && <Bulk n={g.sel.length} acoes={loteAcoes(g.sel)} onFechar={() => g.setSel([])} />}
    </>
  );
}

// Formulário genérico no Drawer (campos do blade)
function Campos({ campos, valores, onChange, travado }) {
  const { Campo, Escolha, Texto } = U();
  return (
    <div className="hrm-campos">
      {campos.map((c) => {
        const v = valores[c.k] ?? "";
        if (c.t === "sel") return <Escolha key={c.k} label={c.l} help={c.h} valor={v} opcoes={c.o.map((x) => ({ v:x, l:x }))} onChange={(x) => onChange(c.k, x)} wide={c.w} />;
        if (c.t === "texto") return <Texto key={c.k} label={c.l} help={c.h} valor={v} onChange={(x) => onChange(c.k, x)} />;
        return <Campo key={c.k} label={c.l} help={travado ? "Somente leitura pro seu papel." : c.h} valor={v} onChange={(x) => onChange(c.k, x)} wide={c.w} />;
      })}
    </div>
  );
}

function Painel({ titulo, sub, campos, secoes, inicial, extra, onFechar, onSalvar, somenteLeitura }) {
  const { Drawer, Sec } = U();
  const [f, setF] = useState(inicial || {});
  const [erro, setErro] = useState(null);
  const set = (k, v) => { if (somenteLeitura) return; setF((s) => ({ ...s, [k]:v })); setErro(null); };
  if (!Drawer) return null;
  const salvar = () => {
    if (!String(f.nome || "").trim()) { setErro("Dá um nome antes de salvar — é o que aparece na lista."); return; }
    onSalvar(f);
  };
  return (
    <Drawer title={titulo} sub={sub} onClose={onFechar} largo
      footer={<>
        <button className="os-btn ghost" onClick={onFechar}>Fechar</button>
        <button className="os-btn primary" disabled={somenteLeitura} onClick={salvar}>Salvar</button>
      </>}>
      {erro && <p className="cfg-erro" role="alert">{erro}</p>}
      {secoes
        ? secoes.map((s) => <Sec key={s.t} title={s.t}>{s.render ? s.render(f, set) : <Campos campos={s.campos} valores={f} onChange={set} travado={somenteLeitura} />}</Sec>)
        : <Campos campos={campos} valores={f} onChange={set} travado={somenteLeitura} />}
      {extra ? extra(f, set) : null}
    </Drawer>
  );
}

function Confirma({ titulo, texto, onCancelar, onConfirmar }) {
  const ds = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const corpo = (
    <>
      <p className="cfg-conf-t">{texto}</p>
      <div className="cfg-conf-a">
        <button className="os-btn ghost" onClick={onCancelar}>Cancelar</button>
        <button className="os-btn danger" onClick={onConfirmar}>Excluir</button>
      </div>
    </>
  );
  if (ds.Modal) return <ds.Modal open onClose={onCancelar} title={titulo}>{corpo}</ds.Modal>;
  return <div className="cfg-conf-bg" onClick={onCancelar}><div className="cfg-conf" onClick={(e) => e.stopPropagation()}><h3>{titulo}</h3>{corpo}</div></div>;
}

function SemPerm({ frase }) {
  const { Vazio } = U();
  return <Vazio variante="no-perm" titulo="Acesso restrito" desc={frase} />;
}
const acaoBtn = (l, on, tone) => <button key={l} className={`os-btn xs ${tone || ""}`} onClick={(e) => { e.stopPropagation(); on(); }}>{l}</button>;
const Pad = () => <span className="cfg-pad">padrão</span>;

// ═══════════ Locais comerciais ═══════════
function Locais({ A, aviso, carregando }) {
  const { Nota, Tabela } = U();
  const ordens = [
    { v:"nome", l:"nome", cmp:(a, b) => a.nome.localeCompare(b.nome) },
    { v:"cidade", l:"cidade", cmp:(a, b) => a.cidade.localeCompare(b.cidade) },
    { v:"ref", l:"ID do local", cmp:(a, b) => a.ref.localeCompare(b.ref) },
  ];
  const g = useGrade(L0, { campos:["nome", "cidade", "ref", "razao", "cnpj"], ordens });
  const [form, setForm] = useState(null);
  const [recibo, setRecibo] = useState(null);
  const [detalhe, setDetalhe] = useState(null);
  if (!A.pode("business_settings.access")) return <SemPerm frase="Locais comerciais pede `business_settings.access` — a mesma permissão da Configuração da empresa. O legado não separa as duas." />;
  const podeEditar = true; // no legado, quem vê a lista tem a mesma permissão de criar e editar
  const limite = A.plano.locais;

  const cols = [
    { key:"nome", label:"Nome", width:220 }, { key:"ref", label:"ID do local", width:110 },
    { key:"marco", label:"Ponto de referência", width:180 }, { key:"cidade", label:"Cidade", width:130 },
    { key:"cep", label:"CEP", width:100 }, { key:"uf", label:"UF", width:60 },
    { key:"tabela", label:"Tabela de preço", width:130 }, { key:"esquema", label:"Numeração", width:120 },
    { key:"layoutVenda", label:"Layout na venda", width:130 }, { key:"acao", label:"Ações", width:190 },
  ];
  const linhas = (fatia) => fatia.map((l) => ({
    id:l.id, state:l.ativo ? undefined : "archived",
    cells:{ ...l, nome:{ primary:l.nome, sub:l.ativo ? l.cnpj : "desativado · " + l.cnpj },
      acao:<span className="cfg-acoes">
        {acaoBtn("Editar", () => setForm(l))}
        {acaoBtn("Recibo", () => setRecibo(l))}
        {acaoBtn(l.ativo ? "Desativar" : "Ativar", () => {
          g.setItens(g.itens.map((i) => i.id === l.id ? { ...i, ativo:!i.ativo } : i));
          aviso(l.ativo ? `${l.nome} desativado — sai da emissão, o histórico fica.` : `${l.nome} reativado.`);
        }, l.ativo ? "ghost" : "")}
      </span> } }));

  const salvar = (f) => {
    // Espelha validateInvoiceRefs() do BusinessLocationController: esquema e layout são NOT NULL + FK
    if (!f.esquema || !f.layoutVenda) { aviso("Selecione o esquema de numeração e o layout da fatura."); return; }
    const dupe = g.itens.some((i) => i.ref && f.ref && i.ref === f.ref && i.id !== f.id);
    if (dupe) { aviso(`O ID ${f.ref} já está em outro local.`); return; }
    if (f.id) { g.setItens(g.itens.map((i) => i.id === f.id ? { ...i, ...f } : i)); aviso(`Local ${f.nome} atualizado.`); }
    else {
      const ref = f.ref || "BL" + String(g.itens.length + 1).padStart(4, "0");
      g.setItens([...g.itens, { ...f, id:Date.now(), ativo:true, ref, pagamentos:f.pagamentos || { Dinheiro:"Caixa da loja" } }]);
      aviso(`Local cadastrado — a permissão location.${g.itens.length + 1} passou a existir nas Funções.`);
    }
    setForm(null);
  };

  const secoes = [
    { t:"Identificação", campos:[
      { k:"nome", l:"Nome" }, { k:"ref", l:"ID do local" },
      { k:"razao", l:"Razão social", w:true }, { k:"fantasia", l:"Nome fantasia", w:true },
      { k:"cnpj", l:"CNPJ" }, { k:"ie", l:"Inscrição estadual" }, { k:"im", l:"Inscrição municipal" }] },
    { t:"Endereço", campos:[
      { k:"marco", l:"Ponto de referência", w:true }, { k:"cidade", l:"Cidade" }, { k:"cep", l:"CEP" },
      { k:"uf", l:"Estado" }, { k:"pais", l:"País" }] },
    { t:"Contato", campos:[{ k:"cel", l:"Celular" }, { k:"tel2", l:"Telefone alternativo" }, { k:"email", l:"E-mail" }, { k:"site", l:"Site" }] },
    { t:"Documento e preço", campos:[
      { k:"esquema", l:"Numeração na venda (obrigatório)", t:"sel", o:["", ...E0.map((e) => e.nome)] },
      { k:"esquemaPdv", l:"Numeração no PDV", t:"sel", o:["", ...E0.map((e) => e.nome)] },
      { k:"layoutVenda", l:"Layout na venda (obrigatório)", t:"sel", o:["", ...LAY0.map((l) => l.nome)] },
      { k:"layoutPdv", l:"Layout no PDV", t:"sel", o:["", ...LAY0.map((l) => l.nome)] },
      { k:"tabela", l:"Tabela de preço padrão", t:"sel", o:["Varejo", "Atacado", "Corporativo"] },
      { k:"destaques", l:"Produtos em destaque no PDV", t:"texto" }] },
    { t:"Formas de pagamento", render:(f, set) => <FormasPagto valores={f.pagamentos || {}} onChange={(v) => set("pagamentos", v)} travado={!podeEditar} /> },
  ];

  return (
    <>
      <Grade g={g} cols={cols} linhas={linhas} carregando={carregando} altura={300}
        placeholder="Buscar local, cidade, CNPJ ou ID  ·  /" ordens={ordens}
        novo={podeEditar ? () => setForm({}) : null} novoLabel="Adicionar"
        vazioTitulo="Nenhum local cadastrado" vazioDesc="Todo documento sai por um local — cadastre a matriz primeiro."
        loteAcoes={podeEditar ? (sel) => [{ label:"Exportar seleção", onSelect:() => aviso(`${sel.length} locais exportados.`) }] : null} />

      <div className="hrm-note-ds">
        <Nota tone={g.itens.length >= limite ? "warn" : "info"} title={g.itens.length >= limite ? "Cota da assinatura" : "Local não se exclui — se desativa"}>
          {g.itens.length >= limite
            ? <>O legado barra o cadastro em <code>ModuleUtil::isQuotaAvailable('locations')</code>: a cota do pacote <b>{A.plano.nome}</b> é de {limite} {limite === 1 ? "local" : "locais"} e você tem {g.itens.length}. Pra abrir outro,{" "}<button className="cfg-link" onClick={() => go("cfg-pacote")}>troque de pacote</button>.</>
            : <><code>BusinessLocationController::destroy()</code> está vazio no <code>main</code> — não existe excluir local, só ativar e desativar. Cada local guarda CNPJ, IE e IM próprios, e criar um novo cria também a permissão <code>location.&lt;id&gt;</code> que aparece nas Funções. Você usa {g.itens.length} de {limite} {limite === 1 ? "local" : "locais"} da cota do pacote {A.plano.nome}.</>}
        </Nota>
      </div>

      {form && <Painel titulo={form.id ? `Editar ${form.nome}` : "Adicionar local comercial"}
        sub="business_location/create.blade.php" inicial={form} secoes={secoes} somenteLeitura={!podeEditar}
        onFechar={() => setForm(null)} onSalvar={salvar} />}

      {recibo && <ReciboDoLocal local={recibo} onFechar={() => setRecibo(null)}
        onSalvar={(v) => { g.setItens(g.itens.map((i) => i.id === recibo.id ? { ...i, ...v } : i)); aviso(`Recibo de ${recibo.nome} atualizado.`); setRecibo(null); }} />}

      {detalhe && (() => {
        const { Drawer, Sec } = U();
        return (
          <Drawer title={`Formas de pagamento — ${detalhe.nome}`} sub="default_payment_accounts" onClose={() => setDetalhe(null)}
            footer={<button className="os-btn ghost" onClick={() => setDetalhe(null)}>Fechar</button>}>
            <Sec title="O que o caixa aceita neste local">
              <FormasPagto valores={detalhe.pagamentos} travado onChange={() => {}} />
            </Sec>
          </Drawer>);
      })()}
    </>
  );
}

// Configurações de recibo do local (location_settings/index.blade.php + LocationSettingsController)
function ReciboDoLocal({ local, onFechar, onSalvar }) {
  const { Drawer, Sec, Escolha, Nota } = U();
  const [f, setF] = useState({
    imprimeNaFatura:local.imprimeNaFatura || "Sim",
    tipoImpressao:local.tipoImpressao || "Impressão pelo navegador",
    impressora:local.impressora || "Caixa 1 (balcão)",
    layoutVenda:local.layoutVenda || "Nota A4",
    esquema:local.esquema || "Padrão",
  });
  const set = (k) => (v) => setF((s) => ({ ...s, [k]:v }));
  if (!Drawer) return null;
  const porImpressora = f.tipoImpressao === "Impressora configurada";
  return (
    <Drawer title={`Configurações de recibo — ${local.nome}`} sub="location_settings/index.blade.php" onClose={onFechar} largo
      footer={<><button className="os-btn ghost" onClick={onFechar}>Fechar</button><button className="os-btn primary" onClick={() => onSalvar(f)}>Atualizar</button></>}>
      <Sec title="Como o recibo sai">
        <div className="hrm-campos">
          <Escolha label="Imprimir recibo ao faturar" valor={f.imprimeNaFatura} onChange={set("imprimeNaFatura")}
            opcoes={[{ v:"Sim", l:"Sim" }, { v:"Não", l:"Não" }]} help="Sim manda o cupom direto ao finalizar a venda." />
          <Escolha label="Tipo de impressão" valor={f.tipoImpressao} onChange={set("tipoImpressao")}
            opcoes={[{ v:"Impressão pelo navegador", l:"Impressão pelo navegador" }, { v:"Impressora configurada", l:"Impressora configurada" }]} />
          {porImpressora && <Escolha label="Impressora de recibo" valor={f.impressora} onChange={set("impressora")}
            opcoes={P0.map((p) => ({ v:p.nome, l:p.nome }))} />}
        </div>
      </Sec>
      <Sec title="Documento deste local">
        <div className="hrm-campos">
          <Escolha label="Layout da fatura" valor={f.layoutVenda} onChange={set("layoutVenda")} opcoes={LAY0.map((l) => ({ v:l.nome, l:l.nome }))} />
          <Escolha label="Esquema de numeração" valor={f.esquema} onChange={set("esquema")} opcoes={E0.map((e) => ({ v:e.nome, l:e.nome }))} />
        </div>
      </Sec>
      {Nota && <div className="hrm-note-ds"><Nota tone="info" title="Em demonstração só navegador">
        O <code>LocationSettingsController::updateSettings</code> força <code>receipt_printer_type = browser</code> quando
        <code> APP_ENV=demo</code>, e exige também <code>can_access_this_location</code> — gerente de uma filial não mexe na outra.
      </Nota></div>}
    </Drawer>
  );
}

function FormasPagto({ valores, onChange, travado }) {
  const marcadas = valores || {};
  const alterna = (forma) => {
    const n = { ...marcadas };
    if (n[forma]) delete n[forma]; else n[forma] = CONTAS[0];
    onChange(n);
  };
  return (
    <div className="os-table-wrap cfg-pagtos">
      <table className="os-table">
        <thead><tr><th scope="col">Forma de pagamento</th><th scope="col">Aceita</th><th scope="col">Conta padrão</th></tr></thead>
        <tbody>
          {FORMAS.map((forma) => (
            <tr key={forma}>
              <td>{forma}</td>
              <td>
                <input type="checkbox" checked={!!marcadas[forma]} disabled={travado} onChange={() => alterna(forma)} aria-label={`Aceitar ${forma}`} />
              </td>
              <td>
                <select className="hrm-sel" value={marcadas[forma] || ""} disabled={travado || !marcadas[forma]}
                  onChange={(e) => onChange({ ...marcadas, [forma]:e.target.value })} aria-label={`Conta padrão de ${forma}`}>
                  <option value="">—</option>
                  {CONTAS.map((c) => <option key={c} value={c}>{c}</option>)}
                </select>
              </td>
            </tr>))}
        </tbody>
      </table>
    </div>
  );
}

// ═══════════ Configurações da fatura ═══════════
function Fatura({ A, aviso, carregando }) {
  const { Nota } = U();
  const [aba, setAba] = useState("esquemas");
  const [layouts, setLayouts] = useState(LAY0);
  const [editor, setEditor] = useState(null); // {} = novo · objeto = editar
  const [excluir, setExcluir] = useState(null);
  const [formEsq, setFormEsq] = useState(null);
  const ordens = [
    { v:"nome", l:"nome", cmp:(a, b) => a.nome.localeCompare(b.nome) },
    { v:"emitidas", l:"mais usadas", cmp:(a, b) => parseFloat(b.emitidas.replace(".", "")) - parseFloat(a.emitidas.replace(".", "")) },
  ];
  const g = useGrade(E0, { campos:["nome", "prefixo", "tipo"], ordens });
  if (!A.pode("invoice_settings.access")) return <SemPerm frase="Numeração e layout pedem `invoice_settings.access` — e o legado não separa ver de editar: quem entra, altera." />;
  const podeEditar = true;
  const F = window.ConfigFatura;

  if (editor && F) return (
    <F.LayoutEditor inicial={editor.id ? editor : null} rotulos={A.rotulos} somenteLeitura={!podeEditar}
      onFechar={() => setEditor(null)}
      onSalvar={(f) => {
        if (editor.id) setLayouts(layouts.map((l) => l.id === editor.id ? { ...l, nome:f.nome, design:f.design } : l));
        else setLayouts([...layouts, { id:Date.now(), nome:f.nome || "Novo layout", design:f.design, locais:"—", padrao:false }]);
        aviso(`Layout ${f.nome || "novo"} salvo.`);
        setEditor(null);
      }} />
  );

  const cols = [
    { key:"nome", label:"Nome", width:150 }, { key:"prefixo", label:"Prefixo", width:100 },
    { key:"tipo", label:"Tipo de número", width:150 }, { key:"inicio", label:"Número inicial", width:120, align:"right" },
    { key:"emitidas", label:"Emitidas", width:110, align:"right" }, { key:"digitos", label:"Dígitos", width:90, align:"right" },
    { key:"acao", label:"Ações", width:170 },
  ];
  const linhas = (fatia) => fatia.map((e) => ({ id:e.id, cells:{ ...e,
    nome:{ primary:e.nome, sub:e.padrao ? "padrão do negócio" : "" },
    acao:<span className="cfg-acoes">
      {podeEditar && acaoBtn("Editar", () => setFormEsq(e))}
      {podeEditar && acaoBtn("Excluir", () => setExcluir({ tipo:"esquema", item:e }), "ghost")}
    </span> } }));

  return (
    <>
      <div className="hrm-seg" role="tablist">
        <button className={aba === "esquemas" ? "on" : ""} onClick={() => setAba("esquemas")}>Esquemas de numeração</button>
        <button className={aba === "layouts" ? "on" : ""} onClick={() => setAba("layouts")}>Layouts da fatura</button>
      </div>

      {aba === "esquemas" ? (
        <Grade g={g} cols={cols} linhas={linhas} carregando={carregando} altura={280} porPagina={6}
          placeholder="Buscar esquema ou prefixo" ordens={ordens}
          novo={podeEditar ? () => setFormEsq({}) : null} novoLabel="Adicionar"
          vazioTitulo="Nenhum esquema de numeração" vazioDesc="Sem esquema o documento sai sem número — crie ao menos o padrão." />
      ) : (
        <>
          <div className="hrm-toolbar">
            <span className="cfg-hint">Layout é o desenho impresso: cabeçalho, rótulos das colunas, totais e QR. Abre em tela cheia com folha de prova.</span>
            {podeEditar && <button className="os-btn primary" onClick={() => setEditor({})}>Novo layout</button>}
          </div>
          <div className="hrm-grid">
            {layouts.map((l) => (
              <section key={l.id} className="hrm-card">
                <h3>{l.nome} {l.padrao ? <Pad /> : null}</h3>
                <p className="cfg-p">{l.design}</p>
                <dl className="hrm-kv"><dt>Usado em</dt><dd>{l.locais}</dd></dl>
                <div className="cfg-acoes">
                  {acaoBtn(podeEditar ? "Abrir editor" : "Ver layout", () => setEditor(l))}
                  {podeEditar && !l.padrao && acaoBtn("Excluir", () => setExcluir({ tipo:"layout", item:l }), "ghost")}
                </div>
              </section>))}
          </div>
        </>
      )}

      <div className="hrm-note-ds">
        <Nota tone="warn" title="Numeração fiscal é outra coisa">
          Isto numera o documento interno (fatura, orçamento, OS). Série e número da NF-e são da SEFAZ e vivem em <b>NF-e Brasil</b>.
        </Nota>
      </div>

      {formEsq && <Painel titulo={formEsq.id ? `Editar ${formEsq.nome}` : "Adicionar esquema de numeração"}
        sub="invoice_scheme/create.blade.php" inicial={formEsq} somenteLeitura={!podeEditar}
        campos={[{ k:"nome", l:"Nome" }, { k:"tipo", l:"Tipo de número", t:"sel", o:["Sequencial", "Ano + sequencial"] },
          { k:"prefixo", l:"Prefixo" }, { k:"inicio", l:"Número inicial" },
          { k:"digitos", l:"Total de dígitos", h:"Completa com zero à esquerda: 4 dígitos = OS0001." }]}
        onFechar={() => setFormEsq(null)}
        onSalvar={(f) => {
          if (f.id) g.setItens(g.itens.map((i) => i.id === f.id ? { ...i, ...f } : i));
          else g.setItens([...g.itens, { ...f, id:Date.now(), emitidas:"0", padrao:false }]);
          aviso(`Esquema ${f.nome} salvo.`); setFormEsq(null);
        }} />}

      {excluir && <Confirma titulo={`Excluir ${excluir.item.nome}?`}
        texto={excluir.tipo === "esquema"
          ? "Documento já emitido mantém o número. O esquema só deixa de ser oferecido nas próximas vendas."
          : "As vendas antigas continuam imprimindo pelo layout guardado; novas vendas passam a usar o padrão."}
        onCancelar={() => setExcluir(null)}
        onConfirmar={() => {
          if (excluir.tipo === "esquema") g.setItens(g.itens.filter((i) => i.id !== excluir.item.id));
          else setLayouts(layouts.filter((l) => l.id !== excluir.item.id));
          aviso(`${excluir.item.nome} excluído.`); setExcluir(null);
        }} />}
    </>
  );
}

// ═══════════ Código de barras ═══════════
function Barras({ A, aviso, carregando }) {
  const { Nota } = U();
  const ordens = [{ v:"nome", l:"nome", cmp:(a, b) => a.nome.localeCompare(b.nome) }, { v:"papel", l:"tipo de papel", cmp:(a, b) => a.papel.localeCompare(b.papel) }];
  const g = useGrade(B0, { campos:["nome", "desc", "papel"], ordens });
  const [form, setForm] = useState(null);
  const [excluir, setExcluir] = useState(null);
  if (!A.pode("barcode_settings.access")) return <SemPerm frase="Configuração de etiqueta pede `barcode_settings.access`. Pra imprimir etiqueta, use a tela de produtos." />;
  const podeEditar = true;
  const cols = [{ key:"nome", label:"Nome da configuração", width:280 }, { key:"desc", label:"Descrição", width:300 },
    { key:"papel", label:"Papel", width:180 }, { key:"acao", label:"Ações", width:190 }];
  const linhas = (fatia) => fatia.map((b) => ({ id:b.id, cells:{ nome:{ primary:b.nome, sub:b.padrao ? "padrão" : "" }, desc:b.desc, papel:b.papel,
    acao:<span className="cfg-acoes">
      {acaoBtn("Imprimir prova", () => aviso("Folha de prova enviada pra impressora."))}
      {podeEditar && acaoBtn("Editar", () => setForm(b))}
      {podeEditar && acaoBtn("Excluir", () => setExcluir(b), "ghost")}
    </span> } }));
  return (
    <>
      <Grade g={g} cols={cols} linhas={linhas} carregando={carregando} altura={260} porPagina={6}
        placeholder="Buscar configuração de etiqueta" ordens={ordens}
        novo={podeEditar ? () => setForm({}) : null} novoLabel="Nova configuração"
        vazioTitulo="Nenhuma configuração de etiqueta" vazioDesc="Cada folha ou rolo que a loja compra vira uma configuração aqui." />
      <div className="hrm-note-ds">
        <Nota tone="info" title="Medidas em milímetro">
          O blade pede tudo em polegada (<code>in</code>). Aqui entra em mm e converte na impressão — quem compra etiqueta no Brasil compra em mm.
        </Nota>
      </div>
      {form && <Painel titulo={form.id ? `Editar ${form.nome}` : "Nova configuração de etiqueta"} sub="barcode/create.blade.php"
        inicial={form} somenteLeitura={!podeEditar} onFechar={() => setForm(null)}
        onSalvar={(f) => {
          if (f.id) g.setItens(g.itens.map((i) => i.id === f.id ? { ...i, ...f } : i));
          else g.setItens([...g.itens, { ...f, id:Date.now(), papel:f.papel || "Folha (várias etiquetas)", padrao:false }]);
          aviso("Configuração de etiqueta salva."); setForm(null);
        }}
        secoes={[
          { t:"Identificação", campos:[{ k:"nome", l:"Nome" }, { k:"desc", l:"Descrição", t:"texto" },
            { k:"papel", l:"Papel", t:"sel", o:["Folha (várias etiquetas)", "Rolo contínuo"] }] },
          { t:"Folha", campos:[{ k:"pw", l:"Largura do papel (mm)" }, { k:"ph", l:"Altura do papel (mm)" },
            { k:"mt", l:"Margem superior (mm)" }, { k:"ml", l:"Margem esquerda (mm)" }] },
          { t:"Etiqueta", campos:[{ k:"w", l:"Largura (mm)" }, { k:"h", l:"Altura (mm)" },
            { k:"porLinha", l:"Etiquetas por linha" }, { k:"porFolha", l:"Etiquetas por folha" },
            { k:"dLinha", l:"Distância entre linhas (mm)" }, { k:"dCol", l:"Distância entre colunas (mm)" }] },
        ]} />}
      {excluir && <Confirma titulo={`Excluir ${excluir.nome}?`} texto="Só a configuração sai; nenhuma etiqueta já impressa muda."
        onCancelar={() => setExcluir(null)}
        onConfirmar={() => { g.setItens(g.itens.filter((i) => i.id !== excluir.id)); aviso("Configuração excluída."); setExcluir(null); }} />}
    </>
  );
}

// ═══════════ Impressoras ═══════════
function Impressoras({ A, aviso, carregando }) {
  const { Nota } = U();
  const ordens = [{ v:"nome", l:"nome", cmp:(a, b) => a.nome.localeCompare(b.nome) }, { v:"conexao", l:"tipo de conexão", cmp:(a, b) => a.conexao.localeCompare(b.conexao) }];
  const g = useGrade(P0, { campos:["nome", "conexao", "ip", "caminho"], ordens });
  const [form, setForm] = useState(null);
  const [excluir, setExcluir] = useState(null);
  const podeEditar = A.pode("access_printers");
  const cols = [
    { key:"nome", label:"Nome", width:210 }, { key:"conexao", label:"Tipo de conexão", width:140 },
    { key:"perfil", label:"Perfil", width:140 }, { key:"cpl", label:"Caracteres/linha", width:140, align:"right" },
    { key:"ip", label:"Endereço IP", width:130 }, { key:"porta", label:"Porta", width:90 },
    { key:"caminho", label:"Caminho", width:150 }, { key:"acao", label:"Ações", width:200 },
  ];
  const linhas = (fatia) => fatia.map((p) => ({ id:p.id, cells:{ ...p,
    acao:<span className="cfg-acoes">
      {acaoBtn("Testar", () => aviso(`Cupom de teste enviado pra ${p.nome}.`))}
      {podeEditar && acaoBtn("Editar", () => setForm(p))}
      {podeEditar && acaoBtn("Excluir", () => setExcluir(p), "ghost")}
    </span> } }));
  return (
    <>
      <Grade g={g} cols={cols} linhas={linhas} carregando={carregando} altura={280} porPagina={6}
        placeholder="Buscar impressora, IP ou caminho" ordens={ordens}
        novo={podeEditar ? () => setForm({}) : null} novoLabel="Adicionar impressora"
        vazioTitulo="Nenhuma impressora cadastrada" vazioDesc="Sem impressora o cupom sai pelo diálogo do navegador." />
      <div className="hrm-note-ds">
        <Nota tone="info" title="Rede, Windows ou Linux">
          Rede pede IP e porta (9100 na maioria). Windows aceita <code>LPT1</code> ou <code>COM1</code>. Linux usa
          <code> /dev/usb/lp1</code> pra USB e <code>/dev/ttyUSB0</code> pra USB-serial. Testar imprime um cupom curto.
        </Nota>
      </div>
      {form && <Painel titulo={form.id ? `Editar ${form.nome}` : "Adicionar impressora"} sub="printer/create.blade.php"
        inicial={form} somenteLeitura={!podeEditar} onFechar={() => setForm(null)}
        onSalvar={(f) => {
          if (f.id) g.setItens(g.itens.map((i) => i.id === f.id ? { ...i, ...f } : i));
          else g.setItens([...g.itens, { ...f, id:Date.now(), ip:f.ip || "—", porta:f.porta || "—", caminho:f.caminho || "—" }]);
          aviso("Impressora cadastrada."); setForm(null);
        }}
        campos={[{ k:"nome", l:"Nome", w:true },
          { k:"conexao", l:"Tipo de conexão", t:"sel", o:["Rede", "Windows", "Linux"] },
          { k:"perfil", l:"Perfil de capacidade", t:"sel", o:["Padrão simples", "Simples", "Epson", "Star"] },
          { k:"cpl", l:"Caracteres por linha", h:"48 na bobina de 80 mm, 32 na de 58 mm." },
          { k:"ip", l:"Endereço IP" }, { k:"porta", l:"Porta" }, { k:"caminho", l:"Caminho", w:true }]} />}
      {excluir && <Confirma titulo={`Excluir ${excluir.nome}?`} texto="Os caixas que usam esta impressora voltam a imprimir pelo navegador."
        onCancelar={() => setExcluir(null)}
        onConfirmar={() => { g.setItens(g.itens.filter((i) => i.id !== excluir.id)); aviso("Impressora excluída."); setExcluir(null); }} />}
    </>
  );
}

// ═══════════ Taxas de imposto ═══════════
function Impostos({ A, aviso, carregando }) {
  const { Nota, Tabela } = U();
  const ordens = [{ v:"nome", l:"nome", cmp:(a, b) => a.nome.localeCompare(b.nome) },
    { v:"aliquota", l:"alíquota", cmp:(a, b) => parseFloat(b.aliquota.replace(",", ".")) - parseFloat(a.aliquota.replace(",", ".")) }];
  const g = useGrade(T0, { campos:["nome", "aliquota"], ordens });
  const [grupos, setGrupos] = useState(G0);
  const [form, setForm] = useState(null);
  const [excluir, setExcluir] = useState(null);
  if (!A.pode("tax_rate.view") && !A.pode("tax_rate.create")) return <SemPerm frase="Taxas pedem `tax_rate.view` ou `tax_rate.create` — elas mexem no total de toda venda." />;
  const podeCriar = A.pode("tax_rate.create");
  const podeAtualizar = A.pode("tax_rate.update");
  const podeExcluir = A.pode("tax_rate.delete");
  const emGrupo = (t) => grupos.some((gr) => gr.subs.includes(t.nome));
  const cols = [{ key:"nome", label:"Nome", width:220 }, { key:"aliquota", label:"Alíquota (%)", width:130, align:"right" }, { key:"acao", label:"Ações", width:170 }];
  const linhas = (fatia) => fatia.map((t) => ({ id:t.id, cells:{
    nome:{ primary:t.nome, sub:t.grupoSo ? "só dentro de grupo" : emGrupo(t) ? "em uso num grupo" : "" }, aliquota:t.aliquota,
    acao:<span className="cfg-acoes">
      {podeAtualizar && acaoBtn("Editar", () => setForm({ tipo:"taxa", item:t }))}
      {podeExcluir && (emGrupo(t)
        ? <button key="x" className="os-btn xs ghost" disabled title="Pertence a um grupo de imposto — o servidor recusa (tax_rate.can_not_be_deleted)">Em grupo</button>
        : acaoBtn("Excluir", () => setExcluir({ tipo:"taxa", item:t }), "ghost"))}
    </span> } }));
  const colsG = [{ key:"nome", label:"Nome", width:220 }, { key:"aliquota", label:"Alíquota (%)", width:130, align:"right" },
    { key:"subs", label:"Impostos do grupo", width:320 }, { key:"acao", label:"Ações", width:170 }];
  const rowsG = grupos.map((gr) => ({ id:gr.id, cells:{ ...gr,
    acao:<span className="cfg-acoes">
      {podeAtualizar && acaoBtn("Editar", () => setForm({ tipo:"grupo", item:gr }))}
      {podeExcluir && acaoBtn("Excluir", () => setExcluir({ tipo:"grupo", item:gr }), "ghost")}
    </span> } }));
  return (
    <>
      <Grade g={g} cols={cols} linhas={linhas} carregando={carregando} altura={260} porPagina={6}
        placeholder="Buscar taxa ou alíquota" ordens={ordens}
        novo={podeCriar ? () => setForm({ tipo:"taxa", item:{} }) : null} novoLabel="Adicionar taxa"
        vazioTitulo="Nenhuma taxa cadastrada" vazioDesc="Sem taxa, toda venda sai sem imposto no total." />
      <div className="hrm-toolbar cfg-toolbar2">
        <span className="cfg-hint">Grupos de imposto — combinação de taxas aplicada de uma vez</span>
        {podeCriar && <button className="os-btn primary" onClick={() => setForm({ tipo:"grupo", item:{} })}>Adicionar grupo</button>}
      </div>
      {carregando ? <div className="hrm-skel" /> : <Tabela cols={colsG} rows={rowsG} altura={180} />}
      <div className="hrm-note-ds">
        <Nota tone="warn" title="Isto não substitui o cálculo fiscal">
          São as taxas do UltimatePOS, usadas no preço e no total do documento. ICMS-ST, redução de base, CST e CFOP são do
          módulo <b>NF-e Brasil</b> — cadastrar aqui não emite nada. Duas regras do <code>TaxRateController</code>: taxa que
          pertence a um grupo <b>não pode ser excluída</b>, e mudar a alíquota de uma taxa <b>recalcula os grupos</b> que a contêm.
        </Nota>
      </div>
      {form && <Painel
        titulo={form.item.id ? `Editar ${form.item.nome}` : form.tipo === "taxa" ? "Adicionar taxa de imposto" : "Adicionar grupo de imposto"}
        sub={form.tipo === "taxa" ? "tax_rate/create.blade.php" : "tax_group/create.blade.php"}
        inicial={form.item} somenteLeitura={form.item.id ? !podeAtualizar : !podeCriar} onFechar={() => setForm(null)}
        campos={form.tipo === "taxa"
          ? [{ k:"nome", l:"Nome", w:true }, { k:"aliquota", l:"Alíquota (%)" },
             { k:"uso", l:"Uso", t:"sel", o:["Solta e em grupo", "Só dentro de grupo"] }]
          : [{ k:"nome", l:"Nome", w:true }, { k:"subs", l:"Impostos do grupo", t:"texto", h:"Some as alíquotas das taxas escolhidas." }]}
        onSalvar={(f) => {
          if (form.tipo === "taxa") {
            if (f.id) {
              g.setItens(g.itens.map((i) => i.id === f.id ? { ...i, ...f, grupoSo:f.uso === "Só dentro de grupo" } : i));
              if (emGrupo(f)) { aviso(`${f.nome} salva — os grupos que a usam foram recalculados.`); setForm(null); return; }
            } else g.setItens([...g.itens, { ...f, id:Date.now(), aliquota:f.aliquota || "0,00", grupoSo:f.uso === "Só dentro de grupo" }]);
          } else {
            if (f.id) setGrupos(grupos.map((i) => i.id === f.id ? { ...i, ...f } : i));
            else setGrupos([...grupos, { ...f, id:Date.now(), aliquota:f.aliquota || "0,00" }]);
          }
          aviso(`${f.nome} salvo.`); setForm(null);
        }} />}
      {excluir && <Confirma titulo={`Excluir ${excluir.item.nome}?`}
        texto={excluir.tipo === "taxa"
          ? "Produto que usa esta taxa passa a sair sem imposto — confira antes de vender. Taxa que pertence a um grupo o servidor recusa excluir."
          : "As taxas do grupo continuam cadastradas; só a combinação desaparece."}
        onCancelar={() => setExcluir(null)}
        onConfirmar={() => {
          if (excluir.tipo === "taxa") g.setItens(g.itens.filter((i) => i.id !== excluir.item.id));
          else setGrupos(grupos.filter((i) => i.id !== excluir.item.id));
          aviso(`${excluir.item.nome} excluído.`); setExcluir(null);
        }} />}
    </>
  );
}

// ═══════════ Modificadores ═══════════
function Modificadores({ A, aviso, carregando }) {
  const { Nota } = U();
  const ordens = [{ v:"nome", l:"nome", cmp:(a, b) => a.nome.localeCompare(b.nome) }];
  const g = useGrade(M0, { campos:["nome", "itens", "produtos"], ordens });
  const [form, setForm] = useState(null);
  const [excluir, setExcluir] = useState(null);
  if (!A.pode("product.create")) return <SemPerm frase="O `ModifierSetsController` do legado exige `product.create` — quem cuida do catálogo cuida dos modificadores." />;
  const podeEditar = true;
  const cols = [{ key:"nome", label:"Conjunto", width:190 }, { key:"itens", label:"Modificadores", width:400 },
    { key:"produtos", label:"Produtos", width:190 }, { key:"acao", label:"Ações", width:230 }];
  const linhas = (fatia) => fatia.map((m) => ({ id:m.id, cells:{ ...m,
    acao:<span className="cfg-acoes">
      {podeEditar && acaoBtn("Editar", () => setForm(m))}
      {podeEditar && acaoBtn("Produtos", () => aviso(`${m.nome}: escolha os produtos que oferecem este conjunto.`))}
      {podeEditar && acaoBtn("Excluir", () => setExcluir(m), "ghost")}
    </span> } }));
  return (
    <>
      <Grade g={g} cols={cols} linhas={linhas} carregando={carregando} altura={260} porPagina={6}
        placeholder="Buscar conjunto, modificador ou produto" ordens={ordens}
        novo={podeEditar ? () => setForm({}) : null} novoLabel="Novo conjunto"
        vazioTitulo="Nenhum conjunto de modificadores" vazioDesc="Acabamento e serviço extra do item moram aqui — bastão, ilhós, instalação." />
      <div className="hrm-note-ds">
        <Nota tone="info" title="Permissão emprestada do catálogo">
          O conjunto é ligado a produtos e, no PDV, vira opção com preço próprio somada na linha da venda. Achado do{" "}
          <code>main</code>: as rotas vivem em <code>/modules/modifiers</code> (<code>App\Http\Controllers\Restaurant</code>,
          core do UltimatePOS — não há módulo <code>Modules/Restaurant</code>) e o controle de acesso é <code>product.create</code>:
          não existe permissão própria de modificador.
        </Nota>
      </div>
      {form && <Painel titulo={form.id ? `Editar ${form.nome}` : "Novo conjunto de modificadores"}
        sub="restaurant/modifier_sets/create.blade.php" inicial={form} somenteLeitura={!podeEditar}
        onFechar={() => setForm(null)}
        onSalvar={(f) => {
          if (f.id) g.setItens(g.itens.map((i) => i.id === f.id ? { ...i, ...f } : i));
          else g.setItens([...g.itens, { ...f, id:Date.now(),
            itens:[[f.m1, f.p1], [f.m2, f.p2], [f.m3, f.p3]].filter((x) => x[0]).map(([m, p]) => `${m} (R$ ${p || "0,00"})`).join(" · ") || "—",
            produtos:f.produtos || "—" }]);
          aviso("Conjunto de modificadores salvo."); setForm(null);
        }}
        campos={[{ k:"nome", l:"Nome do conjunto", w:true },
          { k:"m1", l:"Modificador 1" }, { k:"p1", l:"Preço 1" },
          { k:"m2", l:"Modificador 2" }, { k:"p2", l:"Preço 2" },
          { k:"m3", l:"Modificador 3" }, { k:"p3", l:"Preço 3" }]} />}
      {excluir && <Confirma titulo={`Excluir ${excluir.nome}?`} texto="Vendas antigas mantêm o que foi cobrado; o conjunto deixa de aparecer no PDV."
        onCancelar={() => setExcluir(null)}
        onConfirmar={() => { g.setItens(g.itens.filter((i) => i.id !== excluir.id)); aviso("Conjunto excluído."); setExcluir(null); }} />}
    </>
  );
}

// ═══════════ Tipos de serviço ═══════════
function Servicos({ A, aviso, carregando }) {
  const { Nota } = U();
  const ordens = [{ v:"nome", l:"nome", cmp:(a, b) => a.nome.localeCompare(b.nome) }, { v:"tipo", l:"tipo de taxa", cmp:(a, b) => a.tipo.localeCompare(b.tipo) }];
  const g = useGrade(S0, { campos:["nome", "desc", "taxa"], ordens });
  const [form, setForm] = useState(null);
  const [excluir, setExcluir] = useState(null);
  const podeEditar = A.pode("access_types_of_service");
  const cols = [{ key:"nome", label:"Nome", width:210 }, { key:"desc", label:"Descrição", width:300 },
    { key:"taxa", label:"Taxa de embalagem", width:160, align:"right" }, { key:"acao", label:"Ações", width:170 }];
  const linhas = (fatia) => fatia.map((s) => ({ id:s.id, cells:{ nome:{ primary:s.nome, sub:s.tipo }, desc:s.desc, taxa:s.taxa,
    acao:<span className="cfg-acoes">
      {podeEditar && acaoBtn("Editar", () => setForm(s))}
      {podeEditar && acaoBtn("Excluir", () => setExcluir(s), "ghost")}
    </span> } }));
  return (
    <>
      <Grade g={g} cols={cols} linhas={linhas} carregando={carregando} altura={260} porPagina={6}
        placeholder="Buscar tipo de serviço" ordens={ordens}
        novo={podeEditar ? () => setForm({}) : null} novoLabel="Adicionar tipo"
        vazioTitulo="Nenhum tipo de serviço" vazioDesc="Retirada, entrega e montagem entram aqui — a venda escolhe qual é." />
      <div className="hrm-note-ds">
        <Nota tone="warn" title="Dois achados do TypesOfServiceController">
          O preço por local vai em <code>location_price_group</code> (JSON): o <code>update()</code> faz
          <code> json_encode</code>, o <code>store()</code> <b>não</b> — tipo criado agora grava o campo torto. E não há
          FormRequest: nome vazio passa pelo servidor. O bloqueio de nome vazio aqui é da tela, não do backend.
        </Nota>
      </div>
      {form && <Painel titulo={form.id ? `Editar ${form.nome}` : "Adicionar tipo de serviço"} sub="types_of_service/create.blade.php"
        inicial={form} somenteLeitura={!podeEditar} onFechar={() => setForm(null)}
        onSalvar={(f) => {
          if (f.id) g.setItens(g.itens.map((i) => i.id === f.id ? { ...i, ...f } : i));
          else g.setItens([...g.itens, { ...f, id:Date.now(), tipo:f.tipoTaxa || "Fixo",
            taxa:f.tipoTaxa === "Percentual" ? `${f.taxa || "0,00"}%` : `R$ ${f.taxa || "0,00"}`, desc:f.desc || "—" }]);
          aviso("Tipo de serviço salvo."); setForm(null);
        }}
        secoes={[
          { t:"Identificação", campos:[{ k:"nome", l:"Nome" }, { k:"desc", l:"Descrição", t:"texto" }] },
          { t:"Cobrança", campos:[{ k:"tipoTaxa", l:"Tipo da taxa", t:"sel", o:["Fixo", "Percentual"] }, { k:"taxa", l:"Taxa de embalagem" }] },
          { t:"Preço por local", campos:[
            { k:"pMatriz", l:"Matriz — tabela de preço", t:"sel", o:["Varejo", "Atacado", "Corporativo"] },
            { k:"pOficina", l:"Oficina — tabela de preço", t:"sel", o:["Varejo", "Atacado", "Corporativo"] }] },
        ]} />}
      {excluir && <Confirma titulo={`Excluir ${excluir.nome}?`} texto="Vendas antigas mantêm o tipo registrado; ele só sai da escolha do caixa."
        onCancelar={() => setExcluir(null)}
        onConfirmar={() => { g.setItens(g.itens.filter((i) => i.id !== excluir.id)); aviso("Tipo de serviço excluído."); setExcluir(null); }} />}
    </>
  );
}

// ═══════════ Assinatura de pacote ═══════════
function Pacote({ A, carregando }) {
  const { Tabela, Nota, Skel } = U();
  if (!A.pode("business_settings.access")) return <SemPerm frase="Assinatura e pagamento do sistema são do dono da conta (módulo Superadmin — permissão exata não conferida no main)." />;
  const cols = [{ key:"pacote", label:"Pacote", width:180 }, { key:"inicio", label:"Início", width:120 },
    { key:"fim", label:"Validade", width:120 }, { key:"valor", label:"Valor", width:120, align:"right" },
    { key:"forma", label:"Forma", width:130 }, { key:"situacao", label:"Situação", width:120 }];
  const rows = [
    { id:1, cells:{ pacote:{ primary:"Profissional", sub:"12 meses" }, inicio:"01/03/2026", fim:"28/02/2027", valor:"R$ 4.788,00", forma:"Pix", situacao:"Ativa" } },
    { id:2, cells:{ pacote:{ primary:"Profissional", sub:"12 meses" }, inicio:"01/03/2025", fim:"28/02/2026", valor:"R$ 4.320,00", forma:"Boleto", situacao:"Encerrada" } },
    { id:3, cells:{ pacote:{ primary:"Essencial", sub:"12 meses" }, inicio:"01/03/2024", fim:"28/02/2025", valor:"R$ 2.388,00", forma:"Boleto", situacao:"Encerrada" } },
  ];
  return (
    <>
      <div className="hrm-grid">
        <section className="hrm-card">
          <h3>Pacote ativo</h3>
          <dl className="hrm-kv">
            <dt>Pacote</dt><dd>{A.plano.nome}</dd>
            <dt>Vence em</dt><dd className="tabular">28/02/2027</dd>
            <dt>Usuários</dt><dd className="tabular">9 de {A.plano.usuarios}</dd>
            <dt>Locais</dt><dd className="tabular">3 de {A.plano.locais}</dd>
            <dt>Faturas no mês</dt><dd className="tabular">318 de ilimitado</dd>
          </dl>
        </section>
        <section className="hrm-card">
          <h3>Trocar de pacote</h3>
          <p className="cfg-p">A troca vale do próximo ciclo em diante; o saldo do ciclo atual entra como crédito.</p>
          <div className="cfg-acoes">
            <button className="os-btn primary" onClick={() => go("sa-pacotes")}>Ver pacotes</button>
            <button className="os-btn ghost" onClick={() => go("sa-assinaturas")}>Assinaturas SaaS</button>
          </div>
        </section>
      </div>
      {carregando ? <Skel n={4} /> : <Tabela cols={cols} rows={rows} altura={220} />}
      <div className="hrm-note-ds">
        <Nota tone="info" title="Quem manda aqui é o Superadmin">
          Esta é a visão do cliente (<code>/subscription</code>). Criar pacote, mudar preço e cortar acesso são do módulo
          Superadmin — o cliente assina, paga e vê o histórico.
        </Nota>
      </div>
    </>
  );
}

function Tela({ view, A }) {
  const { Aviso, useCarga } = U();
  const carregando = useCarga(360);
  const [aviso, setAviso] = useState(null);
  const anunciar = (m) => { setAviso(m); setTimeout(() => setAviso(null), 2800); };
  const mapa = {
    "cfg-locais":Locais, "cfg-fatura":Fatura, "cfg-barras":Barras, "cfg-impressoras":Impressoras,
    "cfg-impostos":Impostos, "cfg-modificadores":Modificadores, "cfg-servicos":Servicos, "cfg-pacote":Pacote,
  };
  const Comp = mapa[view];
  if (!Comp) return null;
  return <><Comp A={A} aviso={anunciar} carregando={carregando} />{Aviso ? <Aviso msg={aviso} /> : null}</>;
}

window.ConfigCadastros = { Tela, FORMAS, CONTAS };
})();
