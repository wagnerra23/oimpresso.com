---
proposal_id: sidebar-dark-vs-light
status: decided
decision: A
created: 2026-05-24
decided_at: 2026-05-24
proposed_by: claude-code
decided_by: wagner
parent_adr: UI-0013 (Constituição UI v2)
related_adrs: [UI-0008, UI-0009, UI-0014]
resulting_adr: UI-0014
type: conflito-camada-2-shell
---

# Proposta · Sidebar dark vs light — desempate explícito

> **Status:** ✅ **DECIDED 2026-05-24 — opção A** (sidebar permanece light).
> Comando exato do Wagner: *"eu realmente gosto como esta hoje. não gostaria de mudar"*.
> Formalizada em **[ADR UI-0014](../../requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md)**.
> Este doc fica como referência histórica do desempate.

## Contexto

A **Constituição UI v2** (handoff Claude Design 2026-05-24, sessão chat8) propõe **sidebar sempre dark, em qualquer tema** ([ADR 0041 v2](../../../.claude/worktrees/frosty-greider-83ab2f/_v2-tmp/project/06-decisoes/0041-sidebar-sempre-dark.md) — externa) — citando Stripe Dashboard, Vercel, Linear como benchmark. Argumento:

1. **Diferenciação visual** — sidebar light vira parecido com filtros/toolbar, perde papel de "chrome de navegação"
2. **Consistência de referência** — usuário sempre tem âncora visual à esquerda, mesmo trocando de tema
3. **Cor de origem dos grupos** — hues `oklch(0.62 0.13 H)` precisam de fundo escuro pra contrastar; em fundo claro ficam diluídos

O oimpresso JÁ TEM decisão oposta vigente: [**ADR UI-0009 Cockpit Sidebar Light padrão**](../../requisitos/_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md), aceita 2026-05-04, **superseding parcial** o trecho "sidebar dark" da [UI-0008](../../requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md). Sidebar light foi escolha **explícita do Wagner** quando viu a versão dark inicial — comando registrado: "manter sidebar" (light).

## Conflito

| Aspecto | v2 (proposta) | oimpresso (vigente) |
|---|---|---|
| Cor sidebar base | dark fixo `oklch(0.21 0 0)` | light `data-theme` do usuário |
| Tokens | `--sb-*` literais fora do `[data-theme="dark"]` | `--sb-*` herda `data-theme` |
| Benchmark | Stripe, Vercel, Linear | Wagner-preference 2026-05-04 |
| Cor grupos (Operação/Fiscal/etc) | `oklch(0.62 0.13 H)` brilha em dark | precisa hue/L ajustado em light |
| Reversibilidade | trivial (toggle tokens) | trivial (toggle tokens) |
| Impacto | shell único — afeta todas as telas | shell único — afeta todas as telas |

## Por que isto não pode regredir silenciosamente

Se um agente futuro (Claude DS, Claude Code, sucessor) ler a Constituição UI v2 e aplicar "dark sempre" sem ler UI-0009, **regressão automática** — Wagner perde a escolha que fez 2026-05-04. Esta proposta congela o estado e força desempate explícito.

## Opções

### Opção A · Manter UI-0009 (sidebar light)

- ✅ Honra escolha explícita do Wagner 2026-05-04
- ✅ Zero refactor — sidebar já vive light em produção há 20 dias
- ✅ Cliente Larissa (biz=4 ROTA LIVRE) já habituou
- ❌ Diverge de benchmark Stripe/Vercel/Linear
- ❌ Hues dos grupos precisam ajuste se v2 incorporar paleta de 11 hues

### Opção B · Adotar v2 (sidebar dark sempre)

- ✅ Casa com benchmark global Stripe/Vercel/Linear
- ✅ Diferenciação visual mais forte (sidebar = "chrome", main = "conteúdo")
- ✅ Cor de grupos (`oklch(0.62 0.13 H)`) brilha mais
- ❌ Reverte decisão Wagner 2026-05-04 — precisa ADR explícita `supersedes: [UI-0009]`
- ❌ Larissa precisa reaprender visual (mitigação: avisar antes de deploy + fallback toggle no Aparência)
- ❌ Refactor `cockpit.css` move `--sb-*` pra fora do `[data-theme="dark"]`

### Opção C · Híbrido (toggle no Aparência)

- ✅ Wagner não escolhe — usuário escolhe
- ✅ Comporta ambas escolas
- ❌ Token custodial: dobra superfície de teste (cada tela renderiza nos 2 modos)
- ❌ Larissa não toca no toggle — fica no default da casa = volta ao problema "qual o default?"
- ❌ Anti-padrão "ESM" (Excessive Setting Multiplication) — Anthropic skill `update-config` recomenda evitar setting que poucos usuários tocam

### Opção D · Pendente (mantém UI-0009, posterga decisão)

- ✅ Status quo · zero risco
- ❌ Decisão volta a aparecer toda vez que um novo handoff Claude Design propor mudança
- ❌ Constituição UI v2 fica "incompleta" no oimpresso até decidir

## Recomendação técnica

**Opção A** se prioridade é estabilidade + honrar histórico Wagner.
**Opção B** se prioridade é adotar v2 integralmente + alinhar com Stripe.
**Opção C** é tentação de "ter os dois" — geralmente vira dívida (`update-config` skill anti-padrão).
**Opção D** evita decisão — pode acumular se outras propostas v2 dependerem desta.

Eu recomendo **A ou B explícito, não C nem D**. Wagner decide qual com base em preferência atual.

## Se A for escolhida

1. ADR UI-0014 formaliza: "Constituição UI v2 adotada exceto sidebar dark — Wagner mantém UI-0009 light"
2. v2 ADR 0041 entra como `historical` no oimpresso (referência, não vigente)
3. PT-01-Lista.md atualiza tabela de tokens pra refletir light
4. CHANGELOG apenda `DECISION · A · sidebar permanece light · Wagner`

## Se B for escolhida

1. ADR UI-0014 supersedes UI-0009 explicitamente
2. `cockpit.css` move `--sb-*` pra fora de `[data-theme="dark"]`
3. Smoke test em biz=4 ROTA LIVRE antes de deploy prod (Larissa)
4. Bump v2.0.0 do DS (mudança Fundação/Shell breaking)
5. ADR menciona "se feedback de fadiga visual em dark mode, considerar L: 0.16 ao invés de 0.21" (mitigação preventiva da v2)

## Próximo passo

Wagner abre PR de aprovação dessa proposta marcando **A** ou **B** no comentário. Claude Code cria ADR UI-0014 correspondente, atualiza CHANGELOG, e (se B) inicia refactor em PR separado.

## Refs

- [ADR UI-0008](../../requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md) — Cockpit layout-mãe (sidebar dark original)
- [ADR UI-0009](../../requisitos/_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md) — sidebar light padrão (vigente)
- [ADR UI-0013](../../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (aditiva)
- ADR 0041 v2 (externa) — sidebar sempre dark
- [ADR 0185](../0185-drawer-760-canon-entidades-cadastrais.md) — drawer 760 canônico (similar pattern de desempate)
