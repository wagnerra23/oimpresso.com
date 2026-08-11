#!/usr/bin/env node
/**
 * Selftest do workflow `.claude/workflows/reguas-do-sistema.js` — PERSISTÊNCIA INCREMENTAL.
 *
 * POR QUE EXISTE (defeito MEDIDO, não hipótese): a grade rodava como transação única de ~88
 * agentes com a fase `Persistir` só no FIM. As duas rodadas de 2026-07-25/26 morreram no meio
 * (interrupção; depois teto de uso com 27 de 88 agentes falhando) e nas DUAS o trabalho caro
 * (12 pesquisas web + 24 refutações + 15 integrações) sobreviveu só no journal do run:
 * `notas` null, `grade` null, `persistencia` null, `memory/reguas/retratos.json` intocado.
 * O conserto foi persistir por CHECKPOINT (claims após Integração · retrato+fraquezas após a
 * composição determinística, ANTES da prosa cara). Este selftest trava a ORDEM e a HONESTIDADE
 * do retrato — sem ele, uma edição futura reintroduz o defeito e nada avisa.
 *
 * COMO TESTA O CÓDIGO REAL: o arquivo do workflow é executado pelo tool Workflow dentro de uma
 * função async com os globals (`args`, `agent`, `parallel`, `phase`, `log`) injetados — por isso
 * ele tem `return`/`await` de topo e não é importável direto. Aqui o MESMO fonte é embrulhado
 * nessa função e rodado com um DUBLÊ de `agent()` que devolve payload no formato de cada schema.
 * ZERO agentes, zero rede, zero LLM — milissegundos. Não é fixture copiada: é o arquivo vivo.
 *
 * Advisory por desenho (ADR 0314/0275: required = só Tier-0).
 */
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const RAIZ = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const ALVO = path.join(RAIZ, '.claude/workflows/reguas-do-sistema.js')
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'reguas-wf-'))

const carregar = async (arquivo) => {
  const src = fs.readFileSync(arquivo, 'utf8').replace(/^export const meta/m, 'const meta')
  const f = path.join(TMP, `wf-${Math.random().toString(36).slice(2)}.mjs`)
  fs.writeFileSync(f, 'export default async function (args, agent, parallel, phase, log) {\n' + src + '\n}')
  return (await import(`file://${f}`)).default
}

let falhas = 0
const ok = (cond, msg) => { console.log(`${cond ? '  ok  ' : '  FALHOU  '}${msg}`); if (!cond) falhas++ }

// Dublê: responde por PREFIXO de label (o contrato que o workflow usa pra rotular cada agente).
const rodar = async (args, { verificarNulo = 0, persistOk = () => true, scan, evidencia = 'arq.mjs:12' } = {}) => {
  const chamadas = []; const logs = []
  let nVerif = 0
  const agent = async (prompt, opts = {}) => {
    const l = opts.label || ''
    chamadas.push({ label: l, prompt, model: opts.model, effort: opts.effort })
    if (l === 'delta-scan') return scan
    if (l.startsWith('p:')) {
      const key = l.slice(2)
      return { dimensao: key, lideres: [], roubar: [],
        oimpresso_acima: [{ ideia: `acima-${key}`, porque_acima: 'pq' }],
        oimpresso_atras: [`fraqueza-A-${key}`, `fraqueza-B-${key}`, `fraqueza-C-${key}`] }
    }
    if (l.startsWith('canary:') || l.startsWith('r:')) return { veredito: 'REFUTADO', razao: 'peer existe', quem_ja_faz: 'PeerCorp' }
    if (l.startsWith('i:')) return { veredito: 'DIFERENCIAL_SISTEMA', incremento: 'a cola', razao: 'r' }
    if (l.startsWith('v:')) { nVerif++; return nVerif <= verificarNulo ? null : { veredito: 'PARCIAL', evidencia, nota_sugerida: 6 } }
    if (l.startsWith('cp-') || l.startsWith('re-')) return { ok: persistOk(l), resumo: 'gravou' }
    return 'prosa qualquer'
  }
  const fn = await carregar(ALVO)
  const out = await fn(args, agent, (fns) => Promise.all(fns.map((f) => f())), () => {}, (m) => logs.push(String(m)))
  const labels = chamadas.map((c) => c.label)
  return { out, chamadas, logs, labels, prompt: (l) => (chamadas.find((c) => c.label === l) || {}).prompt || '' }
}
const pos = (labels, l) => labels.indexOf(l)
// `a` roda ANTES de `b` — exige os DOIS presentes. Sem essa guarda, `indexOf` devolve -1 pro
// ausente e a comparacao passa VAZIA (o teste diria "ok" num arquivo que nem tem checkpoint).
// Pego pelo proprio controle-negativo deste selftest rodado contra o arquivo pre-mudanca.
const ordem = (labels, a, b) => pos(labels, a) > -1 && pos(labels, b) > -1 && pos(labels, a) < pos(labels, b)

