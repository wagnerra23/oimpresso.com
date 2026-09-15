// jana-telas-novas.jsx — as telas da Jana que NUNCA foram desenhadas (F1 [CC]).
// Lido no `main` em 2026-08-27 antes de desenhar:
//   Modules/Jana/Http/routes.php · AlertasController + UpdateAlertasConfigRequest +
//   views/alertas/{index,config}.blade.php · Services/AlertaService.php ·
//   Notifications/MetaDesvioNotification.php · AcaoHitlController + AcaoHitlService ·
//   SuperadminController + views/superadmin/metas.blade.php · Resources/permissions.php.
// Três áreas, nenhuma rota nova no protótipo (abas de área da tela única da Jana):
//   JmAlertas     → /ia/alertas + /ia/alertas/config  (index é stub; config valida e DESCARTA)
//   JmAcoesFila   → /ia/acoes    (as 2 rotas HITL existem; a FILA é "PR próprio", nunca desenhada)
//   JmPlataforma  → /ia/superadmin/metas + /ia/install (Blade AdminLTE cru, gate jana.superadmin)
// Permissão é camada de tela aqui, não enfeite: `papel` decide o que existe.
const { useState: useStateTN, useEffect: useEffectTN, useMemo: useMemoTN } = React;

// ── Permissão (Resources/permissions.php, lido no main) ─────────────────────────
// funcionaria: jana.access + jana.chat · dona: idem + metas.manage (e Gate::before
// de Admin#business_id passa em QUALQUER ability) · superadmin: + jana.superadmin.
const JTN_PERMS = {
  funcionaria: ["jana.access", "jana.chat"],
  dona: ["jana.access", "jana.chat", "jana.metas.manage"],
  superadmin: ["jana.access", "jana.chat", "jana.metas.manage", "jana.superadmin", "jana.mcp.usage.all"] };

function jtnCan(papel, key) {return (JTN_PERMS[papel] || JTN_PERMS.dona).indexOf(key) >= 0;}

function JtnSemPermissao({ perm, o_que }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { EmptyState } = DS;
  const t = "Esta área pede " + perm;
  const d = o_que + " Quem não tem a permissão não vê a aba nem a consulta é feita — " +
  "o gate está no grupo /ia (can:jana.access) e na própria rota, não só no menu.";
  return EmptyState ?
  <EmptyState variant="no-perm" title={t} description={d} /> :
  <div className="jm-mem-empty"><b>{t}</b><small>{d}</small></div>;
}

// ═══════════════════════════════════════════════════════════════════════════════
// A) ALERTAS — /ia/alertas (index) + /ia/alertas/config
// O Blade index diz, com todas as letras, que "a lista de alertas ainda não existe".
// Esta é a lista: o desvio que o AlertaService JÁ calcula, com projeção linear,
// severidade por múltiplo do threshold (1x baixa · 1,5x média · 3x alta) e o canal
// que a MetaDesvioNotification usa hoje (database + broadcast = in-app).
// ═══════════════════════════════════════════════════════════════════════════════
const JTN_ALERTAS = [
  { id: "al1", meta: "Faturamento mensal", slug: "faturamento_mensal", dataRef: "14/05/2026",
    projetado: 72500, realizado: 47010, unidade: "R$", status: "novo" },
  { id: "al2", meta: "Margem de contribuição", slug: "margem_contribuicao", dataRef: "30/04/2026",
    projetado: 38, realizado: 31, unidade: "%", status: "novo" },
  { id: "al3", meta: "Ticket médio", slug: "ticket_medio", dataRef: "14/05/2026",
    projetado: 2400, realizado: 1890, unidade: "R$", status: "lido" },
  { id: "al4", meta: "Recuperação de vencidos", slug: "recuperacao_vencidos", dataRef: "14/05/2026",
    projetado: 200000, realizado: 212400, unidade: "R$", status: "lido" },
  { id: "al5", meta: "Novos clientes", slug: "novos_clientes", dataRef: "14/05/2026",
    projetado: 30, realizado: 34, unidade: "qtd", status: "silenciado" },
  { id: "al6", meta: "Prazo médio de entrega", slug: "prazo_medio_entrega", dataRef: "31/01/2026",
    projetado: 5, realizado: 6.4, unidade: "dias", status: "silenciado" }];

function jtnSeveridade(desvio, threshold) {
  const abs = Math.abs(desvio);
  if (abs >= threshold * 3) return "alta";
  if (abs >= threshold * 1.5) return "media";
  return "baixa";
}
function jtnValor(v, unidade) {
  if (unidade === "R$") return "R$ " + v.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (unidade === "%") return String(v).replace(".", ",") + "%";
  if (unidade === "dias") return String(v).replace(".", ",") + " dias";
  return v.toLocaleString("pt-BR");
}
const JTN_SEV_LABEL = { alta: "alta", media: "média", baixa: "baixa" };

