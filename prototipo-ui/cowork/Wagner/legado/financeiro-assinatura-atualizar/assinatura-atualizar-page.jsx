// assinatura-atualizar-page.jsx — Atualizar cobranca de assinatura (FIN-004). Cockpit V2.
// GERADO pelo designer-agente (ADR 0282 §0.1) ancorado no DS canon + no dominio REAL:
//   props    Modules/Financeiro/Http/Controllers/AssinaturaController::showAtualizar (:41)
//   regras   Modules/RecurringBilling/Http/Requests/UpdateAssinaturaRequest (:32-43)
//   forma    Financeiro/AssinaturaAtualizar.charter.md §Goals · §Anti-hooks
// NAO derivado do .tsx vivo (porte reverso e proibido — §5 2026-06-05 / 2026-08-28).
//
// O charter tem DOIS anti-hooks que mandam no desenho, e os dois estao aplicados:
//   1. "nunca disparar PATCH automatico por mudanca de campo" — nada aqui salva sozinho.
//   2. "Controller/log NUNCA imprime valor real (biz=4 prod)" — este prototipo nao loga nada.
// E o Goal que define a tela: PREVIEW DE IMPACTO (de -> para) antes de confirmar, com o
// botao travado enquanto nao houver diff real ("sem patch cego").
// Token-driven (claro/escuro pelo host). Expoe window.AssinaturaAtualizarPage.
(() => {
const { useState, useMemo } = React;

// Rule::in do UpdateAssinaturaRequest — em PORTUGUES nesta rota
// (o enum de rb_plans usa monthly/quarterly/...; ver SOURCE.md §6).
const CICLOS = { mensal: 'Mensal', trimestral: 'Trimestral', semestral: 'Semestral', anual: 'Anual' };
const FORMAS = { boleto: 'Boleto', pix: 'Pix', cartao: 'Cartao' };

// shape exato que o showAtualizar() manda
const ASSINATURAS = [
  { id: 3120, plano: 'Manutencao mensal — plano Prata', status: 'active',
    next_due_date: '2026-09-20', valor_atual: 349.9, ciclo_atual: 'mensal', forma_pagamento_atual: 'boleto' },
  { id: 3118, plano: 'Suporte anual — plano Ouro', status: 'active',
    next_due_date: '2026-11-02', valor_atual: 3600.0, ciclo_atual: 'anual', forma_pagamento_atual: 'pix' },
  { id: 3101, plano: 'Locacao de equipamento', status: 'past_due',
    next_due_date: '2026-09-01', valor_atual: 780.0, ciclo_atual: 'trimestral', forma_pagamento_atual: 'cartao' },
];

const STATUS = { active: 'Ativa', past_due: 'Em atraso' };

const brl = (v) => 'R$ ' + (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dataBR = (iso) => { if (!iso) return '—'; const [a, m, d] = iso.split('-'); return d + '/' + m + '/' + a; };
const paraNumero = (s) => { const l = String(s).replace(/\./g, '').replace(',', '.'); return l === '' ? NaN : Number(l); };

function AssinaturaAtualizarPage() {
  const [selId, setSelId] = useState(null);
  const [f, setF] = useState(null);

  const sel = ASSINATURAS.find(a => a.id === selId) || null;

  const escolher = (a) => {
    setSelId(a.id);
    setF({ valor: a.valor_atual.toFixed(2).replace('.', ','),
           ciclo: a.ciclo_atual, forma_pagamento: a.forma_pagamento_atual });
  };

  const valorNum = f ? paraNumero(f.valor) : NaN;
  // min:0.01 do UpdateAssinaturaRequest
  const erroValor = f == null ? null
    : Number.isNaN(valorNum) ? 'Informe um valor.'
    : valorNum < 0.01 ? 'Valor deve ser maior que zero.' : null;

  // payload PARCIAL: so o que mudou (o Request e todo `sometimes`)
  const diff = useMemo(() => {
    if (!sel || !f) return [];
    const linhas = [];
    if (!erroValor && Math.abs(valorNum - sel.valor_atual) > 0.000001)
      linhas.push({ campo: 'Valor por ciclo', de: brl(sel.valor_atual), para: brl(valorNum) });
    if (f.ciclo !== sel.ciclo_atual)
      linhas.push({ campo: 'Ciclo', de: CICLOS[sel.ciclo_atual], para: CICLOS[f.ciclo] });
    if (f.forma_pagamento !== sel.forma_pagamento_atual)
      linhas.push({ campo: 'Forma de pagamento', de: FORMAS[sel.forma_pagamento_atual], para: FORMAS[f.forma_pagamento] });
    return linhas;
  }, [sel, f, valorNum, erroValor]);

  const podeSalvar = diff.length > 0 && !erroValor;
  const mudou = (k) => {
    if (!sel || !f) return false;
    if (k === 'valor') return !erroValor && Math.abs(valorNum - sel.valor_atual) > 0.000001;
    if (k === 'ciclo') return f.ciclo !== sel.ciclo_atual;
    return f.forma_pagamento !== sel.forma_pagamento_atual;
  };

  return (
    <div className="as">
      <div className="as-head">
        <h1 className="as-h1">Atualizar cobrança</h1>
        <div className="as-sub">
          Altere valor, ciclo ou forma de pagamento de uma assinatura ativa.
          Você confere o impacto campo a campo antes de confirmar — nada é salvo automaticamente.
        </div>
      </div>

      <div className="as-body">
        <div className="as-card">
          <div className="as-card-h">
            <div className="as-card-t">Assinaturas ativas</div>
            <div className="as-card-s">Escolha uma para editar. Mostrando as {ASSINATURAS.length} mais recentes.</div>
          </div>
          <div className="as-wrap">
            <table className="as-tb">
              <thead><tr>
                <th>Plano</th><th>Situação</th><th className="as-num">Valor atual</th>
                <th>Ciclo</th><th>Forma</th><th>Próximo vencimento</th>
              </tr></thead>
              <tbody>
                {ASSINATURAS.map(a => (
                  <tr key={a.id} data-sel={a.id === selId} onClick={() => escolher(a)}
                      tabIndex={0} onKeyDown={(e) => { if (e.key === 'Enter') escolher(a); }}
                      aria-label={'Editar cobranca de ' + a.plano}>
                    <td className="as-plano">{a.plano}</td>
                    <td><span className={'as-chip' + (a.status === 'active' ? ' as-chip-on' : '')}>{STATUS[a.status]}</span></td>
                    <td className="as-num">{brl(a.valor_atual)}</td>
                    <td>{CICLOS[a.ciclo_atual]}</td>
                    <td>{FORMAS[a.forma_pagamento_atual]}</td>
                    <td>{dataBR(a.next_due_date)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* charter: "form so aparece apos selecao (foco/contexto)" */}
        {!sel ? (
          <div className="as-card">
            <div className="as-vazio">
              <div className="as-vazio-t">Escolha uma assinatura acima</div>
              <div className="as-vazio-s">
                O formulário aparece aqui depois que você selecionar — assim não há edição
                fora de contexto.
              </div>
            </div>
          </div>
        ) : (
          <div className="as-card">
            <div className="as-card-h">
              <div className="as-card-t">{sel.plano}</div>
              <div className="as-card-s">
                Vence em {dataBR(sel.next_due_date)} · hoje cobra {brl(sel.valor_atual)} por {CICLOS[sel.ciclo_atual].toLowerCase()}
              </div>
            </div>
            <div className="as-card-b">
              <div className="as-fg">
                <div className="as-f">
                  <label className="as-lb">Valor por ciclo</label>
                  <input className="as-in as-money" inputMode="decimal" value={f.valor}
                         data-mudou={mudou('valor')} aria-invalid={!!erroValor}
                         onChange={(e) => setF({ ...f, valor: e.target.value })} />
                  {erroValor ? <span className="as-err" role="alert">{erroValor}</span>
                             : <span className="as-hint">Duas casas, vírgula como separador.</span>}
                </div>
                <div className="as-f">
                  <label className="as-lb">Ciclo</label>
                  <select className="as-in" value={f.ciclo} data-mudou={mudou('ciclo')}
                          onChange={(e) => setF({ ...f, ciclo: e.target.value })}>
                    {Object.entries(CICLOS).map(([k, v]) => <option value={k} key={k}>{v}</option>)}
                  </select>
                </div>
                <div className="as-f">
                  <label className="as-lb">Forma de pagamento</label>
                  <select className="as-in" value={f.forma_pagamento} data-mudou={mudou('forma_pagamento')}
                          onChange={(e) => setF({ ...f, forma_pagamento: e.target.value })}>
                    {Object.entries(FORMAS).map(([k, v]) => <option value={k} key={k}>{v}</option>)}
                  </select>
                </div>
              </div>

              {/* o Goal central: preview de impacto antes de confirmar */}
              {diff.length > 0 ? (
                <div className="as-diff">
                  <div className="as-diff-h">O que muda nesta assinatura</div>
                  <div className="as-diff-b">
                    {diff.map((d, i) => (
                      <div className="as-diff-l" key={i}>
                        <span className="as-diff-k">{d.campo}</span>
                        <span className="as-de">{d.de}</span>
                        <span className="as-seta">→</span>
                        <span className="as-para">{d.para}</span>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="as-nodiff">
                  Nada mudou ainda. Altere um campo para ver o impacto — e só então
                  o botão de confirmar libera.
                </div>
              )}
            </div>
            <div className="as-acts">
              <button className="as-btn" onClick={() => { setSelId(null); setF(null); }}>Cancelar</button>
              <button className="as-btn as-btn-p" disabled={!podeSalvar}>
                {diff.length === 0 ? 'Confirmar'
                  : diff.length === 1 ? 'Confirmar 1 alteração'
                  : 'Confirmar ' + diff.length + ' alterações'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

window.AssinaturaAtualizarPage = AssinaturaAtualizarPage;
})();
