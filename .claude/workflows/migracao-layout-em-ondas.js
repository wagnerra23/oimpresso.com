export const meta = {
  name: 'migracao-layout-em-ondas',
  description: 'Orquestra a substituição visual das telas React/Inertia pelo layout Claude Design em três gates: plano mestre read-only, dossiê read-only de uma onda e execução de uma única onda aprovada. Reconcilia React × Blade × design sem inventar mapeamentos e preserva comportamento, permissões, estados, validações e multi-tenant.',
  whenToUse: 'Quando Wagner pedir para planejar ou executar a atualização de layout de várias telas React/Inertia usando Claude Design, preservando paridade com Blade. Use modo=plano primeiro; dossie e executar exigem aprovações e escopo estruturados.',
  phases: [
    { title: 'Validar entrada', detail: 'gates de modo, aprovações, onda e telas exatas' },
    { title: 'Censo', detail: 'inventário React × Blade × rota × controller × design, sem editar' },
    { title: 'Reconciliação', detail: 'paridade funcional, delta visual e governança em paralelo' },
    { title: 'Plano mestre', detail: 'ondas pequenas, matriz de prioridade e perguntas bloqueantes' },
    { title: 'Dossiê', detail: 'contrato executável de uma única onda, ainda sem editar' },
    { title: 'Executar', detail: 'uma onda aprovada, usando o processo MWART/aplicar-prototipo existente' },
    { title: 'Verificar', detail: 'paridade Blade, design, testes e relatório antes da próxima onda' },
  ],
}

const parseArgs = (value) => {
  if (!value) return { modo: 'plano' }
  if (typeof value === 'object') return { ...value, modo: String(value.modo || 'plano').toLowerCase() }
  const [modo, ...resto] = String(value).trim().split(/\s+/)
  return { modo: (modo || 'plano').toLowerCase(), escopo: resto.join(' '), entrada_livre: true }
}

const cfg = parseArgs(typeof args === 'undefined' ? null : args)
const alias = { planejar: 'plano', planejamento: 'plano', dossier: 'dossie', implementar: 'executar', aplicar: 'executar' }
const modo = alias[cfg.modo] || cfg.modo
const telas = Array.isArray(cfg.telas) ? cfg.telas.filter(Boolean) : []
const escopo = cfg.escopo || 'todas as telas React/Inertia alcançáveis pelo censo canônico'

const LEIS = `REPO: D:/oimpresso.com. PT-BR. Leia CLAUDE.md, memory/proibicoes.md, ADR 0093, ADR 0062, ADR 0277, memory/requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md, prototipo-ui/PROCESSO_MEMORIA_CC.md e os skills aplicar-prototipo, migracao-blade-react, mwart-process e mwart-comparative antes de concluir.
LEIS: (1) business_id é Tier 0; fila recebe businessId no construtor. (2) Precedência funcional: teste verde citando UC > casos.md > charter > SPEC. (3) Precedência visual: Fundações > Shell > Padrão de Tela > Módulo; sidebar dark-fixo é definitiva (UI-0023). (4) Testes usam tenant 98; adversarial 99; smoke manual biz=1; NUNCA biz=4. (5) PHP/Pest/PHPStan só CT 100. (6) Cálculo de VALOR/ESTOQUE exige duas confirmações, impacto antes→depois e aprovação humana. (7) Blade-only ou design-only sem Page React não pertence automaticamente ao escopo de substituir layout React. (8) Não invente arquivo, rota, equivalência, API, dado ou destino: marque AMBÍGUO, cite evidência e formule uma pergunta objetiva. (9) Reuse-index com sha=unknown é somente advisory; não prova ausência nem frescor. (10) Cada onda tem no máximo DUAS telas, conforme o backpressure do PROTOCOL.md §8. (11) Uma execução termina após UMA onda e aguarda aprovação; nunca inicia a seguinte.`

const CENSO_SCHEMA = {
  type: 'object',
  properties: {
    base_sha: { type: 'string' },
    comandos: { type: 'array', items: { type: 'string' } },
    telas: { type: 'array', items: { type: 'object', properties: {
      id: { type: 'string' }, react: { type: 'array', items: { type: 'string' } }, blade: { type: 'array', items: { type: 'string' } },
      design: { type: 'array', items: { type: 'string' } }, rota: { type: 'string' }, controller: { type: 'string' },
      classificacao: { type: 'string', enum: ['trio-confirmado', 'mapeamento-ambiguo', 'react-only', 'blade-only', 'design-only'] },
      evidencias: { type: 'array', items: { type: 'string' } }, duvidas: { type: 'array', items: { type: 'string' } },
    }, required: ['id', 'react', 'blade', 'design', 'rota', 'controller', 'classificacao', 'evidencias', 'duvidas'] } },
    totais: { type: 'object' }, bloqueios: { type: 'array', items: { type: 'string' } },
  },
  required: ['base_sha', 'comandos', 'telas', 'totais', 'bloqueios'],
}

