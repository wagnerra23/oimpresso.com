---
sessao: "07"
titulo: ALVO do sidebar, protótipo × vivo (read-only)
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main 3fd66d73d (o #7942 já mergeado)
---

# _saida-07 · ALVO do sidebar

## Feito

- **Alvo versionado:** `governance/design/targets/cockpit--sidebar.alvo.json` + `cockpit--sidebar.secoes.json`.
  Slug vem de `--tela cockpit/_sidebar`, a mesma `tela` do contrato estático `contracts/cockpit-sidebar.contract.json`.
  As seções são as 5 âncoras desse contrato (`sb-modos · sb-topo · sb-corpo · sb-rodape · sb-alcas`).
  Seletores colhidos por `alvo.mjs --mapa --raiz aside` no espelho servido, não de memória.
- **Critérios da prova `revisao`:**
  - *duas leituras iguais:* 2 execuções de `--alvo` deram **bytes idênticos** (`cmp`). A sonda espera `__oiLazyDone` e depois uma janela de 2000 ms sem mudança no nº de nós.
  - *caso de sanidade:* o próprio `alvo.mjs` roda `garantirSanidade()` antes de derivar contraste. Na sonda à parte (expanded), o caso conhecido foi a largura da alça, 20px, igual nos dois lados e no alvo.
  - *slug conforme o README de targets:* `<tela>` normalizada pelo `alvo.mjs`. A linha do alvo novo entrou na tabela "Alvos exportados".
- **Bite-test T5:** `--injetar-falha aside.sb` removeu o último filho, e `sb-alcas` virou `ausente` no JSON. Uma 1ª tentativa estourou timeout e **não gerou arquivo**. Ela foi descartada, não contada como mordida.
- **Gate consumidor:** `secao-check --todos --servir-espelho` → `cockpit--sidebar 5 conforme · jana--index 10 conforme`, rc 0.
- **`pedido.mjs --tela cockpit/_sidebar --secoes`** → rc 0, lista as 5 (antes: exit 2, "sem alvo").

## Não feito, e por quê

1. **O alvo cobre só o modo RAIL.** O `alvo.mjs` mede com viewport fixo 1280×900 e browser limpo. No protótipo, o auto-rail é `(max-width: 1280px)` **inclusivo** (`app.jsx:573`), então a 1280 a sidebar nasce em rail. Não há flag de largura nem de modo. Expanded e hidden só são mensuráveis mudando o `alvo.mjs` (e o `secao-check`, que reproduz as flags), e os dois estão fora do prefixo desta thread. Consequência para as 08–12: o cabeçalho de grupo, o item ativo e as sub-telas **não existem** no alvo versionado. O slot deles só vira cobrança de CI depois dessa mudança na máquina.
2. **O pedido de seção ainda para em "sem charter".** `pedido.mjs --secao sb-corpo` → `NÃO MEDI: sem charter pra essa tela`. É o mesmo resíduo que o contrato já registra (`ancora.mjs cockpit/Sidebar`: sem âncora computável). Não criei charter: fora do prefixo, e âncora inventada é pior que ausente.
3. **Prova `${REC}/07-revisao.json` não escrita.** O placar diz que o avaliador de recibo não foi portado (ADR 0397). Os 3 critérios estão atendidos acima, com os recibos.

## Medida da tabela a–e (sonda à parte, expanded, dark, mesma técnica: `getComputedStyle` após nº de nós estável por 10 leituras)

Protótipo: espelho servido, 1440×900, `oimpresso.sidebar.mode=expanded` no contexto limpo.
Vivo: `https://oimpresso.com/ia`, sessão já logada, 1440×900, sem gravar nada.

| # | propriedade | protótipo (medido) | vivo (medido) | ficha dizia |
|---|---|---|---|---|
| a | ordem no `.sb-group-h` | ícone (x18) · rótulo · contador (x217) · **seta (x231)** | **seta (x18)** · ícone · rótulo · contador (x123) | ✅ confere. E mais: no vivo o contador fica colado ao rótulo, no protótipo vai para a direita |
| b | cor do cabeçalho | `oklch(0.8 0.008 295)` = `--sb-text` | `oklch(0.55 0 0)` = `--sb-text-dim` | ✅ confere |
| c | raio | `4px` | `6px` (`--radius-sm` = 6px) | ✅ confere, com o valor resolvido |
| d | rótulo | inline `oklch(0.72 0.09 h)` | CSS `oklch(0.78 0.08 h)` | ✅ confere |
| d | ícone do grupo | inline `oklch(0.65 0.14 h)` | **inline `oklch(0.65 0.15 h)`** (`Sidebar.tsx:859`) | ⚠️ **a ficha errou aqui**: `oklch(.68 .13 h)` é o CSS do `.sb-group-dot`, que só aparece em grupo **sem** ícone. Os 9 grupos têm ícone. O delta real é só a croma, .14 × .15 |
| e | contador + atalho (`.sb-kbd`) | `oklch(0.58 0.005 90)` = `--text-mute` | `oklch(0.55 0 0)` = `--sb-text-dim` | ✅ confere |

`h` medido = 202 no 1º grupo dos dois lados.

## Rail: vivo × alvo (a 1280, mesmos seletores e campos)

Para medir o rail no vivo, retirei a chave `oimpresso.sb.mode` do localStorage, medi e **restaurei** o valor original (`expanded`, confirmado depois).
- `sb-modos`, `sb-topo`, `sb-corpo` e `sb-alcas`: estilo idêntico ao alvo. Geometria só como informativo (corpo com 681 × 748 de altura).
- **`sb-rodape` seria AUSENTE no vivo.** O protótipo usa `aside > .sb-user` e o vivo `aside > .sb-user-wrap`. Medido pelo mesmo seletor, o `secao-check` contra um render do vivo reprovaria esse slot. Rodapé do vivo: `display:grid`, `padding:8px`, altura 66 (protótipo: 57).
- Slot do alerta de certificado: `button.sb-rail-btn.sb-cert-rail` no protótipo × `a.nfe-cert-badge` no vivo. Mesma posição (2º filho), elemento e classe diferentes. Não virou seção: o contrato o põe dentro de `sb-topo`, e no DOM ele é irmão.

## Descobertas (não consertadas, fora do escopo)

- **A chave de persistência difere:** protótipo `oimpresso.sidebar.mode`, vivo `oimpresso.sb.mode` (`shared.ts:181`). Não quebra nada (origens diferentes), mas explica por que o browser do [W] abre expandido a 1280: há escolha manual salva. Isso não é bug.
- O `.sb-group-h` do vivo tem `--gh` via CSS por `[style*="--gh"]`; o protótipo pinta rótulo e ícone inline. É a diferença que a thread 08 vai decidir.

## Prefixo tocado

- `governance/design/targets/cockpit--sidebar.alvo.json` (novo)
- `governance/design/targets/cockpit--sidebar.secoes.json` (novo)
- `governance/design/targets/README.md` (+1 linha na tabela "Alvos exportados")
- este `_saida-07.md`

Nada em `Components/`, `cockpit.css`, `AppShellV2.tsx`, `app/Sidebar/`, nem no índice ou nas fichas.
