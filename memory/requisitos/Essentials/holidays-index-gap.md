---
id: requisitos-essentials-holidays-index-gap
tela: Essentials/Holidays/Index (/hrm/holiday)
prototipo: prototipo-ui/cowork/hrm-page.jsx
tela_viva: resources/js/Pages/Essentials/Holidays/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Essentials/Holidays/Index

> **Fase 1 = PARIDADE.** `hrm-page.jsx:2` declara: *"Espelha o topnav de nav_hrm.blade"* — **porte
> reverso**. Região: `Feriados` (`hrm-page.jsx:385-466`).
> Este gap executa a thread [`08-feriados-puxar.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/playbook/08-feriados-puxar.md)
> do playbook, cujo §Estado admite que em 04/09 Feriados foi tratada como 🔵 *"pela existência, não
> por paridade medida — o conteúdo **não foi lido**"*. **Aqui foi lido.**
> A régua de triagem é a §3 daquela thread, literal: *"o que o protótipo tem que a Page não tem →
> só vira pedido se for **comportamento** (ordenar, filtrar), nunca layout"*.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Filtro por localidade | **Paridade de capacidade.** Vivo `Holidays/Index.tsx:106-115` (partial reload `only: ['holidays','filtros']`) e limpar em `:119-121`; o payload traz `locations` (`:75`) e `filtros` (`:76`). Protótipo `:424-429`: `<select>` com *"Todas as localidades"* / *"Vale para o negócio inteiro"* / as filiais. | Nada — paridade. |
| Criar / editar / excluir feriado | **Paridade.** Vivo: `<Dialog>` (`:99`, `:126`, `:138`, fecha em `:149`), botão *"Novo feriado"* (`:183-184`) e `AlertDialog` de exclusão (`:23-30`), tudo sob `can_manage` (`:96`). Protótipo: `podeGerir` (`:403`) governando "Novo feriado" (`:432`), Editar e Excluir (`:452-453`). | Nada — paridade. |
| Ordenação por nome / início / dias | **Ausente — e é o achado desta tela.** Protótipo `:436-439`: cada `th` ordenável carrega um `<button class="mod-sort">` **dentro** do `th`, com indicador `↑/↓` (`:401`). Varredura contada no vivo: `sort` em `Holidays/Index.tsx` = **0 ocorrências**; os 5 `th` (`:250-254`) são estáticos. | **Decidir.** É **comportamento** (ordenar) → vira pedido pela régua §3. ⚠️ E o playbook nomeia o padrão certo: o botão dentro do `th` é *"o padrão a11y **certo** — o `DataTablePro` do DS não faz isso, RESÍDUO 4"*. Ou seja, implementar aqui à mão diverge do DS; usar o DS perde a a11y. A decisão de [W] é **onde** pagar: nesta tela ou no primitivo. |
| Colunas da tabela | **Diverge: 5 no vivo × 7 no protótipo.** Vivo `:250-254`: Nome · **Período** · Localidade · Nota · Ações. Protótipo `:436-440`: Feriado · **Início** · **Fim** · **Dias** · Localidade · Observação · (ações). O vivo **funde** Início+Fim em "Período" e **não exibe** a contagem de Dias; `Feriado`→`Nome` e `Observação`→`Nota` são renomeações. | **Decidir.** Fusão de colunas é **layout** → pela régua §3, **não** vira pedido sozinha. Mas a coluna `Dias` é o que a ordenação por duração usa (`:397`, `H.dias(a.ini,a.fim)`), então as duas decisões andam juntas. |
| KPIs do topo | **Ausente.** Protótipo `:418-422`: 3 KPIs — *Feriados no ano* (com "N dias no filtro") · *Só de uma localidade* · *Maior parada*. Varredura contada no vivo: os termos `KpiCard` e `KpiGrid` em `Holidays/Index.tsx` = **0 de cada**. | Nada — **layout**, e a régua §3 é explícita: layout não vira pedido. Registrado para não virar "bug" na próxima leitura. |
| Aviso ao excluir | **Diverge, medido — e a diferença é de domínio.** Vivo `Holidays/Index.tsx:386-389`: *"Remover feriado?"* + *"«nome» será apagado permanentemente."* — consequência **genérica**. Protótipo `hrm-page.jsx:411`: *"Excluir «nome»? O feriado sai da escala de todos os turnos — marcações já lançadas não mudam."* — consequência **de negócio**, que é o que o operador precisa saber antes de confirmar. | **Decidir.** Não é preciosismo de copy: o aviso do protótipo responde *"o que isso quebra?"* (escala de turnos) e *"o que isso NÃO quebra?"* (marcações já lançadas — a garantia append-only da Portaria 671). O vivo não diz nem um nem outro. Copy de ação destrutiva é soberania de [W]. |