// Estado da config no protótipo. Chaves e limites são os do UpdateAlertasConfigRequest
// (enabled · canais.{email,whatsapp,dashboard} · thresholds.{meta_atingida,meta_drift}
// · silencio_horario_{inicio,fim}) — não invento nome de campo.
const JTN_CFG_PADRAO = {
  enabled: true,
  canais: { dashboard: true, email: false, whatsapp: false },
  thresholds: { meta_atingida: 100, meta_drift: 10 },
  silencio_horario_inicio: "22:00",
  silencio_horario_fim: "07:00" };

// Silêncio por meta é estado COMPARTILHADO: o contador da aba e a lista têm que dizer a
// mesma coisa. Store minúscula com subscribe (mesmo padrão do JmStore de jana-metas.jsx).
const JtnSilStore = { sil: {}, subs: new Set() };
try {JtnSilStore.sil = JSON.parse(localStorage.getItem("oimpresso.jana.alertas.sil") || "{}");} catch (e) {}
function jtnEmitSil() {
  try {localStorage.setItem("oimpresso.jana.alertas.sil", JSON.stringify(JtnSilStore.sil));} catch (e) {}
  JtnSilStore.subs.forEach((f) => f());
}
function jtnSilenciada(a) {
  const o = JtnSilStore.sil[a.id];
  return o === undefined ? a.status === "silenciado" : !!o;
}
function jtnToggleSil(a) {JtnSilStore.sil = { ...JtnSilStore.sil, [a.id]: !jtnSilenciada(a) };jtnEmitSil();}
function jtnSubscribeSil(fn) {JtnSilStore.subs.add(fn);return () => JtnSilStore.subs.delete(fn);}
function useJtnSil() {
  const [, tick] = useStateTN(0);
  useEffectTN(() => jtnSubscribeSil(() => tick((n) => n + 1)), []);
  return JtnSilStore.sil;
}

function jtnCorte() {
  try {
    const c = JSON.parse(localStorage.getItem("oimpresso.jana.alertas") || "{}");
    const v = Number(c && c.thresholds && c.thresholds.meta_drift);
    return isFinite(v) && v >= 0 ? v : JTN_CFG_PADRAO.thresholds.meta_drift;
  } catch (e) {return JTN_CFG_PADRAO.thresholds.meta_drift;}
}
// Contagem da aba: MESMA regra da lista no estado padrão (|desvio| > corte E não
// silenciada) — silenciada não notifica, então não pode entrar num contador de aba.
function jtnContarAlertas() {
  const th = jtnCorte();
  return JTN_ALERTAS.filter((a) =>
  !jtnSilenciada(a) && Math.abs((a.realizado - a.projetado) / a.projetado * 100) > th).length;
}

