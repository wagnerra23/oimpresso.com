// jana-metas.jsx — CADASTRO de metas (F1 [CC]): a camada que ficou em Blade AdminLTE.
// Absorve `Modules/Jana/Resources/views/metas/{index,create,edit,show}.blade.php`
// + `fontes/show.blade.php` para dentro da tela única da Jana — sem rota nova, sem .html novo.
// index  → tabela de cadastro (view "Cadastro" da seção Metas)
// create/edit → drawer de formulário (PT-02: detalhe/form em drawer, nunca modal full-screen)
// show   → seções "Apurações"/"Fonte" do drawer de detalhe + "Forçar reapuração" (Modal PT-04)
// Estado é local ao protótipo: salvar/reapurar não fala com servidor, só mostra o ritmo real.
const { useState: useStateMC, useMemo: useMemoMC, useEffect: useEffectMC } = React;

const JM_UNIDADES = [
  { value: "R$", label: "R$ (moeda)" },
  { value: "qtd", label: "Quantidade" },
  { value: "%", label: "Percentual" },
  { value: "dias", label: "Dias" }];

const JM_AGREGACOES = [
  { value: "soma", label: "Soma — acumula na janela" },
  { value: "media", label: "Média — valor médio da janela" },
  { value: "ultimo", label: "Último valor da janela" },
  { value: "contagem", label: "Contagem de registros" }];

const JM_JANELAS = [
  { value: "mensal", label: "Mensal" },
  { value: "trimestral", label: "Trimestral" },
  { value: "semanal", label: "Semanal" }];

