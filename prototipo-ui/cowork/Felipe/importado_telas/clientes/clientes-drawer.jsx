// Drawer de Cliente — slide-in lateral (540px) com tabs PF/PJ,
// 5 seções (Identificação · Contato · Endereço · Comercial · Classificação),
// máscaras BR, validação inline, ViaCEP mock, busca CNPJ mock,
// histórico de OSs e financeiro.

const { useState: useSD, useEffect: useED, useRef: useRD, useMemo: useMD } = React;

// ── Field primitive ──────────────────────────────────────────────────

function Field({ label, hint, error, children, span = 1, optional }) {
  return (
    <div className={`cl-field ${error ? 'has-error' : ''}`} style={{ gridColumn: `span ${span}` }}>
      <label>
        {label}
        {optional && <span className="cl-opt">(opcional)</span>}
      </label>
      {children}
      {error && <small className="cl-err"><Icon.AlertCircle size={11} /> {error}</small>}
      {!error && hint && <small className="cl-hint">{hint}</small>}
    </div>
  );
}

function TextInput({ value, onChange, placeholder, mask, ...rest }) {
  return (
    <input className="cl-input" type="text" placeholder={placeholder}
      value={value || ''}
      onChange={e => onChange(mask ? mask(e.target.value) : e.target.value)}
      {...rest} />
  );
}

function SelectInput({ value, onChange, options, placeholder = 'Selecionar…' }) {
  return (
    <select className="cl-input" value={value || ''} onChange={e => onChange(e.target.value)}>
      <option value="">{placeholder}</option>
      {options.map(o => {
        const v = typeof o === 'object' ? o.value : o;
        const l = typeof o === 'object' ? o.label : o;
        return <option key={v} value={v}>{l}</option>;
      })}
    </select>
  );
}

function TagsField({ value, onChange }) {
  const toggle = (t) => onChange(value.includes(t) ? value.filter(x => x !== t) : [...value, t]);
  return (
    <div className="cl-tags-input">
      {window.TAG_OPTIONS.map(t => (
        <button key={t} type="button" className={`cl-tag-btn ${value.includes(t) ? 'on' : ''}`}
          onClick={() => toggle(t)}>{t}</button>
      ))}
    </div>
  );
}

// ── Drawer shell ─────────────────────────────────────────────────────

