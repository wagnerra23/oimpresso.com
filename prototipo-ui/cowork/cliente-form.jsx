// cliente-form.jsx — /cliente/create · /cliente/{id}/edit
// Paridade lida em: _form/ClienteForm.tsx (ordem das seções + campos),
// _form/DadosFiscaisBRSection.tsx (bloco fiscal + flags), _form/ClienteRail.tsx
// (rail = pré-visualização + prontidão fiscal + slot do copiloto — NÃO é menu de
// seções), Lib/format-br.ts (INDICADOR_IE_OPTIONS · REGIME_TRIBUTARIO_OPTIONS).
const { useState: useStateCF, useMemo: useMemoCF } = React;

const CF_TIPOS = [
  { value: "customer", label: "Cliente" },
  { value: "supplier", label: "Fornecedor" },
  { value: "both", label: "Cliente e fornecedor" }];

const CF_IND_IE = [
  { value: "1", label: "1 — Contribuinte ICMS" },
  { value: "2", label: "2 — Contribuinte isento de inscrição" },
  { value: "9", label: "9 — Não contribuinte" }];

const CF_REGIMES = [
  { value: "simples", label: "Simples Nacional" },
  { value: "presumido", label: "Lucro Presumido" },
  { value: "real", label: "Lucro Real" },
  { value: "mei", label: "MEI" }];

// Normaliza string-ou-evento no ponto de consumo (mesma trava do drawer).
function cfVal(v) { return v && v.target ? (v.target.type === "checkbox" ? v.target.checked : v.target.value) : v; }
function cfDigitos(v) { const x = cfVal(v); return String(x == null ? "" : x).replace(/\D/g, ""); }
function cfMascaraDoc(v) {
  const d = cfDigitos(v).slice(0, 14);
  if (d.length <= 11) return d.replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})(\d{1,2})$/, "$1-$2");
  return d.replace(/(\d{2})(\d)/, "$1.$2").replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})(\d)/, "$1/$2").replace(/(\d{4})(\d{1,2})$/, "$1-$2");
}

function CfSecao({ titulo, children }) {
  return (
    <section className="cf-sec">
      <h2 className="cf-sec-t">{titulo}</h2>
      <div className="cf-grid">{children}</div>
    </section>);
}

function CfCampo({ label, help, erro, largura, obrigatorio, children }) {
  return (
    <label className={"cf-f" + (largura ? " cf-f--" + largura : "")}>
      <span className="cf-f-l">{label}{obrigatorio && <em className="cf-req" title="Obrigatório">*</em>}</span>
      {children}
      {erro ? <span className="cf-f-e">{erro}</span> : help ? <span className="cf-f-h">{help}</span> : null}
    </label>);
}

const CfIn = ({ value, onChange, ...p }) => <input className="cf-in" value={value} onChange={(e) => onChange?.(cfVal(e))} {...p}/>;
const CfSel = ({ value, onChange, options, placeholder, ...p }) => (
  <select className="cf-in" value={value} onChange={(e) => onChange?.(cfVal(e))} {...p}>
    {placeholder && <option value="">{placeholder}</option>}
    {options.map((o) => { const x = typeof o === "string" ? { value: o, label: o } : o; return <option key={x.value} value={x.value}>{x.label}</option>; })}
  </select>);

