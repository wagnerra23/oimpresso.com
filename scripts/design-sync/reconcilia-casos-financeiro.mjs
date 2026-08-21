#!/usr/bin/env node
/**
 * Concilia o espelho Cowork dos casos do Financeiro com o canon vivo do repo.
 *
 * POR QUE EXISTE: o espelho (`prototipo-ui/design-docs/cowork-inbox/casos-financeiro-2026-08-17/`)
 * é cópia fiel e congelada do projeto Cowork — ele CONTRADIZ o canon em alguns pontos, de
 * propósito. Sem uma porta viva que meça o delta, a próxima sessão lê o espelho, vê `UC-*`
 * que "sumiram" do canon e os restaura — reintroduzindo afirmação de prova inexistente numa
 * tela de cobrança de cliente real.
 *
 * O QUE ELE RESPONDE (derivado da árvore, nunca de tabela escrita à mão):
 *   1. que UC existe só num lado;
 *   2. desses, quais SOBREVIVEM no canon como `[BACKLOG]` (rebaixamento G-2 da ADR 0264 —
 *      o certo) e quais se perderam de fato;
 *   3. que UC existe nos dois lados com status divergente, e para que lado.
 *
 * NÃO EDITA NADA. Mede e reporta; o que portar é decisão humana.
 *
 * Uso:
 *   node scripts/design-sync/reconcilia-casos-financeiro.mjs           # relatório
 *   node scripts/design-sync/reconcilia-casos-financeiro.mjs --check   # exit 1 se houver UC PERDIDO
 *
 * O `--check` falha SÓ em perda real (comportamento que não sobrevive nem como backlog).
 * Divergência de status e rebaixamento G-2 são reportados, não reprovados: rebaixar é o
 * comportamento correto do canon.
 */
import fs from 'node:fs';
import path from 'node:path';

const RAIZ_ESPELHO = 'prototipo-ui/design-docs/cowork-inbox/casos-financeiro-2026-08-17';
const RAIZ_CANON = 'resources/js/Pages/Financeiro';

/** espelho (nome achatado) → canon (caminho real). O achatamento é do lado Cowork. */
const MAPA = {
  'Advisor.Dashboard': 'Advisor/Dashboard',
  'Advisor.Login': 'Advisor/Login',
  AssinaturaAtualizar: 'AssinaturaAtualizar',
  'Categorias.Index': 'Categorias/Index',
  'Cobranca.Index': 'Cobranca/Index',
  'Configuracoes.Contador': 'Configuracoes/Contador',
  'ContasBancarias.Index': 'ContasBancarias/Index',
  'Dashboard.Index': 'Dashboard/Index',
  'Dre.Index': 'Dre/Index',
  'Extrato.Index': 'Extrato/Index',
  'Fluxo.Index': 'Fluxo/Index',
  'PlanoContas.Index': 'PlanoContas/Index',
  'Relatorios.Index': 'Relatorios/Index',
  'Unificado.Novo': 'Unificado/Novo',
};

const norm = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
const simbolo = (s) => (String(s).match(/[✅\u{1F9EA}⬜❌]/u) || ['?'])[0];