console.log('\n[1] full: o ledger recebe o que fechou ANTES da prosa cara')
{
  const r = await rodar({ base: '/base' })
  ok(pos(r.labels, 'cp-claims') > -1 && pos(r.labels, 'cp-retrato') > -1, 'os 2 checkpoints rodam')
  ok(ordem(r.labels, 'cp-claims', 'v:fraqueza-A-spec-governanca'), 'claims gravam ANTES da fase Verificar')
  ok(ordem(r.labels, 'cp-retrato', 'grade-final'), 'retrato grava ANTES da grade-final (morte da prosa nao perde a nota)')
  ok(!r.labels.some((l) => l.startsWith('re-')), 'checkpoint confirmado => 0 agentes de retentativa')
  ok(r.out.modo === 'full' && (r.out.cobertura || {}).completo === true, 'rodada inteira => modo full, cobertura completa')
  ok(r.chamadas.filter((c) => c.label.startsWith('cp-')).every((c) => c.effort === 'low'), 'checkpoints sao mecanicos (effort low / modelo barato)')
}

console.log('\n[2] retrato PARCIAL nunca se passa por completo (restricao dura)')
{
  const r = await rodar({ base: '/base' }, { verificarNulo: 5 })
  ok(r.out.modo === 'full-parcial' && (r.out.cobertura || {}).completo === false, 'perda de agentes => full-parcial')
  ok(/verificar 19\/24/.test((r.out.cobertura || {}).motivo_parcial || ''), `motivo_parcial nomeia a perda (${(r.out.cobertura || {}).motivo_parcial})`)
  ok(r.prompt('cp-retrato').includes('"completo":false'), 'o prompt do retrato leva cobertura.completo=false LITERAL')
  ok(r.prompt('cp-retrato').includes('modo: "full-parcial"'), 'o retrato e gravado como full-parcial')
}

console.log('\n[3] regras duras do ledger viajam no prompt de persistencia')
{
  const p = (await rodar({ base: '/base' })).prompt('cp-retrato')
  ok(p.includes('NUNCA edite retrato antigo (append-only)'), 'append-only')
  ok(p.includes('PRESERVE o valor de `indexado`'), 'indexado nunca rebaixa true->false')
  ok(p.includes('cobertura.assinatura'), 'idempotencia por assinatura (retentativa nao duplica retrato)')
  ok(p.includes('PROIBIDO recalcular'), 'regra 16: o agente transcreve, nao decide nota')
}

console.log('\n[4] retentativa: so gasta agente pelo que nao confirmou')
{
  const r = await rodar({ base: '/base' }, { persistOk: (l) => l !== 'cp-retrato' })
  ok(r.labels.includes('re-retrato') && !r.labels.includes('re-claims'), 'retenta so o pendente')
  ok(r.prompt('re-retrato').startsWith('RETENTATIVA'), 'a retentativa checa idempotencia antes de escrever')
  const r2 = await rodar({ base: '/base' }, { persistOk: () => false })
  ok(r2.labels.includes('re-retrato') && r2.labels.includes('re-claims'), 'os DOIS pendentes sao retentados')
}

console.log('\n[5] rodada menor: args.eixo e args.dimensoes')
{
  const r = await rodar({ base: '/base', eixo: 'rodar-e-observar' })
  ok(r.out.dimensoes === 4, `eixo resolve pras 4 dimensoes do eixo (${r.out.dimensoes})`)
  ok(r.out.modo === 'full-parcial' && (r.out.cobertura || {}).selecao === 'eixo', 'rodada de 1 eixo NUNCA vira retrato do sistema inteiro')
  ok(JSON.stringify((r.out.cobertura || {}).eixos_nao_medidos) === '["construir-e-governar","servir-o-negocio"]', 'declara os eixos NAO medidos')
  const inval = await rodar({ base: '/base', eixo: 'nao-existe' })
  ok(inval.logs.some((l) => l.includes('eixo sem match')) && inval.out.dimensoes === 12, 'eixo invalido loga e cai pro completo (nunca silencioso)')
  const dim = await rodar({ base: '/base', dimensoes: ['observabilidade-agente'] })
  ok(dim.out.dimensoes === 1, 'args.dimensoes (que ja existia) segue funcionando')
  const str = await rodar(JSON.stringify({ base: '/base', eixo: 'servir-o-negocio' }))
  ok(str.out.dimensoes === 1, 'args serializado em STRING pela fronteira do tool continua sendo parseado')
}

