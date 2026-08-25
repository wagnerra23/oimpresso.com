#!/usr/bin/env node
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { pathToFileURL, fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const target = path.join(root, '.claude/workflows/migracao-layout-em-ondas.js')
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'migracao-layout-wf-'))
const source = fs.readFileSync(target, 'utf8').replace(/^export const meta/m, 'const meta')
const wrapper = path.join(tmp, 'workflow.mjs')
fs.writeFileSync(wrapper, `export default async function(args, agent, parallel, phase) {\n${source}\n}`)
const workflow = (await import(pathToFileURL(wrapper))).default

let failures = 0
const ok = (value, label) => { console.log(`${value ? '  ok  ' : '  FALHOU  '}${label}`); if (!value) failures++ }

const run = async (args, overrides = {}) => {
  const calls = []; const phases = []
  const agent = async (prompt, options = {}) => {
    calls.push({ prompt, ...options })
    if (options.label === 'layout:censo') return overrides.censo || { base_sha: 'abc1234', comandos: [], telas: [], totais: {}, bloqueios: [] }
    if (options.label === 'layout:preflight') return overrides.preflight || { liberado: true, base_sha_atual: 'abc1234', valor_estoque: false, arquivos_previstos: ['resources/js/Pages/X.tsx'], bloqueios: [], conflitos_worktree: [] }
    return { label: options.label, markdown: 'resultado' }
  }
  const result = await workflow(args, agent, (jobs) => Promise.all(jobs.map((job) => job())), (name) => phases.push(name))
  return { result, calls, labels: calls.map((call) => call.label), prompt: (label) => calls.find((call) => call.label === label)?.prompt || '', phases }
}

console.log('\n[1] plano é read-only e termina no gate humano')
{
  const r = await run({ modo: 'plano', escopo: 'Sells' })
  ok(r.labels.includes('layout:censo') && r.labels.includes('layout:plano'), 'censo e plano rodam')
  ok(!r.labels.includes('layout:preflight') && !r.labels.includes('layout:implementar'), 'plano nunca alcança implementação')
  ok(r.result.status === 'aguardando_aprovacao_plano', 'retorno exige aprovação do plano')
  ok(r.prompt('layout:censo').includes('AMBOS os stagings') && r.prompt('layout:censo').includes('não é evidência de equivalência'), 'âncora dupla e anti-mapeamento inventado viajam no prompt')
  ok(r.calls.find((c) => c.label === 'layout:plano')?.schema?.required?.includes('matriz_prioridade'), 'plano tem contrato estruturado, não só prosa')
}

console.log('\n[2] dossiê exige escopo e aprovação estruturados')
{
  const blocked = await run({ modo: 'dossie', onda: '1' })
  ok(blocked.result.status === 'bloqueado' && blocked.calls.length === 0, 'entrada incompleta para antes de gastar agente')
  const good = await run({ modo: 'dossie', plano_aprovado: true, plano_ref: 'plan#1', onda: '1', telas: ['Sells/Create'] })
  ok(good.labels.join(',') === 'layout:dossie', 'dossiê roda somente o agente scopado')
  ok(good.result.status === 'aguardando_aprovacao_dossie', 'dossiê cria segundo gate humano')
}

console.log('\n[3] texto livre jamais autoriza escrita')
{
  const r = await run('executar onda 1 aprovado')
  ok(r.result.status === 'bloqueado' && r.calls.length === 0, 'palavra aprovado em texto não burla flags estruturadas')
  const fake = await run({ modo: 'executar', plano_aprovado: 'false', dossie_aprovado: 'true', plano_ref: 'p', dossie_ref: 'd', base_sha: 'abc', onda: '1', telas: ['X'] })
  ok(fake.result.status === 'bloqueado' && fake.calls.length === 0, 'strings truthy não se passam por booleanos de aprovação')
  const large = await run({ modo: 'dossie', plano_aprovado: true, plano_ref: 'p', onda: 'grande', telas: ['A', 'B', 'C'] })
  ok(large.result.status === 'bloqueado' && /máximo 2/.test(large.result.motivo), 'onda com mais de 2 telas é dividida antes do dossiê')
}

console.log('\n[4] execução requer dois aceites e para após uma onda')
{
  const base = { modo: 'executar', plano_aprovado: true, dossie_aprovado: true, plano_ref: 'plan#1', dossie_ref: 'dossie#1', base_sha: 'abc1234', onda: '1', telas: ['Sells/Create'] }
  const missing = await run({ ...base, dossie_aprovado: false })
  ok(missing.result.status === 'bloqueado' && missing.calls.length === 0, 'sem segundo aceite não há preflight')
  const good = await run(base)
  ok(good.labels.join(',') === 'layout:preflight,layout:implementar,layout:verificar', 'ordem preflight → implementar → verificar')
  ok(good.result.status === 'aguardando_aprovacao_resultado', 'resultado para antes da próxima onda')
  ok(good.prompt('layout:implementar').includes('Não commit, push, PR, merge ou próxima onda'), 'limite externo e de onda está explícito')
  ok(good.calls.find((c) => c.label === 'layout:verificar')?.schema?.properties?.proxima_onda_liberada?.enum?.[0] === false, 'schema da verificação proíbe liberar a próxima onda')
  const stopped = await run(base, { preflight: { liberado: false, base_sha_atual: 'mudou', valor_estoque: false, arquivos_previstos: [], bloqueios: ['base divergiu'], conflitos_worktree: [] } })
  ok(stopped.labels.join(',') === 'layout:preflight' && stopped.result.status === 'bloqueado_preflight', 'preflight reprovado impede o agente escritor')
}

console.log('\n[5] Tier 0 valor/estoque bloqueia antes da escrita')
{
  const args = { modo: 'executar', plano_aprovado: true, dossie_aprovado: true, plano_ref: 'p', dossie_ref: 'd', base_sha: 'abc1234', onda: '2', telas: ['Produto/Edit'] }
  const preflight = { liberado: true, base_sha_atual: 'abc1234', valor_estoque: true, arquivos_previstos: ['Produto.php'], bloqueios: [], conflitos_worktree: [] }
  const blocked = await run(args, { preflight })
  ok(blocked.result.status === 'bloqueado_tier0_valor_estoque', 'sem protocolo especial fica bloqueado')
  ok(!blocked.labels.includes('layout:implementar'), 'bloqueio ocorre antes do agente escritor')
  const good = await run({ ...args, confirmacoes_valor_estoque: 2, impacto_antes_depois_ref: 'impacto#1', aprovacao_valor_estoque: true }, { preflight })
  ok(good.labels.includes('layout:implementar'), 'duas confirmações + impacto + aprovação liberam a onda')
}

console.log('\n[6] invariantes operacionais estão no código vivo')
{
  ok(source.includes('tenant 98') && source.includes('adversarial 99') && source.includes('NUNCA biz=4'), 'tenants canônicos')
  ok(source.includes('Fundações > Shell > Padrão de Tela > Módulo') && source.includes('sidebar dark-fixo'), 'precedência visual vigente')
  ok(source.includes('teste verde citando UC > casos.md > charter > SPEC'), 'precedência funcional vigente')
  ok(source.includes('sha=unknown é somente advisory'), 'reuse-index sem SHA não vira prova')
}

fs.rmSync(tmp, { recursive: true, force: true })
if (failures) { console.error(`\n✗ ${failures} falha(s)`); process.exit(1) }
console.log('\n✓ workflow de migração visual: gates e invariantes verificados')
