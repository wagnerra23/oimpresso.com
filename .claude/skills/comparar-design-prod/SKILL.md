---
name: comparar-design-prod
description: BLOQUEADOR de eyeball — ATIVAR SEMPRE que a tarefa envolver COMPARAR design/protótipo com tela em produção ou declarar que estão iguais. Gatilhos "compare o design com a tela", "confira a tela contra o protótipo", "o que mudou no protótipo", "iguale os dois", "está igual ao design?", "as diferenças que o design encontrou", "aplicou certo?", "ficou igual?", "veja se o protocolo funcionou", OU antes de EU declarar "igual/aplicado/fiel ao design" sobre qualquer tela. Carrega o PROTOCOLO-COMPARACAO-RUNTIME (D1–D8) + o mecanismo `scripts/design/design-diff.mjs` — comparação é MEDIDA (computed style, mesma sonda nos dois lados), NUNCA no olho. Origem strike 2 (LICOES_CODE LC-06, 2026-07-07)- o agente eyeballou 2x e o Wagner pegou com o canário do alinhamento.
tier: B
---

# comparar-design-prod — comparação MEDIDA, nunca no olho

> **Por que existe (LC-06, strike 2):** em 06/07 e 07/07 o agente comparou design×prod por
> screenshot e declarou "estruturalmente igual" — perdeu KPI center×left, dark-mode invisível
> e o roxinho do primary. Wagner: *"o processo é mais importante do que arrumar"*. A regra dura:
> **screenshot é ilustração; a prova é a MEDIDA.**

## O fluxo obrigatório (nenhum passo é opcional)

1. **Fonte = o arquivo do git, resolvido pela máquina.** `node scripts/design/ancora.mjs <Mod/Tela>`
   diz qual arquivo sob `prototipo-ui/` é a âncora da tela; é ele que se renderiza do lado design.
   Até 2026-10-23 os passos 0 e 1 desta skill mediam o espelho contra o Cowork vivo
   (`--live-only`, `--manifest` → `get_file` → `--compare`). A ADR 0438 tirou o Cowork como fonte,
   e esses modos saíram do `cowork-mirror-freshness` na onda 2c-2.

1b. **A fonte é o arquivo do git — não há rota de download** ([ADR 0438](../../../memory/decisions/0438-artefato-substitui-cowork-como-superficie-do-prototipo.md), 2026-10-23).
   Compare contra o arquivo versionado sob `prototipo-ui/`. Se a âncora não existir no git, ela não
   existe: o Code **gera** ancorado no DS canon ([ADR 0282](../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md)),
   nunca baixa do Cowork. Até 2026-10-23 este passo ensinava as rotas de baixar (`aplicar-payload.mjs`
   e `--export-from`); o `aplicar-payload` saiu na onda 2a da 0438.
2. **Mesmo tema nos dois lados.** O tema é o que o Wagner usa (hoje: dark). Comparar light×dark
   invalida D6 inteira.
2b. **D0 · IDENTIDADE DA VIEW — antes de qualquer dimensão.**
   `node scripts/design/design-diff.mjs --compare prod.json design.json --contrato governance/design/contracts/<tela>.contract.json`
   O `ancora.mjs` responde QUAL ARQUIVO é a âncora, **nunca QUAL VIEW dentro dele** — e medido em
   2026-08-28 **38 telas compartilham âncora** (`ponto-telas.jsx` sozinho serve 17; `jana-merge.jsx`, 3).
   O shell do Cowork carrega os protótipos JUNTOS, então "abrir a âncora" não diz em que tela você está.
   Sem esta prova a comparação mede a TELA ERRADA e devolve veredito **plausível** — o pior erro, porque
   não se denuncia. Sai **exit 2 = NÃO MEDI** (nunca 0) quando os lados não são a mesma tela.
   O veredito é **relacional**: copy de shell (header, selo de plano) aparece em toda view, então o que
   denuncia é a ASSIMETRIA entre os lados — no caso real que originou isto, `3 × 1`.

3. **Mesma sonda, medida:** `node scripts/design/design-diff.mjs --probe` → injetar a sonda IGUAL
   nos dois renders via Chrome MCP (`window.__DD_ROLES` mapeia os seletores por papel: `.fin-stat`
   na prod × `.os-stat` no design) → salvar os 2 JSON → `--compare prod.json design.json --check`.
4. **Dimensões não-mecanizadas** (D1 rede/partial-reload · D3 ícones · D5 footer/somatórios):
   seguir o [PROTOCOLO-COMPARACAO-RUNTIME](../../../memory/requisitos/_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
   passo a passo — D1 SEMPRE (clicar 1 filtro + `read_network_requests`).
5. **Canário:** antes de concluir, validar a sonda contra UMA diferença já conhecida (ex: o
   alinhamento dos KPI). Sonda que não pega o canário = sonda quebrada, não "tela igual".
6. **Veredito por dimensão** (`IGUAL / DÍVIDA A FECHAR / PROTÓTIPO ATRASADO`) → registrar no
   `<tela>-visual-comparison.md` (append; 1 tema = 1 doc). ~~`DIVERGE(decisão)` e
   `PROD-À-FRENTE`~~ **saíram em 2026-09-18, por revogação de [W]** (*"agora é o Protótipo quem
   manda, e a paridade deve ser o objetivo"*): no eixo FORMA **não há divergência aceita, há
   dívida**. O que era `DIVERGE(decisão)` vira **DÍVIDA A FECHAR** (a prod converge); o que era
   `PROD-À-FRENTE` vira **PROTÓTIPO ATRASADO** (re-exportar o protótipo, que é o lado a mover).
   Os dois rótulos antigos ficam riscados como registro; **não são mais veredito válido**.
   Fonte: [PROTOCOLO-COMPARACAO-RUNTIME §Regra de veredito](../../../memory/requisitos/_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
   + [RESPEITAR-PROTOTIPO](../../../memory/requisitos/_DesignSystem/RESPEITAR-PROTOTIPO.md).
   ⚠️ **O eixo importa:** isto vale para **FORMA** (layout, cor, tipografia, ícone, rótulo,
   afordância, estado). **Visibilidade** (permissão/pacote/módulo), **dado** e **comportamento**
   seguem do código — ali a cadeia é a de [proibicoes.md](../../../memory/proibicoes.md)
   (*teste verde > casos > charter > SPEC*), e ausência de dado no protótipo **não** é dívida.

## Proibições desta skill

- ⛔ Declarar "igual/aplicado/fiel" a partir de screenshot — print não distingue center×left.
- ⛔ Comparar contra `prototipo-ui/cowork/Wagner/` sem provar `SYNC` naquele arquivo.
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
  previsto → `scripts/design/design-diff.mjs`) · `PROTOCOLO-COMPARACAO-RUNTIME.md` (as 8 dimensões).
