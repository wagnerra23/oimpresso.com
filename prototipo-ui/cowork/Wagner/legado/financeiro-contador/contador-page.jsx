// contador-page.jsx — Contador parceiro (Financeiro / Configuracoes). Cockpit V2.
// GERADO pelo designer-agente (ADR 0282 §0.1) ancorado no DS canon + no dominio REAL:
//   props    Modules/Financeiro/Http/Controllers/AdvisorAccessController::index (:41)
//   regras   AdvisorAccessController::grant (:86) — cnpj size:14 regex \d{14} · nome 200 · email rfc 191
//   forma    Financeiro/Configuracoes/Contador.charter.md §Goals · §Non-Goals · §Anti-hooks
// NAO derivado do .tsx vivo (porte reverso e proibido — §5 2026-06-05 / 2026-08-28).
//
// TRES anti-hooks do charter mandam no desenho, e os tres estao aplicados:
//   1. "Nao expor CNPJ completo no front — sempre advisor_cnpj_mascarado do backend."
//      -> a lista NUNCA monta CNPJ; usa o campo mascarado que o controller manda.
//   2. "Nao transformar o consent LGPD em opt-out — e checkbox obrigatorio opt-in."
//      -> nasce DESMARCADO e trava o botao ate ser marcado.
//   3. "Acoes destrutivas via AlertDialog controlado (sem window.confirm)."
//      -> revogar abre dialogo proprio.
// Token-driven (claro/escuro pelo host). Expoe window.ContadorPage.
(() => {
const { useState, useMemo } = React;

// shape exato do index(): note que o CNPJ ja chega MASCARADO do backend
const ACESSOS = [
  { id: 9, advisor_nome: 'Contabilidade Sul Assessoria', advisor_email: 'fiscal@exemplo.com.br',
    advisor_cnpj_mascarado: '12.***.***/0001-**', granted_at_label: '14/07/2026',
    can_view_unificado: true, can_view_reports: true, has_consent: true },
  { id: 7, advisor_nome: 'Marina Prado — contadora', advisor_email: 'marina@exemplo.com.br',
    advisor_cnpj_mascarado: '31.***.***/0001-**', granted_at_label: '02/05/2026',
    can_view_unificado: true, can_view_reports: false, has_consent: true },
];

const soDigitos = (s) => String(s).replace(/\D+/g, '');
// espelha o `size:14` + `regex:/^\d{14}$/` do grant()
const cnpjValido = (s) => soDigitos(s).length === 14;
const emailValido = (s) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(String(s).trim());
// mascara so pra DIGITACAO — o que a lista mostra vem mascarado do backend
const mascaraCnpj = (s) => {
  const d = soDigitos(s).slice(0, 14);
  return d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
          .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
};

function ContadorPage() {
  const [acessos, setAcessos] = useState(ACESSOS);
  const [f, setF] = useState({ cnpj: '', nome: '', email: '', telefone: '',
                               unificado: true, relatorios: false, consent: false });
  const [flash, setFlash] = useState(null);
  const [revogando, setRevogando] = useState(null);

  const set = (k) => (e) => setF({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

  const erros = useMemo(() => ({
    cnpj: f.cnpj && !cnpjValido(f.cnpj) ? 'O CNPJ precisa ter 14 dígitos.' : null,
    email: f.email && !emailValido(f.email) ? 'Informe um e-mail válido.' : null,
  }), [f.cnpj, f.email]);

  const escopoOk = f.unificado || f.relatorios;
  const podeConceder = cnpjValido(f.cnpj) && f.nome.trim() !== '' && emailValido(f.email)
                       && escopoOk && f.consent;

  const conceder = () => {
    setAcessos([{ id: Date.now(), advisor_nome: f.nome, advisor_email: f.email,
      advisor_cnpj_mascarado: soDigitos(f.cnpj).replace(/^(\d{2})\d{6}(\d{4})\d{2}$/, '$1.***.***/$2-**'),
      granted_at_label: '09/09/2026', can_view_unificado: f.unificado,
      can_view_reports: f.relatorios, has_consent: true }, ...acessos]);
    setFlash('Acesso concedido a ' + f.nome + '. O contador recebe o convite por e-mail.');
    setF({ cnpj: '', nome: '', email: '', telefone: '', unificado: true, relatorios: false, consent: false });
  };

  const revogar = () => {
    setAcessos(acessos.filter(a => a.id !== revogando.id));
    setFlash('Acesso de ' + revogando.advisor_nome + ' revogado. Ele perde o acesso imediatamente.');
    setRevogando(null);
  };

  return (
    <div className="ct">
      <div className="ct-head">
        <div className="ct-crumb">Financeiro · Configurações · <b>Contador</b></div>
        <h1 className="ct-h1">Contador parceiro</h1>
        <div className="ct-sub">
          Dê ao seu contador acesso <b>somente leitura</b> ao Financeiro, num portal próprio dele —
          sem compartilhar sua senha. Você revoga quando quiser.
        </div>
      </div>

      <div className="ct-body">
        {flash && <div className="ct-alert" role="status">{flash}</div>}

        <div className="ct-card">
          <div className="ct-card-h">
            <div className="ct-card-t">Acessos ativos</div>
            <div className="ct-card-s">Quem hoje consegue abrir o seu Financeiro em modo leitura.</div>
          </div>
          {acessos.length === 0 ? (
            <div className="ct-vazio">
              <div className="ct-vazio-t">Nenhum contador com acesso</div>
              <div className="ct-vazio-s">
                Conceda abaixo informando CNPJ, nome e e-mail. Nada é compartilhado antes disso.
              </div>
            </div>
          ) : (
            <div className="ct-wrap">
              <table className="ct-tb">
                <thead><tr>
                  <th>Contador</th><th>CNPJ</th><th>Pode ver</th><th>Desde</th><th></th>
                </tr></thead>
                <tbody>
                  {acessos.map(a => (
                    <tr key={a.id}>
                      <td>
                        <div className="ct-nome">{a.advisor_nome}</div>
                        <div style={{ fontSize: '11px', color: 'var(--fg-3)' }}>{a.advisor_email}</div>
                      </td>
                      <td className="ct-mono">{a.advisor_cnpj_mascarado}</td>
                      <td>
                        {a.can_view_unificado && <span className="ct-chip ct-chip-on">Visão unificada</span>}
                        {a.can_view_reports && <span className="ct-chip ct-chip-on">Relatórios</span>}
                        {a.has_consent && <span className="ct-chip">Consentimento</span>}
                      </td>
                      <td>{a.granted_at_label}</td>
                      <td style={{ textAlign: 'right' }}>
                        <button className="ct-btn" onClick={() => setRevogando(a)}
                                aria-label={'Revogar acesso de ' + a.advisor_nome}>Revogar</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="ct-card">
          <div className="ct-card-h">
            <div className="ct-card-t">Conceder acesso</div>
            <div className="ct-card-s">O contador é identificado por CNPJ e e-mail.</div>
          </div>
          <div className="ct-card-b">
            <div className="ct-fg">
              <div className="ct-f">
                <label className="ct-lb">CNPJ do contador<span className="ct-req">*</span></label>
                <input className="ct-in ct-mono" value={mascaraCnpj(f.cnpj)}
                       onChange={(e) => setF({ ...f, cnpj: e.target.value })}
                       aria-invalid={!!erros.cnpj} placeholder="00.000.000/0000-00" inputMode="numeric" />
                {erros.cnpj ? <span className="ct-err" role="alert">{erros.cnpj}</span>
                            : <span className="ct-hint">Só os 14 dígitos são enviados.</span>}
              </div>
              <div className="ct-f">
                <label className="ct-lb">Nome ou razão social<span className="ct-req">*</span></label>
                <input className="ct-in" value={f.nome} onChange={set('nome')} maxLength={200} />
              </div>
              <div className="ct-f">
                <label className="ct-lb">E-mail<span className="ct-req">*</span></label>
                <input className="ct-in" type="email" value={f.email} onChange={set('email')}
                       maxLength={191} aria-invalid={!!erros.email} />
                {erros.email ? <span className="ct-err" role="alert">{erros.email}</span>
                             : <span className="ct-hint">É por aqui que o convite chega.</span>}
              </div>
              <div className="ct-f">
                <label className="ct-lb">Telefone</label>
                <input className="ct-in" value={f.telefone} onChange={set('telefone')} maxLength={20} />
                <span className="ct-hint">Opcional.</span>
              </div>

              <div className="ct-f ct-f-full">
                <label className="ct-lb">O que ele vai poder ver<span className="ct-req">*</span></label>
                <div className="ct-escopo">
                  <label className="ct-check">
                    <input type="checkbox" checked={f.unificado} onChange={set('unificado')} />
                    <span className="ct-check-t">Visão unificada
                      <span className="ct-check-s">Contas a pagar e a receber, em leitura.</span></span>
                  </label>
                  <label className="ct-check">
                    <input type="checkbox" checked={f.relatorios} onChange={set('relatorios')} />
                    <span className="ct-check-t">Relatórios
                      <span className="ct-check-s">DRE e fluxo de caixa, em leitura.</span></span>
                  </label>
                  {!escopoOk && <span className="ct-err" role="alert">Escolha ao menos uma coisa que ele pode ver.</span>}
                </div>
              </div>
            </div>

            <div className="ct-lgpd">
              <div className="ct-lgpd-t">Consentimento (LGPD, Art. 7º, II)</div>
              <label className="ct-check">
                <input type="checkbox" checked={f.consent} onChange={set('consent')} />
                <span className="ct-check-t">
                  Autorizo o compartilhamento dos dados financeiros da minha empresa com este contador,
                  em modo somente leitura.
                  <span className="ct-check-s">
                    Fica registrado quem autorizou e quando. Você pode revogar a qualquer momento,
                    e o acesso cai na hora.
                  </span>
                </span>
              </label>
            </div>
          </div>
          <div className="ct-acts">
            <button className="ct-btn ct-btn-p" disabled={!podeConceder} onClick={conceder}>
              Conceder acesso
            </button>
          </div>
        </div>
      </div>

      {revogando && (
        <React.Fragment>
          <div className="ct-modal-bg" onClick={() => setRevogando(null)} />
          <div className="ct-modal-w" role="dialog" aria-modal="true" aria-label="Confirmar revogação">
            <div className="ct-modal">
              <div className="ct-modal-b">
                <div className="ct-modal-t">Revogar o acesso de {revogando.advisor_nome}?</div>
                <div className="ct-modal-s">
                  Ele perde o acesso ao seu Financeiro imediatamente. A concessão e a revogação
                  ficam registradas. Para devolver o acesso depois, é preciso conceder de novo.
                </div>
              </div>
              <div className="ct-acts">
                <button className="ct-btn" onClick={() => setRevogando(null)}>Manter acesso</button>
                <button className="ct-btn ct-btn-p" onClick={revogar}>Revogar</button>
              </div>
            </div>
          </div>
        </React.Fragment>
      )}
    </div>
  );
}

window.ContadorPage = ContadorPage;
})();
