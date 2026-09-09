#!/usr/bin/env node
// cowork-ssot-guard.mjs — MÁQUINA de fonte única do protótipo de design.
// Garante que `prototipo-ui/cowork/` é a ÚNICA fonte de design (BUILD-ONLY) e que
// não existe protótipo/dupla-fonte fora dela. Encaixado em design-memory-gate.yml
// (sem workflow novo — respeita o teto anti-proliferação, ADR 0298).
// Origem: ADR-proposta 2026-06-23-prototipo-ssot-unico-com-historico.
//
// ⚠️ LIMITE REAL (medido 2026-08-13 — a frase acima promete mais do que o código faz):
// as 3 regras varrem SÓ dentro de `prototipo-ui/`. Dupla-fonte FORA dali passa verde.
// Achados no dia, com o guard verde: 13 duplicatas `.jsx/.css` na raiz de `prototipo-ui/`
// (7 DEFASADAS vs o espelho — o `oficina-page.jsx` da raiz tinha 26KB a menos que o vivo)
// e 11 arquivos de design em `resources/js/Pages/Financeiro/_cowork-bundle/`.
// POR QUE NÃO VIROU R4 — o FP foi medido ANTES e reprovou: a regra sintática óbvia
// ("arquivo de design fora do espelho com homônimo dentro") dá 24 hits, dos quais ~5 são
// falso-positivo por construção (fixtures de teste PRECISAM da cópia: `prototipo-ui/fixtures/`,
// `tests/governance-fixtures/`) e 19 são cópias DECLARADAS e intencionais (`_BACKUP-NAO-USAR-…`,
// `_cowork-bundle/` com README explicando, allowlist do R3). Distinguir "duplicata acidental"
// de "cópia declarada" não é decidível pelo path — é a família das 4 lápides de guard sintático
// do §5 (allowlist-de-pasta 06-30 · `@scope` 07-09 · vocabulário 130 FP 07-16 · `toHaveKey` 07-26).
// Fechar isso exige um critério que leia INTENÇÃO (README/marcador declarado), não nome de pasta.
// Falha (exit 1) se:
//   R1  qualquer .md dentro de cowork/        (knowledge = canon: memory/ + prototipo-ui root, não aqui)
//   R2  bundle datado prototipo-ui/cowork-*/  (SSOT é UM cowork/, sem datados = sem 2ª fonte)
//   R3  prototipo-ui/prototipos/<dir> fora do allowlist transitório OU da lista histórica
//   R4  .html na RAIZ de cowork/ que não seja o host oimpresso.com.html (host único — pedido
//       do lado design 2026-09-01; nasceu limpo: a 2ª cópia da raiz [Prova Viva] foi movida
//       pra prototipos/ no mesmo PR que criou a regra, então dívida herdada = 0 por medição)
//
// Uso: node scripts/governance/cowork-ssot-guard.mjs [--json]
import { readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const COWORK = 'prototipo-ui/cowork';

// Telas cujo design AINDA não existe no export → só sobrevive o recorte antigo.
// O DESIGN deve exportá-las pro cowork/ (ver FRESCOR). Migrou → REMOVER daqui (meta: allowlist = 0).
// 'perfil' = baseline da Fase 0 do protocolo aplicar-prototipo (2026-06-24, handoff ComVis);
// transitório como os outros — sai daqui quando o design exportar pro cowork/.
const PROTOTIPOS_ALLOWLIST = new Set(['compras-grade-matrix', 'inventario-migracao', 'perfil']);

// NATUREZA DIFERENTE do allowlist acima (que é transitório, meta = 0): estes são ÂNCORAS
// HISTÓRICAS — protótipo cuja cópia upstream foi APOSENTADA por decisão [W], mas que um
// charter vivo ainda declara em `related_prototype`. Não têm prazo pra sair: saem daqui
// só quando o charter deixar de ancorar neles. Misturá-los no transitório apodreceria a
// meta "allowlist = 0".
// 'financeiro-prova-viva' — âncora do ProvaViva.charter.md; upstream apagado por [W]
// 2026-09-01 (a cópia da raiz do espelho moveu pra cá no mesmo PR — fidelidade provada
// por hash idêntico entre o espelho de 06/23 e o download do vivo na véspera da deleção).
const PROTOTIPOS_HISTORICOS = new Set(['financeiro-prova-viva']);

// TERCEIRA natureza — e a DIREÇÃO é o que a distingue das duas de cima.
// O allowlist transitório é pra design que VEIO do Cowork e ainda não voltou (sai quando o
// design exportar; meta = 0). Estes são o inverso: design que NASCEU aqui, gerado pelo
// agente Code como designer-agente (ADR 0282 §0.1 — "onde não há protótipo, o agente gera,
// ancorado no DS canon"), pra tela cuja fonte visual não existe em nenhum dos dois donos do
// inventário (espelho + Cowork vivo por ID). Não têm de onde ser "exportados" — precisam
// SUBIR. Sai daqui quando o design subir pro Cowork e voltar pelo cowork/ normal, ou quando
// [W] decidir descartar.
// Misturá-los no transitório inverteria a semântica dele e apodreceria a meta "allowlist = 0"
// (é a mesma razão pela qual PROTOTIPOS_HISTORICOS não virou parte do transitório).
// Cada entrada tem SOURCE.md declarando proveniência, âncora de domínio e status.
// 'nfe-tributacao' — 4 telas de NfeBrasil/Tributacao; ausência medida nos 2 donos em
// 2026-09-09 (a própria fonte declara o limite: configuracoes-page.jsx:324, prefs-page.jsx:121).
// 'nfse-emitir'    — Nfse/Emitir; a única emissão NFS-e desenhada é o modal dentro da venda
// (vendas-flow.jsx:573) e o botão do cockpit só navega (fiscal-page.jsx:96). Medido 2026-09-09.
// 'payment-gateway-cnab' — Settings/PaymentGateways/CnabRetorno (tela de dinheiro sem fonte);
// ausência medida nos 2 donos em 2026-09-09. O espelho TEM vocabulário parcial de CNAB — dois
// `SheetRemessaRetorno` (boletos-page.jsx:509, pg-cobranca-page.jsx:863) — mas os dois são sheet
// de LISTA dentro da Cobrança, sem dropzone/validação/contadores por arquivo (G1-G4 do charter),
// e pertencem a outra US (US-FIN-018, cujo `Implementado em:` aponta outro .tsx).
const PROTOTIPOS_GERADOS = new Set(['nfe-tributacao', 'nfse-emitir', 'payment-gateway-cnab']);

const errors = [];

function walk(dir) {
  const abs = join(ROOT, dir);
  if (!existsSync(abs)) return [];
  const out = [];
  for (const e of readdirSync(abs, { withFileTypes: true })) {
    const rel = `${dir}/${e.name}`;
    if (e.isDirectory()) out.push(...walk(rel));
    else out.push(rel);
  }
  return out;
}

// R1 — zero .md em cowork/ (build-only)
for (const f of walk(COWORK)) {
  if (f.toLowerCase().endsWith('.md')) errors.push(`R1 .md em cowork/ (mova pro canon — memory/ ou prototipo-ui root): ${f}`);
}

// R2 — sem bundles datados cowork-*
const pu = join(ROOT, 'prototipo-ui');
if (existsSync(pu)) {
  for (const e of readdirSync(pu, { withFileTypes: true })) {
    if (e.isDirectory() && /^cowork-/.test(e.name)) errors.push(`R2 bundle datado proibido (SSOT é cowork/): prototipo-ui/${e.name}`);
  }
}

// R3 — prototipos/ só allowlist transitório OU âncora histórica declarada
const proto = join(ROOT, 'prototipo-ui/prototipos');
if (existsSync(proto)) {
  for (const e of readdirSync(proto, { withFileTypes: true })) {
    if (e.isDirectory() && !PROTOTIPOS_ALLOWLIST.has(e.name) && !PROTOTIPOS_HISTORICOS.has(e.name) && !PROTOTIPOS_GERADOS.has(e.name)) {
      errors.push(`R3 protótipo fora do cowork/ (mova o build pro cowork/): prototipo-ui/prototipos/${e.name}`);
    }
  }
}

// R4 — host único na RAIZ do espelho: o único .html de raiz é o próprio host.
// Subdiretórios ficam FORA da regra de propósito (venda-v3/index.html é de OUTRA conta —
// FORA_DESTA_CONTA no protocolo.config — e ds-v6/produto-preco-especial são gabaritos
// declarados). O exportPlan pousa qualquer não-.md do vivo em cowork/<path>, então um
// .html novo de raiz É notícia, nunca herança (raiz medida limpa em 2026-09-01).
const coworkAbs = join(ROOT, COWORK);
if (existsSync(coworkAbs)) {
  for (const e of readdirSync(coworkAbs, { withFileTypes: true })) {
    if (e.isFile() && e.name.toLowerCase().endsWith('.html') && e.name !== 'oimpresso.com.html') {
      errors.push(`R4 segundo .html na raiz do espelho (host único é oimpresso.com.html; protótipo standalone aposentado vai pra prototipos/ com decisão [W]): ${COWORK}/${e.name}`);
    }
  }
}

if (process.argv.includes('--json')) {
  console.log(JSON.stringify({
    ok: errors.length === 0,
    errors,
    allowlist: [...PROTOTIPOS_ALLOWLIST],
    historicos: [...PROTOTIPOS_HISTORICOS],
    gerados: [...PROTOTIPOS_GERADOS],
  }, null, 2));
} else if (errors.length) {
  console.error(`✗ cowork-ssot-guard: ${errors.length} violação(ões) de fonte única:`);
  for (const e of errors) console.error('  - ' + e);
  console.error('\nRegra: prototipo-ui/cowork/ = ÚNICA fonte de design (BUILD-ONLY). Conhecimento = canon (memory/ + prototipo-ui root).');
  console.error('ADR: memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md');
  if (PROTOTIPOS_ALLOWLIST.size) console.error(`Allowlist transitório (design deve exportar pro cowork/): ${[...PROTOTIPOS_ALLOWLIST].join(', ')}`);
  if (PROTOTIPOS_HISTORICOS.size) console.error(`Âncoras históricas (upstream aposentado, charter ainda ancora): ${[...PROTOTIPOS_HISTORICOS].join(', ')}`);
  if (PROTOTIPOS_GERADOS.size) console.error(`Gerados pelo Code (ADR 0282 §0.1, aguardam subir pro Cowork): ${[...PROTOTIPOS_GERADOS].join(', ')}`);
} else {
  console.log('✓ cowork-ssot-guard: fonte única OK (cowork/ build-only · sem bundles datados · prototipos só allowlist/histórico · host único na raiz).');
}
process.exit(errors.length ? 1 : 0);