function JmAlertas({ papel = "dona", onAbrirMeta, onPerguntar, onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { DataTable, StatusBadge, Alert, EmptyState, Button, DropdownMenu } = DS;
  const JcIcon = window.JcIcon;
  const podeConfig = jtnCan(papel, "jana.metas.manage");
  const [cfg, setCfg] = useStateTN(() => {
    try {return { ...JTN_CFG_PADRAO, ...JSON.parse(localStorage.getItem("oimpresso.jana.alertas") || "{}") };}
    catch (e) {return JTN_CFG_PADRAO;}
  });
  useEffectTN(() => {
    try {localStorage.setItem("oimpresso.jana.alertas", JSON.stringify(cfg));} catch (e) {}
  }, [cfg]);
  const [config, setConfig] = useStateTN(false);
  const [sev, setSev] = useStateTN("todas");
  const [status, setStatus] = useStateTN("abertos");
  const sil = useJtnSil();

  const th = Number(cfg.thresholds.meta_drift) || 10;
  const linhas = useMemoTN(() => JTN_ALERTAS.map((a) => {
    const desvio = (a.realizado - a.projetado) / a.projetado * 100;
    const st = jtnSilenciada(a) ? "silenciado" : a.status === "silenciado" ? "novo" : a.status;
    return { ...a, desvio, severidade: jtnSeveridade(desvio, th), status: st,
      dispara: Math.abs(desvio) > th };
  }), [th, sil]);

  const rows = linhas.filter((a) => {
    if (!a.dispara) return false;
    if (sev !== "todas" && a.severidade !== sev) return false;
    if (status === "abertos" && a.status === "silenciado") return false;
    if (status === "silenciados" && a.status !== "silenciado") return false;
    return true;
  });
  const abaixoDoCorte = linhas.filter((a) => !a.dispara).length;

  const columns = [
  { key: "meta", label: "Meta" },
  { key: "desvio", label: "Desvio", align: "right" },
  { key: "sev", label: "Severidade" },
  { key: "conta", label: "Projetado × realizado", align: "right" },
  { key: "data", label: "Data ref." },
  { key: "canal", label: "Chegou por" },
  { key: "acoes", label: "", align: "right" }];

  const canais = [cfg.canais.dashboard && "in-app", cfg.canais.email && "e-mail", cfg.canais.whatsapp && "WhatsApp"].
  filter(Boolean).join(" · ") || "nenhum canal ligado";

  const data = rows.map((a) => ({
    id: a.id,
    state: a.severidade === "alta" && a.status !== "silenciado" ? "urgent" : a.status === "silenciado" ? "archived" : undefined,
    meta: { primary: a.meta, sub: a.slug },
    desvio: <span className={"jtn-desvio " + (a.desvio < 0 ? "neg" : "pos")}>
        {(a.desvio < 0 ? "" : "+") + a.desvio.toFixed(1).replace(".", ",")}%
      </span>,
    sev: <span className="jtn-sev"><i className={"jtn-dot " + a.severidade} />{JTN_SEV_LABEL[a.severidade]}</span>,
    conta: <span className="jtn-num">{jtnValor(a.projetado, a.unidade)} × {jtnValor(a.realizado, a.unidade)}</span>,
    data: <span className="jtn-num">{a.dataRef}</span>,
    canal: <span className={"jtn-num" + (a.status === "silenciado" ? " jtn-sil" : "")}>{a.status === "silenciado" ? "silenciado" : canais}</span>,
    acoes: DropdownMenu ?
    <span className="jmc-acoes" onClick={(e) => e.stopPropagation()}><DropdownMenu align="right" width={230}
      trigger={<span className="jmc-kebab" role="button" tabIndex={0} aria-label={"Ações do alerta de " + a.meta}>···</span>}
      items={[
      { id: "meta", label: "Abrir a meta", onSelect: () => onAbrirMeta?.(a.slug) },
      { id: "perg", label: "Perguntar por que caiu", onSelect: () => onPerguntar?.("Por que " + a.meta.toLowerCase() + " ficou " + Math.abs(a.desvio).toFixed(0) + "% fora da projeção em " + a.dataRef + "?") },
      { id: "sep", separator: true },
      { id: "sil", label: a.status === "silenciado" ? "Voltar a alertar" : "Silenciar esta meta",
        tone: a.status === "silenciado" ? undefined : "danger",
        onSelect: () => {
          const eraSilenciada = a.status === "silenciado";
          jtnToggleSil(a);
          onAviso?.(eraSilenciada ?
          "Volta a alertar na próxima apuração." :
          "Silenciada. O desvio continua sendo calculado e gravado — só não notifica.", "warn");
        } }]} /></span> :
    null }));

  return (
    <div className="jtn-wrap" data-screen-label="Jana — Alertas">
      {Alert &&
      <Alert tone="warn" title="Hoje o servidor não guarda esta configuração"
      action={podeConfig ? <button className="jm-btn ghost" onClick={() => setConfig(true)}>Ver a configuração</button> : null}>
          O <code>AlertasController@updateConfig</code> valida a whitelist e devolve
          “nada foi alterado” — a persistência é a <code>US-COPI-061</code>. Em produção o alerta
          dispara com <b>desvio de 10%</b> e chega <b>in-app</b>, fixo no código. Aqui você mexe nos
          valores pra ver o efeito na lista; o que salva é o protótipo, não a empresa.
        </Alert>}

      <div className="jtn-toolbar">
        <div className="jm-mem-cats">
          {["todas", "alta", "media", "baixa"].map((f) =>
          <button key={f} className={"jm-cat" + (sev === f ? " active" : "")} onClick={() => setSev(f)}>
              {f === "todas" ? "todas" : JTN_SEV_LABEL[f]}
            </button>
          )}
        </div>
        <div className="jm-mem-cats">
          {["abertos", "silenciados", "todos"].map((f) =>
          <button key={f} className={"jm-cat" + (status === f ? " active" : "")} onClick={() => setStatus(f)}>{f}</button>
          )}
        </div>
        <div className="jtn-right">
          <span className="jmc-count">{rows.length} disparando · corte em {th}%</span>
          {podeConfig ?
          Button ?
          <Button variant="ghost" onClick={() => setConfig(true)}>Configurar alertas</Button> :
          <button className="jm-btn ghost" onClick={() => setConfig(true)}>Configurar alertas</button> :
          null}
        </div>
      </div>

      {rows.length === 0 ?
      EmptyState ?
      <EmptyState variant={status === "silenciados" ? "filtered" : "done"}
        icon={JcIcon ? <JcIcon name="check" /> : null}
        title={status === "silenciados" ? "Nenhuma meta silenciada" : "Nenhum desvio acima do corte"}
        description={status === "silenciados" ?
        "Silenciar é por meta e não apaga nada: o desvio continua calculado e gravado, só não notifica." :
        "Com corte em " + th + "%, as " + linhas.length + " metas apuradas estão dentro da projeção linear do período."} /> :
      <div className="jm-mem-empty"><b>Nenhum desvio acima do corte.</b></div> :
      <div className="jmc-table"><DataTable columns={columns} rows={data} /></div>}

      <p className="jtn-nota">
        Projeção é linear entre <code>data_ini</code> e <code>data_fim</code> do período vigente
        (<code>AlertaService::avaliar</code>); severidade é múltiplo do corte — 1× baixa, 1,5× média, 3× alta.
        {abaixoDoCorte > 0 && " " + abaixoDoCorte + " meta(s) apurada(s) ficaram abaixo do corte e por isso não aparecem."}
        {" "}Sem período ativo ou sem apuração o serviço volta calado: não existe alerta sem com o que comparar.
      </p>

      <JtnAlertasConfig open={config} onClose={() => setConfig(false)} cfg={cfg} setCfg={setCfg} onAviso={onAviso} />
    </div>);

}

// /ia/alertas/config — o formulário que o Blade desenhou desabilitado.
// Campos, limites e MENSAGENS de erro são os do UpdateAlertasConfigRequest.
function JtnAlertasConfig({ open, onClose, cfg, setCfg, onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Drawer, DrawerSection, Button, Input, Switch, Alert } = DS;
  const [f, setF] = useStateTN(cfg);
  const [erros, setErros] = useStateTN({});
  useEffectTN(() => {if (open) {setF(cfg);setErros({});}}, [open]);
  if (!Drawer || !open) return null;

  const setTh = (k, v) => setF((p) => ({ ...p, thresholds: { ...p.thresholds, [k]: v } }));
  const setCanal = (k, v) => setF((p) => ({ ...p, canais: { ...p.canais, [k]: v } }));
  const validar = () => {
    const e = {};
    const ma = Number(f.thresholds.meta_atingida), md = Number(f.thresholds.meta_drift);
    if (!isFinite(ma) || ma < 0) e.meta_atingida = "Precisa ser um número de 0 a 200.";else
    if (ma > 200) e.meta_atingida = "O threshold de meta atingida não pode passar de 200%.";
    if (!isFinite(md) || md < 0) e.meta_drift = "Precisa ser um número de 0 a 100.";else
    if (md > 100) e.meta_drift = "O threshold de drift não pode passar de 100%.";
    const hhmm = /^([01]\d|2[0-3]):[0-5]\d$/;
    if (f.silencio_horario_inicio && !hhmm.test(f.silencio_horario_inicio)) e.ini = "Use o formato HH:MM (ex: 22:00).";
    if (f.silencio_horario_fim && !hhmm.test(f.silencio_horario_fim)) e.fim = "Use o formato HH:MM (ex: 07:00).";
    setErros(e);
    return Object.keys(e).length === 0;
  };
  const salvar = () => {
    if (!validar()) return;
    setCfg({ ...f, thresholds: { meta_atingida: Number(f.thresholds.meta_atingida), meta_drift: Number(f.thresholds.meta_drift) } });
    onClose();
    onAviso?.("Corte salvo no protótipo — a lista já usa o valor novo. No vivo, a rota ainda descarta.", "warn");
  };
  const nenhumCanal = !f.canais.dashboard && !f.canais.email && !f.canais.whatsapp;

  return (
    <Drawer open onClose={onClose} width={520}
      title="Configurar alertas"
      subtitle="Quando a Jana interrompe você — e por onde"
      badge={<span className="jm-badge">por empresa</span>}
      footer={
      <div className="jm-drawer-foot">
          <Button variant="ghost" onClick={onClose}>Cancelar</Button>
          <span className="jm-foot-spacer" />
          <Button variant="primary" onClick={salvar}>Salvar configuração</Button>
        </div>}>

      <DrawerSection title="Ligado">
        {Switch &&
        <Switch checked={!!f.enabled} onChange={(v) => setF((p) => ({ ...p, enabled: v }))}
          label="Alertar desvio de meta" sublabel="Desligado, a Jana segue apurando e gravando o desvio — só não interrompe ninguém." />}
      </DrawerSection>

      <DrawerSection title="Corte do desvio">
        <div className="jtn-cfg2">
          <Input label="Drift aceitável (%)" value={String(f.thresholds.meta_drift)} error={erros.meta_drift}
            onChange={(e) => setTh("meta_drift", e.target.value)}
            help="Acima disso vira alerta. Produção usa 10%." />
          <Input label="Meta atingida (%)" value={String(f.thresholds.meta_atingida)} error={erros.meta_atingida}
            onChange={(e) => setTh("meta_atingida", e.target.value)}
            help="Percentual do alvo que conta como batida — até 200%." />
        </div>
      </DrawerSection>

      <DrawerSection title="Canais">
        {Switch &&
        <>
            <Switch checked={!!f.canais.dashboard} onChange={(v) => setCanal("dashboard", v)}
            label="In-app (painel e sino)" sublabel="É o canal que existe hoje: MetaDesvioNotification grava em database e faz broadcast." />
            <Switch checked={!!f.canais.email} onChange={(v) => setCanal("email", v)}
            label="E-mail" sublabel="Um por desvio, com projetado × realizado e o link da meta." />
            <Switch checked={!!f.canais.whatsapp} onChange={(v) => setCanal("whatsapp", v)}
            label="WhatsApp" sublabel="Só desvio de severidade alta — e nunca dentro do silêncio noturno." />
          </>}
        {nenhumCanal && Alert &&
        <Alert tone="danger" title="Sem canal, o alerta não sai de lugar nenhum">
            O desvio continua gravado e visível nesta lista, mas ninguém é avisado. Se é isso que você
            quer, desligue “Alertar desvio de meta” — é mais honesto que deixar ligado sem canal.
          </Alert>}
      </DrawerSection>

      <DrawerSection title="Silêncio noturno">
        <div className="jtn-cfg2">
          <Input label="A partir de" value={f.silencio_horario_inicio || ""} error={erros.ini}
            placeholder="22:00" onChange={(e) => setF((p) => ({ ...p, silencio_horario_inicio: e.target.value }))} />
          <Input label="Até" value={f.silencio_horario_fim || ""} error={erros.fim}
            placeholder="07:00" onChange={(e) => setF((p) => ({ ...p, silencio_horario_fim: e.target.value }))} />
        </div>
        <p className="jtn-nota">Em branco nos dois campos = sem janela de silêncio. O que cair dentro dela
        é acumulado e entra no brief da manhã, não é descartado.</p>
      </DrawerSection>
    </Drawer>);

}

// ═══════════════════════════════════════════════════════════════════════════════
// B) AÇÕES — /ia/acoes (a fila que o AcaoHitlController chama de "PR próprio")
// As 5 chaves e os 5 rótulos de CTA são os do AcaoHitlService::ACOES, byte a byte.
// `alcance` null = a ação é LEITURA (não manda mensagem pra ninguém) — diferente de 0.
// ═══════════════════════════════════════════════════════════════════════════════
const JTN_HITL = [
  { key: "regua-whatsapp", titulo: "Régua de cobrança no WhatsApp", cta: "Revisar régua", alcance: 14,
    resumo: "Mensagem de cobrança para 14 venda(s) vencida(s), somando R$ 38.420,00. Uma mensagem por cliente, com o valor e o vencimento de cada título — nada agregado, nada genérico.",
    contexto: [["overdueCount", "14"], ["overdueValue", "38420.00"]] },
  { key: "negociar-top", titulo: "Negociar com o maior devedor", cta: "Revisar proposta", alcance: 1,
    resumo: "Proposta de negociação para Transportes Martinho — R$ 11.900,00. Contato direto, uma pessoa só: não entra na régua automática.",
    contexto: [["topDevedor.name", "Transportes Martinho"], ["topDevedor.total", "11900.00"]] },
  { key: "investigar-ticket", titulo: "Investigar a queda do ticket médio", cta: "Revisar recorte", alcance: null,
    resumo: "Recorte do ticket médio (R$ 1.890,00) por produto e por vendedor na janela de 30 dias, pra achar o mix que puxou pra baixo. Nenhuma mensagem sai: é leitura.",
    contexto: [["ticketMedio", "1890.00"]] },
  { key: "pix-adocao", titulo: "Adoção de PIX contra o faturado", cta: "Revisar leitura", alcance: null,
    resumo: "Leitura da adoção de PIX de hoje contra o faturado, com a quebra por forma de pagamento dos últimos 30 dias. Nenhuma mensagem sai: é leitura.",
    contexto: [["methodsAgg", "5 formas"]] },
  { key: "preventivo-pendentes", titulo: "Lembrete antes do vencimento", cta: "Revisar lembrete", alcance: null,
    resumo: "Lembrete amigável para os títulos que ainda NÃO venceram — antes da régua. Um por cliente, citando a data de vencimento. A receber hoje: R$ 52.310,00.",
    contexto: [["totalAReceber", "52310.00"]] }];

function JmAcoesFila({ papel = "dona", onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Alert, Button, Modal } = DS;
  const [aberta, setAberta] = useStateTN(null);
  const [recibos, setRecibos] = useStateTN({});
  const [enviando, setEnviando] = useStateTN(false);
  const [aba, setAba] = useStateTN("sugeridas");

  if (!jtnCan(papel, "jana.access")) return <JtnSemPermissao perm="jana.access" o_que="A fila de ações é do painel da Jana." />;

  const aprovar = (a) => {
    setEnviando(true);
    setTimeout(() => {
      setEnviando(false);
      setRecibos((r) => ({ ...r, [a.key]: { quem: "você", quando: new Date().toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }) } }));
      setAberta(null);
      onAviso?.("Aprovação registrada — nada sai antes do envio ser ligado.", "ok");
    }, 800);
  };

  const lista = JTN_HITL.filter((a) => aba === "sugeridas" ? !recibos[a.key] : !!recibos[a.key]);

  return (
    <div className="jtn-wrap" data-screen-label="Jana — Ações">
      {Alert &&
      <Alert tone="info" title="Aprovar registra a decisão. Nada é enviado.">
          As duas rotas que existem são prévia e aprovação (<code>jana.acoes.previa</code> ·
          <code> jana.acoes.aprovar</code>): a aprovação grava um recibo em <code>jana_acao_aprovacoes</code>
          com o texto que o servidor gerou. O disparo (WhatsApp/e-mail) é trabalho próprio — por isso o
          botão diz “Revisar”, e não “Disparar”.
        </Alert>}

      <div className="jtn-toolbar">
        <div className="jm-mem-cats">
          {["sugeridas", "aprovadas"].map((f) =>
          <button key={f} className={"jm-cat" + (aba === f ? " active" : "")} onClick={() => setAba(f)}>
              {f}{f === "aprovadas" && Object.keys(recibos).length ? " · " + Object.keys(recibos).length : ""}
            </button>
          )}
        </div>
        <span className="jtn-right jmc-count">prévia e alcance vêm do servidor</span>
      </div>

      <div className="jtn-acoes">
        {lista.length === 0 &&
        <div className="jm-mem-empty">
            <b>{aba === "aprovadas" ? "Nenhuma ação aprovada ainda." : "Todas as sugestões de hoje já foram revisadas."}</b>
            <small>{aba === "aprovadas" ?
          "O recibo aparece aqui no instante em que você aprova — com quem aprovou e quando." :
          "A Jana propõe de novo na próxima apuração, se o número continuar pedindo."}</small>
          </div>}
        {lista.map((a) => {
          const rec = recibos[a.key];
          return (
            <div key={a.key} className={"jtn-acao" + (rec ? " aprovada" : "")}>
              <div>
                <div className="jtn-acao-t">
                  {a.titulo}
                  <span className="jtn-acao-k">{a.key}</span>
                </div>
                <p className="jtn-acao-d">{a.resumo}</p>
                <div className="jtn-acao-m">
                  <span className={"jtn-tipo " + (a.alcance == null ? "leitura" : "envio")}>
                    {a.alcance == null ? "leitura" : "envio"}
                  </span>
                  <span>{a.alcance == null ? "não manda mensagem pra ninguém" : a.alcance + " destinatário(s), um por cliente"}</span>
                </div>
              </div>
              <div className="jtn-acao-cta">
                {rec ?
                <>
                    <span className="jtn-recibo">aprovada · {rec.quando}<br />por {rec.quem}</span>
                    <button className="jm-btn ghost" onClick={() => setAberta(a)}>Ver o recibo</button>
                  </> :
                Button ?
                <Button variant="ghost" onClick={() => setAberta(a)}>{a.cta}</Button> :
                <button className="jm-btn" onClick={() => setAberta(a)}>{a.cta}</button>}
              </div>
            </div>);
        })}
      </div>

      <p className="jtn-nota">
        Fila por empresa: o <code>business_id</code> vem da sessão, nunca do request. Chave desconhecida
        volta 404 no controller e no service — quem chama o service direto (job, tinker) topa no mesmo muro.
      </p>

      {Modal && aberta &&
      <Modal open onClose={() => setAberta(null)} width={480} title={recibos[aberta.key] ? "Recibo da aprovação" : "Prévia · " + aberta.titulo}
      footer={
      <div className="jm-drawer-foot">
            <Button variant="ghost" onClick={() => setAberta(null)} disabled={enviando}>Fechar</Button>
            {!recibos[aberta.key] &&
        <Button variant="primary" onClick={() => aprovar(aberta)} disabled={enviando}>
                {enviando ? "Registrando…" : "Aprovar"}
              </Button>}
          </div>}>
          <p className="jmc-modal-p">{aberta.resumo}</p>
          <h4 className="jtn-h3">Contexto gravado com a aprovação</h4>
          <table className="jmc-apur">
            <tbody>
              {aberta.contexto.map(([k, v]) =>
            <tr key={k}><td><code>{k}</code></td><td className="jmc-mono">{v}</td></tr>
            )}
              <tr><td><code>alcance</code></td><td className="jmc-mono">{aberta.alcance == null ? "null — é leitura" : aberta.alcance}</td></tr>
            </tbody>
          </table>
          <p className="jtn-nota">O texto acima é o do servidor no instante da leitura — a tela exibe,
          não calcula. Aprovar grava esse mesmo texto no recibo; o front não reescreve o que foi aprovado.</p>
        </Modal>}
    </div>);

}

