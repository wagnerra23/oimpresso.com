/**
 * KB Unificado — mock data
 *
 * Seed dos 18 artigos do protótipo Cowork (`kb-page.jsx::INITIAL_ARTICLES`)
 * + 7 categorias + 16 subcategorias + 3 trilhas + 3 troubleshooters.
 *
 * USO: fallback dev-mode quando Agent A ainda não entregou Controller/backend.
 * Quando props Inertia vierem, este mock é IGNORADO.
 *
 * Conversão Cowork → schema canônico:
 *   - id "a1" → id 1
 *   - cat slug "equip" → category_id (resolvido pelo lookup mockado)
 *   - "updated: 'há 3 dias'" → updated_at ISO relativo (subtraindo do agora)
 *   - body[] (Cowork) → body_blocks (schema)
 *   - reads/helpful/outdated → reads_count/helpful_count/outdated_votes
 *
 * TODO[CL]: quando backend entregar, mover este arquivo pra
 *   `_lib/mockData.dev.ts` e gate via `import.meta.env.DEV`.
 */

import type {
  KbCategory,
  KbDecisionTree,
  KbDecisionTreeStep,
  KbKpis,
  KbNode,
  KbPath,
  KbSubcategory,
} from './types';

// ──────────────────────────────────────────────────────────────────
// Helper pra gerar updated_at relativo (compat com seeds Cowork "há X dias")
// ──────────────────────────────────────────────────────────────────

function daysAgo(d: number): string {
  return new Date(Date.now() - d * 86_400_000).toISOString();
}

// ──────────────────────────────────────────────────────────────────
// Categorias (port do Cowork CATEGORIES + 1 "governance" pra ADRs)
// ──────────────────────────────────────────────────────────────────

export const MOCK_CATEGORIES: KbCategory[] = [
  { id: 1, business_id: 4, slug: 'producao', label: 'Produção', description: 'Impressão, acabamento, expedição', hue: 30, icon: null, sort_order: 1 },
  { id: 2, business_id: 4, slug: 'equip', label: 'Equipamentos', description: 'Roland, HP Latex, plotter, laminadora', hue: 280, icon: null, sort_order: 2 },
  { id: 3, business_id: 4, slug: 'arte', label: 'Pré-impressão', description: 'Sangria, ICC, fontes, PDF', hue: 200, icon: null, sort_order: 3 },
  { id: 4, business_id: 4, slug: 'atendim', label: 'Atendimento', description: 'Brief, aprovação, retirada', hue: 145, icon: null, sort_order: 4 },
  { id: 5, business_id: 4, slug: 'fiscal', label: 'Fiscal & financeiro', description: 'NF-e, NFS-e, boleto Inter, SEFAZ', hue: 60, icon: null, sort_order: 5 },
  { id: 6, business_id: 4, slug: 'sistema', label: 'Sistema (ERP)', description: 'OS, vendas, cadastros, atalhos', hue: 250, icon: null, sort_order: 6 },
  { id: 7, business_id: 4, slug: 'rh', label: 'Pessoas', description: 'Ponto, escalas, segurança', hue: 295, icon: null, sort_order: 7 },
];

// ──────────────────────────────────────────────────────────────────
// Subcategorias (port do Cowork KB_SUBCATS)
// Sem auto_match — frontend usa derivação client-side via mockData.deriveSub
// ──────────────────────────────────────────────────────────────────

export const MOCK_SUBCATEGORIES: KbSubcategory[] = [
  // equip (cat 2)
  { id: 1, business_id: 4, category_id: 2, slug: 'roland', label: 'Roland VS-540', description: null, auto_match: { field: 'equip', op: '=', value: 'Roland VS-540' } },
  { id: 2, business_id: 4, category_id: 2, slug: 'latex', label: 'HP Latex 365', description: null, auto_match: { field: 'equip', op: '=', value: 'HP Latex 365' } },
  { id: 3, business_id: 4, category_id: 2, slug: 'plotter', label: 'Plotter Graphtec', description: null, auto_match: { field: 'equip', op: '=', value: 'Plotter Graphtec' } },
  { id: 4, business_id: 4, category_id: 2, slug: 'lamina', label: 'Laminadora Royal', description: null, auto_match: { field: 'equip', op: '=', value: 'Laminadora Royal' } },
  // producao (cat 1)
  { id: 5, business_id: 4, category_id: 1, slug: 'impressao', label: 'Impressão', description: null, auto_match: { field: 'tags', op: 'regex', value: 'impress|tinta|cor|icc' } },
  { id: 6, business_id: 4, category_id: 1, slug: 'acabamento', label: 'Acabamento', description: null, auto_match: { field: 'tags', op: 'regex', value: 'acab|lamin|recorte' } },
  { id: 7, business_id: 4, category_id: 1, slug: 'expedicao', label: 'Expedição', description: null, auto_match: { field: 'tags', op: 'regex', value: 'motoboy|romaneio|expedi' } },
  // arte (cat 3)
  { id: 8, business_id: 4, category_id: 3, slug: 'pdf', label: 'PDF & fontes', description: null, auto_match: { field: 'tags', op: 'regex', value: 'pdf|fonte' } },
  { id: 9, business_id: 4, category_id: 3, slug: 'medida', label: 'Medidas & sangria', description: null, auto_match: { field: 'tags', op: 'regex', value: 'sangria|medida|illustrator|banner' } },
  // atendim (cat 4)
  { id: 10, business_id: 4, category_id: 4, slug: 'brief', label: 'Brief & abertura', description: null, auto_match: { field: 'tags', op: 'regex', value: 'brief|os|atend' } },
  { id: 11, business_id: 4, category_id: 4, slug: 'aprovacao', label: 'Aprovação', description: null, auto_match: { field: 'tags', op: 'regex', value: 'aprovaç|arte|fluxo' } },
  { id: 12, business_id: 4, category_id: 4, slug: 'comunic', label: 'Comunicação', description: null, auto_match: { field: 'tags', op: 'regex', value: 'comunic|cliente|atraso' } },
  // fiscal (cat 5)
  { id: 13, business_id: 4, category_id: 5, slug: 'nf', label: 'Notas fiscais', description: null, auto_match: { field: 'tags', op: 'regex', value: 'nf-e|nfs-e|sefaz|rps' } },
  { id: 14, business_id: 4, category_id: 5, slug: 'cobranca', label: 'Cobrança', description: null, auto_match: { field: 'tags', op: 'regex', value: 'boleto|inter|cobrança' } },
  // sistema (cat 6)
  { id: 15, business_id: 4, category_id: 6, slug: 'atalhos', label: 'Atalhos', description: null, auto_match: { field: 'tags', op: 'regex', value: 'atalho|teclado' } },
  // rh (cat 7)
  { id: 16, business_id: 4, category_id: 7, slug: 'ponto', label: 'Ponto WR2', description: null, auto_match: { field: 'tags', op: 'regex', value: 'ponto|wr2' } },
];

