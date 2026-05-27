// financeiro-output.jsx — Refino #3 KB-9.75 · Guia + Saída (2026-05-18)
// 5 features espelhando vendas-output.jsx + vendas-curation.jsx:
// - FIN_TROUBLES + FinTroubleButton (reuso KBTroubleshooterDialog)
// - FinChecklistFechamento (12 passos do fechamento mensal · trilha)
// - FinTranscriptPDF (lançamento como folha jurídica imprimível)
// - FinPresentationMode (fullscreen pra reunião com sócio)
// - useFinFavs (favoritos pessoais · atalho B)

(() => {
  const { useState, useEffect, useMemo, useCallback } = React;

  const _fmt = (n) => (n || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const _date = (d) => d ? d.toLocaleDateString("pt-BR") : "—";
  const _dateLong = (d) => d ? d.toLocaleDateString("pt-BR", { day:"2-digit", month:"long", year:"numeric" }) : "—";

  // ═════════════════════════════════════════════════════════════
  // 1) FIN_TROUBLES — árvore de decisão pra divergências comuns
  //    Estrutura compatível com window.KBTroubleshooterDialog
  // ═════════════════════════════════════════════════════════════
  const FIN_TROUBLES = [
    {
      id: "tr-extrato-nao-bate",
      title: "Saldo do extrato não bate com o caixa",
      equip: "conciliação",
      hue: 25,
      when: "fim do dia · diferença entre OFX e contábil",
      steps: [
        { q: "A diferença é menor que R$ 5,00?",
          yes: { fix: "Tolerância normal de arredondamento bancário. Aceite a conciliação automática. Anote a diferença em 'Outros' do plano de contas e siga." },
          no: 1 },
        { q: "Existe boleto pago hoje sem match no Financeiro?",
          yes: { fix: "Provavelmente cliente pagou direto no banco e o sistema não baixou. Vai em Conciliação → linha pendente → 'Confirmar manual' apontando pra venda original. Atualiza paid_at." },
          no: 2 },
        { q: "Houve sangria ou suprimento de caixa hoje?",
          yes: { fix: "Cheque se a sangria foi lançada como saída e o suprimento como entrada. Em Caixa do dia → 'Movimentos'. Se faltar, lance retroativo com motivo." },
          no: 3 },
        { q: "Cliente fez PIX direto pra conta pessoal do Wagner?",
          yes: { fix: "Pessoa-jurídica ≠ pessoa-física. Wagner registra como 'aporte do sócio' e a venda fica em aberto. Pede o PIX correto pra conta da empresa." },
          no: { fix: "Diferença não-trivial. Compare 3 últimos dias contra OFX, baixa de Inter (web), confronta cada linha. Se persistir, abre chamado com a contabilidade." } },
      ],
    },
    {
      id: "tr-boleto-pago-2x",
      title: "Cliente pagou o mesmo boleto 2 vezes",
      equip: "boleto",
      hue: 240,
      when: "cliente entrou em contato reclamando · duplicidade",
      steps: [
        { q: "As duas baixas estão visíveis no extrato Inter?",
          yes: 1,
          no: { fix: "Cliente diz que pagou 2× mas só 1 baixa apareceu — pode ser estorno automático do banco origem. Aguardar 24h. Se confirmar duplicidade no extrato dele (pedir comprovante), aí investiga." } },
        { q: "O cliente quer estorno ou crédito pra próxima compra?",
          yes: { fix: "Crédito: cria lançamento payable manual no Financeiro com a contraparte do cliente, marca 'crédito disponível' e abate na próxima venda. Não emite NF-e nova." },
          no: 2 },
        { q: "Foram menos de 24h da segunda baixa?",
          yes: { fix: "Pede estorno via Inter API (módulo Boleto → boleto → 'Estornar'). SEFAZ não envolve porque a NF-e original está correta. Cliente recebe via PIX em algumas horas." },
          no: { fix: "Após 24h, faz transferência manual TED/PIX devolvendo o valor a mais. Documenta no comentário do lançamento financeiro: 'Devolução por duplicidade · ref. PIX X'." } },
      ],
    },
    {
      id: "tr-fornecedor-cobrou-errado",
      title: "Fornecedor cobrou diferente do pedido",
      equip: "compras",
      hue: 60,
      when: "NF chegou maior que orçado · #PC-NNN",
      steps: [
        { q: "A diferença é menor que 5% do total do pedido?",
          yes: { fix: "Tolerância normal de oscilação de insumo. Aceita, lança no payable normal, anota a diferença. Se for sistemática (mesmo fornecedor 3× seguidas), renegocia." },
          no: 1 },
        { q: "Houve mudança de quantidade ou produto não combinado?",
          yes: { fix: "Recusa a NF (não dá entrada no estoque). Pede ao fornecedor pra emitir NF de DEVOLUÇÃO da diferença ou refazer a nota correta. Não paga até regularizar." },
          no: 2 },
        { q: "Frete ou ICMS-ST veio sem aviso?",
          yes: { fix: "Cheque o pedido de compra original (#PC-NNN). Se frete não foi orçado, é discussão. Se foi 'FOB' (por conta do destinatário), você paga separado. ICMS-ST geralmente é repassado e ok." },
          no: { fix: "Diferença não justificada. Abre conversa formal com fornecedor (e-mail, não WhatsApp). Suspende próximos pedidos até esclarecer. Se reincidente, troca de fornecedor." } },
      ],
    },
    {
      id: "tr-nfe-rejeitada-fin",
      title: "Rejeição da SEFAZ chegou no Financeiro",
      equip: "fiscal",
      hue: 25,
      when: "venda já pendurada · #V- + rejeição",
      steps: [
        { q: "Já abriu o drawer da venda original pra ver o motivo?",
          yes: 1,
          no: { fix: "Vai primeiro no drawer da venda (#V-NNNN no campo desc). Veja a aba Fiscal · status NF-e. Lá tem o código de rejeição SEFAZ específico." } },
        { q: "É código 539 (duplicidade) ou 692 (IE inválida)?",
          yes: { fix: "Esses são os 2 mais comuns. Há troubleshooter próprio no Vendas: drawer da venda → footer → '? Resolver: NF-e rejeitada'. Ele te leva pelo passo-a-passo." },
          no: 2 },
        { q: "O cliente já pagou antes da rejeição?",
          yes: { fix: "Cliente recebe o produto/serviço, você recebe o dinheiro, mas a NF não está autorizada. Tem 24h pra inutilizar o número rejeitado e emitir o próximo. Avise a Eliana pra não conciliar até resolver." },
          no: { fix: "Sem pagamento ainda, sem pressa. Vendedor resolve a rejeição na venda original e re-emite. Financeiro continua aguardando paid_at." } },
      ],
    },
  ];
  window.FIN_TROUBLES = FIN_TROUBLES;

  function FinTroubleButton({ row }) {
    const [open, setOpen] = useState(false);
    // Sugere o trouble certo baseado no estado
    const suggested = useMemo(() => {
      if (!row) return null;
      const desc = (row.desc || "").toLowerCase();
      const channel = (row.channel || "").toLowerCase();
      if (channel.includes("boleto") && !row.paid_at) return "tr-boleto-pago-2x";
      if (row.kind === "payable" && desc.includes("#pc-")) return "tr-fornecedor-cobrou-errado";
      if (desc.includes("#v-") && row.status === "atrasado") return "tr-nfe-rejeitada-fin";
      return "tr-extrato-nao-bate";
    }, [row]);
    const sg = suggested ? FIN_TROUBLES.find(t => t.id === suggested) : null;

    return (
      <>
        <button className="fin-trouble-btn" onClick={() => setOpen(true)}>
          <span className="fin-trouble-ic">?</span>
          <span className="fin-trouble-lbl">
            {sg ? <>Resolver: <b>{sg.title}</b></> : "Guia de divergências"}
          </span>
          <span className="fin-trouble-ct">{FIN_TROUBLES.length} fluxos</span>
        </button>
        {open && window.KBTroubleshooterDialog && (
          <window.KBTroubleshooterDialog
            customTroubles={FIN_TROUBLES}
            onClose={() => setOpen(false)}
            onPickArticle={() => {}}
            onCreateNew={() => {}}
          />
        )}
      </>
    );
  }
  window.FinTroubleButton = FinTroubleButton;

  // ═════════════════════════════════════════════════════════════
  // 2) FECHAMENTO MENSAL · 12 passos (trilha)
  // ═════════════════════════════════════════════════════════════
  const FECHAMENTO_STEPS = [
    { id: 1, title: "Conciliar extrato Inter",
      hint: "Importar OFX do mês · Conciliação automática · revisar pendências",
      action: "Abrir Conciliação", actionRoute: "concil" },
    { id: 2, title: "Marcar todos lançamentos como conferidos",
      hint: "Eliana valida cada receivable + payable do mês (✓ Conferir)",
      action: "Voltar pra Visão unificada", actionRoute: "unified" },
    { id: 3, title: "Liquidar pendentes pagos fora do sistema",
      hint: "PIX direto no app do banco · transferências manuais",
      action: "Filtrar 'Só atrasados'" },
    { id: 4, title: "Verificar boletos emitidos vs cobranças recorrentes",
      hint: "Toda assinatura ativa deve ter boleto do mês emitido",
      action: "Abrir Cobranças recorrentes" },
    { id: 5, title: "Lançar despesas de cartão corporativo",
      hint: "Fatura do cartão Wagner · separar por categoria · classificar plano de contas",
      action: "Novo lançamento manual" },
    { id: 6, title: "Categorizar lançamentos pendentes",
      hint: "Qualquer 'Outros' ou 'Sem categoria' precisa receber plano de contas",
      action: "Abrir Plano de contas", actionRoute: "pcontas" },
    { id: 7, title: "Revisar anomalias detectadas pela IA",
      hint: "Boletos 25%+ acima da média · fornecedores fora do padrão",
      action: "Abrir resumo IA" },
    { id: 8, title: "Conferir folha de pagamento + INSS + FGTS",
      hint: "Lançamento manual com competência + vencimento separados",
      action: "Verificar payables · categoria Folha" },
    { id: 9, title: "Emitir DRE provisório do mês",
      hint: "Receita - Despesa = Resultado. Compara com plano",
      action: "Abrir DRE / Relatórios", actionRoute: "dre" },
    { id: 10, title: "Bater saldo bancário com fechamento contábil",
      hint: "Saldo Inter no último dia útil = soma de receivables liquidados - payables liquidados + saldo abertura",
      action: "Conferir manualmente" },
    { id: 11, title: "Exportar CSV/PDF para contabilidade externa",
      hint: "Contador recebe planilha do mês até dia 5",
      action: "Exportar" },
    { id: 12, title: "Fechar competência no sistema",
      hint: "Bloqueia edição retroativa do mês fechado · só superadmin reabre",
      action: "Fechar mês" },
  ];

  function FinFechamentoTrilha({ open, onClose, onNavigate }) {
    const [done, setDone] = useState(() => {
      try { return new Set(JSON.parse(localStorage.getItem("oimpresso.financeiro.fechamento.done") || "[]")); }
      catch (e) { return new Set(); }
    });
    useEffect(() => {
      try { localStorage.setItem("oimpresso.financeiro.fechamento.done", JSON.stringify([...done])); } catch (e) {}
    }, [done]);
    useEffect(() => {
      if (!open) return;
      const onKey = (e) => { if (e.key === "Escape") onClose(); };
      window.addEventListener("keydown", onKey);
      return () => window.removeEventListener("keydown", onKey);
    }, [open, onClose]);

    const toggle = (id) => setDone(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
    const pct = Math.round((done.size / FECHAMENTO_STEPS.length) * 100);
    const reset = () => {
      if (confirm("Reiniciar checklist? Todos os passos voltam pra 'não feito'.")) setDone(new Set());
    };

    if (!open) return null;
    return (
      <div className="fin-trilha-overlay" onClick={onClose}>
        <div className="fin-trilha" onClick={e => e.stopPropagation()}>
          <header className="fin-trilha-h">
            <div>
              <span className="fin-trilha-eyebrow">Trilha · Fechamento mensal</span>
              <h2>Fechamento de {new Date().toLocaleDateString("pt-BR", { month: "long", year: "numeric" })}</h2>
              <p>12 passos · checklist persistente · Eliana</p>
            </div>
            <div className="fin-trilha-progress">
              <div className="fin-trilha-pct">{pct}%</div>
              <small>{done.size}/{FECHAMENTO_STEPS.length}</small>
            </div>
            <button className="fin-trilha-x" onClick={onClose} aria-label="Fechar (Esc)">×</button>
          </header>
          <div className="fin-trilha-bar"><div style={{width: pct + "%"}}/></div>
          <ol className="fin-trilha-list">
            {FECHAMENTO_STEPS.map(s => {
              const isDone = done.has(s.id);
              return (
                <li key={s.id} className={"fin-trilha-step" + (isDone ? " done" : "")}>
                  <button
                    className="fin-trilha-check"
                    onClick={() => toggle(s.id)}
                    aria-label={isDone ? "Desmarcar" : "Marcar feito"}>
                    {isDone ? "✓" : s.id}
                  </button>
                  <div className="fin-trilha-body">
                    <b>{s.title}</b>
                    <small>{s.hint}</small>
                  </div>
                  {s.action && (
                    <button className="fin-trilha-cta"
                            onClick={() => { if (s.actionRoute && onNavigate) { onNavigate(s.actionRoute); onClose(); } }}>
                      {s.action} →
                    </button>
                  )}
                </li>
              );
            })}
          </ol>
          <footer className="fin-trilha-ft">
            <button className="fin-trilha-reset" onClick={reset}>Reiniciar checklist</button>
            <span>Salvo automaticamente em localStorage</span>
          </footer>
        </div>
      </div>
    );
  }
  window.FinFechamentoTrilha = FinFechamentoTrilha;

  // ═════════════════════════════════════════════════════════════
  // 3) FAVORITOS PESSOAIS (atalho B) — Eliana pina o que está acompanhando
  // ═════════════════════════════════════════════════════════════
  function useFinFavs() {
    const [set, setSet] = useState(() => {
      try { return new Set(JSON.parse(localStorage.getItem("oimpresso.financeiro.favs") || "[]")); }
      catch (e) { return new Set(); }
    });
    useEffect(() => {
      try { localStorage.setItem("oimpresso.financeiro.favs", JSON.stringify([...set])); } catch (e) {}
    }, [set]);
    return {
      has: (id) => set.has(id),
      toggle: (id) => setSet(prev => {
        const next = new Set(prev);
        if (next.has(id)) next.delete(id); else next.add(id);
        return next;
      }),
      count: set.size,
      all: [...set],
    };
  }
  window.useFinFavs = useFinFavs;

  // ═════════════════════════════════════════════════════════════
  // 4) MODO APRESENTAÇÃO — fullscreen pra reunião com Wagner/sócio
  //    Espelha VdPresentationMode (4 slides)
  // ═════════════════════════════════════════════════════════════
  function FinPresentationMode({ open, onClose }) {
    const [slide, setSlide] = useState(0);
    const slides = [
      { id: "visao", label: "Visão do mês" },
      { id: "fluxo", label: "Onde está o dinheiro" },
      { id: "atencao", label: "Pontos de atenção" },
      { id: "decisao", label: "Decisões pra tomar" },
    ];

    useEffect(() => {
      if (!open) return;
      const onKey = (e) => {
        if (e.key === "Escape") onClose();
        else if (e.key === "ArrowRight" || e.key === " ") setSlide(s => Math.min(slides.length - 1, s + 1));
        else if (e.key === "ArrowLeft") setSlide(s => Math.max(0, s - 1));
      };
      window.addEventListener("keydown", onKey);
      return () => window.removeEventListener("keydown", onKey);
    }, [open, onClose]);

    if (!open) return null;
    const rows = window.FIN_ROWS || [];
    const today = window.FIN_TODAY;
    const monthStart = today ? new Date(today.getFullYear(), today.getMonth(), 1) : null;
    const monthRows = monthStart ? rows.filter(r => (r.due >= monthStart) || (r.paid_at && r.paid_at >= monthStart)) : rows;
    const recebido = monthRows.filter(r => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const pago = monthRows.filter(r => r.kind === "payable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aReceber = monthRows.filter(r => r.kind === "receivable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aPagar = monthRows.filter(r => r.kind === "payable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const saldo = recebido - pago;
    const previsto = saldo + aReceber - aPagar;
    const overdue = rows.filter(r => r.status === "atrasado");
    const byCat = {};
    monthRows.filter(r => r.kind === "payable").forEach(r => { byCat[r.category] = (byCat[r.category] || 0) + r.amount; });
    const topCats = Object.entries(byCat).sort((a,b) => b[1]-a[1]).slice(0, 4);
    const totalGasto = Object.values(byCat).reduce((s, v) => s + v, 0);

    return (
      <div className="fin-pres-overlay">
        <header className="fin-pres-top">
          <span className="fin-pres-brand">OIMPRESSO · Financeiro · {(monthStart || new Date()).toLocaleDateString("pt-BR", { month: "long", year: "numeric" })}</span>
          <span className="fin-pres-counter">{slide + 1} / {slides.length}</span>
          <button className="fin-pres-close" onClick={onClose} title="Esc">×</button>
        </header>
        <main className="fin-pres-stage">
          {slide === 0 && (
            <div className="fin-pres-slide">
              <small className="fin-pres-eyebrow">Saldo previsto fim do mês</small>
              <div className={"fin-pres-amount " + (previsto < 0 ? "neg" : "pos")}>{_fmt(previsto)}</div>
              <div className="fin-pres-row">
                <div><span>Recebido</span><b className="pos">{_fmt(recebido)}</b></div>
                <div><span>Pago</span><b className="neg">{_fmt(pago)}</b></div>
                <div><span>A receber</span><b className="pos">{_fmt(aReceber)}</b></div>
                <div><span>A pagar</span><b className="neg">{_fmt(aPagar)}</b></div>
              </div>
            </div>
          )}
          {slide === 1 && (
            <div className="fin-pres-slide">
              <small className="fin-pres-eyebrow">Onde o dinheiro está saindo</small>
              <h1>Top {topCats.length} categorias</h1>
              <ul className="fin-pres-cats">
                {topCats.map(([cat, val]) => (
                  <li key={cat}>
                    <span className="fin-pres-cat-l">{cat}</span>
                    <span className="fin-pres-cat-r">{_fmt(val)} <small>{Math.round(val/totalGasto*100)}%</small></span>
                  </li>
                ))}
              </ul>
            </div>
          )}
          {slide === 2 && (
            <div className="fin-pres-slide">
              <small className="fin-pres-eyebrow">{overdue.length} lançamentos em atraso</small>
              <div className={"fin-pres-amount-mid " + (overdue.length > 0 ? "neg" : "pos")}>
                {_fmt(overdue.reduce((s, r) => s + r.amount, 0))}
              </div>
              <ul className="fin-pres-late">
                {overdue.slice(0, 5).map(r => (
                  <li key={r.id}>
                    <span className="fin-pres-late-l">{r.party}</span>
                    <span className="fin-pres-late-m">{r.desc.slice(0, 38)}{r.desc.length > 38 ? "…" : ""}</span>
                    <span className="fin-pres-late-r">{_fmt(r.amount)}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
          {slide === 3 && (
            <div className="fin-pres-slide">
              <small className="fin-pres-eyebrow">Pra próxima semana</small>
              <h1>Decisões</h1>
              <ol className="fin-pres-decisoes">
                {previsto < 0 && <li>Saldo previsto fica <b>negativo em R$ {Math.abs(previsto).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".")}</b> — antecipar receitas ou empurrar pagamentos?</li>}
                {overdue.length > 0 && <li>Cobrar os <b>{overdue.length} atrasados</b> antes de fim do mês</li>}
                {aPagar > recebido && <li>A pagar excede o já recebido — <b>liberar próximo aporte</b>?</li>}
                {topCats[0] && <li>Maior despesa: <b>{topCats[0][0]}</b> ({_fmt(topCats[0][1])}) · revisar fornecedores?</li>}
                <li>Reunião próxima 6ª · 14h · pauta gerada</li>
              </ol>
            </div>
          )}
        </main>
        <footer className="fin-pres-bot">
          <button onClick={() => setSlide(s => Math.max(0, s - 1))} disabled={slide === 0}>←</button>
          <div className="fin-pres-dots">
            {slides.map((s, i) => (
              <span key={i} className={"fin-pres-dot" + (i === slide ? " on" : "")} onClick={() => setSlide(i)}/>
            ))}
          </div>
          <button onClick={() => setSlide(s => Math.min(slides.length - 1, s + 1))} disabled={slide === slides.length - 1}>→</button>
        </footer>
      </div>
    );
  }
  window.FinPresentationMode = FinPresentationMode;

})();
