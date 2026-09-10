---
date: "2026-09-06"
topic: "Refutação GT-G5 r3 do lote PR #6914 (13 arquivos Estoque + Manufacturing: 6 gap.md + 6 map.json + _STATUS-GENERATED) — 565 itens medidos contra origin/main, 4 refutados (0,71%), PII 0 hits, 7/7 controles OK"
authors: ["C"]
prs: [6914]
outcomes:
  - "565 itens verificados em 5 grupos contra origin/main 5baadae608; 4 refutados (2 frontmatter `prototipo:` com 1º token ≠ âncora computada; 2 afirmações sobre código contraditas pelo .tsx) — error_rate 0,71% < 2%"
  - "Scan PII nas 1.484 linhas `+` de memory/requisitos: 7 padrões × 0 hits, 7/7 controles positivos casaram"
  - "Máquinas: requisitos-status Manufacturing --check rc=0 · design-code-map-check --check --strict rc=0 · doc-id-index --check-collisions rc=0 · plans-index --check rc=1 por drift PRÉ-EXISTENTE em origin/main (RecurringBilling/cobranca-recorrente-planos-gap.md, fora do lote)"
---

# Refutação GT-G5 · rodada r3 · lote PR #6914

## Contexto

**Base:** `origin/main` = `5baadae60806d5ed9674a8d2ee9cecaa3f1825a1` · **HEAD:** `ee1b37824a0dde0b14ab3e163fe4ea7b0df8cb35` · `git rev-list --left-right --count origin/main...HEAD` = `0 9` (HEAD é descendente direto; merge-base = origin/main) · **repo raso:** `false` · **sessão fresca:** sim — instância nova, zero contexto do gerador; NÃO abri `memory/sessions/*refutacao*` (os r1/r2/base deste mesmo PR aparecem no diff e ficaram fechados) nem `memory/handoffs/` de hoje; NÃO li corpo de PR/commit. Refutador: Fable 5.1 (tier máximo).

Regra de medição: os arquivos-fonte citados (`prototipo-ui/cowork/*.jsx`, `resources/js/Pages/**/*.tsx`, charters, scripts) são **idênticos** entre HEAD e origin/main (`git diff --name-status origin/main HEAD -- resources prototipo-ui` = vazio; o diff do PR toca só `memory/` e 3 JSONs de estado em `scripts/`). Logo ler o working tree = ler origin/main para eles. Existência de path por `git ls-tree origin/main -- <path>`; controle negativo `path/inexistente/controle-negativo.md` → `MISSING` (e o link relativo intra-lote `stock-adjustment-index-gap.md` → `MISSING` em origin/main, esperado: é arquivo `A` do próprio lote).

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1 = tier máximo; igualdade só aceita no teto)
- [x] Amostra: 100% anchors (tipo do lote = anchors; sem prosa destilada amostrada → sem seed)
- [x] Cada item verificado contra o código real em origin/main, não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff — 7 padrões manuais + 7 controles positivos (gitleaks não cobre PII BR)
- [x] `error_rate_pct` calculado e < 2 → **0,71**
- [ ] Entry no ledger — NÃO é deste refutador (mandato: não escrever no ledger); fica pro workflow

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **13 `A`** (os 13 do mandato, re-medidos). Fora de `memory/requisitos` o PR ainda toca `memory/sessions/2026-09-06-refutacao-gt-g5-lote-6914{,-r1,-r2}.md` (não abertos) e `M` em `scripts/design-sync/state/application-report.json`, `scripts/design-sync/state/applications.json`, `scripts/governance/.cowork-freshness-ledger.json` (ver observações).

