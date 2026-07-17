export const meta = {
  name: 'validador-modulo-prototipo',
  description: 'Validador ADVERSARIAL de um MÓDULO inteiro ligado ao protótipo: inventaria charters+âncoras+casos+baselines, roda os DONOS já existentes scopados ao módulo, dirige o loop de protótipo LOCAL (ancora→render-proto-baseline→style-fingerprint --check hermético), e faz o passe de COERÊNCIA charter↔código (o eixo sem dono: cada Automation Anti-hook confrontado com Controller/Service, PROVA file:line exigida). Entrega = achados ranqueados que ATERRISSAM nos donos existentes (UC no casos.md sob casos-gate G-2, errata de charter, teste na allowlist do pest-lane, comando --compare local). ZERO gate/verde-vermelho próprio (anti-§5). Parametrizado por <Mod> via args.',
  whenToUse: 'Quando [W] pedir "valida o módulo X inteiro", "confere a estrutura de <Mod> ligada ao protótipo", "passe adversarial de <Mod>", "/validador-modulo <Mod>", OU antes de fechar uma onda de um módulo. Read-only (não commita). 1ª instância validada: Financeiro. Reusa idêntico pra ComVis/OficinaAuto com a própria allowlist+dict+âncoras.',
  phases: [
    { title: 'Inventário', detail: 'charters+âncoras+casos+baselines + donos existentes scopados ao módulo' },
    { title: 'Coerência', detail: 'skeptic por charter: cada anti-hook confrontado com código (prova file:line)' },
    { title: 'Fidelidade', detail: 'loop de protótipo LOCAL: --check hermético + comandos --gerar/--compare por tela ancorável' },
    { title: 'Síntese', detail: 'achados ranqueados aterrissando nos donos existentes — zero gate novo' },
  ],
}

const REPO = 'wagnerra23/oimpresso.com'
const MOD = (typeof args === 'string' ? args.trim() : (args && args.mod)) || 'Financeiro'

// CONTEXTO comum: as REGRAS DURAS que impedem o validador de virar teatro (§5 deste repo).
const CONTEXTO = `MÓDULO ALVO: ${MOD}. Working dir D:/oimpresso.com. READ-ONLY: NÃO edita/commita — o validador ACHA, o humano corrige nos donos.

LEIS DO REPO (violar = achado inválido):
- ANTI-§5 (memory/proibicoes.md "Ideias avaliadas e DESCARTADAS"): NÃO propor gate/catraca cuja métrica seja redundante com régua consolidada. Todo achado ATERRISSA num DONO EXISTENTE — nunca "criar gate novo". Antes de dizer "falta mecanismo", ache o dono (gates-registry.json, casos-gate, anchor-content-check, financeiro-pest, ui-architecture-gate).
- PRESENÇA ≠ CORREÇÃO (L-24): "artefato X existe/foi tocado" é teatro. Achado precisa de PROVA de comportamento (teste vermelho, caminho concreto de valor/cross-tenant), não de presença.
- PRECEDÊNCIA Tier 0 (memory/proibicoes.md): quando .tsx × charter × casos.md × SPEC discordam, a ordem é: teste-verde-citando-UC > casos.md > charter > SPEC. Corrigir o PERDEDOR no mesmo PR. O charter PODE estar errado e ainda é "lei de intenção" — anti-hook que contradiz código correto é instrução pra regressão (precedente Fiscal/Cockpit: anti-hook "cache só agregado" × código correto que cacheia por business = vazamento cross-tenant).
- ADR 0290 (render-diff pareado em CI = RECUSADO): fidelidade prod×proto roda LOCAL/dispatch. Em CI só o --check HERMÉTICO (schema+âncora+sha). NUNCA proponha comparar render em CI.

DESCUBRA o estado ATUAL (números de doc envelhecem):
- Charters: resources/js/Pages/${MOD}/**/*.charter.md (Glob). Âncora de CADA: node prototipo-ui/ancora.mjs "${MOD}/<Tela>" (resolve related_prototype; NUNCA no olho).
- casos.md por tela + manifesto scripts/casos-test-results.json (o G-7 do casos-coverage-guard morde status:unverified/lies/stale).
- proto-baselines: memory/requisitos/${MOD}/*.proto-baseline.json (node prototipo-ui/render-proto-baseline.mjs --check é hermético — pode rodar).
- Donos scopados: node scripts/governance/anchor-content-check.mjs (âncora podre/NO-SECTION); node scripts/casos-coverage-guard.mjs (trio+G-2+G-7); pest-lane do módulo em .github/workflows/<mod>-pest.yml (allowlist curada).`