// Fila do cadastro. `origem` = quem criou a meta (o Blade mostra a coluna crua);
// `fonte` = a consulta que alimenta a apuração (fontes/show.blade.php, hoje só leitura).
const JM_CAD = [
  { id: "m1", nome: "Faturamento mensal", slug: "faturamento_mensal", unidade: "R$", agregacao: "soma",
    janela: "mensal", alvo: "145000", origem: "sistema", escopo: "Business #164", ativo: true,
    ultima: "14/05/2026 06:02",
    apuracoes: [["14/05/2026", "47.010,00"], ["30/04/2026", "71.320,00"], ["31/03/2026", "84.155,40"], ["29/02/2026", "98.204,10"]],
    periodos: [{ id: "pd1", de: "01/05/2026", ate: "31/05/2026", alvo: "145000" }, { id: "pd2", de: "01/04/2026", ate: "30/04/2026", alvo: "140000" }],
    fonte: '{\n  "driver": "sql",\n  "tabela": "transactions",\n  "campo": "final_total",\n  "filtros": { "type": "sell", "status": "final" },\n  "escopo": "business_id",\n  "agregacao": "soma"\n}' },
  { id: "m2", nome: "Recuperação de vencidos", slug: "recuperacao_vencidos", unidade: "R$", agregacao: "soma",
    janela: "trimestral", alvo: "400000", origem: "jana", escopo: "Business #164", ativo: true,
    ultima: "14/05/2026 06:02",
    apuracoes: [["14/05/2026", "212.400,00"], ["30/04/2026", "233.100,00"], ["31/03/2026", "219.870,00"]],
    periodos: [{ id: "pd3", de: "01/04/2026", ate: "30/06/2026", alvo: "400000" }],
    fonte: '{\n  "driver": "sql",\n  "tabela": "transaction_payments",\n  "campo": "amount",\n  "filtros": { "vencido_na_baixa": true },\n  "escopo": "business_id",\n  "agregacao": "soma"\n}' },
  { id: "m4", nome: "Ticket médio", slug: "ticket_medio", unidade: "R$", agregacao: "media",
    janela: "mensal", alvo: "2400", origem: "sistema", escopo: "Business #164", ativo: true,
    ultima: "14/05/2026 06:02",
    apuracoes: [["14/05/2026", "1.890,00"], ["30/04/2026", "1.932,00"], ["31/03/2026", "1.988,00"]],
    periodos: [{ id: "pd4", de: "01/05/2026", ate: "31/05/2026", alvo: "2400" }],
    fonte: '{\n  "driver": "sql",\n  "tabela": "transactions",\n  "campo": "final_total",\n  "filtros": { "type": "sell" },\n  "escopo": "business_id",\n  "agregacao": "media"\n}' },
  { id: "m5", nome: "Novos clientes", slug: "novos_clientes", unidade: "qtd", agregacao: "contagem",
    janela: "mensal", alvo: "30", origem: "manual", escopo: "Business #164", ativo: true,
    ultima: "14/05/2026 06:02",
    apuracoes: [["14/05/2026", "34"], ["30/04/2026", "36"], ["31/03/2026", "31"]],
    periodos: [{ id: "pd5", de: "01/05/2026", ate: "31/05/2026", alvo: "30" }],
    fonte: '{\n  "driver": "sql",\n  "tabela": "contacts",\n  "filtros": { "type": "customer" },\n  "escopo": "business_id",\n  "agregacao": "contagem"\n}' },
  { id: "m6", nome: "Margem de contribuição", slug: "margem_contribuicao", unidade: "%", agregacao: "media",
    janela: "mensal", alvo: "38", origem: "sistema", escopo: "Business #164", ativo: true,
    ultima: "30/04/2026 06:02", pendente: true,
    apuracoes: [["30/04/2026", "31,00"], ["31/03/2026", "32,00"], ["29/02/2026", "30,00"]],
    periodos: [{ id: "pd6", de: "01/05/2026", ate: "31/05/2026", alvo: "38" }],
    fonte: '{\n  "driver": "job",\n  "job": "ApurarMargemJob",\n  "quando": "fechamento do mês",\n  "escopo": "business_id",\n  "agregacao": "media"\n}' },
  { id: "m7", nome: "Prazo médio de entrega", slug: "prazo_medio_entrega", unidade: "dias", agregacao: "media",
    janela: "mensal", alvo: "5", origem: "manual", escopo: "Business #164", ativo: false,
    ultima: "31/01/2026 06:02",
    apuracoes: [["31/01/2026", "6,40"], ["31/12/2025", "7,10"]],
    periodos: [{ id: "pd7", de: "01/01/2026", ate: "31/01/2026", alvo: "5" }],
    fonte: '{\n  "driver": "sql",\n  "tabela": "ordens_producao",\n  "campo": "dias_entrega",\n  "escopo": "business_id",\n  "agregacao": "media"\n}' },
  { id: "m8", nome: "Adesão ao ponto eletrônico", slug: "adesao_ponto", unidade: "%", agregacao: "media",
    janela: "mensal", alvo: "95", origem: "sistema", escopo: "Plataforma", ativo: false,
    ultima: "—", semApuracao: true, apuracoes: [],
    periodos: [],
    fonte: '{\n  "driver": "sql",\n  "tabela": "marcacoes",\n  "escopo": null,\n  "agregacao": "media"\n}' }];

