// nfe-tributacao-page.jsx — Tributação · NF-e Brasil. Cockpit V2.
// GERADO pelo designer-agente (ADR 0282 §0.1) ancorado no DS canon + no domínio REAL:
//   schema  Modules/NfeBrasil/Database/Migrations/2026_05_06_010000_create_nfe_fiscal_rules_table.php
//   regras  Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php
//   CSV     Modules/NfeBrasil/Services/Tributacao/ImportRegrasCsvService::COLUNAS_OBRIGATORIAS
//   tpl     Modules/NfeBrasil/Resources/templates/*.php (11 arquivos)
// NÃO derivado do .tsx vivo (porte reverso é proibido — §5 2026-06-05 / 2026-08-28).
// 4 telas: Index (hub) · ConfigDefault (PT-02) · RegraForm (PT-02) · ImportCsv (PT-02).
// Token-driven (claro/escuro pelo host). Expõe window.NfeTributacaoPage.
(() => {
const { useState, useMemo } = React;

// Ícones locais (prefixo TbI pra não colidir com I global)
const TbI = {
  plus:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>,
  upload: (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 9l5-5 5 5M12 4v12"/></svg>,
  edit:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4H4v16h16v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>,
  trash:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>,
  search: (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>,
  back:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>,
  arrow:  (p) => <svg width={p.s||12} height={p.s||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>,
  info:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>,
  file:   (p) => <svg width={p.s||22} height={p.s||22} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/></svg>,
};

// ── Domínio: UFs (as 27 do UpsertRegraTributariaRequest::UFS) ──
const UFS = ["AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"];

// ── Domínio: regime (enum da coluna nfe_business_configs.regime) ──
const REGIMES = [
  { v: "mei",              l: "MEI",                crt: "1" },
  { v: "simples",          l: "Simples Nacional",   crt: "1" },
  { v: "lucro_presumido",  l: "Lucro Presumido",    crt: "3" },
  { v: "lucro_real",       l: "Lucro Real",         crt: "3" },
];

// ── Domínio: os 11 templates de Resources/templates/ ──
const TEMPLATES = [
  { slug:"mei-varejo-sp",                    titulo:"MEI · Varejo · SP",                       descricao:"Microempreendedor individual. NFC-e modelo 65 pra consumidor final.",             regime:"mei",             uf:"SP", modelo:"65" },
  { slug:"comercio-varejo-simples-sp",       titulo:"Comércio varejo · Simples · SP",          descricao:"Loja de rua ou e-commerce que vende pra consumidor final.",                        regime:"simples",         uf:"SP", modelo:"65" },
  { slug:"comercio-atacado-simples-sp",      titulo:"Comércio atacado/B2B · Simples · SP",     descricao:"Distribuidor, atacado pra revendedores. NFe 55 com transferência de crédito.",     regime:"simples",         uf:"SP", modelo:"55" },
  { slug:"comercio-varejo-presumido-sp",     titulo:"Comércio varejo · Lucro Presumido · SP",  descricao:"Varejo fora do Simples — CST no lugar de CSOSN.",                                 regime:"lucro_presumido", uf:"SP", modelo:"65" },
  { slug:"comercio-varejo-real-sp",          titulo:"Comércio varejo · Lucro Real · SP",       descricao:"Varejo Lucro Real, PIS/COFINS não-cumulativo.",                                    regime:"lucro_real",      uf:"SP", modelo:"65" },
  { slug:"industria-grafica-simples-sp",     titulo:"Indústria gráfica · Simples · SP",        descricao:"Gráfica que industrializa por encomenda. CFOP de industrialização.",               regime:"simples",         uf:"SP", modelo:"55" },
  { slug:"industria-grafica-presumido-sp",   titulo:"Indústria gráfica · Presumido · SP",      descricao:"Gráfica fora do Simples, com IPI destacado.",                                      regime:"lucro_presumido", uf:"SP", modelo:"55" },
  { slug:"comercio-varejo-simples-mg",       titulo:"Comércio varejo · Simples · MG",          descricao:"Varejo Simples com alíquota interna de Minas Gerais.",                             regime:"simples",         uf:"MG", modelo:"65" },
  { slug:"comercio-varejo-simples-rj",       titulo:"Comércio varejo · Simples · RJ",          descricao:"Varejo Simples com FCP do Rio de Janeiro.",                                        regime:"simples",         uf:"RJ", modelo:"65" },
  { slug:"comercio-varejo-simples-rs",       titulo:"Comércio varejo · Simples · RS",          descricao:"Varejo Simples com alíquota interna do Rio Grande do Sul.",                        regime:"simples",         uf:"RS", modelo:"65" },
  { slug:"comercio-varejo-simples-sc",       titulo:"Comércio varejo · Simples · SC",          descricao:"Varejo Simples com alíquota interna de Santa Catarina.",                           regime:"simples",         uf:"SC", modelo:"65" },
];

// ── Config default de exemplo (formato da coluna JSON tributacao_default) ──
const CONFIG = {
  regime: "simples",
  auto_emission_enabled: false,
  tributacao_default: { csosn:"102", cst:null, cfop:"5102", aliquota_icms:0, aliquota_pis:0, aliquota_cofins:0, aliquota_ipi:0 },
};

// ── Regras NCM de exemplo — ordenadas NCM → uf_origem → uf_destino (NULL last) ──
const REGRAS = [
  { id:1, ncm:"48191000", uf_origem:"SP", uf_destino:null, cfop:"5102", csosn:"102", cst:null, aliquota_icms:0.18,   aliquota_pis:0.0065, aliquota_cofins:0.03, aliquota_ipi:0,     mva:null,  fcp:null },
  { id:2, ncm:"48191000", uf_origem:"SP", uf_destino:"MG", cfop:"6102", csosn:"102", cst:null, aliquota_icms:0.12,   aliquota_pis:0.0065, aliquota_cofins:0.03, aliquota_ipi:0,     mva:null,  fcp:0.02 },
  { id:3, ncm:"49019900", uf_origem:"SP", uf_destino:null, cfop:"5101", csosn:"101", cst:null, aliquota_icms:0,      aliquota_pis:0,      aliquota_cofins:0,    aliquota_ipi:0,     mva:null,  fcp:null },
  { id:4, ncm:"39269090", uf_origem:"SP", uf_destino:"RJ", cfop:"6404", csosn:null,  cst:"010", aliquota_icms:0.12,  aliquota_pis:0.0165, aliquota_cofins:0.076, aliquota_ipi:0.05, mva:0.4131, fcp:0.02 },
  { id:5, ncm:"61091000", uf_origem:"SC", uf_destino:null, cfop:"5102", csosn:"102", cst:null, aliquota_icms:0.17,   aliquota_pis:0.0065, aliquota_cofins:0.03, aliquota_ipi:0,     mva:null,  fcp:null },
];

// ── Formatadores (contrato do charter: NCM XXXX.XX.XX · alíquota 2 casas PT-BR) ──
const fmtNcm = (n) => !n ? "—" : `${n.slice(0,4)}.${n.slice(4,6)}.${n.slice(6,8)}`;
const pct = (d) => d === null || d === undefined ? "—" : `${(d * 100).toFixed(2).replace(".", ",")}%`;
const regimeLabel = (v) => (REGIMES.find((r) => r.v === v) || {}).l || v;

// ═══════════════════════════════════════════════════════════════════
// TELA 1 · Index — hub de configuração (não casa PT-01/02: segue o DS)
// ═══════════════════════════════════════════════════════════════════
function TbIndex({ go }) {
  const [q, setQ] = useState("");
  const [auto, setAuto] = useState(CONFIG.auto_emission_enabled);
  const temConfig = !!CONFIG.regime;

  const regras = useMemo(() => {
    const t = q.trim().toLowerCase();
    if (!t) return REGRAS;
    return REGRAS.filter((r) => r.ncm.includes(t.replace(/\D/g, "")) || (r.cfop || "").includes(t));
  }, [q]);

  return (
    <div className="tb">
      {/* ── Configuração default ── */}
      <div className="tb-sec">
        <div className="tb-sec-h">
          <span className="tb-sec-t">Configuração default</span>
          <span className="tb-sec-d">Aplicada quando nenhuma regra NCM específica casa</span>
        </div>
        <div className="tb-card">
          <div className="tb-card-h">
            <span className="tb-card-t">Regime {regimeLabel(CONFIG.regime)}</span>
            <button className="tb-btn sm" onClick={() => go("config")}>
              <TbI.edit s={13} /> Editar
            </button>
          </div>
          <div className="tb-card-b">
            <div className="tb-row"><span className="tb-row-l">CFOP padrão</span><span className="tb-row-v num">{CONFIG.tributacao_default.cfop}</span></div>
            <div className="tb-row"><span className="tb-row-l">CSOSN</span><span className="tb-row-v num">{CONFIG.tributacao_default.csosn || "—"}</span></div>
            <div className="tb-row"><span className="tb-row-l">ICMS</span><span className="tb-row-v num">{pct(CONFIG.tributacao_default.aliquota_icms)}</span></div>
            <div className="tb-row"><span className="tb-row-l">PIS</span><span className="tb-row-v num">{pct(CONFIG.tributacao_default.aliquota_pis)}</span></div>
            <div className="tb-row"><span className="tb-row-l">COFINS</span><span className="tb-row-v num">{pct(CONFIG.tributacao_default.aliquota_cofins)}</span></div>
            <div className="tb-row"><span className="tb-row-l">IPI</span><span className="tb-row-v num">{pct(CONFIG.tributacao_default.aliquota_ipi)}</span></div>
          </div>
        </div>
      </div>

      {/* ── Gate per-business: emissão automática NFC-e ── */}
      <div className="tb-sec">
        <div className="tb-gate">
          <div className="tb-gate-txt">
            <div className="tb-gate-t">Emissão automática de NFC-e</div>
            <div className="tb-gate-d">
              {temConfig
                ? "Ao finalizar a venda, a NFC-e é emitida sem intervenção. Vale só para este negócio."
                : "Indisponível: configure a tributação default antes de ligar a emissão automática."}
            </div>
          </div>
          <div
            className="tb-sw"
            role="switch"
            aria-checked={auto}
            aria-label="Emissão automática de NFC-e"
            tabIndex={0}
            data-on={String(auto)}
            data-disabled={String(!temConfig)}
            onClick={() => temConfig && setAuto(!auto)}
          ><i /></div>
        </div>
      </div>

      {/* ── Templates setoriais ── */}
      <div className="tb-sec">
        <div className="tb-sec-h">
          <span className="tb-sec-t">Templates por setor</span>
          <span className="tb-sec-count">{TEMPLATES.length}</span>
          <span className="tb-sec-d">Aplicar substitui a configuração default; as regras NCM permanecem</span>
        </div>
        <div className="tb-tpl-grid">
          {TEMPLATES.map((t) => (
            <div className="tb-tpl" key={t.slug}>
              <div className="tb-tpl-t">{t.titulo}</div>
              <div className="tb-tpl-d">{t.descricao}</div>
              <div className="tb-tpl-chips">
                <span className="tb-chip">{regimeLabel(t.regime)}</span>
                <span className="tb-chip mute">{t.uf}</span>
                <span className="tb-chip mute">modelo {t.modelo}</span>
              </div>
              <div className="tb-tpl-foot">
                <span className="tb-f-hint">{t.regime === "mei" || t.regime === "simples" ? "CSOSN" : "CST"}</span>
                <button className="tb-btn sm primary">Aplicar template</button>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* ── Regras NCM específicas ── */}
      <div className="tb-sec" style={{ paddingBottom: 24 }}>
        <div className="tb-sec-h">
          <span className="tb-sec-t">Regras por NCM</span>
          <span className="tb-sec-count">{REGRAS.length}</span>
          <span className="tb-sec-d">Mais específica vence: NCM + origem + destino &gt; NCM + origem &gt; default</span>
        </div>

        <div className="tb-toolbar">
          <div className="tb-search">
            <TbI.search s={13} />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por NCM ou CFOP" aria-label="Buscar regra" />
          </div>
          <button className="tb-btn" onClick={() => go("import")}><TbI.upload s={13} /> Importar CSV</button>
          <button className="tb-btn primary" onClick={() => go("regra")}><TbI.plus s={13} /> Nova regra</button>
        </div>

        {regras.length === 0 ? (
          <div className="tb-card"><div className="tb-empty">
            <div className="tb-empty-t">Nenhuma regra encontrada</div>
            <div className="tb-empty-d">Sem regra específica, toda venda usa a configuração default acima.</div>
          </div></div>
        ) : (
          <div className="tb-tbl-wrap">
            <table className="tb-tbl">
              <thead>
                <tr>
                  <th>NCM</th><th>Origem → Destino</th><th>CFOP</th><th>CSOSN / CST</th>
                  <th className="num">ICMS</th><th className="num">PIS</th><th className="num">COFINS</th>
                  <th className="num">IPI</th><th className="num">MVA</th><th className="num">FCP</th><th />
                </tr>
              </thead>
              <tbody>
                {regras.map((r) => (
                  <tr key={r.id}>
                    <td><span className="tb-ncm">{fmtNcm(r.ncm)}</span></td>
                    <td><span className="tb-uf">{r.uf_origem} <TbI.arrow s={11} /> {r.uf_destino || "todas"}</span></td>
                    <td className="tb-ncm">{r.cfop}</td>
                    <td>
                      <span className="tb-chip mute">{r.csosn ? `CSOSN ${r.csosn}` : `CST ${r.cst}`}</span>
                    </td>
                    <td className="num">{pct(r.aliquota_icms)}</td>
                    <td className="num">{pct(r.aliquota_pis)}</td>
                    <td className="num">{pct(r.aliquota_cofins)}</td>
                    <td className="num">{pct(r.aliquota_ipi)}</td>
                    <td className="num">{pct(r.mva)}</td>
                    <td className="num">{pct(r.fcp)}</td>
                    <td>
                      <div className="tb-acts">
                        <button className="tb-ico" title="Editar regra" aria-label="Editar regra" onClick={() => go("regra")}><TbI.edit s={13} /></button>
                        <button className="tb-ico" title="Remover regra" aria-label="Remover regra"><TbI.trash s={13} /></button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// TELA 2 · ConfigDefault — PT-02 Formulário
// ═══════════════════════════════════════════════════════════════════
function TbConfigDefault({ go }) {
  const [regime, setRegime] = useState(CONFIG.regime);
  const usaCsosn = regime === "mei" || regime === "simples";

  return (
    <div className="tb">
      <div className="tb-sec" style={{ paddingBottom: 24 }}>
        <div className="tb-toolbar">
          <button className="tb-btn sm" onClick={() => go("index")}><TbI.back s={13} /> Tributação</button>
        </div>

        <div className="tb-form">
          <div className="tb-note">
            <TbI.info s={14} />
            <div>
              O <b>regime</b> decide qual código tributário vale: MEI e Simples Nacional usam <b>CSOSN</b> (CRT 1);
              Lucro Presumido e Lucro Real usam <b>CST</b> (CRT 3). Os dois nunca convivem na mesma configuração.
            </div>
          </div>

          <div className="tb-card">
            <div className="tb-card-h"><span className="tb-card-t">Regime tributário</span></div>
            <div className="tb-card-b">
              <div className="tb-fs">
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="cfg-regime">Regime <span className="tb-f-req">*</span></label>
                  <select id="cfg-regime" value={regime} onChange={(e) => setRegime(e.target.value)}>
                    {REGIMES.map((r) => <option key={r.v} value={r.v}>{r.l}</option>)}
                  </select>
                  <span className="tb-f-hint">CRT {(REGIMES.find((r) => r.v === regime) || {}).crt}</span>
                </div>
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="cfg-cfop">CFOP padrão <span className="tb-f-req">*</span></label>
                  <input id="cfg-cfop" defaultValue={CONFIG.tributacao_default.cfop} maxLength={4} inputMode="numeric" placeholder="5102" />
                  <span className="tb-f-hint">4 dígitos. 5xxx = dentro da UF · 6xxx = interestadual</span>
                </div>
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="cfg-cod">{usaCsosn ? "CSOSN" : "CST"} <span className="tb-f-req">*</span></label>
                  <input id="cfg-cod" defaultValue={usaCsosn ? CONFIG.tributacao_default.csosn : ""} maxLength={3} inputMode="numeric" placeholder={usaCsosn ? "102" : "000"} />
                  <span className="tb-f-hint">3 dígitos, conforme o regime selecionado</span>
                </div>
              </div>
            </div>
          </div>

          <div className="tb-card">
            <div className="tb-card-h"><span className="tb-card-t">Alíquotas default</span></div>
            <div className="tb-card-b">
              <div className="tb-fs">
                {[
                  { id:"icms",   l:"ICMS",   v:CONFIG.tributacao_default.aliquota_icms },
                  { id:"pis",    l:"PIS",    v:CONFIG.tributacao_default.aliquota_pis },
                  { id:"cofins", l:"COFINS", v:CONFIG.tributacao_default.aliquota_cofins },
                  { id:"ipi",    l:"IPI",    v:CONFIG.tributacao_default.aliquota_ipi },
                ].map((a) => (
                  <div className="tb-f" key={a.id}>
                    <label className="tb-f-l" htmlFor={`cfg-${a.id}`}>{a.l} <span className="tb-f-req">*</span></label>
                    <div className="tb-suffix">
                      <input id={`cfg-${a.id}`} defaultValue={(a.v * 100).toFixed(2).replace(".", ",")} inputMode="decimal" />
                      <span>%</span>
                    </div>
                  </div>
                ))}
              </div>
              <div className="tb-f-hint" style={{ marginTop: 10 }}>
                Guardado em decimal (18,00% = 0,18). Máximo 100%.
              </div>
            </div>
          </div>

          <div className="tb-foot">
            <span className="spacer" />
            <button className="tb-btn" onClick={() => go("index")}>Cancelar</button>
            <button className="tb-btn primary">Salvar configuração</button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// TELA 3 · RegraForm — PT-02 Formulário (criar/editar regra NCM)
// ═══════════════════════════════════════════════════════════════════
function TbRegraForm({ go }) {
  const [codTipo, setCodTipo] = useState("csosn");   // CSOSN ⊕ CST — exclusivo
  const [ufDestino, setUfDestino] = useState("");    // "" = todas as UFs (NULL)

  return (
    <div className="tb">
      <div className="tb-sec" style={{ paddingBottom: 24 }}>
        <div className="tb-toolbar">
          <button className="tb-btn sm" onClick={() => go("index")}><TbI.back s={13} /> Tributação</button>
        </div>

        <div className="tb-form">
          <div className="tb-card">
            <div className="tb-card-h"><span className="tb-card-t">Classificação fiscal</span></div>
            <div className="tb-card-b">
              <div className="tb-fs">
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="rg-ncm">NCM <span className="tb-f-req">*</span></label>
                  <input id="rg-ncm" maxLength={8} inputMode="numeric" placeholder="48191000" />
                  <span className="tb-f-hint">Exatamente 8 dígitos, sem pontos</span>
                </div>
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="rg-ufo">UF origem <span className="tb-f-req">*</span></label>
                  <select id="rg-ufo" defaultValue="SP">
                    {UFS.map((u) => <option key={u} value={u}>{u}</option>)}
                  </select>
                </div>
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="rg-ufd">UF destino</label>
                  <select id="rg-ufd" value={ufDestino} onChange={(e) => setUfDestino(e.target.value)}>
                    <option value="">Todas as UFs</option>
                    {UFS.map((u) => <option key={u} value={u}>{u}</option>)}
                  </select>
                  <span className="tb-f-hint">Em branco vale para qualquer destino — é a regra mais genérica</span>
                </div>
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="rg-cfop">CFOP <span className="tb-f-req">*</span></label>
                  <input id="rg-cfop" maxLength={4} inputMode="numeric" placeholder="5102" />
                  <span className="tb-f-hint">4 dígitos</span>
                </div>
              </div>
            </div>
          </div>

          <div className="tb-card">
            <div className="tb-card-h">
              <span className="tb-card-t">Código tributário</span>
              <div className="tb-tpl-chips">
                <button className={`tb-btn sm${codTipo === "csosn" ? " primary" : ""}`} onClick={() => setCodTipo("csosn")}>CSOSN</button>
                <button className={`tb-btn sm${codTipo === "cst" ? " primary" : ""}`} onClick={() => setCodTipo("cst")}>CST</button>
              </div>
            </div>
            <div className="tb-card-b">
              <div className="tb-fs">
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="rg-cod">{codTipo === "csosn" ? "CSOSN" : "CST"} <span className="tb-f-req">*</span></label>
                  <input id="rg-cod" maxLength={3} inputMode="numeric" placeholder={codTipo === "csosn" ? "102" : "000"} />
                  <span className="tb-f-hint">
                    {codTipo === "csosn" ? "Simples Nacional (CRT 1)" : "Regime Normal (CRT 3)"} — 3 dígitos
                  </span>
                </div>
              </div>
              <div className="tb-note" style={{ marginTop: 12 }}>
                <TbI.info s={14} />
                <div>Informe <b>um</b> dos dois. CSOSN e CST não podem ser preenchidos juntos — a escolha segue o regime do negócio.</div>
              </div>
            </div>
          </div>

          <div className="tb-card">
            <div className="tb-card-h"><span className="tb-card-t">Alíquotas</span></div>
            <div className="tb-card-b">
              <div className="tb-fs">
                {[
                  { id:"icms",   l:"ICMS",   req:true },
                  { id:"pis",    l:"PIS",    req:true },
                  { id:"cofins", l:"COFINS", req:true },
                  { id:"ipi",    l:"IPI",    req:true },
                ].map((a) => (
                  <div className="tb-f" key={a.id}>
                    <label className="tb-f-l" htmlFor={`rg-${a.id}`}>{a.l} {a.req && <span className="tb-f-req">*</span>}</label>
                    <div className="tb-suffix"><input id={`rg-${a.id}`} defaultValue="0,00" inputMode="decimal" /><span>%</span></div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="tb-card">
            <div className="tb-card-h"><span className="tb-card-t">Substituição tributária e FCP</span><span className="tb-chip mute">opcional</span></div>
            <div className="tb-card-b">
              <div className="tb-fs">
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="rg-mva">MVA</label>
                  <div className="tb-suffix"><input id="rg-mva" placeholder="41,31" inputMode="decimal" /><span>%</span></div>
                  <span className="tb-f-hint">Margem de valor agregado do ICMS-ST. Até 500%.</span>
                </div>
                <div className="tb-f">
                  <label className="tb-f-l" htmlFor="rg-fcp">FCP</label>
                  <div className="tb-suffix"><input id="rg-fcp" placeholder="2,00" inputMode="decimal" /><span>%</span></div>
                  <span className="tb-f-hint">Fundo de Combate à Pobreza da UF de destino.</span>
                </div>
              </div>
            </div>
          </div>

          <div className="tb-foot">
            <span className="spacer" />
            <button className="tb-btn" onClick={() => go("index")}>Cancelar</button>
            <button className="tb-btn primary">Salvar regra</button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// TELA 4 · ImportCsv — PT-02 (upload → prévia → aplicar)
// ═══════════════════════════════════════════════════════════════════
const COLUNAS = ["ncm","uf_origem","uf_destino","cfop","csosn","cst","aliquota_icms","aliquota_pis","aliquota_cofins","aliquota_ipi"];

function TbImportCsv({ go }) {
  const [fase, setFase] = useState("upload");   // upload | previa

  const previa = {
    validas: 128,
    invalidas: 3,
    erros: [
      { linha: 14, motivo: "NCM deve ter exatamente 8 dígitos." },
      { linha: 57, motivo: "Informe CSOSN (Simples) ou CST (Regime Normal)." },
      { linha: 91, motivo: "UF destino inválida." },
    ],
  };

  return (
    <div className="tb">
      <div className="tb-sec" style={{ paddingBottom: 24 }}>
        <div className="tb-toolbar">
          <button className="tb-btn sm" onClick={() => go("index")}><TbI.back s={13} /> Tributação</button>
        </div>

        <div className="tb-form">
          {fase === "upload" ? (
            <>
              <div className="tb-drop">
                <TbI.file s={26} />
                <div className="tb-drop-t">Selecione o arquivo CSV com as regras</div>
                <div className="tb-drop-d">
                  A primeira linha precisa ser o cabeçalho, com estas colunas. Nada é gravado agora —
                  o próximo passo mostra a prévia do que será criado.
                </div>
                <div className="tb-cols">
                  {COLUNAS.map((c) => <span className="tb-col-tag" key={c}>{c}</span>)}
                </div>
                <button className="tb-btn primary" style={{ marginTop: 6 }} onClick={() => setFase("previa")}>
                  <TbI.upload s={13} /> Escolher arquivo
                </button>
              </div>

              <div className="tb-note">
                <TbI.info s={14} />
                <div>
                  Alíquotas em <b>decimal</b> (0.18 = 18%). <b>uf_destino</b> em branco vale para todas as UFs.
                  Preencha <b>csosn</b> ou <b>cst</b> — nunca os dois na mesma linha.
                </div>
              </div>
            </>
          ) : (
            <>
              <div className="tb-stat">
                <div className="tb-stat-i">
                  <div className="tb-stat-n">{previa.validas}</div>
                  <div className="tb-stat-l">linhas válidas</div>
                </div>
                <div className="tb-stat-i">
                  <div className="tb-stat-n">{previa.invalidas}</div>
                  <div className="tb-stat-l">linhas com erro</div>
                </div>
                <div className="tb-stat-i">
                  <div className="tb-stat-n">{previa.validas + previa.invalidas}</div>
                  <div className="tb-stat-l">total no arquivo</div>
                </div>
              </div>

              {previa.invalidas > 0 && (
                <div className="tb-card">
                  <div className="tb-card-h"><span className="tb-card-t">Linhas recusadas</span><span className="tb-chip mute">não serão importadas</span></div>
                  <div className="tb-card-b">
                    {previa.erros.map((e) => (
                      <div className="tb-row" key={e.linha}>
                        <span className="tb-err-l">Linha <b>{e.linha}</b></span>
                        <span className="tb-row-l">{e.motivo}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <div className="tb-note">
                <TbI.info s={14} />
                <div>
                  Aplicar cria as <b>{previa.validas}</b> regras válidas e ignora as recusadas.
                  Regra com mesmo NCM, origem e destino é <b>atualizada</b>, não duplicada.
                </div>
              </div>

              <div className="tb-foot">
                <span className="spacer" />
                <button className="tb-btn" onClick={() => setFase("upload")}>Trocar arquivo</button>
                <button className="tb-btn primary">Aplicar {previa.validas} regras</button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// Host — navegação entre as 4 telas do bloco
// ═══════════════════════════════════════════════════════════════════
function NfeTributacaoPage() {
  const [tela, setTela] = useState("index");
  const go = (t) => setTela(t);

  if (tela === "config") return <TbConfigDefault go={go} />;
  if (tela === "regra")  return <TbRegraForm go={go} />;
  if (tela === "import") return <TbImportCsv go={go} />;
  return <TbIndex go={go} />;
}

window.NfeTributacaoPage = NfeTributacaoPage;
})();
