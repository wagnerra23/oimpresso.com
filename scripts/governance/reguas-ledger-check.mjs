#!/usr/bin/env node
// @ts-check
/**
 * reguas-ledger-check.mjs — o ledger de réguas contradiz a si mesmo?
 *
 * ── POR QUE EXISTE (achado adversarial 2026-07-26, sobre a ADR 0353) ─────────
 * O ledger `memory/reguas/` é apresentado como "o ESTADO versionado do MEDIR". Um
 * passe adversarial mediu que ele contém, hoje, DOIS vereditos falsos na rodada
 * 2026-07-26:
 *
 *   1. `claims.json` → 24 de 24 `DIFERENCIAL_SISTEMA`
 *      `retratos.json[0].placar` → `refutado_tb: 1`  ← os dois discordam
 *
 *   2. `dtc-proveniencia-design-contrato` tem `refutador: ACIMA_CONFIRMADO` e
 *      `integracao: DIFERENCIAL_SISTEMA` — mas o workflow FILTRA
 *      `veredito !== 'ACIMA_CONFIRMADO'` ANTES da fase Integração. É um veredito
 *      para uma claim que nunca entrou na fase.
 *
 * ── POR QUE UM SCRIPT EXTERNO, E NÃO `fs.writeFileSync` NO WORKFLOW ──────────
 * A correção óbvia seria o workflow gravar o ledger determinísticamente a partir do
 * `placarJS` que ele já fecha em JS. **Não dá:** o runtime de Workflow não expõe
 * filesystem (`grep -c "require(\|writeFileSync" reguas-do-sistema.js` = 0, e a doc
 * do runtime declara "No filesystem or Node.js API access"). Por isso `persistir()`
 * é `agent(...)` — transcrição por LLM — e por isso a corrupção é possível.
 *
 * Então a defesa não pode ser na escrita; tem que ser na CONFERÊNCIA. Este script é
 * o par que faltava: o workflow escreve, isto verifica. E verifica o ARQUIVO, não o
 * prompt — o teste de CI existente (`reguas-workflow.test.mjs`) confere que a regra
 * está escrita no prompt (`p.includes('PROIBIDO recalcular')`), que é presence-gate
 * sobre texto e por construção não enxerga o que foi gravado.
 *
 * USO:
 *   node scripts/governance/reguas-ledger-check.mjs            relatório (exit 0)
 *   node scripts/governance/reguas-ledger-check.mjs --check    exit 1 se incoerente
 *   node scripts/governance/reguas-ledger-check.mjs --json     machine-readable
 *   node scripts/governance/reguas-ledger-check.mjs --selftest fixtures (morde/libera)
 *
 * Advisory ao nascer (ADR 0275). Node puro, sem deps.
 */
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const DIR = 'memory/reguas';

/** Vereditos do refutador que o workflow EXCLUI antes da fase Integração (linha 507). */
const NAO_VAI_PRA_INTEGRACAO = new Set(['ACIMA_CONFIRMADO']);

const arr = (x) => (Array.isArray(x) ? x : Object.values(x || {}));

/**
 * NÚCLEO PURO (testável, sem I/O). Recebe claims e retratos já parseados.
 * Devolve as violações — cada uma com o que esperava, o que achou, e por quê importa.
 */
export function conferir(claims, retratos) {
  const v = [];
  const cs = arr(claims);
  const rs = arr(retratos);

  // ── V1: o placar do retrato bate com as claims da mesma rodada? ────────────
  for (const r of rs) {
    const data = r?.data;
    const placar = r?.placar;
    if (!data || !placar) continue;
    const daRodada = cs.filter((c) => c?.data_veredito === data);
    if (!daRodada.length) continue;               // rodada sem claims no ledger — V3 cobre

    const conta = (val) => daRodada.filter((c) => c?.integracao === val).length;
    for (const campo of ['diferencial_sistema', 'refutado_tb']) {
      const esperado = Number(placar[campo] ?? 0);
      const achado = conta(campo.toUpperCase());
      if (esperado !== achado) {
        v.push({
          regra: 'V1',
          gravidade: 'alta',
          rodada: data,
          msg: `placar diz ${campo}=${esperado}, mas claims.json tem ${achado} com integracao=${campo.toUpperCase()}`,
          porque: 'os dois artefatos do MESMO ledger discordam sobre a MESMA rodada — um dos dois está errado, e o delta-scan lê claims.json pra decidir TTL (o erro se propaga)',
        });
      }
    }
  }

  // ── V2: claim que o código nunca mandou pra Integração tem veredito de Integração? ──
  for (const c of cs) {
    if (!c?.integracao) continue;
    if (!NAO_VAI_PRA_INTEGRACAO.has(String(c.refutador))) continue;
    v.push({
      regra: 'V2',
      gravidade: 'alta',
      claim: c.id,
      msg: `refutador=${c.refutador} mas integracao=${c.integracao}`,
      porque: 'o workflow filtra `veredito !== ACIMA_CONFIRMADO` ANTES da fase Integração — este veredito não pode ter sido produzido pelo código',
    });
  }

  // ── V3: rodada com retrato mas sem NENHUMA claim registrada ────────────────
  for (const r of rs) {
    const data = r?.data;
    if (!data || !r?.placar) continue;
    if (Number(r.placar.claims ?? 0) === 0) continue;
    if (!cs.some((c) => c?.data_veredito === data)) {
      v.push({
        regra: 'V3',
        gravidade: 'media',
        rodada: data,
        msg: `retrato declara ${r.placar.claims} claims, mas claims.json não tem NENHUMA com data_veredito=${data}`,
        porque: 'a nota do retrato não é recomputável do ledger — a proveniência morre com o journal do run',
      });
    }
  }

  return v;
}

