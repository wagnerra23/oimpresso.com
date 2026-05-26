// sefaz-actions.ts — Receitas guiadas por código cstat SEFAZ.
//
// DETERMINÍSTICO. SEM IA. Dicionário canônico de "como corrigir" pra
// cada cstat de rejeição comum. Port do fiscal-page.jsx §SEFAZ_ACTIONS.
//
// Cada receita tem:
//  - headline: 1 frase descrevendo a causa
//  - steps: 3-5 passos numerados pra corrigir (ordem que faria sentido)
//  - primary: ação principal sugerida (label + kind) → vira botão destacado
//  - secondary: ação alternativa opcional
//
// Quando cstat NÃO está mapeado aqui, UI mostra apenas hint genérico do
// SEFAZ_CODES (não invoca IA — separation of concerns Constituição UI v2).

export type ActionKind = 'primary' | 'danger' | 'warn' | 'ghost';

export interface SefazActionRecipe {
  headline: string;
  steps: string[];
  primary?: { label: string; kind: ActionKind };
  secondary?: { label: string; kind?: ActionKind };
}

export const SEFAZ_ACTIONS: Record<number, SefazActionRecipe> = {
  110: {
    headline: 'Destinatário com IE inválida ou suspensa na SEFAZ-Origem.',
    steps: [
      'Abrir cadastro do cliente em Contatos → conferir se a IE está digitada certo (sem pontos/traços).',
      'Consultar status da IE no portal SEFAZ do estado de origem (Sintegra).',
      'Se IE foi cancelada, marcar cliente como "Consumidor final" (sem IE) e re-emitir com CFOP de consumidor.',
      'Se IE está OK no Sintegra mas SEFAZ rejeita, abrir chamado SEFAZ — pode ser sincronização atrasada (>48h).',
    ],
    primary: { label: 'Editar cliente', kind: 'primary' },
    secondary: { label: 'Retransmitir' },
  },

  204: {
    headline: 'NF-e já foi autorizada com este número/série/CNPJ.',
    steps: [
      'Buscar a NF-e duplicada: abra Cockpit → filtro número.',
      'Confirmar se a autorizada anterior é a mesma operação (mesmo cliente + valor).',
      'Se sim: cancelar este lançamento (não re-emitir).',
      'Se for operação diferente: incrementar número da série e retransmitir.',
    ],
    primary: { label: 'Cancelar lançamento', kind: 'danger' },
    secondary: { label: 'Incrementar número e retransmitir' },
  },

  220: {
    headline: 'Erro de preenchimento numérico (valor unitário, quantidade ou total).',
    steps: [
      'Abrir aba "Itens" deste drawer e conferir cada linha.',
      'Validar valor unitário (vUnCom) > 0 e ≤ 9999999.99 com 2-4 decimais.',
      'Validar quantidade (qCom) > 0 com até 4 decimais.',
      'Validar Total = soma (qCom × vUnCom) para cada item.',
      'Corrigir o item incorreto na venda original e retransmitir.',
    ],
    primary: { label: 'Editar venda', kind: 'primary' },
  },

  539: {
    headline: 'CNPJ/CPF destinatário não confere com base SEFAZ.',
    steps: [
      'Abrir cadastro do cliente → validar CNPJ via lookup (CNPJ.ws ou Receita).',
      'Conferir se nome/razão social cadastrado bate com base SEFAZ.',
      'Se cliente é CPF, validar formato (11 dígitos sem pontuação).',
      'Re-emitir após correção.',
    ],
    primary: { label: 'Lookup CNPJ', kind: 'primary' },
    secondary: { label: 'Retransmitir' },
  },

  691: {
    headline: 'Item da NF-e com NCM, CFOP ou CST/CSOSN inválido.',
    steps: [
      'Abrir Itens deste drawer — identificar item com erro (geralmente último).',
      'Validar NCM (8 dígitos, ativo na TIPI — checar tabela vigente).',
      'Validar CFOP compatível com operação (5xxx interno / 6xxx interestadual / 7xxx exterior).',
      'Validar CST/CSOSN compatível com regime (CSOSN só Simples; CST para Lucro Real/Presumido).',
      'Corrigir cadastro do produto em Produtos e retransmitir.',
    ],
    primary: { label: 'Editar produto', kind: 'primary' },
  },

  778: {
    headline: 'XML não passou na validação schema PL_009 (estrutura inválida).',
    steps: [
      'Reconsultar SEFAZ pra capturar xMotivo completo.',
      'Procurar campo citado no xMotivo (ex: "tpAmb absent", "vNF incorreto").',
      'Verificar se cadastro do business está completo (cnpj, IE, UF, regime).',
      'Validar XML local com xsdValidator antes de retransmitir.',
      'Se persistir, abrir issue técnica anexando XML + xMotivo.',
    ],
    primary: { label: 'Reconsultar SEFAZ', kind: 'ghost' },
    secondary: { label: 'Validar XML local' },
  },

  217: {
    headline: 'NF-e não encontrada na base SEFAZ (provável transmissão que não chegou).',
    steps: [
      'Conferir se o ambiente está correto (homologação vs produção).',
      'Reconsultar SEFAZ depois de 10s — pode ser propagação.',
      'Se 217 persistir, retransmitir do zero (chave nova).',
    ],
    primary: { label: 'Reconsultar SEFAZ', kind: 'ghost' },
    secondary: { label: 'Retransmitir' },
  },

  301: {
    headline: 'CNPJ emitente está inscrito como INAPTO na Receita Federal.',
    steps: [
      'Acessar portal Receita Federal → consulta CNPJ.',
      'Verificar motivo da inaptidão (falta entrega declaração / pendência fiscal).',
      'Regularizar pendência com contador.',
      'Aguardar atualização SEFAZ (até 5 dias úteis após regularização).',
      'Não retransmitir até CNPJ voltar ATIVO.',
    ],
    primary: { label: 'Pausar emissão', kind: 'warn' },
  },
};

export function hasRecipe(code: number | string): boolean {
  const n = typeof code === 'number' ? code : parseInt(code, 10);
  return SEFAZ_ACTIONS[n] !== undefined;
}

export function getRecipe(code: number | string): SefazActionRecipe | null {
  const n = typeof code === 'number' ? code : parseInt(code, 10);
  return SEFAZ_ACTIONS[n] ?? null;
}
