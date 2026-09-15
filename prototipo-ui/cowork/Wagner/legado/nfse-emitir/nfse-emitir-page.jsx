// nfse-emitir-page.jsx — Emissão de NFS-e (nota fiscal de serviço). Cockpit V2.
// GERADO pelo designer-agente (ADR 0282 §0.1) ancorado no DS canon + no domínio REAL:
//   campos/limites  Modules/NFSe/Http/Requests/StoreNfseRequest::rules()
//   props da tela   Modules/NFSe/Http/Controllers/NfseController::emitir (Inertia::render)
//   permissão       nfse.emit
// NÃO derivado do .tsx vivo (porte reverso é proibido — §5 2026-06-05 / 2026-08-28).
// 2 modos: avulsa · vinculada a uma venda (transaction_id).
// Token-driven (claro/escuro pelo host). Expõe window.NfseEmitirPage.
(() => {
const { useState, useMemo } = React;

const NeI = {
  send:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 3 10.5 13.5M21 3l-6.5 18-4-8-8-4Z"/></svg>,
  back:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>,
  user:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>,
  doc:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/></svg>,
  cart:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12h11.2L21 7H6"/></svg>,
  alert: (p) => <svg width={p.s||15} height={p.s||15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3 2 20h20Z"/><path d="M12 10v4M12 17h.01"/></svg>,
  shield:(p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6Z"/><path d="m9 12 2 2 4-4"/></svg>,
};

// ── Props que o Controller entrega (formato de NfseController::emitir) ──
const CONFIG = { lc116_codigo_default: "14.01", aliquota_iss: 0.05, ambiente: "homologacao", cert_valido: true, cert_expira: "12/03/2027" };
const VENDA  = { transaction_id: 4821, invoice_no: "0001234", transaction_date: "2026-09", contact_nome: "Construtora Vale Verde LTDA", contact_cnpj: "12.345.678/0001-90", contact_cpf: null, contact_email: "financeiro@valeverde.com.br", final_total: 3250.00 };

const brl = (v) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);
const pct = (d) => `${(d * 100).toFixed(2).replace(".", ",")}%`;

function NfseEmitirPage() {
  // modo: "venda" = veio de uma Transaction · "avulsa" = emissão direta
  const [modo, setModo] = useState("venda");
  const venda = modo === "venda" ? VENDA : null;

  const [valor, setValor]     = useState(venda ? venda.final_total : 0);
  const [aliq, setAliq]       = useState(CONFIG.aliquota_iss);
  const [retido, setRetido]   = useState(false);
  const [desc, setDesc]       = useState("");

  const iss = useMemo(() => Number(valor || 0) * Number(aliq || 0), [valor, aliq]);
  const liquido = retido ? Number(valor || 0) - iss : Number(valor || 0);
  const certOk = CONFIG.cert_valido;

  return (
    <div className="ne">
      <div className="ne-wrap">

        {/* ── Ambiente + certificado ── */}
        <div className="ne-amb">
          <NeI.shield s={15} />
          <div>
            <div className="ne-amb-t">Certificado digital {certOk ? "válido" : "vencido"}</div>
            <div className="ne-amb-d">
              {certOk ? `Vence em ${CONFIG.cert_expira}` : "Renove o certificado A1 antes de emitir"}
            </div>
          </div>
          <span className="spacer" />
          <span className={`ne-chip${CONFIG.ambiente === "producao" ? "" : " mute"}`}>
            {CONFIG.ambiente === "producao" ? "Produção" : "Homologação"}
          </span>
          {/* alternador só do protótipo, pra mostrar os 2 modos da tela */}
          <button className="ne-btn sm" onClick={() => setModo(modo === "venda" ? "avulsa" : "venda")}>
            {modo === "venda" ? "Ver modo avulso" : "Ver modo com venda"}
          </button>
        </div>

        {/* key={modo}: os campos usam defaultValue (uncontrolled). Sem remount, trocar de modo
            deixava o tomador da venda preenchido na emissão avulsa — pego na medição do DOM. */}
        <div className="ne-form" key={modo}>
          {!certOk && (
            <div className="ne-block">
              <NeI.alert s={15} />
              <div><b>Emissão bloqueada.</b> Sem certificado A1 válido a prefeitura recusa o RPS. Atualize em Configurações → Certificado.</div>
            </div>
          )}

          {CONFIG.ambiente !== "producao" && (
            <div className="ne-block">
              <NeI.alert s={15} />
              <div><b>Ambiente de homologação.</b> A nota é gerada para teste e <b>não tem validade fiscal</b>.</div>
            </div>
          )}

          {/* ── Venda de origem (só no modo vinculado) ── */}
          {venda && (
            <div className="ne-card">
              <div className="ne-card-h">
                <span className="ne-card-t"><NeI.cart s={14} /> Venda de origem</span>
                <span className="ne-chip mute">#{venda.transaction_id}</span>
              </div>
              <div className="ne-card-b">
                <div className="ne-venda">
                  <div><div className="ne-vi-l">Nota</div><div className="ne-vi-v num">{venda.invoice_no}</div></div>
                  <div><div className="ne-vi-l">Competência</div><div className="ne-vi-v num">{venda.transaction_date}</div></div>
                  <div><div className="ne-vi-l">Cliente</div><div className="ne-vi-v">{venda.contact_nome}</div></div>
                  <div><div className="ne-vi-l">Total da venda</div><div className="ne-vi-v num">{brl(venda.final_total)}</div></div>
                </div>
              </div>
            </div>
          )}

          {/* ── Tomador ── */}
          <div className="ne-card">
            <div className="ne-card-h"><span className="ne-card-t"><NeI.user s={14} /> Tomador do serviço</span></div>
            <div className="ne-card-b">
              <div className="ne-fs">
                <div className="ne-f wide">
                  <label className="ne-f-l" htmlFor="ne-nome">Nome ou razão social <span className="ne-f-req">*</span></label>
                  <input id="ne-nome" defaultValue={venda ? venda.contact_nome : ""} maxLength={150} placeholder="Quem contratou o serviço" />
                  <div className="ne-f-hint"><span>Até 150 caracteres</span></div>
                </div>
                <div className="ne-f">
                  <label className="ne-f-l" htmlFor="ne-cnpj">CNPJ</label>
                  <input id="ne-cnpj" defaultValue={venda ? (venda.contact_cnpj || "") : ""} placeholder="00.000.000/0000-00" inputMode="numeric" />
                </div>
                <div className="ne-f">
                  <label className="ne-f-l" htmlFor="ne-cpf">CPF</label>
                  <input id="ne-cpf" defaultValue={venda ? (venda.contact_cpf || "") : ""} placeholder="000.000.000-00" inputMode="numeric" />
                </div>
                <div className="ne-f">
                  <label className="ne-f-l" htmlFor="ne-email">E-mail</label>
                  <input id="ne-email" type="email" defaultValue={venda ? (venda.contact_email || "") : ""} placeholder="para enviar a nota" />
                </div>
              </div>
              <div className="ne-f-hint" style={{ marginTop: 10 }}>
                <span>Informe CNPJ ou CPF conforme o tomador. Os dois campos são opcionais no envio.</span>
              </div>
            </div>
          </div>

          {/* ── Serviço ── */}
          <div className="ne-card">
            <div className="ne-card-h"><span className="ne-card-t"><NeI.doc s={14} /> Serviço prestado</span></div>
            <div className="ne-card-b">
              <div className="ne-fs">
                <div className="ne-f">
                  <label className="ne-f-l" htmlFor="ne-comp">Competência <span className="ne-f-req">*</span></label>
                  <input id="ne-comp" type="month" defaultValue={venda ? venda.transaction_date : ""} />
                  <div className="ne-f-hint"><span>Mês de referência (AAAA-MM)</span></div>
                </div>
                <div className="ne-f">
                  <label className="ne-f-l" htmlFor="ne-lc116">Código LC 116 <span className="ne-f-req">*</span></label>
                  <input id="ne-lc116" defaultValue={CONFIG.lc116_codigo_default} maxLength={5} placeholder="14.01" />
                  <div className="ne-f-hint"><span>Lista de serviços da LC 116/2003</span></div>
                </div>
                <div className="ne-f wide">
                  <label className="ne-f-l" htmlFor="ne-desc">Discriminação do serviço <span className="ne-f-req">*</span></label>
                  <textarea id="ne-desc" maxLength={2000} value={desc} onChange={(e) => setDesc(e.target.value)}
                    placeholder="O que foi prestado. Esse texto vai impresso no corpo da nota." />
                  <div className="ne-f-hint">
                    <span>Vai impresso na nota</span>
                    <span>{desc.length} / 2000</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* ── Valores ── */}
          <div className="ne-card">
            <div className="ne-card-h"><span className="ne-card-t">Valores e ISS</span></div>
            <div className="ne-card-b">
              <div className="ne-fs">
                <div className="ne-f">
                  <label className="ne-f-l" htmlFor="ne-valor">Valor dos serviços <span className="ne-f-req">*</span></label>
                  <div className="ne-prefix">
                    <span>R$</span>
                    <input id="ne-valor" inputMode="decimal" value={String(valor).replace(".", ",")}
                      onChange={(e) => setValor(Number(e.target.value.replace(/\./g, "").replace(",", ".")) || 0)} />
                  </div>
                  <div className="ne-f-hint"><span>Mínimo R$ 0,01</span></div>
                </div>
                <div className="ne-f">
                  <label className="ne-f-l" htmlFor="ne-aliq">Alíquota do ISS <span className="ne-f-req">*</span></label>
                  <div className="ne-suffix">
                    <input id="ne-aliq" inputMode="decimal" value={(aliq * 100).toFixed(2).replace(".", ",")}
                      onChange={(e) => setAliq((Number(e.target.value.replace(",", ".")) || 0) / 100)} />
                    <span>%</span>
                  </div>
                  <div className="ne-f-hint"><span>Padrão do município: {pct(CONFIG.aliquota_iss)}</span></div>
                </div>
                <div className="ne-f wide">
                  <label className="ne-check" htmlFor="ne-retido">
                    <input id="ne-retido" type="checkbox" checked={retido} onChange={(e) => setRetido(e.target.checked)} />
                    <span>
                      <span className="ne-check-t">ISS retido pelo tomador</span>
                      <span className="ne-check-d">
                        Quando o tomador é o responsável por recolher o imposto. O valor do ISS é descontado do que você recebe.
                      </span>
                    </span>
                  </label>
                </div>
              </div>
            </div>

            {/* Prévia do cálculo — o valor oficial é o que a prefeitura apura no retorno do RPS */}
            <div className="ne-calc">
              <div className="ne-calc-r"><span>Valor dos serviços</span><span className="ne-calc-v">{brl(Number(valor || 0))}</span></div>
              <div className="ne-calc-r"><span>ISS ({pct(aliq)})</span><span className="ne-calc-v">{brl(iss)}</span></div>
              <div className="ne-calc-r total">
                <span>{retido ? "Líquido a receber (ISS retido)" : "Total da nota"}</span>
                <span className="ne-calc-v">{brl(liquido)}</span>
              </div>
              <div className="ne-calc-nota">
                Prévia. O valor oficial do ISS é o que a prefeitura apura no retorno do RPS.
              </div>
            </div>
          </div>

          <div className="ne-foot">
            <span className="spacer ne-foot-nota">A emissão é assíncrona — a nota aparece na listagem com o status do processamento.</span>
            <button className="ne-btn"><NeI.back s={13} /> Cancelar</button>
            <button className="ne-btn primary" disabled={!certOk}><NeI.send s={13} /> Emitir NFS-e</button>
          </div>
        </div>
      </div>
    </div>
  );
}

window.NfseEmitirPage = NfseEmitirPage;
})();
