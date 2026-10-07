---
sessao: "12"
titulo: Tier 0 — edit/update com id de outro negócio
dono: "[CL]"
base: 50e23057f1c2
origem: _saida-04 §3
---
# 12 · Tier 0 — edit/update com id de outro negócio

`PrinterController::edit` e `BarcodeController::edit` usam `find()` → `null` com id alheio (a Blade quebra); `BusinessLocationController::update` com id alheio não altera mas responde `success: true`. Os três passam a responder 404. Teste tenant 98×99 por rota, controle positivo com o próprio id.
