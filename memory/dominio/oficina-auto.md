---
dominio: OficinaAuto — oficina mecânica de reparo (cliente piloto Martinho Caçambas, biz=1)
fonte_unica: este arquivo é a fonte canônica do vocabulário de domínio do módulo (ADR 0264 G-4)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum de migration ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0265-oficina-reparo-erradica-locacao, 0143-fsm-pipeline-live-prod-marco-2026-05-12, 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada]
---

# Dicionário de domínio — OficinaAuto

> **Fonte única do vocabulário de domínio** da Oficina (ADR 0264 G-4). O guard `dominio:check`
> compara este dicionário com os `enum(...)`/`ENUM(...)` reais das migrations do módulo. Divergência
> = débito (ratchet). **Editar aqui é uma decisão de domínio** — não se acrescenta valor de enum sem
> registrar a semântica.

## Princípio canônico — reparo é o único domínio (ADR 0265)

A Oficina é **reparo/mecânica, ponto.** **Não existe fluxo de locação.** "Caçambas" é só o **nome
comercial** do cliente Martinho (razão social), nunca tipo de ordem, coluna de kanban, KPI ou label.
Reintroduzir `locacao`/`locada`/`disponivel` como **conceito de negócio** viola a ADR 0265 e este
dicionário (ver `memory/proibicoes.md`).

## FSM da Ordem de Serviço (ADR 0143 — não é enum de coluna, vive em `service_order_stages`)

Estados canônicos: `orcamento → aprovada → em_servico → concluida` (+ `cancelada`).
Apresentação na tela (kanban de reparo): `recepcao → diagnostico → pecas → execucao → pronto`.

## Regras de negócio (split fiscal — base da Venda×Oficina)

- Item `tipo = peca` tem `product_id` → toca **estoque core** (UltimatePOS). Vira **NF-e**.
- Item `tipo = mao_obra` ou `servico_terceiro` → **não toca estoque**. Mão-de-obra vira **NFS-e**.

## Enums canônicos (machine-checked por `dominio:check`)

> Chave = `tabela.coluna`. O guard deriva o estado ATUAL de cada enum pela migration mais recente
> (last-write-wins, up() only) e compara com a lista canônica abaixo.

```json
{
  "module": "OficinaAuto",
  "enums": {
    "service_orders.order_type": ["manutencao", "mecanica"],
    "oficina_service_order_items.tipo": ["peca", "mao_obra", "servico_terceiro"],
    "vehicles.vehicle_type": ["caminhao", "cavalo", "semi_reboque", "cacamba_estacionaria", "cacamba_avulsa", "cacamba_caminhao", "recapagem", "automovel", "motocicleta", "outros", "outro"],
    "vehicles.current_status": ["disponivel", "locada", "manutencao", "indisponivel"],
    "oa_inspection_items.categoria": ["motor", "freios", "correia", "bateria", "pneus", "suspensao", "direcao", "eletrica", "fluidos", "outro"],
    "oa_inspection_items.severity": ["ok", "atencao", "critico"],
    "oa_inspection_items.client_decision": ["pending", "approved", "rejected"]
  },
  "forbidden_ui_terms": ["locacao", "cacamba"],
  "forbidden_ui_paths": [
    "resources/js/Pages/OficinaAuto",
    "Modules/OficinaAuto/Database/Seeders",
    "Modules/OficinaAuto/Database/Migrations"
  ]
}
```

### Por que `order_type` aqui é `{manutencao, mecanica}` (e o guard acusa `locacao` como débito)

Decisão de Wagner (ADR 0265): erradicar `locacao`. A migration atual ainda carrega o enum
`{locacao, manutencao, mecanica}` → o `dominio:check` reporta `locacao` como **valor não-declarado**
(débito absorvido no baseline F1). O **PR de erradicação (P0)** baixa o enum pra `{manutencao, mecanica}`
e o débito **zera sozinho** — é a prova viva de que o gate pega a alucinação que nenhuma spec de tela
pegava.

### Resíduo de locação ainda NÃO erradicado (fora do P0 `order_type` — decisão de Wagner pendente)

Estes valores existem no schema e estão **declarados acima pra o guard ficar verde**, mas são
**vocabulário vestigial de locação** — candidatos a erradicação futura (não decididos no P0 da ADR 0265,
que é `order_type`-only). Marcados aqui pra ficarem **visíveis**, não escondidos:

