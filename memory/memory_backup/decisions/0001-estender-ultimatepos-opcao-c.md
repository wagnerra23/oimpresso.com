# ADR 0001 — Estender UltimatePOS em vez de build próprio ou fork

**Status:** ✅ Aceita
**Data:** 2026-04-18
**Decisores:** Eliana (WR2), Claude (assistente)

## Contexto

A WR2 Sistemas precisa oferecer um módulo de ponto eletrônico em conformidade com a Portaria MTP 671/2021 para seus clientes. Três caminhos foram avaliados:

**A. Build from scratch (Laravel puro):** máximo controle, mas 100% do esforço é nosso — cadastros, auth, RBAC, multi-empresa, UI.

**B. Fork do UltimatePOS:** modificar o core para injetar o ponto. Quebra contrato de atualização, gera divergência perpétua com upstream.

**C. Estender UltimatePOS como módulo:** adicionar `Modules/PontoWr2/` que complementa o Essentials & HRM sem tocar no core.

O cliente da WR2 já roda UltimatePOS em produção. Existe uma base instalada. Reescrever cadastros de empresa, usuários, auth e multi-tenancy seria jogar trabalho fora. O Essentials já tem Attendance primitiva (útil como gancho) mas não atende legislação brasileira.

## Decisão

**Adotar Opção C — estender UltimatePOS criando `Modules/PontoWr2/` que depende do Essentials.**

O módulo:

- Não modifica tabelas core (`users`, `business`, `roles`, etc.)
- Usa a tabela `users` do UltimatePOS como fonte única de colaborador
- Conecta via tabela bridge `ponto_colaborador_config` (ver ADR 0004)
- Prefixa todas suas tabelas com `ponto_`
- Declara dependência de Essentials em `module.json`: `"requires": ["Essentials"]`

## Consequências

### Positivas

- Zero retrabalho em auth, multi-empresa, permissões, dashboards administrativos
- Updates do UltimatePOS continuam aplicáveis sem conflito
- Cliente continua em ambiente familiar
- Time-to-market muito menor que Opção A
- Não herda dívida de Opção B (fork)

### Negativas

- Limitados pelo stack do UltimatePOS (Laravel 10, PHP 8.1, MySQL, AdminLTE/jQuery)
- Se UltimatePOS mudar arquitetura (ex.: abandonar nWidart), temos que acompanhar
- UI em AdminLTE/jQuery é menos moderna que Vue/React

### Neutras

- Exigiu aprender a estrutura interna do Essentials
- Qualquer nova feature do Essentials pode potencialmente colidir com a nossa