// Store compartilhada: a tabela (dentro da seção Metas) e os overlays (no nível da página)
// mexem na mesma lista, e o drawer de detalhe consegue abrir o formulário sem prop-drilling.
const JmStore = { rows: JM_CAD.map((r) => ({ ...r })), form: null, fonte: null, reapurar: null, subs: new Set() };
function jmEmit() {JmStore.subs.forEach((f) => f());}
function useJmStore() {
  const [, tick] = useStateMC(0);
  useEffectMC(() => {
    const f = () => tick((n) => n + 1);
    JmStore.subs.add(f);
    return () => JmStore.subs.delete(f);
  }, []);
  return JmStore;
}
function jmCad(id) {return JmStore.rows.find((r) => r.id === id) || null;}
function jmAbrirForm(id) {JmStore.form = id ? { mode: "editar", id } : { mode: "nova" };jmEmit();}
function jmAbrirFonte(id) {JmStore.fonte = id;jmEmit();}
function jmAbrirReapurar(id) {JmStore.reapurar = id;jmEmit();}
function jmFecharOverlays() {JmStore.form = null;JmStore.fonte = null;JmStore.reapurar = null;jmEmit();}
function jmSalvar(patch) {
  if (patch.id && jmCad(patch.id)) {
    JmStore.rows = JmStore.rows.map((r) => r.id === patch.id ? { ...r, ...patch } : r);
  } else {
    JmStore.rows = [{ ...patch, id: "n" + Date.now(), origem: "manual", escopo: "Business #164",
      ultima: "—", semApuracao: true, apuracoes: [],
      fonte: '{\n  "driver": null,\n  "nota": "fonte ainda não configurada — sem fonte a meta não apura"\n}' },
    ...JmStore.rows];
  }
  jmEmit();
}
function jmToggleAtivo(id) {
  JmStore.rows = JmStore.rows.map((r) => r.id === id ? { ...r, ativo: !r.ativo } : r);
  jmEmit();
}

function jmSlugify(s) {
  return (s || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "")
  .replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "").slice(0, 40);
}
function jmAlvoFmt(r) {
  const n = Number(r.alvo);
  if (!isFinite(n)) return "—";
  if (r.unidade === "R$") return "R$ " + n.toLocaleString("pt-BR");
  if (r.unidade === "%") return n + "%";
  if (r.unidade === "dias") return n + " dias";
  return n.toLocaleString("pt-BR");
}
const JM_ORIGEM_LABEL = { sistema: "consulta do sistema", jana: "proposta da Jana", manual: "cadastro manual" };

