#!/usr/bin/env node
// build-artefato-prototipo.mjs — gera, a partir do git, a página que é publicada como Artefato.
//
// ADR 0438 D2: o Artefato é publicado a partir do arquivo do repo. O protótipo do [W]
// (prototipo-ui/cowork/Wagner/oimpresso.com.html) roda no navegador com Babel compilando ~190
// arquivos .jsx; publicado assim ele leva dezenas de segundos para abrir no celular. Até
// 2026-10-23 a página publicada era o pacote antigo do Cowork, remendado à mão segmento a
// segmento, sem script no repo: ninguém do time conseguia refazê-la nem provar que batia com o git.
//
// Este build faz a mesma transformação que aquele pacote fazia, inteira e a partir do HTML:
//   - <script type="text/babel" src="x.jsx">  → js/x.js compilado (roda no escopo global, como o Babel)
//   - <script src="x.js">                     → js/x.js copiado
//   - <link rel=stylesheet href="x.css">      → css/x.css copiado
//   - <link rel=preload as=image href="x">    → copiado na raiz
//   - <script type="text/oi-lazy" data-src>   → js/lazy-bundle.js, um segmento por arquivo, na ordem
//                                                do HTML, cada um no próprio escopo (como o carregador)
//   - Babel standalone sai; a base do DS vira ds/ e leva os arquivos do Design System do git.
// E grava fonte.json: o commit de origem e o sha256 de cada arquivo gerado.
//
// Uso:
//   node scripts/design/build-artefato-prototipo.mjs --out <dir>
//     [--html prototipo-ui/cowork/Wagner/oimpresso.com.html] [--ds prototipo-ui/design-system]
//     [--rota-inicial pcv] [--titulo "Produção — protótipo Wagner"]
// --rota-inicial: a página abre nessa rota do shell, a menos que a rota guardada no navegador já
//   comece por ela (quem navega dentro do módulo e recarrega continua onde estava). É o que faz um
//   Artefato de um módulo só, como o da Produção, sem copiar nem cortar o protótipo.
// --titulo: troca o <title> da página publicada.
// Sai 1 se algum arquivo citado pelo HTML não existir no repo (não publica página quebrada).
// Publicar: Artifact publish com url do Artefato, root=<dir>, file_path=<dir>/index.html e
// files = todos os demais caminhos de <dir>.
import { readFileSync, writeFileSync, mkdirSync, copyFileSync, existsSync, readdirSync, rmSync } from "node:fs";
import { join, dirname, basename, resolve, relative } from "node:path";
import { createHash } from "node:crypto";
import { execFileSync } from "node:child_process";

// substitui o carregador que compila no navegador: o pacote já vem compilado, na ordem do HTML
const CARREGADOR = `<script>
window.__oiLazyDone = false;
(function () {
  function start() {
    var el = document.createElement("script");
    el.src = "js/lazy-bundle.js";
    el.onload = el.onerror = function () {
      try { document.dispatchEvent(new Event("oi:lazy-tick")); } catch (e) { console.error(e); }
      window.__oiLazyDone = true;
      document.dispatchEvent(new Event("oi:lazy-done"));
    };
    document.body.appendChild(el);
  }
  var t0 = Date.now();
  (function espera() {
    if (document.querySelector("#app > *") || Date.now() - t0 > 30000) return setTimeout(start, 30);
    setTimeout(espera, 50);
  })();
})();
</script>`;

function arg(nome, padrao) {
  const i = process.argv.indexOf(nome);
  return i > -1 && process.argv[i + 1] ? process.argv[i + 1] : padrao;
}
const HTML = arg("--html", "prototipo-ui/cowork/Wagner/oimpresso.com.html");
const DS = arg("--ds", "prototipo-ui/design-system");
const OUT = arg("--out", null);
const ROTA = arg("--rota-inicial", null);
const TITULO = arg("--titulo", null);
if (ROTA && !/^[a-z0-9-]+$/.test(ROTA)) {
  console.error("--rota-inicial aceita só letras minúsculas, números e hífen (ex.: pcv).");
  process.exit(2);
}
if (!OUT) {
  console.error("uso: build-artefato-prototipo.mjs --out <dir> [--html <arquivo>] [--ds <pasta>]");
  process.exit(2);
}
let esbuild;
try {
  esbuild = await import("esbuild");
} catch {
  console.error("esbuild não encontrado — rode npm ci (ele vem com o vite).");
  process.exit(2);
}

