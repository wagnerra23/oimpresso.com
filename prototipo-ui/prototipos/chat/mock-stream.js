// mock-stream.js — fake SSE chunks pra simular Centrifugo streaming
// Substitui o setTimeout do chat.jsx V1 (anti-pattern: charter exige streaming token-a-token)
// Em produção, JanaController::send() → ProcessJanaMessage job → Centrifugo channel jana:thread:{id}
//
// Uso: mockStream(prompt, onDelta, onFinal) — onDelta recebe { delta: 'token' } múltiplas vezes,
//      onFinal recebe { messages: [...] } no fim (array de blocks tipados).

// Bancos de exemplos por intenção detectada (heurística simples no prompt)
const MOCK_RESPONSES = {
  vendas_hoje: {
    chunks: ['Hoje', ' você', ' teve', ' **7', ' vendas**', ' totalizando', ' **R$', ' 4.382,50**', '.'],
    finalBlocks: [
      { role: 'assistant', kind: 'tool_use', tool: 'sells.list_today', params: { date: 'today' }, status: 'done' },
      { role: 'assistant', kind: 'markdown',
        markdown: 'Hoje você teve **7 vendas** totalizando **R$ 4.382,50** [1]. 3 foram à vista e 4 a prazo [2].',
        sources: [
          { n: 1, label: 'Modules/Vestuario/Sells/Index', href: '/vestuario/vendas?date=today' },
          { n: 2, label: 'Relatório Financeiro', href: '/financeiro/dre' },
        ],
      },
      { role: 'assistant', kind: 'data_table',
        caption: 'Vendas de hoje',
        columns: [
          { key: 'id', label: '#' },
          { key: 'cliente', label: 'Cliente' },
          { key: 'total', label: 'Total' },
          { key: 'forma', label: 'Forma' },
        ],
        rows: [
          { id: '#1234', cliente: 'Maria Silva', total: 'R$ 850,00', forma: 'Pix' },
          { id: '#1235', cliente: 'João Souza', total: 'R$ 1.200,00', forma: 'Cartão' },
          { id: '#1236', cliente: 'Ana Costa', total: 'R$ 432,50', forma: 'À prazo' },
          { id: '#1237', cliente: 'Carlos Lima', total: 'R$ 580,00', forma: 'Pix' },
          { id: '#1238', cliente: 'Pedro Alves', total: 'R$ 320,00', forma: 'Pix' },
          { id: '#1239', cliente: 'Beatriz F.',  total: 'R$ 600,00', forma: 'À prazo' },
          { id: '#1240', cliente: 'Marcos R.',   total: 'R$ 400,00', forma: 'À prazo' },
        ],
      },
    ],
  },

  os_atrasadas: {
    chunks: ['Encontrei', ' **3', ' OS', ' atrasadas**', '.'],
    finalBlocks: [
      { role: 'assistant', kind: 'tool_use', tool: 'repair.list_delayed', params: { biz: 4 }, status: 'done' },
      { role: 'assistant', kind: 'markdown',
        markdown: 'Encontrei **3 OS atrasadas** no Modules/Repair [1]. Todas com mais de 5 dias além do prazo prometido.',
        sources: [
          { n: 1, label: 'Modules/Repair/JobSheets', href: '/repair/jobsheets?status=atrasada' },
        ],
      },
      { role: 'assistant', kind: 'data_table',
        caption: 'OS atrasadas (>5d)',
        columns: [
          { key: 'os', label: 'OS' },
          { key: 'cliente', label: 'Cliente' },
          { key: 'atraso', label: 'Atraso' },
          { key: 'estagio', label: 'Estágio' },
        ],
        rows: [
          { os: 'OS-4521', cliente: 'Loja Bella',    atraso: '7 dias', estagio: 'Em produção' },
          { os: 'OS-4519', cliente: 'Café da Esq.',  atraso: '6 dias', estagio: 'Aguardando peça' },
          { os: 'OS-4515', cliente: 'Mecânica Vila', atraso: '12 dias', estagio: 'Em diagnóstico' },
        ],
      },
    ],
  },

  inadimplentes: {
    chunks: ['Top', ' **5', ' clientes', ' inadimplentes**', ' agora:'],
    finalBlocks: [
      { role: 'assistant', kind: 'tool_use', tool: 'financeiro.top_inadimplentes', params: { biz: 4, limit: 5 }, status: 'done' },
      { role: 'assistant', kind: 'markdown',
        markdown: 'Top **5 clientes inadimplentes** [1] totalizando **R$ 12.450,00** em atraso.',
        sources: [
          { n: 1, label: 'Modules/Financeiro/Inadimplencia', href: '/financeiro/inadimplencia' },
        ],
      },
      { role: 'assistant', kind: 'data_table',
        caption: 'Top 5 inadimplentes',
        columns: [
          { key: 'cliente', label: 'Cliente' },
          { key: 'total', label: 'Total atrasado' },
          { key: 'dias', label: 'Dias atrasado' },
        ],
        rows: [
          { cliente: 'Construtora ABC',  total: 'R$ 3.800,00', dias: '45' },
          { cliente: 'Padaria Estrela',  total: 'R$ 2.700,00', dias: '32' },
          { cliente: 'Pet Shop Bichinho', total: 'R$ 2.450,00', dias: '28' },
          { cliente: 'Lava Jato Rápido', total: 'R$ 2.100,00', dias: '21' },
          { cliente: 'Bar do Joaquim',   total: 'R$ 1.400,00', dias: '15' },
        ],
      },
    ],
  },

  cancelar_venda: {
    chunks: ['Vou', ' preparar', ' o', ' cancelamento.', ' Confirme', ' para', ' executar.'],
    finalBlocks: [
      { role: 'assistant', kind: 'markdown',
        markdown: 'Vou preparar o cancelamento da venda **#1234**. Confirme abaixo para executar.',
      },
      { role: 'assistant', kind: 'action_card',
        action: 'cancelar_venda',
        summary: 'Cancelar venda #1234 (R$ 850,00) — Maria Silva. Isso vai liberar estoque (3 peças) e disparar refund se já houver pagamento.',
        confirm_required: true,
        result: null,
      },
    ],
  },

  default: {
    chunks: ['Posso', ' ajudar', ' com', ' isso.', ' Aqui', ' está', ' o', ' que', ' consultei:'],
    finalBlocks: [
      { role: 'assistant', kind: 'tool_use', tool: 'jana.search', params: { q: '(prompt do usuário)' }, status: 'done' },
      { role: 'assistant', kind: 'markdown',
        markdown: 'Resposta exemplo da Jana para um prompt genérico [1]. Em produção, o Brain B (Sonnet via gateway interno) gera a resposta real com base no contexto da empresa.',
        sources: [
          { n: 1, label: 'Modules/Copiloto/MemoriaContrato', href: '/copiloto/admin/memoria' },
        ],
      },
    ],
  },
};

