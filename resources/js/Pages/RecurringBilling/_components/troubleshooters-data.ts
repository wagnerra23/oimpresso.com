// Onda 12 v9,75 — árvores de decisão guiadas (4 problemas comuns cobrança recorrente).
// Port enxuto do prototipo Cowork (recurring-data.jsx linhas 150-216).

export type TroubleshooterId = 'boleto-recusado' | 'cartao-expirado' | 'cliente-sumiu' | 'suspensao';
export type TroubleshooterIcon = 'boleto' | 'card' | 'user' | 'pause';

export interface StepOption { label: string; next: number }
export interface Step { q: string; opts?: StepOption[]; final?: string }
export interface Troubleshooter {
  id: TroubleshooterId;
  title: string;
  icon: TroubleshooterIcon;
  hue: number;
  steps: Step[];
}

export const TROUBLESHOOTERS: Troubleshooter[] = [
  {
    id: 'boleto-recusado',
    title: 'Boleto recusou / não pago',
    icon: 'boleto',
    hue: 60,
    steps: [
      { q: 'Cliente já recebeu o boleto?', opts: [{ label: 'Sim', next: 1 }, { label: 'Não / não sei', next: 2 }] },
      { q: 'Motivo da não-pagamento?', opts: [
        { label: 'Sem saldo / problema financeiro', next: 3 },
        { label: 'Esqueceu / vai pagar', next: 4 },
        { label: 'Não conseguiu falar', next: 5 },
      ]},
      { q: 'Verificar e re-enviar boleto', final: 'Reenviar via WhatsApp + e-mail. Confirmar recebimento. Reagendar retentativa em 3 dias.' },
      { q: 'Negociar parcelamento ou pausa', final: 'Oferecer pausa de 1 ciclo ou parcelamento 2x. Documentar acordo em nota interna.' },
      { q: 'Aguardar com retentativa programada', final: 'Manter retentativa 2/3 agendada. Após pagamento, registrar em nota.' },
      { q: 'Escalar para Wagner', final: 'Tentar 1× por telefone. Se não responder em 48h, escalar para Wagner decidir suspensão.' },
    ],
  },
  {
    id: 'cartao-expirado',
    title: 'Cartão recusou / expirado',
    icon: 'card',
    hue: 250,
    steps: [
      { q: 'Cliente já forneceu novo cartão?', opts: [{ label: 'Sim', next: 1 }, { label: 'Não', next: 2 }] },
      { q: 'Atualizar e re-cobrar', final: 'Atualizar dados no gateway. Re-cobrar manualmente. Confirmar sucesso antes de fechar.' },
      { q: 'Solicitar pelo WhatsApp', final: 'Enviar template HSM "atualizar-cartao". Aguardar 72h. Sugerir migração pra boleto/pix se persistir.' },
    ],
  },
  {
    id: 'cliente-sumiu',
    title: 'Cliente sumiu há 7+ dias',
    icon: 'user',
    hue: 25,
    steps: [
      { q: 'Quantos canais já testou?', opts: [
        { label: 'Só WhatsApp', next: 1 },
        { label: 'WhatsApp + e-mail', next: 2 },
        { label: 'WhatsApp + e-mail + telefone', next: 3 },
      ]},
      { q: 'Tentar canais adicionais', final: 'Enviar e-mail formal + ligar no telefone cadastrado. Documentar em nota interna.' },
      { q: 'Última tentativa antes de suspender', final: 'Ligar telefone + tentar sócios na junta comercial (CNPJ).' },
      { q: 'Suspender com prazo de retomada', final: 'Pausar 30 dias com "cliente sem resposta". Após 30 dias, cancelar com motivo "inadimplência". Notificar Wagner.' },
    ],
  },
  {
    id: 'suspensao',
    title: 'Suspensão por inadimplência',
    icon: 'pause',
    hue: 25,
    steps: [
      { q: 'Quantas falhas consecutivas?', opts: [{ label: '3 (mínimo)', next: 1 }, { label: '4 ou mais', next: 2 }] },
      { q: 'Suspensão sugerida', final: 'Pausar. Enviar e-mail formal informando. Manter histórico ativo para retomada. Notificar Wagner.' },
      { q: 'Cancelamento sugerido', final: 'Cancelar com motivo "inadimplência". Bloquear nova contratação por 90d. Registrar em auditoria.' },
    ],
  },
];

export function findTroubleshooter(id: TroubleshooterId): Troubleshooter | undefined {
  return TROUBLESHOOTERS.find((t) => t.id === id);
}
