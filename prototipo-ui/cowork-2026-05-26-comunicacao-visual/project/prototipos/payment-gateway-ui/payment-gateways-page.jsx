/* @ts-nocheck */
/* eslint-disable */
// payment-gateways-page.jsx — Tela 2 F1 · Settings/PaymentGateways/Index
// Persona: Wagner. Wagner-only · permission paymentgateway.credenciais.*
// Material canon: boleto-contas-app.jsx 668-826 (SheetConfigInter 4 tabs generalizado)
(() => {
const { useState, useMemo } = React;
const {
  PG_brl: brl, PG_fmtDate: fmtDate, PG_fmtDateRel: fmtDateRel, PG_cn: cn,
  PG_I: I, PG_DRIVERS: DRIVERS, PG_ACCOUNTS: ACCOUNTS, PG_GATEWAYS: GATEWAYS, PG_COBRANCAS: COBRANCAS,
  PG_Btn: Btn, PG_KpiCard: KpiCard, PG_Header: Header,
} = window;

const TODAY = '2026-05-19';

const HEALTH_STYLES = {
  ok:       { bg:'bg-emerald-50', fg:'text-emerald-700', dot:'bg-emerald-500', label:'OK' },
  degraded: { bg:'bg-amber-50',   fg:'text-amber-700',   dot:'bg-amber-500',   label:'Degradado' },
  down:     { bg:'bg-rose-50',    fg:'text-rose-700',    dot:'bg-rose-500',    label:'Fora do ar' },
};

function HealthBadge({ status }) {
  const s = HEALTH_STYLES[status] || HEALTH_STYLES.ok;
  return (
    <span className={cn("inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border border-current/20", s.bg, s.fg)}>
      <span className={cn("w-1 h-1 rounded-full", s.dot)} />
      {s.label}
    </span>
  );
}

function DriverChip({ driver, size = 'sm' }) {
  const d = DRIVERS[driver];
  if (!d) return null;
  return (
    <span className="inline-flex items-center gap-1.5">
      <span className={cn(
        "rounded-sm grid place-items-center text-white font-bold tracking-tight shrink-0",
        size === 'lg' ? "w-7 h-7 text-[12px]" : "w-5 h-5 text-[10px]",
        d.dot
      )}>{d.sigla}</span>
      <span className={cn("text-[12px] font-medium", size === 'lg' ? "text-[13px]" : "text-stone-700")}>{d.nome}</span>
      {d.deprecated && (
        <span className="text-[9.5px] uppercase font-bold px-1 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">deprecated</span>
      )}
    </span>
  );
}

// ─────────────────────────────────────────────────────────────
// PaymentGatewaysPage — Tela 2 root
// ─────────────────────────────────────────────────────────────
function PaymentGatewaysPage() {
  const [drawer, setDrawer] = useState(null);
  const [novoOpen, setNovoOpen] = useState(false);

  const kpis = useMemo(() => {
    const ativas = GATEWAYS.filter(g => g.ativo).length;
    const fail = GATEWAYS.filter(g => g.health !== 'ok').length;
    const cobsHoje = COBRANCAS.filter(c => c.emitida_em?.startsWith(TODAY)).length;
    return { ativas, fail, cobsHoje };
  }, []);

  return (
    <div className="h-full bg-stone-50 flex flex-col font-sans" data-screen-label="02 Settings/Gateways">

      <Header
        title="Gateways de Pagamento"
        breadcrumb="Configurações · Pagamento"
        right={<>
          <Btn variant="outline"><span>{I.shield}</span>Testar todos</Btn>
          <Btn variant="primary" onClick={() => setNovoOpen(true)}>{I.plus}Novo gateway</Btn>
        </>}
      />

      {/* KPIs */}
      <div className="px-6 pt-5 grid grid-cols-3 gap-3">
        <KpiCard label="Credenciais ativas" value={kpis.ativas} sub={`de ${GATEWAYS.length} configuradas · ${GATEWAYS.length - kpis.ativas} inativas/legacy`} icon={I.shield} />
        <KpiCard tone={kpis.fail > 0 ? 'rose' : 'emerald'} label="Health check" value={kpis.fail > 0 ? `${kpis.fail} fail` : '100% OK'} sub={kpis.fail > 0 ? 'BCB Pix degradado · ver detalhes' : 'todos drivers respondendo'} icon={I.webhook} />
        <KpiCard label="Cobranças hoje" value={kpis.cobsHoje} sub="emitidas em todos drivers · navegacional" icon={I.receipt} />
      </div>

      {/* Aviso BCB warn */}
      {GATEWAYS.some(g => g.warn && g.health !== 'down') && (
        <div className="px-6 pt-3">
          <div className="bg-amber-50 border border-amber-200 rounded-md p-3 flex items-start gap-3 text-[11.5px]">
            <span className="text-amber-700 mt-0.5">{I.alert}</span>
            <div className="flex-1 text-amber-900">
              <strong>1 credencial precisa de atenção:</strong> BCB · Recebedor Pix Aut. — mTLS expira em 47 dias. Renove o certificado antes do vencimento ou cobranças PIX Automático param.
            </div>
            <Btn variant="outline" size="xs" className="!border-amber-300 !text-amber-800">Renovar mTLS</Btn>
          </div>
        </div>
      )}

      {/* TABELA */}
      <div className="px-6 pt-4 pb-3 flex items-center gap-2">
        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Gateways configurados · {GATEWAYS.length}</div>
        <div className="flex-1" />
        <div className="text-[11px] text-stone-500 tabular-nums">última sync 09:14 BRT</div>
      </div>

      <div className="px-6 pb-6 flex-1 overflow-auto">
        <div className="bg-white border border-stone-200 rounded-md overflow-hidden">
          <table className="w-full text-[12.5px]" style={{fontVariantNumeric:'tabular-nums'}}>
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/60">
                <th className="pl-5 pr-2 py-2 text-left font-medium">Apelido</th>
                <th className="px-2 py-2 text-left font-medium w-[200px]">Driver</th>
                <th className="px-2 py-2 text-left font-medium w-[120px]">Ambiente</th>
                <th className="px-2 py-2 text-left font-medium w-[200px]">Conta destino</th>
                <th className="px-2 py-2 text-left font-medium w-[140px]">Health · latência</th>
                <th className="px-2 py-2 text-left font-medium w-[100px]">Ativo</th>
                <th className="pl-2 pr-5 py-2 text-right font-medium w-[100px]"></th>
              </tr>
            </thead>
            <tbody>
              {GATEWAYS.map(g => {
                const d = DRIVERS[g.driver];
                const acct = ACCOUNTS.find(a => a.id === g.account_id);
                return (
                  <tr key={g.id} className="border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer" onClick={() => setDrawer(g)}>
                    <td className="pl-5 pr-2 py-2.5">
                      <div className="font-medium text-stone-900">{g.nome}</div>
                      {g.warn && <div className="text-[10.5px] text-amber-700 mt-0.5">{I.alert}{g.warn}</div>}
                    </td>
                    <td className="px-2 py-2.5"><DriverChip driver={g.driver} /></td>
                    <td className="px-2 py-2.5">
                      <span className={cn("inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border",
                        g.ambiente === 'production' ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-amber-50 text-amber-700 border-amber-200"
                      )}>
                        <span className={cn("w-1 h-1 rounded-full", g.ambiente === 'production' ? "bg-emerald-500" : "bg-amber-500")} />
                        {g.ambiente === 'production' ? 'Produção' : 'Sandbox'}
                      </span>
                    </td>
                    <td className="px-2 py-2.5 text-stone-700">
                      {acct ? (
                        <button className="hover:underline hover:underline-offset-2 text-left inline-flex items-center gap-1" onClick={e => e.stopPropagation()}>
                          {acct.name}{I.external}
                        </button>
                      ) : <span className="text-stone-400 italic">não vinculado</span>}
                    </td>
                    <td className="px-2 py-2.5">
                      <HealthBadge status={g.health} />
                      <div className="text-[10.5px] text-stone-400 tabular-nums mt-0.5">{g.latencia ? `${g.latencia}ms` : '—'} · {fmtDateRel(g.last_check.slice(0,10), TODAY)}</div>
                    </td>
                    <td className="px-2 py-2.5">
                      <Toggle on={g.ativo} />
                    </td>
                    <td className="pl-2 pr-5 py-2.5 text-right" onClick={e => e.stopPropagation()}>
                      <button title="Rodar health check" className="inline-flex items-center justify-center w-6 h-6 rounded hover:bg-stone-200 text-stone-500">{I.refresh}</button>
                      <button title="Configurar" className="inline-flex items-center justify-center w-6 h-6 rounded hover:bg-stone-200 text-stone-500 ml-0.5">{I.settings}</button>
                      <button title="Mais ações" className="inline-flex items-center justify-center w-6 h-6 rounded hover:bg-stone-200 text-stone-500 ml-0.5">{I.more}</button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* SEÇÃO: drivers disponíveis */}
        <div className="mt-6">
          <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500 mb-2">Drivers disponíveis</div>
          <div className="grid grid-cols-5 gap-3">
            {Object.values(DRIVERS).map(d => (
              <div key={d.key} className={cn(
                "bg-white border rounded-md p-3 transition",
                d.deprecated ? "border-amber-200 opacity-70" : "border-stone-200 hover:shadow-sm hover:border-stone-300"
              )}>
                <div className="flex items-start gap-2">
                  <span className={cn("w-7 h-7 rounded-sm grid place-items-center text-white text-[12px] font-bold", d.dot)}>{d.sigla}</span>
                  <div className="flex-1 min-w-0">
                    <div className="text-[12.5px] font-semibold truncate">{d.nome}</div>
                    {d.deprecated && <div className="text-[9.5px] uppercase tracking-widest font-bold text-amber-700">deprecated</div>}
                  </div>
                </div>
                <div className="mt-2 flex flex-wrap gap-1">
                  {d.tipos.map(t => {
                    const tp = window.PG_TIPOS[t];
                    return <span key={t} className={cn("text-[9.5px] font-medium px-1 py-0.5 rounded", tp?.bg, tp?.fg)}>{tp?.short}</span>;
                  })}
                </div>
                <div className="text-[10.5px] text-stone-500 mt-2 leading-snug">{d.cred}</div>
                {d.deprecated && <div className="text-[10px] text-amber-700 mt-1.5">{d.deprecatedReason}</div>}
              </div>
            ))}
          </div>
        </div>
      </div>

      {drawer && <DrawerGateway gateway={drawer} onClose={() => setDrawer(null)} />}
      {novoOpen && <SheetNovoGateway onClose={() => setNovoOpen(false)} />}
    </div>
  );
}

function Toggle({ on }) {
  return (
    <span className={cn("inline-flex w-9 h-5 rounded-full p-0.5 transition", on ? "bg-emerald-500" : "bg-stone-300")}>
      <span className={cn("w-4 h-4 rounded-full bg-white shadow-sm transition", on && "translate-x-4")} />
    </span>
  );
}

// ─────────────────────────────────────────────────────────────
// DrawerGateway — 4 tabs canônicas: Identificação / Credenciais / Webhook / Health
// ─────────────────────────────────────────────────────────────
function DrawerGateway({ gateway, onClose }) {
  const d = DRIVERS[gateway.driver];
  const acct = ACCOUNTS.find(a => a.id === gateway.account_id);
  const [tab, setTab] = useState('identificacao');
  const [revealSecret, setRevealSecret] = useState(false);
  const [testStatus, setTestStatus] = useState(null);
  const testar = () => {
    setTestStatus('testando');
    setTimeout(() => setTestStatus('ok'), 1100);
  };

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[640px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <span className={cn("w-9 h-9 rounded-md grid place-items-center text-white text-[12px] font-bold shrink-0", d.dot)}>{d.sigla}</span>
          <div className="flex-1 min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Gateway #{gateway.id} · {d.nome}</div>
            <div className="text-[15px] font-semibold mt-0.5 truncate">{gateway.nome}</div>
          </div>
          <HealthBadge status={gateway.health} />
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>

        {/* Tabs */}
        <div className="border-b border-stone-200 bg-stone-50/40 px-5">
          <div className="flex gap-1">
            {[
              { id:'identificacao', label:'Identificação' },
              { id:'credenciais',   label:'Credenciais' },
              { id:'webhook',       label:'Webhook' },
              { id:'health',        label:'Health' },
            ].map(t => (
              <button key={t.id} onClick={() => setTab(t.id)} className={cn(
                "h-9 px-3 text-[12px] border-b-2 -mb-px transition",
                tab === t.id ? "border-stone-900 text-stone-900 font-medium" : "border-transparent text-stone-500 hover:text-stone-800"
              )}>{t.label}</button>
            ))}
          </div>
        </div>

        <div className="flex-1 overflow-auto px-5 py-4 space-y-4">

          {tab === 'identificacao' && (
            <div className="space-y-3">
              <Field label="Apelido"><input defaultValue={gateway.nome} className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]" /></Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Driver">
                  <div className="h-8 bg-stone-50 border border-stone-300 rounded px-2 flex items-center gap-2 text-[12.5px]">
                    <span className={cn("w-2 h-2 rounded-sm", d.dot)} />
                    <span>{d.nome}</span>
                    <span className="text-[10.5px] text-stone-400 ml-auto font-mono">{d.key}</span>
                  </div>
                </Field>
                <Field label="Ambiente">
                  <div className="inline-flex bg-stone-100 rounded p-0.5 border border-stone-200">
                    {d.ambientes.map(a => (
                      <button key={a} className={cn(
                        "h-7 px-3 rounded text-[11.5px] transition",
                        gateway.ambiente === a ? "bg-white shadow-sm font-medium" : "text-stone-600"
                      )}>{a === 'production' ? 'produção' : 'sandbox'}</button>
                    ))}
                  </div>
                </Field>
              </div>
              <Field label="Conta destino (FK accounts)">
                <select defaultValue={gateway.account_id || ''} className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]">
                  {ACCOUNTS.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Status"><Toggle on={gateway.ativo} /></Field>
                <Field label="Criado em"><div className="text-[12.5px] py-1">{fmtDate(gateway.created_at)}</div></Field>
              </div>
            </div>
          )}

          {tab === 'credenciais' && (
            <div className="space-y-3">
              <div className="text-[10.5px] text-stone-500 bg-stone-50 border border-stone-200 rounded px-3 py-2 leading-snug">
                {d.cred}
              </div>
              {d.key === 'inter' && (
                <>
                  <Field label="Client ID"><input defaultValue="4f9c2a8e-7b1d-4e3f-9c80-2a8e7b1d4e3f" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
                  <Field label="Client Secret">
                    <div className="flex gap-2">
                      <input type={revealSecret ? 'text' : 'password'} defaultValue="•••••••••••••••• (criptografado)" className="flex-1 h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" />
                      <Btn variant="outline" size="xs" onClick={() => setRevealSecret(s => !s)}>{revealSecret ? 'Ocultar' : 'Editar'}</Btn>
                    </div>
                  </Field>
                  <div className="grid grid-cols-2 gap-3">
                    <FileField label="Certificado .crt" hint="público · base64 no config_json" />
                    <FileField label="Chave privada .key" hint="criptografada · nunca exibida" />
                  </div>
                </>
              )}
              {d.key === 'c6' && (
                <div className="grid grid-cols-3 gap-3">
                  <Field label="Agência"><input defaultValue="0001" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                  <Field label="Conta"><input defaultValue="12440-3" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                  <Field label="Código cliente"><input defaultValue="4892331" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                </div>
              )}
              {d.key === 'asaas' && (
                <Field label="API Key">
                  <div className="flex gap-2">
                    <input type={revealSecret ? 'text' : 'password'} defaultValue="$aact_YTU5YTE0M2M2N..." className="flex-1 h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" />
                    <Btn variant="outline" size="xs" onClick={() => setRevealSecret(s => !s)}>{revealSecret ? 'Ocultar' : 'Editar'}</Btn>
                  </div>
                </Field>
              )}
              {d.key === 'bcb_pix' && (
                <>
                  <div className="bg-violet-50 border border-violet-200 rounded p-3 text-[11px] text-violet-900 mb-2">
                    {I.shield}<strong className="ml-1">Resolução BCB 380/2024:</strong> exige homologação prévia do CNPJ recebedor + certificado mTLS válido. Sandbox BCB libera o PSP testar antes da homologação production.
                  </div>
                  <Field label="CNPJ recebedor homologado">
                    <input defaultValue="12.345.678/0001-90" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" />
                  </Field>
                  <div className="grid grid-cols-2 gap-3">
                    <FileField label="Certificado mTLS .crt" hint="emitido pela ICP-Brasil" />
                    <FileField label="Chave mTLS .key" hint="senha em campo separado" />
                  </div>
                  <Field label="Senha do certificado">
                    <input type="password" defaultValue="••••••••••" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" />
                  </Field>
                  <Field label="Status homologação">
                    <div className="h-8 bg-emerald-50 border border-emerald-200 rounded px-2 flex items-center gap-2 text-[12px] text-emerald-700">
                      <span>{I.check}</span><span>Recebedor homologado · DICT online</span>
                    </div>
                  </Field>
                </>
              )}
              {d.key === 'pesapal' && (
                <div className="bg-amber-50 border border-amber-200 rounded p-3 text-[11.5px] text-amber-900">
                  <div className="font-medium mb-1">Driver deprecated</div>
                  <p>PesaPal foi UltimatePOS legacy pra cartão internacional. Hoje recomenda-se <strong>Asaas</strong> (BR nativo + 3DS + PIX). Migração: criar Asaas → desativar PesaPal → backfill subscriptions ativas.</p>
                  <Btn variant="outline" size="xs" className="mt-2 !border-amber-300 !text-amber-800">Iniciar migração</Btn>
                </div>
              )}
            </div>
          )}

          {tab === 'webhook' && (
            <div className="space-y-3">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">URL pública</div>
              <div className="flex items-center gap-2 bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
                <div className="font-mono text-[11.5px] flex-1 break-all text-stone-700">
                  https://app.oimpresso.com/webhooks/{d.key}/{gateway.id}
                </div>
                <Btn variant="outline" size="xs">{I.copy}Copiar</Btn>
              </div>
              <div className="text-[10.5px] text-stone-500">
                Cole esta URL no painel {d.nome} → Integrações → Webhooks. Idempotência garantida via <span className="font-mono">gateway_webhook_events.external_id</span>.
              </div>

              <div className="pt-2">
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Assinatura HMAC (verificação)</div>
                <div className="flex gap-2">
                  <input type={revealSecret ? 'text' : 'password'} defaultValue="whsec_8f3a4b9c2e1d6f5a..." className="flex-1 h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" />
                  <Btn variant="outline" size="xs" onClick={() => setRevealSecret(s => !s)}>{revealSecret ? 'Ocultar' : 'Rotacionar'}</Btn>
                </div>
              </div>

              <div className="pt-2">
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Eventos recebidos · últimas 24h</div>
                <div className="border border-stone-200 rounded-md overflow-hidden">
                  {[
                    { evento:'cobranca.paga',       qtd:8,  ultimo:'09:14' },
                    { evento:'cobranca.emitida',    qtd:14, ultimo:'08:32' },
                    { evento:'cobranca.cancelada',  qtd:1,  ultimo:'07:18' },
                  ].map((e, i) => (
                    <div key={i} className="px-3 py-2 border-b border-stone-100 last:border-b-0 flex items-center text-[12px]">
                      <span className="font-mono text-stone-700 flex-1">{e.evento}</span>
                      <span className="tabular-nums text-stone-500">{e.qtd}× · último {e.ultimo}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {tab === 'health' && (
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-3">
                <KpiCard label="Último check" value={gateway.last_check.slice(11,16)} sub={fmtDate(gateway.last_check.slice(0,10))} icon={I.refresh} />
                <KpiCard label="Latência" value={gateway.latencia ? `${gateway.latencia}ms` : '—'} sub={gateway.latencia && gateway.latencia > 500 ? 'acima do esperado (<500ms)' : 'dentro do SLA'} tone={gateway.latencia > 500 ? 'rose' : 'emerald'} icon={I.zap} />
                <KpiCard label="Status" value={HEALTH_STYLES[gateway.health].label} sub={gateway.warn || 'sem alertas'} tone={gateway.health === 'ok' ? 'emerald' : gateway.health === 'down' ? 'rose' : 'default'} icon={I.shield} />
              </div>

              <Btn variant="outline" onClick={testar} disabled={testStatus === 'testando'}>
                {testStatus === 'testando' ? I.refresh : I.shield}
                {testStatus === 'testando' ? 'Testando…' : 'Rodar health check agora'}
              </Btn>
              {testStatus === 'ok' && (
                <div className="bg-emerald-50 border border-emerald-200 rounded p-3 text-[11.5px] text-emerald-900">
                  <div className="flex items-center gap-2">{I.check}<strong>Conexão OK</strong> · 240ms · OAuth válido · {d.tipos.length} tipo(s) suportado(s)</div>
                </div>
              )}

              <div className="pt-2">
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-2">Histórico (7 dias)</div>
                <div className="flex items-end gap-1 h-16">
                  {[1,1,1,0.5,1,1,gateway.health === 'ok' ? 1 : 0.3].map((h, i) => (
                    <div key={i} className={cn("flex-1 rounded-t", h === 1 ? "bg-emerald-400" : h === 0.5 ? "bg-amber-400" : "bg-rose-400")} style={{height: `${h * 100}%`}} />
                  ))}
                </div>
                <div className="flex justify-between text-[9.5px] text-stone-400 mt-1 tabular-nums">
                  {['7d','6d','5d','4d','3d','2d','hoje'].map(l => <span key={l}>{l}</span>)}
                </div>
              </div>
            </div>
          )}
        </div>

        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          <div className="text-[11px] text-stone-500">Alterações em credenciais ou conta com cobranças em aberto exigem confirmação extra.</div>
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          <Btn>Salvar</Btn>
        </div>
      </div>
    </div>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1">{label}</div>
      {children}
    </label>
  );
}

function FileField({ label, hint, accept }) {
  return (
    <Field label={label}>
      <div className="h-8 bg-white border border-stone-300 border-dashed rounded flex items-center gap-2 px-2 text-[11.5px] text-stone-500 hover:border-stone-500 cursor-pointer transition">
        <span>{I.upload}</span>
        <span>arrastar arquivo {accept || ''} ou clicar</span>
      </div>
      {hint && <div className="text-[10.5px] text-stone-500 mt-1">{hint}</div>}
    </Field>
  );
}

// ─────────────────────────────────────────────────────────────
// SheetNovoGateway — 3-step wizard: driver / credenciais / vínculo
// ─────────────────────────────────────────────────────────────
function SheetNovoGateway({ onClose }) {
  const [step, setStep] = useState(1);
  const [driver, setDriver] = useState(null);

  const d = driver && DRIVERS[driver];
  const canNext = step === 1 ? !!driver : true;

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[640px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Novo gateway</div>
            <div className="text-[15px] font-semibold mt-0.5">passo {step} de 3</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>

        <div className="px-5 py-3 border-b border-stone-200 bg-stone-50/40 flex items-center gap-2 text-[11px]">
          {['Driver','Credenciais','Vínculo'].map((s, i) => (
            <React.Fragment key={i}>
              <div className={cn("flex items-center gap-1.5", step === i+1 ? "text-stone-900 font-semibold" : step > i+1 ? "text-emerald-700" : "text-stone-400")}>
                <span className={cn("w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold",
                  step === i+1 ? "bg-stone-900 text-white" :
                  step > i+1 ? "bg-emerald-100 text-emerald-700" :
                  "bg-stone-200 text-stone-500"
                )}>{step > i+1 ? '✓' : i+1}</span>
                {s}
              </div>
              {i < 2 && <span className="text-stone-300">{I.chevR}</span>}
            </React.Fragment>
          ))}
        </div>

        <div className="flex-1 overflow-auto p-5">
          {step === 1 && (
            <div className="space-y-3">
              <div className="text-[11px] text-stone-500">Escolha o driver. Cada um suporta tipos diferentes de cobrança.</div>
              <div className="space-y-2">
                {Object.values(DRIVERS).map(opt => (
                  <button key={opt.key} onClick={() => setDriver(opt.key)} className={cn(
                    "w-full text-left rounded-md border p-3 transition flex items-start gap-3",
                    driver === opt.key ? "border-stone-900 ring-2 ring-stone-900/10 bg-stone-50" : "border-stone-200 hover:border-stone-400 hover:bg-stone-50",
                    opt.deprecated && "opacity-70"
                  )}>
                    <span className={cn("w-9 h-9 rounded-md grid place-items-center text-white text-[13px] font-bold shrink-0", opt.dot)}>{opt.sigla}</span>
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <div className="text-[13px] font-semibold">{opt.nome}</div>
                        {opt.deprecated && <span className="text-[9px] uppercase tracking-widest font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">deprecated</span>}
                      </div>
                      <div className="flex flex-wrap gap-1 mt-1.5">
                        {opt.tipos.map(t => {
                          const tp = window.PG_TIPOS[t];
                          return <span key={t} className={cn("text-[10px] font-medium px-1.5 py-0.5 rounded", tp?.bg, tp?.fg)}>{tp?.short}</span>;
                        })}
                        <span className="text-[10px] text-stone-400 ml-1">· {opt.ambientes.join(' / ')}</span>
                      </div>
                      <div className="text-[10.5px] text-stone-500 mt-1.5">{opt.cred}</div>
                    </div>
                    {opt.key === 'bcb_pix' && <span className="text-[9px] uppercase tracking-widest font-bold text-violet-700 self-start">novo</span>}
                  </button>
                ))}
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="space-y-3">
              <Field label="Apelido">
                <input placeholder={`ex: ${d?.nome} · Operacional`} className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]" />
              </Field>
              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700 mb-3">
                <strong>{d?.nome}:</strong> {d?.cred}
              </div>
              {/* Campos dinâmicos resumidos por driver */}
              {d?.key === 'inter' && <>
                <Field label="Client ID"><input className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
                <Field label="Client Secret"><input type="password" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
                <div className="grid grid-cols-2 gap-3">
                  <FileField label="Certificado .crt" />
                  <FileField label="Chave .key" />
                </div>
              </>}
              {d?.key === 'asaas' && <>
                <Field label="API Key"><input type="password" placeholder="$aact_..." className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
                <Field label="Webhook secret"><input type="password" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
              </>}
              {d?.key === 'c6' && <div className="grid grid-cols-3 gap-3">
                <Field label="Agência"><input className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                <Field label="Conta"><input className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                <Field label="Código cliente"><input className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
              </div>}
              {d?.key === 'bcb_pix' && <>
                <Field label="CNPJ recebedor"><input placeholder="12.345.678/0001-90" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                <div className="grid grid-cols-2 gap-3">
                  <FileField label="Cert mTLS ICP-Brasil .crt" />
                  <FileField label="Chave mTLS .key" />
                </div>
                <Field label="Senha do certificado"><input type="password" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
              </>}
              {d?.key === 'pesapal' && <>
                <Field label="API Key"><input type="password" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
                <Field label="Consumer Secret"><input type="password" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
              </>}

              <div className="pt-2 border-t border-stone-200 mt-3 flex items-center gap-2">
                <Btn variant="outline">{I.shield}Testar conexão</Btn>
                <span className="text-[10.5px] text-stone-500">verifica credenciais antes de salvar</span>
              </div>
            </div>
          )}

          {step === 3 && (
            <div className="space-y-3">
              <Field label="Conta destino (FK accounts)">
                <select className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]">
                  {ACCOUNTS.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
              </Field>
              <Field label="Ambiente inicial">
                <div className="inline-flex bg-stone-100 rounded p-0.5 border border-stone-200">
                  {d?.ambientes.map(a => (
                    <button key={a} className={cn(
                      "h-7 px-3 rounded text-[11.5px] transition",
                      a === d.ambientes[0] ? "bg-white shadow-sm font-medium" : "text-stone-600"
                    )}>{a === 'production' ? 'produção' : 'sandbox'}</button>
                  ))}
                </div>
              </Field>
              <label className="flex items-center gap-3 py-1.5">
                <input type="checkbox" defaultChecked className="accent-stone-900 w-4 h-4" />
                <div>
                  <div className="text-[12.5px] text-stone-800">Ativar imediatamente</div>
                  <div className="text-[10.5px] text-stone-500">se desligado, gateway fica cadastrado mas não emite cobrança</div>
                </div>
              </label>
              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700">
                Ao confirmar, será criada uma linha em <span className="font-mono">payment_gateway_credentials</span> com <span className="font-mono">business_id={1}</span>. Webhook URL será gerada automaticamente — cole no painel do {d?.nome} após criação.
              </div>
            </div>
          )}
        </div>

        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          {step > 1 && <Btn variant="outline" onClick={() => setStep(s => s - 1)}>{I.arrowL}Voltar</Btn>}
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          {step < 3 && <Btn variant="primary" onClick={() => setStep(s => s + 1)} disabled={!canNext}>Avançar{I.arrowR}</Btn>}
          {step === 3 && <Btn variant="primary" onClick={onClose}>{I.check}Criar gateway</Btn>}
        </div>
      </div>
    </div>
  );
}

window.PG_PaymentGatewaysPage = PaymentGatewaysPage;
})();