function ucsComStatus(txt) {
  const linhas = txt.split('\n');
  const out = new Map();
  for (let i = 0; i < linhas.length; i++) {
    const m = linhas[i].match(/^##\s+(UC-[A-Z0-9-]+)\s*—\s*(.*)$/);
    if (!m) continue;
    let status = '';
    for (let j = i + 1; j < Math.min(i + 4, linhas.length); j++) {
      const s = linhas[j].match(/^Status:\s*(.*)$/);
      if (s) { status = s[1]; break; }
    }
    out.set(m[1], { titulo: m[2].trim(), status: status.trim() });
  }
  return out;
}

/** Todo título de heading do canon (UC ou [BACKLOG]) + bullets [BACKLOG]. */
function titulosDoCanon(txt) {
  return [
    ...[...txt.matchAll(/^##\s+(?:\[BACKLOG\]\s*)?(?:UC-[A-Z0-9-]+\s*—\s*)?(.*)$/gm)].map((m) => m[1]),
    ...[...txt.matchAll(/^-\s+\*\*\[BACKLOG\]\s*(.*?)\*\*/gm)].map((m) => m[1]),
  ].map(norm).filter(Boolean);
}

const check = process.argv.includes('--check');
let iguais = 0, sobrevivem = 0, soCanon = 0;
const perdidos = [];
const divergentes = [];
const ausentes = [];
const porTela = [];

for (const [esp, can] of Object.entries(MAPA)) {
  const pEsp = path.join(RAIZ_ESPELHO, `${esp}.casos.md`);
  const pCan = path.join(RAIZ_CANON, `${can}.casos.md`);
  if (!fs.existsSync(pEsp)) { ausentes.push(`espelho ausente: ${pEsp}`); continue; }
  if (!fs.existsSync(pCan)) { ausentes.push(`CANON ausente: ${pCan} — candidato real a portar`); continue; }

  const tEsp = fs.readFileSync(pEsp, 'utf8');
  const tCan = fs.readFileSync(pCan, 'utf8');
  const uEsp = ucsComStatus(tEsp);
  const uCan = ucsComStatus(tCan);
  const titulos = titulosDoCanon(tCan);
  const corpoCanon = norm(tCan);

  const linhas = [];
  for (const [id, info] of uEsp) {
    if (uCan.has(id)) {
      if (simbolo(info.status) === simbolo(uCan.get(id).status)) { iguais++; continue; }
      divergentes.push({ tela: can, id, espelho: info.status, canon: uCan.get(id).status });
      linhas.push(`  ~ ${id}: espelho=${simbolo(info.status)} canon=${simbolo(uCan.get(id).status)}`);
      continue;
    }
    const t = norm(info.titulo);
    const sobreviveu = titulos.some((c) => c === t || c.includes(t) || t.includes(c)) || corpoCanon.includes(norm(id));
    if (sobreviveu) { sobrevivem++; linhas.push(`  ↓ ${id} rebaixado a [BACKLOG] no canon (G-2)`); }
    else { perdidos.push({ tela: can, id, titulo: info.titulo }); linhas.push(`  ✗ ${id} PERDIDO — ${info.titulo}`); }
  }
  for (const id of uCan.keys()) if (!uEsp.has(id)) { soCanon++; linhas.push(`  + ${id} só no canon`); }

  porTela.push(`${can}  (espelho ${uEsp.size} UC · canon ${uCan.size} UC)` +
    (linhas.length ? '\n' + linhas.join('\n') : '\n  — idêntico em ids e status'));
}

console.log(porTela.join('\n\n'));
if (ausentes.length) { console.log('\nARQUIVOS AUSENTES:'); ausentes.forEach((a) => console.log('  ! ' + a)); }

console.log('\n================ PLACAR ================');
console.log(`UC iguais em id e status:                 ${iguais}`);
console.log(`UC rebaixados a [BACKLOG] no canon (ok):  ${sobrevivem}`);
console.log(`UC só no canon:                           ${soCanon}`);
console.log(`UC com status divergente:                 ${divergentes.length}`);
console.log(`UC PERDIDOS (comportamento sumiu):        ${perdidos.length}`);

if (divergentes.length) {
  console.log('\nDivergências de status (o canon costuma estar certo — confira a prova ANTES de mexer):');
  for (const d of divergentes) {
    console.log(`  • ${d.tela} :: ${d.id}\n      espelho: ${d.espelho}\n      canon  : ${d.canon}`);
  }
}
if (perdidos.length) {
  console.log('\nPERDIDOS — estes SIM são candidatos a portar pro canon:');
  perdidos.forEach((p) => console.log(`  ✗ ${p.tela} :: ${p.id} — ${p.titulo}`));
}

console.log(
  '\nLembrete: rebaixar UC sem prova para [BACKLOG] é o G-2 da ADR 0264, não regressão.' +
  '\nNÃO promova de volta sem o teste que cite o id.'
);

if (check && perdidos.length) process.exit(1);
