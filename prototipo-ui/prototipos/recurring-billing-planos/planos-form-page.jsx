// planos-form-page.jsx — Plano de assinatura: Criar (PT-02) + Editar (PT-02). Cockpit V2.
// GERADO pelo designer-agente (ADR 0282 §0.1) ancorado no DS canon + no dominio REAL:
//   schema     Modules/RecurringBilling/Database/Migrations/2026_05_06_001000_create_rb_plans_table.php
//   regras     Modules/RecurringBilling/Http/Requests/StorePlanRequest.php (rules + messages)
//   props      Modules/RecurringBilling/Http/Controllers/PlanController.php (create :65, edit :115)
//   forma      Planos/Create.charter.md §Goals · §Non-Goals · §UX Anti-patterns
// NAO derivado do .tsx vivo (porte reverso e proibido — §5 2026-06-05 / 2026-08-28).
// ⚠️ O charter PROIBE validacao client-side custom ("duplicaria FormRequest — server fala a
//    palavra final"). Entao aqui NAO ha regra propria: os erros desenhados sao os que voltam
//    do FormRequest, e o unico gate no cliente e `required` do HTML.
// Token-driven (claro/escuro pelo host). Expoe window.PlanosFormPage.
(() => {
const { useState, useMemo } = React;

const Ic = ({ d, size = 14 }) => (
  <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
       strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">{d}</svg>
);
const PlI = { back: <Ic size={12} d={<><path d="m15 18-6-6 6-6"/></>} /> };

// enum do banco — os 5 valores da coluna `ciclo`
const CICLOS = {
  monthly:    { rotulo: 'Mensal',      dias: 30 },
  quarterly:  { rotulo: 'Trimestral',  dias: 90 },
  semiannual: { rotulo: 'Semestral',   dias: 180 },
  yearly:     { rotulo: 'Anual',       dias: 365 },
  custom:     { rotulo: 'Personalizado', dias: null },
};
// Rule::in do FormRequest
const FISCAL = { none: 'Sem documento fiscal', nfe: 'NF-e (produto)', nfse: 'NFS-e (servico)' };

// defaults que o create() manda
const DEFAULTS = { ciclo: 'monthly', trial_days: 0, ativo: true, fiscal_type: 'none' };

// um plano existente, no shape do edit()
const PLANO = {
  id: 14, name: 'Manutencao mensal — plano Prata', slug: 'manutencao-mensal-prata',
  descricao_curta: 'Visita tecnica mensal + suporte por WhatsApp',
  description: 'Inclui uma visita tecnica por mes, atendimento prioritario e reposicao de pecas de desgaste.',
  valor: 349.9, ciclo: 'monthly', ciclo_dias: null, trial_days: 7, ativo: true,
  fiscal_type: 'nfse', fiscal_cfop: '', fiscal_servico: '14.01',
};

const brl = (v) => 'R$ ' + (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// slug derivado do nome quando o campo fica vazio (charter §Goals)
const slugify = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
  .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);

function Campo({ label, obrigatorio, hint, erro, full, children }) {
  return (
    <div className={'pl-f' + (full ? ' pl-f-full' : '')}>
      <label className="pl-lb">{label}{obrigatorio && <span className="pl-req">*</span>}</label>
      {children}
      {erro ? <span className="pl-err" role="alert">{erro}</span>
            : hint ? <span className="pl-hint">{hint}</span> : null}
    </div>
  );
}

function Formulario({ modo }) {
  const criando = modo === 'criar';
  const base = criando
    ? { name: '', slug: '', descricao_curta: '', description: '', valor: '',
        ciclo: DEFAULTS.ciclo, ciclo_dias: '', trial_days: DEFAULTS.trial_days,
        ativo: DEFAULTS.ativo, fiscal_type: DEFAULTS.fiscal_type, fiscal_cfop: '', fiscal_servico: '' }
    // a coluna e decimal(15,2): o campo nasce com 2 casas, sempre. Mostrar "349,9"
    // num campo de dinheiro e o descuido que convida truncamento (§5 2026-06-05).
    : { ...PLANO, valor: PLANO.valor.toFixed(2).replace('.', ',') };

  const [f, setF] = useState(base);
  // erros VINDOS DO SERVIDOR (422) — o prototipo mostra o estado, nao inventa a regra
  const [erros, setErros] = useState({});
  const set = (k) => (e) => setF({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

  const slugEfetivo = f.slug || slugify(f.name);
  const dias = f.ciclo === 'custom' ? (Number(f.ciclo_dias) || null) : CICLOS[f.ciclo].dias;
  const valorNum = Number(String(f.valor).replace(/\./g, '').replace(',', '.')) || 0;

  // simula a volta 422 do FormRequest — as mensagens sao as do StorePlanRequest
  const simularErro = () => setErros({
    name: 'Nome do plano é obrigatório.',
    valor: 'O campo valor deve ser um número maior ou igual a 0.',
    ciclo_dias: 'O campo ciclo dias é obrigatório quando ciclo é custom.',
  });

  return (
    <React.Fragment>
      <div className="pl-head">
        <button className="pl-crumb">{PlI.back}Voltar para planos</button>
        <h1 className="pl-h1">{criando ? 'Novo plano · cobrança recorrente' : 'Editar plano'}</h1>
        <div className="pl-sub">
          {criando ? 'O plano define quanto e de quanto em quanto tempo o cliente é cobrado.'
                   : PLANO.name + ' · ' + PLANO.slug}
        </div>
      </div>

      {Object.keys(erros).length > 0 && (
        <div className="pl-alert" role="alert">
          <b>Não foi possível salvar.</b> Confira os campos marcados abaixo — as mensagens vêm da
          validação do servidor.
        </div>
      )}

      <div className="pl-grid">
        <div>
          <div className="pl-card">
            <div className="pl-card-h">
              <div className="pl-card-t">Identificação</div>
              <div className="pl-card-s">Como o plano aparece para você e para o cliente.</div>
            </div>
            <div className="pl-card-b">
              <div className="pl-fg">
                <Campo label="Nome do plano" obrigatorio erro={erros.name} full>
                  <input className="pl-in" required value={f.name} onChange={set('name')}
                         aria-invalid={!!erros.name} maxLength={150}
                         placeholder="Manutencao mensal — plano Prata" />
                </Campo>
                <Campo label="Identificador (slug)" erro={erros.slug}
                       hint={f.slug ? 'Único dentro da sua empresa.' : 'Deixe vazio para gerar do nome: ' + (slugEfetivo || '—')}>
                  <input className="pl-in" value={f.slug} onChange={set('slug')} maxLength={80}
                         placeholder={slugEfetivo || 'gerado-do-nome'} />
                </Campo>
                <Campo label="Descrição curta" hint="Aparece na lista e na fatura. Até 200 caracteres.">
                  <input className="pl-in" value={f.descricao_curta} onChange={set('descricao_curta')} maxLength={200} />
                </Campo>
                <Campo label="Descrição" full hint="O que está incluso. Até 2.000 caracteres.">
                  <textarea className="pl-in" value={f.description} onChange={set('description')} maxLength={2000} />
                </Campo>
              </div>
            </div>
          </div>

          <div className="pl-card">
            <div className="pl-card-h">
              <div className="pl-card-t">Cobrança</div>
              <div className="pl-card-s">Valor e periodicidade — é isto que gera a fatura.</div>
            </div>
            <div className="pl-card-b">
              <div className="pl-fg">
                <Campo label="Valor por ciclo" obrigatorio erro={erros.valor}
                       hint="Duas casas decimais, vírgula como separador.">
                  <input className="pl-in pl-money" required inputMode="decimal" value={f.valor}
                         onChange={set('valor')} aria-invalid={!!erros.valor} placeholder="0,00" />
                </Campo>
                <Campo label="Ciclo" obrigatorio>
                  <select className="pl-in" value={f.ciclo} onChange={set('ciclo')} required>
                    {Object.entries(CICLOS).map(([k, v]) => <option value={k} key={k}>{v.rotulo}</option>)}
                  </select>
                </Campo>
                <Campo label="Dias de teste" hint="0 a 90. O cliente só é cobrado depois desse prazo.">
                  <input className="pl-in" type="number" min={0} max={90} value={f.trial_days} onChange={set('trial_days')} />
                </Campo>
                <div className="pl-f">
                  <label className="pl-lb">Situação</label>
                  <label className="pl-check">
                    <input type="checkbox" checked={!!f.ativo} onChange={set('ativo')} />
                    <span>Plano ativo (pode receber novas assinaturas)</span>
                  </label>
                </div>
              </div>

              {f.ciclo === 'custom' && (
                <div className="pl-cond">
                  <div className="pl-cond-t">Ciclo personalizado</div>
                  <div className="pl-fg">
                    <Campo label="A cada quantos dias" obrigatorio erro={erros.ciclo_dias}
                           hint="De 1 a 365 dias.">
                      <input className="pl-in" type="number" min={1} max={365} value={f.ciclo_dias}
                             onChange={set('ciclo_dias')} aria-invalid={!!erros.ciclo_dias} />
                    </Campo>
                  </div>
                </div>
              )}
            </div>
          </div>

          <div className="pl-card">
            <div className="pl-card-h">
              <div className="pl-card-t">Documento fiscal</div>
              <div className="pl-card-s">O que emitir quando a fatura deste plano for paga.</div>
            </div>
            <div className="pl-card-b">
              <div className="pl-fg">
                <Campo label="Tipo" full>
                  <select className="pl-in" value={f.fiscal_type} onChange={set('fiscal_type')}>
                    {Object.entries(FISCAL).map(([k, v]) => <option value={k} key={k}>{v}</option>)}
                  </select>
                </Campo>
              </div>
              {f.fiscal_type === 'nfe' && (
                <div className="pl-cond">
                  <div className="pl-cond-t">Dados da NF-e</div>
                  <div className="pl-fg">
                    <Campo label="CFOP" obrigatorio erro={erros.fiscal_cfop} hint="Até 8 caracteres.">
                      <input className="pl-in" value={f.fiscal_cfop} onChange={set('fiscal_cfop')} maxLength={8} required />
                    </Campo>
                  </div>
                </div>
              )}
              {f.fiscal_type === 'nfse' && (
                <div className="pl-cond">
                  <div className="pl-cond-t">Dados da NFS-e</div>
                  <div className="pl-fg">
                    <Campo label="Código do serviço" obrigatorio erro={erros.fiscal_servico} hint="Até 8 caracteres.">
                      <input className="pl-in" value={f.fiscal_servico} onChange={set('fiscal_servico')} maxLength={8} required />
                    </Campo>
                  </div>
                </div>
              )}
            </div>
            <div className="pl-acts">
              <button className="pl-btn" onClick={simularErro}>Ver estado com erro do servidor</button>
              <button className="pl-btn">Cancelar</button>
              <button className="pl-btn pl-btn-p">{criando ? 'Criar plano' : 'Salvar alterações'}</button>
            </div>
          </div>
        </div>

        <aside>
          <div className="pl-card">
            <div className="pl-card-h"><div className="pl-card-t">Como o cliente vê</div></div>
            <div className="pl-card-b">
              <div className="pl-preco">{brl(valorNum)}</div>
              <div className="pl-preco-s">
                {f.ciclo === 'custom'
                  ? (dias ? 'a cada ' + dias + ' dias' : 'defina o intervalo em dias')
                  : 'por ' + CICLOS[f.ciclo].rotulo.toLowerCase().replace('mensal', 'mês')
                      .replace('trimestral', 'trimestre').replace('semestral', 'semestre').replace('anual', 'ano')}
              </div>
              <dl style={{ margin: '12px 0 0' }}>
                <div className="pl-kv"><dt>Identificador</dt><dd>{slugEfetivo || '—'}</dd></div>
                <div className="pl-kv"><dt>Teste grátis</dt>
                  <dd>{Number(f.trial_days) > 0 ? f.trial_days + ' dias' : 'sem teste'}</dd></div>
                <div className="pl-kv"><dt>Fiscal</dt><dd>{FISCAL[f.fiscal_type]}</dd></div>
                <div className="pl-kv pl-kv-forte"><dt>Situação</dt>
                  <dd>{f.ativo ? 'Ativo' : 'Inativo'}</dd></div>
              </dl>
              {/* equivalente mensal so quando ACRESCENTA informacao — num plano de 30 dias
                  ele repetiria o proprio preco. NAO existe no backend: ver SOURCE.md §6. */}
              {dias && dias !== 30 && valorNum > 0 && (
                <dl style={{ margin: '10px 0 0' }}>
                  <div className="pl-kv"><dt>Equivale a</dt>
                    <dd>{brl(valorNum / (dias / 30))} / mês</dd></div>
                </dl>
              )}
            </div>
          </div>
        </aside>
      </div>
    </React.Fragment>
  );
}

function PlanosFormPage() {
  const [modo, setModo] = useState('criar');
  return (
    <div className="pl">
      <nav className="pl-nav" aria-label="Telas do prototipo">
        <button aria-current={modo === 'criar'} onClick={() => setModo('criar')}>Criar</button>
        <button aria-current={modo === 'editar'} onClick={() => setModo('editar')}>Editar</button>
      </nav>
      <Formulario modo={modo} key={modo} />
    </div>
  );
}

window.PlanosFormPage = PlanosFormPage;
})();
