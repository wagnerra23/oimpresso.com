---
sessao: "12"
titulo: Garantias — recorte "Garantia crítica" dentro de Bens (reescrita 2026-09-29, D-GARANTIAS)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main a71c2f2d052f (lida 2026-09-29) — reler no turno
prefixo: Modules/AssetManagement/Http/Controllers/AssetController.php (@index) · resources/js/Pages/Patrimonio/Bens.tsx · Bens.charter.md · Bens.casos.md
nao_toca: resources/js/Pages/Patrimonio/Garantias/ (não nasce) · Routes/web.php (nenhuma rota nova) · _shared/
depende: thread 07 · D-GARANTIAS
---
# 12 · Garantias vira recorte de Bens

## Decisão
**D-GARANTIAS = filtro dentro de Bens, sem tela própria** ([W] 2026-09-29, decide-for-me sobre a recomendação do [CC]). A versão anterior desta ficha (tela `Garantias/` + rota + controller novos) está **revogada**.

## O que o `main` diz hoje (medido)
- `Bens.tsx:17` e `Bens.charter.md:75` — os sub-recortes "Garantia crítica" / "Em manutenção" ficaram **fora** porque pedem predicado SQL novo.
- `Bens.charter.md:108` — **proíbe** derivar "garantia crítica" no cliente a partir de `dias_restantes`. Essa proibição **continua**: o recorte nasce no servidor.
- `Bens.casos.md:168` — os sub-recortes estão em `[BACKLOG]`.
- `AssetController.php:18` já usa `AssetWarranty`; `:213-223` lê a janela da garantia por linha.

## Alvo (protótipo)
`patrimonio-page.jsx` — `CliTabs` "Recorte do patrimônio" (`:353-360`): Todos · Alocáveis · **Garantia crítica** · Em manutenção. Predicado do protótipo (`:269`): garantia **vencida ou vencendo em 30 dias**; bem **sem** registro de garantia **não** entra (vai para "sem garantia", `:185`).

## Execução (1 PR ≤ 300 linhas)
1. `AssetController@index`: aceitar `recorte=garantia` (whitelist, valor fora vira `todos`) → `whereHas('warranties', end_date <= hoje + 30 dias)` com `business_id` explícito; devolver a **contagem** do recorte junto (o cliente não conta).
2. `Bens.tsx`: sub-recorte "Garantia crítica" com a contagem do servidor; troca faz `router.get` com `recorte`.
3. `Bens.charter.md`: tirar o Non-Goal `:75` **só para Garantia crítica**; `:108` fica.
4. `Bens.casos.md`: UC do recorte (Dado · Quando · Então) + controle negativo (bem sem garantia não aparece); `:168` sai do BACKLOG só para este recorte.

## PARAR SE
- o predicado exigir mudar `asset_warranties` (migration) → outro PR;
- alguém propor "Em manutenção" junto → não foi decidido; fica BACKLOG.

## Prova
`Bens.tsx` contém `Garantia crítica` · `Garantias/Index.tsx` **ausente** · Pest do recorte com tenant cruzado · `_saida-12.md`.
