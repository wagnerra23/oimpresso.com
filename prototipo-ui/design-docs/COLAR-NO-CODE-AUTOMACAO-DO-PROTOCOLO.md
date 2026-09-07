# COLAR NO CODE — AUTOMAÇÃO DO PROTOCOLO DE EXPORT (PR-A1…A7)

> **Pedido de [W] 2026-09-03:** automatizar o ciclo `MAPA → ALVO → EXPORT → PR → PLACAR → pacote`, e valer **dos dois lados** (repo e Cowork).
> **Método:** `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md`. **Ponte** — destino `prototipo-ui/` (root). Eu não commito.
> **Princípio:** nenhum dono novo. Cada PR abaixo **estende** máquina que já existe (`design-memory-gate.yml`, `cowork-ssot-guard`, `cowork-mirror-freshness`, `casos-gate`, `contrato-de-tela`, `design-spec-gen`, `prototipo-readiness`, `gerar-payload-partes`).
> **Risco:** 🟢 mecânico · 🟡 regra de domínio · 🔴 schema/CI crítico. 1 assunto por PR, ≤8 arquivos, ≤~350 linhas.

---

## Ordem (cada uma destrava a seguinte)

`A1 + A2` → `A5` → `A3` → `A4` → `A6` → `A7` → `A8`. A1 é a única que **não** depende de nada.

---

## PR-A1 · `ALVO` executável 🟢 — *primeiro da fila*

- **Cria:** `scripts/design-sync/alvo.mjs`.
- **Faz:** headless (Playwright já usado no repo? se não, `puppeteer-core` + Chrome do runner) abre o espelho servido, seta a rota, **espera `window.__oiLazyDone` E duas leituras iguais** de `document.querySelectorAll('*').length`, e roda a sonda: por seletor → nº de nós · nº de filhos · **ordem das classes dos filhos** · `getComputedStyle` dos campos pedidos · `scrollWidth × clientWidth` (truncamento) · retângulo (tamanho de alvo).
- **Modo `--mapa`:** colhe filhos diretos da raiz da view + classes repetidas ≥N e imprime no **stdout**. **Nunca grava arquivo** (mapa é comando — L-42 · ADR 0256).
- **Saída do modo alvo:** `prototipo-ui/contrato/<tela>.alvo.json` — *fonte de teste*, não retrato: é insumo do A3.
- **Aceite falsificável:** rodar 2× seguidas dá **byte-idêntico**; remover 1 filho no DOM via `--injetar-falha` faz o JSON mudar e o A3 reprovar (é o T5 do protocolo, embutido).
- **Aposenta:** eu medindo à mão e ditando números no chat.

## PR-A2 · sonda com caso de sanidade obrigatório 🟡

- **Faz:** todo cálculo derivado dentro do `alvo.mjs` (contraste, luminância, razão) roda antes um **caso de valor conhecido** e **aborta** se ele não bater.
- **Por que existe:** medido em 2026-09-03 — minha 1ª sonda leu `oklch(0.94 0.005 90)` com regex de `rgb()` e deu contraste **2,62** no `.fj-title`; a "correção" via `canvas.fillStyle` **não converte** oklch e repetiu o mesmo número, parecendo confirmação. Só a 3ª (OKLCH→OKLab→sRGB) vale: **10,84**, com sanidade branco-sobre-`--bg` = **15,52**.
- **Aceite:** sabotar a conversão faz o script **falhar**, não retornar número plausível.

## PR-A3 · `secao-check` no CI 🟢 (T2 · T3 · T6)

- **Cria:** `scripts/qa/secao-check.mjs`; **liga em** `.github/workflows/design-memory-gate.yml`.
- **Faz:** lê `<tela>.alvo.json` e compara com o render (preview/prod autenticada): contagem, **ordem**, tokens resolvidos. Reprova nomeando o ausente.
- **Vizinhança (T6):** roda também o alvo das seções **já fechadas** da mesma tela — regressão de vizinho vira falha de CI, não descoberta em review.
- **Aceite:** o PR que remove um slot do alvo fica **vermelho**; o que respeita fica verde 3× seguidas antes de virar required.

## PR-A4 · bateria A1–A12 de a11y, nos dois lados 🟡

