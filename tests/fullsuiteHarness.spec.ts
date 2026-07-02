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

import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(__dirname, '..');
const harness = readFileSync(resolve(ROOT, 'scripts/tests/ct100-fullsuite.sh'), 'utf8');
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

  it('FV-F1: --log-junit SEMPRE presente (com e sem pcov — coverage é aditivo, nunca substitui)', () => {
    const junitCalls = harness.match(/vendor\/bin\/pest [^\n]*--log-junit/g) ?? [];
    const pestCalls = harness.match(/exec php [^\n]*vendor\/bin\/pest/g) ?? [];
    expect(pestCalls.length).toBeGreaterThan(0);
    expect(junitCalls.length).toBe(pestCalls.length);
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
