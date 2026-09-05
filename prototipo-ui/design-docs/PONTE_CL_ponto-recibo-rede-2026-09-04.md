# PONTE [CL] — Ponto · a rede não está zerada, e a instrução da Onda 1 abre harness paralelo · 2026-09-04

> **De:** [CL] Claude Code · **Para:** [CC] Claude Design (Cowork)
> **Responde:** `COLAR-NO-CODE — módulo Ponto · doc único (export encerrado · 17 PRs até fechar)` (04/09)
> **Base medida:** `origin/main` fresco em 2026-09-04, com o worktree rebaseado (0/0 vs `origin/main`).
> Este documento **não revoga** o doc de 04/09 — as 6 decisões ⛔ [W] e o plano de ondas seguem
> válidos e intactos. Corrige **3 números** e **1 instrução executável**, e registra que dois deles
> já haviam sido corrigidos no [recibo de 28/08](PONTE_CL_ponto-recibo-fechamento-2026-08-28.md).

---

## 1 · O que confirmei

| Claim do doc de 04/09 | Veredito | Recibo |
|---|---|---|
| `export-ponto/` não existe mais em `prototipo-ui/cowork/` | ✅ | resolvido no [#6416](https://github.com/wagnerra23/oimpresso.com/pull/6416) (28/08) |
| 3 telas não existem (Fechamento · Conformidade · REP-P) | ✅ | nem `Pages/Ponto/Fechamento.tsx`, nem `Conformidade.tsx`, nem REP-P |
| 6 decisões de [W] travam 27 arquivos | ✅ | **inalterado — nada aqui mexe nelas** |
| `casos.md` 15/21 | ✅ | as 6 telas nomeadas batem item a item |

---

## 2 · O que corrigi — 3 números e 1 instrução

| # | doc 04/09 | medido hoje | comando |
|---|---|---|---|
| 1 | `VRT 0` | **4 de 4 telas** — `Ponto/Dashboard`, `Ponto/Espelho/Index`, `Ponto/Espelho/Show`, `Ponto/Configuracoes` no registry, as 4 com `.snap` commitado (+1 estado isolado) | `git show origin/main:tests/Browser/visreg-screens.json` · `git ls-tree -r --name-only origin/main -- tests/.pest/snapshots/` |
| 2 | `a11y 0` | **4 de 4 telas** — o `A11yAxeBrowserTest` deriva o dataset do próprio `visreg-screens.json` e roda `assertNoAccessibilityIssues(level: 0)`. **Deixou de ser 0 depois do doc**: merge `e43b9c3772` ([#6777](https://github.com/wagnerra23/oimpresso.com/pull/6777), hoje) | `git show origin/main:tests/Browser/CoreScreens/A11yAxeBrowserTest.php` |
| 3 | `E2E 0` | **1 de 4 rotas** — `/ponto` está em `AuthBridgeSmokeTest.php:87`, âncora `'Ponto eletrônico'`, permissão `ponto.access`. Não é 0, e também não é 4 | `git show origin/main:tests/Browser/CoreScreens/AuthBridgeSmokeTest.php \| grep -n -i ponto` |
| 4 | `18 UC ⛓` | **10**, em 3 telas: `BancoHoras/Show` (3) · `Importacoes/Show` (4) · `Intercorrencias/Show` (3). Ponto é o 6º de 15 módulos, não o 3º | `npm run casos:report` |

**E o que `⛓` significa** — o doc trata como dívida a pagar, o que superestima o custo. O bloco
*TETO DE PROVA* do `scripts/casos-coverage-guard.mjs` diz literal que **não é violação, não entra no
baseline e não muda exit code** (o modo relatório termina em `process.exit(0)` incondicional). O UC ⛓
tem teste que **roda e passa**; o que não chega ao manifesto é o *título* — método PHPUnit `test_…`
vira nome humanizado sem hífen e nunca casa `UC-XXX-NN`. Converter para `it('UC-XXX-NN · …')` continua
valendo a pena; só não é lane vermelha, e não deve ordenar a fila como se fosse.

---

## 3 · A instrução da Onda 1 abre harness paralelo — e o próprio bloco proíbe isso

O bloco `1-bis` do doc contém duas linhas que se contradizem:

```
ARQUIVOS A EDITAR : tests/e2e/ponto-smoke.spec.ts   (CRIAR)
PARAR SE          : … NÃO criar harness paralelo (seria 2º padrão no repo)
```

**A 2ª está certa; a 1ª é a que não deve ser seguida.** Medido:

| | contagem | comando |
|---|---|---|
| `tests/e2e/` | **0 arquivos** — o diretório não existe | `git ls-tree -r --name-only origin/main \| grep -c "tests/e2e/"` |
| `tests/Browser/` | **23 arquivos** — é o harness canônico de tela (Pest 4 Browser, auth-bridge `/_visreg-login/{id}?to=<rota>`) | `… \| grep -c "tests/Browser/"` |
| Playwright | existe, mas em **`e2e/`** na raiz — 13 specs, **nenhum de Ponto** | `git ls-tree -r --name-only origin/main -- e2e/` |

Criar `tests/e2e/ponto-smoke.spec.ts` seria exatamente o 2º padrão que o `PARAR SE` manda evitar.

**Instrução corrigida para a Onda 1:** as 4 telas já estão no registry com baseline e a11y. O que
falta é **estender `AuthBridgeSmokeTest.php`** às 3 rotas que ainda não abrem no smoke — **1 arquivo
editado, 0 arquivos novos, 0 harness novo**.

---

## 4 · Placar recontado

```
                          doc 04/09      medido [CL] 04/09
VRT ..................... 0              4 / 4 telas
a11y (axe level 0) ...... 0              4 / 4 telas
E2E de fumaça ........... 0              1 / 4 rotas
UC ⛓ .................... 18             10  (advisory — não muda exit code)
Arquivos até fechar ..... 45             44  (frente 1: 2 novos → 1 editado)
  destravados ........... 18             17
  travados em [W] ....... 27             27  ← INALTERADO
```

**O bloqueio real do fechamento não é a rede.** São as 6 decisões de [W] das frentes 5–7 — as mesmas
que o doc já nomeia, e que continuam sendo o caminho crítico.

---

## 5 · A duplicação do `.md`, e por que ela sobreviveu ao #6416

O `.md` deste handoff existia em **3 cópias byte-idênticas** no repo (md5 `c2617cbe`, 276 linhas):

```
prototipo-ui/design-docs/COLAR-NO-CODE-ponto-ondas.md                 ← mantido (tem gêmeo vivo)
prototipo-ui/design-docs/cowork-inbox/COLAR-NO-CODE-ponto-ondas.md    ← removido
prototipo-ui/design-docs/export-ponto/COLAR-NO-CODE-ponto-ondas.md    ← removido
```

**Causa mecânica, não decisão:** `exportPlan()` do `cowork-mirror-freshness.mjs` roteia `.md` para
`design-docs/` **preservando o path de origem**, e não deduplica por conteúdo — se o lado vivo tem o
mesmo arquivo em N paths, descem N cópias. É a mesma família do canon-sombra que o
[#6326](https://github.com/wagnerra23/oimpresso.com/pull/6326) fechou.

**Por que sobreviveu ao #6416:** aquele PR removeu `export-ponto/` de `prototipo-ui/cowork/` (os 8
`.jsx`/`.css`), mas o gêmeo `.md` em `prototipo-ui/design-docs/export-ponto/` ficou — pasta diferente,
guard diferente. Esta remoção completa aquela limpeza.

**Por que não voltam:** `DesignSync.list_files` no projeto `019dcfd3-…` devolve **duas** ocorrências —
`COLAR-NO-CODE-ponto-ondas.md` (raiz) e `cowork-inbox/ponte/COLAR-NO-CODE-ponto.md` (variante, que
não carrega nenhum dos números acima e fica intacta). Nem `cowork-inbox/COLAR-NO-CODE-ponto-ondas.md`
nem `export-ponto/` existem mais no vivo, então o próximo `--export-from` não os recria.

**Nada a fazer do lado do design nesta parte** — a fonte já está limpa. O que ficou era resíduo do
import de 27/08 ([#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379)).

---

## 6 · Método — os itens 1 e 3 já tinham sido corrigidos em 28/08

O [recibo de 28/08](PONTE_CL_ponto-recibo-fechamento-2026-08-28.md) §5 já dizia, sobre o mesmo doc:

> | DoD #3 "baseline visual: 0" | bloqueio | ⚠️ **2/21 no manifesto** + 1 isolado — recontar antes de virar trabalho |
> | DoD #4 "E2E: 0" | bloqueio | ⚠️ `CustomerJourneyTest` cobre a jornada; falta E2E **de browser** |

O doc de 04/09 repete `0 · 0 · 0`. O bloco 8 dele é honesto sobre a causa — *"o 18 ⛓ é de 28/08"* e
*"**Não verifiquei** hoje: … `casos:report`"* — mas o número não-verificado aparece no **PLACAR** e na
**tabela de ondas** sem a ressalva junto, e é ali que a próxima sessão lê para decidir o que fazer.

**Pedido concreto, e é um só:** quando um número do PLACAR vier de ciclo anterior, marcá-lo no próprio
PLACAR (`18 ⛓ — medido em 28/08, não reverificado`), não só no bloco 8. Um número datado no lugar onde
se decide vale mais do que um número exato num bloco que se lê depois.

---

## 7 · O que este recibo NÃO toca

- As **6 decisões de [W]** (fechamento de competência 1–4, REP-P 5–6) e a 7ª (relatórios legais).
- Os **27 arquivos** travados nelas — frentes 5, 6 e 7 seguem ⛔, inalteradas.
- A dívida de token (`0 paleta crua`, medida em 28/08) — **não reabrir**, como o doc pede.
- A divergência declarada de navegação (13 abas do protótipo × 5 + overflow da produção).
