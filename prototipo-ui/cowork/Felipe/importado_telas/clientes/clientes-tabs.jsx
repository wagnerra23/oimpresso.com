// Tabs extras do drawer (Refino #3 KB-9.75):
//   • OSs           — histórico completo de ordens de serviço
//   • IA            — 4 opções de IA (resumo · sugest · próxima ação · risco)
//   • Auditoria     — histórico de alteração (C2 LGPD) + anotações

const { useState: useSX, useEffect: useEX, useMemo: useMX } = React;

// ── Mock de audit log por cliente ────────────────────────────────
// Determinístico: mesmo cliente gera mesma timeline. Realista pra LGPD.
function fakeAudit(cliente) {
  const events = [];
  const cad = cliente.cadastradoEm;
  const now = Date.now();
  const day = 86400000;
  const seed = cliente.id.charCodeAt(cliente.id.length - 1);

  events.push({
    id: 1, at: cad, who: 'Wagner', avatar: 'WR',
    type: 'create', label: 'Cliente cadastrado',
    detail: `Cadastro inicial via formulário web.`,
  });

  // Some field updates spread along the timeline
  const updates = [
    { type: 'field', label: 'Telefone alterado', detail: 'Atualizado de (11) 9 9999-9999 para ' + cliente.tel, field: 'tel' },
    { type: 'field', label: 'E-mail alterado', detail: 'E-mail principal atualizado', field: 'email' },
    { type: 'tags', label: 'Tags atualizadas', detail: `Adicionadas: ${(cliente.tags || []).slice(0, 2).join(', ')}` },
    { type: 'status', label: 'Status alterado', detail: 'ativo → ' + cliente.status, field: 'status' },
    { type: 'view', label: 'Acessado por equipe', detail: 'Visualização durante atendimento via Inbox' },
    { type: 'os', label: `OS-${2400 - seed} criada`, detail: 'Banner 2x4m · ' + window.BRL(cliente.ticketMedio) },
  ];

  const stride = Math.max(1, Math.floor((now - cad) / day / 6));
  const people = [
    { who: 'Wagner', avatar: 'WR' },
    { who: 'Larissa', avatar: 'LR' },
    { who: 'Tiago', avatar: 'TI' },
    { who: 'Marina (cliente)', avatar: 'MC' },
  ];
  const count = Math.min(6, Math.max(2, Math.floor(cliente.totalOSs / 4)));
  for (let i = 0; i < count; i++) {
    const upd = updates[(i + seed) % updates.length];
    const p = people[(i + seed * 3) % people.length];
    events.push({
      id: i + 2, at: cad + (i + 1) * stride * day,
      who: p.who, avatar: p.avatar,
      type: upd.type, label: upd.label, detail: upd.detail,
    });
  }

  // Final: last interaction
  if (cliente.saldo > 0) {
    events.push({
      id: 999, at: now - 7 * day, who: 'Wagner', avatar: 'WR',
      type: 'note', label: 'Régua de cobrança iniciada',
      detail: `Saldo aberto de ${window.BRL(cliente.saldo)} — cobrança automática agendada.`,
    });
  }
  events.push({
    id: 1000, at: now - 2 * 3600000, who: 'Wagner', avatar: 'WR',
    type: 'view', label: 'Você acessou esta ficha agora',
    detail: 'Visualização para edição (sessão atual).',
  });

  return events.sort((a, b) => b.at - a.at);
}

