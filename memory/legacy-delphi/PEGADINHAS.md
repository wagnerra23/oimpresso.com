---
slug: legacy-delphi-pegadinhas
title: "Pegadinhas — migração Delphi WR Comercial → oimpresso"
type: knowledge-reference
authority: canonical
lifecycle: ativo
owner: felipe
last_updated: 2026-05-15
pii: false
---

# Pegadinhas — migração Delphi WR Comercial → oimpresso

> Gotchas catalogados durante migração. Felipe apende conforme encontra. **Princípio:** se você gastou >30min descobrindo um quirk, ele vale uma entrada aqui — próximo dev economiza.

## Schema/Dados

### `PESSOAS` tem 329 colunas — só ~30 canônicas
Schema cresceu organicamente em 26 anos. Campos como `PLACA`, `MARCAMODELO`, `ANO` vazaram do contexto oficina pro schema geral. Ao migrar pra `contacts` UltimatePOS, **mapear só o subset canônico** documentado em [SCHEMA-FIREBIRD.md](SCHEMA-FIREBIRD.md) §2.5. O resto vira `custom_fields` JSON ou descarta.

### `STATUS='INATIVO'` é soft-delete — sempre filtrar
Em `FINANCEIRO`, `MENSALIDADE_FINANCEIRO`, `CONTRATO`, etc., registros com `STATUS='INATIVO'` são cancelados/excluídos. Análise de saúde do banco SEMPRE filtra `STATUS='ATIVO'`. Migração precisa decidir: ignora INATIVO ou move pra Laravel com `deleted_at` populado?

### `PROVISORIO='S'` em FINANCEIRO = lançamento previsto não-confirmado
Não é dívida real ainda. Filtrar `PROVISORIO='N'` em queries de receita real. Ao migrar, considerar se vira `is_provisional` BOOLEAN ou descarta.

### `CONTRATO` com `VALOR=NULL` precisa reconciliação antes de migrar
62 de 313 contratos no ServidorWR2 estão com `VALOR=NULL`. Antes de importar pra `Modules/RecurringBilling`, decidir: backfill via histórico de pagamentos? Ignora?

### Versão do schema varia muito entre clientes (571 → 1474)
Ver matriz em [`memory/clientes-legacy/_index.md`](../clientes-legacy/_index.md). Cliente `GoldenPrint` está em v571 (gap de ~900 updates pra v1474 mais recente). Migração precisa lidar com schemas heterogêneos — não assumir schema homogêneo.

### Confirme versão real lendo `CONFIGURACOES`, não o registry Delphi
Versão do registry (editor de bancos Wagner) **pode estar desatualizada** se cliente subiu schema sem o editor saber. Query canônica:
```sql
SELECT VALOR FROM CONFIGURACOES WHERE CONFIG = 'VERSAO_BANCO';
```

## Encoding/Charset

### Firebird WR Comercial usa charset WIN1252
Atenção ao conectar via Python:
```python
# firebird-driver Python resolve automaticamente (não precisa argumento)
con = fb.connect('192.168.0.55:Banco', user='SYSDBA', password='masterkey')
```

Mas isql precisa:
```cmd
isql -ch WIN1252 -u SYSDBA -p masterkey "192.168.0.55:Banco"
```

Sem isso, caracteres acentuados (`ç`, `ã`, `é`) viram garbage em queries com `RAZAOSOCIAL`.

### Migração pra MySQL exige conversão WIN1252 → UTF-8
Caracteres especiais precisam ser convertidos. Erro comum: dump direto sem conversão → MySQL grava bytes WIN1252 num campo declarado UTF-8 → render quebrado no React.

## Sincronização paralela Delphi ↔ oimpresso

### Bridge `Controller.OImpresso.pas` é cliente-side — depende de hábito
A bridge **existe** desde antes (Wagner descobriu em 2026-05-11) mas **só roda quando o cliente Delphi clica em "Sincronizar"** ou se for chamada por job interno. Não é automática por default. Sync incompleto = oimpresso fica com snapshot antigo.

### Token OAuth bug histórico — ordem de atribuição (fixed 2026-04-24)
**Sintoma:** Delphi autentica em `/oauth/token` (200 OK) mas `/connector/api/...` nunca chega. Causa: em `TThread.Execute`, `FToken := Token_Registro` era setado **antes** do fetch do token. Resultado: `FToken` ficava vazio, `Bearer ` (vazio) → 401 silencioso.

**Fix:** mover atribuição pra **depois** do fetch. Detalhes completos em [`memory/dominios/wr-comercial/ARQUITETURA.md`](../dominios/wr-comercial/ARQUITETURA.md) §"Bug histórico — ordem do token".