const PREFLIGHT_SCHEMA = {
  type: 'object',
  properties: {
    liberado: { type: 'boolean' }, base_sha_atual: { type: 'string' }, valor_estoque: { type: 'boolean' },
    arquivos_previstos: { type: 'array', items: { type: 'string' } }, bloqueios: { type: 'array', items: { type: 'string' } },
    conflitos_worktree: { type: 'array', items: { type: 'string' } },
  },
  required: ['liberado', 'base_sha_atual', 'valor_estoque', 'arquivos_previstos', 'bloqueios', 'conflitos_worktree'],
}

const PLANO_SCHEMA = { type: 'object', properties: {
  documento: { type: 'string' }, telas: { type: 'array', items: { type: 'object' } }, ondas: { type: 'array', items: { type: 'object' } },
  matriz_prioridade: { type: 'array', items: { type: 'object' } }, perguntas_bloqueantes: { type: 'array', items: { type: 'string' } },
}, required: ['documento', 'telas', 'ondas', 'matriz_prioridade', 'perguntas_bloqueantes'] }

const DOSSIE_SCHEMA = { type: 'object', properties: {
  onda: { type: 'string' }, telas: { type: 'array', items: { type: 'string' } }, arquivos: { type: 'array', items: { type: 'string' } },
  tarefas: { type: 'array', items: { type: 'object' } }, paridade: { type: 'array', items: { type: 'object' } }, validacoes: { type: 'array', items: { type: 'string' } },
  criterio_pronto: { type: 'array', items: { type: 'string' } }, riscos: { type: 'array', items: { type: 'string' } }, perguntas_bloqueantes: { type: 'array', items: { type: 'string' } }, documento: { type: 'string' },
}, required: ['onda', 'telas', 'arquivos', 'tarefas', 'paridade', 'validacoes', 'criterio_pronto', 'riscos', 'perguntas_bloqueantes', 'documento'] }

const IMPLEMENTACAO_SCHEMA = { type: 'object', properties: {
  arquivos_tocados: { type: 'array', items: { type: 'string' } }, mudancas: { type: 'array', items: { type: 'string' } }, preservacoes: { type: 'array', items: { type: 'string' } },
  desvios: { type: 'array', items: { type: 'string' } }, validacoes: { type: 'array', items: { type: 'string' } },
}, required: ['arquivos_tocados', 'mudancas', 'preservacoes', 'desvios', 'validacoes'] }

const VERIFICACAO_SCHEMA = { type: 'object', properties: {
  veredito: { type: 'string', enum: ['APROVADO', 'REPROVADO'] }, evidencias: { type: 'array', items: { type: 'string' } }, regressoes: { type: 'array', items: { type: 'string' } },
  pendencias: { type: 'array', items: { type: 'string' } }, rollback: { type: 'array', items: { type: 'string' } }, proxima_onda_liberada: { type: 'boolean', enum: [false] },
}, required: ['veredito', 'evidencias', 'regressoes', 'pendencias', 'rollback', 'proxima_onda_liberada'] }

if (!['plano', 'dossie', 'executar'].includes(modo)) {
  return { status: 'bloqueado', motivo: `modo inválido: ${modo}`, uso: '{ modo: "plano" } | { modo: "dossie", plano_aprovado: true, plano_ref, onda, telas[] } | { modo: "executar", plano_aprovado: true, dossie_aprovado: true, plano_ref, dossie_ref, base_sha, onda, telas[] }' }
}

if (modo !== 'plano' && telas.length > 2) {
  return { status: 'bloqueado', motivo: 'uma onda aceita no máximo 2 telas, conforme o backpressure do PROTOCOL.md §8; divida o escopo para mantê-lo pequeno, reversível e verificável' }
}

if (modo === 'dossie' && (cfg.plano_aprovado !== true || !cfg.plano_ref || !cfg.onda || !telas.length)) {
  return { status: 'bloqueado', motivo: 'dossiê exige plano_aprovado=true, plano_ref, onda e telas[] exatas; entrada livre não vale como aprovação' }
}

if (modo === 'executar' && (cfg.entrada_livre || cfg.plano_aprovado !== true || cfg.dossie_aprovado !== true || !cfg.plano_ref || !cfg.dossie_ref || !cfg.base_sha || !cfg.onda || !telas.length)) {
  return { status: 'bloqueado', motivo: 'execução exige objeto estruturado com duas aprovações explícitas, plano_ref, dossie_ref, base_sha, uma onda e telas[] exatas' }
}

