---
date: "2026-08-08"
time: "19:38 BRT"
slug: "permissoes-classe-d-idioma-gate-before"
tldr: "Classe D da US-GOV-059 triada: órfãs 24 → 17. O achado: o Gate::before tem DUAS pernas e o detector modela uma — a perna else faz toda ability não-declarada responder 'sim pra admin, não pro resto', e 4 nomes exploram isso de propósito, então declarar seria nocivo. Mais 4 nomes errados no menu trocados pelo gate real do endpoint e 3 permissões declaradas, vistas vivas em prod. Dois casos sob a regra VALOR/ESTOQUE ficaram medidos e apresentados, sem uma linha tocada, aguardando [W]."
cycle: null
prs: [5384]
us: ["US-GOV-059"]
decided_by: [W]
next_steps:
  - "Decidir edit_purchase_price: declarar (recomendado) + registrar que o flag de UI não é barreira de segurança"
  - "Decidir report.stock_details: separar leitura de mutação (recomendado) OU declarar como está aceitando a armadilha do nome"
  - "Destravar a lane vermelha do Ponto pra o PR de ponto.importacoes.criar sair"
---

# Handoff — permissões classe D e o idioma do `Gate::before`

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `tasks-list module:Governance` → 10 ativas; a US desta sessão (**US-GOV-059**, p2) está abaixo do corte do limite e segue `todo` — a classe D fechou, mas as 2 pendências de [W] a mantêm aberta.
- `decisions-search "permissões Spatie Gate::before drift"` → 4 hits, **nenhum é dono deste tema** (são ADRs ARQ-0002 per-módulo sobre granularidade de nome). **Nenhuma ADR nova criada** — a classe D é execução de US existente, e o achado do `Gate::before` é sobre código existente, não decisão nova. O registro canônico é o SPEC.
- Handoff anterior: [`2026-08-07-0846-jana-onda1-e-o-vermelho-de-15-runs`](2026-08-07-0846-jana-onda1-e-o-vermelho-de-15-runs.md). Irmãos desta US: [`2026-08-06-1303`](2026-08-06-1303-d0-trilha-d-invocador-e-permissoes-orfas.md) e [`2026-08-05-1438`](2026-08-05-1438-promocoes-required-e-hooks-observaveis.md).

## O que aconteceu

O pedido era triar a **classe D** (core UltimatePOS) das órfãs do `permission-drift` — 16 casos. O método herdado das classes anteriores mandava consultar a **base de produção antes de qualquer conclusão**, e ele se pagou de novo: 495 permissões na tabela, e das 16 **só `sale.history.view` existe** (concedida a `Admin#164` — Martinho, OficinaAuto LIVE). As outras 15 são órfãs de verdade.

O achado que mudou a forma do problema não estava em nenhuma das 16 isoladamente — estava no **`AuthServiceProvider`**. O `Gate::before` tem **duas pernas**, e o detector modela uma: ele isenta corretamente a *lista nomeada* (`backup`, `superadmin`, `manage_modules`), derivada do arquivo. Mas o `else` faz **qualquer** outra ability não-declarada responder *"sim para admin, não para o resto"*. Quatro nomes exploram isso **de propósito** — são o idioma "só admin" escrito com o vocabulário do `can()`, não permissão faltando.

O caso mais afiado é o `admin` no PDV. Os `disable_*` (`disable_pay_checkout`, `disable_draft`, `disable_discount`…) **são declarados**, e o `Gate::before` dá **todos** eles ao admin — logo `!Gate::check('disable_X')` é sempre `false` para ele, e sem o `|| can('admin')` o admin perderia os botões. Declarar `admin` viraria um checkbox concedível que **fura os `disable_*`** de qualquer papel. `only_admin` tem o nome como intenção; `edit_essentials_settings` gateia endpoint admin-only por desenho (`is_admin()` + abort em PT-BR), então menu e endpoint já concordam; e `subscribe` vive atrás do middleware `superadmin`, então declará-lo **fabricaria teatro**. Os quatro ficam como resíduo declarado — é código correto que o detector reporta por enxergar metade do mecanismo.