const BASE = dirname(HTML);
const semQuery = (s) => s.split("?")[0];
const faltando = [];
const gerados = new Map(); // caminho publicado -> conteúdo (Buffer)

function ler(rel) {
  const p = join(BASE, rel);
  if (!existsSync(p)) {
    faltando.push(relative(".", p));
    return null;
  }
  return readFileSync(p);
}
function compilar(src, nome) {
  return esbuild.transformSync(src, {
    loader: "jsx",
    jsx: "transform",
    jsxFactory: "React.createElement",
    jsxFragment: "React.Fragment",
    sourcefile: nome,
    charset: "utf8",
  }).code;
}
// O pacote antigo repetia no topo de cada segmento o cabeçalho de comentários do arquivo; mantém.
function cabecalho(src) {
  const out = [];
  for (const l of src.split("\n")) {
    if (!l.startsWith("//")) break;
    out.push(l);
  }
  return out.join("\n");
}

let html = readFileSync(HTML, "utf8");
const lazy = [];
const linhas = html.split("\n");
const saida = [];
let pulandoCarregador = false;

for (const linha of linhas) {
  if (pulandoCarregador) {
    if (linha.trim() === "</script>") pulandoCarregador = false;
    continue;
  }
  if (linha.trim() === "window.__oiLazyDone = false;" && saida.at(-1)?.trim() === "<script>") {
    saida.pop();
    saida.push(CARREGADOR);
    pulandoCarregador = true;
    continue;
  }
  if (/@babel\/standalone/.test(linha)) continue;

  let m;
  if ((m = linha.match(/^<script type="text\/oi-lazy" data-src="([^"]+)"><\/script>\s*$/))) {
    lazy.push(m[1]);
    continue;
  }
  if ((m = linha.match(/^<script type="text\/babel" src="([^"]+)"><\/script>\s*$/))) {
    const nome = semQuery(m[1]);
    const src = ler(nome);
    if (src) gerados.set("js/" + basename(nome).replace(/\.jsx$/, ".js"), Buffer.from(compilar(src.toString("utf8"), nome)));
    saida.push(`<script defer src="js/${basename(nome).replace(/\.jsx$/, ".js")}"></script>`);
    continue;
  }
  let l = linha;
  l = l.replace(/<script src="(?!https?:)([^"]+\.js)(?:\?[^"]*)?"><\/script>/g, (_, f) => {
    const src = ler(f);
    if (src) gerados.set("js/" + basename(f), src);
    return `<script src="js/${basename(f)}"></script>`;
  });
  l = l.replace(/(<link rel="stylesheet" href=")(?!https?:)([^"'+]+\.css)(?:\?[^"]*)?"/g, (_, a, f) => {
    const src = ler(f);
    if (src) gerados.set("css/" + basename(f), src);
    return `${a}css/${basename(f)}"`;
  });
  l = l.replace(/<link rel="preload" as="image" href="(?!https?:)([^"]+)"/g, (t, f) => {
    const src = ler(f);
    if (src) gerados.set(basename(f), src);
    return t.replace(f, basename(f));
  });
  // a base do DS passa a ser fixa: os arquivos dele vão junto, em ds/
  if (l.includes("window.__OI_DS_BASE__=location.pathname")) {
    l = l.replace(/window\.__OI_DS_BASE__=[^;]+;/, "window.__OI_DS_BASE__='ds/';");
  }
  // a promoção da folha da rota compara o nome do arquivo, que agora vem com css/ na frente
  if (l.includes('var n = (l.getAttribute("href") || "").split("?")[0];')) {
    l = l.replace('.split("?")[0];', '.split("?")[0].replace(/^css\\//, "");');
  }
  saida.push(l);
}

// segmentos do carregamento diferido: mesmo escopo próprio que o carregador do HTML dá a cada arquivo
const segmentos = [];
for (const f of lazy) {
  const nome = semQuery(f);
  const src = ler(nome);
  if (!src) continue;
  const texto = src.toString("utf8");
  const js = compilar(texto, nome).trimEnd();
  segmentos.push(`try{(function(){\n${cabecalho(texto)}\n${js}\n})();}catch(e){console.error("Falha ao carregar ${f}",e);}`);
}
gerados.set("js/lazy-bundle.js", Buffer.from(segmentos.join("\n")));

