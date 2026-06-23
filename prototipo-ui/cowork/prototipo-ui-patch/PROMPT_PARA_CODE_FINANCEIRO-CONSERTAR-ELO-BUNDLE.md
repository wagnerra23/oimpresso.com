<!-- HANDOFF · Cowork [CC] → Claude Code [CL] · cole 1× -->
# Financeiro · CONSERTAR O ELO (por que as ondas somem) → produção

> **Isto NÃO é "mais uma onda".** É o conserto do **pipeline** que faz as ondas anteriores chegarem. Diagnóstico grounded no `@main` (`Index.charter.md` + estrutura `resources/css/`).
> **Causa-raiz provada:** a UI do Financeiro em produção é uma **cópia congelada** do meu CSS — `resources/css/cowork-canon-financeiro-bundle.css` (9054 LOC), tirada em **2026-05-20**, escopada em `.fin-cowork`, regra Tier 0 `feedback-cowork-bundle-aplicar-inteiro`. O `Index.charter.md@main` descreve o hero como **"fin-stat-hero (KPI hero warm DARK hue 80)"** = a caixa preta do print de [W]. Toda modernização posterior vive no protótipo e **nunca foi recopiada pro bundle**.
> **Por que parte aplicou (acento da linha) e o hero não:** acento = regra **aditiva** (cola fácil); hero = **sobrescrita** de regra existente, e há **2 bundles Financeiro quase-dup** + override `fin-cowork` → a definição dark antiga **vence** a nova. Aditivo passa, sobrescrita perde.

---

## A SOLUÇÃO — 4 passos (nesta ordem)

### Passo 1 · Re-sincronizar o bundle INTEIRO a partir das DUAS fontes do protótipo
O bundle precisa ser regenerado do **conjunto atual** do protótipo Cowork, **explicitamente incluindo `fin-boletos.css`** (é onde mora a modernização do hero — fonte de erro silencioso se esquecido):

