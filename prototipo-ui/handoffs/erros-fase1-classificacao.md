<!-- cowork: target: prototipo-ui/handoffs/erros-fase1-classificacao.md -->
---
handoff_id: erros-fase1-classificacao
tela: Plataforma/ErrorHandling
files: [app/Exceptions/Handler.php, app/Support/Errors/ErrorClassifier.php, app/Support/Errors/Severity.php, app/Support/Errors/Audience.php, app/Notifications/S0Alert.php, config/errors.php]
created_by: CC
audited_against: 0f98814eb4f8
---
## Fase 1 do Plano Sustentável de Erros — classificar na origem + canal do S0

**Objetivo (crawl, não boil-the-ocean):** todo erro nasce **carimbado** com `severidade (S0–S3) +
público (operador/construtor) + dono + chave de dedup` — e só o **S0** interrompe 1 humano. O resto
vai pra log/dashboard (OTel + mcp_audit_log que já existem). NÃO é o sistema de alerta completo; é a
**régua na origem** + o cano do S0. Dedup-pesado, digest e auto-resolução são Fase 2/3.

**Fonte da régua:** o Mapa de Severidade do Oimpresso (rascunho [W]) — fiscal, cobrança, produção,
inbox, plataforma/Tier-0, loop. Os S0 reais: gateway de pagamento fora · baixa não concilia ·
**vazamento entre clientes (S0 silencioso)** · ERP fora · auth quebrado · backup/migration destrutiva.

**§10.4:** validar contra o `main` fresco antes. Onde a spec divergir do main, **main vence**.
Reusar o que existe: `OtelHelper::span`, `mcp_audit_log`, padrão de notificação do projeto. NÃO inventar
stack de observabilidade nova.

### 1. Vocabulário (enums)
- `app/Support/Errors/Severity.php` — enum `S0|S1|S2|S3` com `slaMinutes()` e `interrompeHumano(): bool` (só S0).
- `app/Support/Errors/Audience.php` — enum `OPERADOR|CONSTRUTOR|AMBOS`.

### 2. Classificador (o coração)
`app/Support/Errors/ErrorClassifier.php` — `classify(Throwable $e, ?Request $r): Classification`
devolve `{severity, audience, owner, dedupKey, operatorMessage}`. Regras por tipo/domínio, na ordem
(primeira que casar vence), espelhando o Mapa:
- **S0:** exceção de gateway de pagamento · divergência de conciliação · **detecção de cross-tenant**
  (query/registro com `business_id` ≠ do contexto — o S0 silencioso) · falha de boot/DB indisponível ·
  falha de auth global · erro em migration/backup.
- **S1:** emissão fiscal nossa falhou (certificado/config) · WhatsApp/Baileys desconectou · OS travada ·
  boleto falhou p/ 1 · transporte de ingest mudo · servidor stale (já tem a sentinela).
- **S2:** erro-rate/latência subindo · certificado a vencer · webhook atrasado (recuperável) · gate
  vermelho · handoff parado >3d.
- **S3 (default):** `ValidationException`, exceções esperadas/tratadas, ruído conhecido.
- `dedupKey = hash(classe + local + business_id)` — pra Fase 2 agrupar; aqui já carimba.
- `operatorMessage` = texto humano de recuperação (NUNCA o trace). Ex.: "Não consegui emitir a NF-e
  agora — salvei como rascunho. Tente de novo ou chame o suporte."

### 3. Roteamento no Handler
`app/Exceptions/Handler.php` (estende o do projeto, não substitui):
- `report()`: classifica → grava em `mcp_audit_log` (severity/audience/owner/dedupKey) + span OTel.
- **S0** → dispara `S0Alert` (Notification, §4) **com rate-limit por dedupKey** (1 por grupo a cada
  N min via Cache — `Cache::has`/`put`, NUNCA `Cache::flush`). S1 → canal ops (se já existir; senão só
  audit+log nesta fase). S2/S3 → só audit/log/OTel, **nada de alerta**.
- `render()` (operador): resposta Inertia/JSON com `operatorMessage` + caminho de recuperação —
  **nunca** stack/trace pro cliente. Trace só no canal construtor.

### 4. Canal do S0 (1 só, configurável)
`app/Notifications/S0Alert.php` + `config/errors.php`:
- `'s0_channel' => env('ERROR_S0_WEBHOOK')` — 1 webhook/destino (push ou WhatsApp de 1 pessoa).
  **[W] seta UMA vez** no `.env`. Sem env → degrada pra log (skip, sem crash).
- Payload enxuto: o quê, onde, business afetado, dedupKey, link pro dashboard/PR. **Sem PII** (LGPD).

### 5. UX de recuperação (as 3 telas que mais doem)
Operador vê recuperação, não erro técnico, em: **NF-e (emissão)**, **Pagamento/cobrança**, **Fila/OS**.
Componente único de erro-com-recuperação (mensagem + ação "tentar de novo / salvar rascunho / suporte").

### NÃO FAZER (anti-escopo / anti-Fase-2)
- ❌ Construir agrupamento/digest/auto-resolução completos — é Fase 2/3.
- ❌ Alertar S1/S2/S3 por push/WhatsApp — só S0 interrompe humano.
- ❌ Mostrar trace pro operador. ❌ `Cache::flush()`. ❌ PII no alerta.
- ❌ Substituir o Handler do projeto — estender. ❌ stack de observabilidade nova (usar OTel/audit).

### PRONTO QUANDO (prova · Pest)
- `ErrorClassifier` mapeia cada exceção-exemplo do Mapa pra severity+audience corretos (teste tabelado).
- Exceção S0 dispara `S0Alert` **uma vez** por dedupKey na janela (rate-limit testado); S2/S3 **não** disparam.
- `render()` de erro pro operador retorna `operatorMessage` e **não** vaza trace (teste de resposta).
- Detector cross-tenant: registro com `business_id` alheio → S0 (teste Tier-0).
- Sem `ERROR_S0_WEBHOOK` → degrada pra log, sem exceção.

> [W] faz UMA VEZ: setar `ERROR_S0_WEBHOOK` no `.env`. Cowork read-only no git — este handoff é DESIGN
> (dado), o código é PR revisado do [CL] (toca `app/` → review humano, nunca auto-merge).
