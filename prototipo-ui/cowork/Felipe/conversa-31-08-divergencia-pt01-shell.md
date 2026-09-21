# Conversa de 31/08/2026 — divergência PT-01 × shell: `PageHeader`, `TabBar` e "mais alguns detalhes"

Registro da sessão entre Maiara e o agente. Ordem cronológica. Serve para enviar ao agente de código
(Claude Code) e fechar as questões de **DS / template / shell** sem reler o chat.

**Documentos tocados nesta sessão:** `divergencia-pageheader-tabbar-pt01.md` (novo, entregue para
download) · este registro. **Nenhum arquivo de tela, de template ou do DS foi modificado.**

**Procedência marcada em todo item** (Lei da §0 do `manual-escrita-para-agente.md`):
**[DS]** citado do design system · **[REPO]** lido do código do `main` · **[TELA]** decidido no
protótipo · **[RUNTIME]** observado em execução.

---

## 1 · A pergunta

> *"Veja o template `Pt01Lista.dc.html`. Ele está com PageHeader diferente e TabBar também (diverge
> da tela do Wagner que você falou que estava no shell). Entre mais alguns detalhes. POR QUÊ? Quem
> está certo aqui?"*

Antes de responder, medi **três lugares** — a regra do projeto ("o DS decide por componente E por
template"): (1) o componente do DS que renderiza o elemento; (2) o template canônico do mesmo tipo de
tela, `templates/pt-01-lista`; (3) a tela do Clientes no `main`, que é a referência que o Wagner
citou como "a do shell".

---

## 2 · Por quê: coexistem três gerações de cabeçalho no repo

A divergência não é erro de ninguém — é **idade**. O repo tem três cabeçalhos vivos ao mesmo tempo.

| Geração | Arquivo **[REPO]** | Estado | Anatomia |
| --- | --- | --- | --- |
| 1 | `resources/js/Components/shared/PageHeader.tsx` | `@deprecated` / **CONGELADO** no próprio docblock; o ratchet `pageheader-gate` reprova CI se tela nova importar | placa de ícone 40×40 `bg-primary/10` · `description` em `text-sm` · **abas dentro do slot `action`**, na mesma linha do título ([W] 2026-05-17) |
| 2 | `resources/js/Components/PageHeader/PageHeader.tsx` | **canon v3.8** (ADR 0189 / 0190) | flat · sem fundo · sem raio · **sem placa de ícone** · `border-b` em `var(--border)` · h1 22px · subtítulo tabular · zonas A / B / C |
| 3 | decisão de **14/07/2026**, visível em `Pages/Cliente/Index.tsx` L882-883 | **vigente** | *"ZONA C · subnav MOVIDA pra faixa própria abaixo do header — 'mesma posição do Clientes/protótipo em todas'"* — a Zona C do canon foi deliberadamente esvaziada |

Amostra de 39 `Index.tsx` lidas no `main`: **17 ainda na geração 1**, **22 migradas**. A geração 1
serve cerca de 104 telas no total — é por isso que ela continua no repo e continua aparecendo em
tela quando se abre um módulo antigo.

**`Pt01Lista.dc.html` implementa a geração 3.** A tela que aparece com placa de ícone e abas na mesma
linha do título é a geração 1.

---

## 3 · Quem está certo

**Na estrutura, o template está certo.** `PageHeader` (slot 1) + `TabBar` em faixa própria (slot 2)
reproduz a última decisão do Wagner (14/07/2026), e o componente canon do repo concorda com ela.

Dito de outro modo: a "tela do shell" que a Maiara viu **não** é a referência atual — é uma tela que
ainda não migrou. O template não divergiu do shell; o shell é que tem duas versões de si mesmo.

---

## 4 · O que NÃO divergiu (abas — mesma origem, mesmos números)

`TabBar` **[DS]** e `PageHeaderTabs` **[REPO]** descendem do mesmo `.cli-moduletopnav-tab` do
protótipo e batem número por número:

- sublinhado ativo **2 px** em `var(--accent)`, deslocado **-1px** para cobrir a borda da faixa
- fundo do item ativo `color-mix(--accent-soft 50%)`
- rótulo ativo peso **600**
- pílula de contador: peso **600**, **10.5px / 1.4**, `min-width 18`, raio total
- pílula ativa em `--accent` / `--accent-fg`

As abas, portanto, **não divergem em valor**. Divergem em **posição** (§2) e em **mecânica** (§5).

---

## 5 · Os "mais alguns detalhes" — divergências reais e medidas · lista **fechada**

| Item | DS / template **[DS]** | Canon do repo **[REPO]** |
| --- | --- | --- |
| h1 | 600 · 22px / 1.3 · `-.015em` | **700** · 22px · `tracking-tight` (−0.025em) · `leading-snug` |
| subtítulo | `stats[]` · 400 · **13px** / 1.45 · mt 4px | ReactNode livre · **12px** (`text-xs`) · mt 2px |
| caixa do header | `14px 0` — lateral vem do wrapper (14px compacto / 22px confortável) · `align-items: flex-start` | `pt-6 px-6 pb-3.5` · `min-h-[60px]` · `items-center` |
| landmark | `<header>` sem `role` | `<header role="banner">` |
| altura da aba | **36 px** · padding `0 14px` · 13px | `px-3 py-1.5 text-sm` → 14px, ~**30 px** |
| mecânica da aba | `<button>` + `onChange` (estado local) · `<nav aria-label="Sub-navegação">` · `overflow-x: auto` | `<Link href>` Inertia (URL) · `role="tablist"` + ←/→/Home/End · `maxVisible 5` + overflow `⋯ Mais` |
| pílula inativa | `var(--bg-2)` / `var(--text-dim)` | `oklch(0.32 0.01 240)` / `oklch(0.70 0.01 240)` **cravados** (hue frio 240) |
| ação primária | fora da barra de abas | `PageHeaderPrimary`, **dentro** da barra de abas |

**Nota de densidade.** Em densidade confortável o respiro lateral de 22px do template praticamente
encosta nos 24px do canon. A diferença de respiro só aparece no compacto.

**Não auditado nesta sessão** (declarado para não virar omissão silenciosa): sidebar, faixa de KPI,
toolbar, tabela, paginação.

---

## 6 · Decisão pendente da Maiara

Pela regra do projeto **o DS ganha, e divergência autoriza pergunta — nunca ação**. Nada foi
alterado. Candidatos a registro em `pauta-design-system.md`:

| # | Item | Sugestão de prioridade |
| --- | --- | --- |
| a | peso do h1 — 700 **[REPO]** vs 600 **[DS]** | **P1** (aparece em toda tela) |
| b | subtítulo — 12px **[REPO]** vs 13px **[DS]** | **P2** (esperar segundo caso) |
| c | altura da aba — 30 px **[REPO]** vs 36 px **[DS]** | **P1** (36 px também é o alvo de toque) |
| d | `TabBar` sem `role="tablist"` e sem navegação por seta | **D** — defeito de origem do DS |
| e | `PageHeader` do DS não emite `role="banner"` | **D** — defeito de origem do DS |
| f | pílula inativa em hue 240 cravado no repo, fora de token | item de **repo**, não de pauta |

Pergunta aberta à Maiara: **abrir os cinco (a–e) ou só os que valem segundo caso?**

---

## 7 · O que o agente de código precisa decidir (para levar ao Code)

Três perguntas, nesta ordem. Nenhuma delas é de estilo — todas mudam código.

1. **Qual geração é o alvo de cada tela nova?** A resposta esperada é "geração 3", e o
   `pageheader-gate` já força isso. Se for para valer, o pacote de handoff precisa dizer:
   *não importar `Components/shared/PageHeader` em tela nova; importar `Components/PageHeader`
   e emitir a subnav em faixa própria abaixo do header, Zona C vazia.*
   **Teste de aceite:** `rg "shared/PageHeader'" resources/js/Pages` não retorna a tela nova.

2. **As 17 telas da geração 1 vão migrar, e em que onda?** Enquanto não migrarem, a comparação
   "template × tela real" vai reabrir esta mesma dúvida a cada módulo aberto. Ou migram, ou o
   documento diz explicitamente quais telas ainda são legado e não servem de referência.
   **Teste de aceite:** existe lista nomeada das telas legado em `COWORK_NOTES.md`.

3. **Cada divergência da §5 resolve em qual ponta?** Para cada linha: ou o repo aproxima do DS
   (então é diff de repo), ou o DS aproxima do repo (então é proposta na pauta, decidida pelo
   Wagner). **Não fazer as duas** — e não "corrigir" no template, que é o único que não decide.
   **Teste de aceite:** cada uma das 8 linhas da §5 tem um destino escrito (repo / pauta / aceita
   como está) antes de qualquer commit.

**Proibições explícitas para quem implementar** (regra ausente não é proibição):

- **Não** alterar `Pt01Lista.dc.html` para "bater" com a tela legado.
- **Não** substituir `TabBar` nem `PageHeader` por componente local — contornar na tela ou registrar
  proposta na pauta.
- **Não** converter, calcular ou arredondar os valores da §5; eles são citação, não sugestão.
- **Não** tratar `--accent` como cor da empresa: `--accent` é reescrito pelo seletor de matiz do
  shell via `localStorage`; o contrato é `--color-primary`. **[RUNTIME]**

---

## 8 · Teste de aceite desta entrega

- Nenhum arquivo de tela, template ou DS modificado — só os dois `.md` desta sessão.
- Toda linha da §5 tem procedência **[DS]** ou **[REPO]** declarada.
- A lista da §5 está marcada **fechada**; a §4 diz o que **não** divergiu; a §5 declara o que **não**
  foi auditado.
- As três perguntas da §7 têm teste de aceite de uma linha cada.
