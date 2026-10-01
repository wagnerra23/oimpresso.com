---
sessao: "01"
titulo: Escopo de negócio na API de licença
dono: "[CL]"
base: 89f32db43080
---
# 01 · L1 + L7

`Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php`: `:243` `::all()`; `:267`, `:293`, `:310` `::find($id)`. Escopar pelo negócio do token. **Não mudar** o formato de resposta nem as rotas — o desktop parseia literal.

Teste: token do negócio A não lê nem altera equipamento do negócio B; client pré-existente continua obtendo resposta `S;…`.

## Prova
No JSON do índice.
