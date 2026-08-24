# [CC]→[CL] · GUARD DA REINCIDÊNCIA — as classes de erro recorrentes viram trava no git

> **Origem:** `O Cacador de Reincidencia.html` + `O Adversario do Protocolo.html` (red-team [CC], aprovado [W] 2026-06-16).
> **Tese:** todo erro que se repete aqui é "regra sem trava = intenção". A cura é máquina que recusa
> no instante da ação — baseline só-desce, controle-negativo, ratchet (igual aos guards que o repo já tem).
> Já mecanizei a classe C4 no Cowork (`memory-health.js` CHECK 8, rodado: pegou 18 órfãos + 19 refs mortas).
> **Esta entrega porta a REGRA pro git (memória permanente) e arma no CI as classes mecanizáveis (C3/C4/C5).**
>
> **AUTO-CONTIDO — código embutido, sem URL.** §10.4: valide vs `main`; estender, não reinventar (Regra 7).
> As classes C1/C2/C6 são rituais Cowork-side (comportamento [CC]) — NÃO são pra você codar; ficam no doc da Onda 1 como contexto.

---

## ONDA 1 — a REGRA das 6 classes (texto durável; [W]: "toda regra mora no git como fonte da verdade")
Adicionar ao `prototipo-ui/PROCESSO_MEMORIA_CC.md` a seção **"Caçador de reincidência — classes de erro & condição de morte"**:

| # | Classe | Condição de morte (trava) | Lado |
|---|---|---|---|
| C1 | Explicar em vez de fazer | Entregável tem nome/lugar fixos (`COLE_NO_CODE.md`); regenerado todo handoff sem pedir | ritual Cowork |
| C2 | Afirmar sem prova (✅ sem recibo) | Estado padrão `⚠ enfileirado`; só "pousou" após `CODE_NOTES@main` confirmar | ritual Cowork |
| C3 | Edição que funde/trunca bloco vizinho | **CI:** cabeçalho de item da fila bem-formado (1 marcador por bloco) | **git-gate** |
| C4 | Órfão & referência morta na fila | **CI:** prompt na pasta fora da fila = 🔴; citação sem arquivo = 🔴 | **git-gate** |
| C5 | Retrabalho por não conferir o `main` | **CI:** item ativo da fila exige carimbo `verificado vs main @SHA` | **git-gate** |
| C6 | Esquecer passo sob pressão de contexto | N passos → 1 ritual + `memory-health` como rede | ritual Cowork |

Regra-texto curta (durável): (1) criar `PROMPT_PARA_CODE_*.md` sem citá-lo acima da LINHA D'ÁGUA = proibido; (2) prompt durável é auto-contido, sem URL efêmera no corpo; (3) item ativo carrega `verificado vs main @SHA`; (4) só dizer "pousou" após `CODE_NOTES@main`; (5) ondas por padrão (1 tela/arquivo = 1 PR, Onda 0 = inventário).

## ONDA 2 — o GUARD no CI (mecaniza C3 + C4 + C5)
**Confirme o home primeiro (Regra 7):** leia `.github/scripts/cowork-inbox.py`, `.github/workflows/governance-gate.yml`,
`scripts/*-guard.mjs`. Se couber estender um deles, **estenda — não crie paralelo**. Confirme os paths reais
(`prototipo-ui/COWORK_NOTES.md` e onde os handoffs vivem). Baseline `*.json` congela a dívida atual; só NOVO trava.

**Lógica (porta o CHECK 8 do Cowork + C3 + C5):**
```js
// scripts/reincidencia-guard.mjs  (ou função no home existente)
import { readFileSync, readdirSync, existsSync } from 'node:fs';
const QUEUE = 'prototipo-ui/COWORK_NOTES.md';   // confirmar path
const DIR   = 'prototipo-ui-patch';              // confirmar onde vivem os prompts
const WATER = "LINHA D'ÁGUA";
const BASE  = new Set(JSON.parse(readFileSync('scripts/reincidencia-baseline.json','utf8')));

const notes  = readFileSync(QUEUE, 'utf8');
const active = notes.split(WATER)[0];            // só acima da linha d'água
const files  = existsSync(DIR) ? readdirSync(DIR).filter(f => /^PROMPT_PARA_CODE_.*\.md$/.test(f)) : [];
const fail = [];

// C4 — órfão (na pasta, fora da fila ativa) + ref morta (citado, sem arquivo)
const cited = [...new Set(active.match(/PROMPT_PARA_CODE_[\w-]+\.md/g) || [])];
files.filter(f => !active.includes(f) && !BASE.has(f)).forEach(f => fail.push(`C4 ÓRFÃO: ${f}`));
cited.filter(c => !files.includes(c) && !BASE.has(c)).forEach(c => fail.push(`C4 REF MORTA: ${c}`));

// C3 — cabeçalho de bloco bem-formado: cada item ativo começa com marcador "> " e
//      nenhuma linha funde dois itens (heurística: "> ... :** > **Artefato" na mesma linha,
//      ou texto antes de "(N PRs" sem quebra). Ajustar à convenção real do arquivo.
active.split('\n').forEach((ln, i) => {
  if (/\*\*Artefato:\*\*/.test(ln) && /:\*\*\s*>/.test(ln)) fail.push(`C3 BLOCO FUNDIDO L${i+1}`);
});

// C5 — todo item ativo (cada "> 🧭/🛡️/🌓 NOVO ... → [CL]") carrega "verificado vs main"
const heads = [...active.matchAll(/^>\s.*→\s*\[CL\].*$/gm)].map(m => m[0]);
const semCarimbo = heads.filter(h => !/verificad[oa]\s+vs\s+main/i.test(active.slice(active.indexOf(h), active.indexOf(h)+800)));
semCarimbo.forEach(h => fail.push(`C5 SEM CARIMBO vs-main: ${h.slice(0,70)}…`));

if (fail.length) { console.error('reincidencia-guard 🔴\n'+fail.join('\n')); process.exit(1); }
console.log(`reincidencia-guard 🟢 (baseline ${BASE.size})`);
```
**Auto-teste obrigatório (padrão do repo, L-31):** controle-negativo provando que cada regra (C3/C4/C5)
PEGA o caso ruim injetado E PASSA quando limpo. Workflow `paths:` cobrindo a fila + o diretório de handoffs.
Ratchet `warn → baseline` como os guards irmãos.

---

## Ondas & PRs
1. **Onda 1** (doc da regra) — aditivo, pode mergear autônomo.
2. **Onda 2** (guard C3/C4/C5 + baseline + auto-teste + workflow) — 1 PR.

Se virar regra de processo formal (ADR) = Tier 0 = **[W] numera**. Ao terminar: marque `[PROCESSADO AAAA-MM-DD]` aqui + retorno em `CODE_NOTES.md`. Cowork é read-only no git — o Code resolve com este pedido.