/**
 * Derivação client-side de subcategoria a partir do nó.
 * Espelha kbDeriveSub do Cowork — backend pode reproduzir mesma lógica via auto_match.
 */
export function deriveSubcategoryId(
  node: KbNode,
  subcategories: KbSubcategory[],
): number | null {
  const matches = subcategories.filter(
    (s) => s.category_id === node.category_id,
  );
  for (const s of matches) {
    if (!s.auto_match) continue;
    const { field, op, value } = s.auto_match as {
      field: string;
      op: string;
      value: string;
    };
    if (field === 'equip' && op === '=' && node.equip === value) {
      return s.id;
    }
    if (field === 'tags' && op === 'regex') {
      const re = new RegExp(value, 'i');
      if ((node.tags ?? []).some((t) => re.test(t))) return s.id;
    }
  }
  return null;
}

// ──────────────────────────────────────────────────────────────────
// Nodes — 18 artigos do Cowork
// ──────────────────────────────────────────────────────────────────

export const MOCK_NODES: KbNode[] = [
  {
    id: 1, business_id: 4, type: 'article', slug: 'kb-a1-calibrar-icc-roland',
    title: 'Calibrar perfil ICC na Roland VS-540 (VersaWorks)',
    excerpt: 'Passo a passo para gerar e aplicar perfil ICC novo em vinil Avery 900 com tinta Eco-Sol Max 2.',
    body_blocks: [
      { kind: 'para', t: 'Use este SOP toda vez que trocar lote de vinil ou tinta. O perfil antigo NÃO serve — desvio de ΔE pode ultrapassar 4, e o cliente rejeita.' },
      { kind: 'h2', t: 'Antes de começar' },
      { kind: 'list', items: ['Limpeza diária da Roland feita (artigo #kb-a3).', 'Bobina nova já carregada, ponta cortada reta.', 'Espectrofotômetro X-Rite i1Pro ligado e calibrado em branco.', 'VersaWorks 6 aberto, modo Color Management → Profile.'] },
      { kind: 'h2', t: '1. Imprimir o target' },
      { kind: 'list', items: ['Em VersaWorks: Color Management → Create Profile → Linearization Chart.', 'Configure tinta 4 cores, qualidade Alta (8 passos).', 'Imprima em vinil de teste 60×30cm. Aguarde 30 min de secagem antes de medir.'] },
      { kind: 'callout', tone: 'warn', t: 'Não meça com adesivo morno — o cromatismo escorrega. Espere os 30min.' },
      { kind: 'h2', t: '2. Medir com i1Pro' },
      { kind: 'list', items: ['Trace o target inteiro em 3 passagens.', 'Software vai gerar arquivo .icc — salve em VersaWorks/Profiles com nome AveryHP_EcoSolMax2_LOTE-2026-04.icc.', 'Aplique como perfil padrão para esse material.'] },
      { kind: 'callout', tone: 'ok', t: 'Boa prática: anote no campo Observações da OS qual ICC foi usado. Facilita rastrear retrabalho.' },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: true,
    category_id: 2, subcategory_id: 1, nivel: 'intermediario', equip: 'Roland VS-540',
    tags: ['ICC', 'VersaWorks', 'vinil', 'Eco-Sol Max 2'],
    reads_count: 142, helpful_count: 28, outdated_votes: 1, os_linked_count: 4,
    author_user_id: null, author_name: 'Mateus PCP', read_time_min: 6,
    last_verified_at: daysAgo(15), created_at: daysAgo(120), updated_at: daysAgo(3), deleted_at: null,
  },
  {
    id: 2, business_id: 4, type: 'article', slug: 'kb-a2-trocar-bobina-latex',
    title: 'Trocar bobina vinil HP Latex 365 sem desperdício',
    excerpt: 'Como engatar bobina nova com no máximo 30cm de perda. Inclui truque do alinhamento do tubo.',
    body_blocks: [
      { kind: 'para', t: 'A HP Latex tem aprendizado de tração automático, mas se você não respeitar o sentido do enrolamento, perde 80cm.' },
      { kind: 'h2', t: 'Passos' },
      { kind: 'list', items: ['Pause o job atual no painel. Não aborte — pause.', 'Recolha a bobina velha pelo botão Unload media.', 'Posicione a bobina nova com o adesivo de origem para CIMA — esse é o lado da face impressa.', 'Encaixe o tubo no eixo direito primeiro, depois esquerdo. Trave os flanges.', 'Auto-feed: a impressora puxa 25cm e mede largura sozinha.'] },
      { kind: 'callout', tone: 'info', t: "Se a impressora reclamar de 'media skew', cheque se o flange esquerdo está mesmo travado. 9 de 10 vezes é isso." },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 2, subcategory_id: 2, nivel: 'iniciante', equip: 'HP Latex 365',
    tags: ['bobina', 'vinil', 'troca', 'HP Latex'],
    reads_count: 89, helpful_count: 22, outdated_votes: 0, os_linked_count: 2,
    author_user_id: null, author_name: 'Carla Souza', read_time_min: 4,
    last_verified_at: daysAgo(30), created_at: daysAgo(60), updated_at: daysAgo(14), deleted_at: null,
  },
  {
    id: 3, business_id: 4, type: 'article', slug: 'kb-a3-limpeza-diaria-roland',
    title: 'Limpeza diária da cabeça de impressão — Roland VS-540',
    excerpt: 'Rotina de 8 minutos no início do turno. Faltar 2 dias seguidos entope a cabeça (R$ [redacted Tier 0] de reposição).',
    body_blocks: [
      { kind: 'para', t: 'Esta rotina é obrigatória no início de cada turno. Carla cobra na passagem de bastão.' },
      { kind: 'h2', t: 'Material' },
      { kind: 'list', items: ['Solução de limpeza Roland (frasco amarelo, nunca o azul).', 'Cotonete absorvente Eco-Sol — 6 unidades.', 'Luva nitrílica (nunca látex).'] },
      { kind: 'h2', t: 'Rotina' },
      { kind: 'list', items: ['Painel: Menu → Cleaning → Daily. A máquina move a cabeça.', 'Aplique 2 gotas no capping station.', 'Passe cotonete molhado nas laterais da cabeça — NUNCA na face de jato.', "Roda o teste de bicos. Se faltar bico, repete uma vez. Se faltar de novo: abre OS interna 'manutenção'."] },
      { kind: 'callout', tone: 'bad', t: 'Nunca, jamais, use álcool isopropílico. Resseca a junta da cabeça e fura o orçamento da assistência.' },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: true,
    category_id: 2, subcategory_id: 1, nivel: 'iniciante', equip: 'Roland VS-540',
    tags: ['limpeza', 'manutenção', 'cabeça impressão'],
    reads_count: 312, helpful_count: 71, outdated_votes: 0, os_linked_count: 0,
    author_user_id: null, author_name: 'Mateus PCP', read_time_min: 5,
    last_verified_at: daysAgo(30), created_at: daysAgo(180), updated_at: daysAgo(30), deleted_at: null,
  },
  {
    id: 4, business_id: 4, type: 'article', slug: 'kb-a4-sangria-banner',
    title: 'Sangria e safety zone em banner 3×2m',
    excerpt: 'Cliente manda PDF na medida exata. Como expandir 3cm de sangria por lado sem distorcer.',
    body_blocks: [
      { kind: 'para', t: 'Banner em lona 440g pede 3cm de sangria por lado. Em vinil de fachada, 2cm bastam. Confirme no brief antes de imprimir.' },
      { kind: 'h2', t: 'Quando o cliente manda PDF fechado' },
      { kind: 'list', items: ['Abra no Illustrator. Document Setup → Bleed: 30mm em todos os lados.', 'Selecione fundo. Se for sólido, basta esticar com Shift até a sangria.', 'Se for imagem, use Object → Artboard → Fit Artboard to Bleed e refaça o crop.', 'Salve como PDF/X-4 com perfil de saída do equipamento (artigo #kb-a1).'] },
      { kind: 'callout', tone: 'warn', t: 'Nunca redimensione PDF fechado em CorelDRAW — quebra fontes embutidas. Sempre Illustrator.' },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 3, subcategory_id: 9, nivel: 'intermediario', equip: '—',
    tags: ['sangria', 'PDF', 'Illustrator', 'banner'],
    reads_count: 76, helpful_count: 19, outdated_votes: 0, os_linked_count: 3,
    author_user_id: null, author_name: 'Joana Lima', read_time_min: 4,
    last_verified_at: daysAgo(20), created_at: daysAgo(90), updated_at: daysAgo(5), deleted_at: null,
  },
  {
    id: 5, business_id: 4, type: 'article', slug: 'kb-a5-fontes-pdf',
    title: 'Fontes fechadas em PDF do cliente — como verificar antes de imprimir',
    excerpt: 'Em 3 cliques no Acrobat você vê se as fontes estão embutidas. Falhar nisso retrabalho garantido.',
    body_blocks: [
      { kind: 'para', t: "Abra o PDF no Acrobat Pro → Ferramentas → Verificação preliminar (Preflight) → 'Listar todas as fontes'." },
      { kind: 'list', items: ['Verde = embutida, segue o jogo.', 'Amarelo = subset embutido, ok.', 'Vermelho = não embutida, PARA o pedido e volta pro cliente.'] },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 3, subcategory_id: 8, nivel: 'iniciante', equip: '—',
    tags: ['PDF', 'fontes', 'Acrobat'],
    reads_count: 58, helpful_count: 14, outdated_votes: 0, os_linked_count: 1,
    author_user_id: null, author_name: 'Bruna Vendas', read_time_min: 2,
    last_verified_at: daysAgo(45), created_at: daysAgo(180), updated_at: daysAgo(30), deleted_at: null,
  },
  {
    id: 6, business_id: 4, type: 'article', slug: 'kb-a6-plotter-graphtec',
    title: 'Plotter Graphtec FC9000 — recorte de adesivo recortado',
    excerpt: 'Pressão de lâmina, offset e ajuste de marca de registro para recorte preciso a 0,1mm.',
    body_blocks: [
      { kind: 'para', t: 'Adesivo recortado fino exige pressão baixa, offset bem ajustado e marca de registro impressa nítida. Erre um e perde a tiragem inteira.' },
      { kind: 'h2', t: 'Parâmetros padrão (vinil 80μ)' },
      { kind: 'list', items: ['Pressão: 80g · Velocidade: 30cm/s · Offset: 0.25', 'Lâmina CB09U (azul). Não use a vermelha (CB15U) em adesivo fino.', 'Marca de registro: 8mm preto pleno, 3 marcas em L.'] },
      { kind: 'h2', t: 'Calibração de offset' },
      { kind: 'list', items: ['Pressione TEST no painel → Cut Test.', 'Veja o quadradinho. Se canto não fechou: aumente offset 0.05.', 'Se canto passou: diminua 0.05. Repita até quadrado perfeito.'] },
      { kind: 'callout', tone: 'ok', t: 'Mateus já documentou os offsets de cada bobina nos painéis no aplicativo. Consulte antes de chutar.' },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: true,
    category_id: 1, subcategory_id: 6, nivel: 'intermediario', equip: 'Plotter Graphtec',
    tags: ['plotter', 'recorte', 'marca de registro', 'Graphtec'],
    reads_count: 134, helpful_count: 31, outdated_votes: 0, os_linked_count: 5,
    author_user_id: null, author_name: 'Felipe Acab.', read_time_min: 7,
    last_verified_at: daysAgo(7), created_at: daysAgo(150), updated_at: daysAgo(7), deleted_at: null,
  },
  {
    id: 7, business_id: 4, type: 'article', slug: 'kb-a7-brief-os',
    title: 'Como abrir OS com brief incompleto',
    excerpt: "Cliente quer 'só fazer logo grande' sem mandar arquivo. Use este checklist mínimo.",
    body_blocks: [
      { kind: 'para', t: 'Recusar pedido sem brief? Não. Mas você precisa GARANTIR 6 dados mínimos antes de mandar produzir.' },
      { kind: 'h2', t: 'Os 6 dados mínimos' },
      { kind: 'list', items: ['Material e medida final (lona 440? vinil adesivo? quantos cm?)', 'Quantidade exata.', 'Cor de fundo dominante (importa pro cálculo de tinta).', 'Acabamento (ilhós? laminação? recorte?)', "Prazo de retirada — combinado, não 'urgente'.", 'Arquivo OU referência (foto, link, qualquer coisa visual).'] },
      { kind: 'callout', tone: 'warn', t: 'Sem qualquer um desses 6, abra como ORÇAMENTO, não OS. OS só com brief fechado.' },
      { kind: 'h2', t: 'Atalho no ERP' },
      { kind: 'para', t: 'Tecle N na lista de OS pra abrir o form. O wizard exige os 6 campos. Se algo faltar, ele bloqueia o salvar.' },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: true,
    category_id: 4, subcategory_id: 10, nivel: 'iniciante', equip: '—',
    tags: ['brief', 'OS', 'atendimento', 'balcão'],
    reads_count: 203, helpful_count: 47, outdated_votes: 0, os_linked_count: 12,
    author_user_id: null, author_name: 'Larissa B.', read_time_min: 3,
    last_verified_at: daysAgo(2), created_at: daysAgo(200), updated_at: daysAgo(2), deleted_at: null,
  },
  {
    id: 8, business_id: 4, type: 'article', slug: 'kb-a8-fluxo-aprovacao-arte',
    title: 'Fluxo de aprovação de arte — quem aprova o quê',
    excerpt: "Versão 1 vai pra cliente. Aprovou? Vira versão final. Diferenças não-óbvias entre 'pré-prova' e 'arte final'.",
    body_blocks: [
      { kind: 'para', t: 'Toda OS de impressão tem 3 estágios de arte: prévia, prova e final. Cada uma exige aprovação diferente.' },
      { kind: 'list', items: ['Prévia (PDF baixa res, sem perfil ICC): aprovação do operador comercial.', 'Prova (impressa em A4 ou trecho real): aprovação do cliente — REGISTRE no thread da OS.', 'Final (arquivo pra produção, com perfil ICC do material): aprovação do PCP.'] },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 4, subcategory_id: 11, nivel: 'iniciante', equip: '—',
    tags: ['aprovação', 'arte', 'fluxo'],
    reads_count: 67, helpful_count: 12, outdated_votes: 0, os_linked_count: 4,
    author_user_id: null, author_name: 'Joana Lima', read_time_min: 4,
    last_verified_at: daysAgo(21), created_at: daysAgo(150), updated_at: daysAgo(21), deleted_at: null,
  },
  {
    id: 9, business_id: 4, type: 'article', slug: 'kb-a9-erros-sefaz',
    title: 'Erros frequentes SEFAZ na emissão de NF-e — códigos e correção',
    excerpt: 'Rejeição 539, 778, 692 e 539 são 80% dos casos. Como interpretar e o que ajustar no cadastro.',
    body_blocks: [
      { kind: 'para', t: 'Quando a SEFAZ rejeita, o ERP mostra código + mensagem. Mas mensagem em jargão fiscal não ajuda — esta tabela traduz.' },
      { kind: 'h2', t: 'Top 4' },
      { kind: 'list', items: ['539: Duplicidade de NF — você já emitiu essa série/número. Cheque o último número e incremente.', '692: Inscrição estadual do destinatário inválida. Consulte cliente no SINTEGRA, atualize o cadastro.', '778: CFOP inválido para a operação. Operação dentro do estado é 5102; fora, 6102.', '402: Origem da mercadoria inválida. Use 0 para nacional, 1 para estrangeira.'] },
      { kind: 'callout', tone: 'info', t: 'Antes de reemitir, INUTILIZE o número rejeitado. Senão fura a sequência e SEFAZ rejeita o próximo também.' },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 5, subcategory_id: 13, nivel: 'intermediario', equip: '—',
    tags: ['NF-e', 'SEFAZ', 'rejeição', 'fiscal'],
    reads_count: 91, helpful_count: 24, outdated_votes: 0, os_linked_count: 0,
    author_user_id: null, author_name: 'Eliana Fin.', read_time_min: 6,
    last_verified_at: daysAgo(5), created_at: daysAgo(90), updated_at: daysAgo(5), deleted_at: null,
  },
  {
    id: 10, business_id: 4, type: 'article', slug: 'kb-a10-boleto-inter',
    title: 'Boleto Inter — fluxo completo de emissão a baixa',
    excerpt: 'Da geração via API até a conciliação automática. O ERP faz quase tudo — você só precisa entender por quê.',
    body_blocks: [
      { kind: 'para', t: 'O Inter retorna o PDF do boleto + linha digitável em 2s. Salvamos junto da fatura no Financeiro.' },
      { kind: 'h2', t: 'Por que às vezes demora?' },
      { kind: 'list', items: ['Token de API expirou → re-autentique em Config → Inter.', 'Cliente sem CPF/CNPJ → boleto NÃO emite. Atualize o cadastro.', 'Valor abaixo de R$ [redacted Tier 0] → Inter rejeita. Use cobrança recorrente ou Pix.'] },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 5, subcategory_id: 14, nivel: 'iniciante', equip: '—',
    tags: ['boleto', 'Inter', 'financeiro', 'conciliação'],
    reads_count: 124, helpful_count: 33, outdated_votes: 0, os_linked_count: 8,
    author_user_id: null, author_name: 'Eliana Fin.', read_time_min: 5,
    last_verified_at: daysAgo(7), created_at: daysAgo(120), updated_at: daysAgo(7), deleted_at: null,
  },
  {
    id: 11, business_id: 4, type: 'article', slug: 'kb-a11-atalhos-erp',
    title: 'Atalhos de teclado essenciais do ERP',
    excerpt: '30 atalhos que economizam 40 min por dia de balcão. Imprimir e colar do lado do monitor.',
    body_blocks: [
      { kind: 'para', t: 'Larissa testou — sai do balcão 30min mais cedo decorando esses 30 atalhos.' },
      { kind: 'h2', t: 'Globais' },
      { kind: 'list', items: ['⌘K — Busca global em tudo (clientes, produtos, OS, KB).', '⌘\\ — Esconde/mostra sidebar.', '⌘⇧\\ — Esconde sidebar TOTAL (modo foco).', 'G + número — Vai pra módulo (G1 Chat, G2 Tarefas, G3 OS...).'] },
      { kind: 'h2', t: 'Listagens' },
      { kind: 'list', items: ['N — Nova OS / cliente / produto (depende da tela).', 'F — Filtros.', '/ — Foco na busca da lista.', 'J/K — Navega entre linhas (vi-style).'] },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: true,
    category_id: 6, subcategory_id: 15, nivel: 'iniciante', equip: '—',
    tags: ['atalhos', 'teclado', 'produtividade'],
    reads_count: 287, helpful_count: 64, outdated_votes: 0, os_linked_count: 0,
    author_user_id: null, author_name: 'Wagner R.', read_time_min: 3,
    last_verified_at: daysAgo(4), created_at: daysAgo(180), updated_at: daysAgo(4), deleted_at: null,
  },
  {
    id: 12, business_id: 4, type: 'article', slug: 'kb-a12-laminacao',
    title: 'Laminação fosca vs brilho — quando usar cada uma',
    excerpt: 'Cliente não sabe qual escolher. Você não pode chutar. Critério: ambiente, manuseio, fotorrealismo.',
    body_blocks: [
      { kind: 'list', items: ['Brilho: cor vibrante, mas reflete luz — ruim em ambiente com janela atrás. Bom em adesivo de carro.', 'Fosca: cor levemente apagada, sem reflexo. Bom em cardápio (mesa com vela acima), painel de loja, fachada.', 'Texturizada: mascara dedo gordo. Boa em cartão.'] },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 1, subcategory_id: 6, nivel: 'intermediario', equip: 'Laminadora Royal',
    tags: ['laminação', 'acabamento', 'fosca', 'brilho'],
    reads_count: 52, helpful_count: 11, outdated_votes: 0, os_linked_count: 2,
    author_user_id: null, author_name: 'Felipe Acab.', read_time_min: 4,
    last_verified_at: daysAgo(21), created_at: daysAgo(120), updated_at: daysAgo(21), deleted_at: null,
  },
  {
    id: 13, business_id: 4, type: 'article', slug: 'kb-a13-comunicacao-atraso',
    title: 'Comunicação cliente: avisar atraso sem queimar o relacionamento',
    excerpt: 'Roteiro testado para WhatsApp/telefone quando OS atrasa. 3 frases — não improvise.',
    body_blocks: [
      { kind: 'para', t: 'Atraso acontece. O problema é a omissão. Cliente perdoa atraso comunicado; não perdoa o silêncio.' },
      { kind: 'h2', t: 'Roteiro' },
      { kind: 'list', items: ["1. Reconheça antes de explicar: 'Olá [nome], aqui é da Oimpresso. Sua OS [n] teve um imprevisto.'", '2. Diga o novo prazo, NÃO o motivo (motivo só se perguntar).', '3. Ofereça algo concreto: entrega expressa, brinde, ou desconto.'] },
      { kind: 'callout', tone: 'bad', t: "NUNCA diga 'a máquina quebrou'. Cliente lê como 'eles não são confiáveis'. Diga 'estamos finalizando o acabamento'." },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 4, subcategory_id: 12, nivel: 'iniciante', equip: '—',
    tags: ['cliente', 'comunicação', 'atraso'],
    reads_count: 119, helpful_count: 38, outdated_votes: 0, os_linked_count: 0,
    author_user_id: null, author_name: 'Bruna Vendas', read_time_min: 3,
    last_verified_at: daysAgo(14), created_at: daysAgo(90), updated_at: daysAgo(14), deleted_at: null,
  },
  {
    id: 14, business_id: 4, type: 'article', slug: 'kb-a14-custos-os',
    title: 'Lançar custos diretos numa OS para fechar margem real',
    excerpt: 'Sem custo lançado, margem é fantasia. Como anexar bobina consumida, tinta e hora-máquina.',
    body_blocks: [
      { kind: 'list', items: ['Abrir a OS no detalhe → aba Custos.', 'Adicionar linha: insumo (vinil, tinta, ilhós), quantidade, custo unitário (vem do cadastro).', 'Adicionar hora-máquina pelo timer do PCP (ou manual em minutos).', 'Margem real recalcula sozinho no rodapé.'] },
      { kind: 'callout', tone: 'warn', t: "Este artigo está marcado como 'pode estar desatualizado' — campo de custo foi reformulado em maio." },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 6, subcategory_id: null, nivel: 'intermediario', equip: '—',
    tags: ['custo', 'margem', 'OS'],
    reads_count: 73, helpful_count: 17, outdated_votes: 1, os_linked_count: 0,
    author_user_id: null, author_name: 'Wagner R.', read_time_min: 5,
    last_verified_at: daysAgo(40), created_at: daysAgo(150), updated_at: daysAgo(30), deleted_at: null,
  },
  {
    id: 15, business_id: 4, type: 'article', slug: 'kb-a15-ponto-wr2',
    title: 'Bater ponto pelo Ponto WR2 — 3 jeitos',
    excerpt: 'Pelo ERP, pelo app celular ou pelo totem da entrada. Como funciona e quando cada um vale.',
    body_blocks: [
      { kind: 'list', items: ['Totem na entrada: válido sempre. Bate biometria.', 'ERP web: válido só se você estiver na faixa de IP da empresa (Wi-Fi interno).', 'App celular: válido com geo-fence de 200m do escritório.'] },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 7, subcategory_id: 16, nivel: 'iniciante', equip: '—',
    tags: ['ponto', 'WR2', 'RH'],
    reads_count: 198, helpful_count: 41, outdated_votes: 0, os_linked_count: 0,
    author_user_id: null, author_name: 'Carla Souza', read_time_min: 3,
    last_verified_at: daysAgo(30), created_at: daysAgo(180), updated_at: daysAgo(30), deleted_at: null,
  },
  {
    id: 16, business_id: 4, type: 'article', slug: 'kb-a16-nfse-rps',
    title: 'NFS-e — diferença entre RPS lote e envio síncrono',
    excerpt: 'Algumas prefeituras aceitam só lote (assíncrono). Como saber qual e configurar.',
    body_blocks: [
      { kind: 'callout', tone: 'warn', t: 'Conteúdo precisa de revisão — Eliana sinalizou que a integração de São Paulo mudou em abril.' },
      { kind: 'para', t: 'RPS é Recibo Provisório de Serviço — gera enquanto não há resposta da prefeitura.' },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'outdated', pinned: false,
    category_id: 5, subcategory_id: 13, nivel: 'avancado', equip: '—',
    tags: ['NFS-e', 'RPS', 'prefeitura'],
    reads_count: 34, helpful_count: 8, outdated_votes: 2, os_linked_count: 0,
    author_user_id: null, author_name: 'Eliana Fin.', read_time_min: 7,
    last_verified_at: daysAgo(120), created_at: daysAgo(240), updated_at: daysAgo(60), deleted_at: null,
  },
  {
    id: 17, business_id: 4, type: 'article', slug: 'kb-a17-romaneio',
    title: 'Romaneio de motoboy — o que conferir antes de liberar',
    excerpt: 'Endereço completo (não só CEP), peça contada, peso aproximado, hora máxima de entrega.',
    body_blocks: [
      { kind: 'list', items: ['Endereço completo: rua, número, complemento, bairro. CEP sozinho NÃO basta.', 'Quantidade conferida em voz alta com o motoboy.', 'Peso e volume (ele escolhe se vai de moto ou carro com isso).', 'Janela de horário do cliente (não a sua).'] },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'ok', pinned: false,
    category_id: 1, subcategory_id: 7, nivel: 'iniciante', equip: '—',
    tags: ['expedição', 'motoboy', 'romaneio'],
    reads_count: 56, helpful_count: 14, outdated_votes: 0, os_linked_count: 6,
    author_user_id: null, author_name: 'Felipe Acab.', read_time_min: 2,
    last_verified_at: daysAgo(7), created_at: daysAgo(120), updated_at: daysAgo(7), deleted_at: null,
  },
  {
    id: 18, business_id: 4, type: 'article', slug: 'kb-a18-multi-empresa',
    title: 'Trocar empresa ativa no ERP sem perder filtros',
    excerpt: "Você está em OI, troca pra WR e os filtros de tela ficam. Como deixar 'limpo' por padrão.",
    body_blocks: [
      { kind: 'callout', tone: 'bad', t: 'Conteúdo possivelmente obsoleto — 3 colegas votaram desatualizado. Quem souber, marque como revisado.' },
    ],
    source_doc_id: null, source_entity_type: null, source_entity_id: null,
    is_editable: true, status: 'outdated', pinned: false,
    category_id: 6, subcategory_id: null, nivel: 'avancado', equip: '—',
    tags: ['multi-empresa', 'filtros', 'config'],
    reads_count: 21, helpful_count: 4, outdated_votes: 3, os_linked_count: 0,
    author_user_id: null, author_name: 'Wagner R.', read_time_min: 4,
    last_verified_at: daysAgo(180), created_at: daysAgo(365), updated_at: daysAgo(180), deleted_at: null,
  },
];

// ──────────────────────────────────────────────────────────────────
// Trilhas (3) e Troubleshooters (3) — port do Cowork
// ──────────────────────────────────────────────────────────────────

export const MOCK_PATHS: KbPath[] = [
  {
    id: 1, business_id: 4, slug: 'onb-balcao', title: 'Onboarding do Balcão',
    audience: 'Larissa · primeiro mês',
    description: 'Domínio mínimo para atender no balcão da ROTA LIVRE sem supervisão.',
    hue: 145, status: 'published', author_user_id: null,
    steps: [
      { id: 1, business_id: 4, path_id: 1, node_id: 7, position: 1, step_type: 'leitura', note: 'Brief mínimo — base de tudo' },
      { id: 2, business_id: 4, path_id: 1, node_id: 11, position: 2, step_type: 'leitura', note: 'Atalhos de teclado essenciais' },
      { id: 3, business_id: 4, path_id: 1, node_id: 8, position: 3, step_type: 'leitura', note: 'Quando uma arte está pronta para produção' },
      { id: 4, business_id: 4, path_id: 1, node_id: 13, position: 4, step_type: 'pratica', note: 'Avisar atraso — pratique uma vez com colega' },
      { id: 5, business_id: 4, path_id: 1, node_id: 5, position: 5, step_type: 'leitura', note: 'Como conferir PDF fechado do cliente' },
      { id: 6, business_id: 4, path_id: 1, node_id: 14, position: 6, step_type: 'leitura', note: 'Fechar margem real lançando custos' },
    ],
  },
  {
    id: 2, business_id: 4, slug: 'manut-tecnico', title: 'Manutenção semanal — Técnico',
    audience: 'Mateus PCP · toda segunda',
    description: 'Rotinas de produção que evitam quebra de máquina (R$ [redacted Tier 0] reposição da cabeça Roland).',
    hue: 30, status: 'published', author_user_id: null,
    steps: [
      { id: 7, business_id: 4, path_id: 2, node_id: 3, position: 1, step_type: 'leitura', note: 'Limpeza diária da Roland — obrigatório' },
      { id: 8, business_id: 4, path_id: 2, node_id: 1, position: 2, step_type: 'leitura', note: 'Calibragem ICC quando trocar bobina' },
      { id: 9, business_id: 4, path_id: 2, node_id: 2, position: 3, step_type: 'leitura', note: 'Troca de bobina HP Latex sem desperdício' },
      { id: 10, business_id: 4, path_id: 2, node_id: 6, position: 4, step_type: 'leitura', note: 'Plotter Graphtec — pressão e offset' },
      { id: 11, business_id: 4, path_id: 2, node_id: 12, position: 5, step_type: 'leitura', note: 'Quando usar laminação fosca vs brilho' },
    ],
  },
  {
    id: 3, business_id: 4, slug: 'emerg-fiscal', title: 'Emergência fiscal',
    audience: 'Eliana Fin. · quando dá problema',
    description: 'Rejeição SEFAZ, boleto travado, NF que não emite — o que olhar primeiro.',
    hue: 60, status: 'published', author_user_id: null,
    steps: [
      { id: 12, business_id: 4, path_id: 3, node_id: 9, position: 1, step_type: 'leitura', note: 'Códigos de rejeição SEFAZ mais comuns' },
      { id: 13, business_id: 4, path_id: 3, node_id: 10, position: 2, step_type: 'leitura', note: 'Boleto Inter — fluxo e por que falha' },
      { id: 14, business_id: 4, path_id: 3, node_id: 16, position: 3, step_type: 'leitura', note: 'RPS lote vs síncrono (NFS-e)' },
    ],
  },
];

// Troubleshooter helper — formato achatado pro frontend
export interface MockTreeStepFlat {
  position: number;
  question: string;
  yes_next?: number;
  yes_fix?: string;
  no_next?: number;
  no_fix?: string;
}

export interface MockTroubleshooter extends Omit<KbDecisionTree, 'steps'> {
  flat_steps: MockTreeStepFlat[];
}

export const MOCK_TROUBLESHOOTERS: MockTroubleshooter[] = [
  {
    id: 1, business_id: 4, slug: 'tr-roland',
    title: 'Roland VS-540 não imprime', equip: 'Roland VS-540',
    when_to_use: 'máquina parou ou job não sai',
    hue: 280, status: 'published', root_step_id: null,
    flat_steps: [
      { position: 0, question: 'A impressora liga (LCD acende, motor faz ruído inicial)?', yes_next: 1, no_fix: "Cheque cabo de força e disjuntor da bancada. Se ok, abra OS interna 'manutenção elétrica'." },
      { position: 1, question: 'Reconhece a bobina (mostra largura no painel)?', yes_next: 2, no_fix: 'Recarregue: Unload + Load. Se persistir, limpe o sensor de mídia com pano seco — área branca à esquerda. Detalhe em #kb-a2.' },
      { position: 2, question: 'Os bicos (nozzle check) saem completos?', yes_next: 3, no_fix: "Faça a rotina de limpeza diária — passo a passo em #kb-a3. Se faltar bico após 2 limpezas, abra OS 'manutenção cabeça'." },
      { position: 3, question: 'O VersaWorks reconhece a impressora (status verde)?', yes_next: 4, no_fix: 'Cabo USB ou rede caiu. Reinicie o serviço Roland Print no Windows (services.msc → restart). Se persistir, troque o cabo USB.' },
      { position: 4, question: 'O job inicia mas para no meio?', yes_fix: 'Provavelmente perfil ICC errado pro material. Confira #kb-a1 e reaplique o perfil correto antes de re-enviar.', no_fix: 'Tudo certo na máquina. Re-envie o job e monitore o primeiro 1 metro. Se sair com cor estranha, confira #kb-a1.' },
    ],
  },
  {
    id: 2, business_id: 4, slug: 'tr-latex',
    title: 'HP Latex 365 — cor saindo errada', equip: 'HP Latex 365',
    when_to_use: 'cliente reclamou de cor, ΔE > 4',
    hue: 220, status: 'published', root_step_id: null,
    flat_steps: [
      { position: 0, question: 'A bobina é nova (trocada nas últimas 24h)?', yes_next: 1, no_fix: 'Antes de tudo, refaça calibragem ICC — bobinas envelhecem. Procedimento em #kb-a1.' },
      { position: 1, question: 'O ICC aplicado bate com o LOTE da bobina nova?', yes_next: 2, no_fix: 'Aplique o ICC correto. Cada lote tem um perfil — Mateus arquiva em VersaWorks/Profiles. Procedimento em #kb-a1.' },
      { position: 2, question: 'A laminação foi feita antes ou depois do problema aparecer?', yes_next: 3, no_fix: 'Imprima nova prova SEM laminação. Se cor ok, problema é o filme de laminação — troque lote.' },
      { position: 3, question: 'O ambiente da gráfica está com temperatura > 28°C?', yes_fix: 'Tinta Latex sofre acima de 28°C. Resfrie a sala (AC mínimo 22°C) e re-imprima após 30 min de estabilização.', no_fix: "Refaça calibragem ICC completa (#kb-a1). Se ΔE continuar acima de 4 após calibragem, abra OS 'assistência HP'." },
    ],
  },
  {
    id: 3, business_id: 4, slug: 'tr-nfe',
    title: 'NF-e rejeitada pela SEFAZ', equip: '—',
    when_to_use: 'código de rejeição apareceu',
    hue: 60, status: 'published', root_step_id: null,
    flat_steps: [
      { position: 0, question: 'A rejeição é código 539 (duplicidade)?', yes_fix: 'Você emitiu esse número antes. Vá em Fiscal → NF-e → consultar último número emitido. Use o próximo da sequência. Mais em #kb-a9.', no_next: 1 },
      { position: 1, question: 'É código 692 (Inscrição Estadual inválida)?', yes_fix: 'IE do destinatário está errada ou desativada. Consulte o cliente no SINTEGRA da UF dele e atualize o cadastro. Mais em #kb-a9.', no_next: 2 },
      { position: 2, question: 'É código 778 (CFOP inválido)?', yes_fix: 'Para operação dentro do estado use 5102. Fora do estado, 6102. Ajuste no produto ou na nota. Mais em #kb-a9.', no_next: 3 },
      { position: 3, question: 'É código 402 (origem da mercadoria)?', yes_fix: 'Cadastre origem 0 (nacional) ou 1 (estrangeira) no produto. Re-emita.', no_fix: 'Erro fora da lista frequente. Abra o XML da rejeição (Fiscal → Logs → última transmissão) e poste o código no #financeiro pra Eliana analisar. Antes de re-emitir: SEMPRE inutilize o número rejeitado pra não furar a sequência.' },
    ],
  },
];

// ──────────────────────────────────────────────────────────────────
// KPIs computados a partir dos nodes
// ──────────────────────────────────────────────────────────────────

export function computeMockKpis(nodes: KbNode[]): KbKpis {
  const totalReads = nodes.reduce((s, n) => s + n.reads_count, 0);
  const totalOsLinked = nodes.reduce((s, n) => s + n.os_linked_count, 0);
  const outdated = nodes.filter(
    (n) => n.status === 'outdated' || n.outdated_votes >= 2,
  ).length;
  const fresh14 = nodes.filter((n) => {
    if (!n.updated_at) return false;
    const days = (Date.now() - new Date(n.updated_at).getTime()) / 86_400_000;
    return days <= 14;
  }).length;
  const mostRead = [...nodes].sort((a, b) => b.reads_count - a.reads_count)[0];
  return {
    total: nodes.length,
    outdated,
    fresh_last_14d: fresh14,
    total_reads: totalReads,
    total_os_linked: totalOsLinked,
    most_read: mostRead
      ? { id: mostRead.id, title: mostRead.title, reads_count: mostRead.reads_count }
      : null,
    pinned_count: nodes.filter((n) => n.pinned).length,
    ultimo_sync: new Date().toISOString(),
  };
}

// ──────────────────────────────────────────────────────────────────
// Tags top-N
// ──────────────────────────────────────────────────────────────────

export function computeMockTagsTop(
  nodes: KbNode[],
  limit: number = 16,
): Array<{ tag: string; count: number }> {
  const m: Record<string, number> = {};
  nodes.forEach((n) => (n.tags ?? []).forEach((t) => (m[t] = (m[t] || 0) + 1)));
  return Object.entries(m)
    .sort((a, b) => b[1] - a[1])
    .slice(0, limit)
    .map(([tag, count]) => ({ tag, count }));
}
