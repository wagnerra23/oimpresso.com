---
sessao: "A1"
titulo: "ALVO da Produção (PCP) — 7 vistas + ficha medidas, escuro e claro, 390 no celular — saída da thread"
autor: "[CL]"
data: 2026-10-15
base: origin/main be7603623b
thread: A1-alvos.md
veredito: "entregue — 18 alvos medidos (8 do contrato + 8 no tema claro + 2 em 390px), 98 seções, 0 ausentes; cada alvo 2× byte-idêntico; secao-check conforme nos 18. Bateria a11y A1–A12 rodada: 3 itens falham no protótipo e voltam ao Cowork como pedido (§Perguntas)."
---

# _saida-A1 · Alvos da Produção (PCP)

## O que saiu

Os 8 alvos que o índice pede como prova (tema escuro, 1440×900):

| slug | rota no protótipo | seções |
|---|---|---|
| `producao--painel--index` | `pcv` → `ProducaoCvPage view=painel` | 8 · header · abas · kpis · alertas · fila_arte · acontecendo · carga_midia · produtividade |
| `producao--quadro--index` | `pcv-quadro` | 6 · header · abas · filtro · barra · board · eventos |
| `producao--linha--index` | `pcv-linha` | 7 · header · abas · filtro · barra · gantt · eventos_maquinas · sugestao |
| `producao--custo--index` | `pcv-custo` | 5 · header · abas · kpis · perdas · por_os |
| `producao--instalacao--index` | `pcv-instalacao` | 4 · header · abas · agenda · celular |
| `producao--apontamento--index` | `pcv-apontamento` | 4 · header · abas · texto · celular |
| `producao--roteiros--index` | `pcv-roteiros` | 4 · header · abas · familias · fluxo |
| `producao--ficha--index` | `pcv-quadro` + clique no 1º cartão (`.pcv-card-open`, OS-2614) | 7 · drawer · cabecalho · resumo · fsm · abas_ficha · etapas · rodape |

Mais as variantes que o `A1-alvos.md` pede, cada uma como slug próprio (precedente: `cockpit--sidebar-expanded`):

- **Tema claro, 1440×900:** `producao--<vista>--index-claro` para as 8 telas acima. O tema troca pelo menu do usuário do rodapé da sidebar (Aparência → Claro), que é o dono do tweak `theme` do shell (`sidebar.jsx` UserMenu → `window.__setTweak`). O alvo grava os quatro cliques em `clicar` (abrir menu · Aparência · Claro · fechar menu), e o `secao-check` os reaplica.
- **Celular, 390×844, tema escuro:** `producao--instalacao--index-390` e `producao--apontamento--index-390`.

Arquivos: `governance/design/targets/<slug>.{secoes,alvo}.json` para os 18 slugs. Os `.alvo.json` são saída do `alvo.mjs`, sem edição à mão; os `.secoes.json` foram gerados por script a partir dos seletores colhidos com `--mapa`.

## Como foi medido

- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`), porta 5571.
- Seletores colhidos com `node scripts/design-sync/alvo.mjs --mapa <url> --rota <r> --raiz <.pcv-root | .pcv-root > .pcv-body | .pcv-ficha>`. Nenhuma vista do PCP tem `data-contract`; os seletores vão por classe (`.pcv-kpis`, `.pcv-gantt`, `.pcv-phone`…) e, onde o bloco é um `Widget` sem classe própria, por posição dentro de `.pcv-body`.
- Medida: `node scripts/design-sync/alvo.mjs --alvo <url> --tela <slug> --rota <r> --viewport <L>x<A> --secoes <slug>.secoes.json --quieto-ms 2000 [--clicar …]`, que espera `__oiLazyDone` e 2000 ms sem mudança no nº de nós, e chama a sonda `design-diff.mjs --probe`. Duas vezes por slug; sha256 (16 primeiros) idêntico nas duas, para os 18. Exemplos: painel `6c8200e5b8cd91ec` · ficha `52dff2b4e817ee33` · ficha clara `0b0ec2f0bf721883` · apontamento 390 `88e9f6d7c036b077`.
- Conferência de que o tema mudou de fato, e não só os cliques: no painel, o texto dos KPIs sai `oklch(0.94 0.005 90)` no escuro e `oklch(0.22 0.01 80)` no claro; o fundo do drawer da ficha sai `oklch(0.3 0.008 240)` × `rgb(255, 255, 255)`.
- `node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`: **conforme** nos 18.

## Bateria a11y A1–A12 no protótipo

Rodada nas 7 vistas a 1440×900, tema escuro, com uma sonda Playwright (a bateria do §5-bis não tem script no repo; a sonda ficou fora do PR). A8 converte OKLCH→sRGB e só calcula depois de conferir que branco × preto dá 21:1.

| # | item | resultado | veredito |
|---|---|---|---|
| A1 | falso interativo | 0 elementos com `cursor:pointer` fora de controle nativo, nas 7 vistas | ✅ |
| A2 | foco removido | `producao-cv.css`: 0 `outline:none/0`, 12 `:focus-visible`, 0 `:focus` solto | ✅ |
| A3 | ícone sem nome | 0 clicáveis com ícone e sem nome; 0 `svg` sem `aria-hidden` dentro de clicável | ✅ |
| A4 | overlay | ficha: `role=dialog`, `aria-modal=true`, rótulo com a OS, foco dentro ao abrir e após 3 Tab, Esc fecha | ✅ |
| A5 | estado das abas | `TabBar`: a aba ativa tem `aria-current="page"`, as outras não têm | ✅ |
| A6 | estado só por cor | pontos de setor e de feed: todos com texto ao lado ou `aria-hidden` | ✅ |
| A7 | alvo de toque ≥24px | 0 abaixo de 24px, salvo o nome da máquina na linha do tempo (`.pcv-g-maq`, 171×19, 7 ocorrências) | 🔴 pedido |
| A8 | contraste AA | ver lista abaixo | 🔴 pedido |
| A9 | arraste sem alternativa | quadro: cada cartão tem "Mais ações" (menu ⋮); linha: blocos são `button` e o `aria-label` diz "Enter abre; Alt+setas move" | ✅ |
| A10 | `aria-live` | 1 região viva no painel/quadro/linha; o toast de "Nova OS" é `role=status` | ✅ |
| A11 | skip link | 1º focável é o trocador de empresa da sidebar, não "pular para o conteúdo" | 🟠 shell |
| A12 | estado vazio | quadro filtrado até zero: "Nada com esse filtro." nas 6 colunas e o link "Limpar" | ✅ |

**A8, o que falha (texto pequeno, AA 4,5):**

- Número vermelho das estatísticas do `PageHeader` ("atrasadas ou vencendo"): `oklch(0.55 0.17 18)` a 13px sobre o fundo escuro → **2,93**. Aparece nas 7 vistas.
- `StatusBadge` com texto branco sobre fundo opaco claro: "Aguardando cliente" (amarelo `oklch(0.74 0.13 75)`) → **2,35**; "Alta" (prioridade, no cartão do quadro) → **2,35**; "Agendada" (instalação) → **3,07**. 11,5px.
- Texto do cliente dentro dos blocos da linha do tempo (`.pcv-g-blk > span`, `oklch(0.72 0.005 90)` a 11,5px): **4,46** nos blocos `is-fila`/`is-feito` e **3,75** em `is-andamento`.
- Roteiros, `small` "com etapas em paralelo" no botão da família (`oklch(0.67 0.005 90)`, 12px) → **4,04**.

Limite da medida: o fundo é o 1º ancestral com alpha > 0,5, sem mistura de camadas. Para o badge amarelo o fundo é opaco (conferido), então o 2,35 é real.

## Perguntas ao Cowork (o espelho é build-only; nada foi corrigido aqui)

1. **A8 · `StatusBadge` de tom `warning`/prioridade alta com texto branco** — é do DS, não da Produção, e falha em 2,35. O build troca o texto para tinta escura ou escurece o fundo? Afeta "Aguardando cliente", "Alta" e "Agendada".
2. **A8 · número vermelho do `PageHeader`** (2,93 no escuro) — o tom `danger` das estatísticas precisa de um passo mais claro no tema escuro.
3. **A8 · texto dos blocos da linha do tempo** (3,75–4,46) e o `small` dos roteiros (4,04) — subir o texto secundário para ≥4,5 nesses dois lugares.
4. **A7 · `.pcv-g-maq`** (19px de altura) — é o botão que filtra os eventos pela máquina. Aumentar a altura do rótulo para ≥24px ou declarar que, no ERP denso, fica abaixo (decisão do Cowork/[W], como o A7 do §5-bis já registra).
5. **A11 · skip link** — é do shell, não da Produção; fica registrado, não é pedido desta thread.

## O que a ancoragem não resolve

- **Estados `vazio`/`carregando`/`erro`** (tweak `pcvEstado`) não foram medidos: o `alvo.mjs` não tem como escolher tweak (só `--rota`, `--clicar`, `--viewport`, `--sb-mode`), e o tweak não está em controle clicável fora do painel de Tweaks. Os UC de estado entram pelo teste das threads, não pelo alvo.
- **Vistas secundárias:** quadro mediu "Por setor" (o "Por máquina" é outro clique); linha mediu "hoje" (não "amanhã"); apontamento mediu o passo "lista" (scan, etapa e fim exigem cliques em sequência); roteiros mediu a 1ª família; ficha mediu a aba "Etapas" e a 1ª OS do quadro (OS-2614). Se o contrato de uma thread pedir essas vistas, é outro alvo com `--clicar`.
- **Tema claro a 390px** e a a11y no tema claro não foram medidos — o `A1-alvos.md` pede 390 só para apontamento e instalação, e o claro a 1440.
- **Fidelidade ao Office Comercial/Blade:** não há tela no `main` para comparar (tela nova, índice §1). O alvo mede o protótipo.
- **README de `governance/design/targets/`** não foi atualizado: está fora do `prefixo` da thread.

## Provas do índice conferidas

`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/producao/playbook/00-INDICE.md --thread A1` — os 8 `producao--*--index.alvo.json` existem com a chave `secoes`.

## Placar

entregue 18 de 18 alvos · 98 de 98 seções medidas · ausentes 0 · a11y 9 de 12 passam, 2 falham (A7, A8) e 1 é do shell (A11).
