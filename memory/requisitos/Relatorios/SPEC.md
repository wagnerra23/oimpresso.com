---
id: requisitos-relatorios-spec
module: Relatorios
status: ativo
version: "0.1.0"
owner: wagner
last_updated: "2026-10-26"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
---

# SPEC — Relatórios (`/reports/*`)

> Os relatórios gerais do núcleo (`App\Http\Controllers\ReportController`). A migração de cada
> relatório para Inertia segue os `RUNBOOK-*.md` desta pasta; este SPEC registra o que é **novo**.

## User Stories

### US-REL-001 · Relatório montável pelo usuário, salvo e enviado por agenda

> owner: — · priority: p2 · estimate: 24h · status: todo · type: epic
> blocked_by: —

**Implementado em:** _pendente_ — planejada (lacunas 2026-10-25); hoje só existem os relatórios prontos do `ReportController`

Origem: levantamento de lacunas 2026-10-25 (P2 Plataforma — relatório montável, salvo e agendado).
- O usuário escolhe a fonte (vendas, compras, estoque, contas), as colunas, os filtros e o agrupamento, vê o resultado e salva com um nome.
- O relatório salvo é da empresa (`business_id`) e respeita as permissões de quem abre: coluna de custo só aparece para quem vê custo.
- Agenda opcional (diária, semanal, mensal) que manda o arquivo por e-mail para usuários da própria empresa; o job leva `business_id` no construtor.
- Os números batem com o relatório pronto equivalente no mesmo período (prova por dois caminhos — regra mestre de valor).
- Teste 98 × 99: relatório salvo da 98 não abre nem roda na 99.
- Antes do código: [W] escolhe as fontes da primeira versão.

### US-REL-002 · Os relatórios prontos do núcleo abrem em React pelo menu

> owner: wagner · priority: p2 · estimate: 16h · status: doing · type: story
> blocked_by: —

**Implementado em:** _pendente_ — em andamento: cutover pelo menu de Despesas, Grupos de clientes e Mesas (2026-10-27, telas em resources/js/Pages/Relatorios/); as demais telas de Relatórios ainda abrem a Blade

Origem: migração Blade → React do núcleo (ADR 0104), regra de cutover D1 [W] 2026-10-07 (*por tela, React vira padrão, Blade só com `?classico=1`*).
- Cada relatório pronto do `ReportController` que já tem tela em `Relatorios/` abre a tela React pelo menu; a Blade fica em `?classico=1` até o canário, e depois sai.
- Antes do cutover, a tela cobre o que a Blade faz (o §4 de cada `RUNBOOK-*.md` lista o que falta).
- Os números da tela são os da Blade, provados por dois caminhos no teste de cada tela (regra mestre de valor).
- Teste 98 × 99 em cada tela: nada do outro negócio aparece.
