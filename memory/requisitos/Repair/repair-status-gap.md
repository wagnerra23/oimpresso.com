---
id: requisitos-repair-repair-status-gap
tela: Repair/Status/Index (/repair/status)
prototipo: prototipo-ui/cowork/repair-page.jsx
tela_viva: resources/js/Pages/Repair/Status/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Repair/Status/Index

> Fase 1 do protocolo (`prototipo-ui/PROTOCOL.md`) em modo **PARIDADE**. `repair-page.jsx` é porte REVERSO dos blades (cabeçalho l.1-2; §5 2026-08-28); a aba comparada é `Status` (repair-page.jsx:302-334, origem `status/index.blade.php`). Estado no vivo medido em 2026-09-06 sobre `origin/main` `80bc4ef8b9`, com `grep -n`. Lidos antes: `Index.charter.md` (Goals l.30-33, Non-Goals l.40-44, UX anti-pattern l.61) e `Index.casos.md` (UC-RSTIDX-01..06, 6 passed no CT 100 em 2026-09-05). Dado mock do protótipo não é gap.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header e ação primária | `PageHeader` "Status de OS (Repair)" + "Novo status" → `/repair/status/create` (Index.tsx:29-41). O gate de permissão é do Controller (RepairStatusController.php:44; UC-RSTIDX-06) | Nada — paridade com título／descrição e "Adicionar status" gated por `repair_status.access` (repair-page.jsx:307-310, 330-331). |
| Lista de status (nome · cor · ordem · concluído) | Tabela Nome／Cor (swatch + hex)／Ordem／Concluído?／Editar (Index.tsx:50-97), ordenada por `sort_order` (RepairStatusController.php:76-78; UC-RSTIDX-01), cor preservada (UC-RSTIDX-02) | Nada — paridade com as linhas do mockup (selo colorido, ordem, flag concluído, Editar — repair-page.jsx:313-325). O Blade de origem só tinha nome／cor／ordem／ação (status/index.blade.php:11-14); o vivo já está à frente dele com a coluna Concluído?. |
| Metadados por status (folhas no status · coluna do kanban · prévia do SMS) | A prop `statuses` traz só `id`／`name`／`color`／`sort_order`／`is_completed_status` (RepairStatusController.php:74-78; Index.tsx:14-20). `sms_template`／`email_subject`／`email_body` existem na tabela (migrations 2020_07_11 e 2020_08_22; RepairStatus.php:28-30) e não vêm na prop; contagem de folhas por status e coluna do kanban não são calculadas | **Decidir.** O mockup mostra por linha "ordem N · coluna X · N folha(s)" e a prévia do SMS (repair-page.jsx:319-321). Nenhum dos 3 existe no Blade de origem nem no vivo — é adição do mockup; os Non-Goals (l.40-44) não os vetam. Exige o Controller agregar a contagem e expor o template. Construir ou rejeitar por escrito. |
| Alerta de exclusão | Não há exclusão nesta tela (0 botão excluir no Index.tsx); o charter manda delete para outra tela (l.61) | Nada — paridade. O `Alert` "Excluir status não é reversível" do mockup (repair-page.jsx:327-329) não tem onde morar aqui; decisão já registrada no charter. |
| Estado vazio | `EmptyState` "Nenhum status configurado" (Index.tsx:43-48) | Nada — vivo à frente. O mockup não tem estado vazio nesta aba (`D.STATUS` fixo). |
