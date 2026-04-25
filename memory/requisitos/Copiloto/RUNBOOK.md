# Runbook — Copiloto

Operação e debug. Assume módulo scaffoldado em `Modules/Copiloto/` e Horizon rodando.

## 1. Seed inicial

```bash
php artisan module:seed Copiloto
```

Seed popula:
- 5 metas template globais (superadmin): `faturamento`, `mrr`, `clientes_ativos`, `churn_mensal`, `ticket_medio`.
- Fontes SQL default pra cada uma (pode customizar via UI ou seed override).
- Meta raiz **"Faturamento anual oimpresso R$ 5mi"** (`business_id = null`) conforme ADR 0022.

## 2. Schedule das apurações

O provider registra via `Kernel.php` do módulo:

```php
$schedule->job(new ApurarMetasAtivasJob)->hourly();
$schedule->job(new AvaliarAlertasJob)->everyFifteenMinutes();
```

`ApurarMetasAtivasJob` descobre metas com `ativo=true` e `cadencia='horaria'` (ou 'diaria' se já passou da meia-noite) e despacha `ApurarMetaJob` por meta.

## 3. Rodar apuração manual

Via tinker:
```php
$meta = Modules\Copiloto\Entities\Meta::find(1);
dispatch_sync(new Modules\Copiloto\Jobs\ApurarMetaJob($meta, now()));
```

Via rota (se permitido):
```
POST /copiloto/metas/1/reapurar
```

## 4. Debug do chat IA

### Verificar qual adapter está ativo
```php
app(Modules\Copiloto\Contracts\AiAdapter::class)::class
// → Modules\Copiloto\Services\Ai\LaravelAiDriver (se módulo LaravelAI ativo)
// → Modules\Copiloto\Services\Ai\OpenAiDirectDriver (fallback)
```

### Logs
- Prompts e respostas completos: `storage/logs/copiloto-ai.log` (canal dedicado).
- Tokens: tabela `copiloto_mensagens.tokens_in` / `tokens_out`.
- Custo acumulado: query agregada em `copiloto_mensagens` × preço OpenAI do modelo.

### Modo dry-run
```bash
COPILOTO_AI_DRY_RUN=true php artisan tinker
```
Neste modo o adapter retorna propostas fixtures (não chama API) — útil pra testar UI sem gastar tokens.

## 5. Adicionar nova métrica (nova Meta de catálogo)

1. Via UI: `/copiloto/metas/create` (wizard guia o processo).
2. Via seed (preferido pra metas template disponíveis a todos businesses):
   ```php
   // Database/seeders/MetasCatalogoSeeder.php
   Meta::create([
       'slug' => 'nps',
       'nome' => 'NPS',
       'unidade' => '%',
       'tipo_agregacao' => 'media',
       'business_id' => null, // meta de catálogo; usuários fazem uma cópia ativa por business
       'origem' => 'seed',
   ]);
   ```

## 6. Trocar driver de apuração

Edita `copiloto_meta_fontes.driver` + `config_json`.

### Driver `sql`
```json
{
  "query": "SELECT SUM(final_total) FROM transactions WHERE business_id = :business_id AND type = 'sell' AND status = 'final' AND transaction_date BETWEEN :data_ini AND :data_fim",
  "binds_extra": {}
}
```
Binds `:business_id`, `:data_ini`, `:data_fim` são injetados pelo `SqlDriver` — **nunca** interpolar string; PDO com bind.

### Driver `php`
```json
{
  "callable": "Modules\\Copiloto\\Drivers\\Php\\ChurnMensal@handle"
}
```
Classe precisa implementar `CalculaMeta` e estar registrada no `CopilotoServiceProvider`.

### Driver `http`
```json
{
  "url": "https://api.externa.com/kpi/xyz",
  "method": "GET",
  "auth": "bearer:env:EXTERNA_TOKEN",
  "json_path": "$.data.total"
}
```

## 7. Problemas comuns

| Sintoma | Diagnóstico | Fix |
|---|---|---|
| Dashboard mostra "sem dados" | Nenhuma MetaApuracao ainda | Força apuração manual (seção 3) |
| Chat trava em "pensando..." | OpenAI offline ou key inválida | Verificar `storage/logs/copiloto-ai.log` |
| Meta duplicada no dashboard | Apuração rodou 2x em paralelo | Checar `fonte_query_hash` na apuração — deve haver unique `(meta_id, data_ref, fonte_query_hash)` |
| Superadmin não vê meta da plataforma | Permissão `copiloto.superadmin` não atribuída | `php artisan permission:assign {user_id} copiloto.superadmin` |
| SQL da fonte lê dados de outro business | Esqueceu `:business_id` no bind | Fixar SQL — PHPUnit test cobre isso |
| Alerta não dispara | `AvaliarAlertasJob` não está no schedule | `php artisan schedule:list \| grep Copiloto` |

## 8. Remoção / limpeza

**Cuidado:** `copiloto_meta_apuracoes` é append-only e pode crescer muito. Pra arquivar:
```sql
DELETE FROM copiloto_meta_apuracoes WHERE data_ref < CURDATE() - INTERVAL 2 YEAR;
```
Ou mover pra tabela fria (`copiloto_meta_apuracoes_archive`). Ainda não implementado — vira RUNBOOK item quando crescer.

## 9. Observabilidade — dashboards recomendados

- **Pulse:** taxa de chamadas IA por hora, tokens acumulados, erros 5xx do adapter.
- **Horizon:** jobs `ApurarMetaJob` — tempo médio, falhas.
- **Grafana/Prometheus** (v2): gauges de "metas ativas sem apuração há 24h+" (sinal de breakage silencioso).

---

**Última atualização:** 2026-04-24