- **Cria:** `scripts/qa/a11y-alvo.mjs` = **axe-core** + as sondas que o axe não faz: `DIV` clicável sem `role`/`tabindex` · `svg` em clicável sem `aria-hidden` nem nome · `aria-live` ausente onde há conteúdo dinâmico · overlay sem `role`/`aria-modal`/foco · `aria-selected|pressed` estático · contraste OKLCH (usa A2) · alvo <24×24.
- **Roda no protótipo E na tela.** Regra do protocolo: **o que falhar no protótipo corrige-se no build**, não vira pedido.
- **Baseline honesta (medida na Forja, 2026-09-03):** 23 `DIV` clicáveis · **66 de 66** svg anônimos · drawer sem foco · **0 de 6** abas com ARIA de estado · `aria-live` 0 · 4 falhas AA de contraste · 81 de 118 alvos <24px (⚪ decisão [W]).
- **Aceite:** número de violações **só pode cair** (ratchet, régua do `ds:report`).

## PR-A5 · pacote regenerado por máquina 🟢 — *o que me destrava de vez*

- **Cria:** workflow `cowork-bundle.yml` — no push a `prototipo-ui/cowork/**`, roda
  `node scripts/design-sync/gerar-payload-partes.mjs --root prototipo-ui/cowork --out sync/ --previous sync/bundle.manifest.json`
  e commita `sync/`.
- **Por que:** o gerador exige os arquivos **em disco** e por isso não roda do meu lado (ADR 0374) — **o runner tem disco**. Sintoma que a justifica: `sync/bundle.manifest.json` já ficou congelado com 3 ciclos de design fechados fora dele.
- **Consequência medida da divisão 1-arquivo-por-tela:** os 17 `forja-*.jsx` estão **todos abaixo do piso de ~48 KB**, então a rota avulsa `get_file` não serve mais — o pacote deixou de ser conveniência e é **a** rota.
- **Aceite:** manifesto com `date` do commit e N arquivos igual ao `ls` do diretório; o `github.md` recebe a linha `bundle regenerado (<data> · N arquivos)` **pelo bot**, não por mim.

## PR-A6 · placar como bot de PR 🟢

- **Faz:** `scripts/qa/placar.mjs` compara `alvo.json` × render e **comenta no PR**: `entregue X de Y · ausentes <classe> por <motivo>`; motivos vêm de um `ausentes:` declarado no `alvo.json` (sem endpoint · campo inexistente · decisão [W]).
- **Deriva as 3 métricas** sem máquina nova: cobertura cumulativa (Σ entregue ÷ Σ alvo) · reincidência por motivo · retrabalho (seção reaberta).
- **Aceite:** PR sem placar **não** mergeia (o comentário é o gate) — hoje "esquecer o placar" é grátis, e é o que faz a omissão sumir.

## PR-A7 · pedido gerado + sessão limpa por bootstrap 🟡

- **Faz:** `scripts/design-sync/pedido.mjs --tela X --secao Y` monta os 4 blocos (A identidade com **ancoragem dupla** · B não inventar · C alvo do `alvo.json` · D DoD) lendo `alvo.json` + `<Tela>.design-spec.json` + charter; emite o `.md` da ponte.
- **Sessão limpa (§2-quater, obrigatório):** `.github/ISSUE_TEMPLATE/onda.yml` + `.claude/commands/onda.md` carregam o **read-order** na abertura — a sessão nasce lida, não "lembra de ler".
- **Aceite — teste do estranho:** um executor sem histórico abre o pedido e não precisa perguntar nada sobre alvo, âncora, dado ou aceite.

---

## PR-A8 · playbook por módulo + placar da LISTA 🟢 — pedido [W] 2026-09-05 (`SINCRONIZAR <Mod>`, §12 do protocolo)