// GUARD anti-stale (mesma disciplina do sdd-avaliador — base-freshness): medir contra origin/main.
const GUARD = `GUARD DE CHECKOUT (anti falso-positivo): antes de QUALQUER claim de medição, rode \`git fetch origin main --quiet && git rev-parse HEAD origin/main\`. Se HEAD != origin/main o cwd está STALE — leia via \`git show origin/main:<path>\` ou crie worktree limpa. Medição sem essa prova = achado inválido.`

const INVENTARIO_SCHEMA = {
  type: 'object',
  properties: {
    modulo: { type: 'string' },
    charters: { type: 'array', items: { type: 'object', properties: {
      tela: { type: 'string' },
      anchor: { type: 'string', description: 'related_prototype resolvido por ancora.mjs, ou "n/a"/"não-resolvível"' },
      anchor_veredito: { type: 'string', enum: ['OK', 'NO-SECTION', 'NO-MODULE', 'SHELL', 'MISSING', 'n/a-segue-DS', 'não-resolvível', 'compartilhado'] },
      tem_casos: { type: 'boolean' },
      tem_baseline: { type: 'boolean' },
      anti_hooks: { type: 'array', items: { type: 'string' }, description: 'Automation Anti-hooks + Non-Goals LITERAIS do charter (copiar, não parafrasear)' },
    }, required: ['tela', 'anchor', 'anchor_veredito', 'tem_casos', 'tem_baseline', 'anti_hooks'] } },
    donos_scopados: { type: 'array', items: { type: 'object', properties: {
      dono: { type: 'string' }, veredito: { type: 'string' }, evidencia: { type: 'string' },
    }, required: ['dono', 'veredito', 'evidencia'] } },
    telas_ancoraveis: { type: 'array', items: { type: 'string' }, description: 'telas cuja âncora .jsx bespoke RESOLVE (candidatas ao loop de fidelidade local)' },
  },
  required: ['modulo', 'charters', 'donos_scopados', 'telas_ancoraveis'],
}

const COERENCIA_SCHEMA = {
  type: 'object',
  properties: {
    tela: { type: 'string' },
    achados: { type: 'array', items: { type: 'object', properties: {
      anti_hook: { type: 'string', description: 'o anti-hook/Non-Goal LITERAL do charter' },
      contradiz: { type: 'boolean', description: 'existe código que o contradiz?' },
      prova_file_line: { type: 'string', description: 'arquivo:linha do código que contradiz (VAZIO se contradiz=false). SEM isso o achado é inválido.' },
      severidade: { type: 'string', enum: ['tier0-cross-tenant', 'tier0-valor-estoque', 'regressao', 'divergencia', 'nenhuma'] },
      resolve_por: { type: 'string', description: 'pela precedência Tier 0: quem vence (teste-verde/casos/charter/SPEC) e qual PERDEDOR corrigir' },
      aterrissa_no_dono: { type: 'string', description: 'correção concreta num DONO existente: "UC no <tela>.casos.md + Pest guard (casos-gate G-2)" / "errata do charter" / "teste X na allowlist <mod>-pest.yml". NUNCA "gate novo".' },
    }, required: ['anti_hook', 'contradiz', 'prova_file_line', 'severidade', 'resolve_por', 'aterrissa_no_dono'] } },
    cobertura_casos: { type: 'string', description: 'a tela tem casos.md? os anti-hooks viraram UC+guard? (gap de cobertura = trabalho sob o dono, não máquina nova)' },
  },
  required: ['tela', 'achados', 'cobertura_casos'],
}

const FIDELIDADE_SCHEMA = {
  type: 'object',
  properties: {
    check_hermetico: { type: 'string', description: 'saída de render-proto-baseline.mjs --check (rodado): baselines íntegros/stale/ausentes' },
    telas: { type: 'array', items: { type: 'object', properties: {
      tela: { type: 'string' },
      ancora_resolve: { type: 'boolean' },
      tem_baseline: { type: 'boolean' },
      comando_local: { type: 'string', description: 'o comando EXATO pra rodar o loop local: --gerar <Mod/Tela> [--route <id>] e/ou --compare. VAZIO se âncora não resolve (gap honesto).' },
      bloqueio: { type: 'string', description: 'por que não roda hoje (âncora prosa não-resolvível, sub-view sem rota, n/a-segue-DS), ou vazio' },
    }, required: ['tela', 'ancora_resolve', 'tem_baseline', 'comando_local', 'bloqueio'] } },
    fronteira_0290: { type: 'string', description: 'confirmar: compare = LOCAL; em CI só --check. Nada de render pareado.' },
  },
  required: ['check_hermetico', 'telas', 'fronteira_0290'],
}

