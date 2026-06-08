// FinTroubleshooter — Cowork KB-9.75 Financeiro Onda 7b
// (dialog modal árvore de decisão pra divergências comuns).
//
// Refs:
//  - prototipo-ui/financeiro-output.jsx FIN_TROUBLES + FinTroubleButton
//
// 4 troubleshooters canônicos:
//   1. Saldo do extrato não bate com o caixa (conciliação)
//   2. Cliente pagou o mesmo boleto 2 vezes (boleto)
//   3. Fornecedor cobrou diferente do pedido (compras)
//   4. Rejeição da SEFAZ chegou no Financeiro (fiscal)
//
// Pure compute, sem backend, sem LLM. Wagner regra Tier 0 multi-tenant safe
// (não toca queries Eloquent, só renderiza guia).

import { useState, useEffect, useMemo, useCallback, type ReactNode } from 'react';

type TroubleHue = 25 | 60 | 145 | 240;

interface Fix {
  fix: string;
}

interface StepNode {
  q: string;
  yes: Fix | number; // number = índice do próximo step
  no: Fix | number;
}

export interface TroubleTree {
  id: string;
  title: string;
  equip: string;
  hue: TroubleHue;
  when: string;
  steps: StepNode[];
}

// Árvores de decisão canônicas (espelhadas do protótipo Cowork)
export const FIN_TROUBLES: TroubleTree[] = [
  {
    id: 'tr-extrato-nao-bate',
    title: 'Saldo do extrato não bate com o caixa',
    equip: 'conciliação',
    hue: 25,
    when: 'fim do dia · diferença entre OFX e contábil',
    steps: [
      {
        q: 'A diferença é menor que R$ [redacted Tier 0]?',
        yes: { fix: "Tolerância normal de arredondamento bancário. Aceite a conciliação automática. Anote a diferença em 'Outros' do plano de contas e siga." },
        no: 1,
      },
      {
        q: 'Existe boleto pago hoje sem match no Financeiro?',
        yes: { fix: "Provavelmente cliente pagou direto no banco e o sistema não baixou. Vai em Conciliação → linha pendente → 'Confirmar manual' apontando pra venda original. Atualiza paid_at." },
        no: 2,
      },
      {
        q: 'Houve sangria ou suprimento de caixa hoje?',
        yes: { fix: "Cheque se a sangria foi lançada como saída e o suprimento como entrada. Em Caixa do dia → 'Movimentos'. Se faltar, lance retroativo com motivo." },
        no: 3,
      },
      {
        q: 'Cliente fez PIX direto pra conta pessoal do Wagner?',
        yes: { fix: "Pessoa-jurídica ≠ pessoa-física. Wagner registra como 'aporte do sócio' e a venda fica em aberto. Pede o PIX correto pra conta da empresa." },
        no: { fix: 'Diferença não-trivial. Compare 3 últimos dias contra OFX, baixa de Inter (web), confronta cada linha. Se persistir, abre chamado com a contabilidade.' },
      },
    ],
  },
  {
    id: 'tr-boleto-pago-2x',
    title: 'Cliente pagou o mesmo boleto 2 vezes',
    equip: 'boleto',
    hue: 240,
    when: 'cliente entrou em contato reclamando · duplicidade',
    steps: [
      {
        q: 'As duas baixas estão visíveis no extrato Inter?',
        yes: 1,
        no: { fix: 'Cliente diz que pagou 2× mas só 1 baixa apareceu — pode ser estorno automático do banco origem. Aguardar 24h. Se confirmar duplicidade no extrato dele (pedir comprovante), aí investiga.' },
      },
      {
        q: 'O cliente quer estorno ou crédito pra próxima compra?',
        yes: { fix: "Crédito: cria lançamento payable manual no Financeiro com a contraparte do cliente, marca 'crédito disponível' e abate na próxima venda. Não emite NF-e nova." },
        no: 2,
      },
      {
        q: 'Foram menos de 24h da segunda baixa?',
        yes: { fix: "Pede estorno via Inter API (módulo Boleto → boleto → 'Estornar'). SEFAZ não envolve porque a NF-e original está correta. Cliente recebe via PIX em algumas horas." },
        no: { fix: "Após 24h, faz transferência manual TED/PIX devolvendo o valor a mais. Documenta no comentário do lançamento financeiro: 'Devolução por duplicidade · ref. PIX X'." },
      },
    ],
  },
  {
    id: 'tr-fornecedor-cobrou-errado',
    title: 'Fornecedor cobrou diferente do pedido',
    equip: 'compras',
    hue: 60,
    when: 'NF chegou maior que orçado · #PC-NNN',
    steps: [
      {
        q: 'A diferença é menor que 5% do total do pedido?',
        yes: { fix: 'Tolerância normal de oscilação de insumo. Aceita, lança no payable normal, anota a diferença. Se for sistemática (mesmo fornecedor 3× seguidas), renegocia.' },
        no: 1,
      },
      {
        q: 'Houve mudança de quantidade ou produto não combinado?',
        yes: { fix: 'Recusa a NF (não dá entrada no estoque). Pede ao fornecedor pra emitir NF de DEVOLUÇÃO da diferença ou refazer a nota correta. Não paga até regularizar.' },
        no: 2,
      },
      {
        q: 'Frete ou ICMS-ST veio sem aviso?',
        yes: { fix: "Cheque o pedido de compra original (#PC-NNN). Se frete não foi orçado, é discussão. Se foi 'FOB' (por conta do destinatário), você paga separado. ICMS-ST geralmente é repassado e ok." },
        no: { fix: 'Diferença não justificada. Abre conversa formal com fornecedor (e-mail, não WhatsApp). Suspende próximos pedidos até esclarecer. Se reincidente, troca de fornecedor.' },
      },
    ],
  },
  {
    id: 'tr-nfe-rejeitada-fin',
    title: 'Rejeição da SEFAZ chegou no Financeiro',
    equip: 'fiscal',
    hue: 25,
    when: 'venda já pendurada · #V- + rejeição',
    steps: [
      {
        q: 'Já abriu o drawer da venda original pra ver o motivo?',
        yes: 1,
        no: { fix: 'Vai primeiro no drawer da venda (#V-NNNN no campo desc). Veja a aba Fiscal · status NF-e. Lá tem o código de rejeição SEFAZ específico.' },
      },
      {
        q: 'É código 539 (duplicidade) ou 692 (IE inválida)?',
        yes: { fix: "Esses são os 2 mais comuns. Há troubleshooter próprio no Vendas: drawer da venda → footer → '? Resolver: NF-e rejeitada'. Ele te leva pelo passo-a-passo." },
        no: 2,
      },
      {
        q: 'O cliente já pagou antes da rejeição?',
        yes: { fix: 'Cliente recebe o produto/serviço, você recebe o dinheiro, mas a NF não está autorizada. Tem 24h pra inutilizar o número rejeitado e emitir o próximo. Avise a Eliana pra não conciliar até resolver.' },
        no: { fix: 'Sem pagamento ainda, sem pressa. Vendedor resolve a rejeição na venda original e re-emite. Financeiro continua aguardando paid_at.' },
      },
    ],
  },
];