**Regra aprendida:** se um TThread copia estado global (Token, config, etc) pra campo de instância, sempre atribuir a cópia DEPOIS de qualquer mutação do global.

### Build antigo do Delphi só autentica, não chama Connector API
Vargas (biz=164) e outros clientes **não populam** as tabelas oimpresso porque rodam build anterior ao fix de 2026-04-24 — só executam `/oauth/token` mas não `RegistrarSistema` (DFM evento `OnAfterLogin` não wired). Recompilar em IDE Delphi + distribuir `.exe` resolve, mas custo operacional alto (precisa atualizar 1 máquina por vez).

### Backend captura tudo via `log.delphi` middleware
Middleware aplicado a **todo** `/connector/api/*` (inclui groups CRM + fieldforce), **antes** de `auth:api` → grava até 401. Pra debugar:
```bash
php artisan officeimpresso:inspect-api --limit=20
```

Mostra payload + formato + headers das últimas chamadas em prod.

## API/contrato

### Resposta do Connector API NÃO é JSON
`POST /connector/api/processa-dados-cliente` retorna SEMPRE string `S;<msg>` ou `N;<motivo>`. **Não retornar JSON** — quebra Delphi em produção permanentemente ([ADR 0021](../decisions/0021-officeimpresso-contrato-api-delphi.md) imutabilidade).

### Duas variantes de `Services.OImpresso.API.pas` coexistem no disco
Mas só uma compila (Delphi não aceita dois units com mesmo nome). Cuidado ao referenciar — confirmar qual variante o build atual usa antes de fazer afirmação.

### Token `Token_Registro` é global — race condition possível
Múltiplas threads chamando `GetToken` simultaneamente podem sobrescrever o token. Em produção raramente acontece (login é evento único), mas Pest reproduzindo paralelismo pode falhar.

## Acesso/conectividade

### Banco fora da rede `192.168.0.0/24` = sem acesso
Probe `Test-NetConnection 192.168.0.55 -Port 3050` antes de tentar. Wagner roda os Firebird em LAN interna; CT 100 + Hostinger NÃO têm acesso direto.

### `servidor-crm` não é Tailscale — é hostname LAN
Aliases `servidor-crm:D:\DadosClientes\...` no registry Delphi referem-se a hostname interno Windows. SSH/CT 100 não consegue resolver.

### Horário comercial — carga no servidor
Skill `officeimpresso-financial-snapshot` proíbe rodar em horário comercial cliente sem aviso prévio. Lote grande de queries pode degradar performance Delphi do cliente.

## Mapping campos

### `CODIGO` vs `id` — ordem inversa de criação
Em Firebird, `CODIGO` é tipicamente `INTEGER` sequencial via `GENERATOR + TRIGGER BEFORE INSERT`. Em Laravel/MySQL, `id` é `BIGINT UNSIGNED AUTO_INCREMENT`. Ao migrar, considerar:

- Manter `CODIGO` num campo `legacy_codigo` BIGINT pra cross-reference?
- Ou descartar e usar novo `id` Laravel?

Decisão depende de necessidade de rastreio bidirecional (sync gradual).

### `EMISSAO`/`VENCTO`/`DATAPAGTO` são `TIMESTAMP` Firebird
Mas Delphi frequentemente usa só a parte `DATE` (hora 00:00:00). Cuidado ao comparar com `created_at` Laravel (que tem hora real). Não-bug — só semântica diferente.

### `RAZAOSOCIAL` em FINANCEIRO/MENSALIDADE_FINANCEIRO é PII
Denormalizado (cópia do `PESSOAS.RAZAOSOCIAL`). Ao logar ou exportar, sempre anonimizar (`[REDACTED]` ou sha1 6 chars).

### Charset isso é PII e isso não é
PII (sempre redactar/anonimizar antes de commit/log): `RAZAOSOCIAL`, `FANTASIA`, `CNPJCPF`, `ENDERECO`, `FONE*`, `EMAIL`, `MOTIVO_EXCLUSAO` (pode conter nome cliente).
Não-PII (ok logar livre): `CODIGO`, `VALOR`, `DT_*`, `TIPO`, `STATUS`, `BLOQUEADO`, `CODPLANOCONTAS`, `CODTIPOPAGTO`.

## Migração

### Modules/Officeimpresso já existe — não é "criar Module" do zero
**É módulo bridge**, recebe sync via Connector API + gerencia licenças por máquina + audit append-only LicencaLog. NÃO confundir com "migrar feature Delphi pra Module novo" — esses são `Modules/Sells/...`, `Modules/Contact/...`, `Modules/Financeiro/...`, etc.

