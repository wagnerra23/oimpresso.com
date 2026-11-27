---
name: triagem-tickets-dev
description: ATIVAR quando o usuário colar uma LISTA de tickets/pendências de desenvolvimento (várias telas ou módulos de uma vez) ou pedir "triagem dos tickets", "organiza essa lista", "modo econômico", "economizar tokens nos tickets", "/triagem-tickets". Classifica cada item por tipo e tamanho, agrupa por causa provável e por módulo, propõe a ordem e quantas sessões usar, e define o modo econômico de execução. NÃO executa os tickets e NÃO abre código na triagem. Diferente de `ticket-triage`, que prioriza conversas de ATENDIMENTO ao cliente (inbox).
tier: B
auto_trigger: on_demand
status: active
version: 1.0
---

# triagem-tickets-dev — triagem em lote com economia de tokens

## Entrada

Uma linha por item basta: `tela/módulo — o que está errado`. A descrição longa só é
necessária quando o assunto é vago, quando mexe em VALOR ou ESTOQUE, ou quando um cliente
específico está afetado.

**Imagens:** não são necessárias na triagem nem na execução de comparativo — a comparação é
medida pela sonda (`design-diff.mjs`), e screenshot não é prova (skill `comparar-design-prod`).
Anexe imagem só quando o texto não deixa claro QUAL componente é (ex.: "o botão ali em cima").

## Triagem (sem abrir código)

Classifique cada item num destes tipos — o tipo decide o custo e quem resolve:

| Tipo | Sinal no texto | Primeiro passo barato |
|---|---|---|
| **ROTA** | "abre a tela antiga em Blade", "não abre o React", "erro ao abrir" | ver a rota/link e se a Page React existe |
| **AUSENTE** | "não aparece", "não subiu", "falta a gaveta" | checar flag de cutover, pacote do superadmin e permissão (3 camadas) ANTES de supor que falta construir |
| **VISUAL** | borda, espaçamento, pílula, fonte, cor, tamanho | medir com a sonda; procurar causa COMUM a várias telas |
| **COPY/DECISÃO** | nome confuso, "não sei se deve existir" | decisão do dono — pergunta curta, não código |
| **VAGO** | "vários componentes diferem" | medir primeiro para virar lista; só então estimar |
| **VALOR/ESTOQUE** | total, preço, saldo, quantidade (dado, não fonte tipográfica) | Tier 0: dupla prova + antes→depois |

Regras da triagem:
1. **Agrupe por causa provável, não por tela.** O mesmo defeito em 4 telas costuma ser um
   componente ou CSS compartilhado — um conserto, não quatro.
2. Marque ambiguidade em vez de supor (ex.: "fonte" = tipografia ou origem do dado?).
3. Tamanho: **P** (1–2 arquivos), **M** (uma tela), **G** (gaveta/tela inteira a construir ou
   módulo) — e diga se cabe numa sessão.
4. Saída: tabela `item · tipo · tamanho · grupo` + ordem sugerida + perguntas abertas.

## Ordem padrão

1. Causas comuns (maior alavanca). 2. ROTA (vitórias rápidas). 3. AUSENTE — uma checagem de
flags/pacote responde vários de uma vez. 4. VISUAL por tela. 5. VAGO, depois de medido.
COPY/DECISÃO vai junto, como pergunta, sem bloquear o resto.

## Modo econômico na execução

- **Uma sessão por grupo**, `/clear` entre grupos, `/compact` após cada PR mergeado.
- Item **P**: sem pré-flight extenso, sem `ciclo-adversary`, sem handoff, sem session log.
- Leia só os arquivos do item; não explore o módulo inteiro.
- Varredura mecânica (renomear, buscar em muitos arquivos) → subagente com modelo mais barato.
- **Nunca economiza:** `business_id` (ADR 0093), dupla prova em valor/estoque, CI verde antes
  de propor merge, aprovação do dono para merge (R10).

## Comparativo protótipo × produção — prompt padrão

Todo item VISUAL roda com este pedido como padrão, sem o usuário precisar colar:

> Cheque todas as telas, gavetas e drawers e iguale todos os componentes ao protótipo em
> tudo. Meça os dois lados com a mesma sonda e me mostre a tabela de todas as propriedades
> que diferem, com antes e depois. O que não puder ficar igual, me diga qual e por quê antes
> de abrir o PR.

Execução pela skill `comparar-design-prod` (âncora via `node scripts/design/ancora.mjs`).
⚠️ Medir produção exige navegador com acesso e login (sessão local com browser, ou staging).
Uma sessão na nuvem sem acesso à produção pode medir o protótipo e ler o código, mas não pode
afirmar como a tela está em produção — diga isso em vez de supor.
