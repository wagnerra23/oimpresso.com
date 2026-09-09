# Protótipo — Atualizar cobrança de assinatura (FIN-004)

> **Fonte de design GERADA pelo designer-agente** ([ADR 0282](../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1). Cobre `Financeiro/AssinaturaAtualizar` — uma das 7 telas que tocam valor e não tinham fonte visual.

## 1 · Status — PROPOSTA de forma, não lei

A tela **já existe em produção** (309 linhas de `.tsx`, `related_us: [US-FIN-063]`) e declara `related_prototype: n/a (sem protótipo Cowork …)`. Este protótipo nasceu depois. **Não promover a âncora sem decisão [W]** — a tela altera **quanto um cliente real é cobrado** (o próprio charter avisa: *"biz=4 ROTA LIVRE, prod"*). O `n/a` permanece; nenhum charter foi tocado.

## 2 · Ausência confirmada — os dois donos, medido 2026-09-09

| dono | método | resultado |
|---|---|---|
| repo + espelho | `rg -l --hidden 'AssinaturaAtualizar' prototipo-ui/` | **9 arquivos** — 8 docs/intake **+ `prototipo-ui/cowork/AssinaturaAtualizar.tsx`** |
| Cowork vivo | `DesignSync.list_files` **por ID** | o mesmo `.tsx` na raiz; nenhum desenho |

⚠️ **Errata do recibo herdado.** O inventário dizia *"`FIN-004`/`atualizar cobran` → 0"* — grep de string literal, o instrumento que [§5 2026-08-18](../../../memory/proibicoes.md) proíbe. **O arquivo existe**, com o nome da tela. Mas **não é fonte**: medido, é **porte reverso** do código vivo — 310 linhas contra 309 do `.tsx` de produção, diferindo só na API do PageHeader (`@/Components/shared/PageHeader` + `description`/`action` contra `@/Components/PageHeader` + `subtitle`/`actions`). Ancorar nele seria ancorar a tela nela mesma ([§5 2026-06-05](../../../memory/proibicoes.md)). **A conclusão "sem fonte" sobrevive; o recibo é que estava errado.**

## 3 · O que foi desenhado

O charter define a tela por um Goal central — **preview de impacto antes de confirmar** — e dois anti-hooks. O desenho é isso:

| bloco | conteúdo |
|---|---|
| lista | assinaturas ativas: plano, situação, valor atual, ciclo, forma, próximo vencimento |
| form | aparece **só após selecionar** (charter §UX targets); 3 campos, com marca visual em quem mudou |
| **preview** | diff campo a campo `de → para`, só do que mudou (o payload é parcial) |
| ações | botão **travado** enquanto não houver diff real, e o rótulo conta quantas mudanças |

Estados desenhados: **sem seleção**, **selecionado sem mudança** (com a frase que explica o botão travado), **1 mudança**, **3 mudanças**, **valor inválido**.

## 4 · Âncora do domínio — nada inventado

| o que | de onde |
|---|---|
| shape da lista (`plano` · `status` · `next_due_date` · `valor_atual` · `ciclo_atual` · `forma_pagamento_atual`) | `AssinaturaController::showAtualizar` (`:41`) |
| `valor` `numeric min:0.01` · `ciclo` in `mensal\|trimestral\|semestral\|anual` · `forma_pagamento` in `boleto\|pix\|cartao` · todos `sometimes` | `UpdateAssinaturaRequest::rules()` (`:32-34`) |
| mensagem *"Valor deve ser maior que zero."* | `UpdateAssinaturaRequest::messages()` (`:43`) |
| preview de impacto · form só após seleção · botão só com diff real · nunca PATCH automático | `AssinaturaAtualizar.charter.md` §Goals e §Anti-hooks |

## 5 · Uma divergência de domínio entre módulos — registrada, não resolvida

O mesmo conceito tem **dois vocabulários** no código:

| onde | valores |
|---|---|
| `rb_plans.ciclo` (migration) e `StorePlanRequest` | `monthly` · `quarterly` · `semiannual` · `yearly` · `custom` |
| `UpdateAssinaturaRequest.ciclo` (esta rota) | `mensal` · `trimestral` · `semestral` · `anual` |

Desenhei **cada tela com o vocabulário da sua própria rota** — é o que cada `Rule::in` aceita, e inventar uma tradução seria desenhar comportamento que não existe. Mas a divergência é real: um plano `yearly` vira assinatura `anual`, e nada no código que li faz essa ponte explicitamente. Reconciliar (ou declarar que são domínios distintos de propósito) é **decisão [W]**, não conserto de desenho.

**Segunda divergência, mesma natureza:** o charter declara a permissão `recurringbilling.assinatura.update`, mas o controller usa `recurringbilling.subscriptions.manage` — e o comentário dele (`:71-77`) explica que a do charter *"não é declarada em lugar nenhum"* e que por isso editar assinatura só funcionava para admin via `Gate::before` (US-GOV-059 classe C). O **charter está desatualizado**; corrigi-lo é PR de charter, fora do escopo deste desenho.

## 6 · Conformidade e runtime — medido, não screenshot

Zero cor crua. **13 tokens** do DS, prefixo `.as-`. Host estático com as 3 folhas do shell, viewport **1280**:

| medida | resultado |
|---|---|
| console | **0 erro** |
| tokens não resolvidos | **0** de 13 (sonda com controle positivo e negativo) |
| scroll horizontal @1280 | **false** |
| form antes de selecionar | **ausente** — como o charter pede |
| ao selecionar | valor nasce `349,90` (2 casas), botão **travado**, frase explicando por quê |
| 1 mudança | `Valor por ciclo · R$ 349,90 → R$ 399,90` · botão *"Confirmar 1 alteração"*, liberado |
| 3 mudanças | as 3 linhas de diff · botão *"Confirmar 3 alterações"* |
| valor `0` | *"Valor deve ser maior que zero."* · botão **travado** · o diff cai para 2 linhas (o valor sai) |

## 7 · Aviso Tier 0

Esta tela **altera cobrança de cliente real**. O desenho não muda cálculo nenhum, mas quem levar ao `.tsx` cai na regra-mestre inteira de [proibicoes.md](../../../memory/proibicoes.md) — dois caminhos de prova, tabela antes→depois, aprovação [W]. O preview de impacto desenhado aqui **é** a tabela antes→depois na tela; ele não substitui a prova no PR.

## 8 · O que este protótipo NÃO decide

- **Se vira âncora** — decisão [W] (§1).
- **A forma da produção** — divergência é dado para `design-diff --probe`, nunca veredito no olho (LC-06).
- **O audit trail visível**, que o charter marca como Non-Goal ("futuro"). Não desenhei.

## Refs
[ADR 0282](../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 · [UI-0013](../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [UI-0029](../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) · [INVENTÁRIO §5.3/§5.7](../../../memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md) · precedente [#7145](https://github.com/wagnerra23/oimpresso.com/pull/7145)