interface FinTroubleshooterDialogProps {
  open: boolean;
  onClose: () => void;
  suggestedId?: string | null;
}

export function FinTroubleshooterDialog({ open, onClose, suggestedId = null }: FinTroubleshooterDialogProps) {
  const [activeId, setActiveId] = useState<string | null>(null);
  const [stepIdx, setStepIdx] = useState(0);
  const [resolution, setResolution] = useState<string | null>(null);

  const active = useMemo(() => FIN_TROUBLES.find((t) => t.id === activeId), [activeId]);

  // Sugere automaticamente quando abre com suggestedId
  useEffect(() => {
    if (open && suggestedId) {
      setActiveId(suggestedId);
      setStepIdx(0);
      setResolution(null);
    }
    if (!open) {
      setActiveId(null);
      setStepIdx(0);
      setResolution(null);
    }
  }, [open, suggestedId]);

  const handleAnswer = useCallback((answer: 'yes' | 'no') => {
    if (!active) return;
    const step = active.steps[stepIdx];
    const next = answer === 'yes' ? step.yes : step.no;
    if (typeof next === 'number') {
      setStepIdx(next);
    } else {
      setResolution(next.fix);
    }
  }, [active, stepIdx]);

  const handleRestart = useCallback(() => {
    setStepIdx(0);
    setResolution(null);
  }, []);

  const handleBackToList = useCallback(() => {
    setActiveId(null);
    setStepIdx(0);
    setResolution(null);
  }, []);

  if (!open) return null;

  return (
    <>
      <div className="fin-trouble-backdrop" onClick={onClose} aria-hidden="true" />
      <div className="fin-trouble-dialog" role="dialog" aria-labelledby="fin-trouble-title">
        <header className="fin-trouble-h">
          <div>
            <h2 id="fin-trouble-title">? Resolver problemas comuns</h2>
            <small>4 fluxos de decisão · troubleshooter financeiro</small>
          </div>
          <button type="button" className="fin-trouble-x" onClick={onClose} aria-label="Fechar">×</button>
        </header>

        {!active && (
          <div className="fin-trouble-list">
            {FIN_TROUBLES.map((t) => (
              <button
                key={t.id}
                type="button"
                className="fin-trouble-card"
                style={{ '--tr-hue': t.hue } as React.CSSProperties}
                onClick={() => { setActiveId(t.id); setStepIdx(0); setResolution(null); }}
              >
                <div className="fin-trouble-card-h">
                  <span className="fin-trouble-card-equip">{t.equip}</span>
                  <h3>{t.title}</h3>
                </div>
                <small>{t.when}</small>
                <span className="fin-trouble-card-arrow" aria-hidden="true">→</span>
              </button>
            ))}
          </div>
        )}

        {active && !resolution && (
          <div className="fin-trouble-flow">
            <div className="fin-trouble-flow-h">
              <button type="button" className="fin-trouble-back" onClick={handleBackToList}>← Voltar</button>
              <h3 style={{ '--tr-hue': active.hue } as React.CSSProperties}>{active.title}</h3>
              <small>Passo {stepIdx + 1} de {active.steps.length}</small>
            </div>
            <div className="fin-trouble-step">
              <p className="fin-trouble-q">{active.steps[stepIdx].q}</p>
              <div className="fin-trouble-answers">
                <button type="button" className="fin-trouble-answer yes" onClick={() => handleAnswer('yes')}>Sim</button>
                <button type="button" className="fin-trouble-answer no" onClick={() => handleAnswer('no')}>Não</button>
              </div>
            </div>
          </div>
        )}

        {active && resolution && (
          <div className="fin-trouble-resolution">
            <div className="fin-trouble-flow-h">
              <button type="button" className="fin-trouble-back" onClick={handleBackToList}>← Lista</button>
              <h3 style={{ '--tr-hue': active.hue } as React.CSSProperties}>{active.title}</h3>
            </div>
            <div className="fin-trouble-fix">
              <span className="fin-trouble-fix-ic" style={{ '--tr-hue': active.hue } as React.CSSProperties}>✓</span>
              <p>{resolution}</p>
            </div>
            <div className="fin-trouble-footer">
              <button type="button" className="fin-trouble-restart" onClick={handleRestart}>↻ Refazer este fluxo</button>
              <button type="button" className="fin-trouble-back" onClick={handleBackToList}>← Outro problema</button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}

/** Botão "? Resolver" pra pôr no footer ou drawer. */
interface FinTroubleButtonProps {
  onClick: () => void;
  label?: string;
}
export function FinTroubleButton({ onClick, label = '? Resolver' }: FinTroubleButtonProps): ReactNode {
  return (
    <button
      type="button"
      className="fin-trouble-trigger"
      onClick={onClick}
      title={`Troubleshooter: ${FIN_TROUBLES.length} fluxos de decisão`}
    >
      {label}
      <span className="fin-trouble-count">{FIN_TROUBLES.length}</span>
    </button>
  );
}

export default FinTroubleshooterDialog;