Partes por map.json (= linhas de tabela por gap): adjustment-create 10 · adjustment-index 13 · transfer-create 12 · transfer-index 15 · manufacturing-index 12 · manufacturing-recipes 14 = **76**.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---:|---:|---:|---|
| 1 · Âncora existe em origin/main | 193 | 193 | 0 | 17 paths de frontmatter (6 `tela_viva` + 11 arquivos de `prototipo:`) + 152 (`prototipo.arquivo` + `vivo.arquivo` das 76 partes) + 24 paths/links/símbolos citados na prosa (charters, ADR 0374 ×3, ADR 0264, proibicoes.md, `estoque-contagem.jsx`, `Movimentacao.casos.md`, `_components/FichaPrint.tsx`, ledger ×3, PR #6927, 2 charters `Officeimpresso/Logs`, `StockAdjustmentController@store`, `StockTransferController@store`/`updateStatus`, `ProductionService`, `window.CliTabs`) — todos `blob` via `git ls-tree`/`git grep` em origin/main; 0 sob `Components/**` apontado como âncora de tela |
| 2 · Âncora não revogada + frontmatter lido pelo consumidor | 73 | 71 | 2 | `ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork` ×6 → todos `âncora ✓` (5 por `bundle_source`, Recipes por `related_prototype`), nenhum charter marca REVOGADA/MIS-ANCHOR (grep 0/6); 30 chaves de frontmatter dos gaps (`id·tela·prototipo·tela_viva·gerado_em` ×6) conferidas contra `fmVal` de `gerar-contrato.mjs` (lê `tela`, `prototipo` 1º token, `tela_viva`) e `resolverArquivosPrototipo` de `gerar-map.mjs`; 1 `authority: generated`; 36 chaves top-level dos map.json (regen idêntica) |
| 3 · Ação × veredito da prosa + afirmações sobre código | 143 | 141 | 2 | 76 linhas de tabela + 26 claims de blockquote lidas contra o `.tsx`/`.jsx` real (toda linha citada aberta e conferida: 6 `.tsx` inteiros + todos os ranges dos 4 `.jsx`) + 41 linhas derivadas do `_STATUS-GENERATED.md` (placar 6 + backlog 7 + UC 28) validadas por `requisitos-status.mjs Manufacturing --check` rc=0 |
| 4 · Célula íntegra + `acao` == map.json | 152 | 152 | 0 | `gerar-map.mjs` re-rodado sobre os 6 gaps e comparado chave a chave (script `cmp.mjs`): `id`, `status`, `acao`, `_acionavel`, `vivo.*`, `prototipo_sha`, `gap_fonte`, `tela` **idênticos** nos 76; única diferença = `prototipo.linhas` (TODO no esqueleto → preenchido no lote, comportamento previsto) e `prototipo.arquivo` escolhido entre os arquivos do frontmatter; 76 partes parseadas = 76 linhas (nenhum pipe cortando célula) |
| 5 · Máquina derivada | 4 | 4 | 0 | rc literais abaixo; o único rc≠0 (`plans-index`) é drift pré-existente fora do lote |
| **Total** | **565** | **561** | **4** | |

## REFUTADOS

### R1 · `memory/requisitos/Estoque/stock-adjustment-create-gap.md` · frontmatter `prototipo:` (grupo 2)

- **Afirmação do lote:** `prototipo: prototipo-ui/cowork/estoque-forms.jsx + estoque-page.jsx` — e, no mesmo arquivo, blockquote l.11: *"Âncora resolvida por bundle_source … `estoque-page.jsx`"*.
- **O que origin/main diz:** `prototipo-ui/gerar-map.mjs:349-351` (docblock do cross-check): *"o gap pode citar arquivos extra tipo -ops.jsx, mas o 1º arquivo deve ser a âncora"*. A âncora computada (`ancora.mjs StockAdjustment/Create` → `estoque-page.jsx`, via `bundle_source: estoque-page.jsx` em `resources/js/Pages/StockAdjustment/Create.charter.md:6`) NÃO é o 1º token. O consumidor real lê exatamente esse token: `gerar-contrato.mjs:124` `fonte = fmVal(fm,'prototipo').split(...)[0]` → `estoque-forms.jsx`; `gerar-map.mjs` usa `arquivosPrototipo[0]` como `principal` e avisa em stderr: *"⚠️ âncora computada do charter … não cita prototipo-ui/cowork/estoque-forms.jsx"*. Os dois gaps de Index do mesmo lote põem `estoque-page.jsx` primeiro (forma correta), e os 5 gaps com `+` já em origin/main (`git grep -E '^prototipo:.*[+]' origin/main -- 'memory/requisitos/*-gap.md'` = 5 de 42) todos põem o `-page.jsx` primeiro.
- **Por que é erro do lote:** a chave é lida por máquina e o 1º token contradiz a âncora que o próprio arquivo declara; é inconsistência interna do lote (create × index) e com a convenção de origin/main. Correção = inverter a ordem. Ressalva honesta: o aviso do `gerar-map` também dispara nos gaps de Index (falso-positivo do cross-check, que só enxerga `related_prototype`, não `bundle_source`) — o que sustenta a refutação é a regra do docblock + o `fonte` do consumidor, não o aviso.

### R2 · `memory/requisitos/Estoque/stock-transfer-create-gap.md` · frontmatter `prototipo:` (grupo 2)

- **Afirmação do lote:** `prototipo: prototipo-ui/cowork/estoque-forms.jsx + estoque-page.jsx`; blockquote l.11 declara âncora `estoque-page.jsx`.
- **O que origin/main diz:** mesma regra e mesmo consumidor de R1 (`gerar-map.mjs:349-351`, `gerar-contrato.mjs:124`); `resources/js/Pages/StockTransfer/Create.charter.md:6` `bundle_source: estoque-page.jsx`; `gerar-map` stderr: *"não cita prototipo-ui/cowork/estoque-forms.jsx"*.
- **Por que é erro do lote:** idem R1 — 1º token ≠ âncora computada; o irmão `stock-transfer-index-gap.md` faz certo.

### R3 · `memory/requisitos/Estoque/stock-adjustment-create-gap.md` · linha "Totais (ajustado / recuperado / perda líquida)" (grupo 3)

- **Afirmação do lote:** *"Nada — paridade. **Mesmos três números e mesmos rótulos** no protótipo (`estoque-forms.jsx:231-233`)."*
- **O que origin/main diz:** vivo `resources/js/Pages/StockAdjustment/Create.tsx:320-323` rotula **"Total ajustado"** · "Recuperado" · "Perda líquida"; protótipo `prototipo-ui/cowork/estoque-forms.jsx:231-233` rotula **"Valor ajustado"** · "Recuperado" · "Perda líquida". 1 dos 3 rótulos difere.
- **Por que é erro do lote:** afirmação literal sobre o código ("mesmos rótulos") que o código contradiz. Os números batem; os rótulos não. Baixa severidade, mas é o que a linha afirma.

### R4 · `memory/requisitos/Estoque/stock-transfer-create-gap.md` · linha "Permissão por papel" (grupo 3)

- **Afirmação do lote:** *"`permissions.view_purchase_price` gateia custo unitário, subtotal **e frete**"* (célula "Estado no vivo").
- **O que origin/main diz:** `resources/js/Pages/StockTransfer/Create.tsx:338-347` — o campo "Frete" é renderizado **sem** condicional; só o fecho Subtotal/Frete/Total em `:348-357` está atrás de `permissions.view_purchase_price`. Custo unitário (`:275-277`, `:303-313`) e subtotal (`:278-280`, `:314-318`) são gateados; o **input** de frete, não.
- **Por que é erro do lote:** afirma gate que o código não tem — exatamente a classe "afirmação sobre código que origin/main contradiz". A linha vizinha "Frete e totais" (`Create.tsx:335-360`) é ambígua no mesmo ponto (campo + fecho "atrás de view_purchase_price"); contada como observação, não como 2º erro, para não punir duas vezes a mesma frase.

## Observações (não contadas)

1. **`.cowork-freshness-ledger.json` "rodada 38" é recibo que viaja NO PRÓPRIO PR**, não em origin/main: lá o ledger tem **37** entradas (última: `staleList: []`, `unchecked: 251`); a 38ª (`2026-09-06T11:48:39Z`, `files: 258`, `stale: 2`, `staleList: ["officeimpresso-page.jsx","oficina-page.jsx"]`, `unchecked: 256`, `verified: []`) é a `M` do PR. As três notas de frescor citam-na corretamente (os números batem, e `ancora.mjs Manufacturing/Recipes` lê a mesma rodada: "mediu 2 de 258"). Evidência consistente, mas **não independente** do lote.
2. **Veredito STALE por `TabBar`/`nav.mfg-tabs` × `window.CliTabs`** vem de `DesignSync.get_file` (Cowork vivo) — **não verificável em origin/main**. O que dá pra medir: `window.CliTabs` existe no espelho em 11 arquivos (`git grep -l CliTabs origin/main -- prototipo-ui`), e `estoque-page.jsx`/`manufacturing-page.jsx` de origin/main de fato usam `TabBar`/`nav.mfg-tabs` (l.258/371 e l.159). O tamanho citado do espelho (43.996 bytes) confere (`git cat-file -s`).
3. **`plans-index.mjs --check` rc=1** — regenerei com `--write`, o diff foi 1 entrada nova: `RecurringBilling/cobranca-recorrente-planos-gap.md` (basename casa `/plan/i`), arquivo que **já existe em origin/main** (blob `ba95ee9e…`) e **não está no lote**. Revertido com `git checkout --` (árvore limpa). Não é erro deste PR; é drift herdado.
4. Ranges com folga de 1-3 linhas (não contados como "linha errada", porque a linha citada contém o afirmado): `BuscaProduto` citado como `estoque-forms.jsx:12-64` (região) e `12-66` (tabela/map) — a função vai de 12 a 64; `Th` de Recipes citado `manufacturing-page.jsx:74-79` — o componente começa em **72** (74-76 é o corpo dele, 79 já é `magra`); `exportar` de Ajustes citado `:248-256` — a função é 246-253; `MP.Header` "seguem até :591" — termina em 590; `estoque-page.jsx:641-657` para a aba `ajuste-novo` — vai até 655.
5. adjustment-create "Cabeçalho": *"o mesmo bloqueio de submit"* — o vivo **desabilita** o botão (`disabled={form.processing || recuperadoExcede}`, `Create.tsx:140`); o protótipo mantém o botão ativo e barra no clique com `aviso(...)` (`estoque-forms.jsx:242-244`). O submit é bloqueado nos dois; o mecanismo difere. Não contado.
6. adjustment-create "Dados do ajuste": chama de "Local" o select cujo rótulo no vivo é **"Filial *"** (`Create.tsx:154`); o Index do mesmo lote diz "filial". Inconsistência de nome, não de fato.
7. manufacturing-index-gap l.15: *"mesma rodada de adoção de DS que atingiu … `oficina-os-page.jsx`"* — a rodada 38 do ledger só nomeia `officeimpresso-page.jsx` e `oficina-page.jsx`; `oficina-os-page.jsx` vem de leitura do `get_file`, não verificável aqui.
8. map.json: `vivo.linhas` = `TODO` e `vivo.ancora: false` nas **76** partes, embora a prosa já cite linhas do `.tsx` em quase todas. É opt-out consciente previsto pelo `design-code-map-check` ("linha-only … 'vivo.ancora: false' explícito"), não drift; registrado porque é trabalho de Fase 1 deixado na mesa.
9. `prototipo_sha` idêntico (`sha256:bfd716b5fd4a`) nos 4 maps de Estoque — esperado: mesmo conjunto {`estoque-forms.jsx`, `estoque-page.jsx`}; regen reproduziu o mesmo hash nos 6.
10. O PR também altera `scripts/design-sync/state/application-report.json` e `applications.json` (estado de ferramenta, fora de `memory/requisitos`); não avaliados — fora do mandato.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos` = 1.484 linhas)

| Padrão | Hits | Controle positivo |
|---|---:|---|
| CPF pontuado (3.3.3-2) | 0 | OK |
| CPF cru (11 dígitos isolados) | 0 | OK |
| CNPJ (2.3.3/4-2) | 0 | OK |
| Telefone BR (DDD + 4/5-4) | 0 | OK |
| Telefone cru (10-11 dígitos) | 0 | OK |
| E-mail | 0 | OK |
| Valor em reais (símbolo seguido de dígito) | 0 | OK (classe `[R][$][ ]?[0-9]`, testada contra 2 sintéticos) |

Únicas 2 linhas com o símbolo de moeda são rótulos de campo entre parênteses, sem dígito ("Valor recuperado (…)" e "Frete (…)"). Nomes de cliente do CRM: 0 (os nomes que aparecem — "Larissa", "Martinho", "Jorge" no placeholder do protótipo, "[M]", "[F]", "[W]" — são persona interna/mock já presentes em origin/main). **pii_hits = 0 · controles 7/7.**

## Máquinas (rc literal)

```
node scripts/governance/requisitos-status.mjs Manufacturing --check   → "✓ memory/requisitos/Manufacturing/_STATUS-GENERATED.md em dia."   rc=0
node scripts/governance/plans-index.mjs --check                        → "✗ PLANS-INDEX-GENERATED.md DESATUALIZADO"                        rc=1  (drift pré-existente, obs. 3)
node scripts/governance/design-code-map-check.mjs --check --strict     → "[OK] nenhum map.json com âncora quebrada ou sha stale. 8 TODO"   rc=0
node scripts/governance/doc-id-index.mjs --check-collisions            → "OK: 0 colisão de id em 2633 ids."                                rc=0
node prototipo-ui/ancora.mjs <6 telas> --staging prototipo-ui/cowork   → 6× "âncora ✓"                                                     rc=0
node prototipo-ui/gerar-map.mjs <6 gaps>  (+ cmp chave a chave)        → 76/76 partes idênticas fora de prototipo.linhas/arquivo             rc=0
```

## Comandos reproduzíveis

```
git rev-parse HEAD origin/main --is-shallow-repository
git rev-list --left-right --count origin/main...HEAD
git diff --name-status origin/main...HEAD -- memory/requisitos
git diff --name-status origin/main HEAD -- resources prototipo-ui        # vazio → fontes idênticas
git ls-tree origin/main -- <cada path citado>                            # + controle negativo path/inexistente/controle-negativo.md
for t in StockAdjustment/Create StockAdjustment/Index StockTransfer/Create StockTransfer/Index Manufacturing/Index Manufacturing/Recipes; do node prototipo-ui/ancora.mjs $t --staging prototipo-ui/cowork; done
node prototipo-ui/gerar-map.mjs memory/requisitos/<Mod>/<tela>-gap.md > <scratch>/<tela>.map.json   # comparar com o do lote chave a chave
grep -c -- 'os-page-h|mfg-th|<Th|ordenar|sortBy|setPag|mfg-pag' resources/js/Pages/Manufacturing/Index.tsx   # 0 cada; 'order' = 4 linhas / 11 casamentos
grep -c -- 'Excluir|AlertDialog|mfg-modal|mfg-toast' resources/js/Pages/Manufacturing/Recipes.tsx          # 0 cada
git grep -E '^prototipo:.*[+]' origin/main -- 'memory/requisitos/*-gap.md'                                  # 5 de 42, todos com -page.jsx primeiro
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' > plus.txt ; node pii.mjs plus.txt ; node reais2.mjs plus.txt
git status --short                                                       # vazio ao final (só este arquivo)
```

```json
{"itens_verificados": 565, "erros_confirmados": 4, "error_rate_pct": 0.71, "pii_hits": 0, "veredito": "aprovado"}
```
