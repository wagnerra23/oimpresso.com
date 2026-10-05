// Dados de demonstração das propostas P1–P8 — mesmo formato dos contratos em handoff/propostas-app/api/.
// Destino no app: juntar a src/demo.ts (modo `npm run build:demo`). Nada aqui calcula valor: os números
// são as respostas que o ERP daria. Tipos espelham os contratos; ajustar src/api.ts junto.

export type ErroApp = 'sem_permissao' | 'nao_encontrado' | 'etapa_mudou' | 'em_andamento' | 'bloqueado' | 'validacao' | 'sem_consentimento' | 'janela_fechada' | 'sem_configuracao';

/** Simulador de erro do modo demo: troque para testar cada estado da tela. */
export const ERRO_DEMO: ErroApp | null = null;

// P1 · tela 22 — GET /pedidos/{id} com acoes[] e POST /pedidos/{id}/acao
export const pedido4812 = {
  id: 4812, numero: '4812', cliente: 'Ótica Visão Clara', resumo: 'Fachada ACM + letra caixa',
  valor: 7840.0, prazo: '2026-10-02', atrasado: true,
  etapa: { chave: 'in_production', rotulo: 'Em produção', grupo: 'producao' }, progresso: 0.5,
  itens_venda: [{ produto: 'ACM 3 mm · 6,40 × 0,90 m', quantidade: 5.76, total: 5184.0 }, { produto: 'Letra caixa inox', quantidade: 14, total: 2656.0 }],
  cliente_detalhe: { id: 1, nome: 'Ótica Visão Clara', telefone: '(47) 3321-0400' },
  etapas: [
    { grupo: 'orcamento', rotulo: 'Orçamento', estado: 'feito' }, { grupo: 'aprovacao', rotulo: 'Aprovação', estado: 'feito' },
    { grupo: 'producao', rotulo: 'Produção', estado: 'atual' }, { grupo: 'entrega', rotulo: 'Entrega', estado: 'futuro' },
    { grupo: 'concluido', rotulo: 'Concluído', estado: 'futuro' },
  ],
  acoes: [{ chave: 'put_on_hold', rotulo: 'Pôr em espera', pode: true }, { chave: 'ready_for_invoice', rotulo: 'Pronto para faturar', pode: true }],
};

// P2 · telas 03/22 — GET /os/{id}/arquivos
export const arquivosOs1042 = {
  itens: [
    { id: 812, nome: 'IMG_0412.jpg', tipo: 'image/jpeg', tamanho: 284113, legenda: 'Fachada · antes', etapa: 'recepcao', enviado_em: '2026-10-05T12:41:00Z', enviado_por: 'Ana Souza', url: '', miniatura: '' },
    { id: 813, nome: 'IMG_0413.jpg', tipo: 'image/jpeg', tamanho: 301877, legenda: 'Medição lateral', etapa: 'recepcao', enviado_em: '2026-10-05T12:43:00Z', enviado_por: 'Ana Souza', url: '', miniatura: '' },
    { id: 820, nome: 'arte-v3.pdf', tipo: 'application/pdf', tamanho: 1204551, legenda: 'Arte aprovada', etapa: 'diagnostico', enviado_em: '2026-10-04T09:10:00Z', enviado_por: 'Bruno Lima', url: '', miniatura: '' },
  ],
  pode_enviar: true,
};

// P3 · tela 22 — POST /pedidos/{id}/entrega (201)
export const entrega4790 = {
  protocolo: 'ENT-0418', registrado_em: '2026-10-05T14:22:00Z', recebido_por: 'Dra. Paula Reis', com_assinatura: true,
  itens_pedido: [{ linha_id: 5512, descricao: 'Placa ACM recortada', quantidade: '12 un' }, { linha_id: 5513, descricao: 'Kit de fixação', quantidade: '1 cx' }, { linha_id: 5514, descricao: 'Adesivo de porta', quantidade: '2 un' }],
};

