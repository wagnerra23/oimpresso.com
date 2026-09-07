#!/usr/bin/env node
// Selftest de fluxo-sistema.mjs — MORDE nas funções puras (fixtures) e prova que o CLI roda.
// Rodar: node scripts/governance/fluxo-sistema.test.mjs  (ou --selftest no script)

import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { medirDeploys, medirPrs, medirRetrabalho, parseBrief, parseWipTeam, medirWip, medirAdocao, stripBom, NAO_MEDIDAS, formatBrief } from './fluxo-sistema.mjs';

let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };
const HERE = dirname(fileURLToPath(import.meta.url));

// ── deploys: frequência, CFR, recuperação ──
const D = (t, c) => ({ createdAt: `2026-09-0${t}T10:00:00Z`, conclusion: c });
const runs = [D(1, 'success'), D(1, 'failure'), { createdAt: '2026-09-01T10:30:00Z', conclusion: 'success' }, D(2, 'cancelled'), D(3, 'success')];
const d = medirDeploys(runs, 30);
check('deploys: conta total/ok/fail/outros', d.deploy_frequency.total === 5 && d.deploy_frequency.ok === 3 && d.deploy_frequency.fail === 1 && d.deploy_frequency.outros === 1);
check('deploys: dias com deploy', d.deploy_frequency.dias_com_deploy === 3);
check('cfr_pipeline: 1/5 = 20% → nível high', d.cfr_pipeline.value === 20 && d.cfr_pipeline.nivel_dora === 'high');
check('MORDE: recuperação = falha 10:00 → sucesso 10:30 = 30 min (elite)', d.recovery_pipeline.p50_min === 30 && d.recovery_pipeline.nivel_dora === 'elite');
check('deploys vazios → nao_medido, nunca 0%', medirDeploys([], 30).cfr_pipeline.status === 'nao_medido' && medirDeploys([], 30).cfr_pipeline.value === null);
const semRec = medirDeploys([D(1, 'failure')], 30);
check('MORDE: falha sem sucesso depois → recuperação nao_medido (não inventa)', semRec.recovery_pipeline.status === 'nao_medido');

// ── PRs: lead time + tamanho ──
const P = (h, add) => ({ createdAt: '2026-09-01T00:00:00Z', mergedAt: `2026-09-01T${String(h).padStart(2, '0')}:00:00Z`, additions: add, deletions: 0 });
const prs = medirPrs([P(1, 10), P(2, 500), P(5, 20)], { limite: 800 });
check('lead_time_pr: p50 2h → elite', prs.lead_time_pr.p50_h === 2 && prs.lead_time_pr.nivel_dora === 'elite');
check('pr_size: 1 de 3 acima de 300 = 33.3%', prs.pr_size_compliance.acima_300 === 1 && prs.pr_size_compliance.pct_acima_300 === 33.3);
check('MORDE: amostra no teto é declarada', medirPrs([P(1, 1), P(1, 1)], { limite: 2 }).lead_time_pr.amostra_no_teto === true);

// ── retrabalho ──
const rw = medirRetrabalho(['feat(x): a', 'fix(y): b', 'fix: c', 'hotfix: d', 'Revert "e"', 'docs: f']);
check('rework: fix+hotfix = 3/6 = 50%; hotfix/revert = 2', rw.rework_rate.pct_fix === 50 && rw.rework_rate.hotfix_ou_revert === 2);
check('rework: "prefix" nao conta como fix', medirRetrabalho(['prefix: x']).rework_rate.fix === 0);