function ClienteDrawer({ cliente, onClose, onSaved }) {
  const isNew = cliente === 'new';
  // Build editable form state from cliente (or empty PJ skeleton for new)
  const initial = useMD(() => {
    if (isNew) return { id: 'new', tipo: 'PJ', status: 'ativo', tags: [], segmento: '' };
    return JSON.parse(JSON.stringify(cliente));
  }, [cliente]);

  const [form, setForm] = useSD(initial);
  const [section, setSection] = useSD('identificacao');
  const [touched, setTouched] = useSD({});
  const [lookupCnpj, setLookupCnpj] = useSD(null); // 'loading' | 'ok' | null
  const [lookupCep, setLookupCep] = useSD(null);

  useED(() => { setForm(initial); setTouched({}); setSection('identificacao'); }, [cliente]);

  // Drawer keyboard shortcuts: ⌘S save · ⌘P print · 1-5 sections
  useED(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
        e.preventDefault(); onSave();
      } else if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'p' && !isNew) {
        e.preventDefault(); window.KB.printFicha(form);
      } else if (['1','2','3','4','5','6','7','8'].includes(e.key) && !(e.target.matches('input, textarea, select'))) {
        const sectionsAll = ['identificacao','contato','endereco','comercial','classificacao','oss','ia','auditoria'];
        const idx = parseInt(e.key) - 1;
        if (sectionsAll[idx] && (isNew ? idx < 5 : true)) {
          e.preventDefault(); setSection(sectionsAll[idx]);
        }
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  });

  const upd = (key, val) => setForm(f => ({ ...f, [key]: val }));
  const touch = (key) => setTouched(t => ({ ...t, [key]: true }));

  // ── Validation (only show error when touched OR on save attempt)
  const errors = useMD(() => {
    const e = {};
    const v = window.BRValidate;
    if (form.tipo === 'PF') {
      if (!form.nome || form.nome.length < 3) e.nome = 'Nome obrigatório (mínimo 3 caracteres).';
      const cpfOk = v.cpf(form.doc);
      if (cpfOk === false) e.doc = 'CPF inválido. Confere os números.';
      else if (!form.doc) e.doc = 'CPF é obrigatório.';
    } else {
      if (!form.nome || form.nome.length < 3) e.nome = 'Razão social obrigatória.';
      const cnpjOk = v.cnpj(form.doc);
      if (cnpjOk === false) e.doc = 'CNPJ inválido. Confere o dígito verificador.';
      else if (!form.doc) e.doc = 'CNPJ é obrigatório.';
    }
    const emailOk = v.email(form.email);
    if (emailOk === false) e.email = 'Formato de e-mail inválido.';
    const cepOk = v.cep(form.cep);
    if (cepOk === false) e.cep = 'CEP deve ter 8 dígitos.';
    return e;
  }, [form]);

  const visibleErrors = Object.fromEntries(
    Object.entries(errors).filter(([k]) => touched[k] || touched.__all__)
  );

  // ── Mock external lookups ────────────────────────────────────────
  const doCnpjLookup = () => {
    if (!form.doc || window.BRMask.onlyDigits(form.doc).length !== 14) return;
    setLookupCnpj('loading');
    setTimeout(() => {
      // Mock: invent / patch fields if blank
      setForm(f => ({
        ...f,
        nome: f.nome || 'Razão Social Exemplo Ltda',
        fantasia: f.fantasia || 'Fantasia Exemplo',
        ie: f.ie || '123.456.789.012',
      }));
      setLookupCnpj('ok');
      setTimeout(() => setLookupCnpj(null), 2400);
    }, 900);
  };

  const doCepLookup = () => {
    if (!form.cep || window.BRMask.onlyDigits(form.cep).length !== 8) return;
    setLookupCep('loading');
    setTimeout(() => {
      // Mock: fill bairro/cidade/uf if blank
      setForm(f => ({
        ...f,
        endereco: f.endereco || 'Av. Mock, 100',
        bairro: f.bairro || 'Bairro do Exemplo',
        cidade: f.cidade || 'São Paulo',
        uf: f.uf || 'SP',
      }));
      setLookupCep('ok');
      setTimeout(() => setLookupCep(null), 2400);
    }, 700);
  };

  const onSave = () => {
    setTouched(t => ({ ...t, __all__: true }));
    if (Object.keys(errors).length > 0) {
      // jump to the section that contains the first error
      const map = {
        nome: 'identificacao', doc: 'identificacao',
        email: 'contato', cep: 'endereco',
      };
      setSection(map[Object.keys(errors)[0]] || 'identificacao');
      return;
    }
    onSaved(form);
  };

  const sections = [
    { k: 'identificacao', label: 'Identificação', icon: <Icon.User size={12} /> },
    { k: 'contato',       label: 'Contato',       icon: <Icon.Mail size={12} /> },
    { k: 'endereco',      label: 'Endereço',      icon: <Icon.MapPin size={12} /> },
    { k: 'comercial',     label: 'Comercial',     icon: <Icon.DollarSign size={12} /> },
    { k: 'classificacao', label: 'Classificação', icon: <Icon.Tag size={12} /> },
  ];
  const extraTabs = isNew ? [] : [
    { k: 'oss',       label: 'OSs',       icon: <Icon.Briefcase size={12} /> },
    { k: 'ia',        label: 'IA',        icon: <Icon.Sparkles size={12} /> },
    { k: 'auditoria', label: 'Auditoria', icon: <Icon.History size={12} /> },
  ];
  const allTabs = [...sections, ...extraTabs];

  return (
    <React.Fragment>
      <div className="cl-backdrop" onClick={onClose} />
      <aside className="cl-drawer">
        {/* Header */}
        <header className="cl-drawer-h">
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, flex: 1, minWidth: 0 }}>
            <span style={{
              width: 40, height: 40, borderRadius: '50%',
              background: window.avatarFor(form.fantasia || form.nome || 'Novo cliente'),
              color: '#fff', display: 'grid', placeItems: 'center', fontWeight: 700, fontSize: 14,
            }}>{window.initialsFor(form.fantasia || form.nome || 'NC')}</span>
            <div style={{ flex: 1, minWidth: 0 }}>
              <h2 style={{ margin: 0, fontSize: 15, fontWeight: 600, letterSpacing: -0.01 }}>
                {isNew ? 'Novo cliente' : (form.fantasia || form.nome)}
              </h2>
              <small style={{ color: 'var(--text-dim)', fontSize: 11.5 }}>
                {isNew ? 'Preencha os dados para cadastrar' : `${form.tipo === 'PF' ? 'Pessoa física' : 'Pessoa jurídica'} · cadastrado há ${Math.floor((Date.now() - form.cadastradoEm) / 86400000)}d`}
              </small>
            </div>
          </div>
          <button className="icon-btn" onClick={onClose} title="Fechar (Esc)">
            <Icon.X size={14} />
          </button>
        </header>

        {/* Action bar */}
        <div className="cl-drawer-actions">
          <div className="cl-pfpj-toggle">
            <button type="button" className={form.tipo === 'PF' ? 'on' : ''}
              onClick={() => upd('tipo', 'PF')}>
              <Icon.User size={11} /> Pessoa física
            </button>
            <button type="button" className={form.tipo === 'PJ' ? 'on' : ''}
              onClick={() => upd('tipo', 'PJ')}>
              <Icon.Building size={11} /> Pessoa jurídica
            </button>
          </div>
          {!isNew && <window.Clientes.StatusPill status={form.status} />}
          {!isNew && window.KB.needsRevalidacao(form) && <window.KB.RevalidarPill />}
          <span style={{ flex: 1 }} />
          {!isNew && (
            <React.Fragment>
              <button className="cl-btn-ghost" title="Imprimir ficha (⌘P)"
                onClick={() => window.KB.printFicha(form)}>
                <Icon.Download size={12} /> Imprimir ficha
              </button>
              <button className="cl-btn-ghost" title="Falar com Copiloto sobre este cliente">
                <Icon.Sparkles size={12} /> Falar com Copiloto →
              </button>
            </React.Fragment>
          )}
        </div>

        {/* Section tabs */}
        <div className="cl-section-tabs">
          {allTabs.map(s => {
            const hasErr = section !== s.k && touched.__all__ && Object.keys(errors).some(k => {
              const map = { nome: 'identificacao', doc: 'identificacao', email: 'contato', cep: 'endereco' };
              return map[k] === s.k;
            });
            return (
              <button key={s.k} className={`cl-section-tab ${section === s.k ? 'active' : ''}`}
                onClick={() => setSection(s.k)}>
                {s.icon}
                <span>{s.label}</span>
                {hasErr && <span className="cl-section-dot"></span>}
              </button>
            );
          })}
        </div>

        {/* Section body */}
        <div className="cl-drawer-body">
          {!isNew && section === 'identificacao' && (
            <window.KB.AutoSugest form={form} onApply={updates => setForm(f => ({ ...f, ...updates }))} />
          )}

          {section === 'identificacao' && (
            <React.Fragment>
              <SectionIdentificacao form={form} upd={upd} touch={touch} errors={visibleErrors}
                lookup={lookupCnpj} onLookup={doCnpjLookup} />
              {!isNew && (
                <React.Fragment>
                  <HistoricoStrip cliente={cliente} />
                  <window.KB.ComentariosBox cliente={cliente} />
                </React.Fragment>
              )}
            </React.Fragment>
          )}
          {section === 'contato' && (
            <SectionContato form={form} upd={upd} touch={touch} errors={visibleErrors} />
          )}
          {section === 'endereco' && (
            <SectionEndereco form={form} upd={upd} touch={touch} errors={visibleErrors}
              lookup={lookupCep} onLookup={doCepLookup} />
          )}
          {section === 'comercial' && (
            <SectionComercial form={form} upd={upd} />
          )}
          {section === 'classificacao' && (
            <SectionClassificacao form={form} upd={upd} />
          )}
          {section === 'oss' && !isNew && (
            <window.KB.OssTab cliente={cliente} />
          )}
          {section === 'ia' && !isNew && (
            <window.KB.IATab cliente={cliente}
              onApplyForm={updates => setForm(f => ({ ...f, ...updates }))} />
          )}
          {section === 'auditoria' && !isNew && (
            <window.KB.AuditTab cliente={cliente} />
          )}
        </div>

        {/* Footer */}
        <footer className="cl-drawer-foot">
          <span style={{ fontSize: 11.5, color: 'var(--text-mute)' }}>
            {Object.keys(errors).length === 0
              ? <><Icon.CheckCircle size={11} style={{ color: 'oklch(0.55 0.18 145)', verticalAlign: -1 }} /> Tudo válido</>
              : <><Icon.AlertCircle size={11} style={{ color: 'oklch(0.55 0.18 25)', verticalAlign: -1 }} /> {Object.keys(errors).length} {Object.keys(errors).length === 1 ? 'pendência' : 'pendências'}</>}
          </span>
          <span style={{ flex: 1 }} />
          <button className="cl-btn-ghost" onClick={onClose}>Cancelar</button>
          <button className="cl-btn-primary" onClick={onSave}>
            <Icon.Check size={12} /> {isNew ? 'Cadastrar' : 'Salvar alterações'}
          </button>
        </footer>
      </aside>
    </React.Fragment>
  );
}