- **Estende** `scripts/qa/placar.mjs` (A6) com `--indice`: **o script já está escrito e testado como ponte** em `cowork-inbox/_scripts/placar-indice.mjs` (zero deps) + `cowork-inbox/_schema/playbook.schema.json`. Lê `cowork-inbox/<mod>/playbook/playbook.json` (fonte: threads · prefixo · dependências · decisões [W] · `provas[]` de tipos `arquivo | ausente | contem | nao_contem | json_com_chaves`, com variáveis `${PAGES}` que só [W] preenche), confere no repo e deriva o estado (`feito · em curso · proximo · pendente · bloqueada`) — **ninguém escreve estado**. Comenta no PR **`entregue X de Y · ausentes <thread> por <arquivo/motivo>`** e imprime `PRÓXIMO:` (deps de thread feitas + decisões respondidas + nenhuma variável nula). `--todos 'cowork-inbox/*/playbook/playbook.json'` soma módulos = unificador. Hoje o placar é por tela; "o Code terminou a lista inteira?" não tem máquina.
- **Estende** `.claude/commands/onda.md` (A7) com `--thread NN`: a sessão limpa nasce com o `NN-*.md` + read-order carregados (bloco de abertura de `ponte/03`).
- **Regras herdadas, não novas:** 1 thread = 1 prefixo (Lei 1) · estado só em `_saida` (Lei 2) · 1 PR por thread (Lei 3) · arquivos proibidos (Lei 4). Índice vive em `cowork-inbox/`, nunca em `cowork/` (R1).
- **Aceite falsificável (T5 da lista):** remover uma `prova:` do repo faz X cair para X−1 **nomeando a thread**; `_saida` ausente reprova a thread mesmo com PR mergeado.
- **Depende de:** A6 (placar) e A7 (bootstrap). **Não automatiza:** qual seção entra (julgamento) e o T7.

---

## O que NÃO se automatiza (e não deve)

- **Decisões [W]:** alocação/label no sidebar · motor do gantt (`@svar-ui/react-gantt` × `.fj-g-*`: 163 dependências viram setas) · alvo de toque em ERP denso · quais capacidades entram.
- **Merge de `.tsx`** — humano por ADR 0283.
- **Qual seção entra na onda** — é julgamento; automatizar julgamento é o erro que o resto do protocolo evita.
- **Dizer "está igual"** — continua sendo `design-diff --compare --check` nos dois renders (T7), que o A3 **alimenta** mas não substitui.

## Bloqueio herdado que o A3 depende

O `--compare` desta área é **medição órfã**: aborta com *"exige um snapshot.json existente"*. O A3 precisa de um job que **gere e versione o snapshot** por seção — sem isso, T7 segue não-afirmável por ninguém.


## Anexos do PR-A8 — landing (só `.md` roteia pelo DesignSync; estes dois arquivos viajam AQUI e o [CL] os cria nos paths abaixo)

> Gerados de `cowork-inbox/_schema/playbook.schema.json` e `cowork-inbox/_scripts/placar-indice.mjs` deste projeto em 2026-09-06 — **não editar aqui**; regenerar de lá. Testados com repo simulado em 2 módulos (HRM 11 threads · Ponto 12): flat/pasta via `um_de`, T5 nomeando thread+arquivo, D4 `Inertia::render` como prova, contrato fora do schema nomeando chaves, thread pela metade nomeando o que falta, **prova de preservação (`guarda`)** não inflando "em curso", `--todos` somando módulos.