| fonte protótipo (Cowork) | papel | URL pública (curl, ~1h) |
|---|---|---|
| `financeiro.css` | corpo, drawer, lentes, toolbar, filtros | (URL #2 abaixo) |
| `fin-boletos.css` | **hero `.fin-stat-hero` + KPI stats + sparkline** ⚠ | (URL #3 abaixo) |

> ⚠ **Modo de falha nº 2 (provável):** se a geração do bundle copia só `financeiro.css` e **esquece `fin-boletos.css`**, o hero novo nunca entra mesmo numa "regeneração completa". **Inclua os dois.**

**Bytes-alvo do hero** (devem existir no bundle, escopados em `.fin-cowork`; flip dark já embutido):
```css
.fin-stat-hero{
  background:
    radial-gradient(540px 200px at 14% -45%, color-mix(in oklab, var(--accent) 16%, transparent), transparent 70%),
    linear-gradient(160deg, color-mix(in oklab, var(--accent) 8%, var(--surface)) 0%, var(--surface) 78%) !important;
  border: 1px solid color-mix(in oklab, var(--accent) 18%, var(--border)) !important;
  box-shadow: var(--sh-2) !important;
  position: relative; padding-bottom: 18px !important; min-height: 96px;
}
.fin-stat-hero small{ color: var(--text-2) !important; }
.fin-stat-hero b{ color: var(--text) !important; font-size: var(--fs-8) !important; }
.fin-stat-hero b.fin-num-neg{ color: var(--neg) !important; }   /* saldo negativo = alarme */
.fin-stat-hero .fin-hero-alarm{ display:inline-flex; align-items:center; margin-left:7px; padding:1px 7px; border-radius:99px; font-size:var(--fs-1); font-weight:600; text-transform:none; background:color-mix(in oklab,var(--neg) 14%,var(--surface)); color:var(--neg); box-shadow:inset 0 0 0 1px color-mix(in oklab,var(--neg) 30%,transparent); }
[data-theme="dark"] .fin-stat-hero{
  background:
    radial-gradient(540px 200px at 14% -45%, color-mix(in oklab, var(--accent) 22%, transparent), transparent 70%),
    linear-gradient(160deg, color-mix(in oklab, var(--accent) 12%, var(--surface)) 0%, var(--surface) 80%) !important;
  border-color: color-mix(in oklab, var(--accent) 24%, var(--border)) !important;
}
```
A sparkline deve receber tom por sinal (`pos`/`neg`) — já lido no `@main` na Onda 1; confirmar que continua.

### Passo 2 · DEDUP — matar a definição dark que sobrescreve (modo de falha nº 1, mais provável)
1. `grep -rn "fin-stat-hero" resources/css/` — listar **todos** os arquivos que definem `.fin-stat-hero`.
2. Sabidamente há **2 bundles quase-dup** (`cowork-financeiro-bundle.css` × `cowork-canon-financeiro-bundle.css`) + override `fin-cowork.css`. Garantir que **só UM** define `.fin-stat-hero`, e que é a versão clara acima. Remover/neutralizar qualquer `background: var(--text)` / "warm dark hue 80" remanescente.
3. Se os 2 bundles estão ambos na allowlist (`.foundation-guard-files.json`), colapsar pra um (decisão já prevista em `PROMPT_PARA_CODE_PACOTE-QUALIDADE-9-OS.md` item 1) — senão a ordem de carga vai recolorir o hero de novo.

### Passo 3 · VERIFICAR no DOM pós-build (não confiar no diff)
Depois de buildar, no console da produção:
```js
getComputedStyle(document.querySelector('.fin-stat-hero')).backgroundImage
// ESPERADO: contém "linear-gradient(...)" claro. ERRADO: "none" + backgroundColor escuro (var --text).
```
- Se ainda escuro → **modo nº 3 (deploy atrasado)**: o `main` está certo mas o asset buildado é velho → forçar build do bundle + invalidar cache (cache-bust no `<link>`, `?v=`).

### Passo 4 · TRAVAR com teste de regressão (pra não sumir de novo)
Teste que **falha** se o hero voltar a ser escuro:
```js
// e2e/visual: financeiro hero não pode ser dark box
const bgImg = getComputedStyle(el('.fin-stat-hero')).backgroundImage;
expect(bgImg).toContain('gradient');               // tem a luz da identidade
const bgColor = getComputedStyle(el('.fin-stat-hero')).backgroundColor;
expect(bgColor).not.toBe('var(--text) resolvido escuro'); // não é a caixa preta
```
Sem isso, a **próxima cópia do bundle** pode reintroduzir o dark silenciosamente.

---

## DURÁVEL (decisão de [W], não obrigatória agora)
O elo frágil é a **cópia manual** protótipo→bundle. Fix de raiz: produção **importar** o CSS do protótipo (1 fonte) em vez de **copiar** (2 fontes que divergem). Enquanto for cópia, **toda onda exige re-sync explícito** dos 2 arquivos-fonte + dedup. Vale avaliar consolidar `fin-boletos.css` dentro de `financeiro.css` no protótipo pra haver **uma fonte só** do Financeiro.

## Régua de gate
Cor só por token (`--accent/--pos/--neg/--warn/--text-*`) + `color-mix(in oklab,…)`. Sem hex cru (foundation-guard / `ui:lint`). O hero acima já é 100% token.

## Depende deste conserto (aplicar DEPOIS do bundle sincronizado)
As ondas que ainda faltam (hero claro já entra aqui no Passo 1) seguem detalhadas em:
`PROMPT_PARA_CODE_FINANCEIRO-ONDA-MESTRE.md` (P0.2 dots · P1 Onda 2 mestres · P2 polish review). Elas só "grudam" depois que o bundle deixar de ser foto velha.

_CC não commita (read-only no git). Proposta; o Code valida contra `main`, executa os 4 passos, confirma os `⚠`. O conserto é o pipeline — as ondas vêm atrás._