O resto se resolveu no padrão já conhecido do `kb.ai`: **4 nomes errados no menu**, cada um citando permissão inexistente enquanto o endpoint exigia outra que existe. E **3 declaradas** de fato, todas com rota viva e sem irmã que servisse.

Dois casos caem sob a **regra-mestre VALOR/ESTOQUE**, que manda apresentar o impacto e só aplicar após confirmação. **Nenhuma linha foi tocada neles.** O `edit_purchase_price` revelou mais do que o esperado: é flag de UI pura (prop Inertia que decide só o `readonly`) e **não há enforcement server-side** — o `update()` aceita o preço postado independentemente. O `report.stock_details` é o oposto do que o nome promete: gateia leitura **e** `adjustProductStock`, que chama `fixVariationStockMisMatch` — **escrita de estoque**. Reaproveitar `stock_report.view` seria leitura autorizando mutação; declarar como está deixa a armadilha do nome de pé.

[W] autorizou o merge; CI 108 verdes, deploy `success`, e o smoke real em prod confirmou as 3 permissões vivas nos grupos certos com label PT-BR renderizado.

## Artefatos gerados

| arquivo | delta |
|---|---|
| `memory/requisitos/Governance/SPEC.md` | +~110 — seção "Classe D triada" (registro canônico da triagem) |
| `app/Http/Middleware/AdminSidebarMenu.php` | 2 nomes de permissão + comentários |
| `resources/views/role/{create,edit}.blade.php` | +34 cada — 3 checkboxes novos |
| `lang/{pt,en}/role.php` | +4 cada — labels |
| `Modules/Essentials/…/sidebar_hrm.blade.php` · `Modules/Repair/…/nav.blade.php` | 1 nome cada |

## Persistência

- **git:** [#5384](https://github.com/wagnerra23/oimpresso.com/pull/5384) → `60d171d3cfb` em `main`. Deploy `success`; prod serve o código (conferido por `grep` no arquivo servido via SSH).
- **MCP:** US-GOV-059 segue `todo` **de propósito** — a classe D fechou, mas as 2 decisões de [W] a mantêm aberta. Não marquei `done`.
- **BRIEFING:** não tocado — a mudança é de dados de autorização, não de capacidade do módulo.

## Próximos passos pra retomar

```bash
node scripts/governance/permission-drift.mjs
```

Deve dar **17 órfãs**: 7 scaffolding classe C · 4 idioma `Gate::before` · 3 `visit.*` · 1 Ponto (lane vermelha) · **2 aguardando [W]**. Ou seja **15 com razão escrita para ficar**. A leitura completa está no SPEC do Governance, seção US-GOV-059.

## Lições catalogadas

- **Consultar o banco de produção primeiro não é formalidade** — é o que separa "órfã real" de "existe e ninguém viu". Decisivo pela 3ª classe seguida.
- **Detector que modela metade de um mecanismo produz achado que parece dívida e é código correto.** Ensinar a outra perna exigiria distinguir "nome que denota o próprio check de admin" — critério por nome, a família de guard sintático já morta 4× no §5. Ficou como resíduo com razão escrita, não como conserto.
- **Errei o Infra Contract:** citei `/business` (404) quando a rota do item de menu é `/business/settings` (302). O código estava certo; o contrato apontava pro lugar errado. Pego pelo próprio curl que o contrato mandava rodar — que é exatamente o valor de escrever os `curl` esperados em vez de afirmar que funciona.
- **`visit.*` falha ABERTO, ao contrário de todo o resto desta US.** Com `view_all`/`view_own` inexistentes, um não-admin resolveria `true && false` e o filtro de escopo **não** seria aplicado. Hoje é inerte (o módulo FieldForce não existe nesta árvore, o endpoint aborta antes) — mas quem ligar o módulo precisa declarar as duas **antes**, senão liga com vazamento intra-tenant.

## Pointers detalhados

- Triagem completa das 16 + tabelas de decisão + impacto medido dos 2 pendentes: `memory/requisitos/Governance/SPEC.md` §US-GOV-059 → "Classe D triada".
- Classes A/B/C (contexto de como o contador chegou em 24): mesma seção, subseções anteriores.
- Método e armadilhas de medição já pagas (truncagem em 25 na saída de texto; substring reprovando o legítimo): mesma seção, fim da classe C.