// ── Sections ────────────────────────────────────────────────────────

function SectionIdentificacao({ form, upd, touch, errors, lookup, onLookup }) {
  const isPJ = form.tipo === 'PJ';
  return (
    <div className="cl-form-grid">
      <Field label={isPJ ? 'Razão social' : 'Nome completo'} error={errors.nome} span={2}>
        <TextInput value={form.nome} onChange={v => upd('nome', v)} onBlur={() => touch('nome')}
          placeholder={isPJ ? 'Ex.: Dragão Verde Comunicação Visual Ltda' : 'Ex.: Marina Costa'} />
      </Field>
      {isPJ && (
        <Field label="Nome fantasia" optional span={2}>
          <TextInput value={form.fantasia} onChange={v => upd('fantasia', v)} placeholder="Como o cliente é conhecido" />
        </Field>
      )}
      <Field label={isPJ ? 'CNPJ' : 'CPF'} error={errors.doc} span={isPJ ? 1 : 1}
        hint={isPJ ? 'Clique em "Buscar" para preencher automático' : null}>
        <div style={{ display: 'flex', gap: 6 }}>
          <TextInput value={form.doc} onChange={v => upd('doc', v)} onBlur={() => touch('doc')}
            mask={isPJ ? window.BRMask.cnpj : window.BRMask.cpf}
            placeholder={isPJ ? '00.000.000/0000-00' : '000.000.000-00'} />
          {isPJ && (
            <button type="button" className="cl-btn-ghost cl-btn-attached" onClick={onLookup} disabled={!!lookup}>
              {lookup === 'loading' ? <Icon.Loader size={12} className="spin" /> :
               lookup === 'ok' ? <><Icon.CheckCircle size={12} /> Encontrado</> :
               <><Icon.Search size={12} /> Buscar CNPJ</>}
            </button>
          )}
        </div>
      </Field>
      {isPJ ? (
        <Field label="Inscrição estadual" optional>
          <TextInput value={form.ie} onChange={v => upd('ie', v)} placeholder="000.000.000.000" />
        </Field>
      ) : (
        <Field label="RG" optional>
          <TextInput value={form.rg} onChange={v => upd('rg', v)} placeholder="00.000.000-0" />
        </Field>
      )}
      {!isPJ && (
        <Field label="Data de nascimento" optional>
          <TextInput value={form.nascimento} onChange={v => upd('nascimento', v)} placeholder="AAAA-MM-DD" type="date" />
        </Field>
      )}
      {isPJ && (
        <Field label="Contato principal" optional span={2}>
          <TextInput value={form.contato} onChange={v => upd('contato', v)} placeholder="Nome do responsável" />
        </Field>
      )}
      {isPJ && (
        <Field label="Cargo do contato" optional span={2}>
          <TextInput value={form.cargo} onChange={v => upd('cargo', v)} placeholder="Ex.: Diretor de marketing" />
        </Field>
      )}
    </div>
  );
}

