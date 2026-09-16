# LEIA-ME — recepção deste zip (Cowork → Code · 2026-09-14)

> **Quem recebe não precisa ter visto a conversa.** Este arquivo é a única coisa que você precisa ler antes de extrair.
> **O que este zip é:** o projeto Cowork inteiro. Ele é ponte de **BYTES** — é o que coloca os arquivos em disco para o `gerar-payload-partes.mjs` rodar (ele só roda de onde os arquivos estão; ADR 0374). **Não é** ponte de prova: sem `sync/bundle.manifest.json` commitado, ninguém audita completude pelo git.

---

## 1 · Onde extrair

**`/oimpresso-erp-conunica-o-visual/`** — raiz do repo, já no `.gitignore` (*"Export local do projeto Cowork vivo … insumo volátil de transporte"*).

❌ **NÃO extraia em `prototipo-ui/_incoming/`.** O `.gitignore` oferece essa pasta como *"staging volátil do unzip"*, mas o **`scripts/governance/cowork-ssot-guard.mjs#R1`** lê o **disco** (`readdirSync` da raiz de `prototipo-ui/`) e só aceita `cowork/` e `design-system/` — a pasta existindo **já é violação**, e os bytes dentro caem no **R4**, que hasheia tudo *"inclusive caches ignorados"*. As duas leis se contradizem; enquanto [W] não desempata, use a pasta da raiz.

---

## 2 · O que pousa — e o que NUNCA pousa

### ✅ BUILD → `prototipo-ui/cowork/Wagner/`, path relativo preservado
Só o que o **host declara** (fechamento transitivo de `oimpresso.com.html`): **273 arquivos** nesta medição.
`_ds/**` vai para `prototipo-ui/design-system/` — o roteamento é do `destinoDoBundle`, não manual.

**Mudaram neste ciclo (confira por sha antes de aplicar):**

| arquivo | bytes | por quê |
|---|---|---|
| `sidebar.jsx` | 35.006 | `aria-hidden`/`focusable` no svg do botão de colapso |
| `app.jsx` | 75.264 | **`<main>` do AP9** (era `<div class="main">`) + rota `pt-` do Ponto |
| `ponto-page.jsx` | 34.640 | `daRota` — a rota vira a fonte; deep link `pt-espelho-<id>` |
| `ponto-telas.jsx` | 65.761 | 26 `data-contract` novos · 6 paginações corrigidas · CPF/PIS mascarados · `Modal` do DS · Editar/Submeter fora da lista · identificador do REP corrigido |
| `ponto-data.jsx` | — | escala **`EST-30` sem vínculo**: o caso que faltava para o ramo "remover escala" existir (B1 do §20) |
| `ponto-fechamento.jsx` | 18.261 | "Reabrir" removido · "assinada" fora · passo 4 vira ponteiro p/ Relatórios |
| `ponto-mobile.jsx` | 17.794 | **biometria removida (ADR 0383)** · copy de `ORIGEM_ANULACAO` |

### ✅ PEDIDOS → `prototipo-ui/cowork/Wagner/cowork-inbox/…`
**Pouse APENAS estes 15 arquivos novos:**

`ponto/playbook/`: **`_SESSAO-FRIA`** · `16-gap-aprovacoes` · **`17-data-contract-no-tsx`** · **`18-contratos-orfaos-bloqueada`** · `20-gap-intercorrencias` · `21-gap-banco-horas` · `22-gap-escalas` · `23-gap-colaboradores` · `24-gap-importacoes` · `25-gap-relatorios` · `26-gap-configuracoes` · `27-emendas-e-guards` · `28-rota-propria` · `29-decisoes-retidas-default` · `30-pedido-retidos-aprovado` · `31-bateria-comportamento` · `ATA-DECISOES-2026-09-14` · `_PATCH-INDICE-2026-09-14`
`ds-atomos/playbook/`: `06-widget-nivel-titulo`

**18 arquivos.** Não há thread **19** como arquivo: ela é **cross-ref** para `ds-atomos/playbook/06` — o dono é o primitivo, não o Ponto. O `_PATCH` declara isso no próprio objeto.