function ClienteFormPage({ modo = "novo" }) {
  const [f, setF] = useStateCF({
    type: "customer", pessoa: "business",
    first_name: "", last_name: "", tax_number: "",
    cpf_cnpj: "", rg: "", nome_fantasia: "",
    inscricao_estadual: "", inscricao_municipal: "", indicador_ie: "", regime: "", suframa: "",
    contribuinte: false, consumidor_final: false,
    email: "", mobile: "", landline: "", site: "",
    cep: "", logradouro: "", numero: "", complemento: "", bairro: "", cidade: "", uf: "SP",
    limite: "", prazo: "30", grupo: "", pgto: "boleto", mensagem_venda: "",
  });
  const [tocado, setTocado] = useStateCF({});
  const [buscando, setBuscando] = useStateCF(false);
  const [lookup, setLookup] = useStateCF(null);
  const [salvo, setSalvo] = useStateCF(false);

  const set = (k) => (v) => setF((s) => ({ ...s, [k]: cfVal(v) }));
  const marcar = (k) => () => setTocado((t) => ({ ...t, [k]: true }));
  const pj = f.pessoa === "business";
  const mostraFinanceiro = f.type === "customer" || f.type === "both";
  const docDigitos = cfDigitos(f.cpf_cnpj);
  const cnpjCompleto = pj && docDigitos.length === 14;

  const erros = {
    first_name: !f.first_name.trim() ? "Sem nome não dá pra cadastrar." : null,
    cpf_cnpj: !docDigitos
      ? (pj ? "CNPJ é obrigatório — a nota fiscal não sai sem ele." : "CPF é obrigatório.")
      : docDigitos.length !== (pj ? 14 : 11) ? (pj ? "CNPJ precisa de 14 dígitos." : "CPF precisa de 11 dígitos.") : null,
    email: f.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email) ? "E-mail inválido." : null,
  };
  const err = (k) => (tocado[k] ? erros[k] : null);
  const podeSalvar = !erros.first_name && !erros.cpf_cnpj && !erros.email;

  // Prontidão fiscal — exatamente os checks do ClienteRail de produção.
  const checks = pj
    ? [
        { label: "CNPJ completo", ok: docDigitos.length === 14 },
        { label: "Razão social", ok: !!f.first_name.trim() },
        { label: "Inscrição estadual (ou ISENTO)", ok: !!f.inscricao_estadual.trim() },
        { label: "Regime tributário", ok: !!f.regime },
        { label: "Indicador de IE", ok: !!f.indicador_ie },
      ]
    : [
        { label: "CPF completo", ok: docDigitos.length === 11 },
        { label: "Nome", ok: !!f.first_name.trim() },
      ];
  const feitos = checks.filter((c) => c.ok).length;
  const prontoNfe = feitos === checks.length;

  const nomePreview = f.first_name.trim() || (pj ? "Nova empresa" : "Novo contato");
  const inicial = (nomePreview[0] || "?").toUpperCase();
  const tipoLabel = (CF_TIPOS.find((t) => t.value === f.type) || CF_TIPOS[0]).label;

  // Lookup CNPJ = preenchimento assistido pela BrasilAPI (não é consulta à Receita).
  const buscarCnpj = () => {
    if (!cnpjCompleto || buscando) return;
    setBuscando(true); setLookup(null);
    setTimeout(() => {
      setBuscando(false);
      setF((s) => ({ ...s,
        first_name: s.first_name || "Empresa trazida da BrasilAPI Ltda",
        nome_fantasia: s.nome_fantasia || "Nome fantasia da consulta",
        cep: s.cep || "01310-100", logradouro: s.logradouro || "Avenida Paulista",
        numero: s.numero || "1000", bairro: s.bairro || "Bela Vista", cidade: s.cidade || "São Paulo", uf: "SP" }));
      setLookup({ ok: true, msg: "Dados preenchidos pela BrasilAPI. Confira antes de salvar — a fonte é pública, não é a Receita." });
    }, 800);
  };

  const buscarCep = () => {
    if (cfDigitos(f.cep).length !== 8) return;
    setF((s) => ({ ...s, logradouro: s.logradouro || "Rua trazida do CEP", bairro: s.bairro || "Centro", cidade: s.cidade || "São Paulo", uf: s.uf || "SP" }));
  };

  return (
    <div className="os-page cf-page">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <button className="ci-voltar" onClick={() => window.__go?.("clientes")}>← Clientes</button>
          <h1>{modo === "editar" ? "Editar cadastro" : "Novo contato"}</h1>
          <p>O cadastro nasce aqui. Depois de criado, o ajuste do dia a dia acontece no painel lateral da lista.</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => window.__go?.("clientes")}>Cancelar</button>
          <button className="os-btn primary" disabled={!podeSalvar}
            onClick={() => { setSalvo(true); setTimeout(() => window.__go?.("clientes"), 900); }}>
            {salvo ? "Salvo" : "Salvar cadastro"}
          </button>
        </div>
      </header>

      <div className="cf-body">
        <div className="cf-main">
          <CfSecao titulo="Identificação">
            <CfCampo label="Tipo">
              <CfSel value={f.type} onChange={set("type")} options={CF_TIPOS}/>
            </CfCampo>
            <CfCampo label="Pessoa">
              <div className="cf-seg" role="radiogroup" aria-label="Tipo de pessoa">
                {[["person", "Física"], ["business", "Jurídica"]].map(([v, l]) => (
                  <button key={v} role="radio" aria-checked={f.pessoa === v} className={"cf-seg-b" + (f.pessoa === v ? " on" : "")} onClick={() => set("pessoa")(v)}>{l}</button>
                ))}
              </div>
            </CfCampo>
            <CfCampo label={pj ? "Razão social" : "Nome completo"} largura="full" obrigatorio erro={err("first_name")}>
              <CfIn value={f.first_name} onChange={set("first_name")} onBlur={marcar("first_name")} maxLength={100}
                placeholder={pj ? "Como está no cartão CNPJ" : "Nome completo"}/>
            </CfCampo>
            {!pj && <CfCampo label="Sobrenome"><CfIn value={f.last_name} onChange={set("last_name")} maxLength={100}/></CfCampo>}
            <CfCampo label="Tax number" help="Campo legado do UPOS — use CPF/CNPJ na seção fiscal">
              <CfIn value={f.tax_number} onChange={set("tax_number")} placeholder="Legado"/>
            </CfCampo>
          </CfSecao>

          <CfSecao titulo="Dados fiscais BR">
            <CfCampo label={pj ? "CNPJ" : "CPF"} largura="full" erro={err("cpf_cnpj")}
              help={lookup && !err("cpf_cnpj") ? null : (pj ? "A busca preenche razão social e endereço" : null)}>
              <span className="cf-in-row">
                <CfIn value={cfMascaraDoc(f.cpf_cnpj)} onChange={(v) => set("cpf_cnpj")(cfDigitos(v))} onBlur={marcar("cpf_cnpj")}
                  inputMode="numeric" autoComplete="off" maxLength={18}
                  placeholder={pj ? "00.000.000/0000-00" : "000.000.000-00"}/>
                {pj && (
                  <button className="os-btn ghost sm" onClick={buscarCnpj} disabled={!cnpjCompleto || buscando}
                    title={cnpjCompleto ? "Buscar dados na BrasilAPI" : "Digite o CNPJ completo pra habilitar"}>
                    {buscando ? "Buscando…" : "Buscar CNPJ"}
                  </button>
                )}
              </span>
              {lookup && <span className="cf-f-ok">{lookup.msg}</span>}
            </CfCampo>

            {!pj && <CfCampo label="RG"><CfIn value={f.rg} onChange={set("rg")} maxLength={20} autoComplete="off"/></CfCampo>}

            {pj && <>
              <CfCampo label="Nome fantasia" largura="full">
                <CfIn value={f.nome_fantasia} onChange={set("nome_fantasia")} maxLength={150} placeholder="Como aparece na fachada (opcional)"/>
              </CfCampo>
              <CfCampo label="Inscrição estadual (IE)">
                <CfIn value={f.inscricao_estadual} onChange={set("inscricao_estadual")} maxLength={30} placeholder="ISENTO se for o caso"/>
              </CfCampo>
              <CfCampo label="Inscrição municipal (IM)">
                <CfIn value={f.inscricao_municipal} onChange={set("inscricao_municipal")} maxLength={30}/>
              </CfCampo>
              <CfCampo label="Indicador IE (NF-e)">
                <CfSel value={f.indicador_ie} onChange={set("indicador_ie")} options={CF_IND_IE} placeholder="— Selecione —"/>
              </CfCampo>
              <CfCampo label="Regime tributário">
                <CfSel value={f.regime} onChange={set("regime")} options={CF_REGIMES} placeholder="— Selecione —"/>
              </CfCampo>
              <CfCampo label="SUFRAMA">
                <CfIn value={f.suframa} onChange={set("suframa")} maxLength={20} placeholder="Apenas Zona Franca"/>
              </CfCampo>
            </>}

            <CfCampo label="Flags" largura="full">
              <div className="cf-flags">
                <label className="cf-check">
                  <input type="checkbox" checked={f.contribuinte} onChange={set("contribuinte")}/>
                  <span>Contribuinte ICMS</span>
                </label>
                <label className="cf-check">
                  <input type="checkbox" checked={f.consumidor_final} onChange={set("consumidor_final")}/>
                  <span>Consumidor final (NF-e)</span>
                </label>
              </div>
            </CfCampo>
          </CfSecao>

          <CfSecao titulo="Contato">
            <CfCampo label="Celular"><CfIn value={f.mobile} onChange={set("mobile")} placeholder="(00) 0 0000-0000"/></CfCampo>
            <CfCampo label="Telefone fixo"><CfIn value={f.landline} onChange={set("landline")} placeholder="(00) 0000-0000"/></CfCampo>
            <CfCampo label="E-mail" erro={err("email")}><CfIn value={f.email} onChange={set("email")} onBlur={marcar("email")} placeholder="contato@exemplo.com.br"/></CfCampo>
            <CfCampo label="Site"><CfIn value={f.site} onChange={set("site")} placeholder="exemplo.com.br"/></CfCampo>
          </CfSecao>

          <CfSecao titulo="Endereço">
            <CfCampo label="CEP" help="Sai do foco e o endereço vem preenchido">
              <CfIn value={f.cep} onChange={set("cep")} onBlur={buscarCep} placeholder="00000-000"/>
            </CfCampo>
            <CfCampo label="Logradouro" largura="full"><CfIn value={f.logradouro} onChange={set("logradouro")} placeholder="Rua, avenida, estrada"/></CfCampo>
            <CfCampo label="Número"><CfIn value={f.numero} onChange={set("numero")} placeholder="123"/></CfCampo>
            <CfCampo label="Complemento"><CfIn value={f.complemento} onChange={set("complemento")} placeholder="Sala, galpão, bloco"/></CfCampo>
            <CfCampo label="Bairro"><CfIn value={f.bairro} onChange={set("bairro")}/></CfCampo>
            <CfCampo label="Cidade"><CfIn value={f.cidade} onChange={set("cidade")}/></CfCampo>
            <CfCampo label="UF">
              <CfSel value={f.uf} onChange={set("uf")} options={["AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"]}/>
            </CfCampo>
          </CfSecao>

          {mostraFinanceiro && (
            <CfSecao titulo="Financeiro">
              <CfCampo label="Limite de crédito" help="Em reais · vazio = sem limite">
                <CfIn value={f.limite} inputMode="numeric" onChange={(v) => set("limite")(cfDigitos(v))} placeholder="0"/>
              </CfCampo>
              <CfCampo label="Prazo padrão (dias)">
                <CfIn value={f.prazo} inputMode="numeric" onChange={(v) => set("prazo")(cfDigitos(v))} placeholder="30"/>
              </CfCampo>
              <CfCampo label="Grupo de cliente" help="Define a tabela de preço aplicada">
                <CfSel value={f.grupo} onChange={set("grupo")} placeholder="Sem grupo (preço padrão)"
                  options={(window.cliGruposLer ? window.cliGruposLer() : []).map((g) => ({ value: g.id, label: g.nome }))}/>
              </CfCampo>
              <CfCampo label="Forma de pagamento preferida">
                <CfSel value={f.pgto} onChange={set("pgto")} options={[
                  { value:"pix", label:"PIX" }, { value:"boleto", label:"Boleto" }, { value:"cartao", label:"Cartão" },
                  { value:"dinheiro", label:"Dinheiro" }, { value:"transferencia", label:"Transferência" }]}/>
              </CfCampo>
              <CfCampo label="Mensagem para a venda" largura="full" help="Aparece como alerta ao vendedor no PDV">
                <textarea className="cf-in cf-ta" rows={2} value={f.mensagem_venda} onChange={(e) => set("mensagem_venda")(cfVal(e))}
                  placeholder="Ex.: cliente paga só com boleto · conferir limite antes de faturar…"/>
              </CfCampo>
            </CfSecao>
          )}
        </div>

        <aside className="cf-rail" aria-label="Contexto do cadastro">
          <div className="cf-card">
            <span className="cf-card-t">Pré-visualização</span>
            <div className="cf-prev">
              <span className="cf-prev-av">{inicial}</span>
              <div className="cf-prev-tx">
                <b>{nomePreview}</b>
                <small>{pj ? "Pessoa jurídica" : "Pessoa física"} · {tipoLabel}</small>
              </div>
            </div>
            <dl className="cf-prev-dl">
              {docDigitos.length > 0 && <div><dt>{pj ? "CNPJ" : "CPF"}</dt><dd className="tabular">{cfMascaraDoc(f.cpf_cnpj)}</dd></div>}
              {!!f.email.trim() && <div><dt>E-mail</dt><dd>{f.email}</dd></div>}
              {!!f.mobile.trim() && <div><dt>Celular</dt><dd>{f.mobile}</dd></div>}
            </dl>
          </div>

          <div className="cf-card">
            <div className="cf-card-hd">
              <span className="cf-card-t">Prontidão fiscal</span>
              <span className="cf-card-n tabular">{feitos} de {checks.length}</span>
            </div>
            <ul className="cf-checks">
              {checks.map((c) => (
                <li key={c.label} className={c.ok ? "ok" : ""}>
                  <span className="cf-checks-ic" aria-hidden="true">{c.ok ? "✓" : "○"}</span>{c.label}
                </li>
              ))}
            </ul>
            {prontoNfe && <div className="cf-pronto">Pronto pra emitir NF-e</div>}
          </div>

          <div className="cf-card cf-card--slot">
            <span className="cf-card-t">Copiloto</span>
            <p>Aviso de CNPJ repetido e sugestão de grupo chegam depois — o endpoint ainda não existe.</p>
          </div>
        </aside>
      </div>
    </div>
  );
}

window.ClienteFormPage = ClienteFormPage;
