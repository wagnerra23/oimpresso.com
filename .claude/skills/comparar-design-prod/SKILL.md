---
name: comparar-design-prod
description: BLOQUEADOR de eyeball — ATIVAR SEMPRE que a tarefa envolver COMPARAR design/protótipo com tela em produção ou declarar que estão iguais. Gatilhos "compare o design com a tela", "confira a tela contra o protótipo", "o que mudou no protótipo", "iguale os dois", "está igual ao design?", "as diferenças que o design encontrou", "aplicou certo?", "ficou igual?", "veja se o protocolo funcionou", OU antes de EU declarar "igual/aplicado/fiel ao design" sobre qualquer tela. Carrega o PROTOCOLO-COMPARACAO-RUNTIME (D1–D8) + o mecanismo `prototipo-ui/design-diff.mjs` — comparação é MEDIDA (computed style, mesma sonda nos dois lados), NUNCA no olho. Origem strike 2 (LICOES_CODE LC-06, 2026-07-07)- o agente eyeballou 2x e o Wagner pegou com o canário do alinhamento.
tier: B
---

# comparar-design-prod — comparação MEDIDA, nunca no olho

> **Por que existe (LC-06, strike 2):** em 06/07 e 07/07 o agente comparou design×prod por
> screenshot e declarou "estruturalmente igual" — perdeu KPI center×left, dark-mode invisível
> e o roxinho do primary. Wagner: *"o processo é mais importante do que arrumar"*. A regra dura:
> **screenshot é ilustração; a prova é a MEDIDA.**

## O fluxo obrigatório (nenhum passo é opcional)

0. **A fonte EXISTE no git?** (passo novo — 2026-08-11, e é o que faltava)
   `node scripts/governance/cowork-mirror-freshness.mjs --live-only <list_files.json>`
   O `--compare` prova que **o que está no espelho** acompanha o vivo; **nunca** que o espelho
   **cobre** o vivo. São perguntas diferentes e só esta responde *"o time no git tem a fonte?"*.
   Medido no dia em que nasceu: **25 de 1310 paths fora do espelho, 14 deles protótipo de tela**
   — incluindo o `jana-merge.jsx`, citado por 21 sites do repo e não versionado.
   ⚠️ Faltando a fonte, **NÃO compare** — exporte antes (passo 1b).

1. **Fonte provada primeiro.** `node scripts/governance/cowork-mirror-freshness.mjs --manifest`
   (v3: âncoras **+ deps de render** do shell) → pull via `DesignSync.get_file` (projeto vivo
   `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`) das âncoras + deps globais (`app.jsx` · `styles.css` ·
   `ds-v6/tokens.css`) + css do módulo → `--compare --check`. `STALE` ⇒ re-exportar ANTES de
   comparar. Só âncora = cego pra infra (LC-07: o PageHeader roxo do [W] driftou no `app.jsx`
   e a rodada ficou verde).
   ⚠️ **Há arquivos HOMÔNIMOS no projeto vivo** (ex.: `jana-merge.jsx` na raiz **e** em
   `prototipo-ui/cowork/`, com conteúdo DIFERENTE — 943 × 923 linhas). O path canônico é o que
   o **`cowork` do manifesto** declara, não o que parece mais organizado. Baixar do errado dá
   `STALE` — e, se você medir um charter contra ele, "corrige" refs que estavam certas
   (aconteceu em 2026-08-11).

1b. **Exportar é `--export-from`, NUNCA transcrever.**
   `DesignSync.get_file` de cada path → salve os JSON num diretório → 
   `node scripts/governance/cowork-mirror-freshness.mjs --export-from <dir>`
   O script escreve `raw.content` direto: fiel por construção. Transcrever à mão produziu um
   arquivo com 20 linhas a menos que passou despercebido até o `--compare`.
2. **Mesmo tema nos dois lados.** O tema é o que o Wagner usa (hoje: dark). Comparar light×dark
   invalida D6 inteira.