function SectionContato({ form, upd, touch, errors }) {
  return (
    <div className="cl-form-grid">
      <Field label="Telefone principal" span={1}>
        <TextInput value={form.tel} onChange={v => upd('tel', v)} mask={window.BRMask.tel}
          placeholder="(00) 0 0000-0000" />
      </Field>
      <Field label="Telefone alternativo" optional span={1}>
        <TextInput value={form.tel2} onChange={v => upd('tel2', v)} mask={window.BRMask.tel}
          placeholder="(00) 0 0000-0000" />
      </Field>
      <Field label="E-mail" error={errors.email} span={2}>
        <TextInput value={form.email} onChange={v => upd('email', v)} onBlur={() => touch('email')}
          placeholder="contato@exemplo.com.br" type="email" />
      </Field>
      <Field label="Site" optional span={2}>
        <TextInput value={form.site} onChange={v => upd('site', v)} placeholder="exemplo.com.br" />
      </Field>
      <Field label="Canal preferido" optional span={2}>
        <div className="cl-radio-row">
          {[{v:'whatsapp',l:'WhatsApp'},{v:'email',l:'E-mail'},{v:'telefone',l:'Telefone'},{v:'presencial',l:'Presencial'}].map(o => (
            <label key={o.v} className={`cl-radio ${form.canal === o.v ? 'on' : ''}`}>
              <input type="radio" name="canal" checked={form.canal === o.v} onChange={() => upd('canal', o.v)} />
              {o.l}
            </label>
          ))}
        </div>
      </Field>
    </div>
  );
}

