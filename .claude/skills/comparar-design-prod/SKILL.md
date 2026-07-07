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

1. **Fonte provada primeiro.** `node scripts/governance/cowork-mirror-freshness.mjs --manifest`
   → pull das âncoras via `DesignSync.get_file` (projeto vivo `019dcfd3…`) → `--compare --check`.
   `STALE` ⇒ re-exportar ANTES de comparar. Comparar contra espelho velho = erro raiz do v1.
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
- ⛔ Sondas diferentes em cada lado (a régua tem que ser idêntica).
- ⛔ Pular D1 (rede): "print igual" esconde full-reload (o pior anti-padrão, D-14).

## Pareada com

- Hook camada 2: `.claude/hooks/design-compare-protocol.mjs` (UserPromptSubmit — lembra este fluxo
  se a skill não disparar).
- `LICOES_CODE.md` LC-06 (classe `visual-compare-eyeball`, two-strikes) · ADR 0299 (`/design-diff`
  previsto → `prototipo-ui/design-diff.mjs`) · `PROTOCOLO-COMPARACAO-RUNTIME.md` (as 8 dimensões).