console.log('\n[6] delta: mesmos checkpoints, heartbeat intacto')
{
  const scan = {
    ultimo_retrato: { data: '2026-07-18', notas: { 'spec-governanca': 6.5, 'custo-eficiencia': 5 }, integ_hist: { runs: 8 } },
    dims_delta: { 'spec-governanca': { commits: 9 }, 'custo-eficiencia': { commits: 0 } },
    claims_vencidas: [{ id: 'c1', titulo: 'claim velha', dimensao: 'spec-governanca', refutador: 'EMPATADO' }],
    fraquezas: [{ id: 'f1', dimensao: 'spec-governanca', titulo: 'buraco', veredito: 'PARCIAL', nota: 4 }],
    delta_min_commits: 3,
  }
  const r = await rodar({ base: '/base', modo: 'delta' }, { scan })
  ok(ordem(r.labels, 'cp-retrato-delta', 'prosa-delta'), 'delta grava o retrato ANTES da prosa')
  ok(r.out.notas['spec-governanca'] === 6 && r.out.notas['custo-eficiencia'] === 5, 'nota re-medida vs herdada preservadas')
  ok((r.out.cobertura || {}).integracao_nao_rodada === true, 'delta declara que NAO rodou Integracao (integ_hist herdado)')
  // O que este caso testa e "0 commits + 0 claims => heartbeat". A ancora (`notas`) precisa ser
  // VALIDA aqui: com notas:{} quem dispara e o guard de ancora do bloco [8], por outro motivo, e
  // a assercao passaria medindo a coisa errada. (Ate 2026-08-08 o fixture tinha notas:{} por
  // acidente — o guard novo expos isso.)
  const vazio = await rodar({ base: '/base', modo: 'delta' }, { scan: { ultimo_retrato: { data: '2026-07-18', notas: { 'spec-governanca': 6.5 }, integ_hist: {} }, dims_delta: { 'spec-governanca': { commits: 0 } }, claims_vencidas: [], fraquezas: [], delta_min_commits: 3 } })
  ok(vazio.out.nada_a_medir === true && !vazio.labels.some((l) => l.startsWith('cp-')), 'nada a medir => 0 persistencia (heartbeat barato)')
}

console.log('\n[7] nao-regressao do que ja existia')
{
  const r = await rodar({ base: '/base' })
  ok(r.prompt('grade-final').includes('NÚMEROS JÁ FECHADOS PELA COMPOSIÇÃO DETERMINÍSTICA'), 'regra 16 intacta no prompt da grade')
  ok(r.chamadas.filter((c) => c.label.startsWith('canary:')).length === 2, 'canarios anti-Goodhart seguem rodando')
  ok(r.logs.some((l) => l.includes('anti-Goodhart')), 'disclosure do canario intacto')
  ok(r.chamadas.filter((c) => c.label.startsWith('v:')).length === 24, 'cap estratificado (24) da fase Verificar intacto')
  ok(r.logs.some((l) => l.includes('CORTE Verificar')), 'corte segue logado (No silent caps)')
  ok(r.prompt('dossie').includes('/base/memory/decisions/'), 'args.base continua chegando aos prompts')
}