3. **Mesma sonda, medida:** `node prototipo-ui/design-diff.mjs --probe` → injetar a sonda IGUAL
   nos dois renders via Chrome MCP (`window.__DD_ROLES` mapeia os seletores por papel: `.fin-stat`
   na prod × `.os-stat` no design) → salvar os 2 JSON → `--compare prod.json design.json --check`.
4. **Dimensões não-mecanizadas** (D1 rede/partial-reload · D3 ícones · D5 footer/somatórios):
   seguir o [PROTOCOLO-COMPARACAO-RUNTIME](../../../memory/requisitos/_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
   passo a passo — D1 SEMPRE (clicar 1 filtro + `read_network_requests`).
5. **Canário:** antes de concluir, validar a sonda contra UMA diferença já conhecida (ex: o
   alinhamento dos KPI). Sonda que não pega o canário = sonda quebrada, não "tela igual".
6. **Veredito por dimensão** (`IGUAL / DIVERGE(bug) / DIVERGE(decisão) / PROD-À-FRENTE`) →
   registrar no `<tela>-visual-comparison.md` (append; 1 tema = 1 doc).

## Proibições desta skill

- ⛔ Declarar "igual/aplicado/fiel" a partir de screenshot — print não distingue center×left.
- ⛔ Comparar contra `prototipo-ui/cowork/` sem provar `SYNC` naquele arquivo.
- ⛔ **Ler `--compare` verde como "o espelho está completo".** Ele mede o que ESTÁ lá; o que
  nunca desceu é invisível por construção (o universo vem do `readdir` do espelho). Use o
  `--live-only`. Régua cujo universo vem do lado que você controla mede a sua diligência,
  não a realidade.
- ⛔ **Declarar que um protótipo não existe** a partir de `DesignSync{list_projects}` — ele
  enumera **só design-systems**, e o protótipo do ERP vive em projeto REGULAR
  (`type: PROJECT_TYPE_PROJECT`). Vá direto ao `list_files` com o projectId.
- ⛔ **Transcrever conteúdo** de arquivo entre sistemas — nem "com cuidado", nem "conferindo
  depois". Quando o conteúdo chega como dado (JSON do `get_file`), a escrita sai do dado.
- ⛔ Sondas diferentes em cada lado (a régua tem que ser idêntica).
- ⛔ Pular D1 (rede): "print igual" esconde full-reload (o pior anti-padrão, D-14).
- ⛔ **Medir contraste pelo AGREGADO de um elemento composto** (range min↔max do card/bloco) —
  **medir não basta, tem que ser POR ELEMENTO**. Card com 3+ cores tem par bom e par ruim ao
  mesmo tempo; o agregado pega o melhor e **mascara o pior**. _Caso 2026-07-16 (KPIs do Board
  da Oficina, PR #4367→#4373):_ agregado do card = **8,75:1** → veredito "passa AA, é só
  estética" → **falso**. Por elemento: sub `"0 boxes/elevadores"` = **1,92:1** · `"0 aguardam OK
  do cliente"` = **2,04:1** — **AA reprovado**. Controle que fecha a causa: a MESMA sub no tom
  `default` (fundo `bg-card` escuro) = **5,27:1** → o culpado é o **fundo claro**, não a sub
  (`text-muted-foreground` é fixo). Sempre medir **par a par (fg×bg de cada texto)** + rodar um
  **controle** que isole a variável. Corolário do LC-06: o strike 1 é olhar; o strike 2 é medir
  a coisa errada — e esse passa despercebido porque **vem com número**.

## Pareada com

- Hook camada 2: `.claude/hooks/design-compare-protocol.mjs` (UserPromptSubmit — lembra este fluxo
  se a skill não disparar).
- `LICOES_CODE.md` LC-06 (classe `visual-compare-eyeball`, two-strikes) · ADR 0299 (`/design-diff`
  previsto → `prototipo-ui/design-diff.mjs`) · `PROTOCOLO-COMPARACAO-RUNTIME.md` (as 8 dimensões).
