---
id: resources-js-pages-repair-job-sheet-index-casos
casos: Lista de OS · /repair/job-sheet
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — "a lista vem do mesmo endpoint do Blade" e "OS de outro negócio não aparece" valem em qualquer troca do motor da tabela
owner: wagner
autor: "[CL] 2026-09-30"
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Lista de OS

> Derivados do [Index.charter.md](Index.charter.md), da
> [US-REPA-004](../../../../../memory/requisitos/Repair/SPEC.md)
> (bloco *"Contrato que a tela não pode quebrar"*) e do
> [RUNBOOK-jobsheet-index.md](../../../../../memory/requisitos/Repair/RUNBOOK-jobsheet-index.md),
> mais o contrato real de `JobSheetController::index` — **não** do `.tsx`
> (teste derivado da implementação é tautológico, [§5 2026-06-05](../../../../../memory/proibicoes.md)).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> Defendidos por [`RepairJobSheetIndexContratoTest`](../../../../../Modules/Repair/Tests/Feature/RepairJobSheetIndexContratoTest.php),
> lane `PHP / Pest (Verticais · MySQL)`, tenant 98.
>
> ⚠️ **O teste nasceu antes destes casos** (2026-09-05, #6879) e já nomeava os UCs. Este arquivo
> fecha o trio: os UCs abaixo são os mesmos ids, agora com o contrato escrito. Nenhum teste foi
> reescrito para caber aqui.
>
> ⚠️ **Metade dos UCs não é medida hoje, e isto fica dito.** No veredito do manifesto
> (`scripts/casos-test-results.json`, gerado 2026-09-29) e no run
> [36571337250](https://github.com/wagnerra23/oimpresso.com/actions/runs/36571337250) da lane, os
> UCs 03, 04 e 06 saem **`skipped`** — o teste pula por ambiente em vez de assertar cego, e um
> `skipped` é ausência de medição, nunca aprovação (LC-13). O JUnit não carrega o motivo do skip;
> a causa **não foi medida** e fica como pendência, não como fato.

---

## UC-JSIDX-01 · Sem permissão de OS, a lista não existe
- **Persona:** usuário do negócio que não trabalha com reparo.
- **Aceite:** Dado usuário sem `superadmin` e sem nenhuma permissão de OS · Quando abro `/repair/job-sheet` · Então **403**, antes de qualquer render.
- **Por que é assim:** o gate do `index()` nega na entrada. O charter não declara o gate — o UC descreve o que o **controller** garante hoje, dito na cara em vez de fingir uma âncora documental.
- **Teste:** `RepairJobSheetIndexContratoTest` — *"UC-JSIDX-01: usuário sem superadmin nem permissão de job_sheet recebe 403"*.
- **Status: ✅** _pass no manifesto (2026-09-29)_

## UC-JSIDX-02 · Flag desligada preserva o Blade legado
- **Persona:** operador de um negócio que ainda não migrou para a tela nova.
- **Aceite:** Dado a flag MWART `repair_job_sheet_index` desligada · Quando abro a lista · Então recebo o Blade legado, sem header `X-Inertia`.
- **Por que é assim:** coexistência opt-in da migração MWART ([ADR 0104](../../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)); o charter registra o status `live` dentro dessa coexistência.
- **Teste:** `RepairJobSheetIndexContratoTest` — *"UC-JSIDX-02: flag MWART OFF entrega Blade, sem header X-Inertia"*.
- **Status: ✅** _pass no manifesto (2026-09-29)_

## UC-JSIDX-03 · Flag ligada entrega a tela com o que ela precisa
- **Persona:** operador de negócio já migrado.
- **Aceite:** Dado a flag ligada · Quando abro a lista · Então recebo o componente `Repair/JobSheet/Index` com `filters`, `flags` e `datatable_url`.
- **Por que é assim:** o charter manda a tela respeitar as 3 flags vindas do Controller (`is_user_service_staff`, `show_serial_no`, `enable_brand_in_job_sheet`) e buscar a lista em `datatable_url`.
- **Teste:** `RepairJobSheetIndexContratoTest` — *"UC-JSIDX-03: flag MWART ON entrega Inertia Repair/JobSheet/Index com filters, flags e datatable_url"*.
- **Status: ⬜** _`skipped` no manifesto e no run 36571337250 — não medido_

## UC-JSIDX-04 · A tela busca no endpoint COMPARTILHADO com o Blade
- **Persona:** ninguém — é armadilha de migração.
- **Aceite:** Dado a flag ligada · Quando a tela é montada · Então `datatable_url` aponta para `route('job-sheet.index')`, o mesmo endpoint que serve o Blade.
- **Por que é assim:** o Automation Hook do charter — *"A lista vem do MESMO endpoint que serve o Blade legado"* — e o contrato da US-REPA-004.
- **Regressão que defende:** apontar a tela para um endpoint exclusivo abre o caminho para alguém "limpar" o ramo `ajax` achando que só a tela o usa.
- **Teste:** `RepairJobSheetIndexContratoTest` — *"UC-JSIDX-04: datatable_url aponta para o endpoint que também serve o Blade"*.
- **Status: ⬜** _`skipped` no manifesto e no run 36571337250 — não medido_

## UC-JSIDX-05 · O ramo `ajax` continua vivo com a flag ligada
- **Persona:** operador de qualquer negócio sem a flag — que hoje é a maioria.
- **Aceite:** Dado a flag ligada · Quando chega uma chamada `ajax` · Então a resposta ainda é o envelope DataTables, com a chave `data`.
- **Por que é assim:** o `index()` é triple-mode (DataTables JSON · Inertia · Blade). A US-REPA-004 diz: trocar o motor de dados desta tela **não pode** alterar o ramo `ajax`.
- **Regressão que defende:** se o envelope quebrar, o Blade legado de todo tenant sem a flag para de listar.
- **Teste:** `RepairJobSheetIndexContratoTest` — *"UC-JSIDX-05: com a flag ON, uma chamada ajax ainda devolve o envelope DataTables"*.
- **Status: ✅** _pass no manifesto (2026-09-29)_

## UC-JSIDX-06 · OS de outro negócio não aparece na lista (Tier 0)
- **Persona:** ninguém — a falha seria silenciosa e cruzaria a fronteira de tenant.
- **Aceite:** Dado uma OS de outro `business_id` · Quando a lista é carregada · Então ela **não** aparece no payload.
- **Por que é assim:** anti-hook literal do charter — *"Não acessa OS de outro `business_id`"* — [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0 irrevogável.
- **Teste:** `RepairJobSheetIndexContratoTest` — *"UC-JSIDX-06: a listagem ajax não devolve OS de outro business"*.
- **Status: ⬜** _`skipped` no manifesto e no run 36571337250 — não medido. Sendo Tier 0, é a pendência mais séria deste arquivo_

---

## Ainda sem teste (prosa, sem id)

- [BACKLOG] Filtrar por local, status e cliente devolve só as OS daquele recorte — Goal do charter e item do DoD da US-REPA-004; nenhum teste exercita os filtros hoje.
- [BACKLOG] A tela não escreve no banco ao listar — Automation Anti-hook do charter (*"Não escreve no banco"*, *"Não muda status de OS"*); sem teste.
- [BACKLOG] A tela não renderiza as colunas HTML do payload (`action`, `status`, `estimated_cost`) — UX Target do charter (R-OWASP); sem teste.

## Rastreabilidade

| UC | Defendido por | Eixo |
|---|---|---|
| 01 | `RepairJobSheetIndexContratoTest` | permissão |
| 02, 03 | `RepairJobSheetIndexContratoTest` | coexistência MWART (ADR 0104) |
| 04, 05 | `RepairJobSheetIndexContratoTest` | endpoint compartilhado — contrato da US-REPA-004 |
| 06 | `RepairJobSheetIndexContratoTest` | Tier 0 — isolamento (ADR 0093) |