// P4 · tela 04 — materiais e /orcamentos/calcular (o app só exibe)
export const materiais = { itens: [
  { id: 12, nome: 'Lona 440g', categoria: 'Lonas', unidade: 'm²', preco_m2: 65.0 },
  { id: 18, nome: 'Adesivo vinil', categoria: 'Adesivos', unidade: 'm²', preco_m2: 85.0 },
  { id: 31, nome: 'ACM 3 mm', categoria: 'Chapas', unidade: 'm²', preco_m2: 280.0 },
] };
/** Resposta do ERP para o par (material, medidas). No demo, só os casos do protótipo. */
export const calculos: Record<string, { area_m2: number; preco_m2: number; total: number }> = {
  '12|3.00|1.20|1': { area_m2: 3.6, preco_m2: 65.0, total: 234.0 },
  '31|6.40|0.90|1': { area_m2: 5.76, preco_m2: 280.0, total: 1612.8 },
};

// P5 · tela 41 — GET /atendimento
export const atendimento = {
  canal: { ligado: true, numero: '+55 47 3321-0400' },
  conversas: [
    { id: 77, contato: { id: 1, nome: 'Ótica Visão Clara' }, ultima: 'Pode mandar o orçamento?', quando: '2026-10-05T12:31:00Z', nao_lidas: 2, consentimento: true, janela_aberta_ate: '2026-10-06T12:31:00Z' },
    { id: 78, contato: { id: 3, nome: 'Mercado Bom Preço' }, ultima: 'Obrigado!', quando: '2026-10-04T17:02:00Z', nao_lidas: 0, consentimento: true, janela_aberta_ate: null },
    { id: 79, contato: { id: 6, nome: 'Padaria Trigo Fino' }, ultima: 'Qual o prazo das placas?', quando: '2026-10-05T12:12:00Z', nao_lidas: 1, consentimento: false, janela_aberta_ate: '2026-10-06T12:12:00Z' },
  ],
  pagina: 1, tem_mais: false,
};

// P6 · tela 13 — ?aba=lucratividade e ?aba=comissoes (mês)
export const lucratividade = { lucratividade: {
  receita: 148230.0, custo_variavel: 79400.0, custo_fixo: 41000.0, margem_contribuicao: 68830.0, margem_pct: 46.4, ponto_equilibrio: 88362.07,
  menores_margens: [
    { pedido_id: 4819, numero: '4819', resumo: 'Placas PS 30 × 40 cm', valor: 452.0, margem_pct: 12.0 },
    { pedido_id: 4807, numero: '4807', resumo: 'Kit PDV campanha verão', valor: 2315.0, margem_pct: 21.0 },
  ] } };
export const comissoes = { comissoes: { base: 'payment_received', vendedores: [
  { id: 7, nome: 'Ana Souza', percentual: 3.0, vendido: 62100.0, recebido: 54300.0, comissao: 1629.0, a_liberar: 234.0 },
  { id: 8, nome: 'Bruno Lima', percentual: 3.0, vendido: 48400.0, recebido: 39800.0, comissao: 1194.0, a_liberar: 258.0 },
] } };

// P7 — nada novo: nao_lidas já vem em /inicio.

// P8 · tela 02 — etiqueta e apontamento
export const etiquetas: Record<string, unknown> = {
  '4812': { pedido: { id: 4812, numero: '4812', resumo: 'Fachada ACM + letra caixa', cliente: 'Ótica Visão Clara' }, etapa: { chave: 'in_production', rotulo: 'Produção · Impressão' }, pode_apontar: true },
  '4815': { pedido: { id: 4815, numero: '4815', resumo: 'Banners formatura', cliente: 'Escola Aprender' }, etapa: { chave: 'in_production', rotulo: 'Produção · Acabamento' }, pode_apontar: true },
};
export const apontamentoEmAndamento = { item: null };