function carregar(nome) {
  const p = join(DIR, `${nome}.json`);
  if (!existsSync(p)) return null;
  try { return JSON.parse(readFileSync(p, 'utf8')); } catch { return null; }
}

function main() {
  const args = new Set(process.argv.slice(2));
  const claims = carregar('claims');
  const retratos = carregar('retratos');

  // GUARDA ANTI-VÁCUO: sem os arquivos, isto não é "ledger íntegro" — é instrumento
  // sem fonte. Reportar 0 violações aqui seria falso verde (§5 2026-07-24).
  if (claims === null || retratos === null) {
    console.error('✗ ledger ausente ou ilegível em memory/reguas/ — NÃO é "sem violações".');
    console.error('  claims.json:', claims === null ? 'FALTA/inválido' : 'ok', '· retratos.json:', retratos === null ? 'FALTA/inválido' : 'ok');
    process.exit(2);
  }

  const v = conferir(claims, retratos);
  const nClaims = arr(claims).length, nRetratos = arr(retratos).length;

  if (args.has('--json')) {
    console.log(JSON.stringify({ gate: 'reguas-ledger', claims: nClaims, retratos: nRetratos, violacoes: v }, null, 2));
    process.exit(0);
  }

  console.log(`\n  LEDGER DE RÉGUAS — coerência interna · ${nClaims} claims · ${nRetratos} retratos · ${v.length} violação(ões)\n`);
  for (const x of v) {
    const alvo = x.claim ? `claim ${x.claim}` : `rodada ${x.rodada}`;
    console.error(`  🔴 [${x.regra}] ${alvo}: ${x.msg}`);
    console.error(`         └ ${x.porque}`);
  }
  if (!v.length) { console.log('  ✓ claims × placar coerentes; nenhum veredito impossível.\n'); process.exit(0); }

  console.error(`\n  O ledger é escrito por LLM (\`persistir()\` → \`agent(...)\`) porque o runtime de`);
  console.error(`  Workflow não expõe filesystem. Logo a fidelidade não pode ser garantida na escrita —`);
  console.error(`  só conferida aqui. Reconcilie contra o journal do run antes de confiar nas notas.\n`);
  process.exit(args.has('--check') ? 1 : 0);
}

// ── selftest: as fixtures são os DEFEITOS REAIS medidos em 2026-07-26 ────────
function selftest() {
  let f = 0;
  const ok = (c, m) => { console.log((c ? '  PASS ' : '  FAIL ') + m); if (!c) f++; };

  // RUIM 1 — o caso real: placar diz refutado_tb 1, claims tem 0
  const ruim1 = conferir(
    [{ id: 'a', data_veredito: '2026-07-26', refutador: 'REFUTADO', integracao: 'DIFERENCIAL_SISTEMA' }],
    [{ data: '2026-07-26', placar: { claims: 1, diferencial_sistema: 1, refutado_tb: 1 } }]);
  ok(ruim1.some((x) => x.regra === 'V1'), 'MORDE V1: placar refutado_tb=1 com claims 0 (o defeito real de 07-26)');

  // RUIM 2 — o caso real: ACIMA_CONFIRMADO com veredito de Integração
  const ruim2 = conferir(
    [{ id: 'dtc-proveniencia-design-contrato', data_veredito: '2026-07-26', refutador: 'ACIMA_CONFIRMADO', integracao: 'DIFERENCIAL_SISTEMA' }],
    [{ data: '2026-07-26', placar: { claims: 1, diferencial_sistema: 1, refutado_tb: 0 } }]);
  ok(ruim2.some((x) => x.regra === 'V2'), 'MORDE V2: ACIMA_CONFIRMADO com veredito de Integração (o código filtra antes)');

  // RUIM 3 — retrato sem claims
  ok(conferir([], [{ data: '2026-01-01', placar: { claims: 5, diferencial_sistema: 5, refutado_tb: 0 } }])
    .some((x) => x.regra === 'V3'), 'MORDE V3: retrato declara claims mas o ledger não tem nenhuma');

  // BOM — coerente
  const bom = conferir(
    [{ id: 'a', data_veredito: '2026-07-26', refutador: 'REFUTADO', integracao: 'DIFERENCIAL_SISTEMA' },
     { id: 'b', data_veredito: '2026-07-26', refutador: 'EMPATADO', integracao: 'REFUTADO_TB' }],
    [{ data: '2026-07-26', placar: { claims: 2, diferencial_sistema: 1, refutado_tb: 1 } }]);
  ok(bom.length === 0, 'LIBERA: placar bate com as claims');

  // BOM — ACIMA_CONFIRMADO SEM veredito de Integração é o esperado
  ok(conferir([{ id: 'c', data_veredito: '2026-07-26', refutador: 'ACIMA_CONFIRMADO' }],
    [{ data: '2026-07-26', placar: { claims: 1, diferencial_sistema: 0, refutado_tb: 0 } }]).length === 0,
    'LIBERA: ACIMA_CONFIRMADO sem integracao (o correto — foi filtrado)');

  // BOM — rodada antiga sem claims no ledger não vira V1 falso
  ok(!conferir([], [{ data: '2026-07-18', placar: { claims: 0, diferencial_sistema: 0, refutado_tb: 0 } }])
    .some((x) => x.regra === 'V1'), 'LIBERA: rodada com placar zerado não gera V1 falso');

  console.log(f ? `\n✗ selftest: ${f} falha(s)` : '\n✓ selftest: morde os 2 defeitos reais e libera o coerente');
  process.exit(f ? 1 : 0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) selftest();
  else main();
}
