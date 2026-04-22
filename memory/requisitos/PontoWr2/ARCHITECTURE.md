# Arquitetura

## 1. Objetivo

Módulo de Ponto Eletrônico conforme Portaria MTP 671/2021 — WR2 Sistemas. Estende UltimatePOS 6 + Essentials & HRM.

## 2. Áreas funcionais

### 2.1. Aprovacao

**Controller(s):** `AprovacaoController`  
**Ações (4):** `index`, `aprovar`, `rejeitar`, `aprovarEmLote`

_Descrição funcional:_ [TODO]

### 2.2. Banco Horas

**Controller(s):** `BancoHorasController`  
**Ações (3):** `index`, `show`, `ajustarManual`

_Descrição funcional:_ [TODO]

### 2.3. Colaborador

**Controller(s):** `ColaboradorController`  
**Ações (3):** `index`, `edit`, `update`

_Descrição funcional:_ [TODO]

### 2.4. Configuracao

**Controller(s):** `ConfiguracaoController`  
**Ações (3):** `index`, `reps`, `storeRep`

_Descrição funcional:_ [TODO]

### 2.5. Core

**Controller(s):** `DashboardController`  
**Ações (1):** `index`

_Descrição funcional:_ [TODO]

### 2.6. Escala

**Controller(s):** `EscalaController`  
**Ações (6):** `index`, `create`, `store`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.7. Espelho

**Controller(s):** `EspelhoController`  
**Ações (3):** `index`, `show`, `imprimir`

_Descrição funcional:_ [TODO]

### 2.8. Importacao

**Controller(s):** `ImportacaoController`  
**Ações (5):** `index`, `create`, `store`, `show`, `baixarOriginal`

_Descrição funcional:_ [TODO]

### 2.9. Intercorrencia

**Controller(s):** `IntercorrenciaController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `submeter`, `cancelar`, `aiClassify`

_Descrição funcional:_ [TODO]

### 2.10. Relatorio

**Controller(s):** `RelatorioController`  
**Ações (2):** `index`, `gerar`

_Descrição funcional:_ [TODO]

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles

### 5.2. Dependências entre módulos

- 🔼 é consumido por **?** (?x)
- 🔼 é consumido por **?** (?x)

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `ApuracaoDia` | `ponto_apuracao_dia` | [TODO] |
| `BancoHorasMovimento` | `ponto_banco_horas_movimentos` | [TODO] |
| `BancoHorasSaldo` | `ponto_banco_horas_saldo` | [TODO] |
| `Colaborador` | `ponto_colaborador_config` | [TODO] |
| `Escala` | `ponto_escalas` | [TODO] |
| `EscalaTurno` | `ponto_escala_turnos` | [TODO] |
| `Importacao` | `ponto_importacoes` | [TODO] |
| `Intercorrencia` | `ponto_intercorrencias` | [TODO] |
| `Marcacao` | `ponto_marcacoes` | [TODO] |
| `Rep` | `ponto_reps` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements PontoWr2`_  
_Ver no DocVault: `/docs/modulos/PontoWr2`_
