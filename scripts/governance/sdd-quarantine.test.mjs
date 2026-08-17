#!/usr/bin/env node
// Bite-test de measureQuarantine (métrica `n_quarantine` do scorecard SDD — ADR 0275 §3,
// ARMADA em GT-G3 required, direction down, target 0).
//
// POR QUE EXISTE: em 2026-08-17 o regex foi AMPLIADO de `legacy-quarantine` pra
// `legacy-quarantine|era-sqlite` (decisão [W] "flip"), e a medida saltou 25 → 252.
// Uma métrica armada em required que muda de DEFINIÇÃO sem teste é ganho reversível
// em silêncio: alguém "simplifica" o regex de volta e o alarme apaga sem nada quebrar.
//
// Roda a FUNÇÃO REAL com raiz injetada — não uma cópia do regex. Assert sobre helper
// exportado prova a mecânica, nunca o contrato do pipeline (lição 2026-08-14).
//
// FP MEDIDO ANTES DE ARMAR (corpus real, 2026-08-17): dos 227 arquivos que o flip
// passou a contar, 225 têm chamada real de skip (skipIf/markTestSkipped/->skip) e
// 2 não — 0,9%. Os 2 são Modules/KB/Tests/{Feature/MultiTenantTraitTest.php,Helpers.php};
// o Helpers.php provavelmente FORNECE o helper de skip, logo carrega o marcador
// legitimamente. FP aceito e declarado, não escondido.
//
// Rodar: node scripts/governance/sdd-quarantine.test.mjs   (exit 0 = passa)

import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { measureQuarantine } from './sdd-scorecard.mjs';

let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── sandbox: uma raiz falsa com tests/ e Modules/<X>/Tests/ ──────────────────
const root = mkdtempSync(join(tmpdir(), 'quar-'));
mkdirSync(join(root, 'tests', 'Feature'), { recursive: true });
mkdirSync(join(root, 'Modules', 'Foo', 'Tests'), { recursive: true });

const escreve = (rel, txt) => writeFileSync(join(root, rel), txt);

// FIXTURE BOA 1 — o marcador HISTÓRICO. Se este parar de contar, o flip apagou
// a metade antiga da métrica em vez de somar à ela.
escreve('tests/Feature/AntigoTest.php', "<?php // @group legacy-quarantine\nit('x', fn () => expect(1)->toBe(1));");

// FIXTURE BOA 2 — o marcador NOVO. É o assert que quebra se alguém estreitar o
// regex de volta pra só `legacy-quarantine`.
escreve('tests/Feature/EraSqliteTest.php', "<?php\nit('y', function () {})->skip('era-sqlite: schema sintético manual incompatível com MySQL persistente');");

// FIXTURE BOA 3 — em Modules/<X>/Tests, pra provar que a 2ª raiz do walk também conta.
escreve('Modules/Foo/Tests/ModuloTest.php', "<?php\ntest('z', fn () => null)->skip('era-sqlite: quarentena Onda 2');");

// CONTROLE NEGATIVO 1 — teste limpo NÃO conta (senão a métrica contaria a suíte toda).
escreve('tests/Feature/LimpoTest.php', "<?php\nit('w', fn () => expect(true)->toBeTrue());");

// CONTROLE NEGATIVO 2 — arquivo não-.php com o marcador NÃO conta.
escreve('tests/Feature/leiame.md', 'era-sqlite legacy-quarantine');

check('conta os 3 marcados (legacy + era-sqlite em tests/ e em Modules/<X>/Tests)',
  measureQuarantine(root).files === 3);

// ── o assert que morde se o flip for revertido ───────────────────────────────
// Sem `era-sqlite` no regex, os fixtures 2 e 3 somem e a contagem cai pra 1.
check('FIXTURE BOA: o marcador era-sqlite É contado (o flip de 2026-08-17)',
  measureQuarantine(root).files > 1);

// ── raiz vazia: não explode e não inventa ───────────────────────────────────
const vazio = mkdtempSync(join(tmpdir(), 'quar-vazio-'));
check('raiz sem tests/ nem Modules/ devolve 0 (não explode, não inventa)',
  measureQuarantine(vazio).files === 0);

// ── direção da métrica: é contagem, nunca percentual ────────────────────────
check('devolve {files:<int>} — contagem inteira, não taxa',
  Number.isInteger(measureQuarantine(root).files));

console.log(fails === 0
  ? '\n[PASS] measureQuarantine: conta legacy-quarantine E era-sqlite, nas 2 raízes, ignora limpo e não-.php.'
  : `\n[FAIL] ${fails} assert(s) falharam.`);
process.exit(fails === 0 ? 0 : 1);
