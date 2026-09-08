#!/usr/bin/env node
// Gera design-system/components/<Nome>/<Nome>.prompt.md a partir de ds-notas-uso.json
// e, com --bundle, injeta as mesmas notas como campo por componente no manifesto do bundle.
//
//   node handoff-ds-notas/ds-notas-gerar.mjs --dry
//   node handoff-ds-notas/ds-notas-gerar.mjs --out design-system/components
//   node handoff-ds-notas/ds-notas-gerar.mjs --bundle resources/js/ds/_ds_manifest.json
//
// Idempotente: reescreve o arquivo inteiro (a nota é derivada do JSON, o JSON é a fonte).
import { readFile, writeFile, mkdir } from "node:fs/promises";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const AQUI = dirname(fileURLToPath(import.meta.url));
const arg = (n, d) => { const i = process.argv.indexOf(n); return i > -1 ? (process.argv[i + 1] || d) : d; };
const flag = (n) => process.argv.includes(n);

const fonte = resolve(AQUI, "ds-notas-uso.json");
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

const out = arg("--out", "design-system/components");
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
