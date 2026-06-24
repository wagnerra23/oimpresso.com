// perfil-page.jsx — "Meu perfil" (conta do usuário logado). Cockpit V2.
// Redesign fiel da tela legada resources/views/user/profile.blade.php (UltimatePOS HRM):
// Alterar senha · Editar perfil · Foto · Mais informações · Dados bancários.
// Token-driven (claro/escuro pelo toggle do host). Expõe window.PerfilPage.
(() => {
const { useState, useRef } = React;

// Ícones locais (prefixo PfI pra não colidir com I global)
const PfI = {
  user:   (p) => <svg width={p.s||15} height={p.s||15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>,
  info:   (p) => <svg width={p.s||15} height={p.s||15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>,
  bank:   (p) => <svg width={p.s||15} height={p.s||15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 10 12 4l9 6"/><path d="M5 10v8M19 10v8M9 10v8M15 10v8M3 21h18"/></svg>,
  lock:   (p) => <svg width={p.s||15} height={p.s||15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>,
  phone:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.6 2Z"/></svg>,
  mail:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>,
  link:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>,
  cam:    (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 8a2 2 0 0 1 2-2h2l1.5-2h7L19 6h0a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><circle cx="12" cy="13" r="3.5"/></svg>,
  check:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 5 5L20 7"/></svg>,
};

// Estado inicial — espelha os campos reais do user model (pré-preenchido com Wagner do print)
const INIT = {
  surname: "Sr", first_name: "Wagner", last_name: "Rocha",
  email: "wagner@wr2.com.br", language: "pt-BR",
  dob: "", gender: "", marital_status: "", blood_group: "",
  contact_number: "", alt_number: "", family_number: "",
  fb_link: "", twitter_link: "", social_media_1: "", social_media_2: "",
  guardian_name: "", id_proof_name: "", id_proof_number: "",
  permanent_address: "", current_address: "",
  bank_account_holder: "", bank_account_number: "", bank_name: "",
  bank_code: "", bank_branch: "", tax_payer_id: "",
};

const LANGS = [
  { v: "pt-BR", l: "Português (Brasil)" },
  { v: "en", l: "English" },
  { v: "es", l: "Español" },
];
const GENDERS = [
  { v: "", l: "Selecionar" },
  { v: "male", l: "Masculino" },
  { v: "female", l: "Feminino" },
  { v: "others", l: "Outro" },
];
const MARITAL = [
  { v: "", l: "Selecionar" },
  { v: "married", l: "Casado(a)" },
  { v: "unmarried", l: "Solteiro(a)" },
  { v: "divorced", l: "Divorciado(a)" },
];

function Field({ label, req, hint, span, children }) {
  return (
    <div className={"pf-field" + (span ? " span-2" : "")}>
      <label className="pf-label">{label}{req && <span className="req">*</span>}</label>
      {children}
      {hint && <span className="pf-hint">{hint}</span>}
    </div>
  );
}

function PerfilPage() {
  const [tab, setTab] = useState("conta");
  const [f, setF] = useState(INIT);
  const [dirty, setDirty] = useState(false);
  const [saved, setSaved] = useState(false);
  const [photo, setPhoto] = useState(null);
  const fileRef = useRef(null);

  // senha (form separado no legado)
  const [pw, setPw] = useState({ current: "", next: "", confirm: "" });
  const pwMismatch = pw.next && pw.confirm && pw.next !== pw.confirm;

  const set = (k) => (e) => { setF((s) => ({ ...s, [k]: e.target.value })); setDirty(true); setSaved(false); };

  const initials = ((f.first_name[0] || "") + (f.last_name[0] || "")).toUpperCase() || "U";
  const fullName = [f.surname, f.first_name, f.last_name].filter(Boolean).join(" ");

  const onPhoto = (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    setPhoto(url); setDirty(true); setSaved(false);
  };

  const onSave = () => { setDirty(false); setSaved(true); };

  const TABS = [
    { id: "conta", label: "Conta", ic: PfI.user },
    { id: "info", label: "Mais informações", ic: PfI.info },
    { id: "banco", label: "Dados bancários", ic: PfI.bank },
    { id: "seguranca", label: "Segurança", ic: PfI.lock },
  ];

  const AvatarVisual = () => photo
    ? <img src={photo} alt="" />
    : <span>{initials}</span>;

  return (
    <div className="pf" data-screen-label="Perfil · Meu perfil">
      {/* Header de identidade */}
      <header className="pf-head">
        <div className="pf-avatar"><AvatarVisual /></div>
        <div className="pf-id">
          <div className="pf-id-name">
            {fullName || "Meu perfil"}
            <span className="pf-chip">Administrador</span>
          </div>
          <div className="pf-id-sub">
            <span>{f.email || "—"}</span>
            <span className="sep">·</span>
            <span>WR2 Sistemas</span>
            <span className="sep">·</span>
            <span>Conta do usuário</span>
          </div>
        </div>
        <div className="pf-head-actions">
          <button className="pf-btn primary" onClick={onSave} disabled={!dirty}>
            {saved ? <><PfI.check s={14}/>Salvo</> : "Salvar alterações"}
          </button>
        </div>
      </header>

      {/* Tabs */}
      <nav className="pf-tabs" aria-label="Seções do perfil">
        {TABS.map((t) => {
          const Ic = t.ic;
          return (
            <button key={t.id}
              className={"pf-tab" + (tab === t.id ? " active" : "")}
              onClick={() => setTab(t.id)}>
              <Ic s={14}/><span>{t.label}</span>
            </button>
          );
        })}
      </nav>

      <div className="pf-body">
        {tab === "conta" && (
          <div className="pf-grid">
            <div className="pf-col">
              <section className="pf-card">
                <div className="pf-card-head">
                  <PfI.user s={15}/>
                  <div>
                    <h3>Editar perfil</h3>
                    <p className="desc">Nome de exibição e dados de acesso</p>
                  </div>
                </div>
                <div className="pf-card-body">
                  <div className="pf-field-grid cols-3">
                    <Field label="Prefixo">
                      <input className="pf-input" value={f.surname} onChange={set("surname")} placeholder="Sr / Sra" />
                    </Field>
                    <Field label="Primeiro nome" req>
                      <input className="pf-input" value={f.first_name} onChange={set("first_name")} placeholder="Primeiro nome" />
                    </Field>
                    <Field label="Sobrenome">
                      <input className="pf-input" value={f.last_name} onChange={set("last_name")} placeholder="Sobrenome" />
                    </Field>
                  </div>
                  <div className="pf-field-grid cols-2" style={{ marginTop: 14 }}>
                    <Field label="E-mail">
                      <div className="pf-input-wrap">
                        <PfI.mail s={14}/>
                        <input className="pf-input" type="email" value={f.email} onChange={set("email")} placeholder="email@empresa.com.br" />
                      </div>
                    </Field>
                    <Field label="Idioma">
                      <select className="pf-select" value={f.language} onChange={set("language")}>
                        {LANGS.map((o) => <option key={o.v} value={o.v}>{o.l}</option>)}
                      </select>
                    </Field>
                  </div>
                </div>
              </section>
            </div>

            <div className="pf-col">
              <section className="pf-card">
                <div className="pf-card-head">
                  <PfI.cam s={15}/>
                  <div><h3>Foto de perfil</h3></div>
                </div>
                <div className="pf-card-body">
                  <div className="pf-photo">
                    <div className="pf-photo-preview"><AvatarVisual /></div>
                    <div className="pf-photo-actions">
                      <button className="pf-btn sm" onClick={() => fileRef.current?.click()}>
                        <PfI.cam s={13}/>Escolher imagem
                      </button>
                      {photo && <button className="pf-btn sm ghost" onClick={() => { setPhoto(null); setDirty(true); }}>Remover</button>}
                    </div>
                    <input ref={fileRef} type="file" accept="image/*" hidden onChange={onPhoto} />
                    <p className="pf-photo-hint">JPG ou PNG · tamanho máximo 5 MB</p>
                  </div>
                </div>
              </section>
            </div>
          </div>
        )}

        {tab === "info" && (
          <div className="pf-col" style={{ maxWidth: 820 }}>
            <section className="pf-card">
              <div className="pf-card-head">
                <PfI.info s={15}/>
                <div><h3>Dados pessoais</h3></div>
              </div>
              <div className="pf-card-body">
                <div className="pf-field-grid cols-2">
                  <Field label="Data de nascimento">
                    <input className="pf-input" type="date" value={f.dob} onChange={set("dob")} />
                  </Field>
                  <Field label="Gênero">
                    <select className="pf-select" value={f.gender} onChange={set("gender")}>
                      {GENDERS.map((o) => <option key={o.v} value={o.v}>{o.l}</option>)}
                    </select>
                  </Field>
                  <Field label="Estado civil">
                    <select className="pf-select" value={f.marital_status} onChange={set("marital_status")}>
                      {MARITAL.map((o) => <option key={o.v} value={o.v}>{o.l}</option>)}
                    </select>
                  </Field>
                  <Field label="Grupo sanguíneo">
                    <input className="pf-input" value={f.blood_group} onChange={set("blood_group")} placeholder="ex: O+" />
                  </Field>
                  <Field label="Nome do responsável">
                    <input className="pf-input" value={f.guardian_name} onChange={set("guardian_name")} placeholder="Nome do responsável" />
                  </Field>
                  <Field label="">
                    <span />
                  </Field>
                </div>
              </div>
            </section>

            <section className="pf-card">
              <div className="pf-card-head">
                <PfI.phone s={15}/>
                <div><h3>Contatos</h3></div>
              </div>
              <div className="pf-card-body">
                <div className="pf-field-grid cols-3">
                  <Field label="Celular">
                    <div className="pf-input-wrap"><PfI.phone s={14}/><input className="pf-input" value={f.contact_number} onChange={set("contact_number")} placeholder="(00) 0 0000-0000" /></div>
                  </Field>
                  <Field label="Telefone alternativo">
                    <input className="pf-input" value={f.alt_number} onChange={set("alt_number")} placeholder="(00) 0000-0000" />
                  </Field>
                  <Field label="Contato da família">
                    <input className="pf-input" value={f.family_number} onChange={set("family_number")} placeholder="(00) 0 0000-0000" />
                  </Field>
                </div>
                <div className="pf-field-grid cols-2" style={{ marginTop: 14 }}>
                  <Field label="Facebook">
                    <div className="pf-input-wrap"><PfI.link s={14}/><input className="pf-input" value={f.fb_link} onChange={set("fb_link")} placeholder="facebook.com/usuario" /></div>
                  </Field>
                  <Field label="Twitter / X">
                    <div className="pf-input-wrap"><PfI.link s={14}/><input className="pf-input" value={f.twitter_link} onChange={set("twitter_link")} placeholder="x.com/usuario" /></div>
                  </Field>
                  <Field label="Rede social 1">
                    <input className="pf-input" value={f.social_media_1} onChange={set("social_media_1")} placeholder="Link" />
                  </Field>
                  <Field label="Rede social 2">
                    <input className="pf-input" value={f.social_media_2} onChange={set("social_media_2")} placeholder="Link" />
                  </Field>
                </div>
              </div>
            </section>

            <section className="pf-card">
              <div className="pf-card-head">
                <PfI.info s={15}/>
                <div><h3>Documento &amp; endereços</h3></div>
              </div>
              <div className="pf-card-body">
                <div className="pf-field-grid cols-2">
                  <Field label="Tipo de documento">
                    <input className="pf-input" value={f.id_proof_name} onChange={set("id_proof_name")} placeholder="ex: RG, CPF, CNH" />
                  </Field>
                  <Field label="Número do documento">
                    <input className="pf-input" value={f.id_proof_number} onChange={set("id_proof_number")} placeholder="Número" />
                  </Field>
                  <Field label="Endereço permanente" span>
                    <input className="pf-input" value={f.permanent_address} onChange={set("permanent_address")} placeholder="Rua, número, bairro, cidade" />
                  </Field>
                  <Field label="Endereço atual" span>
                    <input className="pf-input" value={f.current_address} onChange={set("current_address")} placeholder="Rua, número, bairro, cidade" />
                  </Field>
                </div>
              </div>
            </section>
          </div>
        )}

        {tab === "banco" && (
          <div className="pf-col" style={{ maxWidth: 820 }}>
            <section className="pf-card">
              <div className="pf-card-head">
                <PfI.bank s={15}/>
                <div>
                  <h3>Dados bancários</h3>
                  <p className="desc">Usados para folha de pagamento e reembolsos</p>
                </div>
              </div>
              <div className="pf-card-body">
                <div className="pf-field-grid cols-2">
                  <Field label="Titular da conta">
                    <input className="pf-input" value={f.bank_account_holder} onChange={set("bank_account_holder")} placeholder="Nome do titular" />
                  </Field>
                  <Field label="Número da conta">
                    <input className="pf-input" value={f.bank_account_number} onChange={set("bank_account_number")} placeholder="00000-0" />
                  </Field>
                  <Field label="Banco">
                    <input className="pf-input" value={f.bank_name} onChange={set("bank_name")} placeholder="Nome do banco" />
                  </Field>
                  <Field label="Código do banco">
                    <input className="pf-input" value={f.bank_code} onChange={set("bank_code")} placeholder="ex: 341" />
                  </Field>
                  <Field label="Agência">
                    <input className="pf-input" value={f.bank_branch} onChange={set("bank_branch")} placeholder="0000" />
                  </Field>
                  <Field label="CPF/CNPJ do titular">
                    <input className="pf-input" value={f.tax_payer_id} onChange={set("tax_payer_id")} placeholder="CPF ou CNPJ" />
                  </Field>
                </div>
                <div className="pf-note" style={{ marginTop: 16 }}>
                  <PfI.info s={14}/>
                  <span>Estes dados são sensíveis (LGPD). Visíveis apenas para você e o setor financeiro.</span>
                </div>
              </div>
            </section>
          </div>
        )}

        {tab === "seguranca" && (
          <div className="pf-col" style={{ maxWidth: 560 }}>
            <section className="pf-card">
              <div className="pf-card-head">
                <PfI.lock s={15}/>
                <div>
                  <h3>Alterar senha</h3>
                  <p className="desc">Recomendamos ao menos 8 caracteres</p>
                </div>
              </div>
              <div className="pf-card-body">
                <div className="pf-field-grid">
                  <Field label="Senha atual" req>
                    <div className="pf-input-wrap"><PfI.lock s={14}/><input className="pf-input" type="password" value={pw.current} onChange={(e) => setPw((s) => ({ ...s, current: e.target.value }))} placeholder="Senha atual" /></div>
                  </Field>
                  <Field label="Nova senha" req>
                    <div className="pf-input-wrap"><PfI.lock s={14}/><input className="pf-input" type="password" value={pw.next} onChange={(e) => setPw((s) => ({ ...s, next: e.target.value }))} placeholder="Nova senha" /></div>
                  </Field>
                  <Field label="Confirmar nova senha" req hint={pwMismatch ? null : undefined}>
                    <div className="pf-input-wrap"><PfI.lock s={14}/><input className="pf-input" type="password" value={pw.confirm} onChange={(e) => setPw((s) => ({ ...s, confirm: e.target.value }))} placeholder="Confirmar nova senha" style={pwMismatch ? { borderColor: "oklch(0.58 0.18 25)" } : null} /></div>
                    {pwMismatch && <span className="pf-hint" style={{ color: "oklch(0.58 0.18 25)" }}>As senhas não coincidem.</span>}
                  </Field>
                </div>
                <div style={{ display: "flex", justifyContent: "flex-end", marginTop: 16 }}>
                  <button className="pf-btn primary" disabled={!pw.current || !pw.next || pwMismatch}>Atualizar senha</button>
                </div>
              </div>
            </section>
          </div>
        )}

        {/* Barra de salvar — aparece quando há alterações não salvas (abas de dados) */}
        {dirty && tab !== "seguranca" && (
          <div className="pf-savebar">
            <span className="msg"><b>Alterações não salvas.</b> Revise antes de sair.</span>
            <button className="pf-btn ghost" onClick={() => { setF(INIT); setPhoto(null); setDirty(false); }}>Descartar</button>
            <button className="pf-btn primary" onClick={onSave}>Salvar alterações</button>
          </div>
        )}
      </div>
    </div>
  );
}

window.PerfilPage = PerfilPage;
})();