// ═══════════════════════════════════════════════════════════════════════════════
// C) PLATAFORMA — /ia/superadmin/metas + /ia/install (gate jana.superadmin)
// O Blade lista as duas coleções cruas. A agregação cross-business prometida no
// docblock NÃO existe (medido: zero sum/count/groupBy no controller) — a tela diz isso.
// ═══════════════════════════════════════════════════════════════════════════════
const JTN_PLAT = [
  { nome: "Adesão ao ponto eletrônico", unidade: "%", origem: "sistema", slug: "adesao_ponto" },
  { nome: "Conformidade fiscal (NF-e sem rejeição)", unidade: "%", origem: "sistema", slug: "conformidade_nfe" },
  { nome: "Retenção de business ativos", unidade: "%", origem: "manual", slug: "retencao_business" }];

const JTN_CLIENTES = [
  { biz: 164, nome: "Faturamento mensal", unidade: "R$", periodo: "01/05–31/05", ultima: "14/05/2026", empresa: "Martinho Oficina" },
  { biz: 164, nome: "Recuperação de vencidos", unidade: "R$", periodo: "01/04–30/06", ultima: "14/05/2026", empresa: "Martinho Oficina" },
  { biz: 4, nome: "Ticket médio", unidade: "R$", periodo: "01/05–31/05", ultima: "14/05/2026", empresa: "ROTA LIVRE" },
  { biz: 4, nome: "Novos clientes", unidade: "qtd", periodo: "01/05–31/05", ultima: "14/05/2026", empresa: "ROTA LIVRE" },
  { biz: 91, nome: "Margem de contribuição", unidade: "%", periodo: "01/05–31/05", ultima: "—", empresa: "Gráfica Sul" }];