// index.blade.php → tabela de cadastro. Colunas do Blade (Nome/Unidade/Origem/Ativo) mantidas,
// mais o que o operador precisa pra decidir: alvo, agregação, janela e última apuração.
function JmMetasCadastro({ onAbrir, onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { DataTable, DropdownMenu, Switch, EmptyState, Button } = DS;
  const JcIcon = window.JcIcon;
  const store = useJmStore();
  const [q, setQ] = useStateMC("");
  const [filtro, setFiltro] = useStateMC("ativas");
  const rows = useMemoMC(() => {
    const t = q.trim().toLowerCase();
    return store.rows.filter((r) => {
      if (filtro === "ativas" && !r.ativo) return false;
      if (filtro === "inativas" && r.ativo) return false;
      if (filtro === "plataforma" && r.escopo !== "Plataforma") return false;
      if (!t) return true;
      return (r.nome + " " + r.slug).toLowerCase().includes(t);
    });
  }, [store.rows, q, filtro]);

  const columns = [
  { key: "meta", label: "Meta" },
  { key: "alvo", label: "Alvo", align: "right" },
  { key: "agregacao", label: "Agregação" },
  { key: "origem", label: "Origem" },
  { key: "escopo", label: "Escopo" },
  { key: "ultima", label: "Última apuração" },
  { key: "ativo", label: "Ativo" },
  { key: "acoes", label: "", align: "right" }];

  const data = rows.map((r) => ({
    id: r.id,
    state: r.ativo ? undefined : "archived",
    meta: { primary: r.nome, sub: r.slug },
    alvo: <span className="jmc-mono">{jmAlvoFmt(r)}</span>,
    agregacao: <span className="jmc-dim">{r.agregacao} · {r.janela}</span>,
    origem: <span className="jmc-dim">{JM_ORIGEM_LABEL[r.origem] || r.origem}</span>,
    escopo: <span className={"jmc-escopo" + (r.escopo === "Plataforma" ? " plat" : "")}>{r.escopo}</span>,
    ultima: <span className={"jmc-mono" + (r.semApuracao ? " vazio" : "")}>{r.semApuracao ? "nunca apurada" : r.ultima}</span>,
    ativo: Switch ?
    <span onClick={(e) => e.stopPropagation()}><Switch checked={r.ativo} onChange={() => {jmToggleAtivo(r.id);onAviso?.(r.ativo ? "Meta desativada — sai do farol do painel, histórico preservado." : "Meta reativada — volta ao painel na próxima apuração.");}} /></span> :
    <span>{r.ativo ? "sim" : "não"}</span>,
    acoes: DropdownMenu ?
    <span className="jmc-acoes" onClick={(e) => e.stopPropagation()}><DropdownMenu align="right" width={218}
      trigger={<span className="jmc-kebab" role="button" tabIndex={0} aria-label={"Ações de " + r.nome}>···</span>}
      items={[
      { id: "abrir", label: "Ver apuração", onSelect: () => onAbrir?.(r.id) },
      { id: "editar", label: "Editar meta", onSelect: () => jmAbrirForm(r.id) },
      { id: "fonte", label: "Fonte do número", onSelect: () => jmAbrirFonte(r.id) },
      { id: "sep", separator: true },
      { id: "reapurar", label: "Forçar reapuração", onSelect: () => jmAbrirReapurar(r.id) },
      { id: "desativar", label: r.ativo ? "Desativar meta" : "Reativar meta", tone: r.ativo ? "danger" : undefined,
        onSelect: () => {jmToggleAtivo(r.id);onAviso?.(r.ativo ? "Meta desativada — sai do farol do painel." : "Meta reativada.");} }]} /></span> :

    <button className="jm-btn ghost" onClick={() => jmAbrirForm(r.id)}>Editar</button> }));


  return (
    <div className="jmc-wrap">
      <div className="jmc-toolbar">
        <div className="jm-search jmc-search">
          {JcIcon && <JcIcon name="search" />}
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nome ou slug" aria-label="Buscar meta" />
        </div>
        <div className="jm-mem-cats">
          {["ativas", "inativas", "plataforma", "todas"].map((f) =>
          <button key={f} className={"jm-cat" + (filtro === f ? " active" : "")} onClick={() => setFiltro(f)}>{f}</button>
          )}
        </div>
        <span className="jmc-count">{rows.length} de {store.rows.length}</span>
      </div>
      {rows.length === 0 ?
      EmptyState ?
      <EmptyState variant={q ? "no-results" : "first"}
        icon={JcIcon ? <JcIcon name="target" /> : null}
        title={q ? "Nenhuma meta com esse nome" : "Nenhuma meta cadastrada"}
        description={q ?
        "Busquei por nome e slug nas metas deste business — nada bateu com “" + q + "”." :
        "Sem meta cadastrada não existe farol: a Jana não tem com o que comparar o realizado."}
        action={q ?
        <button className="jm-btn" onClick={() => setQ("")}>Limpar busca</button> :
        <button className="jm-btn" onClick={() => jmAbrirForm(null)}>Nova meta</button>} /> :

      <div className="jm-mem-empty"><b>Nenhuma meta cadastrada.</b></div> :

      <div className="jmc-table">
          <DataTable columns={columns} rows={data} onRowClick={(r) => onAbrir?.(r.id)} />
        </div>}

      <p className="jmc-rodape">
        Escopo <code>business_id</code> da sessão. Metas de <b>Plataforma</b> (business nulo) só aparecem
        para quem tem <code>jana.superadmin</code> — aqui ficam visíveis, em cinza, para inspeção.
        {Button ? null : null}
      </p>
    </div>);

}

// create.blade.php + edit.blade.php → um formulário só, em drawer.
// O Blade de criação pedia slug à mão com `pattern="[a-z0-9_]+"`: aqui o slug é derivado do nome,
// editável antes de salvar e IMUTÁVEL depois (é a chave que a apuração já gravou).
function JmMetaFormDrawer({ onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Drawer, DrawerSection, Button, Input, Select, Switch, Alert } = DS;
  const store = useJmStore();
  const aberto = store.form;
  const base = aberto && aberto.mode === "editar" ? jmCad(aberto.id) : null;
  const [f, setF] = useStateMC(null);
  const [erros, setErros] = useStateMC({});
  const [slugManual, setSlugManual] = useStateMC(false);

  useEffectMC(() => {
    if (!aberto) {setF(null);return;}
    setErros({});
    setSlugManual(false);
    setF(base ? { ...base } : {
      nome: "", slug: "", unidade: "R$", agregacao: "soma", janela: "mensal",
      alvo: "", ativo: true, periodos: [] });
  }, [aberto && aberto.mode, aberto && aberto.id]);

  if (!Drawer || !aberto || !f) return null;
  const novo = !base;
  const set = (k, v) => setF((p) => ({ ...p, [k]: v }));
  const setNome = (v) => setF((p) => ({ ...p, nome: v, slug: slugManual || !novo ? p.slug : jmSlugify(v) }));

  const validar = () => {
    const e = {};
    if (!f.nome.trim()) e.nome = "Dê um nome — é o que aparece no painel.";
    if (!/^[a-z0-9_]+$/.test(f.slug || "")) e.slug = "Só minúsculas, números e _ (é a chave da apuração).";else
    if (novo && JmStore.rows.some((r) => r.slug === f.slug)) e.slug = "Já existe meta com esse slug neste business.";
    const n = Number(String(f.alvo).replace(",", "."));
    if (!f.alvo || !isFinite(n) || n <= 0) e.alvo = "Alvo precisa ser um número maior que zero.";
    setErros(e);
    return Object.keys(e).length === 0;
  };
  const salvar = () => {
    if (!validar()) return;
    jmSalvar({ ...f, id: base ? base.id : undefined, alvo: String(f.alvo).replace(",", ".") });
    jmFecharOverlays();
    onAviso?.(novo ?
    "Meta criada — entra no painel na próxima apuração (job de madrugada)." :
    "Meta salva. A apuração não é refeita: use “Forçar reapuração” se o alvo mudou.");
  };
  const addPeriodo = () => set("periodos", [...(f.periodos || []), { id: "p" + Date.now(), de: "", ate: "", alvo: f.alvo }]);
  const rmPeriodo = (id) => set("periodos", (f.periodos || []).filter((p) => p.id !== id));
  const setPeriodo = (id, k, v) => set("periodos", (f.periodos || []).map((p) => p.id === id ? { ...p, [k]: v } : p));

  return (
    <Drawer open onClose={jmFecharOverlays} width={560}
      title={novo ? "Nova meta" : "Editar: " + base.nome}
      subtitle={novo ?
      "O que a Jana vai comparar com o realizado, janela por janela" :
      "Slug e escopo são imutáveis — a apuração já gravou com essa chave"}
      badge={<span className="jm-badge">{novo ? "cadastro" : base.slug}</span>}
      footer={
      <div className="jm-drawer-foot">
          <Button variant="ghost" onClick={jmFecharOverlays}>Cancelar</Button>
          <span className="jm-foot-spacer" />
          <Button variant="primary" onClick={salvar}>{novo ? "Criar meta" : "Salvar"}</Button>
        </div>}>


      <DrawerSection title="Identificação">
        <Input label="Nome" value={f.nome} onChange={(e) => setNome(e.target.value)} error={erros.nome}
          help="Aparece no card do painel e nas respostas da Jana." />
        <Input label="Slug" value={f.slug} readOnly={!novo} error={erros.slug}
          onChange={(e) => {setSlugManual(true);set("slug", jmSlugify(e.target.value));}}
          help={novo ? "Derivado do nome. Depois de criada, não muda." : "Imutável — a série histórica está gravada nesta chave."} />
      </DrawerSection>

      <DrawerSection title="Como apura">
        <div className="jmc-f2">
          <Select label="Unidade" value={f.unidade} options={JM_UNIDADES} onChange={(e) => set("unidade", e.target.value)} />
          <Input label={"Alvo (" + f.unidade + ")"} value={f.alvo} onChange={(e) => set("alvo", e.target.value)} error={erros.alvo}
            placeholder={f.unidade === "R$" ? "145000" : "30"} help="Valor cheio, sem separador." />
        </div>
        <div className="jmc-f2">
          <Select label="Tipo de agregação" value={f.agregacao} options={JM_AGREGACOES} onChange={(e) => set("agregacao", e.target.value)} />
          <Select label="Janela" value={f.janela} options={JM_JANELAS} onChange={(e) => set("janela", e.target.value)} />
        </div>
        {Alert &&
        <Alert tone="info" title="O farol é veredito do servidor">
            Verde/amarelo/vermelho saem de <code>ApuracaoService::farol</code> comparando realizado × alvo da janela.
            Sem apuração na janela, a meta aparece cinza — “aguardando apuração” — em vez de chutar um veredito.
          </Alert>}

      </DrawerSection>

      <DrawerSection title="Alvo por período (opcional)">
        <div className="jmc-periodos">
          {(f.periodos || []).length === 0 &&
          <p className="jmc-dim">Nenhum período específico. O alvo geral ({jmAlvoFmt(f)}) vale para todas as janelas.</p>}

          {(f.periodos || []).map((p) =>
          <div key={p.id} className="jmc-periodo">
              <Input label="De" value={p.de} placeholder="dd/mm/aaaa" onChange={(e) => setPeriodo(p.id, "de", e.target.value)} />
              <Input label="Até" value={p.ate} placeholder="dd/mm/aaaa" onChange={(e) => setPeriodo(p.id, "ate", e.target.value)} />
              <Input label="Alvo" value={p.alvo} onChange={(e) => setPeriodo(p.id, "alvo", e.target.value)} />
              <button className="jm-btn ghost danger jmc-rm" onClick={() => rmPeriodo(p.id)} aria-label="Remover período">Remover</button>
            </div>
          )}
          <button className="jm-btn" onClick={addPeriodo}>Adicionar período</button>
        </div>
      </DrawerSection>

      <DrawerSection title="Situação">
        {Switch &&
        <Switch checked={!!f.ativo} onChange={(v) => set("ativo", v)}
          label="Meta ativa" sublabel="Inativa sai do farol do painel, mas o histórico de apuração é preservado." />}

        {!novo &&
        <ul className="jm-dr-src">
            <li>Origem: {JM_ORIGEM_LABEL[base.origem] || base.origem}</li>
            <li>Escopo: {base.escopo === "Plataforma" ? "Plataforma (business nulo)" : base.escopo}</li>
            <li>Fonte do número: <button className="jm-fato-ed jmc-link" onClick={() => jmAbrirFonte(base.id)}>ver consulta</button></li>
          </ul>}

      </DrawerSection>
    </Drawer>);

}

// fontes/show.blade.php → seção de leitura. Continua SÓ LEITURA de propósito:
// editor com prévia é a US-COPI-040, não foi decidido aqui.
function JmFonteDrawer() {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Drawer, DrawerSection, Button, Alert } = DS;
  const store = useJmStore();
  const r = store.fonte ? jmCad(store.fonte) : null;
  if (!Drawer || !r) return null;
  return (
    <Drawer open onClose={jmFecharOverlays} width={520}
      title={"Fonte · " + r.nome}
      subtitle="A consulta que alimenta a apuração desta meta"
      badge={<span className="jm-badge">{r.slug}</span>}
      footer={
      <div className="jm-drawer-foot">
          <Button variant="ghost" onClick={jmFecharOverlays}>Fechar</Button>
          <span className="jm-foot-spacer" />
          <Button variant="ghost" onClick={() => jmAbrirForm(r.id)}>Editar meta</Button>
        </div>}>


      <DrawerSection title="Definição">
        <pre className="jmc-json">{r.fonte}</pre>
      </DrawerSection>
      <DrawerSection title="Por que não dá pra editar aqui">
        {Alert ?
        <Alert tone="warn" title="Só leitura, por decisão">
            Mudar a fonte muda o significado da série já gravada. O editor com prévia do número
            antes de salvar é trabalho próprio (<code>US-COPI-040</code>) — até lá, alteração passa por quem
            tem acesso ao servidor.
          </Alert> :

        <p className="jmc-dim">Só leitura — editor com prévia é a US-COPI-040.</p>}
      </DrawerSection>
    </Drawer>);

}

