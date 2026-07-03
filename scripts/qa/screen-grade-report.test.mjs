#!/usr/bin/env node
// Self-test screen-grade-report — prova a derivação de comportamento vs o CONTRATO documentado
// (SCREEN-GRADE-METODO §5/§8), NÃO vs a implementação. Contrato ancorado:
//   cobertura_uc = % de UCs DECLARADOS (heading "## UC-XX") com Status ✅ (prova verde).
//   Status = 1º glyph da linha "Status:" (prosa depois não sobrescreve). Backlog não conta.
// Origem: Onda 0b / ADR 0320. Roda: node scripts/qa/screen-grade-report.test.mjs
import { deriveBehavior, firstStatusGlyph, storedCobertura, d1FromScorecard } from './screen-grade-report.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

// 1. Contrato central: 2 UCs, um ✅ um 🧪 → declared=2, green=1, cobertura "50%".
{
  const casos = [
    '## UC-01 · caso provado', '- **Status:** ✅ passa (manifesto verde)', '',
    '## UC-02 · caso parcial', '- **Status: 🧪** _(re-rodar e2e)_', '',
  ].join('\n');
  const b = deriveBehavior(casos);
  check('2 UCs (✅+🧪) → declared=2, green=1, cobertura 50%', b.declared === 2 && b.green === 1 && b.testing === 1 && b.cobertura === '50%', JSON.stringify(b));
}

// 2. Caso real Sells/Create: 1 UC 🧪, 0 provado → cobertura "0%" (UX 88 NÃO compra comportamento).
{
  const casos = [
    '## UC-S01 · Venda balcão a prazo (fiado)', '- **Status: 🧪** _(refactor só-de-layout)_', '',
    '## Backlog de casos (sem id)', '- **[BACKLOG]** Venda paga no ato', '- **[BACKLOG]** Bloqueio por limite',
  ].join('\n');
  const b = deriveBehavior(casos);
  check('Sells/Create: 1 UC 🧪 → cobertura 0%, 2 backlog, 0 declarado extra', b.declared === 1 && b.testing === 1 && b.green === 0 && b.cobertura === '0%' && b.backlog === 2, JSON.stringify(b));
}

// 3. Sem UC declarado → cobertura "n/a" (não divide por zero, não mente "100%").
{
  const b = deriveBehavior('# só prosa\n## Como rodar\n- passo 1');
  check('0 UC declarado → cobertura n/a', b.declared === 0 && b.cobertura === 'n/a', JSON.stringify(b));
}

// 4. Tudo verde → 100%.
{
  const casos = '## UC-01 x\n- Status: ✅\n\n## UC-02 y\n- Status: ✅';
  const b = deriveBehavior(casos);
  check('2 UCs ✅✅ → cobertura 100%', b.cobertura === '100%' && b.green === 2, JSON.stringify(b));
}

// 5. firstStatusGlyph: prosa DEPOIS do glyph não sobrescreve (bug real do guard — 🧪 c/ ✅ na nota).
{
  const bloco = 'UC-X · algo\n- **Status: 🧪** _(volta a ✅ quando o teste passar)_';
  check('Status 🧪 com "✅" na prosa → lê 🧪 (1º glyph), não green', firstStatusGlyph(bloco) === 'testing', firstStatusGlyph(bloco));
}

// 6. UC hifenado (UC-S01/UC-IMP-01) é reconhecido como declarado (mesmo uc-regex do guard).
{
  const b = deriveBehavior('## UC-IMP-01 · importação\n- Status: ✅');
  check('UC-IMP-01 (hifenado) conta como declarado', b.declared === 1 && b.green === 1, JSON.stringify(b));
}

// 7. Parsers tolerantes a comentário YAML de fim de linha (o bug que apagava a coluna D1).
{
  const yaml = ['casos_coverage:', '  cobertura_uc: "0%"   # derivado', 'd1_calculo:', '  aplica: true', '  nivel: "🔴"          # indefeso'].join('\n');
  check('storedCobertura lê "0%" apesar do # comentário', storedCobertura(yaml) === '0%', String(storedCobertura(yaml)));
  check('d1FromScorecard lê 🔴 apesar do # comentário', d1FromScorecard(yaml) === '🔴', String(d1FromScorecard(yaml)));
}

// 8. Drift dispara: cobertura_uc GRAVADO no scorecard ≠ derivado ao vivo do casos.md.
{
  const yaml = ['casos_coverage:', '  cobertura_uc: "50%"'].join('\n'); // afirma 50%…
  const live = deriveBehavior('## UC-01 x\n- Status: 🧪').cobertura;    // …mas o vivo é 0%
  check('drift detectável: stored 50% ≠ live 0%', storedCobertura(yaml) === '50%' && live === '0%' && storedCobertura(yaml) !== live, `${storedCobertura(yaml)} vs ${live}`);
}

// 9. d1_calculo com aplica:false → "n/a" (não penaliza tela sem cálculo próprio).
{
  check('d1 aplica:false → n/a', d1FromScorecard('d1_calculo:\n  aplica: false\n  nivel: "🔴"') === 'n/a', String(d1FromScorecard('d1_calculo:\n  aplica: false\n  nivel: "🔴"')));
}

console.log(fails === 0 ? '\n✓ screen-grade-report: contrato de derivação OK' : `\n✗ ${fails} falha(s)`);
process.exit(fails === 0 ? 0 : 1);