function JmPlataforma({ papel = "superadmin", onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { DataTable, Alert, Button, Modal, EmptyState } = DS;
  const [confirmar, setConfirmar] = useStateTN(null);

  if (!jtnCan(papel, "jana.superadmin"))
  return <JtnSemPermissao perm="jana.superadmin"
    o_que="Metas da plataforma (business nulo) e a visão cross-business das metas de clientes." />;

  const rodar = () => {
    const acao = confirmar;
    setConfirmar(null);
    onAviso?.(acao === "uninstall" ?
    "Desinstalação enfileirada — no vivo isso roda o rollback das 21 migrations." :
    acao === "update" ?
    "Atualização enfileirada — roda as migrations pendentes e grava a versão nova." :
    "Instalação enfileirada — migrations + seeders do módulo.", acao === "uninstall" ? "danger" : "ok");
  };

  return (
    <div className="jtn-plat" data-screen-label="Jana — Plataforma">
      {Alert &&
      <Alert tone="danger" title="Gate desta tela não separa dono de empresa de superadmin">
          A rota confere <code>can('jana.superadmin')</code>, mas o <code>Gate::before</code> do app devolve
          verdadeiro em qualquer ability para quem tem <code>Admin#business_id</code> — e a consulta aqui roda
          <code> withoutGlobalScope</code>. Vale conferir antes de expor: um dono de empresa que chegue nesta
          URL veria meta de outro cliente. Levantado no protótipo; a decisão é de quem manda no gate.
        </Alert>}

      <section className="jtn-secao">
        <div className="jtn-secao-h">
          <h3>Metas da plataforma</h3>
          <small>business_id NULL · {JTN_PLAT.length} metas</small>
        </div>
        <div className="jmc-table">
          <DataTable
            columns={[{ key: "nome", label: "Meta" }, { key: "unidade", label: "Unidade" }, { key: "origem", label: "Origem" }]}
            rows={JTN_PLAT.map((m) => ({ id: m.slug,
              nome: { primary: m.nome, sub: m.slug },
              unidade: <span className="jtn-num">{m.unidade}</span>,
              origem: <span className="jmc-dim">{m.origem === "sistema" ? "consulta do sistema" : "cadastro manual"}</span> }))} />
        </div>
      </section>

      <section className="jtn-secao">
        <div className="jtn-secao-h">
          <h3>Metas de clientes</h3>
          <small>cross-business · {JTN_CLIENTES.length} metas em {new Set(JTN_CLIENTES.map((c) => c.biz)).size} empresas</small>
        </div>
        <div className="jmc-table">
          <DataTable
            columns={[
            { key: "biz", label: "Business" },
            { key: "nome", label: "Meta" },
            { key: "unidade", label: "Unidade" },
            { key: "periodo", label: "Período atual" },
            { key: "ultima", label: "Última apuração" }]}
            rows={JTN_CLIENTES.map((c, i) => ({ id: "c" + i,
              state: c.ultima === "—" ? "archived" : undefined,
              biz: { primary: "#" + c.biz, sub: c.empresa },
              nome: c.nome,
              unidade: <span className="jtn-num">{c.unidade}</span>,
              periodo: <span className="jtn-num">{c.periodo}</span>,
              ultima: <span className={"jtn-num" + (c.ultima === "—" ? " jmc-mono vazio" : "")}>{c.ultima === "—" ? "nunca apurada" : c.ultima}</span> }))} />
        </div>
        <p className="jtn-nota">
          Listagem crua, de propósito: a <b>agregação cross-business</b> que o docblock antigo prometia não
          existe no controller (nenhum <code>sum</code>/<code>count</code>/<code>groupBy</code>, medido em
          27/08/2026). Somar aqui na tela seria inventar total de plataforma no cliente — a pendência fica
          declarada até alguém decidir o que a plataforma quer medir.
        </p>
      </section>

      <section className="jtn-secao">
        <div className="jtn-secao-h">
          <h3>Instalação do módulo</h3>
          <small>/ia/install · nWidart</small>
        </div>
        <div className="jtn-inst">
          <div className="jtn-inst-cell"><b>21</b><small>migrations</small></div>
          <div className="jtn-inst-cell"><b>4</b><small>seeders</small></div>
          <div className="jtn-inst-cell"><b>24</b><small>permissões</small></div>
          <div className="jtn-inst-cell"><b>instalado</b><small>situação</small></div>
        </div>
        <div className="jtn-inst-acts">
          {Button ?
          <>
              <Button variant="ghost" onClick={() => setConfirmar("update")}>Rodar atualização</Button>
              <Button variant="danger" onClick={() => setConfirmar("uninstall")}>Desinstalar módulo</Button>
            </> :
          <button className="jm-btn" onClick={() => setConfirmar("update")}>Rodar atualização</button>}
        </div>
        <p className="jtn-nota">
          Disparado hoje pelo <code>/manage-modules</code> do superadmin. Desinstalar derruba as tabelas
          <code> jana_*</code> — conversas, memória, metas e apurações — e é irreversível sem backup.
        </p>
      </section>

      {Modal && confirmar &&
      <Modal open onClose={() => setConfirmar(null)} width={440}
      title={confirmar === "uninstall" ? "Desinstalar o módulo Jana" : "Rodar atualização"}
      footer={
      <div className="jm-drawer-foot">
            <Button variant="ghost" onClick={() => setConfirmar(null)}>Cancelar</Button>
            <Button variant={confirmar === "uninstall" ? "danger" : "primary"} onClick={rodar}>
              {confirmar === "uninstall" ? "Desinstalar" : "Atualizar"}
            </Button>
          </div>}>
          <p className="jmc-modal-p">
            {confirmar === "uninstall" ?
          "O rollback apaga as tabelas jana_* deste ambiente: conversas, mensagens, fatos de memória, metas, períodos e apurações. Não há lixeira." :
          "Roda as migrations pendentes e grava a versão nova no módulo. Nada é apagado."}
          </p>
          <ul className="jm-dr-src">
            <li>Roda como job no servidor</li>
            <li>Ambiente atual: CT 100 (Proxmox)</li>
            <li>{confirmar === "uninstall" ? "Sem backup, o histórico não volta" : "Migrations são idempotentes"}</li>
          </ul>
        </Modal>}
    </div>);

}

Object.assign(window, { JmAlertas, JtnAlertasConfig, JmAcoesFila, JmPlataforma, jtnCan, jtnContarAlertas, jtnSubscribeSil, JTN_PERMS });
