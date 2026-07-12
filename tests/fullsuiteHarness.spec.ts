// Contract test do harness do nightly full-suite (US-GOV-018 + US-GOV-020).
//
// CONTRATO (âncora externa — NÃO derivado do código · proibicoes §5 "teste tautológico"):
//   - SPEC memory/requisitos/Governance/SPEC.md — US-GOV-018 DoD A ("mariadb-client na
//     imagem + apk fallback + ssl-verify-server-cert=0"; "A.2 FULLSUITE_FK_OFF REVERTIDO
//     — net-harmful") e DoD B ("ALTER config_json pra TEXT/LONGTEXT alinhado ao cast
//     encrypted:array, migration idempotente + down()").
//   - SPEC US-GOV-020 Fix ("SET GLOBAL log_bin_trust_function_creators=1 + GRANT
//     SET_USER_ID"; re-land #2728, provado 188→377 tabelas / 0→4 triggers no CT100).
//   - FV-F1 (plano SDD 2026-06-12): --log-junit SEMPRE presente no run do pest.
//
// Por que existe: esses fixes foram PROVADOS por reprodução no CT100 (2026-06-13/14) e
// vivem num shell script sem cobertura Pest — sumir um deles num refactor seria regressão
// silenciosa que só a nightly (horas depois, máquina externa) pegaria. Este spec trava o
// contrato no CI barato. Não prova que o harness FUNCIONA (isso é a nightly); prova que
// os artefatos provados não desapareceram do canônico.
//
// @covers-us US-GOV-018
// @covers-us US-GOV-020
// @covers-us US-GOV-045

import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(__dirname, '..');
const harness = readFileSync(resolve(ROOT, 'scripts/tests/ct100-fullsuite.sh'), 'utf8');
const junitSummary = readFileSync(resolve(ROOT, 'scripts/tests/junit-summary.mjs'), 'utf8');
const floorCompute = readFileSync(resolve(ROOT, 'scripts/tests/floor-compute.mjs'), 'utf8');
const nightlyDiff = readFileSync(resolve(ROOT, 'scripts/tests/nightly-diff.mjs'), 'utf8');
const migration = readFileSync(
  resolve(
    ROOT,
    'Modules/PaymentGateway/Database/Migrations/2026_06_13_080000_alter_payment_gateway_credentials_config_json_to_longtext.php'
  ),
  'utf8'
);