if (modo === 'plano') {
  phase('Censo')
  const censo = await agent(`Faça o CENSO MESTRE read-only para: ${escopo}. ${LEIS}

Não edite nenhum arquivo. Prove o inventário rodando e registrando outputs essenciais de: module-surface.mjs --migracao; blade-migration-census.mjs --json; screen-coverage-map.mjs; casos-coverage-guard.mjs --report; design-coverage.mjs. Resolva rota/controller/middleware pelos arquivos reais. Para cada candidato rode ancora.mjs com AMBOS os stagings (prototipo-ui/cowork e oimpresso-erp-conunica-o-visual/project); list_projects não prova ausência. Classifique cada linha do universo como trio-confirmado, ambíguo, React-only, Blade-only ou design-only. Um diretório/nome parecido não é evidência de equivalência.`, { label: 'layout:censo', phase: 'Censo', schema: CENSO_SCHEMA })

  if (!censo || !censo.telas) return { status: 'bloqueado', motivo: 'censo não retornou contrato estruturado', censo }

  phase('Reconciliação')
  const [funcional, visual, governanca] = await parallel([
    () => agent(`Reconcilie FUNCIONALMENTE cada trio confirmado do censo, read-only. ${LEIS}
CENSO: ${JSON.stringify(censo)}
Para cada tela registre rota/finalidade; campos e validações; ações; filtros; tabela/ordenação/paginação; modais/drawers; permissões; estados loading/empty/error/disabled; persistência/atalhos; comportamento só no Blade; já migrado no React; lacunas/regressões; dependências externas. Cite file:line ou comando por afirmação. Não force equivalência nos itens ambíguos.`, { label: 'layout:funcional', phase: 'Reconciliação' }),
    () => agent(`Reconcilie VISUALMENTE React atual × referência Claude Design, read-only. ${LEIS}
CENSO: ${JSON.stringify(censo)}
Resolva a referência pelo charter/ancora, nunca por screenshot audit-* nem pelo nome da pasta. Para cada tela separe: elementos visuais a substituir; componentes/padrões canônicos a reutilizar; peças realmente compartilháveis; mudanças que pedem backend/dados/API; risco de mudar comportamento. Caixa/Atendimento marcado como OURO é contrato: não repintar sem autorização explícita.`, { label: 'layout:visual', phase: 'Reconciliação' }),
    () => agent(`Faça a leitura de GOVERNANÇA E EXECUTABILIDADE, read-only. ${LEIS}
CENSO: ${JSON.stringify(censo)}
Confronte ADR 0277/roadmap, charters, casos, RUNBOOKs, contrato-de-tela, módulos silenciados, dependências compartilhadas e saúde das fontes. Identifique fundações que precisam ser serializadas antes das telas, perguntas que bloqueiam e uma proposta de ondas pequenas/reversíveis. Não crie gate novo: aterrise nos donos existentes.`, { label: 'layout:governanca', phase: 'Reconciliação' }),
  ])

  phase('Plano mestre')
  const plano = await agent(`Produza o PLANO MESTRE solicitado, sem editar arquivos. ${LEIS}
CENSO: ${JSON.stringify(censo)}
PARIDADE FUNCIONAL: ${JSON.stringify(funcional)}
DELTA VISUAL: ${JSON.stringify(visual)}
GOVERNANÇA: ${JSON.stringify(governanca)}

Entregue documento Markdown completo contendo, para CADA tela: fonte React, Blade correspondente, referência visual, rota/controller/dependências, inventário de paridade, diferenças, estrutura-alvo, compartilhados, aceite e validação. Depois proponha ondas pequenas com objetivo, arquivos exatos, pré-requisitos, tarefas atômicas, legado preservado, validações, DoD, riscos e decisões [W]. Termine com matriz tela × impacto × regressão × esforço × dependências × onda. Separe perguntas BLOQUEANTES de dívidas não bloqueantes. Se houver mapeamento inseguro, não o complete: pergunte. Além do documento, preencha todos os campos estruturados do schema.`, { label: 'layout:plano', phase: 'Plano mestre', schema: PLANO_SCHEMA })

  return { status: 'aguardando_aprovacao_plano', modo, censo, funcional, visual, governanca, plano, proximo: 'Aprovar o plano e chamar modo=dossie com plano_ref, onda e telas[] exatas.' }
}

