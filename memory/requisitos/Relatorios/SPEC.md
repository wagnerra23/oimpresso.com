---
id: requisitos-relatorios-spec
module: Relatorios
status: ativo
version: "0.1.0"
owner: wagner
last_updated: "2026-10-27"
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

**Implementado em:** _pendente_ — fatia servidor (executar, salvar e reabrir em JSON, atrás de `RELATORIO_MONTAVEL`: `app/Services/Relatorios/Montavel/`, `RelatorioMontavelController`; teste em `tests/Feature/Relatorios/RelatorioMontavelContratoTest.php`); falta a tela (protótipo `prototipo-ui/cowork/Wagner/relatorio-montavel-page.jsx` aguarda o OK de design do [W]) e a agenda por e-mail

Origem: levantamento de lacunas 2026-10-25 (P2 Plataforma — relatório montável, salvo e agendado).
- O usuário escolhe a fonte (vendas, compras, estoque, contas), as colunas, os filtros e o agrupamento, vê o resultado e salva com um nome.
- O relatório salvo é da empresa (`business_id`) e respeita as permissões de quem abre: coluna de custo só aparece para quem vê custo.
- Agenda opcional (diária, semanal, mensal) que manda o arquivo por e-mail para usuários da própria empresa; o job leva `business_id` no construtor.
- Os números batem com o relatório pronto equivalente no mesmo período (prova por dois caminhos — regra mestre de valor).
- Teste 98 × 99: relatório salvo da 98 não abre nem roda na 99.
- Decisão [W] 2026-10-27: fontes da primeira versão = vendas, títulos, estoque e OS. Cada fonte exige a permissão do relatório pronto da mesma área.