### A8.1 · `prototipo-ui/design-docs/cowork-inbox/_schema/playbook.schema.json`
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "cowork-inbox/_schema/playbook.schema.json",
  "title": "Playbook de módulo (SINCRONIZAR <Mod>) — fonte do placar da lista",
  "description": "Um por módulo, EMBUTIDO como primeiro bloco ```json do cowork-inbox/<mod>/playbook/00-INDICE.md (só .md roteia pelo DesignSync; .json solto não chega). Declara threads e PROVAS; o ESTADO nunca é escrito — é derivado por placar-indice.mjs lendo o repo (Lei 2 por construção).",
  "type": "object",
  "required": ["modulo", "sha", "gerado", "threads"],
  "additionalProperties": false,
  "properties": {
    "$schema": { "type": "string" },
    "modulo": { "type": "string", "minLength": 1 },
    "sha": { "type": "string", "pattern": "^[0-9a-f]{7,40}$", "description": "árvore/commit do main lida quando o playbook nasceu" },
    "gerado": { "type": "string", "format": "date" },
    "absorve": { "type": "array", "items": { "type": "string" }, "description": "pedidos anteriores do módulo que este playbook substitui (anti-scatter)" },
    "variaveis": {
      "type": "object",
      "description": "placeholders usados em paths (${NOME}). null = decisão pendente; prova com variável nula fica 'indefinida' e a thread não vira 'feito'.",
      "additionalProperties": { "type": ["string", "null"] }
    },
    "decisoes": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["id", "pergunta", "respondida"],
        "additionalProperties": false,
        "properties": {
          "id": { "type": "string" },
          "pergunta": { "type": "string" },
          "respondida": { "type": "boolean" },
          "resposta": { "type": "string" },
          "define": { "type": "string", "description": "nome da variável que esta decisão preenche" },
          "destrava": { "type": "array", "items": { "type": "string" } }
        }
      }
    },
    "threads": {
      "type": "array",
      "minItems": 1,
      "items": {
        "type": "object",
        "required": ["id", "titulo", "dono", "arquivo", "prefixo", "provas"],
        "description": "_saida-NN.md na pasta do playbook é PROVA IMPLÍCITA de toda thread (sem ela nada é 'feito'). Provas explícitas = evidência de trabalho NOVO no repo — nunca arquivo que já existia antes da thread (isso é reuso e vai em nao_toca/nota).",
        "additionalProperties": false,
        "properties": {
          "id": { "type": "string", "pattern": "^[0-9]{2}$" },
          "titulo": { "type": "string" },
          "dono": { "type": "string", "enum": ["CC", "CL", "W", "W+CL", "CC->CL"] },
          "vaga": { "type": "integer", "minimum": 1 },
          "arquivo": { "type": "string", "pattern": "\\.md$", "description": "NN-*.md da thread, na mesma pasta" },
          "prefixo": { "type": "array", "items": { "type": "string" }, "description": "Lei 1 — só aqui a thread escreve" },
          "nao_toca": { "type": "array", "items": { "type": "string" } },
          "depende_threads": { "type": "array", "items": { "type": "string", "pattern": "^[0-9]{2}$" } },
          "depende_decisoes": { "type": "array", "items": { "type": "string" } },
          "bloqueio": { "type": "string", "description": "declarado por decisão [W] (ex.: D2 → ADR própria). Thread bloqueada não é pendência do Code." },
          "nota_provas": { "type": "string" },
          "provas": {
            "type": "array",
            "minItems": 0,
            "items": {
              "type": "object",
              "required": ["tipo"],
              "additionalProperties": false,
              "properties": {
                "tipo": { "type": "string", "enum": ["arquivo", "ausente", "contem", "nao_contem", "json_com_chaves", "um_de"] },
                "path": { "type": "string" },
                "paths": { "type": "array", "items": { "type": "string" }, "description": "um_de: basta UMA existir (ex.: Essentials/Licencas.tsx flat OU Essentials/Licencas/Index.tsx — quem decide é o criar-tela.mjs)" },
                "padrao": { "type": "string", "description": "contem/nao_contem: texto literal procurado" },
                "chaves": { "type": "array", "items": { "type": "string" }, "description": "json_com_chaves: chaves obrigatórias no topo (ex.: alvo, secoes)" },
                "guarda": { "type": "boolean", "description": "prova de PRESERVAÇÃO: já verdadeira antes da thread e obrigatória no fim (ex.: arquivo vivo não pode sumir; termo proibido não pode voltar). Conta para feito, nunca para em curso." },
                "nota": { "type": "string" }
              }
            }
          }
        }
      }
    }
  }
}
```

### A8.2 · `scripts/qa/placar-indice.mjs` (ou fundir como `--indice` em `scripts/qa/placar.mjs`)
Uso: `node scripts/qa/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/<mod>/playbook/00-INDICE.md --root . --proximo` · todos: `--todos 'prototipo-ui/design-docs/cowork-inbox/*/playbook/00-INDICE.md'` · exit 1 enquanto houver thread não-feita e não-bloqueada (gate).
```js
#!/usr/bin/env node
// placar-indice.mjs — PLACAR da LISTA de um playbook (SINCRONIZAR <Mod>), derivado do repo. Zero dependências.
// Ponte pro Code (PR-A8): destino scripts/qa/placar-indice.mjs (ou flag --indice em scripts/qa/placar.mjs).
// Uso: node placar-indice.mjs --indice <00-INDICE.md | playbook.json> --root . [--proximo] [--json] [--todos 'prototipo-ui/design-docs/cowork-inbox/*/playbook/00-INDICE.md']
//   --indice aceita .json OU .md: no .md a fonte é o PRIMEIRO bloco ```json (só .md roteia pelo DesignSync — o JSON viaja embutido).
// Estado NUNCA é lido da fonte — é calculado das provas + do _saida-NN.md (Lei 2 por construção).
// _saida-NN.md é prova IMPLÍCITA de toda thread; provas explícitas são evidência de trabalho NOVO (arquivo pré-existente não é prova — falseava "em curso").
// prova com "guarda": true = PRESERVAÇÃO (já é verdade hoje e tem de continuar: ex. PDF vivo não pode sumir, selfie não pode voltar). Conta para "feito", NUNCA para "em curso".
// Aceite T5: apagar uma prova do repo derruba X→X−1 nomeando a thread e o arquivo.

