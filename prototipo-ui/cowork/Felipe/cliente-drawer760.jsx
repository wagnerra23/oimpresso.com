// cliente-drawer760.jsx — drawer 760px do Cliente, paridade com PRODUÇÃO.
// Fonte lida no repo (espelho local, não main): resources/js/Pages/Cliente/Index.tsx
// (ClienteSheet, DRAWER_TABS) + Pages/Cliente/_drawer/{Identificacao,Contato,Endereco,
// Comercial,Classificacao,Oss,Placas,IA,Auditoria}Tab.tsx · ADR 0179 (drawer 760
// substitui Show.tsx full-page) · ADR 0188 Onda 4 (papéis).
// Regras de produção preservadas: 6 abas cadastrais na tab bar; leituras (Placas/IA/
// anexos) como CHIPS no header; Operações com 8 sub-abas; atalhos 1–6 trocam de aba;
// Esc NÃO fecha (padrão Notion/Linear — drawer de edição não perde trabalho).
const { useState: useStateCD, useEffect: useEffectCD, useMemo: useMemoCD, useRef: useRefCD } = React;

const CD_TABS = [
  { key: "identificacao", label: "Identificação" },
  { key: "contato", label: "Contato" },
  { key: "endereco", label: "Endereço" },
  { key: "comercial", label: "Comercial" },
  { key: "classificacao", label: "Classificação" },
  { key: "operacoes", label: "Operações" }];

const CD_SUBTABS = [
  { key: "ledger", label: "Extrato" },
  { key: "sales", label: "Vendas" },
  { key: "payments", label: "Pagamentos" },
  { key: "documents", label: "Documentos" },
  { key: "persons", label: "Pessoas" },
  { key: "subscriptions", label: "Assinaturas" },
  { key: "rewards", label: "Pontos" },
  { key: "auditoria", label: "Auditoria" }];

// Segmento — 6 valores, rótulo que ensina o domínio (produção: SEGMENTO_OPTIONS).
const CD_SEGMENTOS = [
  { value: "varejo", label: "Varejo (lojinha, loja própria)" },
  { value: "atacado", label: "Atacado / distribuição" },
  { value: "agencia", label: "Agência / parceiro de mídia" },
  { value: "corporativo", label: "Corporativo / B2B" },
  { value: "evento", label: "Evento pontual" },
  { value: "governo", label: "Governo / órgão público" }];
// Tags — 9 valores (produção: TAG_OPTIONS).
const CD_TAGS = ["varejo", "atacado", "corporativo", "evento", "parceiro", "agência", "governo", "vip", "reincidente"];
// Status — 3 valores; enum separado do toggle de bloqueio comercial (ADR 0195).
const CD_STATUS = [
  { value: "ativo", label: "Ativo" },
  { value: "inativo", label: "Inativo" },
  { value: "bloqueado", label: "Bloqueado" }];
// Papéis — 5 flags (ADR 0188 Onda 4 + ADR 0246 "Outros").
const CD_PAPEIS = [
  { flag: "cliente", label: "Cliente" },
  { flag: "fornecedor", label: "Fornecedor" },
  { flag: "funcionario", label: "Funcionário" },
  { flag: "representante", label: "Representante" },
  { flag: "outros", label: "Outros" }];
const CD_PGTO = [
  { value: "pix", label: "PIX" },
  { value: "boleto", label: "Boleto" },
  { value: "cartao", label: "Cartão" },
  { value: "dinheiro", label: "Dinheiro" },
  { value: "transferencia", label: "Transferência" }];
// Tabela de preço = FK customer_group_id. Opções vêm da lista viva de grupos
// (mesma fonte da tela Grupos de cliente e do filtro da lista) — nunca hardcoded.
function cdTabelas() {
  const grupos = window.cliGruposLer ? window.cliGruposLer() : [];
  return [{ value: "", label: "Sem tabela (preço padrão)" }, ...grupos.map((g) => ({ value: g.id, label: g.nome }))];
}
const CD_CANAIS = ["WhatsApp", "E-mail", "Telefone", "Presencial"];
const CD_UFS = ["SP", "MG", "RJ", "PR", "SC", "RS", "BA", "GO", "DF"];

// Data operacional em dd/mm/aaaa (aceita ISO gravado no lançamento).
function cdData(v) {
  if (!v) return "—";
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v));
  return m ? `${m[3]}/${m[2]}/${m[1]}` : String(v);
}

function CdField({ label, help, erro, largura, st, children }) {
  return (
    <label className={"cd-f" + (largura ? " cd-f--" + largura : "")}>
      <span className="cd-f-l">{label}</span>
      {children}
      {erro ? <span className="cd-f-e">{erro}</span> :
      st && st.s ? <CdStatus st={st} /> :
      help ? <span className="cd-f-h">{help}</span> : null}
    </label>);

}

// Status por campo — o que produção mostra ao lado de cada input.
function CdStatus({ st }) {
  if (!st || !st.s) return null;
  if (st.s === "err") return <span className="cd-f-s err" role="alert">{st.m}</span>;
  if (st.s === "saving") return <span className="cd-f-s" aria-live="polite">Salvando…</span>;
  return <span className="cd-f-s ok" aria-live="polite">Salvo</span>;
}

// Autosave por campo: PATCH ao sair do foco, otimista, com rollback no erro.
// Espelha _drawer/*Tab.tsx (debounce 800ms + FieldStatus + rollbackField).
function useCdSave() {
  const [st, setSt] = useStateCD({});
  const timers = useRefCD({});
  const mark = (field, v) => setSt((s) => ({ ...s, [field]: v }));

  const commit = (field, value, rollback, validar, onOk) => {
    clearTimeout(timers.current[field]);
    const erro = validar ? validar(value) : null;
    mark(field, { s: "saving" });
    timers.current[field] = setTimeout(() => {
      if (erro) {
        rollback && rollback();
        mark(field, { s: "err", m: erro });
        return;
      }
      onOk && onOk();
      mark(field, { s: "ok" });
      timers.current[field + ":clr"] = setTimeout(() => mark(field, null), 1800);
    }, 420);
  };

  useEffectCD(() => () => Object.values(timers.current).forEach(clearTimeout), []);
  return [st, commit];
}

