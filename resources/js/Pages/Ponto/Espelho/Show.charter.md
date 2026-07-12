---
page: /ponto/espelho/{colaborador}
component: resources/js/Pages/Ponto/Espelho/Show.tsx
related_prototype: n/a (tela de detalhe bespoke — espelho mensal com heatmap + totalizadores + tabela dia-a-dia; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-007, US-PONT-008]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/espelho/{colaborador} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/EspelhoController@show` (rota `ponto.espelho.show`, middleware `ponto.access`). Renderiza o espelho de ponto mensal de um colaborador a partir de `ApuracaoDia` + `Marcacao`.

---

## Mission
Espelho de ponto mensal de um colaborador: mostra os totalizadores do mês (trabalhado, atraso, falta, HE diurna/noturna, saldo de banco de horas), um heatmap do mês, e a tabela dia-a-dia com as marcações e a apuração de cada dia. É a visão que o RH confere e imprime pra fechamento de folha e evidência legal.

---

## Goals — Features (faz)
- Cabeçalho do colaborador (matrícula, escala) carregado eager pra validar o tenant.
- 6 totalizadores mensais somados de `ApuracaoDia` (trabalhado/atraso/falta/HE diurna/HE noturna/BH +/-).
- Alerta de divergências quando há dias com `tem_divergencia`.
- Heatmap mensal (`MonthHeatmap`) com clique que rola até o dia na tabela.
- Tabela dia-a-dia (todos os dias do mês) com marcações (badge por tipo/origem), trabalhado, atraso, falta e HE.
- Navegação de mês (anterior/próximo + seletor) via partial reload de `totais`/`linhas`.
- Botão "Imprimir PDF" abre `/ponto/espelho/{id}/imprimir?mes=...` em nova aba (stream inline).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita nem cria marcações — são append-only (Portaria MTP 671/2021); marcações anuladas (`ORIGEM_ANULACAO`) são filtradas da tabela.
- ❌ Não recalcula a apuração aqui — só lê `ApuracaoDia` já materializada.
- ❌ Não expõe colaborador de outro tenant (o `findOrFail` é scopado por `business_id`). *(inferência pendente de Wagner)*
- ❌ Não aprova/rejeita intercorrências (isso vive em Aprovações/Intercorrências).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- `Inertia::defer` nas props `totais` e `linhas` (8 sums + construção de até 31 linhas viram closures lazy).
- Troca de mês faz partial reload só de `mes`/`totais`/`linhas` (cabeçalho do colaborador não muda com o mês).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling — os dados só re-buscam na troca explícita de mês.
- ❌ Nenhuma mutação em GET — tela read-only; o PDF é `stream` inline, sem escrita.
- ❌ Não notifica colaborador nem RH ao abrir/imprimir.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) incluindo heatmap + tabela dia-a-dia
- [ ] Confirmar comportamento do PDF impresso vs tela (paridade de números)
