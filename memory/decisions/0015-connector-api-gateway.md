---
# ADR 0015 — Connector: API Gateway para Integrações Externas

**Data:** 2026-04-21
**Status:** Aceita
**Autora:** Eliana (WR2 Sistemas) — levantamento sessão 10

---

## Contexto

O módulo **Connector** é o gateway de API REST do oimpresso.com. Possui 40+ controllers de API cobrindo praticamente todos os recursos do sistema. É o ponto de entrada para: aplicativos mobile, BI externo, integrações de terceiros e o próprio módulo `ia.oimpresso.com`.

---

## Arquitetura do Connector

```
Modules/Connector/
├── Http/
│   ├── Controllers/Api/
│   │   ├── BaseApiController.php   ← Controller base com autenticação
│   │   ├── BusinessController.php
│   │   ├── AttendanceController.php  ← Frequência (Essentials)
│   │   ├── SellController.php
│   │   ├── PessoasGrupoController.php
│   │   ├── CondicaopagtoController.php
│   │   ├── JanaController.php       ← Integração módulo Jana
│   │   ├── OimpressoController.php  ← API customizada WR2
│   │   ├── CaixaController.php
│   │   ├── BalancoController.php
│   │   └── ... (35+ outros controllers)
│   └── Middleware/
│       └── Oimpressoapiauth.php    ← Autenticação própria da WR2
├── Http/routes.php                 ← Todas as rotas /api/...
└── Providers/ConnectorServiceProvider.php
```

---

## Decisão

### PontoWR2 deve expor sua API **via Connector**, não com rotas próprias de API

**Razão:** O Connector já tem:
- Middleware de autenticação (`Oimpressoapiauth`) compatível com o BI
- Padrão estabelecido de rotas `/api/`
- Controle de permissões por business_id
- Clientes já integrados (BI, mobile, ia.oimpresso.com)

**Como implementar:**
```php
// Em Modules/Connector/Http/routes.php — adicionar grupo PontoWR2:
Route::group(['prefix' => 'api', 'middleware' => ['api', 'Oimpressoapiauth']], function () {
    Route::get('ponto/colaboradores', [PontoController::class, 'colaboradores']);
    Route::post('ponto/marcacoes', [PontoController::class, 'registrarMarcacao']);
    Route::get('ponto/espelho/{colaborador_id}', [PontoController::class, 'espelho']);
    Route::get('ponto/banco-horas/{colaborador_id}', [PontoController::class, 'bancoHoras']);
});
```

**Alternativa descartada:** Criar `Routes/api.php` próprio no PontoWR2 (foi removido na sessão 09 — incompatível com nWidart v9 padrão).

---

## Endpoints prioritários para PontoWR2

| Endpoint | Método | Uso |
|----------|--------|-----|
| `GET /api/ponto/colaboradores` | GET | Lista colaboradores ativos do business |
| `POST /api/ponto/marcacoes` | POST | Registrar batida de ponto (AFD/REP-P) |
| `GET /api/ponto/espelho/{id}/{mes}` | GET | Espelho de ponto mensal |
| `GET /api/ponto/banco-horas/{id}` | GET | Saldo atual banco de horas |
| `POST /api/ponto/intercorrencias` | POST | Registrar intercorrência |
| `GET /api/ponto/afd/{mes}` | GET | Download AFD mensal |

---

## Consequências

- PontoWR2 pode ser acessado pelo BI (`ia.oimpresso.com`) via Connector
- Aplicativo mobile pode registrar batidas via `POST /api/ponto/marcacoes`
- Integração com eSocial pode usar o mesmo gateway
- **Risco:** Se o Connector mudar autenticação, o PontoWR2 API quebra junto — mas é o trade-off correto vs. manter autenticação duplicada

---

> **Referências:** ADR 0013 (ecossistema módulos), CLAUDE.md §6
