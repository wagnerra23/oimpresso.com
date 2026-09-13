#!/usr/bin/env node
// Gera design-system/components/<Nome>/<Nome>.prompt.md a partir de governance/design/ds-notas-uso.json
// e, com --bundle, injeta as mesmas notas como campo por componente no manifesto do bundle.
//
//   node scripts/design/ds-notas-gerar.mjs --dry
//   node scripts/design/ds-notas-gerar.mjs --out prototipo-ui/design-system/components
//   node scripts/design/ds-notas-gerar.mjs --bundle prototipo-ui/design-system/_ds_manifest.json
//
// Idempotente: reescreve o arquivo inteiro (a nota é derivada do JSON, o JSON é a fonte).
//
// ── POR QUE ela não tem workflow, hook, npm script nem `.test.mjs` (medido 2026-09-13) ──
// É órfã POR DESIGN, e o motivo está na natureza dela: não é MEDIDOR (não lê-e-reporta em
// cadência) — é o APLICADOR de um handoff do Cowork, que roda UMA VEZ, à mão, quando [W] aceita
// a entrega. Pendurar num cron/CI reescreveria 44 arquivos a cada run, sem humano no meio.
// Regra que classifica: `memory/proibicoes.md` §"Sempre fazer — LIGUE A MÁQUINA", itens 2-3.
//
// Origem (fato datado): o `_meta` do JSON-fonte diz autor "[CD] no projeto Cowork", data
// 2026-08-31, e declara o destino — `<Nome>.prompt.md` + campo por componente no bundle do DS.
// Máquina e contrato chegaram juntos em `prototipo-ui/cowork/handoff-ds-notas/`; o #7224
// (2026-09-11, "separar fontes por dono e remover paralelos") os realocou para as casas que o
// `cowork-ssot-guard` exige — máquina em `scripts/design/`, contrato em `governance/design/` —
// e removeu a pasta do handoff. Não foi abandono, foi arrumação: os três atos estão no MESMO
// commit (`git log --diff-filter=A -- scripts/design/ds-notas-gerar.mjs`).
//
// Quem a roda: ninguém automaticamente (medido: 0 ocorrência em `.claude/`, `.github/`,
// `package.json`, `AUTOMATIONS.md` e `scripts/governance/gates-registry.json`). À mão:
//   --dry                 sonda read-only — sai ANTES de escrever; use pra conferir a fonte
//   (sem flag)            aplica as 44 notas em <out>/<Nome>/<Nome>.prompt.md
//   --bundle <manifesto>  injeta os mesmos campos por componente no manifesto do bundle
//
// ⚠️ ESTADO EM 2026-09-13 — o payload NUNCA foi aplicado. Medido em `origin/main@c6ab4c8a59`:
//   · há 1 único `*.prompt.md` na árvore (PageHeader) e ele está em INGLÊS, enquanto este script
//     gera PT-BR — ou seja, ele NÃO é saída daqui;
//   · o `_ds_manifest.json` vivo (58 componentes) tem 0 ocorrência de `usage`, `whenToUse`,
//     `pairsWith`, `whenNotToUse` e `moduleAliases`.
//   O número de HOJE não se lê nesta linha: rode `--dry` e conte os `*.prompt.md` da árvore.
//   (Cobertura na mesma data: 44 dos 58 componentes do manifesto têm nota — os 14 sem nota o
//    próprio `--bundle` lista no fim da saída.)
//
// ⚠️ APLICAR EMBUTE UMA DECISÃO [W], e ela está declarada DENTRO do payload: o `_meta.idioma`
// diz que o `PageHeader.prompt.md` existente, em inglês, é reescrito aqui em PT "pra não
// ficarem duas línguas no mesmo diretório — se [W] preferir manter EN, é só traduzir de volta".
// Esse arquivo é rastreado por hash em `config/ds-handoff-baseline.json`, logo a reescrita
// aparece como ALTERADO no `handoff-changed.mjs`. Rodar sem esse aceite troca a língua de um
// artefato versionado sem humano no meio — é por isso que ela fica desligada, não por esquecimento.
//
// ⚠️ O exemplo de `--bundle` acima apontava para `resources/js/ds/_ds_manifest.json`, caminho
// que não existe (medido 2026-09-13: `resources/js/ds/` tem 0 arquivo versionado). Corrigido
// para o manifesto vivo. No código o default é `null` — sem `--bundle`, esse modo não roda.
//
// ⚠️ SONDAS — não conclua "morta" a partir de uma só:
//   · o basename cru acha os 2 sites: `git grep -F "ds-notas-gerar" origin/main` → este arquivo
//     + `scripts/governance/.cowork-freshness-ledger.json`, que é REGISTRO, não invocação.
//   · `git grep` é cego a NOME de arquivo (casa conteúdo); quem cobre isso é
//     `git ls-tree -r origin/main --name-only | grep ds-notas`.
//   · a raiz `ds-notas` acha um site a mais que o nome completo perde
//     (`memory/requisitos/_DesignSystem/SPEC.md`, sobre a pasta `handoff-ds-notas`).
//   · o `memory/reference/MAQUINAS-INVENTARIO.md` não a lista — e isso NÃO prova ausência:
//     medido em 2026-09-13 ele tem 0 ocorrência de `scripts/design/` (cobre `scripts/governance/`,
//     `scripts/tests/` e `scripts/design-sync/`). A cegueira é do inventário, não do script.
import { readFile, writeFile, mkdir } from "node:fs/promises";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const AQUI = dirname(fileURLToPath(import.meta.url));
const arg = (n, d) => { const i = process.argv.indexOf(n); return i > -1 ? (process.argv[i + 1] || d) : d; };
const flag = (n) => process.argv.includes(n);

const fonte = resolve(AQUI, "../../governance/design/ds-notas-uso.json");
const dados = JSON.parse(await readFile(fonte, "utf8"));
const comps = dados.componentes;
const nomes = Object.keys(comps);

const linha = (c) => {
  const partes = [c.quando, c.pareia, c.nao];
  if (c.apelidos?.length) partes.push("Substitui nos módulos: " + c.apelidos.join(" · ") + ".");
  return partes.filter(Boolean).join(" ");
};

if (flag("--dry")) {
  for (const n of nomes) console.log(`\n# ${n}.prompt.md\n${linha(comps[n])}`);
  console.log(`\n${nomes.length} notas (esperado 44).`);
  process.exit(0);
}

const out = arg("--out", "prototipo-ui/design-system/components");
let escritos = 0;
for (const n of nomes) {
  const dir = join(out, n);
  await mkdir(dir, { recursive: true });
  await writeFile(join(dir, `${n}.prompt.md`), linha(comps[n]) + "\n", "utf8");
  escritos++;
}
console.log(`${escritos} *.prompt.md em ${out}`);

const manifesto = arg("--bundle", null);
if (manifesto) {
  const m = JSON.parse(await readFile(manifesto, "utf8"));
  let tocados = 0, faltando = [];
  for (const c of m.components) {
    const nota = comps[c.name];
    if (!nota) { faltando.push(c.name); continue; }
    c.usage = linha(nota);
    c.whenToUse = nota.quando;
    c.pairsWith = nota.pareia;
    c.whenNotToUse = nota.nao;
    if (nota.apelidos?.length) c.moduleAliases = nota.apelidos;
    tocados++;
  }
  await writeFile(manifesto, JSON.stringify(m, null, 0), "utf8");
  console.log(`${tocados}/${m.components.length} componentes com nota no manifesto${faltando.length ? " — sem nota: " + faltando.join(", ") : ""}`);
}
