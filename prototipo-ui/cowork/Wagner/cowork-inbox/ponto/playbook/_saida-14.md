---
sessao: "14"
titulo: "Forma — Espelho · lista — saída da thread"
autor: "[CL]"
criado: 2026-09-29
base: 95c30e2ad
thread: 14-forma-espelho-lista.md
veredito: "entregue — barra no Toolbar do DS, card flush com badge, th 11px/--text-dim medido igual ao protótipo; paginação no servidor intacta (W11). Colunas sem backend declaradas, não inventadas."
---

# _saída 14 · Espelho · lista — forma

Um arquivo tocado: `resources/js/Pages/Ponto/Espelho/Index.tsx`. Nada criado. `Show.tsx`, `MonthHeatmap.tsx`, o contrato e o controller ficaram intactos.

## O que mudou

| seção | antes | agora |
|---|---|---|
| barra | `Card` com busca e mês soltos, sem `Toolbar` | `Toolbar` (`@/Components/shared/Toolbar`) com `bordered={false}` dentro da própria moldura (radius 12 + border 1px, `bg-card`), igual ao `window.PtBarra`. Filhos: Mês de referência · Buscar (o que estica, `1 1 260px`) · spacer |
| lista | `Card` sem título, tabela `text-sm`, `th` em `muted-foreground` sem caixa | `Card flush` com `CardTitle as="h2"` "Colaboradores" + badge `(N ativos)`. `th` 11px, uppercase, `.07em`, 600, `--text-dim`. `td` 12.5px. Sub-linha do colaborador com o e-mail (10.5px, `--text-dim`). Botão "Ver espelho". Rodapé "Mostrando X–Y de T colaboradores" sempre visível |

Ganchos de medição `ponto-root` e `pt-body` no DOM, sem CSS próprio, para os seletores de `governance/design/targets/ponto--espelho--index.secoes.json` (mesmo idioma do `Escalas/Index`).

## th medido (PROVA)

Mesma leitura nos dois lados, dark, 1280px:

| | protótipo (`ponto-page.jsx`, rota `pt-espelho`) | produção (harness) |
|---|---|---|
| font-size | 11px | 11px |
| cor | `oklch(0.72 0.005 90)` (`--text-dim`) | `oklch(0.72 0.005 90)` |
| letter-spacing | 0.77px (.07em) | 0.77px |
| weight / caixa | 600 · uppercase | 600 · uppercase |
| contraste sobre `--color-card` `oklch(0.30 0.008 240)` | 5,49 | 5,49 |

`--text-mute` (`oklch(0.58 0.005 90)`) no mesmo fundo dá 3,18, que reprova AA. Nenhum texto pequeno ficou em `--text-mute`.

## Como foi medido

- **Protótipo:** espelho do `main` servido por `servirEstatico` (`render-proto-baseline.mjs`) na porta 5593, `localStorage['oimpresso.route']='pt-espelho'`, dark, 1280×900.
- **Produção:** harness Vite local com o CSS real (`inertia.css` + `foundations.css` + `cockpit.css`) dentro de `.cockpit[data-theme=dark]`, 1280×900. `AppShellV2`, `PontoAreaHeader` e `@inertiajs/react` stubados (não há backend PHP local). Dado fixo: 8 linhas, total 22.
- `secao-check --tela ponto--espelho--index --url <harness>`: `barra` e `lista` sem divergência de estilo, `nos` ou `filhos`. Restam 3:
  - `header` e `tabs` ausentes: stubados no harness. Em produção vêm do `PontoAreaHeader` (W9, #8118), que esta thread não toca.
  - `barra → ordemClasses` `div` × `flex`: é a mesma tag. O rótulo é a 1ª classe do filho; o `Toolbar` do protótipo usa estilo inline, o daqui é `div` com utilitária. Mesmo artefato declarado no #8115.
- `design-diff --compare prod.json design.json --check`: D4 tipografia, D6 cor, D8 alinhamento e D9 texto **IGUAIS**. Declarado:
  - `[D2] nº de colunas` 4 × 9, e por consequência `col2.mono`/`col3.mono`/`col3.alcancavel` (a comparação é por índice e as colunas deslizam): as 5 colunas do protótipo **Escala · Trabalhado · HE · Saldo BH · Controla ponto** e **Divergências** não existem no `EspelhoController@index` (`id · matricula · cpf · nome · email`). É o `campo inexistente` que a `_saida-33` registrou. Não inventei valor.
  - `[D2] filtro linhas` 2 × 3: a sonda agrupa filhos pelo topo; o protótipo tem 6 filhos de alturas diferentes, este tem 3. As duas barras são **uma faixa**: protótipo 1176×73, produção 1232×68 (a largura difere porque o shell do protótipo tem sidebar).
  - `SAÚDE tokens.prod`: no harness os tokens moram em `.cockpit`, não em `<html>`. A página não lê tokens na raiz.
- Reflow: a 820px a barra continua uma faixa (`scrollWidth == clientWidth`, 770px) e a página não tem overflow horizontal.
- Linha da tabela: 49,5px nos dois lados. A thread cita 65px; medido no render, o protótipo dá 49,5px.

## Placar

**entregue 2 de 4 seções** do ALVO (`barra`, `lista`) · **ausentes `header` e `tabs`, por W9** (PR próprio, fora desta thread).

Dentro da barra, **3 de 6 filhos**: Mês de referência · Buscar · spacer. **Ausentes:** select Escala e check "Só com divergência" (sem parâmetro no controller) e a nota "N dias em divergência na competência" (sem dado). Decisão [W] necessária se forem entrar: exigem backend.

## Guardas

- Paginação no servidor intacta: `LengthAwarePaginator`, partial reload `only: ['colaboradores','mes']` (W11, ADR 0418).
- Mês viaja no link `/ponto/espelho/{id}?mes=…` (UC-ESPIDX-03).
- `ponto-espelho.contract.json`: os 5 `data-contract` são do `Show.tsx` (`espelho-dados-colaborador` etc.), não da lista. Nenhum mudou.
- `EspelhoContratoTest`: backend não mudou. Roda na lane Ponto do CI; Pest não roda local.
- `ds-guard` limpo. `tsc` sem erro novo no arquivo.

## Notas

- O Buscar tinha um wrapper com ícone e um `Input` interno; o CSS global do cockpit pinta todo `input`, e isso fazia borda dupla. Ficou um `Input` simples, como o `PtCampo` do protótipo. Segue `disabled` "(em breve)" (charter §Non-Goals).
- A coluna CPF é da produção e o protótipo não tem. Ficou: remover dado da lista é regressão, não paridade (§"O que esta thread não é"). Mascarar CPF na listagem (`D-COLAB-CPF`) foi decidido para Colaboradores; se vale aqui também é pergunta para [W].
- `Ponto/Espelho/Index` está no manifesto do `visual-regression`; a baseline de pixel vai divergir. Regenerar baseline não é prática vigente (ADR 0409).

## PARAR SE

Nenhum disparou. `Toolbar` e `Card` com `badge`/`flush` já estão no `main`; nenhum CSS novo; o contrato não contradiz a ordem.