function detectIntent(prompt) {
  const p = (prompt || '').toLowerCase();
  if (/vendas? (hoje|do dia)/.test(p) || /quantas vendas/.test(p)) return 'vendas_hoje';
  if (/os atrasad/.test(p) || /ordens? (de servico|atrasada)/.test(p)) return 'os_atrasadas';
  if (/inadimpl/.test(p) || /em débito/.test(p) || /devendo/.test(p)) return 'inadimplentes';
  if (/cancelar venda/.test(p) || /cancelar #/.test(p)) return 'cancelar_venda';
  return 'default';
}

// API: mockStream(prompt, onDelta, onFinal)
// onDelta({ delta: 'token' }) chamado a cada ~80ms simulando stream
// onFinal({ blocks: [...] }) chamado no fim
function mockStream(prompt, onDelta, onFinal) {
  const intent = detectIntent(prompt);
  const resp = MOCK_RESPONSES[intent] || MOCK_RESPONSES.default;
  let i = 0;
  const tickMs = 70;

  // Latência inicial ~600ms (charter: primeiro token <800ms p95)
  const firstTokenDelay = 600;

  const interval = setTimeout(function tick() {
    if (i < resp.chunks.length) {
      onDelta({ delta: resp.chunks[i] });
      i++;
      setTimeout(tick, tickMs);
    } else {
      // Finaliza com blocks completos
      setTimeout(() => {
        onFinal({ blocks: resp.finalBlocks });
      }, 200);
    }
  }, firstTokenDelay);

  // Retorna handle pra cancelar (caso user clique stop)
  return {
    cancel: () => clearTimeout(interval),
  };
}

window.mockStream = mockStream;
window.detectIntent = detectIntent;