### ❌ NUNCA pousa
- **`cowork-inbox/**/00-INDICE.md`** e **`_saida-*`** — a pasta é **bidirecional** e o `main` está à frente. Medido: o `00-INDICE.md` do Ponto tem **24.411 B** nesta cópia e **24.929 B** no `main`, e o `main` tem `_saida-01/02` que esta cópia **não tem**. Extrair por cima **regride o seu trabalho**. É para isso que existe o `_PATCH-INDICE-2026-09-14.md`.
- ⚠️ **As threads antigas `01-*` a `15-*` e o `_delta-indice-13a15.md`** — são **cópia do `main`**, não trabalho deste ciclo. A pasta local tem **35 arquivos**; só **18** são novos. Pousar os outros 17 sobrescreve com cache do Cowork o que já está lá (e que pode estar à frente, como o índice provou). **Se a sua ferramenta extrai pasta inteira, extraia e depois descarte os 17 antigos** — a lista branca acima é a autoridade, não o conteúdo do zip.
- **`CLAUDE.md`** e **`github.md`** — donos do lado Cowork.
- Qualquer retrato derivado (manifesto, inventário, mapa tela↔arquivo).

---

## 3 · Depois de pousar

1. **`node scripts/governance/cowork-ssot-guard.mjs`** — R1 raiz · R2 donos · **R3 `.md` é PERMITIDO dentro de `cowork/{Wagner,Felipe}/`** (emenda [W] 2026-09-13) · R4 zero bytes duplicados. *(R4 está limpo nesta remessa: as pastas `entrega-sidebar-*`, que eram o par duplicado medido em 13/09, foram apagadas.)*
2. **Regenere o pacote** a partir do extraído — o manifesto de 14/09 (278 arquivos) **não cobre este estado**:
   `node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json`
   ⚠️ **273 (host) × 278 (manifesto) divergem** — confira por sha, não por contagem.
3. **Confira o exit code.** Em 2026-08-24 o gerador saiu `rc=2` **depois** de escrever o manifesto e alguém aplicou **242 de 247 arquivos** com `missing: []`. Hoje ele limpa o que escreveu, mas o hábito fica.

---

## 4 · O que este zip NÃO resolve

- **Poda.** Extrair só adiciona e sobrescreve. Arquivo que eu apaguei aqui **continua** no espelho — e `apply não apaga` é regra do `aplicar-payload.mjs` (*"podar é decisão [W]"*).
- **Prova de completude.** Sem `sync/` no git (medido: **0 de 16.920** arquivos, e `sync/` **não está** no `.gitignore`), o recibo da ADR 0387 não tem lastro.
- **Leitura de âncora.** Byte que pousa não é âncora lida — isso é `scripts/design/ancora.mjs` + `anchor-content-check.mjs`.

---

## 5 · O que ler primeiro, do que veio

> **→ Se você vai executar, comece por `_SESSAO-FRIA.md`:** ele traz o **prompt de abertura** para colar num chip novo (read-order mínimo + **o que NÃO ler**), a tabela das 15 threads com pré-requisito e quantos PRs cada uma vale, e a ordem que [W] determinou. **1 thread = 1 chip = 1 PR.**

> **Dicionário dos IDs:** as threads citam decisões por id (`D-PONTO-DETALHE`, `D-ESC-DESTROY`, `D-COLAB-CPF`…). **Esses ids nasceram neste ciclo e o único dicionário deles é a `ATA-DECISOES-2026-09-14.md`** — leia a ata **antes** de qualquer thread, ou as siglas ficam órfãs. Elas **não** existem em `memory/decisions/` (exceto as 5 da proposal retida, que a thread 30 manda gravar lá).
> **Citações de §:** as threads citam `§18` (papel × classe), `§19` (adaptação) e `§20` (bateria de comportamento) — três seções **novas** da norma. Se você pousar só as threads e não a norma atualizada, essas referências ficam sem destino.

1. **`ATA-DECISOES-2026-09-14.md`** — as 19 decisões de [W] deste ciclo + as 5 da proposal retida, com as 3 regras que ele pôs acima de todas (R1 guard+protótipo · R2 quem muda é o protótipo · R3 "faça" em vez de "escolha"). **O que está lá não se re-pergunta.**
2. **`30-pedido-retidos-aprovado.md`** — a proposal de 21/08 fechou; 5 PRs em ordem de risco, e o bloco de frontmatter para virar `status: accepted`.
3. **`_PATCH-INDICE-2026-09-14.md`** — os objetos para o `00-INDICE.md` (que você edita, não eu).