// Normaliza o argumento de onChange: string ou evento DOM.
function cdVal(v) { return v && v.target ? v.target.value : v; }

function CdInput({ value, onChange, ...p }) {
  return <input className="cd-in" value={value} onChange={(e) => onChange?.(cdVal(e))} {...p} />;
}

function CdSelect({ value, onChange, options, placeholder, ...p }) {
  const opts = options.map((o) => typeof o === "string" ? { value: o, label: o } : o);
  return (
    <select className="cd-in" value={value} onChange={(e) => onChange?.(cdVal(e))} {...p}>
      {placeholder && <option value="">{placeholder}</option>}
      {opts.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>);

}

function CdSwitch({ on, onToggle, label, sub, tone }) {
  return (
    <div className="cd-sw-row">
      <div className="cd-sw-tx"><b>{label}</b>{sub && <small>{sub}</small>}</div>
      <button className={"cd-sw" + (on ? " on" : "") + (tone === "danger" && on ? " danger" : "")}
      onClick={onToggle} aria-pressed={on} aria-label={label}><i /></button>
    </div>);

}

function useCdFields(initial) {
  const [f, setF] = useStateCD(initial);
  const [st, save] = useCdSave();
  const base = useRefCD({});

  const set = (k) => (v) => {
    if (!(k in base.current)) base.current[k] = f[k];
    setF((s) => ({ ...s, [k]: cdVal(v) }));
  };

  // Blur = PATCH. Rollback usa o baseline, não o valor já digitado.
  const blur = (k, validar) => () => {
    if (!(k in base.current)) return;
    const prev = base.current[k];
    save(k, f[k],
    () => {setF((s) => ({ ...s, [k]: prev }));delete base.current[k];},
    validar,
    () => {delete base.current[k];});
  };

  // Select/toggle: muda e salva no mesmo gesto.
  const pick = (k) => (v) => {
    const prev = f[k];
    base.current[k] = prev;
    const x = cdVal(v);
    setF((s) => ({ ...s, [k]: x }));
    save(k, x,
    () => {setF((s) => ({ ...s, [k]: prev }));delete base.current[k];},
    null,
    () => {delete base.current[k];});
  };

  return { f, setF, set, blur, pick, st, save };
}

// ── Aba 1 · Identificação (PF/PJ + lookup CNPJ na Receita) ──
function CdIdentificacao({ client, derived, tipo, setTipo, onAviso }) {
  const { f, set, blur, st } = useCdFields({
    razao: client.name, fantasia: client.name.split(" ")[0],
    doc: client.doc || "", ie: derived?.ie || "", im: "",
    resp: client.contact || "", cargo: "" });
  const [buscando, setBuscando] = useStateCD(false);
  const pj = tipo === "PJ";
  const validaDoc = (v) => {
    const d = String(v).replace(/\D/g, "");
    if (!d) return null;
    return d.length === (pj ? 14 : 11) ? null : (pj ? "CNPJ precisa de 14 dígitos." : "CPF precisa de 11 dígitos.");
  };

  const buscarDoc = () => {
    setBuscando(true);
    setTimeout(() => {
      setBuscando(false);
      onAviso?.(pj ?
      "Consulta na Receita Federal responde com razão social, IE e endereço — o backend decide se sobrescreve." :
      "Consulta de CPF só valida o dígito; não puxa cadastro (LGPD).");
    }, 900);
  };

  return (
    <div className="cd-tab">
      <div className="cd-seg" role="radiogroup" aria-label="Tipo de pessoa">
        {["PJ", "PF"].map((t) =>
        <button key={t} role="radio" aria-checked={tipo === t}
        className={"cd-seg-b" + (tipo === t ? " active" : "")} onClick={() => setTipo(t)}>
            {t === "PJ" ? "Pessoa jurídica" : "Pessoa física"}
          </button>
        )}
      </div>

      <div className="cd-grid">
        <CdField label={pj ? "Razão social" : "Nome completo"} largura="full" st={st.razao}>
          <CdInput value={f.razao} onChange={set("razao")} onBlur={blur("razao")} />
        </CdField>
        <CdField label={pj ? "Nome fantasia" : "Como é conhecido"} help="Como o cliente é conhecido" largura="full" st={st.fantasia}>
          <CdInput value={f.fantasia} onChange={set("fantasia")} onBlur={blur("fantasia")} placeholder="Como o cliente é conhecido" />
        </CdField>
        <CdField label={pj ? "CNPJ" : "CPF"} st={st.doc} help={pj ? "Busca na Receita preenche razão social e endereço" : null}>
          <span className="cd-in-row">
            <CdInput value={f.doc} onChange={set("doc")} onBlur={blur("doc", validaDoc)} placeholder={pj ? "00.000.000/0000-00" : "000.000.000-00"} />
            <button className="cd-btn ghost" onClick={buscarDoc} disabled={buscando}
            aria-label={pj ? "Buscar CNPJ na Receita Federal" : "Validar CPF"}>
              {buscando ? "Buscando…" : "Buscar"}
            </button>
          </span>
        </CdField>
        {pj ?
        <>
            <CdField label="Inscrição estadual" st={st.ie}><CdInput value={f.ie} onChange={set("ie")} onBlur={blur("ie")} placeholder="00.000.000-0" /></CdField>
            <CdField label="Inscrição municipal" st={st.im}><CdInput value={f.im} onChange={set("im")} onBlur={blur("im")} placeholder="000000" /></CdField>
          </> :

        <CdField label="RG" st={st.ie}><CdInput value={f.ie} onChange={set("ie")} onBlur={blur("ie")} placeholder="00.000.000-0" /></CdField>}

        <CdField label="Responsável" st={st.resp}><CdInput value={f.resp} onChange={set("resp")} onBlur={blur("resp")} placeholder="Nome do responsável" /></CdField>
        <CdField label="Cargo" st={st.cargo}><CdInput value={f.cargo} onChange={set("cargo")} onBlur={blur("cargo")} placeholder="Ex.: Diretor de marketing" /></CdField>
      </div>
      <p className="cd-nota">Cada campo salva sozinho ao sair do foco (autosave por PATCH) — e volta ao valor anterior se o backend recusar.</p>
    </div>);

}

// ── Aba 2 · Contato ──
function CdContato({ client }) {
  const { f, set, blur, pick, st } = useCdFields({
    tel: client.phone || "", tel2: "", whats: client.phone || "",
    email: "", emailCom: "", emailCont: "", site: "", canal: "WhatsApp" });
  const validaEmail = (v) => !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : "E-mail inválido.";
  const validaTel = (v) => {
    const d = String(v).replace(/\D/g, "");
    return !d || d.length >= 10 ? null : "Telefone incompleto — precisa do DDD.";
  };
  return (
    <div className="cd-tab">
      <div className="cd-grid">
        <CdField label="Telefone" st={st.tel}><CdInput value={f.tel} onChange={set("tel")} onBlur={blur("tel", validaTel)} placeholder="(00) 0 0000-0000" /></CdField>
        <CdField label="Telefone 2" st={st.tel2}><CdInput value={f.tel2} onChange={set("tel2")} onBlur={blur("tel2", validaTel)} placeholder="(00) 0 0000-0000" /></CdField>
        <CdField label="WhatsApp" st={st.whats} help="Usado pela régua de cobrança e pelo atendimento">
          <CdInput value={f.whats} onChange={set("whats")} onBlur={blur("whats", validaTel)} placeholder="(00) 0 0000-0000" />
        </CdField>
        <CdField label="Canal preferido" st={st.canal}><CdSelect value={f.canal} onChange={pick("canal")} options={CD_CANAIS} /></CdField>
        <CdField label="E-mail" st={st.email}><CdInput value={f.email} onChange={set("email")} onBlur={blur("email", validaEmail)} placeholder="contato@exemplo.com.br" /></CdField>
        <CdField label="E-mail comercial" st={st.emailCom}><CdInput value={f.emailCom} onChange={set("emailCom")} onBlur={blur("emailCom", validaEmail)} placeholder="comercial@exemplo.com.br" /></CdField>
        <CdField label="E-mail do contador" st={st.emailCont}><CdInput value={f.emailCont} onChange={set("emailCont")} onBlur={blur("emailCont", validaEmail)} placeholder="contador@exemplo.com.br" /></CdField>
        <CdField label="Site" st={st.site}><CdInput value={f.site} onChange={set("site")} onBlur={blur("site")} placeholder="exemplo.com.br" /></CdField>
      </div>
    </div>);

}

// ── Aba 4 · Comercial ──
function CdComercial({ derived }) {
  const CD_TABELAS = cdTabelas();
  const { f, set, blur, pick, st } = useCdFields({ limite: "", prazo: "30", tabela: derived?.grupoId || "", pgto: "boleto", msgVenda: "", obs: "" });
  const saldo = derived?.saldo || 0;
  const limiteN = Number(String(f.limite).replace(/\D/g, "")) || 0;
  const estouro = limiteN > 0 && saldo > limiteN;
  const validaLimite = (v) => {
    const n = Number(String(v).replace(/\D/g, "")) || 0;
    return n > 999999 ? "Limite acima do teto do negócio — precisa de aprovação." : null;
  };
  const semTabela = CD_TABELAS.length <= 1; // só o "sem tabela" = nenhum grupo cadastrado
  return (
    <div className="cd-tab">
      <div className="cd-grid">
        <CdField label="Limite de crédito" st={st.limite}
        help="Em reais · vazio = sem limite"
        erro={estouro ? "Saldo em aberto passou do limite" : null}>
          <CdInput value={f.limite} inputMode="numeric" onChange={(v) => set("limite")(String(cdVal(v)).replace(/\D/g, ""))} onBlur={blur("limite", validaLimite)} placeholder="0" />
        </CdField>
        <CdField label="Prazo padrão (dias)" st={st.prazo}>
          <CdInput value={f.prazo} inputMode="numeric" onChange={(v) => set("prazo")(String(cdVal(v)).replace(/\D/g, ""))} onBlur={blur("prazo")} placeholder="30" />
        </CdField>
        <CdField label="Tabela de preço" st={st.tabela}
        help={semTabela ? "Nenhuma tabela de preço cadastrada neste negócio." : "Grupo de cliente do ERP"}>
          <CdSelect value={f.tabela} onChange={pick("tabela")} options={CD_TABELAS} disabled={semTabela} />
        </CdField>
        <CdField label="Forma de pagamento preferida" st={st.pgto}>
          <CdSelect value={f.pgto} onChange={pick("pgto")} options={CD_PGTO} placeholder="Selecionar forma de pagamento" />
        </CdField>
        <CdField label="Mensagem para a venda" largura="full" st={st.msgVenda}
        help="Aparece como alerta ao vendedor no PDV, antes de fechar a venda">
          <textarea className="cd-in cd-ta" rows={2} value={f.msgVenda} onChange={(e) => set("msgVenda")(cdVal(e))} onBlur={blur("msgVenda")}
          placeholder="Ex.: cliente paga só com boleto · conferir limite antes de faturar…" />
        </CdField>
        <CdField label="Observações comerciais" largura="full" st={st.obs}>
          <textarea className="cd-in cd-ta" rows={2} value={f.obs} onChange={(e) => set("obs")(cdVal(e))} onBlur={blur("obs")}
          placeholder="Particularidades de negociação, condições especiais…" />
        </CdField>
      </div>
    </div>);

}

// ── Aba 5 · Classificação (papéis + segmento + tags + status + VIP + bloqueio) ──
function CdClassificacao({ derived, onAviso }) {
  const [papeis, setPapeis] = useStateCD({ cliente: true, fornecedor: false, funcionario: false, representante: false, outros: false });
  const [segmento, setSegmento] = useStateCD(CD_SEGMENTOS[0].value);
  const [tags, setTags] = useStateCD(() => (derived?.tags || []).map((t) => String(t).toLowerCase()).filter((t) => CD_TAGS.includes(t)));
  const [status, setStatus] = useStateCD(derived?.status || "ativo");
  const [vip, setVip] = useStateCD(!!derived?.isVip);
  const [bloqueado, setBloqueado] = useStateCD(false);
  const [st, save] = useCdSave();
  const toggleTag = (t) => {
    const prev = tags;
    const next = tags.includes(t) ? tags.filter((x) => x !== t) : [...tags, t];
    setTags(next);
    save("tags", next, () => setTags(prev));
  };
  // Invariante do backend: ≥1 papel ativo (anti soft-delete acidental).
  const togglePapel = (flag) => {
    const prev = papeis;
    const next = { ...papeis, [flag]: !papeis[flag] };
    if (!Object.values(next).some(Boolean)) {
      onAviso?.("O cadastro precisa de pelo menos um papel ativo.", "warn");
      return;
    }
    setPapeis(next);
    save("papeis", next, () => setPapeis(prev));
  };
  return (
    <div className="cd-tab">
      <div className="cd-block">
        <span className="cd-block-t">Papéis <em>clique pra alternar</em></span>
        <div className="cd-chips" role="group" aria-label="Papéis do contato">
          {CD_PAPEIS.map((p) =>
          <button key={p.flag} className={"cd-chip" + (papeis[p.flag] ? " on" : "")}
          aria-pressed={papeis[p.flag]} onClick={() => togglePapel(p.flag)}>{p.label}</button>
          )}
        </div>
        <CdStatus st={st.papeis} />
      </div>
      <div className="cd-grid">
        <CdField label="Segmento" largura="full" st={st.segmento}>
          <CdSelect value={segmento} onChange={(v) => {const x = cdVal(v);setSegmento(x);save("segmento", x);}} options={CD_SEGMENTOS} />
        </CdField>
        <CdField label="Status" st={st.status}>
          <CdSelect value={status} onChange={(v) => {const x = cdVal(v);setStatus(x);save("status", x);}} options={CD_STATUS} placeholder="Selecionar status" />
        </CdField>
      </div>
      <div className="cd-block">
        <span className="cd-block-t">Tags <em>clique pra alternar</em></span>
        <div className="cd-chips" role="group" aria-label="Tags do cliente">
          {CD_TAGS.map((t) =>
          <button key={t} className={"cd-chip" + (tags.includes(t) ? " on" : "")} aria-pressed={tags.includes(t)} onClick={() => toggleTag(t)}>{t}</button>
          )}
        </div>
        <CdStatus st={st.tags} />
      </div>
      <CdSwitch on={vip} onToggle={() => {const p = vip;setVip(!p);save("vip", !p, () => setVip(p));}} label="Cliente VIP" sub="Prioridade na fila de produção e no atendimento" />
      <CdStatus st={st.vip} />
      <CdSwitch on={bloqueado} tone="danger" onToggle={() => {setBloqueado((v) => !v);onAviso?.(bloqueado ? "Bloqueio removido." : "Bloqueio impede nova venda e cobrança — fica no log de auditoria.", bloqueado ? "ok" : "warn");}}
      label="Bloquear cobrança e venda" sub="Ninguém fatura nem cobra este cliente enquanto estiver ligado" />
    </div>);

}

// Relógio do protótipo (o mesmo do Financeiro) — pagamento recebido "no futuro"
// é o tipo de mentira que a tela não pode contar.
function cdHoje() {
  const t = window.FIN_TODAY;
  return t instanceof Date ? new Date(t) : new Date();
}
function cdDataMenos(dias) {
  const d = cdHoje();
  d.setDate(d.getDate() - dias);
  return `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;
}
function cdHash(s) { let h = 0; const t = String(s); for (let i = 0; i < t.length; i++) h = (h * 31 + t.charCodeAt(i)) | 0; return Math.abs(h); }
function cdValorOS(o) { return parseFloat(String(o.value || "0").replace(/[^\d,]/g, "").replace(",", ".")) || 0; }

// Pagamentos do cliente derivados das OS dele (paridade _show/PaymentsTab.tsx:
// colunas Data · Nº Ref · Valor · Método · Pago por · Ação).
const CD_METODOS = ["PIX", "Boleto", "Cartão", "Dinheiro", "Transferência"];
function cdPagamentos(client, own) {
  const pagas = own.filter((o) => ["entregue", "faturado", "pronto"].includes(o.stage));
  return pagas.map((o, i) => {
    const h = cdHash(client.name + o.id);
    return {
      id: o.id,
      data: cdDataMenos(3 + (h % 40)),
      ref: "PG-" + String(o.id).padStart(4, "0"),
      valor: cdValorOS(o),
      metodo: CD_METODOS[h % CD_METODOS.length],
      origem: "Venda #" + o.id,
      devolucao: false,
    };
  });
}

// ── Aba 6 · Operações (8 sub-abas) ──
function CdOperacoes({ client, stats, derived, sub, onSub }) {
  const I = window.I || {};
  const fmtBRL = window.cliFmtBRL || ((v) => "R$ " + v);
  const Frescor = window.CliFrescorPill;
  const Saldo = window.SaldoNeg;
  const own = (stats?.ownList || []).slice().sort((a, b) => parseInt(b.id) - parseInt(a.id));
  const stages = (window.OS_DATA && window.OS_DATA.OS_STAGES) || [];
  const pagamentos = cdPagamentos(client, own);
  // Pontos: 1 ponto a cada R$ 10 faturados; resgate e expiração determinísticos.
  const h = cdHash(client.name);
  const acumulados = Math.floor((stats?.totalValue || 0) / 10);
  const usados = acumulados > 0 ? Math.floor(acumulados * ((h % 4) / 10)) : 0;
  const expirados = acumulados > 0 ? Math.floor(acumulados * ((h % 3) / 20)) : 0;
  const pontos = { acumulados, usados, expirados, saldo: Math.max(0, acumulados - usados - expirados) };
  // Auditoria: eventos do próprio cadastro (Spatie ActivityLog só grava mutação).
  const auditoria = [
    { hint: "plus", t: "Cadastro criado", s: `Wagner · ${cdDataMenos(120 + (h % 400))}` },
    ...(derived?.isVip ? [{ hint: "tag", t: "Tag \"vip\" adicionada", s: `Larissa · ${cdDataMenos(4 + (h % 30))}` }] : []),
    ...(derived?.saldo > 0 ? [{ hint: "pencil", t: "Título em aberto lançado", s: `Eliana · ${fmtBRL(derived.saldo)} · ${cdDataMenos(2 + (h % 20))}` }] : []),
    ...(derived?.status !== "ativo" ? [{ hint: "close", t: `Cadastro marcado como ${derived.status}`, s: `Wagner · ${cdDataMenos(1 + (h % 15))}` }] : []),
  ];

  return (
    <div className="cd-tab cd-ops">
      <nav className="cd-subnav" aria-label="Sub-abas operacionais">
        {CD_SUBTABS.map((t) =>
        <button key={t.key} className={"cd-subnav-b" + (sub === t.key ? " active" : "")} onClick={() => onSub(t.key)}>{t.label}</button>
        )}
      </nav>
      <div className="cd-ops-body">
        {sub === "ledger" &&
        <>
            <div className="cd-kpis">
              <div className="cd-kpi"><b>{stats?.count || 0}</b><small>OS no total</small></div>
              <div className="cd-kpi"><b>{stats?.openCount || 0}</b><small>Em aberto</small></div>
              <div className={"cd-kpi" + (stats?.lateCount > 0 ? " danger" : "")}><b>{stats?.lateCount || 0}</b><small>Atrasadas</small></div>
              <div className="cd-kpi"><b>{fmtBRL(stats?.totalValue || 0)}</b><small>Valor total</small></div>
            </div>
            <div className="cd-ops-acao">
              <button className="cd-btn ghost" onClick={() => { window.__CLI_EXTRATO_ID = client.id; window.__go?.("cli-extrato"); }}>
                Abrir extrato completo
              </button>
              <span className="cd-ops-acao-h">período, formato e envio por e-mail ficam na página cheia</span>
            </div>
            <div className="cd-inline">
              <span>Frescor</span>{Frescor && <Frescor state={derived?.frescor?.state} label={derived?.frescor?.label} />}
              <span className="cd-inline-sep">·</span>
              <span>Saldo em aberto</span>{Saldo && <Saldo value={derived?.saldo} />}
              {derived?.credito > 0 &&
              <>
                  <span className="cd-inline-sep">·</span>
                  <span>Crédito a favor</span>
                  <b className="cd-credito">{fmtBRL(derived.credito)}</b>
                </>}
            </div>
            {derived?.credito > 0 &&
            <div className="cd-creditos">
              <span className="cd-block-t">Créditos a favor <em>abatidos na próxima venda; o vendedor pode recusar</em></span>
              {derived.creditoBase > 0 &&
              <div className="cd-li">
                  <span className="cd-li-id">crédito</span>
                  <span className="cd-li-tx">Saldo a favor herdado do Financeiro</span>
                  <span className="cd-li-d">—</span>
                  <span className="cd-li-v cd-li-v--pos">{fmtBRL(derived.creditoBase)}</span>
                </div>}
              {(derived.creditos || []).map((l, i) =>
              <div className="cd-li" key={i}>
                  <span className="cd-li-id">crédito</span>
                  <span className="cd-li-tx">{l.origem}{l.obs ? " · " + l.obs : ""}</span>
                  <span className="cd-li-d">{cdData(l.data)}</span>
                  <span className="cd-li-v cd-li-v--pos">{fmtBRL(l.valor)}</span>
                </div>
              )}
              <div className="cd-li cd-li--total">
                <span className="cd-li-id">total</span>
                <span className="cd-li-tx">Crédito a favor do cliente</span>
                <span className="cd-li-v cd-li-v--pos">{fmtBRL(derived.credito)}</span>
              </div>
            </div>}
            <div className="cd-list">
              {own.length === 0 && <div className="cd-empty">Nenhum lançamento no extrato.</div>}
              {own.map((o) =>
            <div className="cd-li" key={o.id}>
                  <span className="cd-li-id">#{o.id}</span>
                  <span className="cd-li-tx">{o.product}</span>
                  <span className="cd-li-st">{stages.find((s) => s.id === o.stage)?.label || o.stage}</span>
                  <span className="cd-li-d">{o.deadline}</span>
                  <span className="cd-li-v">{o.value}</span>
                </div>
            )}
            </div>
          </>}

        {sub === "sales" && (own.length === 0
          ? <div className="cd-empty">Nenhuma venda registrada.</div>
          : <table className="cd-table">
              <thead><tr><th>Nº</th><th>Item</th><th>Status</th><th className="num">Valor</th></tr></thead>
              <tbody>
                {own.map((o) => {
              const pago = ["entregue", "faturado"].includes(o.stage);
              return (
                <tr key={o.id}>
                      <td className="cd-mono">VEN-{o.id}</td>
                      <td>{o.product}</td>
                      <td><span className={"cd-st " + (pago ? "ok" : "warn")}>{pago ? "paga" : "em aberto"}</span></td>
                      <td className="num">{o.value}</td>
                    </tr>);
            })}
              </tbody>
            </table>)}

        {sub === "payments" && (pagamentos.length === 0
          ? <div className="cd-empty">
              <b>Nenhum pagamento registrado.</b>
              <span>Pagamentos aparecem aqui quando as vendas forem quitadas.</span>
            </div>
          : <table className="cd-table">
              <thead><tr><th>Data</th><th>Nº ref</th><th className="num">Valor</th><th>Método</th><th>Pago por</th><th></th></tr></thead>
              <tbody>
                {pagamentos.map((p) =>
            <tr key={p.id}>
                    <td className="cd-mono">{p.data}</td>
                    <td className="cd-mono">{p.ref}</td>
                    <td className={"num " + (p.devolucao ? "cd-neg" : "cd-pos")}>{p.devolucao ? "−" : ""}{fmtBRL(p.valor)}</td>
                    <td>{p.metodo}</td>
                    <td className="cd-dim">{p.origem}</td>
                    <td className="num"><button className="cd-linkb" title="Abrir a venda">abrir ›</button></td>
                  </tr>
            )}
              </tbody>
            </table>)}

        {sub === "documents" &&
        <div className="cd-docs">
            <div className="cd-dropzone">
              <b>Arraste os arquivos aqui</b>
              <span>Comprovantes, contratos, arte aprovada · ou clique pra escolher</span>
            </div>
            <div className="cd-empty">Nenhum anexo. Envie comprovantes, contratos, fotos.</div>
            <label className="cd-f">
              <span className="cd-f-l">Anotações internas</span>
              <textarea className="cd-in cd-ta" rows={3} placeholder="Anotações internas sobre o cliente (salva sozinho enquanto você escreve)…" />
            </label>
          </div>}

        {sub === "persons" &&
        <div className="cd-pessoas">
            <div className="cd-pessoas-hd">
              <span>1 pessoa de contato</span>
              <button className="cd-btn ghost">Adicionar pessoa</button>
            </div>
            <table className="cd-table">
              <thead><tr><th>Nome</th><th>Usuário</th><th>E-mail</th><th>Departamento</th><th>Cargo</th></tr></thead>
              <tbody>
                <tr>
                  <td>{client.contact || "—"}</td>
                  <td className="cd-dim">—</td>
                  <td className="cd-dim">—</td>
                  <td className="cd-dim">—</td>
                  <td className="cd-dim">contato principal</td>
                </tr>
              </tbody>
            </table>
          </div>}

        {sub === "subscriptions" && <div className="cd-empty">Nenhuma assinatura registrada.</div>}

        {sub === "rewards" &&
        <div className="cd-rewards">
            <div className="cd-kpis">
              <div className="cd-kpi"><b>{pontos.acumulados}</b><small>Pontos acumulados</small></div>
              <div className="cd-kpi"><b>{pontos.usados}</b><small>Resgatados</small></div>
              <div className="cd-kpi"><b>{pontos.expirados}</b><small>Expirados</small></div>
              <div className="cd-kpi"><b>{pontos.saldo}</b><small>Saldo disponível</small></div>
            </div>
            {pontos.acumulados === 0 && <div className="cd-empty">Nenhum histórico de pontos ainda.</div>}
          </div>}

        {sub === "auditoria" &&
        <ol className="cd-audit">
            {auditoria.map((ev) => {
            const Ic = I[ev.hint] || I.audit;
            return (
              <li key={ev.t}>
                  <span className="cd-audit-ic">{Ic && <Ic size={14} />}</span>
                  <div><b>{ev.t}</b><small>{ev.s}</small></div>
                </li>);
          })}
          </ol>}

      </div>
    </div>);

}

// ── Leitura: risco de relacionamento ──
// Score 0–10 determinístico, sem IA: 8 sinais com peso canônico (paridade
// _show/RiscoClienteCard.tsx). 0 = saudável · 10 = alto risco.
function cdRiscoSinais(client, stats, derived) {
  const saldo = derived?.saldo || 0;
  const dias = derived?.frescor?.dias;
  const semCompra = !stats || stats.count === 0;
  return [
    { k: "saldo", label: saldo > 0 ? `Saldo a receber ${fmtBRLcd(saldo)}` : "Saldo a receber", peso: saldo > 1000 ? 3 : 2, on: saldo > 0 },
    { k: "s90", label: `Sem compra há ${dias} dias`, peso: 2, on: dias > 90 && dias <= 180 },
    { k: "s180", label: `Sem compra há ${dias} dias — o cliente esfriou`, peso: 3, on: dias > 180 },
    // Bloqueado e inativo não são a mesma coisa: bloqueado impede faturar e cobrar
    // (pesa mais na retomada), inativo só some das buscas.
    { k: "bloqueado", label: "Cadastro bloqueado — não fatura nem cobra", peso: 3, on: derived?.status === "bloqueado" },
    { k: "inativo", label: "Cadastro inativo", peso: 2, on: derived?.status === "inativo" },
    { k: "contato", label: "Sem e-mail nem celular cadastrados", peso: 1, on: !client.phone },
    { k: "ie", label: "PJ contribuinte de ICMS sem inscrição estadual", peso: 1, on: derived?.contribuinte === true && !derived?.ie },
    { k: "local", label: "Sem cidade ou estado preenchido", peso: 0.5, on: !derived?.city || !derived?.uf },
    { k: "velho", label: "Cadastrado há mais de um ano e nunca comprou", peso: 1, on: semCompra },
  ];
}

function fmtBRLcd(v) { return (window.cliFmtBRL || ((x) => "R$ " + x))(v); }

function CdRisco({ client, stats, derived }) {
  const sinais = cdRiscoSinais(client, stats, derived);
  const score = Math.min(10, sinais.reduce((s, x) => s + (x.on ? x.peso : 0), 0));
  const faixa = score <= 3 ? "ok" : score <= 6 ? "warn" : "danger";
  const rotulo = { ok: "Saudável", warn: "Atenção", danger: "Alto risco" }[faixa];
  const ativos = sinais.filter((s) => s.on);
  return (
    <div className="cd-tab">
      <div className={"cd-risco cd-risco--" + faixa}>
        <div className="cd-risco-hd">
          <span className="cd-risco-l">{rotulo}</span>
          <b className="cd-risco-v">{score % 1 === 0 ? score : score.toFixed(1)}<em>/10</em></b>
        </div>
        <div className="cd-risco-bar"><i style={{ width: score * 10 + "%" }} /></div>
      </div>
      {ativos.length > 0 ?
      <ul className="cd-risco-list" aria-label="Sinais de risco ativos">
          {ativos.map((s) =>
        <li key={s.k}><span>{s.label}</span><b>+{s.peso % 1 === 0 ? s.peso : s.peso.toFixed(1)}</b></li>
        )}
        </ul> :
      <p className="cd-nota">Nenhum sinal de risco neste cadastro.</p>}
      <p className="cd-nota">Score determinístico — 9 sinais com peso fixo, sem IA. Serve pra priorizar cobrança e retomada, não pra recusar venda.</p>
    </div>);

}

// ── Leituras (chips do header): Veículos + IA ──
function CdPlacas({ onAviso }) {
  const [q, setQ] = useStateCD("");
  const PLACAS = [
    { placa: "RTA4B21", uf: "SP", modelo: "Mercedes Axor 2544", tipo: "Caminhão", ano: "2019/2020", cor: "Branco", comb: "Diesel", km: "412.880", status: "Na oficina", ultima: "OS #4712 · troca de lona" },
    { placa: "QXE7J09", uf: "MG", modelo: "Volvo FH 460", tipo: "Caminhão", ano: "2021/2021", cor: "Prata", comb: "Diesel", km: "268.140", status: "Entregue", ultima: "OS #4655 · revisão 40k" }];
  const Placa = (window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {}).PlacaVeiculo;
  const vis = PLACAS.filter((v) => !q || (v.placa + " " + v.modelo + " " + v.cor).toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="cd-tab">
      <input className="cd-in cd-veic-busca" value={q} onChange={(e) => setQ(e.target.value)}
      placeholder="Buscar placa, modelo ou cor…" aria-label="Buscar veículo" />
      <div className="cd-list">
        {vis.length === 0 && <div className="cd-empty">Nenhum veículo com esse termo.</div>}
        {vis.map((v) =>
        <div className="cd-veic" key={v.placa}>
            <div className="cd-veic-hd">
              {Placa ? <Placa placa={v.placa} uf={v.uf} padrao="mercosul" size="sm" /> : <span className="cd-li-id">{v.placa}</span>}
              <span className="cd-veic-m">{v.modelo}</span>
              <span className={"cd-veic-st" + (v.status === "Na oficina" ? " on" : "")}>{v.status}</span>
            </div>
            <dl className="cd-veic-grid">
              <div><dt>Tipo</dt><dd>{v.tipo}</dd></div>
              <div><dt>Ano</dt><dd>{v.ano}</dd></div>
              <div><dt>Cor</dt><dd>{v.cor}</dd></div>
              <div><dt>Combustível</dt><dd>{v.comb}</dd></div>
              <div><dt>Km na entrada</dt><dd className="tabular">{v.km}</dd></div>
            </dl>
            <span className="cd-veic-ult">{v.ultima}</span>
          </div>
        )}
      </div>
      <p className="cd-nota">Somente leitura — o cadastro do veículo vive na Oficina. Esta leitura só aparece quando o módulo Oficina está ligado no negócio.</p>
    </div>);

}

function CdIA({ client, onAviso }) {
  const CARDS = [
    { t: "Resumo do cliente", d: "Histórico, ritmo de compra e o que costuma pedir.", b: "Gerar resumo" },
    { t: "Risco de crédito", d: "Saldo em aberto vs. histórico de pagamento.", b: "Analisar" },
    { t: "Próxima oferta", d: "O que faz sentido oferecer agora, com base no mix.", b: "Sugerir" }];
  return (
    <div className="cd-tab">
      <div className="cd-ia">
        {CARDS.map((c) =>
        <div className="cd-ia-card" key={c.t}>
            <b>{c.t}</b><small>{c.d}</small>
            <button className="cd-btn" onClick={() => onAviso?.("A Jana só roda quando você pede — evita custo em abertura passiva do drawer.")}>{c.b}</button>
          </div>
        )}
      </div>
    </div>);

}

// ── Drawer ──
function ClienteDrawer760({ client, stats, derived, osList, abaInicial, subInicial, onClose }) {
  const I = window.I || {};
  const Avatar = window.CliAvatar;
  const [tab, setTab] = useStateCD(abaInicial || "identificacao");
  const [sub, setSub] = useStateCD(subInicial || "ledger");
  const [leitura, setLeitura] = useStateCD(null); // placas | ia
  const [tipo, setTipo] = useStateCD(derived?.tipo === "PF" ? "PF" : "PJ");
  const [aviso, setAviso] = useStateCD(null);
  const avisoRef = React.useRef(null);
  const avisar = (msg, tone) => {
    setAviso({ msg, tone: tone || "default" });
    clearTimeout(avisoRef.current);
    avisoRef.current = setTimeout(() => setAviso(null), 3200);
  };
  useEffectCD(() => () => clearTimeout(avisoRef.current), []);

  // Atalhos 1–6 trocam de aba (paridade produção). Esc NÃO fecha: drawer de
  // edição não descarta trabalho não-salvo — só o ✕ e o clique fora fecham.
  useEffectCD(() => {
    const onKey = (e) => {
      const el = document.activeElement;
      if (el && (el.tagName === "INPUT" || el.tagName === "TEXTAREA" || el.tagName === "SELECT")) return;
      const n = parseInt(e.key, 10);
      if (n >= 1 && n <= CD_TABS.length) {e.preventDefault();setLeitura(null);setTab(CD_TABS[n - 1].key);}
      if (e.key === "Escape") {e.preventDefault();avisar("Esc não fecha o drawer — use o ✕ pra não perder o que você digitou.");}
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  });

  const cadastrado = useMemoCD(() => {
    const d = client.createdAt ? new Date(client.createdAt) : null;
    if (!d || Number.isNaN(d.getTime())) return "há 3m";
    const dias = Math.floor((Date.now() - d.getTime()) / 86400000);
    if (dias < 1) return "hoje";
    if (dias < 30) return "há " + dias + "d";
    const meses = Math.floor(dias / 30);
    return meses < 12 ? "há " + meses + "m" : "há " + Math.floor(meses / 12) + "a";
  }, [client]);

  // Status do cadastro — mesma fonte da lista (ativo · inativo · bloqueado).
  const statusCad = derived?.status || "ativo";
  const statusLabel = { ativo: "Ativo", inativo: "Inativo", bloqueado: "Bloqueado" }[statusCad] || "Ativo";
  const statusTom = { ativo: "ok", inativo: "off", bloqueado: "danger" }[statusCad] || "ok";

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <div className="os-drawer cd-drawer" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-label={"Cliente " + client.name}>
        <div className="cd-head">
          <div className="cd-head-l">
            {Avatar && <Avatar name={client.name} size={40} />}
            <div className="cd-head-id">
              <div className="cd-head-n">
                {client.name}
                <span className={"cd-badge " + statusTom}>{statusLabel}</span>
                {derived?.isVip && <span className="cd-badge vip">VIP</span>}
              </div>
              <div className="cd-head-s">
                <span className="cd-tipo">{tipo}</span>
                <span className="cd-mono">{client.doc}</span>
                <span className="cd-sep">{derived?.city}/{derived?.uf}</span>
                <span className="cd-sep">cadastrado {cadastrado}</span>
              </div>
            </div>
          </div>
          <div className="cd-head-r">
            <button className="cd-btn ghost" onClick={() => window.print()}>Imprimir ficha</button>
            <button className="cd-btn ghost" onClick={() => {onClose?.();window.__go?.("chat");}}>Falar com a Jana</button>
            <button className="cd-x" onClick={onClose} aria-label="Fechar">{I.close ? <I.close size={16} /> : "✕"}</button>
          </div>
        </div>

        <div className="cd-chipsrow">
          <button className={"cd-hchip" + (leitura === "placas" ? " on" : "")} onClick={() => setLeitura((v) => v === "placas" ? null : "placas")}>Veículos <span>2</span></button>
          <button className={"cd-hchip" + (leitura === "risco" ? " on" : "")} onClick={() => setLeitura((v) => v === "risco" ? null : "risco")}>Risco</button>
          <button className={"cd-hchip" + (leitura === "ia" ? " on" : "")} onClick={() => setLeitura((v) => v === "ia" ? null : "ia")}>Jana</button>
          <button className="cd-hchip" onClick={() => {setLeitura(null);setTab("operacoes");setSub("documents");}}>Anexos <span>0</span></button>
          <span className="cd-chipsrow-hint"><kbd>1</kbd>–<kbd>6</kbd> troca de aba</span>
        </div>

        <nav className="cd-tabs" aria-label="Abas do cadastro">
          {CD_TABS.map((t) =>
          <button key={t.key} className={"cd-tabb" + (tab === t.key && !leitura ? " active" : "")}
          onClick={() => {setLeitura(null);setTab(t.key);}}
          aria-current={tab === t.key && !leitura ? "page" : undefined}>{t.label}</button>
          )}
        </nav>

        <div className="cd-body">
          {leitura === "placas" ? <CdPlacas onAviso={avisar} /> :
          leitura === "risco" ? <CdRisco client={client} stats={stats} derived={derived} /> :
          leitura === "ia" ? <CdIA client={client} onAviso={avisar} /> :
          tab === "identificacao" ? <CdIdentificacao client={client} derived={derived} tipo={tipo} setTipo={setTipo} onAviso={avisar} /> :
          tab === "contato" ? <CdContato client={client} /> :
          tab === "endereco" ? window.CliEnderecoSection ? <div className="cd-tab"><window.CliEnderecoSection client={client} /></div> : null :
          tab === "comercial" ? <CdComercial derived={derived} /> :
          tab === "classificacao" ? <CdClassificacao derived={derived} onAviso={avisar} /> :
          <CdOperacoes client={client} stats={stats} derived={derived} sub={sub} onSub={setSub} />}
        </div>

        <div className="cd-foot">
          <span className="cd-foot-pend"><i className="cd-pend-dot" aria-hidden="true" /> 1 pendência</span>
          <span className="cd-foot-sp" />
          <button className="cd-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="cd-btn primary" onClick={onClose}>Salvar</button>
        </div>

        {aviso && <div className={"cd-toast " + aviso.tone}>{aviso.msg}</div>}
      </div>
    </div>);

}

Object.assign(window, { ClienteDrawer760, CdOperacoes, CdIdentificacao });
