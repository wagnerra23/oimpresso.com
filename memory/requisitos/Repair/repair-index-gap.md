---
id: requisitos-repair-repair-index-gap
tela: Repair/Index (/repair/repair)
prototipo: prototipo-ui/cowork/repair-page.jsx
tela_viva: resources/js/Pages/Repair/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Repair/Index

> Fase 1 do protocolo (`prototipo-ui/PROTOCOL.md`) em modo **PARIDADE**. `repair-page.jsx` é porte REVERSO dos blades (cabeçalho l.1-2; §5 2026-08-28); a aba comparada é `Reparos` (repair-page.jsx:258-300, origem `repair/index.blade.php`) — a lista da **venda-de-reparo** (`transactions` com `sub_type='repair'`), não da folha de OS (UC-RIDX-01). Estado no vivo medido em 2026-09-06 sobre `origin/main` `80bc4ef8b9`, com `grep -n`. Lidos antes: `Index.charter.md` (Non-Goals l.42-45, pendência l.74) e `Index.casos.md` (UC-RIDX-01..04 + BACKLOG dos KPIs). Dado mock do protótipo não é gap.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header | `PageHeader` "Ordens de Serviço" + "Nova OS" condicionado a `permissions.create`, apontando para `/sells/create?sub_type=repair` (Index.tsx:192-205) | Nada — paridade com "Adicionar folha" do shell gated por permissão (repair-page.jsx:705). A aba do mockup se declara só-leitura ("aqui só se lê", repair-page.jsx:297) e o vivo não muta nada em GET (UC-RIDX-04). |
| KPIs | 3 cards Em andamento／Concluídas／Total exibido; os 2 primeiros são clicáveis e alternam o filtro `is_completed`, o 3º não tem `onClick` (Index.tsx:208-232); calculados sobre o filtro aplicado (BACKLOG em Index.casos.md) | **Decidir.** Conjuntos disjuntos: o mockup mostra Reparos faturados／Valor faturado／Em aberto (repair-page.jsx:286-292), dois deles somatórios monetários ⚠️ toca valor (Regra Mestre: só exibir agregado do backend, com dupla prova) — e o próprio mockup anota que cobrança vive no Financeiro (l.290). O charter só pede "KPIs de topo resumindo a fila" (l.33). Adotar, trocar ou rejeitar por escrito; a leitura "sobre o filtro × sobre a base" já está aberta para [W] no BACKLOG do casos. |
| Filtros | Busca por nº／cliente／série, `Select` Local e Responsável, chips de status multi-seleção, "Limpar", partial reload (Index.tsx:235-325; RepairController.php:555-560) | Nada — vivo à frente. A aba Reparos do mockup não tem filtro nenhum (repair-page.jsx:258-300); a busca do shell nem alcança essa aba (ela filtra `folhas`, l.161-165). |
| Tabela | 10 colunas OS／Status／Cliente／Aparelho／Série／Resp.／Aberta／Prazo com selo "atrasada"／Total／Pgto (Index.tsx:342-399). O select Inertia (RepairController.php:513-537) traz `warranty_name`, que a tela tipa (Index.tsx:32) e não renderiza, e não traz `job_sheet_no`／`job_sheet_id`, marca, `total_paid`／saldo nem `added_by` — colunas que o ramo AJAX do Blade seleciona (RepairController.php:168-186) | **Decidir.** O mockup tem Nº do reparo／Fatura／Folha de OS／Cliente／Garantia／Pagamento／Total／Saldo devedor (repair-page.jsx:266-284); o Blade de origem tem ainda marca, local, adicionado por e devolução devida (repair/index.blade.php:63-85). Faltam no vivo contra os dois: link para a Folha de OS, Garantia (dado já chega) e Saldo devedor ⚠️ toca valor (só exibir). O charter chama a tela de port 1:1 do Blade (l.45) e mantém aberta a pendência "Confirmar paridade de colunas／ações vs Blade legacy" (l.74) — esta linha é a medição dela. Construir ou rejeitar por escrito. |
| Ações por linha · detalhe | O nº da OS é link para `/repair/repair/{id}` (Index.tsx:364; `Repair/Show.tsx` existe com `Show.charter.md` e `Show.casos.md`); nenhuma ação de mutação na lista | Nada — paridade com o mockup, que na aba Reparos também só tem clique na linha (repair-page.jsx:295) e nenhuma ação. A coluna Ação do Blade (repair/index.blade.php:63) entra na mesma pendência de paridade do charter (l.74), já apontada na linha Tabela; edição／transição na lista são Non-Goals (l.42-43). |
| Paginação | Server-side, "Mostrando X–Y de N" + anterior／próxima (Index.tsx:404-443) | Nada — vivo à frente. O mockup não pagina a aba (DataTablePro por altura, repair-page.jsx:293-296). |
| Estados | `EmptyState` distingue filtro-sem-resultado de base vazia (Index.tsx:329-340; UC-RIDX-02) | Nada — vivo à frente. O mockup não tem estado vazio nesta aba (dados mock fixos, `D.REPAROS`). |
