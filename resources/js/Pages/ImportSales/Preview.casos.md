---
casos: Prévia da importação de vendas · POST /import-sales/preview
irmaos: Preview.charter.md (lei) · Preview.tsx · Index.casos.md
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a prévia é onde o operador confere a planilha antes de gravar venda e baixar estoque — o que ela mostra tem de ser a planilha, e o mapeamento tem de chegar ao import no formato que ele lê.
owner: wagner
last_run: "2026-10-02"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Prévia da importação de vendas

> **Fonte:** texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Importacao.casos.md`
> + `ImportSalesController@preview` real. Prefixo `UC-IMPV-*` (ver `Index.casos.md`).
>
> **Teste:** `tests/Feature/Sells/ImportSalesContratoTest.php` · ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)`.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-IMPV-06 · As colunas chegam pré-mapeadas `[must]`
- **Persona:** Larissa — a planilha usa os mesmos nomes de coluna do modelo.
- **Aceite:** Dado uma coluna com o mesmo rótulo de um campo · Quando a prévia abre · Então ela vem mapeada para esse campo; coluna sem semelhança de 50% vem como "Ignorar".
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-06 · a prévia pré-mapeia por semelhança de rótulo (R2)`.
- **Regressão que defende:** operador remapeando tudo à mão a cada importação.
- **Status: 🧪**

## UC-IMPV-09 · A prévia mostra a planilha e quantas vendas vão nascer `[must]`
- **Persona:** Larissa — confere antes de gravar.
- **Aceite:** Dado uma planilha com 3 linhas e 2 faturas · Quando envio para a prévia · Então vejo as 3 linhas com o cabeçalho real, e escolher a coluna da fatura em "Agrupar por" informa 2 vendas (UC-IMP-03 do texto revisado).
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-09 · a prévia mostra as linhas da planilha e quantas vendas cada coluna geraria`.
- **Regressão que defende:** prévia que não reflete o arquivo; contagem de vendas feita só sobre as linhas exibidas.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Mesmo campo em duas colunas bloqueia o envio (UC-IMP-02 do texto revisado) — regra só no front (`faltas` do `Preview.tsx`), sem teste de navegador.

## Trilha do tempo
- 2026-10-02 · [CL] trio criado na thread 05 do playbook `venda-menu`. Refs: ADR 0104 · ADR 0264 G-1/G-2 · ADR 0358.