describe('US-GOV-018 Frente A — harness do nightly (DoD A do SPEC)', () => {
  it('A.1: fallback apk do mariadb-client presente (imagem sem client não envenena o schema)', () => {
    expect(harness).toMatch(/apk add --no-cache mariadb-client/);
  });

  it('A.1 parte 2: TLS-verify-off no container efêmero (ERROR 2026 provado — só o binário NÃO basta)', () => {
    expect(harness).toMatch(/ssl-verify-server-cert=0/);
    expect(harness).toMatch(/zz-fullsuite-no-ssl-verify\.cnf/);
  });

  it('A.2 REVERTIDO: docker run do pest NÃO seta FULLSUITE_FK_OFF (net-harmful, run 20260613-115507)', () => {
    expect(harness).not.toMatch(/-e\s+FULLSUITE_FK_OFF=1/);
  });

  it('FV-F1 (sharded): pest roda POR-SHARD com --log-junit por-shard; o summary.json da noite vem do shards-merge', () => {
    // Contrato (ADR proposta 2026-07-12 sharding + chip node #4166 SDD P04): a suite
    // inteira num processo só morria por OOM a ~53% e levava o junit junto (0 bytes) =
    // noite perdida. Cura: shards-plan.mjs particiona os dirs em N shards (bin-pack); cada
    // shard roda num processo php FRESCO com --log-junit num arquivo POR-SHARD e vira
    // shard-<i>.summary.json (junit-summary); shards-merge.mjs funde os VIVOS no summary.json
    // da noite — 1 shard morto perde só ele (all_shards_measured=false), não zera a noite.
    expect(harness).toMatch(/shards-plan\.mjs" --roots tests,Modules --shards/); // plano bin-pack (chip node)
    expect(harness).toMatch(/shards-plan\.mjs" --verify/); // universe-gate (nenhum dir de teste some)
    // regressão do run 20260712-195945: o shards-plan descobre dirs relativos a cwd — DEVE
    // rodar com `cd "$CODE"` (senão acha 0 dirs de /opt e a noite vira vazia-válida vácua).
    expect(harness).toMatch(/cd "\$CODE" && node "\$CODE\/scripts\/tests\/shards-plan\.mjs"/);
    expect(harness).toMatch(/total_dirs/); // guard: 0 dirs descobertos aborta (não vira noite falsa)
    expect(harness).toMatch(/vendor\/bin\/pest \$SHARD_DIRS/); // diagnóstico roda os dirs do shard
    expect(harness).toMatch(/--log-junit "\/artifacts\/junit-shard-\$SHARD_IDX\.xml"/); // junit POR-SHARD
    expect(harness).not.toMatch(/--log-junit \/artifacts\/junit\.xml/); // nunca mais o SPOF do run único
    expect(harness).toMatch(/junit-summary\.mjs" "\$SJUNIT" --out "\$RUN_DIR\/shard-\$i\.summary\.json"/);
    expect(harness).toMatch(
      /shards-merge\.mjs" --shards-dir "\$RUN_DIR" --plan "\$RUN_DIR\/shards-plan\.json"[\s\S]*?--out "\$RUN_DIR\/summary\.json"/
    );
    // exatamente 2 invocações de pest: diagnóstico (no laço, 1×/shard em runtime) + coverage
    expect((harness.match(/vendor\/bin\/pest/g) ?? []).length).toBe(2);
    // o diagnóstico (linha do pest por-shard, memory_limit 4G) NÃO tem pcov
    const diag = harness.match(/exec php -d memory_limit=4G vendor\/bin\/pest[^\n]*/g) ?? [];
    expect(diag.length).toBe(1);
    expect(diag[0]).not.toMatch(/pcov/);
  });

  it('FV-F1: coverage roda SEPARADO (pcov) e NUNCA passa --log-junit (não clobbera o summary do merge)', () => {
    const cov = harness.match(/exec php[^\n]*--coverage-clover[^\n]*/g) ?? [];
    expect(cov.length).toBe(1); // coverage separado único
    expect(cov[0]).toMatch(/pcov\.enabled=1/);
    expect(cov[0]).not.toMatch(/--log-junit/);
  });

  it('V1 sharding: 1 shard morto NÃO zera a noite — merge marca all_shards_measured + N configurável', () => {
    expect(harness).toMatch(/all_shards_measured/); // guard anti-mascaramento (schema sharded/v1)
    expect(harness).toMatch(/FULLSUITE_SHARDS/); // N shards configurável (default 8)
  });
});

describe('US-GOV-018 Frente B — config_json json→longtext (DoD B do SPEC)', () => {
  it('ALTER pra LONGTEXT alinhado ao cast encrypted:array (blob base64 não é JSON)', () => {
    expect(migration).toMatch(/MODIFY config_json LONGTEXT NOT NULL/);
  });

  it('idempotente: só altera se a coluna ainda for json + down() com guarda de driver', () => {
    expect(migration).toMatch(/str_contains\(strtolower\(\$type\), 'json'\)/);
    expect(migration).toMatch(/hasTable\('payment_gateway_credentials'\)/);
    expect(migration).toMatch(/getDriverName\(\) === 'mysql'/);
  });
});

describe('US-GOV-020 Frente C — grants do migrate:fresh (Fix do SPEC, re-land #2728)', () => {
  it('log_bin_trust_function_creators=1 via root (ERROR 1419 sob binlog)', () => {
    expect(harness).toMatch(/SET GLOBAL log_bin_trust_function_creators=1/);
  });

  it('GRANT SET_USER_ID pro usuário fullsuite (ERROR 1227 — trigger DEFINER de prod)', () => {
    expect(harness).toMatch(/GRANT SET_USER_ID ON \*\.\*/);
  });
});

describe('US-GOV-045 — run inválido nunca mais é silencioso (DoD do SPEC)', () => {
  // DoD D.1 — post-mortem que sobrevive à morte silenciosa (2/5 runs 29jun–02jul).
  it('D.1: cada shard emite --log-events-text POR-SHARD (nomeia o teste em voo no kill do shard)', () => {
    expect(harness).toMatch(/--log-events-text "\/artifacts\/pest-events-shard-\$SHARD_IDX\.txt"/);
  });

  it('D.1: noite sem shard morto (all_shards_measured) apaga os events-log por-shard (disco CT100 ~95%)', () => {
    expect(harness).toMatch(/rm -f "\$RUN_DIR"\/pest-events-shard-\*\.txt/);
  });

  // DoD D.2 — marcador explícito de invalidez no summary.json, exit-code do tripwire preservado.
  it('D.2: junit-summary grava marcador {invalid, reason} quando --out foi pedido', () => {
    expect(junitSummary).toMatch(/invalid:\s*true/);
    // as 4 razões do DoD (ausente / 0 bytes / 0 testcases / incoerente)
    expect(junitSummary).toMatch(/'xml_ausente'/);
    expect(junitSummary).toMatch(/'xml_0_bytes'/);
    expect(junitSummary).toMatch(/'coleta_incoerente'/);
  });

  it('D.2: tripwire FV-F1 preservado — exit 1 (artefato) e exit 2 (incoerente) intactos', () => {
    // 0 bytes / ausente → exit 1; incoerência de contagem → exit 2 (contrato duro FV-F1).
    expect(junitSummary).toMatch(/fail\(1,[^)]*0 bytes[\s\S]*?'xml_0_bytes'\)/);
    expect(junitSummary).toMatch(/fail\(2,[^)]*incoerencia[\s\S]*?'coleta_incoerente'\)/);
  });

  // DoD D.3 — leitores ignoram o marcador (dupla guarda além do !coherent).
  it('D.3: floor-compute e nightly-diff pulam runs com invalid:true', () => {
    expect(floorCompute).toMatch(/if \(s\.invalid\) continue;/);
    expect(nightlyDiff).toMatch(/if \(s\.invalid\) return null;/);
  });

  // DoD D.4 — alerta estruturado grep-ável, nomeando o teste em voo.
  it('D.4: harness emite [ALERT] fullsuite_run_invalid key=value com last_test_in_flight', () => {
    expect(harness).toMatch(/\[ALERT\] fullsuite_run_invalid/);
    expect(harness).toMatch(/last_test_in_flight=/);
    // sharded: o alerta dispara só quando o merge da noite é INCOERENTE (coherent=false =
    // TODOS os shards mortos) — o ramo else de `if [ "$COHERENT" = "true" ]` no passo 7.
    expect(harness).toMatch(/if \[ "\$COHERENT" = "true" \]/);
  });
});