// Design System do git
for (const f of ["colors_and_type.css", "cockpit_domains.css", "_ds_bundle.js"]) {
  const p = join(DS, f);
  if (!existsSync(p)) faltando.push(p);
  else gerados.set("ds/" + f, readFileSync(p));
}
const fontes = join(DS, "assets/fonts");
if (existsSync(fontes)) {
  for (const f of readdirSync(fontes)) if (f.endsWith(".woff2")) gerados.set("ds/assets/fonts/" + f, readFileSync(join(fontes, f)));
} else faltando.push(fontes);

if (faltando.length) {
  console.error(`✗ ${faltando.length} arquivo(s) citado(s) pelo HTML não existem no repo:`);
  for (const f of faltando) console.error("  - " + f);
  process.exit(1);
}

let commit = "desconhecido";
let buildCommit = "desconhecido";
let dataCommit = new Date().toISOString().slice(0, 10);
let sujo = null;
try {
  // O commit que vale é o último que mexeu na fonte (HTML + DS), não o HEAD: o build pode rodar
  // num branch cujo commit some no squash, e a pergunta "de onde saiu esta página" é sobre a fonte.
  commit = execFileSync("git", ["log", "-1", "--format=%H", "--", BASE, DS], { encoding: "utf8" }).trim() || commit;
  buildCommit = execFileSync("git", ["rev-parse", "HEAD"], { encoding: "utf8" }).trim();
  dataCommit = execFileSync("git", ["log", "-1", "--format=%cs", "--", BASE], { encoding: "utf8" }).trim() || dataCommit;
  sujo = execFileSync("git", ["status", "--porcelain", "--", BASE, DS], { encoding: "utf8" }).trim() !== "";
} catch {}
// Bloco que o hook vista-publicada-padrao lê: a página publicada declara de que arquivo do git saiu.
const blocoVista = `<!-- vista:\ntema: prototipo${ROTA ? "-" + ROTA : ""}\nestado: viva\nmedido_em: ${dataCommit}\ndono: ${HTML}\n-->\n`;
// A rota inicial entra antes de qualquer script do shell ler `oimpresso.route`.
const rotaInicial = ROTA
  ? `\n<script>try{var _r=localStorage.getItem("oimpresso.route");if(!_r||_r.indexOf(${JSON.stringify(ROTA)})!==0)localStorage.setItem("oimpresso.route",${JSON.stringify(ROTA)})}catch(e){}</script>`
  : "";
const escapa = (t) => t.replace(/&/g, "&amp;").replace(/</g, "&lt;");
let corpo = saida.join("\n");
if (TITULO) corpo = corpo.replace(/<title>[^<]*<\/title>/, `<title>${escapa(TITULO)}</title>`);
const indexHtml =
  blocoVista + corpo.replace("<head>", `<head>\n<meta name="oi-fonte" content="${HTML}@${commit}"/>${rotaInicial}`);
gerados.set("index.html", Buffer.from(indexHtml));

const sha = (b) => createHash("sha256").update(b).digest("hex");
const manifesto = {
  fonte: HTML,
  design_system: DS,
  commit,
  build_commit: buildCommit,
  arvore_suja: sujo,
  rota_inicial: ROTA,
  titulo: TITULO,
  gerado_em: new Date().toISOString(),
  arquivos: Object.fromEntries([...gerados].sort(([a], [b]) => a.localeCompare(b)).map(([k, v]) => [k, sha(v)])),
};

// Só limpa pasta que é saída deste build (tem fonte.json) ou vazia: --out . apagaria o repo.
if (existsSync(OUT) && readdirSync(OUT).length && !existsSync(join(OUT, "fonte.json"))) {
  console.error(`✗ ${OUT} não está vazia e não é saída deste build (sem fonte.json) — escolha outra pasta.`);
  process.exit(2);
}
rmSync(OUT, { recursive: true, force: true });
for (const [k, v] of gerados) {
  const p = join(OUT, k);
  mkdirSync(dirname(p), { recursive: true });
  writeFileSync(p, v);
}
writeFileSync(join(OUT, "fonte.json"), JSON.stringify(manifesto, null, 2) + "\n");

const kb = (n) => Math.round(n / 1024) + " KB";
console.log(`✓ ${gerados.size + 1} arquivos em ${resolve(OUT)}`);
console.log(`  fonte ${HTML} @ ${commit.slice(0, 10)}${sujo ? " (com alterações não commitadas — não publique)" : ""}`);
console.log(`  ${lazy.length} módulos no lazy-bundle (${kb(gerados.get("js/lazy-bundle.js").length)})`);
if (sujo) process.exitCode = 3;
