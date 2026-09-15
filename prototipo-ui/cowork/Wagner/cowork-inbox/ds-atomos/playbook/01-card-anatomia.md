---
sessao: "01"
titulo: ui/card.tsx — anatomia Widget aditiva (badge · note · flush)
dono: "[CL]"
base: 2b4a3ec3b48a
prefixo: resources/js/Components/ui/card.tsx
nao_toca: resources/js/Pages/** · resources/js/Components/shared/** · qualquer CSS
depende: —
---
# 01 · `ui/card.tsx` — três props que faltam pro card virar Widget

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/ponto-ui.jsx` :: `Card` — o que ele monta hoje sobre o `Widget` do bundle.
- **âncora (código):** `resources/js/Components/ui/card.tsx` — **1.987 B**, sha `733033864088`. Arquivo inteiro cabe; leia-o todo.
- **NÃO ler:** `Pages/Ponto/**` (28 arquivos) · `shared/KpiCard.tsx` — oráculo, não leitura.
- **arquétipo:** primitivo de moldura. **persona:** todas.

## B · NÃO INVENTAR
- **Zero CSS novo.** Só utilitárias/tokens que o arquivo já usa (`bg-card`, `border-border`, `rounded-xl`, `shadow-sm`, `text-muted-foreground`).
- **Zero cor crua**, zero hex, zero `oklch()` literal.
- **Copy:** nenhuma — o componente não tem texto próprio.

## C · ALVO MEDIDO (dark, após T1 estável)
Moldura já casa (radius 12px `rounded-xl` · border 1px `--border` · bg `--surface` · shadow `0 1px 2px rgba(0,0,0,.04)`). **O que falta são três slots:**
1. **`badge`** — nó à **direita do título, na mesma linha de base**, `11.5px` mono, cor `--text-dim` (**não** `--text-mute`: 3,18 reprova AA), `white-space:nowrap`, encolhe por último.
2. **`note`** — linha de apoio **abaixo** do título, dentro do header, `text-xs text-muted-foreground`, quebra em 2 linhas (`break-words`, **nunca** `truncate`).
3. **`flush`** — booleano que zera o padding do corpo (pro conteúdo sangrar até a borda, caso da tabela).

**Por que os três, e não um `children` no header:** hoje contagem e frase de apoio caem no `CardTitle` e disputam o `h3` — que trunca. Medido no protótipo antes da correção: título "Marcações mobile a validar" + "(3 pendentes · últimos 7 dias)" cortava a contagem.

## D · COMO VALIDAR
1. `ui/card.tsx` exporta `Card` aceitando `flush` e `CardHeader`/`CardTitle` aceitando `badge`/`note` (ou `Card` recebendo os três e distribuindo — sua escolha, desde que 2 passe).
2. **Guarda (obrigatória):** `<Card>` **sem** as props novas renderiza markup **idêntico** ao de `733033864088` — snapshot antes/depois, 0 diff. É o que protege Backup, Financeiro/Advisor, Financeiro/Unificado e o resto.
3. `badge` com texto longo **não** empurra o título pra fora nem trunca a si mesmo até 1280px.
4. `note` de 90 caracteres quebra em 2 linhas, sem ellipsis.
5. `flush` zera só o padding do corpo — header e footer intactos.
6. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `resources/js/Components/ui/card.tsx` — **só este**.
- **REUSAR:** `cn` de `@/Lib/utils`; o `data-slot` que o arquivo já emite por parte.
- **CRIAR:** nada de arquivo novo.
- **NÃO TOCAR:** nenhuma Page, nenhum `shared/*`, nenhum CSS.
- **PASSO A PASSO:** 1) ler o arquivo inteiro · 2) somar as props **opcionais** (tipos primeiro) · 3) render condicional — prop ausente ⇒ **nenhum nó extra** no DOM · 4) snapshot de guarda · 5) `npm run build` limpo.
- **DADO:** nenhum (primitivo puro).
- **PARAR SE:** a única forma de encaixar exigir mudar o default de qualquer prop existente, ou mexer em `Pages/**` — aí é aditivo furado: **pare e reporte**.

## PRÉ / PÓS
- **antes:** `ui/card.tsx` **sem** `badge`/`note`/`flush`; consumidores fora do Ponto **presentes** (guarda).
- **depois:** as três props presentes e opcionais; snapshot dos consumidores **sem diff**.
- **quebra:** se o "antes" já não vale (alguém somou as props), **não execute** — reporte e pare.

## PROVA
`resources/js/Components/ui/card.tsx` contém `badge`, `note` e `flush` · snapshot de guarda verde · `_saida-01.md`.