- `vehicles.current_status` → **`locada`** (estado de "alugada"; numa oficina de reparo não há aluguel).
- `vehicles.vehicle_type` → **`cacamba_estacionaria`, `cacamba_avulsa`, `cacamba_caminhao`, `recapagem`**
  (equipamento de locação/serviço herdado do enquadramento legado caçamba).

> Erradicar estes exige migration Tier 0 em `vehicles` + ajuste de `Vehicle`/`VehicleQueryService` —
> escopo que Wagner ainda não autorizou. Quando autorizar, remover daqui e do schema juntos.

## Cobertura de CÓDIGO (Salto #3 — domínio além do enum)

O `dominio:check` não para no enum: nos módulos com este dicionário ele também varre o **código de
aplicação** (exclui `Database/` e `Tests/`) e exige que todo valor-literal usado **contra uma coluna
governada** seja canônico. Padrões cobertos: `->where('col','v')` / `->where('col','=','v')` /
`->whereIn('col', ['v',…])`, comparação de campo `$x->col === 'v'` (e `!== / == / != / <>`), e regra
de validação Laravel `'col' => '…|in:v1,v2'`. Só literais entre aspas — `self::CONST`/variável/operador
(`like`) não contam (anti-falso-positivo). Chave: `dominio:undeclared-code-value:<mod>:<col>:<valor>`.

**Por que existe:** erradicar o *enum* não basta — a alucinação sobrevive como **código morto** que
ramifica num valor que não existe mais (ex.: `if ($so->order_type === 'locacao')` depois do enum já ser
`{manutencao, mecanica}`). É o que o enum-check não pegava.

**Débito atual fotografado (baseline F1):** `dominio:undeclared-code-value:OficinaAuto:order_type:locacao`
— 11 ocorrências de código morto pós-erradicação (`ServiceOrder`, `Vehicle`, `ServiceOrderController`,
`AprovacaoOsController`, `ServiceOrderObserver`). Ratchet zera quando o **follow-up de limpeza de
dead-code** (documentado no `RUNBOOK-erradicacao-locacao.md`) remover essas ramificações. Valores de
`current_status.locada` no código **não** flagam (ainda declarados como resíduo vestigial acima).

## Termos PROIBIDOS user-facing (Salto #4 — trava de regressão ADR 0265)

O `dominio:check` também varre os paths declarados em `forbidden_ui_paths` (Pages do módulo +
Seeders/Migrations, onde nascem labels) e flaga **toda ocorrência** de termo em `forbidden_ui_terms`
— matching **case-insensitive e accent-insensitive** ("Locação" ≡ "locacao", "Caçamba" ≡ "cacamba").
Comentários (`//`, `/* */`, `#`) são cegados antes do scan (explicar a erradicação em comentário é
legítimo; mostrar pro usuário não). Chave: `dominio:forbidden-ui-term:<mod>:<file>:<termo>:<i>`
(uma por ocorrência — **ratchet por contagem**: ocorrência NOVA num arquivo já-baselined também
estoura o CI).

**Débito fotografado no baseline:** residuais Tier 0 (keys FSM `cacamba_locacao`/`cacamba_manutencao`
em migrations/seeder — intocáveis por ADR 0143/0194) + identificadores legados do kanban
(`CacambaCard`, `dias_locacao` em ProducaoOficina — dívida F3 em ADR própria, charter v4 PR #2417).
A trava garante que [W] nunca mais descubra "locação" nova na tela: QUALQUER string/label/identificador
novo com os termos = CI vermelho no `dominio-gate`.

## Trilha do tempo

- 2026-06-09 · [CL] semeou o dicionário (ADR 0264 G-4) já com `order_type` canônico pós-erradicação
  (ADR 0265). Resíduo `current_status.locada` + `vehicle_type.cacamba_*` flagado como débito visível.
- 2026-06-09 · [CL] **Salto #3** — `dominio:check` ganhou cobertura de código (`undeclared-code-value`).
  Pegou 11 ramificações de `order_type === 'locacao'` em código vivo (dead-code) que o enum-check não via
  → absorvidas no baseline como débito caçável (ratchet).
