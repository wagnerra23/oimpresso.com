# ADR UI-0014 · Sidebar light mantida — Constituição UI v2 adotada parcialmente (sem dark sempre)

- **Status**: accepted
- **Data**: 2026-05-24
- **Aprovado em**: 2026-05-24 — Wagner explícito "eu realmente gosto como esta hoje. não gostaria de mudar"
- **Decisores**: Wagner (preferência declarada), Claude Code (executor)
- **Categoria**: ui · desempate · governança
- **Confirma**: [UI-0009](0009-cockpit-sidebar-light-padrao.md) — sidebar light padrão (vigente)
- **Aceita parcialmente**: [UI-0013](0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (4 camadas + regra-mestre + vocabulário)
- **Não adota**: ADR 0041 externa da Constituição UI v2 (sidebar dark sempre)
- **Refs**:
  - [UI-0008](0008-cockpit-layout-mae-do-erp.md) — Cockpit layout-mãe
  - [UI-0009](0009-cockpit-sidebar-light-padrao.md) — sidebar light padrão
  - [UI-0013](0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 aceita
  - [proposals/2026-05-24-sidebar-dark-vs-light.md](../../../decisions/proposals/2026-05-24-sidebar-dark-vs-light.md) — proposal decidida (opção A)

## Contexto

A [ADR UI-0013](0013-constituicao-ui-v2-camadas.md) (Constituição UI v2 — hierarquia de 4 camadas) foi aprovada por Wagner em 2026-05-24. A Constituição v2 traz, junto da hierarquia, uma proposta paralela de **sidebar dark sempre** (ADR 0041 externa, fundamentada em Stripe Dashboard, Vercel, Linear como benchmark).

O oimpresso JÁ TEM decisão oposta vigente: [UI-0009 Cockpit Sidebar Light padrão](0009-cockpit-sidebar-light-padrao.md), aceita 2026-05-04 por escolha explícita do Wagner ("manter sidebar" light, vs versão dark inicial do Cockpit).

Para evitar regressão silenciosa (futuro agente lendo v2 e aplicando dark sem ler UI-0009), o conflito foi formalizado em [proposal](../../../decisions/proposals/2026-05-24-sidebar-dark-vs-light.md) com 4 opções (A manter light · B adotar dark · C híbrido toggle · D postergar).

Em 2026-05-24, Wagner desempatou — comando exato: *"eu realmente gosto como esta hoje. não gostaria de mudar"* — escolhendo **opção A**.

## Decisão

**Sidebar do oimpresso permanece light** (segue `data-theme` do usuário — light por padrão, dark elegante quando user troca). [UI-0009](0009-cockpit-sidebar-light-padrao.md) **permanece vigente** sem mudança.

**Constituição UI v2 ([UI-0013](0013-constituicao-ui-v2-camadas.md)) é adotada integralmente** exceto pelo trecho "sidebar dark sempre" — esse trecho da v2 entra no histórico como **referência rejeitada** (não vigente no oimpresso).

### Concretamente

- ✅ Hierarquia 4 camadas (Fundações → Shell → PT → Módulo) — adotada via UI-0013
- ✅ Regra-mestre "não-gastar-tokens-com-pedido-vago" — adotada via UI-0013
- ✅ Vocabulário canônico de pedido — adotado via UI-0013
- ✅ PT-01 Lista canônico — adotado via [`padroes-tela/PT-01-Lista.md`](../padroes-tela/PT-01-Lista.md)
- ✅ PRE-MERGE-UI checklist — adotado via [`PRE-MERGE-UI.md`](../PRE-MERGE-UI.md)
- ❌ Sidebar dark sempre — **não adotado** · UI-0009 vence
- ❌ Tokens `--sb-*` fora de `[data-theme="dark"]` — **não aplicar**

### Tokens `--sb-*` permanecem como hoje

```css
/* cockpit.css — sem mudança nesta ADR */
:root {
  --sb-bg:        oklch(0.985 0.003 90);    /* light por padrão */
  --sb-text:      oklch(0.22 0.01 80);
  /* ... seguem data-theme do usuário */
}

[data-theme="dark"] {
  --sb-bg:        oklch(0.21 0 0);          /* dark quando user escolhe */
  --sb-text:      oklch(0.78 0 0);
}
```

## Justificativa Wagner-explícita

- **"Eu realmente gosto como esta hoje"** — preferência declarada, peso máximo
- Larissa (biz=4 ROTA LIVRE, cliente principal) já habituou ao sidebar light há ~20 dias em produção — trocar seria custo cognitivo sem benefício claro
- Stripe/Vercel são benchmark, não autoridade. Wagner é único humano + único aprovador (matriz de governance UI-0013)
- v2 ADR 0041 externa preserva argumento técnico válido (diferenciação visual, cor de grupos brilhar em dark) — fica no histórico como alternativa registrada se um dia preferência mudar

## Consequências

### Positivas

- **Zero refactor** — `cockpit.css` permanece intacto, UI-0009 já operacional
- **Honra histórico Wagner** explícito 2026-05-04 + 2026-05-24 (consistência cross-sessão)
- **Larissa não precisa reaprender** visual em produção
- **Constituição v2 fica completa** no oimpresso (só o trecho sidebar dark fica de fora)
- **Proposal decidida formalmente** — futuro agente lendo v2 dark vai bater nesta ADR antes de aplicar

### Negativas

- Diverge do benchmark Stripe/Vercel/Linear na cor da sidebar (aceito explicitamente)
- Se v2 incorporar paleta de 11 hues semânticos no futuro (sem ADR aprovada ainda), cores de grupo podem precisar ajuste pra contrastar bem em fundo light — abrir ADR nova quando ocorrer
- Próxima Claude Design vai propor dark de novo provavelmente — esta ADR é a resposta

### Neutras / a observar

- Se algum dia feedback de Larissa ou outros clientes pedir dark mode mais consistente, reavaliar via ADR explícita (nunca silenciosa)
- v2 ADR 0041 externa fica em `prototipo-ui/.../` se importada — marcada como `not_adopted` ou referência histórica

## Status do proposal de origem

A [proposal sidebar-dark-vs-light](../../../decisions/proposals/2026-05-24-sidebar-dark-vs-light.md) muda de `discussion` → `decided` com:
- `decision: A`
- `decided_by: Wagner`
- `decided_at: 2026-05-24`
- `resulting_adr: UI-0014`

## Próximos passos (não bloqueantes desta ADR)

- Nenhum refactor de código necessário — UI-0009 já está em produção há 20+ dias
- Constituição v2 segue adotada via UI-0013 — PT-02..PT-05 abrem ADRs próprias quando vierem
- Se Wagner futuro mudar preferência → abre nova ADR `supersedes: [UI-0014]` e migra `--sb-*` tokens

## Pegadinhas conhecidas

- **Não confundir** com modo dark via `data-theme="dark"` — sidebar acompanha o tema do usuário (light/dark via toggle Aparência). UI-0014 só rejeita "**dark sempre** independente do tema".
- **Próxima Claude Design** lendo Constituição UI v2 externa pode propor sidebar dark — citar UI-0014 como resposta canônica.
- **Não voltar ao tema sem ADR** — se a paleta de 11 hues v2 for adotada, recalcular contraste hue-em-light antes de aplicar.