// ── Auditoria tab ────────────────────────────────────────────────
function AuditTab({ cliente }) {
  const events = useMX(() => fakeAudit(cliente), [cliente.id]);

  const iconFor = (type) => {
    if (type === 'create') return <Icon.UserPlus size={11} />;
    if (type === 'field')  return <Icon.Edit size={11} />;
    if (type === 'status') return <Icon.AlertCircle size={11} />;
    if (type === 'tags')   return <Icon.Tag size={11} />;
    if (type === 'view')   return <Icon.Eye size={11} />;
    if (type === 'os')     return <Icon.Briefcase size={11} />;
    if (type === 'note')   return <Icon.MessageSquare size={11} />;
    return <Icon.Clock size={11} />;
  };
  const colorFor = (type) => ({
    create: 'oklch(0.45 0.15 145)',
    field:  'oklch(0.45 0.10 220)',
    status: 'oklch(0.55 0.18 25)',
    tags:   'oklch(0.45 0.13 295)',
    view:   'var(--text-mute)',
    os:     'oklch(0.45 0.13 60)',
    note:   'var(--accent)',
  }[type] || 'var(--text-mute)');

  return (
    <div className="kb-audit">
      <div className="kb-audit-head">
        <div>
          <h3>
            <Icon.History size={13} />
            Histórico de alteração
          </h3>
          <p>Tudo o que aconteceu com essa ficha. Atende ao Art. 18 da LGPD (direito de acesso aos dados pessoais).</p>
        </div>
        <button className="cl-btn-ghost" title="Exportar log">
          <Icon.Download size={12} /> Exportar log
        </button>
      </div>

      <ol className="kb-audit-tl">
        {events.map((e, i) => (
          <li key={e.id} className="kb-audit-item">
            <div className="kb-audit-bullet" style={{ background: colorFor(e.type), color: '#fff' }}>
              {iconFor(e.type)}
            </div>
            <div className="kb-audit-body">
              <div className="kb-audit-row1">
                <b>{e.label}</b>
                <span className="kb-audit-rel">{window.relDate(e.at)}</span>
              </div>
              <p>{e.detail}</p>
              <div className="kb-audit-foot">
                <span className="kb-audit-who" style={{ background: window.avatarFor(e.who) }}>
                  {window.initialsFor(e.who)}
                </span>
                <span>{e.who}</span>
                <span className="kb-audit-mono">{new Date(e.at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>
              </div>
            </div>
          </li>
        ))}
      </ol>

      <window.KB.ComentariosBox cliente={cliente} />
    </div>
  );
}

// ── OSs tab ──────────────────────────────────────────────────────
function OssTab({ cliente }) {
  // Generate a bigger list than fakeOSs (which caps at 5)
  const oss = useMX(() => {
    const base = window.fakeOSs(cliente);
    // pad up to totalOSs but cap at 14 for display
    const n = Math.min(cliente.totalOSs, 14);
    if (base.length >= n) return base;
    const extras = [];
    const titles = ['Banner 2x4m', 'Cartões 1000un', 'Adesivos vitrine', 'Placa fachada', 'Folder A5', 'Backdrop evento', 'Lona impressa', 'Letra caixa LED', 'Faixa lateral', 'Display PDV', 'Cardápio mesa'];
    for (let i = base.length; i < n; i++) {
      const dy = (i + 1) * (15 + (i * 11 % 30));
      extras.push({
        id: `OS-${2200 + i}`,
        titulo: titles[i % titles.length],
        valor: Math.round(cliente.ticketMedio * (0.5 + ((i * 17) % 90) / 100)),
        em: Date.now() - dy * 86400000,
        status: 'Entregue',
      });
    }
    return [...base, ...extras];
  }, [cliente.id]);

  const totalGasto = oss.reduce((s, o) => s + o.valor, 0);

  return (
    <div className="kb-osstab">
      {/* KPI strip */}
      <div className="cl-hist-stats">
        <div className="cl-hist-stat">
          <small>Total de OSs</small>
          <b>{cliente.totalOSs}</b>
        </div>
        <div className="cl-hist-stat">
          <small>Ticket médio</small>
          <b>{window.BRL(cliente.ticketMedio)}</b>
        </div>
        <div className="cl-hist-stat">
          <small>Total gasto (visível)</small>
          <b>{window.BRL(totalGasto)}</b>
        </div>
        <div className="cl-hist-stat">
          <small>Saldo aberto</small>
          <b style={cliente.saldo > 0 ? { color: 'oklch(0.55 0.18 25)' } : {}}>{window.BRL(cliente.saldo)}</b>
        </div>
      </div>

      <div className="kb-oss-tools">
        <button className="cl-btn-primary"><Icon.Plus size={12} /> Nova OS pra este cliente</button>
        <span style={{ flex: 1 }} />
        <button className="cl-btn-ghost"><Icon.Filter size={12} /> Filtrar</button>
        <button className="cl-btn-ghost"><Icon.Download size={12} /> Exportar</button>
      </div>

      <div className="kb-oss-list">
        {oss.map(os => (
          <div key={os.id} className="kb-oss-row">
            <div className="kb-oss-id mono">{os.id}</div>
            <div className="kb-oss-titulo">
              <b>{os.titulo}</b>
              <small>{new Date(os.em).toLocaleDateString('pt-BR')}</small>
            </div>
            <span className={`kb-oss-status ${os.status === 'Entregue' ? 'ok' : 'pending'}`}>
              {os.status}
            </span>
            <div className="kb-oss-valor mono">{window.BRL(os.valor)}</div>
            <button className="icon-btn" title="Abrir OS"><Icon.ChevronRight size={12} /></button>
          </div>
        ))}
      </div>

      {cliente.totalOSs > 14 && (
        <button className="cl-btn-ghost" style={{ marginTop: 12, alignSelf: 'flex-start' }}>
          Mostrar todas ({cliente.totalOSs})
        </button>
      )}
    </div>
  );
}

// ── IA tab ─────────────────────────────────────────────────────────
function IATab({ cliente, onApplyForm }) {
  return (
    <div className="kb-iatab">
      <div className="kb-ia-intro">
        <Icon.Sparkles size={14} style={{ color: 'var(--accent)' }} />
        <div>
          <b>Copiloto de cliente</b>
          <p>4 análises diferentes. IA propõe, você decide. Tudo é editável antes de aplicar.</p>
        </div>
      </div>

      <div className="kb-ia-cards">
        <div className="kb-ia-card">
          <window.KB.ResumoIA cliente={cliente} />
        </div>

        <div className="kb-ia-card">
          <window.KB.AutoSugestForce form={cliente} onApply={onApplyForm} />
        </div>

        <div className="kb-ia-card">
          <ProximaAcao cliente={cliente} />
        </div>

        <div className="kb-ia-card">
          <RiscoCliente cliente={cliente} />
        </div>
      </div>
    </div>
  );
}

// Variant of AutoSugest that always renders (force re-evaluation)
function AutoSugestForce({ form, onApply }) {
  const [state, setState] = useSX('idle');
  const [suggestion, setSuggestion] = useSX(null);

  const run = async () => {
    setState('loading');
    const prompt = `Você é o Copiloto do oimpresso. Reavalie segmento e tags deste cliente e proponha possíveis correções/adições. Responda em JSON: {"segmento":"...", "tags":["...","..."], "razao":"frase curta explicando"}.

Cliente:
Nome: ${form.nome}
${form.fantasia ? 'Fantasia: ' + form.fantasia + '\n' : ''}Tipo: ${form.tipo}
Cidade: ${form.cidade || '?'}/${form.uf || '?'}
Segmento atual: ${form.segmento || 'vazio'}
Tags atuais: ${(form.tags || []).join(', ') || 'vazio'}
Total OSs: ${form.totalOSs}
Ticket médio: R$ ${form.ticketMedio || 0}

JSON:`;
    let parsed = null;
    try {
      const out = await window.claude.complete(prompt);
      const m = out.match(/\{[\s\S]*?\}/);
      if (m) parsed = JSON.parse(m[0]);
    } catch {}
    if (!parsed) {
      parsed = {
        segmento: form.segmento || 'varejo',
        tags: form.tags && form.tags.length ? form.tags : ['varejo'],
        razao: 'Baseado no perfil de OSs e segmento atual.',
      };
    }
    setSuggestion(parsed);
    setState('suggest');
  };

  const apply = () => {
    onApply({ segmento: suggestion.segmento, tags: suggestion.tags });
    setState('applied');
    setTimeout(() => setState('idle'), 1800);
  };

  return (
    <div className="kb-iac">
      <div className="kb-iac-h">
        <Icon.Tag size={13} style={{ color: 'var(--accent)' }} />
        <b>Reavaliar segmento & tags</b>
        <span style={{ flex: 1 }} />
        {state === 'idle' && <button className="cl-btn-ghost" onClick={run}><Icon.Sparkles size={11} /> Analisar</button>}
        {state === 'loading' && <span style={{ fontSize: 11, color: 'var(--text-mute)' }}><Icon.Loader size={11} className="spin" /> Pensando…</span>}
      </div>
      {state === 'idle' && (
        <p className="kb-iac-body">
          A IA olha o histórico real (OSs, ticket, cidade) e propõe um segmento + tags. Diferente do auto-sugest que aparece quando está vazio — aqui você pode reavaliar a qualquer momento.
        </p>
      )}
      {state === 'suggest' && suggestion && (
        <div>
          <div style={{ display: 'flex', gap: 10, padding: '6px 0', fontSize: 12.5 }}>
            <span className="kb-sugest-label">Segmento</span>
            <b>{suggestion.segmento}</b>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '6px 0', flexWrap: 'wrap' }}>
            <span className="kb-sugest-label">Tags</span>
            <div className="cl-tags">{suggestion.tags.map(t => (
              <span key={t} style={{ fontSize: 10.5, color: 'var(--accent)', background: 'rgba(255,255,255,.7)', border: '1px solid var(--accent-soft)', padding: '1px 7px', borderRadius: 999 }}>{t}</span>
            ))}</div>
          </div>
          {suggestion.razao && (
            <p className="kb-iac-body" style={{ marginTop: 4, fontStyle: 'italic' }}>"{suggestion.razao}"</p>
          )}
          <div style={{ display: 'flex', gap: 6, marginTop: 8 }}>
            <button className="cl-btn-primary" onClick={apply}><Icon.Check size={11} /> Aplicar</button>
            <button className="cl-btn-ghost" onClick={() => setState('idle')}>Descartar</button>
          </div>
        </div>
      )}
      {state === 'applied' && <p style={{ margin: 0, fontSize: 12, color: 'oklch(0.45 0.15 145)' }}><Icon.CheckCircle size={12} /> Aplicado.</p>}
    </div>
  );
}