Spec do Modules/Officeimpresso atual: [`memory/officeimpresso-spec.md`](../officeimpresso-spec.md).

### `Modules/Officeimpresso1/` é backup, não pareado
Em 2026-04-23 descobriu-se que servidor tinha `Modules/Officeimpresso1/` (backup 3.7 não removido pela migração). Causava conflito de namespace (mesmo `name: Officeimpresso`). Foi movido pra `~/Officeimpresso1-3.7-BACKUP/`. Se aparecer de novo em algum servidor cliente — remover.

### `oauth_clients.id` continua INT (não UUID)
Stack Passport v13 convive porque registros existentes funcionam, mas criação de novos clients via `passport:client` falha. Workaround: usar clients existentes. Não tentar "consertar" sem ADR.

### Não recompilar Delphi reflexivamente
[ADR 0113](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) e [`memory/dominios/wr-comercial/ARQUITETURA.md`](../dominios/wr-comercial/ARQUITETURA.md) §"Não recompilar regra":

> "Delphi é código legado sem pipeline de build ativo. Mesmo se houvesse, demanda redistribuir binário pra N máquinas clientes = alto custo operacional."

Mudanças no contrato API quebram clientes em produção permanentemente. **Qualquer integração nova entre Delphi↔Laravel deve ser aditiva** (3 caminhos do ADR 0113).

## Inspecionar Delphi sem `svn.exe` no PATH

TortoiseSVN está em `C:/Program Files/TortoiseSVN/` mas só tem UI, **sem svn.exe CLI**. Pra queries de histórico/checksum, ler `wc.db` (SQLite) direto via Python stdlib:

```python
import sqlite3
c = sqlite3.connect('D:/Programas/.svn/wc.db')
# REPOSITORY: URL + UUID
# NODES: por arquivo — local_relpath, revision, changed_revision, changed_author, checksum
# ACTUAL_NODE: flags de arquivos modificados localmente
# PRISTINE: base de cada checkout
```

Comparar working copy vs pristine (detectar edits não commitados):
```python
import hashlib
with open(path, 'rb') as f:
    sha = hashlib.sha1(f.read()).hexdigest()
# comparar com checksum do NODES (formato '$sha1$<hex>')
```

Detalhes em [`memory/dominios/wr-comercial/ARQUITETURA.md`](../dominios/wr-comercial/ARQUITETURA.md) §"Inspecionar sem svn.exe".

## Backend Laravel (Modules/Officeimpresso)

### `log.delphi` middleware roda ANTES de `auth:api`
Captura 401s também. Útil pra debug quando Delphi não chama o backend (deveria, mas algo trava antes do auth).

### Comando de inspeção
```bash
php artisan officeimpresso:inspect-api --limit=20
```
Mostra payload + formato + headers das últimas chamadas em prod.

### Telas Modules/Officeimpresso são Blade/AdminLTE, NÃO Inertia
Decisão histórica — superadmin-only, sem urgência MWART. Topnav horizontal `layouts/nav.blade.php`. NÃO migrar pra Inertia sem ADR.

## Restrições duras (recap)

- ❌ **NUNCA** modificar `D:\Programas\WR Comercial\app\` — leitura READ-ONLY
- ❌ **NÃO** commitar `.pas` originais no repositório oimpresso (copyright WR)
- ❌ **NUNCA** INSERT/UPDATE/DELETE em banco Firebird de cliente em produção
- ❌ **NUNCA** exportar dados sem anonimização pra git público
- ❌ **NÃO** mudar contrato `/connector/api/processa-dados-cliente` (resposta string `S;`/`N;`)
- ❌ **NÃO** recompilar Delphi sem ADR — qualquer integração nova é aditiva
- ✅ Citar trechos pequenos `.pas` no contexto de docs/ADRs (fair use pra documentar)
- ✅ Salvar mapping/análise em `memory/legacy-delphi/descobertas/`
- ✅ Sempre criar `.gitignore` em pasta com dados reais

## Histórico

| Data | Pegadinha | Custo (h) | Catalogado por |
|---|---|---|---|
| 2026-04-24 | Token OAuth ordem de atribuição | ? | Wagner |
| 2026-04-23 | `Modules/Officeimpresso1/` backup conflitando namespace | ? | Wagner |
| 2026-05-09 | `CONTRATO` com `VALOR=NULL` (62/313) | ? | análise discovery |
| 2026-05-11 | Build antigo Delphi não chama Connector API (Vargas) | ? | descoberta via inspeção `log.delphi` |
| 2026-05-15 | _(Felipe apende)_ | | |