// ── Fase 1 — Inventário (1 agente mapeador; deterministicamente descobre o módulo) ──
phase('Inventário')
log(`validando módulo ${MOD} — inventário de charters + âncoras + donos existentes`)
const inv = await agent(`Você INVENTARIA o módulo ${MOD} do oimpresso (Laravel+Inertia, multi-tenant). ${GUARD}

${CONTEXTO}

TAREFA: monte o inventário COMPLETO e VERIFICADO do módulo ${MOD}:
1. Glob resources/js/Pages/${MOD}/**/*.charter.md — TODAS as telas.
2. Pra cada charter: rode \`node prototipo-ui/ancora.mjs "${MOD}/<Tela>"\` e classifique a âncora (OK/NO-SECTION/n/a-segue-DS/não-resolvível/compartilhado). Copie os Automation Anti-hooks + Non-Goals LITERAIS (não parafraseie — o passe de coerência vai confrontá-los com código).
3. Marque tem_casos (<Tela>.casos.md existe ao lado) e tem_baseline (memory/requisitos/${MOD}/<tela>.proto-baseline.json existe).
4. Rode os DONOS scopados e capture veredito: anchor-content-check.mjs, casos-coverage-guard.mjs, e o pest-lane .github/workflows/${MOD.toLowerCase()}-pest.yml (allowlist).
5. telas_ancoraveis = as com âncora .jsx bespoke que RESOLVE (candidatas ao loop de fidelidade).

Retorne JSON no schema. NÃO opine sobre correção aqui — só INVENTARIE (a coerência é a próxima fase).`, { label: `inv:${MOD}`, phase: 'Inventário', schema: INVENTARIO_SCHEMA, model: 'opus' })

if (!inv || !inv.charters?.length) {
  log(`módulo ${MOD} sem charters resolvíveis — nada a validar`)
  return { modulo: MOD, erro: 'sem charters', inventario: inv }
}

// charters COM anti-hooks são os que o passe de coerência morde (sem anti-hook = nada a confrontar).
const comAntiHooks = inv.charters.filter(c => (c.anti_hooks || []).length > 0)
log(`${inv.charters.length} charters · ${comAntiHooks.length} com anti-hook (alvo do passe de coerência) · ${inv.telas_ancoraveis?.length || 0} ancoráveis`)

// ── Fase 2 — Coerência charter↔código (o eixo SEM DONO — o coração adversarial) ──
// pipeline por charter: cada skeptic tenta CONTRADIZER os anti-hooks com prova file:line.
phase('Coerência')
const coerencia = comAntiHooks.length
  ? (await parallel(comAntiHooks.map(c => () => agent(`Você é um CÉTICO adversarial de COERÊNCIA charter↔código na tela "${c.tela}" do módulo ${MOD}. ${GUARD}

${CONTEXTO}

OS ANTI-HOOKS/NON-GOALS DESTA TELA (do charter — confronte CADA um):
${(c.anti_hooks || []).map((h, i) => `  ${i + 1}. ${h}`).join('\n')}

MÉTODO (o vetor Fiscal/Cockpit: anti-hook "não cachear KPI por business" × CockpitController que cacheia fiscal:cockpit:kpis:biz:{id} = vazamento cross-tenant Tier 0):
1. Pra CADA anti-hook, procure no código do módulo (Modules/${MOD}/**, resources/js/Pages/${MOD}/**) um Controller/Service/componente que o CONTRADIZ.
2. contradiz=true SÓ com PROVA file:line concreta (o caminho de código que viola). Sem prova → contradiz=false (não invente; a maioria dos anti-hooks NÃO é contradita — é o caso saudável).
3. Se contradiz: resolva pela PRECEDÊNCIA Tier 0 (teste-verde-citando-UC > casos > charter > SPEC) — diga quem vence e qual PERDEDOR corrigir. Lembre: o charter pode estar ERRADO (anti-hook que contradiz código correto = corrigir o CHARTER).
4. aterrissa_no_dono: correção num DONO existente (UC+Pest guard sob casos-gate G-2 / errata de charter / teste na allowlist ${MOD.toLowerCase()}-pest). NUNCA "gate novo" (anti-§5). NÃO proponha máquina que leia a PROSA do anti-hook — a autoridade é o teste verde citando o UC.
5. cobertura_casos: a tela tem casos.md? os anti-hooks viraram UC+guard? (gap = trabalho sob o dono, não máquina nova.)

Retorne JSON no schema. Cético: a ausência de contradição é o resultado ESPERADO e honesto — não fabrique achado.`, { label: `coer:${c.tela}`, phase: 'Coerência', schema: COERENCIA_SCHEMA, model: 'opus' }))))
    .filter(Boolean)
  : []