export const ESTADOS = ["feito", "em curso", "proximo", "pendente", "bloqueada"];

export function extrairIndice(texto, nome = "") {
  if (nome.endsWith(".json")) return JSON.parse(texto);
  const m = texto.match(/```json\s*\n([\s\S]*?)\n```/);
  if (!m) throw new Error(`nenhum bloco \`\`\`json em ${nome || "indice"}`);
  return JSON.parse(m[1]);
}

export function resolverPath(p, variaveis = {}) {
  let indefinida = false;
  const out = p.replace(/\$\{([A-Z0-9_]+)\}/g, (_, k) => {
    const v = variaveis[k];
    if (v === null || v === undefined) { indefinida = true; return `\${${k}}`; }
    return v.replace(/\/$/, "");
  });
  return { path: out, indefinida };
}

export function avaliarProva(prova, ctx) {
  if (prova.tipo === "um_de") {
    const res = (prova.paths || []).map((p) => resolverPath(p, ctx.variaveis));
    if (res.some((r) => r.indefinida)) return { ok: false, indefinida: true, path: res.map((r) => r.path).join(" | "), motivo: "variável não decidida" };
    const achado = res.find((r) => ctx.existe(r.path));
    return { ok: !!achado, path: achado ? achado.path : res.map((r) => r.path).join(" | "), motivo: achado ? "" : "nenhuma das formas existe" };
  }
  const { path, indefinida } = resolverPath(prova.path, ctx.variaveis);
  if (indefinida) return { ok: false, indefinida: true, path, motivo: "variável não decidida" };
  const existe = ctx.existe(path);
  switch (prova.tipo) {
    case "arquivo": return { ok: existe, path, motivo: existe ? "" : "arquivo ausente" };
    case "ausente": return { ok: !existe, path, motivo: existe ? "arquivo ainda existe" : "" };
    case "contem": { if (!existe) return { ok: false, path, motivo: "arquivo ausente" };
      const ok = ctx.ler(path).includes(prova.padrao); return { ok, path, motivo: ok ? "" : `não contém "${prova.padrao}"` }; }
    case "nao_contem": { if (!existe) return { ok: false, path, motivo: "arquivo ausente" };
      const ok = !ctx.ler(path).includes(prova.padrao); return { ok, path, motivo: ok ? "" : `ainda contém "${prova.padrao}"` }; }
    case "json_com_chaves": { if (!existe) return { ok: false, path, motivo: "arquivo ausente" };
      let j; try { j = JSON.parse(ctx.ler(path)); } catch { return { ok: false, path, motivo: "JSON inválido" }; }
      const faltam = (prova.chaves || []).filter((k) => !(k in j));
      return { ok: faltam.length === 0, path, motivo: faltam.length ? `faltam chaves ${faltam.join(", ")}` : "" }; }
    default: return { ok: false, path, motivo: `tipo desconhecido ${prova.tipo}` };
  }
}