function ProximaAcao({ cliente }) {
  const [state, setState] = useSX('idle');
  const [acoes, setAcoes] = useSX(null);

  const run = async () => {
    setState('loading');
    const dias = Math.floor((Date.now() - cliente.ultimaCompra) / 86400000);
    const prompt = `Você é o Copiloto do oimpresso. Proponha as próximas 2-3 ações concretas que Wagner deve tomar com este cliente. Cada ação tem: titulo (5-8 palavras), descricao (1 frase), prioridade (alta/media/baixa). Responda em JSON puro: {"acoes":[{"titulo":"...","descricao":"...","prioridade":"..."},...]}.

Cliente:
${cliente.fantasia || cliente.nome} (${cliente.tipo})
Status: ${cliente.status}
${cliente.saldo > 0 ? 'Saldo em aberto: ' + window.BRL(cliente.saldo) + '\n' : ''}Total OSs: ${cliente.totalOSs}
Ticket médio: ${window.BRL(cliente.ticketMedio)}
Última compra: há ${dias} dias
Tags: ${(cliente.tags || []).join(', ')}

JSON:`;
    let parsed = null;
    try {
      const out = await window.claude.complete(prompt);
      const m = out.match(/\{[\s\S]*\}/);
      if (m) parsed = JSON.parse(m[0]);
    } catch {}
    if (!parsed) {
      const acoes = [];
      if (cliente.saldo > 0) acoes.push({ titulo: 'Cobrar saldo aberto', descricao: `Régua de cobrança com ${window.BRL(cliente.saldo)} em aberto. WhatsApp + boleto.`, prioridade: 'alta' });
      const dias2 = Math.floor((Date.now() - cliente.ultimaCompra) / 86400000);
      if (dias2 > 90) acoes.push({ titulo: 'Reativar relacionamento', descricao: `Sem compra há ${dias2} dias. Vale uma sondagem direta no WhatsApp.`, prioridade: 'media' });
      if (cliente.totalOSs > 10) acoes.push({ titulo: 'Oferecer plano fixo', descricao: 'Cliente reincidente — vale propor desconto progressivo ou contrato mensal.', prioridade: 'media' });
      if (acoes.length === 0) acoes.push({ titulo: 'Manter contato bimestral', descricao: 'Cliente saudável. Manter cadência de relacionamento padrão.', prioridade: 'baixa' });
      parsed = { acoes };
    }
    setAcoes(parsed.acoes);
    setState('done');
  };

  const cor = (p) => p === 'alta' ? 'oklch(0.55 0.18 25)' : p === 'media' ? 'oklch(0.62 0.13 60)' : 'oklch(0.55 0.13 145)';

  return (
    <div className="kb-iac">
      <div className="kb-iac-h">
        <Icon.Target size={13} style={{ color: 'var(--accent)' }} />
        <b>Próxima ação sugerida</b>
        <span style={{ flex: 1 }} />
        {state === 'idle' && <button className="cl-btn-ghost" onClick={run}><Icon.Sparkles size={11} /> Sugerir</button>}
        {state === 'loading' && <span style={{ fontSize: 11, color: 'var(--text-mute)' }}><Icon.Loader size={11} className="spin" /> Pensando…</span>}
        {state === 'done' && <button className="cl-btn-ghost" onClick={run} title="Re-gerar"><Icon.Refresh size={11} /></button>}
      </div>
      {state === 'idle' && (
        <p className="kb-iac-body">
          O que fazer com esse cliente agora? A IA combina histórico de compras, saldo, frescor e tags.
        </p>
      )}
      {state === 'done' && acoes && (
        <div className="kb-acoes">
          {acoes.map((a, i) => (
            <div key={i} className="kb-acao">
              <span className="kb-acao-pri" style={{ background: cor(a.prioridade) }} title={a.prioridade}></span>
              <div style={{ flex: 1 }}>
                <b>{a.titulo}</b>
                <p>{a.descricao}</p>
              </div>
              <button className="cl-btn-ghost" title="Marcar como feito"><Icon.Check size={11} /></button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function RiscoCliente({ cliente }) {
  // Compute risk signals locally — no IA needed for this card, deterministic.
  const sinais = useMX(() => {
    const out = [];
    const dias = Math.floor((Date.now() - cliente.ultimaCompra) / 86400000);
    if (cliente.saldo > 0) out.push({ sinal: 'Saldo em aberto', detalhe: window.BRL(cliente.saldo), peso: cliente.saldo > 10000 ? 3 : 2 });
    if (dias > 180) out.push({ sinal: 'Sem compra há ' + dias + ' dias', detalhe: 'Cliente esfriou', peso: 3 });
    else if (dias > 90) out.push({ sinal: 'Sem compra há ' + dias + ' dias', detalhe: 'Atenção', peso: 1 });
    if (cliente.status === 'bloqueado') out.push({ sinal: 'Cliente bloqueado', detalhe: 'Status atual', peso: 4 });
    if (cliente.status === 'inativo') out.push({ sinal: 'Cliente inativo', detalhe: 'Status atual', peso: 2 });
    if (!cliente.tags || cliente.tags.length === 0) out.push({ sinal: 'Sem tags', detalhe: 'Dificulta segmentação', peso: 1 });
    if (!cliente.email) out.push({ sinal: 'Sem e-mail cadastrado', detalhe: 'Limita comunicação', peso: 1 });
    if (cliente.tipo === 'PJ' && !cliente.ie) out.push({ sinal: 'Sem inscrição estadual', detalhe: 'Pode afetar NF-e', peso: 1 });
    return out;
  }, [cliente.id]);

  const pesoTotal = sinais.reduce((s, x) => s + x.peso, 0);
  const score = Math.max(0, Math.min(10, 10 - pesoTotal));
  const nivel = score >= 8 ? 'baixo' : score >= 5 ? 'medio' : 'alto';
  const cor = nivel === 'baixo' ? 'oklch(0.55 0.13 145)' : nivel === 'medio' ? 'oklch(0.62 0.13 60)' : 'oklch(0.55 0.18 25)';

  return (
    <div className="kb-iac">
      <div className="kb-iac-h">
        <Icon.AlertCircle size={13} style={{ color: cor }} />
        <b>Score de relacionamento</b>
        <span style={{ flex: 1 }} />
        <span className="kb-risco-badge" style={{ background: cor }}>
          {score.toFixed(1)}<sub>/10</sub>
        </span>
      </div>
      <div className="kb-risco-meter">
        <div className="kb-risco-fill" style={{ width: `${score * 10}%`, background: cor }}></div>
      </div>
      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 10.5, color: 'var(--text-mute)', marginTop: 4, marginBottom: 10 }}>
        <span>risco alto</span>
        <span>risco {nivel}</span>
        <span>cliente fiel</span>
      </div>

      {sinais.length === 0 ? (
        <p className="kb-iac-body" style={{ color: 'oklch(0.45 0.15 145)' }}>
          <Icon.CheckCircle size={12} /> Nenhum sinal de risco detectado. Cliente saudável.
        </p>
      ) : (
        <ul className="kb-risco-list">
          {sinais.map((s, i) => (
            <li key={i}>
              <span className="kb-risco-pri" style={{ background: cor, opacity: 0.3 + s.peso * 0.18 }}></span>
              <div style={{ flex: 1 }}>
                <b>{s.sinal}</b>
                <small>{s.detalhe}</small>
              </div>
              <span className="kb-risco-peso">peso {s.peso}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

window.KB.AuditTab = AuditTab;
window.KB.OssTab = OssTab;
window.KB.IATab = IATab;
window.KB.AutoSugestForce = AutoSugestForce;
window.KB.fakeAudit = fakeAudit;