if (modo === 'dossie') {
  phase('Dossiê')
  const dossie = await agent(`Gere o DOSSIÊ EXECUTÁVEL read-only de UMA onda. ${LEIS}
PLANO APROVADO: ${cfg.plano_ref}. ONDA: ${cfg.onda}. TELAS EXATAS: ${JSON.stringify(telas)}. ESCOPO: ${escopo}.

Revalide o base SHA e rode os donos scopados por tela: screen-coverage-map --screen, casos-coverage-guard, ancora.mjs com os dois stagings e reuse-index para intenção de componentes. Leia React, Blade, controller/FormRequest/policies, charter/casos/RUNBOOK/visual-comparison e design resolvido. Entregue: arquivos exatos; contrato de props/dados; matriz item-a-item de paridade; estados; permissões; dependências; tarefas atômicas em ordem; testes e evidências; rollback; DoD; riscos; perguntas objetivas. Marque qualquer mudança de backend/API como subonda separada. Não edite.`, { label: 'layout:dossie', phase: 'Dossiê', schema: DOSSIE_SCHEMA })
  return { status: 'aguardando_aprovacao_dossie', modo, onda: cfg.onda, telas, dossie, proximo: 'Aprovar o dossiê e chamar modo=executar com as duas aprovações, refs, base_sha e o mesmo escopo exato.' }
}

phase('Validar entrada')
const preflight = await agent(`Faça o PRE-FLIGHT read-only da execução. ${LEIS}
PLANO: ${cfg.plano_ref}. DOSSIÊ: ${cfg.dossie_ref}. BASE APROVADA: ${cfg.base_sha}. ONDA: ${cfg.onda}. TELAS: ${JSON.stringify(telas)}.
Prove SHA atual, que o dossiê cobre exatamente estas telas, que não há pergunta bloqueante, que referências/rotas existem, e que alterações já presentes no worktree não colidem. Classifique se qualquer arquivo/caminho toca cálculo de valor ou estoque. Liste arquivos previstos. liberado=false diante de qualquer dúvida, drift de base ou conflito.`, { label: 'layout:preflight', phase: 'Validar entrada', schema: PREFLIGHT_SCHEMA })

if (!preflight?.liberado) return { status: 'bloqueado_preflight', modo, onda: cfg.onda, telas, preflight }
if (preflight.valor_estoque && (Number(cfg.confirmacoes_valor_estoque) !== 2 || !cfg.impacto_antes_depois_ref || cfg.aprovacao_valor_estoque !== true)) {
  return { status: 'bloqueado_tier0_valor_estoque', motivo: 'faltam duas confirmações + impacto antes→depois + aprovação humana', preflight }
}

phase('Executar')
const implementacao = await agent(`Implemente SOMENTE a onda aprovada. ${LEIS}
PLANO: ${cfg.plano_ref}. DOSSIÊ: ${cfg.dossie_ref}. ONDA: ${cfg.onda}. TELAS: ${JSON.stringify(telas)}. ARQUIVOS PREVISTOS: ${JSON.stringify(preflight.arquivos_previstos)}.
Leia e siga integralmente .claude/skills/aplicar-prototipo/SKILL.md e seu RUNBOOK, migracao-blade-react, mwart-process, mwart-comparative, charter-first, constituicao-ui-aware e preflight-modulo. Preserve o contrato Blade; mude backend só se o dossiê o autorizou explicitamente; não copie patch/protótipo em massa; serialize componente compartilhado/baseline antes das telas dependentes. Use apply_patch, mantenha o diff pequeno/reversível e não toque alterações alheias. Não commit, push, PR, merge ou próxima onda. Retorne arquivos tocados, mudanças, preservações, desvios e comandos de validação executados.`, { label: 'layout:implementar', phase: 'Executar', schema: IMPLEMENTACAO_SCHEMA })

phase('Verificar')
const verificacao = await agent(`Audite read-only o RESULTADO da única onda; não conserte e não avance. ${LEIS}
PLANO: ${cfg.plano_ref}. DOSSIÊ: ${cfg.dossie_ref}. ONDA: ${cfg.onda}. TELAS: ${JSON.stringify(telas)}. PRE-FLIGHT: ${JSON.stringify(preflight)}. IMPLEMENTAÇÃO: ${JSON.stringify(implementacao)}.
Inspecione o diff real. Refaça a matriz Blade×React item a item; confira permissões, validações, estados, rotas, props, business_id e ausência de mudança de negócio. Rode gates locais cabíveis (ds-guard, integrity/anchor, layout-primitives, casos, lint/tsc/build); encaminhe PHP/Pest/PHPStan ao CT 100. Valide design apenas contra a âncora correta, em 1280/1440 light+dark quando executável. Devolva APROVADO ou REPROVADO, evidências, regressões, pendências e rollback. Nunca declare concluído só por semelhança visual. proxima_onda_liberada deve ser sempre false: só Wagner libera em uma nova chamada.`, { label: 'layout:verificar', phase: 'Verificar', schema: VERIFICACAO_SCHEMA })

return { status: 'aguardando_aprovacao_resultado', modo, onda: cfg.onda, telas, preflight, implementacao, verificacao, proximo: 'Wagner revisa este resultado. A próxima onda exige nova chamada e nova aprovação.' }