// show.blade.php → "Forçar reapuração" com confirmação (o Blade disparava direto no clique).
function JmReapurarModal({ onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Modal, Button } = DS;
  const store = useJmStore();
  const r = store.reapurar ? jmCad(store.reapurar) : null;
  const [estado, setEstado] = useStateMC("idle");
  useEffectMC(() => {setEstado("idle");}, [store.reapurar]);
  if (!Modal || !r) return null;
  const rodar = () => {
    setEstado("rodando");
    setTimeout(() => {
      jmFecharOverlays();
      onAviso?.("Reapuração enfileirada — a janela corrente é recalculada em alguns minutos.");
    }, 900);
  };
  return (
    <Modal open onClose={jmFecharOverlays} width={440} title="Forçar reapuração"
      footer={
      <div className="jm-drawer-foot">
          <Button variant="ghost" onClick={jmFecharOverlays} disabled={estado === "rodando"}>Cancelar</Button>
          <Button variant="primary" onClick={rodar} disabled={estado === "rodando"}>
            {estado === "rodando" ? "Enfileirando…" : "Reapurar janela"}
          </Button>
        </div>}>


      <p className="jmc-modal-p">
        Recalcula <b>{r.nome}</b> na janela corrente e <b>sobrescreve</b> o valor apurado
        em {r.semApuracao ? "—" : r.ultima}.
      </p>
      <ul className="jm-dr-src">
        <li>Roda como job no servidor — o número não muda na hora</li>
        <li>Só a janela corrente é refeita; o histórico fica</li>
        <li>Alvo e farol seguem a definição atual da meta</li>
      </ul>
    </Modal>);

}

