---
id: requisitos-repair-repair-device-models-gap
tela: Repair/DeviceModels/Index (/repair/device-models)
prototipo: prototipo-ui/cowork/repair-page.jsx
tela_viva: resources/js/Pages/Repair/DeviceModels/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Repair/DeviceModels/Index

> Fase 1 do protocolo (`prototipo-ui/PROTOCOL.md`) em modo **PARIDADE**. `repair-page.jsx` é porte REVERSO dos blades (cabeçalho l.1-2; §5 2026-08-28); a aba comparada é `Modelos` (repair-page.jsx:336-366, origem `device_model/index.blade.php`). Estado no vivo medido em 2026-09-06 sobre `origin/main` `80bc4ef8b9`, com `grep -n`. Lidos antes: `Index.charter.md` (Goals l.35, Non-Goals l.42-45) e `Index.casos.md` (UC-DMIDX-01..06). Dado mock do protótipo não é gap.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header | `PageHeader` "Modelos de Dispositivo (Repair)" + "Novo modelo" → `/repair/device-models/create` (Index.tsx:138-150) | Nada — paridade com a dica + "Adicionar modelo" gated por `repair.create` (repair-page.jsx:356-360). |
| KPIs | 3 cards deferidos Total de modelos／Marcas ativas／Categorias (Index.tsx:152-158; UC-DMIDX-05) | Nada — vivo à frente. A aba do mockup não tem KPI (repair-page.jsx:336-366). |
| Filtros | Marca + Categoria server-side com `localStorage` e "Limpar" (Index.tsx:160-214; UC-DMIDX-01／04) | Nada — vivo à frente. A aba do mockup não tem filtro (só a busca do shell, que filtra `folhas`, repair-page.jsx:161-165). |
| Tabela | Modelo／Marca／Categoria／Checklist／Editar (Index.tsx:228-275). Checklist é só um badge "Sim" ou "—" (Index.tsx:254-264), porque o payload manda `has_checklist` booleano (DeviceModelController.php:158) apesar de selecionar `repair_checklist` (l.150) | **Decidir.** (a) O mockup mostra o checklist como chips por item (`split("|")`, repair-page.jsx:351) e o Blade de origem mostrava o texto do checklist (device_model/index.blade.php:12; `editColumn('repair_checklist')` em DeviceModelController.php:97-100) — o vivo regrediu contra o Blade; devolver o conteúdo é trocar o booleano pelo texto no payload, pequeno. (b) Contagem de folhas por modelo (repair-page.jsx:353) é adição do mockup e exige agregado no Controller. "Equipamento" × "Categoria" é rótulo do mesmo `device_id`, não é gap. Construir ou rejeitar por escrito. |
| Estado vazio | `EmptyState` distingue filtro-sem-resultado de catálogo vazio (Index.tsx:217-226) | Nada — vivo à frente. O mockup não tem estado vazio nesta aba (`D.MODELOS` fixo). |
