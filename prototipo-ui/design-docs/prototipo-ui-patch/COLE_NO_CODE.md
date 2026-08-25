# COLE ISSO NO CLAUDE CODE (1 vez · auto-contido · sem URL)

Você é o **Claude Code [CL]** no repo `wagnerra23/oimpresso.com`, branch base `main`.
Regra do loop (§10.4): **valide tudo contra o `main` antes de codar** — se já estiver no `main`,
NÃO refaça. Trabalhe **em ondas: 1 arquivo = 1 PR**. Não cunhe número de ADR (Tier 0 = decisão
do Wagner). Ao terminar, escreva o retorno em `CODE_NOTES.md`.

> **Re-baseline feito pelo [CC] lendo `@main a273254628df` (2026-06-18):**
> - **Header Financeiro 100% no canon.** `Dashboard`, `Dre`, `ContasPagar` **e `Unificado`** já importam
>   `@/Components/PageHeader`. (O [CC] tinha enfileirado "migrar Unificado" por **leitura errada** — o arquivo
>   já estava no canon o tempo todo. Tarefa retirada. É a classe C5 que o guard abaixo trava.)
> - **Caixa Unificada "filtros em 2 botões" JÁ LANDOU** (Onda 1/2 2026-06-16). Nada a fazer.
>
> Sobra **1 tarefa**: o guard de reincidência. Tudo está NESTE texto — sem arquivo externo.

---

## TAREFA ÚNICA — Guard da Reincidência: classes de erro recorrentes viram trava no git

**Por quê:** os erros que se repetem neste loop têm a mesma cura — **máquina que recusa no instante
da ação.** Casos reais já vistos: prompt criado sem entrar na fila → tarefa invisível; fila citando
arquivo que não existe → PR errado; edição que funde dois blocos; **item enfileirado sem conferir o
`main` → retrabalho** (o próprio "migrar Unificado" acima nasceu assim: afirmação "ainda usa o header
deprecated" feita sem abrir o arquivo de verdade).

**Onda 1 (regra, doc — pode mergear autônomo):** adicione ao `PROCESSO_MEMORIA_CC.md` a seção
"Caçador de reincidência — classes de erro & condição de morte": tabela das 6 classes C1–C6 com a
trava de cada uma (C1/C2/C6 = rituais Cowork; C3/C4/C5 = git-gates). Regra-texto: (1) criar prompt sem
citá-lo acima da LINHA D'ÁGUA = proibido; (2) prompt durável é auto-contido, sem URL efêmera; (3) item
ativo carrega `verificado vs main @SHA` **com o arquivo realmente aberto, não pela memória**; (4) só
"pousou" após `CODE_NOTES@main`; (5) ondas por padrão.

**Onda 2 (guard CI):** **primeiro confirme o home** — leia `.github/scripts/cowork-inbox.py`
(existe; hoje só MOVE arquivo, não checa integridade), `.github/workflows/governance-gate.yml` e a
suíte `scripts/*-guard.mjs` (existe muita: `conformance-gate`/`foundation-guard`/`domain-dict-guard`/
`casos-coverage-guard`/`components-tree-guard`/`ds-canon-color-guard`…). Se couber **estender** um
deles, estenda (Regra 7 — não crie paralelo). Confirme os paths reais (fila + diretório de handoffs).
Ratchet/baseline `*.json` como os irmãos; só NOVO trava:
- **C4** — acima da LINHA D'ÁGUA: prompt no diretório fora da fila = ÓRFÃO 🔴; citação sem arquivo = REF MORTA 🔴.
- **C3** — cabeçalho de item bem-formado: nenhuma linha funde dois blocos.
- **C5** — todo item ativo (`> … → [CL]`) carrega `verificado vs main` no corpo; senão 🔴.
- Abaixo da linha d'água: ignorar. **Auto-teste com controle-negativo** (pega cada caso ruim injetado E passa quando limpo) — padrão do repo. Workflow `paths:` cobrindo a fila + o diretório de handoffs.

**Código do guard (Node — adapte ao padrão `scripts/*-guard.mjs`; baseline `*.json` congela a dívida atual, só NOVO trava):**
```js
// scripts/reincidencia-guard.mjs  (ou função dentro do home existente)
import { readFileSync, readdirSync, existsSync } from 'node:fs';
const QUEUE = 'COWORK_NOTES.md';                 // confirmar path real no repo
const DIR   = 'prototipo-ui-patch';              // confirmar onde os handoffs vivem no repo
const WATER = "LINHA D'ÁGUA";
const BASE  = new Set(JSON.parse(readFileSync('scripts/reincidencia-baseline.json','utf8')));

const notes  = readFileSync(QUEUE, 'utf8');
const active = notes.split(WATER)[0];            // só acima da linha d'água
const files  = existsSync(DIR) ? readdirSync(DIR).filter(f => /^PROMPT_PARA_CODE_.*\.md$/.test(f)) : [];
const fail = [];

// C4 — órfão (no diretório, fora da fila ativa) + ref morta (citado, sem arquivo)
const cited = [...new Set(active.match(/PROMPT_PARA_CODE_[\w-]+\.md/g) || [])];
files.filter(f => !active.includes(f) && !BASE.has(f)).forEach(f => fail.push(`C4 ÓRFÃO: ${f}`));
cited.filter(c => !files.includes(c) && !BASE.has(c)).forEach(c => fail.push(`C4 REF MORTA: ${c}`));

// C3 — nenhuma linha funde dois blocos de item (heurística; ajustar à convenção real)
active.split('\n').forEach((ln, i) => {
  if (/\*\*Artefato:\*\*/.test(ln) && /:\*\*\s*>/.test(ln)) fail.push(`C3 BLOCO FUNDIDO L${i+1}`);
});

// C5 — todo item ativo ("> … → [CL]") carrega "verificado vs main" no corpo
const heads = [...active.matchAll(/^>\s.*→\s*\[CL\].*$/gm)].map(m => m[0]);
heads.filter(h => !/verificad[oa]\s+vs\s+main/i.test(active.slice(active.indexOf(h), active.indexOf(h)+800)))
     .forEach(h => fail.push(`C5 SEM CARIMBO vs-main: ${h.slice(0,60)}…`));

if (fail.length) { console.error('reincidencia-guard 🔴\n'+fail.join('\n')); process.exit(1); }
console.log(`reincidencia-guard 🟢 (baseline ${BASE.size})`);
```
**Auto-teste obrigatório** (padrão do repo): controle-negativo provando que cada regra C3/C4/C5 PEGA o caso ruim injetado E PASSA quando limpo.
Se virar regra formal (ADR) = Tier 0 = **[W] numera**.

---

Fim. Comece pela Onda 1. Reporte cada PR em `CODE_NOTES.md`.