export function avaliar(indice, ctx) {
  const dir = ctx.dirPlaybook; // pasta onde vivem NN-*.md e _saida-NN.md
  const decis = Object.fromEntries((indice.decisoes || []).map((d) => [d.id, d]));
  const byId = {};
  const linhas = indice.threads.map((t) => {
    const provas = t.provas.map((p) => ({ ...p, ...avaliarProva(p, { ...ctx, variaveis: indice.variaveis || {} }) }));
    const saida = ctx.existe(`${dir}/_saida-${t.id}.md`);
    const provasOk = provas.every((p) => p.ok);
    const algumaOk = provas.some((p) => p.ok && !p.guarda);
    const decisPend = (t.depende_decisoes || []).filter((id) => !(decis[id] && decis[id].respondida));
    let estado;
    if (t.bloqueio) estado = "bloqueada";
    else if (saida && provasOk) estado = "feito";
    else if (saida || algumaOk) estado = "em curso";
    else estado = "pendente";
    const l = { id: t.id, titulo: t.titulo, dono: t.dono, vaga: t.vaga ?? null, estado, saida, provas, decisPend,
      depende_threads: t.depende_threads || [], ausentes: provas.filter((p) => !p.ok).map((p) => `${p.path} (${p.motivo})`) };
    byId[t.id] = l; return l;
  });
  for (const l of linhas) {
    if (l.estado === "bloqueada" || l.estado === "feito") continue;
    const depsOk = l.depende_threads.every((id) => byId[id] && byId[id].estado === "feito");
    const semIndef = !l.provas.some((p) => p.indefinida);
    l.executavel = depsOk && l.decisPend.length === 0 && semIndef;
    if (l.executavel && l.estado === "pendente") l.estado = "proximo";
  }
  const cont = Object.fromEntries(ESTADOS.map((e) => [e, linhas.filter((l) => l.estado === e).length]));
  const total = linhas.length;
  return { modulo: indice.modulo, sha: indice.sha, total, feito: cont.feito, cont, linhas,
    resumo: `${indice.modulo}: entregue ${cont.feito} de ${total} · próximo ${cont.proximo} · em curso ${cont["em curso"]} · pendente ${cont.pendente} · bloqueada ${cont.bloqueada}`,
    ausentes: linhas.filter((l) => l.estado !== "feito" && l.estado !== "bloqueada").map((l) => `${l.id} ${l.titulo} — ${l.saida ? "" : "sem _saida; "}${l.decisPend.length ? "decisão pendente " + l.decisPend.join(",") + "; " : ""}${l.ausentes.join("; ")}`) };
}

// --- CLI ---
const isMain = typeof process !== "undefined" && process.argv && process.argv[1] && import.meta.url.endsWith(process.argv[1].split(/[\\/]/).pop());
if (isMain) {
  const fs = await import("node:fs"); const path = await import("node:path");
  const arg = (k, d) => { const i = process.argv.indexOf(k); return i > -1 ? process.argv[i + 1] : d; };
  const root = path.resolve(arg("--root", "."));
  const alvos = process.argv.includes("--todos") ? (fs.globSync ? fs.globSync(arg("--todos"), { cwd: root }) : []) : [arg("--indice")];
  const ctxBase = { existe: (p) => fs.existsSync(path.join(root, p)), ler: (p) => fs.readFileSync(path.join(root, p), "utf8") };
  let exit = 0;
  for (const idxPath of alvos) {
    const indice = extrairIndice(fs.readFileSync(path.join(root, idxPath), "utf8"), idxPath);
    const r = avaliar(indice, { ...ctxBase, dirPlaybook: path.dirname(idxPath) });
    if (process.argv.includes("--json")) console.log(JSON.stringify(r, null, 2));
    else {
      console.log(r.resumo);
      for (const l of r.linhas) console.log(`  ${l.id} [${l.estado.padEnd(9)}] ${l.titulo}${l.estado === "feito" || l.estado === "bloqueada" ? "" : " — " + (l.ausentes[0] || (l.saida ? "" : "sem _saida"))}`);
      if (process.argv.includes("--proximo")) { const p = r.linhas.filter((l) => l.estado === "proximo"); console.log(p.length ? `PRÓXIMO: ${p.map((l) => l.id + " " + l.titulo + " [" + l.dono + "]").join(" · ")}` : "PRÓXIMO: nenhum executável — decisões pendentes: " + [...new Set(r.linhas.flatMap((l) => l.decisPend))].join(", ")); }
    }
    if (r.feito < r.total - r.cont.bloqueada) exit = 1;
  }
  process.exit(exit);
}
```