// ── brief (formato do system prompt do BriefGeneratorService) ──
const brief = stripBom('﻿## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n1. wagner @ Forja — Triage — lista, 29d\n2. wagner @ Produto — [G-06] UI de BOM, 53d\n3. felipe @ Repair — OS abertas, 1d\n4. — @ Repair — teste, 32d\n11. +2 outros\n\n## DECISÕES RECENTES (24h)\n- Incidentes: 0\n\n## FLAGS\n- 🟠 US não atribuída: 680 (525 sem dono) — mais antiga: US-ACCO-001 (129d)\n');
const pb = parseBrief(brief);
check('parseBrief: 4 itens em voo (ignora "+N outros")', pb.emVoo.length === 4 && pb.emVoo[0].owner === 'wagner' && pb.emVoo[2].owner === 'felipe');
check('parseBrief: fila 680/525/129d', pb.fila && pb.fila.us_nao_atribuida === 680 && pb.fila.sem_dono === 525 && pb.fila.mais_antiga_dias === 129);
check('parseBrief: brief sem FLAGS → fila null (nao inventa)', parseBrief('## EM VOO AGORA\n1. a @ b — c, 1d\n').fila === null);

// ── WIP vs TEAM.md ──
const team = parseWipTeam('| Pessoa | WIP | Tasks |\n|---|---|---|\n| Wagner [W] | 2 | 4-6 |\n| Luiz [L] | 1 | 2 |\n| **TOTAL** | **8** | — |\n');
check('parseWipTeam: W=2, L=1, TOTAL ignorado', team.W && team.W.wip_max === 2 && team.L.wip_max === 1 && !team.TOTAL);
const w = medirWip(pb.emVoo, team);
check('MORDE: wagner 2 em voo vs WIP 2 → sem violação; e "—" sem limite não explode', w.violacoes.length === 0 && w.por_owner.wagner === 2);
const w2 = medirWip([...pb.emVoo, { owner: 'wagner' }], team);
check('MORDE: wagner 3 em voo vs WIP 2 → violação', w2.violacoes.length === 1 && w2.violacoes[0].em_voo === 3);

// ── adoção (route-hits) ──
const agora = Date.parse('2026-09-07T00:00:00Z');
const rh = { _meta: { gerado_em: '2026-09-01' }, janela_dias: 30, pages: { A: { hits: 5 }, B: { hits: 0 }, C: { hits: 2 } } };
const ad = medirAdocao(rh, 4, agora);
check('adocao: 2 de 4 telas servidas = 50%, ledger fresco', ad.adocao_telas.pct_servidas === 50 && ad.adocao_telas.status === 'measured');
check('MORDE: ledger mais velho que a janela → status stale (nao "measured")', medirAdocao({ ...rh, _meta: { gerado_em: '2026-06-01' } }, 4, agora).adocao_telas.status === 'stale');
check('adocao sem total de telas → nao_medido', medirAdocao(rh, null, agora).adocao_telas.status === 'nao_medido');

// ── as não-medidas declaram fonte futura (nunca 0) ──
for (const [k, v] of Object.entries(NAO_MEDIDAS)) check(`${k}: not_yet_measured com fonte_futura e value null`, v.status === 'not_yet_measured' && v.value === null && typeof v.fonte_futura === 'string');

// ── formatBrief não quebra com resultado parcial ──
const md = formatBrief({ _meta: { janela_dias: 30, medido_em: '2026-09-07T00:00:00Z' }, metricas: { ...d, ...NAO_MEDIDAS }, nao_medido: { prs: 'gh ausente' } });
check('formatBrief: tabela + linha de não medido + régua', /\| Frequência de deploy \|/.test(md) && /Não medido nesta execução: prs/.test(md) && /DORA State of DevOps 2024/.test(md));

// ── E2E: CLI roda sem MCP e sai 0 (mesmo sem gh — declara nao_medido) ──
const r = spawnSync(process.execPath, [join(HERE, 'fluxo-sistema.mjs'), '--json', '--no-mcp', '--days', '7'], { encoding: 'utf8', cwd: join(HERE, '..', '..') });
let js = null; try { js = JSON.parse(r.stdout); } catch { /* falha abaixo */ }
check('E2E: --json --no-mcp → exit 0 e JSON com _meta + metricas', r.status === 0 && js && js._meta && js.metricas && js.nao_medido.brief === 'pulado (--no-mcp)');
check('E2E: cada métrica tem status', js && Object.values(js.metricas).every((m) => typeof m.status === 'string'));

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — fluxo-sistema mede DORA+Flow por fixture, declara o que não mede, CLI roda.');
process.exit(fails ? 1 : 0);
