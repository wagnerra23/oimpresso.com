---
slug: 0360-deprecacao-admin-center-supersede-0122
number: 360
title: "Depreciação do Admin Center (Modules/Admin) — o painel nunca foi alcançável e não exportava nada"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-29"
accepted_via: "Pedido explícito de [W] nesta sessão ('eu quero remover ele, e realocar se tiver algo importante'), com as duas decisões de conteúdo respondidas por ele. O merge deste PR é o ato formal de ratificação (R10)."
module: infra
quarter: 2026-Q3
tags: [governanca, admin, deprecacao, ct100, tailscale, curador, feature-flags]
supersedes:
  - 0122-admin-center-ct100
superseded_by: []
related:
  - 0122-admin-center-ct100
  - 0062-separacao-runtime-hostinger-ct100
  - 0123-modules-arquivos-backbone
  - 0124-curador-conhecimento-pipeline
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

## Contexto

A [ADR 0122](0122-admin-center-ct100.md) criou o **Admin Center**: painel Wagner-only em `admin.oimpresso.com`, no CT 100, atrás de Tailscale, agregando brief + health + cycles + ADRs Tier 0 + Curador + feature flags + Screen Review. O desenho de segurança era bom — gate por IP antes do auth, audit append-only com PII redigida, double-confirmation nas mutations.

Ele nunca entrou em operação. Medido em 2026-07-29:

