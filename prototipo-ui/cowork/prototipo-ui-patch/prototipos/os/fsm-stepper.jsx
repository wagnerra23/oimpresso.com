// fsm-stepper.jsx — FSM padronizada cross-módulo (2026-05-18)
// Stepper canônico reusável pra Vendas, OS, Financeiro, Boleto, Recurring.
//
// Uso:
//   <FsmStepper domain="financeiro" current={2}/>
//   <FsmStepper domain="venda_cv"   current={3} variant="dots-inline"/>
//
// Variantes:
//   dots-inline  — bolinhas + label da fase atual (padrão Vendas inline)
//   full-stepper — passo-a-passo completo com labels (drawer)
//   breadcrumb   — texto compacto "Conferido › Conciliado"

(() => {
  const { useMemo } = React;

  window.FSM_DOMAINS = {
    venda_cv: {
      steps: ["Orçamento", "Pedido", "Faturada", "Entregue", "Paga"],
      lbls:  ["orç", "ped", "fat", "ent", "pag"],
      hue: 155,
    },
    venda_mec: {
      steps: ["Diagnóstico", "Aguardando peça", "Em serviço", "Pronto p/ retirar", "Entregue"],
      lbls:  ["diag", "peça", "serv", "pronto", "ent"],
      hue: 200,
    },
    os: {
      // 6 fases reais (data-os.jsx) + terminal cancelado.
      // Rascunho não entra: é estado pré-pipeline.
      steps: ["Orçado", "Aprovação", "Produção", "Acabamento", "Expedição", "Entregue"],
      lbls:  ["orç", "aprov", "prod", "acab", "exped", "entreg"],
      hue: 220,  // alinhado com --accent do shell (mesmo tema, sem inventar hue)
      terminals: {
        cancelado: { hue: 240, ic: "⊘", lbl: "cancelado" },
      },
    },
    financeiro: {
      steps: ["Emitido", "Conferido", "Conciliado", "Liquidado"],
      lbls:  ["emit", "conf", "conc", "liq"],
      hue: 145,
    },
    boleto: {
      steps: ["Gerado", "Registrado", "Pago"],
      lbls:  ["ger", "reg", "pago"],
      terminals: {
        vencido:   { hue: 25,  ic: "✕", lbl: "vencido" },
        cancelado: { hue: 240, ic: "⊘", lbl: "cancelado" },
      },
      hue: 200,
    },
    recurring: {
      steps: ["Ativa", "Suspensa", "Cancelada"],
      lbls:  ["ativa", "susp", "canc"],
      hue: 295,
    },
  };

  /**
   * Stepper genérico — 3 variantes.
   *
   * Props:
   *   domain   — string key em FSM_DOMAINS
   *   current  — número (índice 0-N)
   *   variant  — "dots-inline" (default) | "full-stepper" | "breadcrumb"
   *   terminal — string opcional (ex: "vencido") — sobrepõe step
   *   onClick  — opcional · (idx) => void (avançar manualmente)
   */
  function FsmStepper({ domain, current, variant = "dots-inline", terminal, onClick }) {
    const def = window.FSM_DOMAINS[domain];
    if (!def) return null;

    // Estado terminal (vencido, cancelado etc) ramifica o caminho normal
    const isTerminal = terminal && def.terminals && def.terminals[terminal];
    const term = isTerminal || null;

    const hue = term ? term.hue : def.hue;
    const total = def.steps.length;
    const safeCurrent = Math.max(0, Math.min(total - 1, current ?? 0));
    const curLabel = term ? term.lbl : def.steps[safeCurrent];
    const curShort = term ? term.lbl : def.lbls?.[safeCurrent];

    // Inline · padrão (5 bolinhas com label da fase atual)
    if (variant === "dots-inline") {
      return (
        <span className={`fsm-stepper fsm-inline ${term ? "fsm-terminal" : ""}`}
              style={{ "--fsm-hue": hue }}
              title={`${curLabel} — fase ${safeCurrent + 1} de ${total}`}>
          {def.steps.map((s, i) => {
            let cls = "fsm-dot";
            if (term) cls += " term";
            else if (i < safeCurrent) cls += " done";
            else if (i === safeCurrent) cls += " current";
            return (
              <span key={i}
                    className={cls}
                    onClick={onClick ? () => onClick(i) : null}
                    style={onClick ? { cursor: "pointer" } : null}/>
            );
          })}
          <span className="fsm-lbl">{term ? `${term.ic} ${term.lbl}` : curShort}</span>
        </span>
      );
    }

    // Full-stepper · passo-a-passo completo (drawer)
    if (variant === "full-stepper") {
      return (
        <ol className={`fsm-stepper fsm-full ${term ? "fsm-terminal" : ""}`}
            style={{ "--fsm-hue": hue }}>
          {def.steps.map((s, i) => {
            let cls = "fsm-step";
            if (term) cls += " term";
            else if (i < safeCurrent) cls += " done";
            else if (i === safeCurrent) cls += " current";
            return (
              <li key={i} className={cls}
                  onClick={onClick ? () => onClick(i) : null}
                  style={onClick ? { cursor: "pointer" } : null}>
                <span className="fsm-step-num">
                  {term ? "—" : (i < safeCurrent ? "✓" : i + 1)}
                </span>
                <span className="fsm-step-lbl">{s}</span>
                {i < total - 1 && <span className="fsm-step-line"/>}
              </li>
            );
          })}
          {term && (
            <li className="fsm-step term-end">
              <span className="fsm-step-num">{term.ic}</span>
              <span className="fsm-step-lbl">{term.lbl}</span>
            </li>
          )}
        </ol>
      );
    }

    // Breadcrumb · só texto
    if (variant === "breadcrumb") {
      return (
        <span className={`fsm-stepper fsm-breadcrumb ${term ? "fsm-terminal" : ""}`}
              style={{ "--fsm-hue": hue }}>
          {def.steps.slice(0, safeCurrent + 1).map((s, i, arr) => (
            <React.Fragment key={i}>
              <span className={i === arr.length - 1 ? "fsm-bc-current" : "fsm-bc-past"}>{s}</span>
              {i < arr.length - 1 && <span className="fsm-bc-sep">›</span>}
            </React.Fragment>
          ))}
          {term && (
            <>
              <span className="fsm-bc-sep">›</span>
              <span className="fsm-bc-terminal">{term.ic} {term.lbl}</span>
            </>
          )}
        </span>
      );
    }

    return null;
  }
  window.FsmStepper = FsmStepper;

  /**
   * Helper específico pra Financeiro — calcula a fase a partir do row.
   * Lógica:
   *   - Liquidado (3) se paid_at != null
   *   - Conciliado (2) se paid_at e match com extrato (proxy: status === "recebido" || "pago")
   *   - Conferido (1) se window.useFinConferido().has(row.id) (proxy via localStorage)
   *   - Emitido (0) default
   *
   * No backend real essas fases viriam do schema. Aqui é deriva.
   */
  window.finFsmStage = function finFsmStage(row, conferidoSet) {
    if (!row) return 0;
    if (row.paid_at) {
      // Se já tem paid_at, pode estar entre Conciliado (2) e Liquidado (3)
      // Hoje o mock não diferencia; assume Liquidado se status === "recebido"|"pago"
      if (row.status === "recebido" || row.status === "pago") return 3;
      return 2; // conciliado mas não totalmente liquidado
    }
    // Não pago
    const isConf = conferidoSet && (conferidoSet.has ? conferidoSet.has(row.id) : conferidoSet.includes(row.id));
    if (isConf) return 1;
    return 0;
  };

  /**
   * Helper pra Boleto — terminal vencido/cancelado se status apropriado
   */
  window.boletoFsmStage = function boletoFsmStage(boleto) {
    if (!boleto) return { current: 0 };
    if (boleto.status === "cancelado") return { current: 0, terminal: "cancelado" };
    if (boleto.status === "vencido")   return { current: 1, terminal: "vencido" };
    if (boleto.paid_at) return { current: 2 };
    if (boleto.registered) return { current: 1 };
    return { current: 0 };
  };

  /**
   * Helper pra OS — mapeia OS_STAGES (data-os.jsx) → índice no FSM os
   * data-os.jsx tem 8 stages; FSM os tem 6 (rascunho e cancelado fora do pipeline).
   */
  window.osFsmStage = function osFsmStage(stageId) {
    switch (stageId) {
      case "rascunho":   return { current: 0 };                              // pré-pipeline
      case "orcado":     return { current: 0 };
      case "aprovacao":  return { current: 1 };
      case "producao":   return { current: 2 };
      case "acabamento": return { current: 3 };
      case "expedicao":  return { current: 4 };
      case "entregue":   return { current: 5 };
      case "cancelado":  return { current: 0, terminal: "cancelado" };
      default:           return { current: 0 };
    }
  };
})();