function SectionEndereco({ form, upd, touch, errors, lookup, onLookup }) {
  return (
    <div className="cl-form-grid">
      <Field label="CEP" error={errors.cep} hint="Auto-preenche endereço (ViaCEP)" span={1}>
        <div style={{ display: 'flex', gap: 6 }}>
          <TextInput value={form.cep} onChange={v => upd('cep', v)} onBlur={() => { touch('cep'); onLookup(); }}
            mask={window.BRMask.cep} placeholder="00000-000" />
          <button type="button" className="cl-btn-ghost cl-btn-attached" onClick={onLookup} disabled={!!lookup}>
            {lookup === 'loading' ? <Icon.Loader size={12} className="spin" /> :
             lookup === 'ok' ? <><Icon.CheckCircle size={12} /> Endereço!</> :
             <><Icon.Search size={12} /> Buscar</>}
          </button>
        </div>
      </Field>
      <Field label="Endereço" span={2}>
        <TextInput value={form.endereco} onChange={v => upd('endereco', v)} placeholder="Rua, avenida…" />
      </Field>
      <Field label="Número" span={1}>
        <TextInput value={form.numero} onChange={v => upd('numero', v)} placeholder="123" />
      </Field>
      <Field label="Complemento" optional span={2}>
        <TextInput value={form.complemento} onChange={v => upd('complemento', v)} placeholder="Apto, conjunto, sala…" />
      </Field>
      <Field label="Bairro" span={1}>
        <TextInput value={form.bairro} onChange={v => upd('bairro', v)} placeholder="" />
      </Field>
      <Field label="Cidade" span={2}>
        <TextInput value={form.cidade} onChange={v => upd('cidade', v)} placeholder="" />
      </Field>
      <Field label="UF" span={1}>
        <SelectInput value={form.uf} onChange={v => upd('uf', v)} options={window.UF_OPTIONS} placeholder="UF" />
      </Field>
    </div>
  );
}

function SectionComercial({ form, upd }) {
  return (
    <div className="cl-form-grid">
      <Field label="Limite de crédito" optional span={1} hint="Em R$, deixe vazio para sem limite">
        <TextInput value={form.limite} onChange={v => upd('limite', v.replace(/[^\d]/g, ''))}
          placeholder="0" />
      </Field>
      <Field label="Prazo padrão (dias)" optional span={1}>
        <TextInput value={form.prazo} onChange={v => upd('prazo', v.replace(/[^\d]/g, ''))} placeholder="30" />
      </Field>
      <Field label="Tabela de preço" optional span={2}>
        <SelectInput value={form.tabelaPreco} onChange={v => upd('tabelaPreco', v)}
          options={[
            { value: 'padrao', label: 'Padrão (atacado quando ≥ 100 un)' },
            { value: 'varejo', label: 'Varejo' },
            { value: 'atacado', label: 'Atacado fixo' },
            { value: 'parceiro', label: 'Parceiro (desconto 12%)' },
          ]} placeholder="Selecionar tabela" />
      </Field>
      <Field label="Forma de pagamento preferida" optional span={2}>
        <div className="cl-radio-row">
          {[{v:'pix',l:'PIX'},{v:'boleto',l:'Boleto'},{v:'cartao',l:'Cartão'},{v:'dinheiro',l:'Dinheiro'},{v:'transferencia',l:'Transferência'}].map(o => (
            <label key={o.v} className={`cl-radio ${form.pgto === o.v ? 'on' : ''}`}>
              <input type="radio" name="pgto" checked={form.pgto === o.v} onChange={() => upd('pgto', o.v)} />
              {o.l}
            </label>
          ))}
        </div>
      </Field>
      <Field label="Observações comerciais" optional span={2}>
        <textarea className="cl-input cl-textarea" value={form.obsComercial || ''}
          onChange={e => upd('obsComercial', e.target.value)}
          rows={3} placeholder="Particularidades de negociação, condições especiais…" />
      </Field>
    </div>
  );
}