// show.blade.php (tabela "Últimas apurações") → seção do drawer de detalhe da meta.
function JmApuracoesSecao({ id, onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { DrawerSection } = DS;
  const store = useJmStore();
  const r = id ? jmCad(id) : null;
  if (!DrawerSection || !r) return null;
  return (
    <DrawerSection title={"Apurações gravadas · " + (r.semApuracao ? "nenhuma ainda" : r.apuracoes.length + " janelas")}>
      {r.apuracoes.length === 0 ?
      <p className="jmc-dim">Nenhuma apuração ainda — a meta entra no farol depois do primeiro job.</p> :

      <table className="jmc-apur">
          <thead><tr><th>Data ref.</th><th>Realizado</th></tr></thead>
          <tbody>
            {r.apuracoes.map(([d, v]) =>
          <tr key={d}><td>{d}</td><td className="jmc-mono">{v}</td></tr>
          )}
          </tbody>
        </table>}

      <div className="jmc-apur-acts">
        <button className="jm-btn ghost" onClick={() => jmAbrirFonte(r.id)}>Fonte do número</button>
        <button className="jm-btn ghost" onClick={() => jmAbrirReapurar(r.id)}>Forçar reapuração</button>
      </div>
    </DrawerSection>);

}

function JmMetasOverlays({ onAviso }) {
  return (
    <>
      <JmMetaFormDrawer onAviso={onAviso} />
      <JmFonteDrawer />
      <JmReapurarModal onAviso={onAviso} />
    </>);

}

Object.assign(window, { JmMetasCadastro, JmMetaFormDrawer, JmFonteDrawer, JmReapurarModal,
  JmApuracoesSecao, JmMetasOverlays, jmAbrirForm, jmAbrirFonte, jmAbrirReapurar, jmCadMeta: jmCad });