// ── Fase 3 — Fidelidade (loop de protótipo LOCAL; --check hermético roda, compare é local) ──
phase('Fidelidade')
const fidelidade = await agent(`Você dirige o loop de FIDELIDADE de protótipo do módulo ${MOD} — dentro da fronteira ADR 0290 (compare = LOCAL, em CI só --check hermético). ${GUARD}

${CONTEXTO}

TELAS ANCORÁVEIS (âncora .jsx bespoke que resolve): ${JSON.stringify(inv.telas_ancoraveis || [])}

TAREFA:
1. Rode \`node prototipo-ui/render-proto-baseline.mjs --check\` (HERMÉTICO — schema+âncora+sha, pode rodar) e reporte o estado dos baselines de ${MOD}.
2. Pra CADA tela ancorável, dê o comando LOCAL exato pra rodar o loop: \`node prototipo-ui/render-proto-baseline.mjs --gerar ${MOD}/<Tela> [--route <id>]\` (quando várias telas compartilham 1 .jsx, a rota mira a seção) + o \`style-fingerprint.mjs --compare proto.json prod.json --tela ${MOD}/<Tela>\`. Se a âncora NÃO resolve (prosa, sub-view sem rota de topo), marque bloqueio (gap honesto) — NÃO invente.
3. fronteira_0290: confirme que o compare é LOCAL e que NADA disso vira gate de CI (render pareado foi RECUSADO).

Retorne JSON no schema. NÃO rode --gerar (precisa staging/browser — é dispatch local do humano); só o --check e a emissão dos comandos.`, { label: `fidelidade:${MOD}`, phase: 'Fidelidade', schema: FIDELIDADE_SCHEMA, model: 'opus' })

// ── Fase 4 — Síntese (achados ranqueados aterrissando nos donos; zero gate novo) ──
phase('Síntese')
const achadosReais = coerencia.flatMap(c => (c.achados || []).filter(a => a.contradiz && a.prova_file_line))
const sintese = await agent(`Sintetize o VALIDADOR do módulo ${MOD} num relatório executável pro Wagner. Zero gate/verde-vermelho próprio — todo achado aterrissa num DONO existente (anti-§5).

INVENTÁRIO: ${JSON.stringify(inv)}
COERÊNCIA (achados COM prova file:line): ${JSON.stringify(achadosReais)}
COBERTURA DE CASOS por tela: ${JSON.stringify(coerencia.map(c => ({ tela: c.tela, cobertura: c.cobertura_casos })))}
FIDELIDADE: ${JSON.stringify(fidelidade)}

Markdown enxuto (sem frontmatter), direto ao Wagner:
1) SAÚDE DO MÓDULO — tabela: nº charters · com âncora resolvível · com casos.md · com baseline · vereditos dos donos scopados.
2) ACHADOS DE COERÊNCIA charter↔código — só os COM prova file:line, ranqueados por severidade (tier0-cross-tenant > tier0-valor > regressão > divergência). Pra cada: anti-hook, prova, quem vence pela precedência, e a correção NO DONO. Se ZERO: diga "nenhuma contradição provada" (é o resultado saudável — não fabrique).
3) COBERTURA — telas sem casos.md cujos anti-hooks ainda não viraram UC+guard (trabalho sob casos-gate G-2, não máquina nova).
4) FIDELIDADE — --check hermético + os comandos LOCAIS por tela ancorável + os gaps honestos (âncora não-resolvível). Fronteira 0290 explícita.
5) PRÓXIMO PASSO — a fila de correções que ATERRISSAM nos donos, ranqueada. NENHUM "gate novo".`, { label: `sintese:${MOD}`, phase: 'Síntese' })

return {
  modulo: MOD,
  inventario: inv,
  coerencia,
  achados_com_prova: achadosReais,
  fidelidade,
  sintese,
}
