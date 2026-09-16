# 06 · `Widget` nasce `h3` e o ícone `warn` sai anônimo — pedido no PRIMITIVO

> **Por papel, não por classe** (§18 do protocolo · decisão [W] 2026-09-14).
> **Medido no protótipo servido** (rota `ponto`, dark, T1 estável 755=755 após `__oiLazyDone`).

## Achado 1 · nível de título do painel

**PAPEL:** moldura de painel com título (o "card com título" de dashboard e detalhe).
**DONO no `main`:** `resources/js/Components/ui/card.tsx` (1.987 B @`733033864088` na última leitura registrada) — **estender, não criar** (é o A9/A10 da linha de acertos: o primitivo existe e é consumido fora do Ponto).
**MEDIDO:** a sequência de títulos da rota `ponto` é `H1 "Ponto"` → `H3 "Fila de aprovações"` → `H3 "Atividade recente"`. **Salto h1 → h3**, que é o A8 da bateria. A origem **não é a tela**: o `Widget` do bundle emite `h3` fixo, e o `Card` do Ponto delega a ele (`ponto-ui.jsx:47-58`).
**ALVO:** o título do painel é o **segundo** nível da página — `h2` sob o `h1` do PageHeader. Prop de nível (`as="h2"` / `titleLevel`), com `h3` seguindo disponível para painel dentro de painel.
**CLASSE (só referência de onde medi):** `.pt-card-h > b` no fallback local; no DS é o `<h3>` do `Widget`.
**GUARDA:** `Card`/`Widget` são consumidos fora do Ponto (Backup · Financeiro/Advisor · Financeiro/Unificado — varredura **parcial**, logo **piso, não teto**). Mudança é **aditiva**: default continua o que é hoje se a prop não vier, e as telas que querem `h2` passam a prop. Prova: a sequência de títulos de 1 tela de cada consumidor, antes e depois.

## Achado 2 · ícone `warn` sem nome acessível

**MEDIDO:** `svg` sem `aria-hidden`, sem `aria-label` e sem `<title>`, dentro de `span` em `div.pt-body`, com `d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 00…"` (triângulo de alerta). **Fonte: o bundle** — `_ds_bundle.js:730`, no dicionário de tons (`c: 'var(--warn)'`, `s: 'var(--warn-soft)'`). É o **único** `svg` anônimo que sobra na rota: os 2 do shell eu consertei no build daqui.
**ALVO:** ícone decorativo ao lado de texto ⇒ `aria-hidden="true"` + `focusable="false"` por padrão, como o `icons.jsx` deste projeto já faz desde 2026-09-10 (o `Icon` passou a espalhar `...rest` e a marcar `aria-hidden`).
**DONO:** o componente do `main` que emite esse ícone (`Components/ui/alert.tsx` / `shared/StatusBadge.tsx` — **não medi qual**; o bundle é espelho e não diz o arquivo de origem). **Primeiro passo da thread é achar o dono por busca**, não supor.

## O que NÃO fazer

- **Não editar `_ds_bundle.js`** nem qualquer arquivo de `prototipo-ui/design-system/` — é espelho compilado (R4 do guard hasheia duplicata; e a fonte é `resources/`).
- **Não consertar na tela** (`ponto-ui.jsx` envolvendo o Widget num `h2` falso) — isso cria pele paralela e o `cowork-pele-paralela.mjs` morde.
- **Não trocar o `h3` por `h2` sem prop** — quebraria painel-dentro-de-painel nos outros consumidores.

## Família

Mesma classe das 2 pendências de DS já registradas: cor crua `rgb(255,255,255)` no `Avatar` dentro de `<h1>` e `TabBar` sem `role="tab"` de origem. **Vale abrir as 3 juntas** — são todas "o primitivo emite semântica que a tela não pode corrigir".