function SectionClassificacao({ form, upd }) {
  return (
    <div className="cl-form-grid">
      <Field label="Segmento" span={2}>
        <SelectInput value={form.segmento} onChange={v => upd('segmento', v)}
          options={[
            { value: 'varejo', label: 'Varejo (lojinha, loja própria)' },
            { value: 'atacado', label: 'Atacado / distribuição' },
            { value: 'agência', label: 'Agência / parceiro de mídia' },
            { value: 'corporativo', label: 'Corporativo / B2B' },
            { value: 'evento', label: 'Evento pontual' },
            { value: 'governo', label: 'Governo / órgão público' },
          ]} placeholder="Selecionar segmento" />
      </Field>
      <Field label="Tags" span={2} hint="Selecione quantas quiser. Clique pra alternar.">
        <TagsField value={form.tags || []} onChange={v => upd('tags', v)} />
      </Field>
      <Field label="Status" span={2}>
        <div className="cl-radio-row">
          {[
            { v: 'ativo', l: 'Ativo', c: 'oklch(0.55 0.18 145)' },
            { v: 'inativo', l: 'Inativo', c: 'oklch(0.55 0.01 80)' },
            { v: 'bloqueado', l: 'Bloqueado', c: 'oklch(0.55 0.18 25)' },
          ].map(o => (
            <label key={o.v} className={`cl-radio ${form.status === o.v ? 'on' : ''}`}>
              <input type="radio" name="status" checked={form.status === o.v} onChange={() => upd('status', o.v)} />
              <span style={{ width: 7, height: 7, borderRadius: 999, background: o.c, display: 'inline-block', marginRight: 4 }}></span>
              {o.l}
            </label>
          ))}
        </div>
      </Field>
      <Field label="VIP" optional span={2} hint="Clientes VIP têm prioridade na agenda de produção">
        <label className="cl-toggle">
          <input type="checkbox" checked={!!form.vip} onChange={e => upd('vip', e.target.checked)} />
          <span className="cl-toggle-track"><span className="cl-toggle-thumb"></span></span>
          <span>Marcar como VIP</span>
        </label>
      </Field>
    </div>
  );
}

// ── Histórico embaixo do form (OS + financeiro) ─────────────────────

function HistoricoStrip({ cliente }) {
  const oss = useMD(() => window.fakeOSs(cliente), [cliente.id]);
  return (
    <div className="cl-hist">
      <h3>
        <Icon.History size={13} />
        Histórico do cliente
      </h3>
      <window.KB.ResumoIA cliente={cliente} />
      <div className="cl-hist-stats" style={{ marginTop: 16 }}>
        <div className="cl-hist-stat">
          <small>OSs no total</small>
          <b>{cliente.totalOSs}</b>
        </div>
        <div className="cl-hist-stat">
          <small>Ticket médio</small>
          <b>{window.BRL(cliente.ticketMedio)}</b>
        </div>
        <div className="cl-hist-stat">
          <small>Saldo aberto</small>
          <b style={cliente.saldo > 0 ? { color: 'oklch(0.55 0.18 25)' } : {}}>{window.BRL(cliente.saldo)}</b>
        </div>
        <div className="cl-hist-stat">
          <small>Última compra</small>
          <b>{window.relDate(cliente.ultimaCompra)}</b>
        </div>
      </div>
      <h4>Últimas ordens de serviço</h4>
      <div className="cl-hist-list">
        {oss.map(os => (
          <div key={os.id} className="cl-hist-row">
            <span className="mono cl-hist-num">{os.id}</span>
            <div className="cl-hist-titulo">
              <b>{os.titulo}</b>
              <small>{new Date(os.em).toLocaleDateString('pt-BR')}</small>
            </div>
            <span style={{
              fontSize: 10.5, fontWeight: 500, padding: '2px 8px', borderRadius: 999,
              background: os.status === 'Entregue' ? 'oklch(0.93 0.07 145)' : 'oklch(0.94 0.07 80)',
              color: os.status === 'Entregue' ? 'oklch(0.32 0.10 145)' : 'oklch(0.40 0.13 65)',
            }}>{os.status}</span>
            <span className="mono cl-hist-valor">{window.BRL(os.valor)}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

window.ClienteDrawer = ClienteDrawer;