| Evidência | Medição | Fonte |
|---|---|---|
| Acesso | Todo `/admin/*` responde **403** fora da CIDR `100.99.0.0/16` | `TailscaleOnly::handle` |
| Pré-requisitos de infra | `US-ADM-002` (DNS + container CT 100) e `US-ADM-010` (smoke) nunca fecharam | `Modules/Admin/SCOPE.md` |
| Mutations executadas em produção | `SELECT COUNT(*) FROM mcp_admin_audit_log` = **0** | prod Hostinger |
| Screen Review | `git ls-files '*.review.md'` = **0** no repo inteiro | git |
| Comando de catálogo | ausente de `php artisan list` — nunca registrado no ServiceProvider; e estourava `ErrorException` na primeira tela sem `.review.md`, que são 100% delas | CT 100 + CI |
| Dependência de código externa | **0** referências a `Modules\Admin\` fora do módulo | varredura por FQCN, 87 arquivos |

O `UI-CATALOG.md` do próprio Admin é seed manual de 2026-05-17 com 4 telas (o módulo tem 8) — coerente com um gerador que nunca conseguiu rodar.

Ou seja: o módulo custava manutenção (19 arquivos de teste, 8 charters, 8 baselines de governança rastreando telas) sem entregar operação. E o que ele "entregava" de fato era **hospedar** duas coisas que pertencem a outros donos.

## Decisão

**Deprecar e remover `Modules/Admin`**, resgatando antes o que tem dono legítimo.

### Resgatado (feito antes de qualquer deleção)

| Peça | Novo lar | Por quê | PR |
|---|---|---|---|
| `ScreenCatalogGenerateCommand` → `UiCatalogGenerateCommand` | `Modules/Governance/Console/Commands/` | gera os 30 `UI-CATALOG.md` do repo; é governança, não administração. Registrado no Artisan e com o bug do `.review.md` corrigido — passou a funcionar pela primeira vez | [#5045](https://github.com/wagnerra23/oimpresso.com/pull/5045) |
| `CuradorStatsReader` | `Modules/Arquivos/Services/Curador/` | lê **só** tabelas do Arquivos (`arquivos`, `arquivos_audit_log`, `arquivos_dedupe`) e sustenta a `US-ARQ-018` **daquele** módulo. Estava no Admin porque o único consumidor era o widget W5 | [#5046](https://github.com/wagnerra23/oimpresso.com/pull/5046) |

O `CuradorStatsReader` ganhou teste que morde o Tier 0 (recorte por `business_id`, [ADR 0093](0093-multi-tenant-isolation-tier-0.md)) rodando na lane MySQL real — o caso herdado só fazia `toHaveKey` e passava com o filtro de tenant deletado.

### Não resgatado, com a perda declarada

- **Widget W5 Curador + `IndexController@__invoke`** — a UI da `US-ARQ-018` morre. O leitor sobrevive; falta dar a ele uma superfície própria. Não é perda de uso: a tela nunca foi alcançável (403).
- **Painel `/admin/feature-flags`** (2 telas) — **zero perda de capacidade**. O motor vive fora do módulo: `app/Services/GrowthBookAdminService.php`, `app/Models/FeatureFlagAudit.php`, a migration em `database/migrations/`, 5 comandos `app/Console/Commands/FeatureFlag/` e 5 tools MCP `Modules/Jana/Mcp/Tools/Flag*Tool.php`. Decisão de [W]: usar MCP/artisan, que já auditam na mesma tabela.
- **`mcp_admin_audit_log`** — a migration sai do repo, mas **a tabela NÃO é dropada em produção**. Ela está vazia, é inofensiva, e a regra Tier 0 "num ERP não se apaga" favorece deixá-la. Limpeza de schema é decisão separada.
- **`ExportAuditCommand`** — exportador da tabela acima. Sem escritor e sem dado, não é capacidade.
- **`IsWagner` / `TailscaleOnly`** — 0 consumidores. Decisão de [W]: morrem com o módulo; o git preserva. O `IsWagner` tinha `user_id=1` hardcoded, frágil de reaproveitar.
- **Screen Review** (tri-pane PDCA) — nunca produziu registro. A porta `/admin/screen-review` sai de [how-trabalhar.md](../how-trabalhar.md) como "UI humana por tela"; o mapa por tela continua nas portas vivas (`screen-coverage:report`, `casos:report`).

### Reconciliação de US de outros módulos

- **`US-ARQ-013`** (`Arquivos`) apontava para `Modules/Admin/Pages/Arquivos/Index.tsx`. Reescrita para **`resources/js/Pages/Arquivos/Index.tsx`** — o padrão canônico do projeto (as telas vivem em `resources/js/Pages/<Mod>/`, não dentro de `Modules/`). Decisão de [W]: *"pode ser dentro do arquivo mesmo"*.
- **`US-ARQ-017`** falava em *"submit pro Admin API"*. Reescrita para o endpoint do próprio Arquivos; junto, a rota da `US-ARQ-011` perde o prefixo `/admin/` e passa a `POST /arquivos/api/upload-batch`.
- **`US-INFRA-008`** perde 2 dos 5 caminhos de implementação (o `FeatureFlagsController` e seu teste). A entrega continua de pé, menor: de **3 canais** (painel + MCP + artisan) para **2** (MCP + artisan). Decisão de [W]: *"pode passar para 2 sem problema"*.

## Consequências

**Melhora**
- Some um módulo que era 100% custo: 8 controllers, 12 services, 7 FormRequests (4 deles sem endpoint algum), 8 telas + 25 componentes, 19 arquivos de teste.
- Dois artefatos passam a viver no dono certo — e um deles **passou a funcionar** no processo.
- O `Modules/Governance/SCOPE.md` deixa de declarar uma fronteira com um módulo inexistente.

**Piora / dívida assumida**
- A `US-ARQ-018` fica sem UI. Registrado no SPEC do Arquivos, não escondido.
- Não há mais painel visual agregando brief/health/cycles/infra. Os dados seguem acessíveis por MCP, artisan e pelos scripts de governança — perde-se a visão única, que nunca esteve no ar.
- 8 baselines e scorecards de governança que rastreavam as telas do Admin precisam ser regenerados na remoção.

**Neutro, e vale registrar**
- A infra pedida pela 0122 (DNS Tailscale, container CT 100, decisão de TLS) deixa de ser pré-requisito de nada. Se um painel administrativo voltar a ser desejado, a decisão de *onde* ele mora nasce limpa — sem herdar o Tailscale-only, que foi justamente o que o tornou inalcançável na prática.

## Gate de reversão

Se um painel administrativo agregado voltar a ser necessário, **não** reabrir a 0122: escrever ADR nova. O que esta decisão ensina e deve entrar lá:

1. **Acessibilidade antes de feature.** O Admin Center ganhou 8 telas, 3 mutations e um dashboard v4 antes de resolver DNS/TLS. Sem caminho de acesso, tudo virou código morto com CI verde.
2. **Painel que hospeda leitor de outro módulo cria acoplamento invisível.** O `CuradorStatsReader` só apareceu como risco porque uma `US` de terceiro estava ancorada nele — não porque alguém importava a classe.
3. **Registro no Artisan não é opcional.** Comando de módulo não registrado no ServiceProvider é indistinguível de comando inexistente, e nenhum teste de `class_exists()` pega isso.

## Refs

- [ADR 0122](0122-admin-center-ct100.md) — superseded por esta
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100 (por que o painel era Tailscale-only)
- [ADR 0123](0123-modules-arquivos-backbone.md) · [ADR 0124](0124-curador-conhecimento-pipeline.md) — dono real do Curador
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — o Tier 0 que o teste do reader agora prova
- [`memory/requisitos/Admin/SPEC.md`](../requisitos/Admin/SPEC.md) — US-ADM-001..020, histórico
