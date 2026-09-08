# COLAR NO CODE — AUTOMAÇÃO DO PROTOCOLO DE EXPORT (PR-A1…A7)

> **Pedido de [W] 2026-09-03:** automatizar o ciclo `MAPA → ALVO → EXPORT → PR → PLACAR → pacote`, e valer **dos dois lados** (repo e Cowork).
> **Método:** `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md`. **Ponte** — destino `prototipo-ui/` (root). Eu não commito.
> **Princípio:** nenhum dono novo. Cada PR abaixo **estende** máquina que já existe (`design-memory-gate.yml`, `cowork-ssot-guard`, `cowork-mirror-freshness`, `casos-gate`, `contrato-de-tela`, `design-spec-gen`, `prototipo-readiness`, `gerar-payload-partes`).
> **Risco:** 🟢 mecânico · 🟡 regra de domínio · 🔴 schema/CI crítico. 1 assunto por PR, ≤8 arquivos, ≤~350 linhas.

---

## Ordem (cada uma destrava a seguinte)

`A1 + A2` → `A5` → `A3` → `A4` → `A6` → `A7`. A1 é a única que **não** depende de nada.

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

## O que NÃO se automatiza (e não deve)

- **Decisões [W]:** alocação/label no sidebar · motor do gantt (`@svar-ui/react-gantt` × `.fj-g-*`: 163 dependências viram setas) · alvo de toque em ERP denso · quais capacidades entram.
- **Merge de `.tsx`** — humano por ADR 0283.
- **Qual seção entra na onda** — é julgamento; automatizar julgamento é o erro que o resto do protocolo evita.
- **Dizer "está igual"** — continua sendo `design-diff --compare --check` nos dois renders (T7), que o A3 **alimenta** mas não substitui.

## Bloqueio herdado que o A3 depende

O `--compare` desta área é **medição órfã**: aborta com *"exige um snapshot.json existente"*. O A3 precisa de um job que **gere e versione o snapshot** por seção — sem isso, T7 segue não-afirmável por ninguém.
