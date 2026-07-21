import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseSealedTable, scoreHuman, template } from './funcao-scorecard-humano.mjs';

const sealed = `| # | Função | Critério | Veredito | Evidência | Âncora |\n|---|---|---|---|---|---|\n| 1 | A | C1 | concordo | x | y |\n| 2 | B | C2 | discordo | x | y |\n| 3 | C | C3 | incerto | x | y |\n| 4 | D | C4 | n/a | x | y |\n| 5 | E | C5 | concordo | x | y |\n| 6 | F | C6 | discordo | x | y |\n| 7 | G | C7 | incerto | x | y |\n| 8 | H | C7 | n/a | x | y |\n| 9 | I | C1 | concordo | x | y |`;

test('parser lê exatamente os nove vereditos da tabela selada', () => {
  assert.deepEqual(Object.values(parseSealedTable(sealed)), ['concordo', 'discordo', 'incerto', 'n/a', 'concordo', 'discordo', 'incerto', 'n/a', 'concordo']);
});

test('pontuação humana calcula K/9 e Cohen kappa, não só percentual', () => {
  const labels = template();
  const expected = parseSealedTable(sealed);
  for (const [id, verdict] of Object.entries(expected)) labels.items[id] = { veredito: verdict, fonte: 'canon', nota: '' };
  const result = scoreHuman(labels, expected);
  assert.equal(result.concordancias, 9);
  assert.equal(result.concordancia_pct, 100);
  assert.equal(result.kappa, 1);
});

test('rótulo incompleto falha antes de abrir uma conclusão', () => {
  const labels = template();
  assert.throws(() => scoreHuman(labels, parseSealedTable(sealed)), /veredito ausente/);
});
