// Financeiro — Conciliação: painel "Regras e maquininha" (US-FIN-080 + US-FIN-083).
//
// Abre do botão "Regras e maquininha" na TelaConciliacao (ao lado de "Aplicar regras").
// Duas seções num drawer lateral:
//   1. Regras de conciliação — "descrição contém X, entrada/saída, faixa de valor → título do
//      plano de contas / categoria Y" · sugere ou concilia sozinho. Só casa quando sobra UM título
//      em aberto do mesmo valor, até 5 dias da data; o resto fica para revisar.
//   2. Maquininha de cartão — taxa e prazo de débito e crédito, como o repasse aparece no
//      extrato; resumo do que falta chegar e quanto a taxa levou nos últimos 30 dias.
// Na linha do extrato, abaixo do status: "regra: <nome>" ou "repasse do cartão · N venda(s)".
//
// Exporta `window.FinRegrasMaquininha` ({ open, onClose }) e `window.FinConciliacaoAcoes`
// ({ onAplicar, onAbrir }) — a barra com os dois botões. Dados fictícios.
(() => {
const { useState } = React;
const I = window.FIN_I;

const brl = (n) => n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

const REGRAS0 = [
  { id: 1, nome: "Mensalidades", ativo: true, desc: "MENSALIDADE", sentido: "C", min: null, max: null, alvo: "categoria Mensalidades", acao: "conciliar" },
  { id: 2, nome: "Tarifa do banco", ativo: true, desc: "TARIFA", sentido: "D", min: 0, max: 80, alvo: "plano 3.2.04 Tarifas bancárias", acao: "conciliar" },
  { id: 3, nome: "Aluguel", ativo: false, desc: "ALUGUEL", sentido: "D", min: null, max: null, alvo: "plano 3.1.01 Aluguel", acao: "sugerir" },
];

const RECEBIVEIS = { aReceber: 4826.37, vendas: 31, repasses30: 18, taxa30: 312.44 };

const Sentido = ({ s }) => (s === "C" ? "entrada" : s === "D" ? "saída" : "entrada ou saída");

function Campo({ rotulo, children, span }) {
  return (
    <label className={`flex flex-col gap-1 ${span ? "col-span-2" : ""}`}>
      <span className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">{rotulo}</span>
      {children}
    </label>
  );
}

const inp = "h-8 px-2.5 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-3)] text-[var(--text)] placeholder:text-[var(--text-3)] outline-none focus:border-[var(--accent)]";

function FinConciliacaoAcoes({ onAplicar, onAbrir }) {
  return (
    <div className="px-6 mt-4 flex items-center gap-2">
      <button className="fin-sysbtn" onClick={onAplicar} title="Casa o que está pendente pelas regras, pelo repasse do cartão e por valor e data">
        {I.Sparkles ? <I.Sparkles size={13} /> : null} Aplicar regras
      </button>
      <button className="fin-sysbtn fin-sysbtn--ghost" onClick={onAbrir}>
        {I.Settings ? <I.Settings size={13} /> : null} Regras e maquininha
      </button>
      <span className="ml-auto text-[length:var(--fs-2)] text-[var(--text-3)]">3 linhas casadas por regra · 2 pelo repasse do cartão hoje</span>
    </div>
  );
}

function FinRegrasMaquininha({ open, onClose }) {
  const [regras, setRegras] = useState(REGRAS0);
  const [nova, setNova] = useState({ nome: "", desc: "", sentido: "C", min: "", max: "", alvo: "", acao: "sugerir" });
  if (!open) return null;

  const adicionar = () => {
    if (!nova.nome || (!nova.desc && !nova.alvo)) return;
    setRegras((r) => [...r, { ...nova, id: Date.now(), ativo: true, min: nova.min || null, max: nova.max || null, alvo: nova.alvo || "qualquer título" }]);
    setNova({ nome: "", desc: "", sentido: "C", min: "", max: "", alvo: "", acao: "sugerir" });
  };

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <div className="os-drawer" style={{ width: 560 }} onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-label="Regras e maquininha">
        <div className="flex items-start gap-3 px-5 py-4 border-b border-[var(--border)]">
          <div className="flex-1">
            <h2 className="m-0 text-[length:var(--fs-5)] font-semibold">Regras e maquininha</h2>
            <p className="mt-1 text-[length:var(--fs-2)] text-[var(--text-2)]">
              Regras casam o extrato com o título sozinhas. A maquininha transforma venda no cartão em recebível e confere o repasse.
            </p>
          </div>
          <button className="fin-sysbtn fin-sysbtn--ghost" onClick={onClose} aria-label="Fechar">✕</button>
        </div>

        <div className="px-5 py-4 flex flex-col gap-6 overflow-y-auto" style={{ maxHeight: "calc(100vh - 90px)" }}>
          {/* 1. Regras */}
          <section className="flex flex-col gap-3">
            <h3 className="m-0 text-[length:var(--fs-4)] font-semibold">Regras de conciliação</h3>
            <p className="m-0 text-[length:var(--fs-2)] text-[var(--text-2)]">
              Uma regra só casa quando sobra um título em aberto do mesmo valor, até 5 dias da data. O resto fica para você revisar.
            </p>
            <div className="fin-card divide-y divide-[var(--border)]">
              {regras.map((r) => (
                <div key={r.id} className={`flex items-start gap-3 px-3 py-2.5 ${r.ativo ? "" : "opacity-55"}`}>
                  <button
                    role="switch" aria-checked={r.ativo} aria-label={`Regra ${r.nome}`}
                    onClick={() => setRegras((all) => all.map((x) => (x.id === r.id ? { ...x, ativo: !x.ativo } : x)))}
                    className={`mt-0.5 w-8 h-[18px] rounded-full relative ${r.ativo ? "bg-[var(--accent)]" : "bg-[var(--sunken)]"}`}
                  >
                    <span className={`absolute top-[2px] w-[14px] h-[14px] rounded-full bg-[var(--surface)] ${r.ativo ? "left-[16px]" : "left-[2px]"}`} />
                  </button>
                  <div className="flex-1 min-w-0">
                    <div className="font-medium text-[var(--text)]">{r.nome}</div>
                    <div className="text-[length:var(--fs-2)] text-[var(--text-2)]">
                      <Sentido s={r.sentido} />{r.desc ? ` com "${r.desc}"` : ""}
                      {r.max ? ` · ${brl(Number(r.min || 0))} a ${brl(Number(r.max))}` : ""}
                      {" → "}{r.alvo} · {r.acao === "conciliar" ? "concilia sozinho" : "sugere"}
                    </div>
                  </div>
                  <button className="fin-sysbtn fin-sysbtn--ghost" title="Remover regra" onClick={() => setRegras((all) => all.filter((x) => x.id !== r.id))}>
                    {I.X ? <I.X size={12} /> : "✕"}
                  </button>
                </div>
              ))}
            </div>

            <div className="fin-card grid grid-cols-2 gap-3 p-3">
              <Campo rotulo="Nome da regra" span><input className={inp} value={nova.nome} onChange={(e) => setNova({ ...nova, nome: e.target.value })} placeholder="Mensalidades" /></Campo>
              <Campo rotulo="Descrição contém"><input className={inp} value={nova.desc} onChange={(e) => setNova({ ...nova, desc: e.target.value })} placeholder="MENSALIDADE" /></Campo>
              <Campo rotulo="Sentido">
                <select className={inp} value={nova.sentido} onChange={(e) => setNova({ ...nova, sentido: e.target.value })}>
                  <option value="C">Entrada</option><option value="D">Saída</option><option value="">Qualquer</option>
                </select>
              </Campo>
              <Campo rotulo="Valor de (R$)"><input className={inp} inputMode="decimal" value={nova.min} onChange={(e) => setNova({ ...nova, min: e.target.value })} /></Campo>
              <Campo rotulo="Valor até (R$)"><input className={inp} inputMode="decimal" value={nova.max} onChange={(e) => setNova({ ...nova, max: e.target.value })} /></Campo>
              <Campo rotulo="Plano de contas / categoria do título">
                <select className={inp} value={nova.alvo} onChange={(e) => setNova({ ...nova, alvo: e.target.value })}>
                  <option value="">Qualquer</option>
                  <option>categoria Mensalidades</option>
                  <option>plano 3.2.04 Tarifas bancárias</option>
                  <option>plano 3.1.01 Aluguel</option>
                </select>
              </Campo>
              <Campo rotulo="Quando casar">
                <select className={inp} value={nova.acao} onChange={(e) => setNova({ ...nova, acao: e.target.value })}>
                  <option value="sugerir">Sugerir para eu confirmar</option>
                  <option value="conciliar">Conciliar sozinho</option>
                </select>
              </Campo>
              <div className="col-span-2 flex justify-end">
                <button className="fin-sysbtn fin-sysbtn--accent" disabled={!nova.nome} onClick={adicionar}>Adicionar regra</button>
              </div>
            </div>
          </section>

          {/* 2. Maquininha */}
          <section className="flex flex-col gap-3">
            <h3 className="m-0 text-[length:var(--fs-4)] font-semibold">Maquininha de cartão</h3>
            <div className="fin-card flex divide-x divide-[var(--border)] overflow-hidden text-center">
              <div className="flex-1 px-3 py-3">
                <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">A receber</div>
                <div className="mt-1 font-semibold num">{brl(RECEBIVEIS.aReceber)}</div>
                <div className="text-[length:var(--fs-1)] text-[var(--text-3)]">{RECEBIVEIS.vendas} venda(s)</div>
              </div>
              <div className="flex-1 px-3 py-3">
                <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Repasses 30 dias</div>
                <div className="mt-1 font-semibold num">{RECEBIVEIS.repasses30}</div>
              </div>
              <div className="flex-1 px-3 py-3">
                <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Taxa 30 dias</div>
                <div className="mt-1 font-semibold num text-[var(--neg)]">{brl(RECEBIVEIS.taxa30)}</div>
              </div>
            </div>

            <div className="fin-card grid grid-cols-2 gap-3 p-3">
              <Campo rotulo="Adquirente"><input className={inp} defaultValue="Stone" /></Campo>
              <Campo rotulo="Como aparece no extrato"><input className={inp} defaultValue="STONE" /></Campo>
              <Campo rotulo="Taxa débito (%)"><input className={inp} inputMode="decimal" defaultValue="1,99" /></Campo>
              <Campo rotulo="Prazo débito (dias)"><input className={inp} inputMode="numeric" defaultValue="1" /></Campo>
              <Campo rotulo="Taxa crédito (%)"><input className={inp} inputMode="decimal" defaultValue="3,19" /></Campo>
              <Campo rotulo="Prazo crédito (dias)"><input className={inp} inputMode="numeric" defaultValue="30" /></Campo>
              <Campo rotulo="Conta do repasse">
                <select className={inp} defaultValue="Itaú PJ"><option>Qualquer conta</option><option>Itaú PJ</option></select>
              </Campo>
              <div className="flex items-end justify-end">
                <button className="fin-sysbtn fin-sysbtn--accent">Salvar maquininha</button>
              </div>
            </div>
            <p className="m-0 text-[length:var(--fs-2)] text-[var(--text-3)]">
              Venda no cartão vira recebível: crédito 100,00 → taxa 3,19 · líquido 96,81 · cai em 30 dias. O repasse no extrato, no valor de um recebível ou da soma do dia, dá baixa.
            </p>
          </section>
        </div>
      </div>
    </div>
  );
}

window.FinRegrasMaquininha = FinRegrasMaquininha;
window.FinConciliacaoAcoes = FinConciliacaoAcoes;
})();