// Defeito MEDIDO (run wf_32c91912-fca, 2026-08-08): `ultimo_retrato` era OPCIONAL no schema
// SCAN. O scanner o omitiu, a validacao passou, e a composicao — que itera as chaves de
// `ultimo_retrato.notas` — rodou ZERO vezes. Resultado: `notas:{}` gravado no topo do ledger
// DEPOIS de 39 agentes e 10,9M tokens. O caso `vazio` do bloco [6] NAO pegava isso: ele tem
// notas:{} mas tambem 0 commits e 0 claims, entao sai antes pelo `nada_a_medir`, por outro
// motivo. Aqui HA trabalho a fazer — e o abort tem que vir ANTES de gastar.
console.log('\n[8] delta sem ANCORA aborta antes de gastar — e nao grava retrato vazio')
{
  const comTrabalho = {
    dims_delta: { 'spec-governanca': { commits: 9 } },
    claims_vencidas: [{ id: 'c1', titulo: 'claim velha', dimensao: 'spec-governanca', refutador: 'EMPATADO' }],
    fraquezas: [{ id: 'f1', dimensao: 'spec-governanca', titulo: 'buraco', veredito: 'PARCIAL', nota: 4 }],
    delta_min_commits: 3,
  }
  // BITE 1 — a forma exata que aconteceu: o campo simplesmente nao veio.
  const ausente = await rodar({ base: '/base', modo: 'delta' }, { scan: { ...comTrabalho } })
  ok(/ncora ausente/.test(ausente.out.erro || ''), `ultimo_retrato ausente => aborta com erro nomeado (${ausente.out.erro})`)
  ok(ausente.out.acao === 'rodar full', 'diz o proximo passo (rodar full), nao so falha')
  ok(!ausente.labels.some((l) => l.startsWith('v:') || l.startsWith('r:')), 'ZERO agentes de Verificar/Refutar — aborta ANTES de gastar')
  ok(!ausente.labels.some((l) => l.startsWith('cp-') || l.startsWith('re-')), 'ZERO persistencia — retrato vazio nunca chega ao ledger')
  ok(!('notas' in ausente.out) || !Object.keys(ausente.out.notas || {}).length, 'nao devolve notas fabricadas')
  ok(ausente.logs.some((l) => l.includes('ABORTADO') && l.includes('SIL')), 'o log NOMEIA o silencio evitado (nao aborta mudo)')
  // BITE 2 — o caso que o `required` do schema NAO pega: presente porem vazio.
  const vazioComTrabalho = await rodar({ base: '/base', modo: 'delta' }, { scan: { ...comTrabalho, ultimo_retrato: { data: '2026-07-26', notas: {}, integ_hist: {} } } })
  ok(/ncora ausente/.test(vazioComTrabalho.out.erro || ''), 'notas:{} presente-porem-vazio tambem aborta (o schema sozinho nao pegaria)')
  // CONTROLE NEGATIVO 1 — ancora boa segue rodando (o guard nao virou bloqueio geral).
  const bom = await rodar({ base: '/base', modo: 'delta' }, { scan: { ...comTrabalho, ultimo_retrato: { data: '2026-07-26', notas: { 'spec-governanca': 6.5 }, integ_hist: { runs: 9 } } } })
  ok(!bom.out.erro && bom.out.notas['spec-governanca'] === 6, 'ancora presente => compoe normal (media determinista)')
  ok(bom.labels.some((l) => l === 'cp-retrato-delta'), 'ancora presente => o checkpoint do retrato roda')
  // CONTROLE NEGATIVO 2 — o heartbeat barato do bloco [6] nao virou erro.
  const nada = await rodar({ base: '/base', modo: 'delta' }, { scan: { ultimo_retrato: { data: '2026-07-26', notas: { 'spec-governanca': 6.5 } }, dims_delta: { 'spec-governanca': { commits: 0 } }, claims_vencidas: [], fraquezas: [], delta_min_commits: 3 } })
  ok(nada.out.nada_a_medir === true && !nada.out.erro, 'sem delta material segue heartbeat (nada_a_medir), nao erro')
  // O schema tem que EXIGIR a ancora — senao a proxima rodada repete o mesmo silencio.
  const src = fs.readFileSync(ALVO, 'utf8')
  ok(/required: \['dims_delta', 'claims_vencidas', 'fraquezas', 'ultimo_retrato'\]/.test(src), 'schema SCAN exige ultimo_retrato')
  ok(/required: \['data', 'notas'\]/.test(src), 'schema SCAN exige ultimo_retrato.notas')
}

