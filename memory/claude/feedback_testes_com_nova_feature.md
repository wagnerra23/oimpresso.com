---
name: Sempre criar teste junto da função nova
description: Toda função/endpoint/rota nova deve vir com teste Pest regression que proteja o contrato
type: feedback
originSessionId: 0922b4af-6c32-45e6-ae30-5d09580ae4ca
---
Sempre que criar uma função, rota, endpoint ou migration nova, escrever um teste Pest que:
- Exercite o comportamento essencial (smoke test: "funciona?")
- Guarde o contrato público (assinatura, response shape, chave única, nome de coluna) via reflexão ou assertion no retorno — se mudar acidentalmente, teste vermelho avisa
- Ficam em `tests/Feature/<Modulo>/<Area>Test.php` (ou `tests/Unit/` se puro)

**Why:** Wagner pediu explicitamente em 2026-04-24 — entende que iterar sem teste custa muito mais token de debug. Um teste no mesmo commit mata regressão e dispensa ler logs pra descobrir o que quebrou.

**How to apply:**
- Não é preciso 100% cobertura; é proteção dos **pontos de contrato** (URL, nome de campo, formato de resposta, match keys de SQL).
- Exemplos concretos no repo: `tests/Feature/Connector/DelphiOImpressoContractTest.php` — 9 testes que blindam o contrato Delphi sem subir infra.
- Quando a função toca um DB real, use reflection sobre o source (como no `saveEquipamento`) em vez de setup pesado de fixtures.
- Rodar `vendor/bin/pest tests/Feature/<Modulo>` antes de cada commit; se falhar, arrumar antes de seguir.