// Defeito MEDIDO (rodada 2026-08-11, dimensao memoria-conhecimento): o proprio relatorio
// registrou que 5 das 8 fraquezas levantadas JA tinham maquina viva que a pesquisa nao achou.
// Nao foi azar — o dossie so lia decisions/ (mapa-dos-niveis curado a mao) + doutrina + §5, e
// 373 das 466 maquinas do inventario derivado NAO aparecem em nenhuma dessas fontes (80%,
// medido em 2026-08-11). Exemplo canonico: `hook-replay`/`hook-bites` estao no inventario
// (linhas ~389-390) e em NENHUMA fonte do dossie — logo os pesquisadores concluiam "o
// oimpresso nao mede isso" sobre coisa que ele mede. Mesmo padrao do 7/9 de 2026-07-09
// (SKILL.md regra 4). Sem estes asserts, uma edicao futura remove a fonte e nada avisa.
console.log('\n[9] o dossie carrega o inventario derivado (lista anti-falso-negativo)')
{
  const p = (await rodar({ base: '/base' })).prompt('dossie')
  ok(p.includes('/base/memory/reference/MAQUINAS-INVENTARIO.md'), 'inventario e FONTE OBRIGATORIA do dossie (com args.base resolvido)')
  ok(/ANTI-FALSO-NEGATIVO/i.test(p), 'o prompt nomeia PRA QUE serve (procurar antes de declarar ausencia)')
  ok(/N.O enumere o inventário inteiro/.test(p), "nao manda listar o inventario inteiro (estouraria o teto de 500 palavras)")
  ok(/ANTES de afirmar que o oimpresso n.o faz X/.test(p), 'o dossie repassa a instrucao aos pesquisadores (o dossie e embutido em COMUM)')
  // As 3 fontes antigas seguem no prompt — a fonte nova SOMA, nao substitui.
  ok(p.includes('mapa-dos-niveis') && p.includes('/base/memory/proibicoes.md') && p.includes('doutrina-documentacao'),
    'as 3 fontes originais continuam obrigatorias (fonte nova soma, nao troca)')
  // CONTROLE NEGATIVO — a mudanca e do PROMPT, nao da arquitetura: contagem de agentes intacta.
  // O esperado e DERIVADO do DIMS_DEFAULT vivo (nunca hardcoded — numero escrito a mao apodrece
  // no dia em que alguem adiciona uma dimensao, e o assert passaria a medir o passado).
  const nDims = [...fs.readFileSync(ALVO, 'utf8').matchAll(/^ {2}\{ key: '/gm)].length
  const r = await rodar({ base: '/base' })
  ok(nDims > 0 && r.chamadas.filter((c) => c.label.startsWith('p:')).length === nDims, `nenhuma fase nova: ${nDims} pesquisadores (1 por dimensao de DIMS_DEFAULT)`)
  ok(r.chamadas.filter((c) => c.label === 'dossie').length === 1, 'segue UM unico agente de dossie')
}

// Defeito MEDIDO (rodada 2026-08-11, modo full-parcial): a `evidencia` — REQUIRED no schema
// EXISTE, e o que torna a nota AUDITAVEL — era descartada na persistencia por 3 `.slice()` mudos
// (200 no full, 250 nos 2 sites do delta). As 8 entradas gravadas naquele dia saíram com 202-249
// chars, TODAS cortadas no meio da palavra. Nenhuma das 8 notas e auditavel pelo ledger.
console.log('\n[10] evidencia chega INTEGRA ao ledger (nao mais truncada em 200/250)')
{
  // 700 chars: acima dos 2 caps antigos (200/250) e abaixo do teto novo (2000) => tem que passar INTEIRA.
  const longa = 'INICIO-' + 'x'.repeat(680) + '-FIM-DA-PROVA'
  const r = await rodar({ base: '/base' }, { evidencia: longa })
  const p = r.prompt('cp-retrato')
  ok(p.includes(longa), `evidencia de ${longa.length} chars viaja INTEIRA ao persistidor (bite: com o slice(0,200) antigo, cai)`)
  ok(p.includes('-FIM-DA-PROVA'), 'o FINAL da evidencia sobrevive — era exatamente o que o corte comia')
  ok(!r.logs.some((l) => l.includes('EVIDÊNCIA CORTADA')), 'evidencia normal NAO dispara log de corte (o teto nao morde no corpus real)')
  // Delta: os outros 2 sites (250) — mesma prova.
  const scanD = {
    ultimo_retrato: { data: '2026-07-26', notas: { 'spec-governanca': 6.5 }, integ_hist: {} },
    dims_delta: { 'spec-governanca': { commits: 9 } }, claims_vencidas: [],
    fraquezas: [{ id: 'f1', dimensao: 'spec-governanca', titulo: 'buraco', veredito: 'PARCIAL', nota: 4 }],
    delta_min_commits: 3,
  }
  const d = await rodar({ base: '/base', modo: 'delta' }, { scan: scanD, evidencia: longa })
  ok(d.prompt('cp-retrato-delta').includes(longa), 'delta tambem persiste a evidencia inteira (site 2/3)')
  // CONTROLE NEGATIVO: patologica (>2000) e cortada, mas NUNCA em silencio.
  const gigante = 'y'.repeat(3000)
  const g = await rodar({ base: '/base' }, { evidencia: gigante })
  ok(g.logs.some((l) => l.includes('EVIDÊNCIA CORTADA')), 'evidencia patologica (3000) corta COM log (No silent caps)')
  ok(!g.prompt('cp-retrato').includes(gigante), 'e de fato corta — o teto de seguranca existe pra nao estourar o fit global')
}

console.log('\n[11] incremento: a justificativa do veredito de integracao chega ao ledger')
{
  const r = await rodar({ base: '/base' })
  const p = r.prompt('cp-claims')
  ok(/incremento/.test(p), 'o prompt de claims MANDA gravar incremento (medido: 0 de 51 claims tinham o campo)')
  ok(p.includes('a cola'), 'o valor produzido pela fase Integracao viaja no payload')
  ok(/OBRIGAT[ÓO]RIO quando vier nos dados/.test(p), 'a instrucao diz que e obrigatorio, nao opcional')
  ok(/delta n[ãa]o roda Integra/.test(p), 'e ressalva o delta (la o campo nao vem — preservar, nao apagar)')
}

// A nota e MEDIA sobre um conjunto: mudou o conjunto, o Δ nao mede capacidade (regra 12).
// Medido: memoria-conhecimento 7,7 (08-08, 2 fraquezas) -> 7,1 (08-11, 8), intersecao de ids VAZIA,
// e 13 fraquezas no ledger (media das 13 = 7,4). O retrato de 08-08 tinha o caveat em 4 dimensoes,
// mas escrito A MAO pelo agente; por isso o de 08-11 nao teve nenhum.
console.log('\n[12] caveat de denominador e DERIVADO (nao escrito a mao)')
{
  const r = await rodar({ base: '/base' })
  const p = r.prompt('cp-retrato')
  ok(/DENOMINADOR N[ÃA]O COMPAR[ÁA]VEL/.test(p), 'full: declara que o conjunto e re-levantado pela pesquisa do dia')
  ok(/regra 12/.test(p), 'o caveat cita a regra 12 (o corolario que ele protege)')
  ok(/"denominador"/.test(p), 'grava QUAIS fraquezas compuseram a nota (forward-only, pra comparacao futura)')

  // DELTA — bite: uma fraqueza que era nota:null ENTRA no denominador => caveat nomeando quem entrou.
  const base = { ultimo_retrato: { data: '2026-07-26', notas: { 'spec-governanca': 6.5 }, integ_hist: {} },
    dims_delta: { 'spec-governanca': { commits: 9 } }, claims_vencidas: [], delta_min_commits: 3 }
  const mudou = await rodar({ base: '/base', modo: 'delta' }, { scan: { ...base, fraquezas: [
    { id: 'f-antiga', dimensao: 'spec-governanca', titulo: 'ja tinha nota', veredito: 'PARCIAL', nota: 4 },
    { id: 'f-nova', dimensao: 'spec-governanca', titulo: 'era null', veredito: 'PARCIAL', nota: null },
  ] } })
  const pm = mudou.prompt('cp-retrato-delta')
  ok(/DENOMINADOR MUDOU/.test(pm), 'delta: conjunto diferente => caveat DERIVADO da comparacao de ids')
  ok(/entraram f-nova/.test(pm), 'o caveat NOMEIA quem entrou (formato do retrato 08-08, agora mecanico)')
  ok(mudou.logs.some((l) => l.includes('DENOMINADOR MUDOU')), 'e avisa no log da rodada, nao so no ledger')

  // CONTROLE NEGATIVO — conjunto IDENTICO nao pode disparar (senao vira carimbo, o anti-padrao do §5).
  const igual = await rodar({ base: '/base', modo: 'delta' }, { scan: { ...base, fraquezas: [
    { id: 'f-antiga', dimensao: 'spec-governanca', titulo: 'ja tinha nota', veredito: 'PARCIAL', nota: 4 },
  ] } })
  ok(!/DENOMINADOR MUDOU/.test(igual.prompt('cp-retrato-delta')), 'CONTROLE: mesmo conjunto => SEM caveat (o alarme discrimina, nao carimba)')
  ok(/re-medida \(1 fraquezas/.test(igual.prompt('cp-retrato-delta')), 'CONTROLE: a proveniencia normal segue intacta')
}

fs.rmSync(TMP, { recursive: true, force: true })
console.log(falhas ? `\nFALHOU: ${falhas} assercao(oes)` : '\nOK: selftest do workflow reguas-do-sistema passou')
process.exit(falhas ? 1 : 0)
