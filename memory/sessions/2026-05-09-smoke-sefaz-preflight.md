# SEFAZ smoke pre-flight — biz=1 (Wagner WR2 SC) — 2026-05-09

> **Goal:** emitir 1 NFC-e modelo 65 em homologação SEFAZ-SC partindo de uma venda real
> no Hostinger. Sem riscar produção (biz=4 ROTA LIVRE protegido por per-business gate).
> Validação ponta-a-ponta do pipeline: Listener → Job → NfeService → SEFAZ → event
> NFCeAutorizada → DanfeEmail.
>
> **Tempo estimado pra Wagner executar (após este doc):** 10–15 min.

---

## Status atual (revisão estática 2026-05-09)

Legend: ✅ verificado em código · 🟡 dependente do servidor · ❌ gap detectado

### Pré-condições verificadas no código

- ✅ **Cert .pfx CertificadoService funciona** — `Modules/NfeBrasil/Services/CertificadoService.php:143` (`carregarParaSefaz`) tem fallback ADR 0090 pro legado `business.certificado` BLOB. Auto-mem afirma cert biz=1 ativo no servidor.
- 🟡 **Template SC aplicado** — `Modules/NfeBrasil/Resources/templates/comercio-varejo-simples-sc.php` existe; aplicação via `POST /nfe-brasil/tributacao/templates/comercio-varejo-simples-sc/aplicar` (verificar `nfe_business_configs.business_id=1` no DB).
- ❌ **NCM default NÃO vem do template SC** — bug crítico (ver §Bugs §1). Wagner precisa setar via `/nfe-brasil/tributacao/config-default` OU coluna legacy `business.ncm_padrao` antes de emitir.
- ✅ **Listener registrado** — `NfeBrasilServiceProvider::boot()` linha 45: `Event::listen(SellCreatedOrModified::class, EmitirNfceAoFinalizarVenda::class)`.
- ✅ **Event SellCreatedOrModified disparado** em `app/Http/Controllers/SellPosController.php:644` e `:1459`.
- ✅ **Job dispatch** — `EmitirNfceJob::dispatch($businessId, $transactionId)` em listener:84.
- ✅ **Endpoint status registrado** — `GET /nfe-brasil/api/transactions/{tx}/nfe-status` em `Modules/NfeBrasil/Routes/web.php:98-100`.
- ✅ **Page status Inertia existe** — `resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx` + hook `useNfceStatus.ts` + componente `NfceStatusBadge.tsx`.
- ✅ **DanfeNotaFiscalMail** — `Modules/NfeBrasil/Mail/DanfeNotaFiscalMail.php` reutilizado (mesma classe pra NFe55 e NFC-e).
- ✅ **Listener NFCeAutorizada → email** — `EnviarDanfeNFCePorEmail` registrado em `NfeBrasilServiceProvider:55`. Default flag `email_danfe_nfce_on_autorizada=false` (consumidor anônimo é caso comum). Para o smoke, NÃO precisa email — log da emissão autorizada já valida.
- ✅ **Per-business gate** — listener:74 lê `nfe_business_configs.auto_emission_enabled`. Default false. Wagner deve confirmar biz=1 está com `auto_emission_enabled=1`.

### O que precisa ser verificado no servidor (Wagner faz)

- [ ] `nfe_certificados` tem registro `business_id=1, ativo=1, valido_ate >= today` (OU legacy `business.certificado` BLOB populado)
- [ ] `nfe_business_configs.tributacao_default.ncm_default` setado pra biz=1 (8 dígitos, ex `49111000`) — **OU** `business.ncm_padrao='49111000'`
- [ ] `nfe_business_configs.auto_emission_enabled=1` pra biz=1
- [ ] `business.id=1` tem: `cnpj`, `ie`, `regime`, `cidade_id` (FK pra `cidades.officeimpresso_codigo` IBGE 7 dígitos), `cep`, `rua`
- [ ] `business.ambiente=2` (homologação) pra biz=1
- [ ] `business_locations` tem `state='SC'` pro `business_id=1`
- [ ] `NFEBRASIL_AUTO_EMISSION_NFCE=true` em `.env` Hostinger (default false em `Config/config.php:30`)
- [ ] `NFEBRASIL_RESPTEC_CNPJ` setado em `.env` (sem ele tag `<infRespTec>` é omitida — SEFAZ rejeita cstat 972)
- [ ] `QUEUE_CONNECTION=sync` em `.env` Hostinger (era assim em `.env.example` — sem worker daemon, listeners ShouldQueue rodam síncronos)

---

## Pre-flight checklist (executar em ordem)

### Passo 1 — SSH Hostinger + warm-up

```bash
# Warm-up (5 hits curl IPv4) pra não dar Connection timed out
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

# SSH robusto (auto-mem reference_hostinger_analise.md)
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
```

### Passo 2 — Verificar último deploy bate com main

```bash
cd ~/domains/oimpresso.com/public_html
git log -1 --oneline   # deve mostrar 8e7f5657 ou commit posterior
git status              # working tree should be clean
```

### Passo 3 — Inspecionar DB (pré-requisitos)

```bash
# Inline php tinker via heredoc — receita auto-mem (não usar tinker --execute)
php artisan tinker <<'EOF'
$biz = DB::table('business')->where('id', 1)->first();
echo "biz1 cnpj={$biz->cnpj} regime={$biz->regime} ambiente={$biz->ambiente} cidade_id={$biz->cidade_id}\n";

$cert = DB::table('nfe_certificados')->where('business_id',1)->where('ativo',1)->first();
echo "cert ativo: " . ($cert ? "sim valido_ate={$cert->valido_ate}" : "NAO") . "\n";

$cfg = DB::table('nfe_business_configs')->where('business_id',1)->first();
echo "config: regime={$cfg?->regime} auto_emission=" . (int)($cfg?->auto_emission_enabled ?? 0) . "\n";
echo "tributacao_default: {$cfg?->tributacao_default}\n";

$loc = DB::table('business_locations')->where('business_id',1)->first();
echo "location state={$loc->state} city={$loc->city}\n";

$cidade = DB::table('cidades')->where('id', $biz->cidade_id ?? 0)->first();
echo "ibge cidade emit: " . ($cidade?->officeimpresso_codigo ?? 'NULL') . "\n";
EOF
```

**Critério passa:** `cert ativo: sim`, `auto_emission=1`, `tributacao_default` contém `ncm_default`, `ibge cidade emit` é numérico de 7 dígitos.

Se `ncm_default` NÃO está no JSON, abrir `https://oimpresso.com/nfe-brasil/tributacao/config-default` na UI e setar (ex: `49111000`).

### Passo 4 — Validar cert openssl (sanity check fora do app)

```bash
# Se cert no formato legacy (BLOB business.certificado) - skipped (lib sped-nfe valida)
# Pingar SEFAZ via app:
php artisan tinker <<'EOF'
$svc = app(\Modules\NfeBrasil\Services\NfeService::class);
print_r($svc->consultarStatusSefaz(1));
EOF
```

**Critério passa:** retorna `ok => true`, `cstat => '107'`, `xMotivo => 'Servico em Operacao'`, `ambiente => 2`, `uf => 'SC'`.

Se cstat 280/281/283 → cert vencido/inválido/assinatura difere — STOP, resolver cert antes de prosseguir.

### Passo 5 — Setar flag no `.env` Hostinger

```bash
# Backup primeiro
cp .env .env.bak.smoke-$(date +%Y%m%d-%H%M)

# Adicionar/atualizar (idempotente)
grep -q '^NFEBRASIL_AUTO_EMISSION_NFCE=' .env \
    && sed -i 's/^NFEBRASIL_AUTO_EMISSION_NFCE=.*/NFEBRASIL_AUTO_EMISSION_NFCE=true/' .env \
    || echo 'NFEBRASIL_AUTO_EMISSION_NFCE=true' >> .env

# Confirmar resp tec setado (SEFAZ rejeita cstat 972 sem ele)
grep '^NFEBRASIL_RESPTEC_CNPJ=' .env
# Se vazio: echo 'NFEBRASIL_RESPTEC_CNPJ=<CNPJ_WR2>' >> .env

# Limpar config cache (Laravel pode estar cachando)
php artisan config:clear
php artisan cache:clear
```

### Passo 6 — Criar venda no UI

Browser: `https://oimpresso.com/sells/create` (logado como user com `business_id=1`).

1. Adicionar 1 produto qualquer (R$ 1,00 mínimo)
2. Forma de pagamento: **Dinheiro** (paid)
3. Status: **Final** (não draft)
4. **Salvar** — anotar `transaction_id` da URL (ex `/sells/12345`)

> ⚠️ A listener filtra `type='sell' + status='final' + payment_status in [paid, partial]`. Outras combinações são no-op silencioso.

### Passo 7 — Acompanhar status (polling endpoint)

```bash
# Substituir TX pelo id real da venda criada
TX=12345

# Loop 30s observando status (parar quando is_terminal=true)
for i in {1..15}; do
  curl -s -b cookies.txt "https://oimpresso.com/nfe-brasil/api/transactions/$TX/nfe-status" \
    | python3 -m json.tool
  sleep 2
done
```

**Alternativa UI** (mais fácil): abrir `https://oimpresso.com/nfe-brasil/transactions/$TX/status` — page Inertia faz polling sozinha e mostra badge.

### Passo 8 — Validar evento + log

```bash
# Grep log laravel pelo emissao_id retornado no passo 7
EMISSAO_ID=<id_retornado>
tail -200 storage/logs/laravel.log | grep -E "NFC-e|NfeService|emissao_id.:.$EMISSAO_ID"

# Esperado encontrar nessa ordem:
# 1. "NFC-e listener triggered"
# 2. "NFC-e Job dispatched"
# 3. "NFC-e emission requested"
# 4. "NfeService: NF-e autorizada" com chave_44 + nProt
# 5. "NFC-e emissão processada via NfeService" status=autorizada
```

### Passo 9 — Validar email DanfeEmail (OPCIONAL — só se flag habilitada)

```bash
# DEFAULT no smoke: flag NFEBRASIL_EMAIL_DANFE_NFCE=false (config:53)
# Email não é enviado. NÃO bloqueia critério "smoke OK".
# Se quiser testar email: setar flag true + transaction.contact com email válido + mail driver Hostinger.
tail -100 storage/logs/laravel.log | grep "EnviarDanfeNFCePorEmail"
```

---

## Critério "smoke OK" (todos verdes)

- [ ] `consultarStatusSefaz(1)` retorna `ok=true cstat=107` (passo 4)
- [ ] Após criar venda, registro em `nfe_emissoes` com `business_id=1, transaction_id=$TX, modelo=65`
- [ ] `status='autorizada'` (não pendente, não rejeitada, não denegada)
- [ ] `cstat='100'` (Autorizado o uso da NF-e) ou `'150'` (autorizado fora do prazo)
- [ ] `chave_44` populada (44 dígitos, começando com `42` para SC = código UF SC)
- [ ] `xml_path` setado (arquivo presente em `storage/app/nfe-brasil/1/notas/1-{numero}.xml`)
- [ ] `metadata.nProt` populado (protocolo SEFAZ)
- [ ] Endpoint `/nfe-brasil/api/transactions/$TX/nfe-status` retorna `is_terminal=true, status=autorizada`

---

## Rollback se falhar

### Resetar flag (volta listener pra no-op)

```bash
sed -i 's/^NFEBRASIL_AUTO_EMISSION_NFCE=.*/NFEBRASIL_AUTO_EMISSION_NFCE=false/' .env
php artisan config:clear
```

### Inspecionar erro

```bash
# Log principal
tail -300 storage/logs/laravel.log | grep -E "ERROR|nfe|NFC-e"

# Storage XML rejeitada (Make.php errors antes de SEFAZ)
ls -la storage/app/nfe-brasil/1/notas/

# DB emissao falhada
php artisan tinker --execute="
print_r(DB::table('nfe_emissoes')
  ->where('business_id',1)
  ->orderByDesc('id')
  ->limit(3)
  ->get(['id','status','cstat','motivo','chave_44'])
  ->toArray());
"
```

### Causas comuns + cstat → ação

| cstat | xMotivo                                  | Ação |
|-------|------------------------------------------|---|
| 107   | Servico em Operacao                      | OK (não é erro) |
| 100   | Autorizado o uso da NF-e                 | OK (sucesso) |
| 280/281/283 | Cert vencido/inválido/assinatura difere | Renovar cert via `/nfe-brasil/configuracao/certificado` |
| 539   | Duplicidade de NF-e                      | número conflict — checar `nfe_emissoes.numero` máx + atualizar `business.ultimo_numero_nfe` |
| 696   | Operacao com nao contribuinte deve indicar consumidor final | Já tratado em código (NfeService:684) — log indicará outro problema |
| 717   | NFC-e exige tag indFinal=1               | Já tratado (NfeService:681) — log indicará outro problema |
| 972   | Resp tec obrigatório                     | Setar `NFEBRASIL_RESPTEC_CNPJ` no `.env` |
| 999   | Resposta SEFAZ sem xMotivo (timeout)     | SEFAZ-SC fora — tentar de novo em 5 min |

### Onde olhar primeiro

1. `storage/logs/laravel.log` (últimas 300 linhas) — grep "NFC-e" / "NfeService"
2. Tabela `nfe_emissoes` — coluna `motivo` é o erro humano-readable
3. Tabela `nfe_eventos` (se inutilizar/cancelar)
4. XML em `storage/app/nfe-brasil/1/notas/1-{numero}.xml` — abrir e validar manualmente se cstat 215/225/etc

---

## Bugs/gaps detectados na revisão estática

### 1. CRÍTICO — Templates tributários NÃO setam `ncm_default`

**File:** `Modules/NfeBrasil/Resources/templates/comercio-varejo-simples-sc.php:40-47` (e TODOS os 11 templates)

**Problema:** O array `tributacao_default` dos templates contém apenas `csosn/cfop/aliquota_*`. NÃO contém `ncm_default`. Quando `TributacaoTemplateService::aplicar()` (Service:122) salva esse array em `nfe_business_configs.tributacao_default`, o JSON resultante é insuficiente pra `NfeService::emitirParaTransaction` (NfeService:263-271) que exige `ncm_default` de 8 dígitos.

**Impacto smoke:** Se Wagner aplicou só template SC e nunca passou em `/nfe-brasil/tributacao/config-default`, a primeira tentativa de emissão **falhará com RuntimeException** "Business 1 sem NCM padrão configurado". O Job dará retry 3× e morre. A venda fica criada (não bloqueia o POS), mas nfe_emissoes não tem registro autorizado.

**Severidade:** alta (bloqueia o smoke se não corrigido).

**Mitigação imediata pro smoke:** abrir `/nfe-brasil/tributacao/config-default` e setar `ncm_default=49111000` (gráfica) antes de criar venda. As observações do template SC (linha 53) inclusive recomendam isso. Doc apenas não automatiza.

**Recomendação pós-smoke:** ADR menor + adicionar `ncm_default` aos 11 templates OU forçar UI redirect pra `config-default` quando ausente.

### 2. ALTO — `emitirParaInvoice` usa `$business` antes de carregar

**File:** `Modules/NfeBrasil/Services/NfeService.php:115` referencia `$business->ncm_padrao` mas `$business` só é carregado no DB na linha **124**.

**Impacto smoke:** ZERO — esse bug está no caminho **NFe55 (Invoice)**, não NFC-e. Smoke usa `emitirParaTransaction` (linha 247) que carrega `$business` ANTES de usar (linha 258 vs 263). Smoke não toca esse code path.

**Severidade:** alta MAS fora do escopo do smoke. PHP 8.4 com `declare(strict_types=1)` solta warning "Undefined variable", null-coalescing salva execução (vira `null` → `?? ''` → 8-char check falha → throw). Bug latente — explode quando `$config` for null + business tiver ncm_padrao válido.

**Recomendação:** mover linha 124 (`$business = DB::table(...)`) pra antes da linha 112. Fix de 3 linhas, ideal pra PR de polimento pós-smoke.

### 3. MÉDIO — `cod_municipio` placeholder hardcoded

**File:** `NfeService.php:303` — destinatário NFC-e (`emitirParaTransaction.dest.cod_municipio`) é literal `'9999999'`. SEFAZ aceita em consumidor anônimo (omissão `<dest>`), mas se Transaction tiver contact_id com cidade, não é resolvido.

**Impacto smoke:** baixo — biz=1 smoke cria venda sem contact_id (anônimo), `<dest>` é omitido inteiro pelo bug-fix de 2026-05-08 (linha 727). Funciona.

**Severidade:** média — bug latente quando vendas POS começarem a capturar CPF do consumidor.

### 4. MÉDIO — `.env.example` NÃO documenta variáveis NFEBRASIL

**File:** `.env - Copia.example` (linhas 100-176) — não menciona `NFEBRASIL_AUTO_EMISSION_NFCE`, `NFEBRASIL_RESPTEC_*`, `NFEBRASIL_EMAIL_DANFE_NFCE`. Apenas `NFSE_*` (módulo diferente!) está documentado.

**Impacto smoke:** zero (Wagner sabe). Mas Felipe/Maiara/Eliana num re-deploy ou clone novo não saberão. PR pequeno: adicionar bloco NFEBRASIL ao `.env.example`.

**Severidade:** média (governança).

### 5. BAIXO — Listener filtra payment_status `paid|partial` mas comentário sugere "à vista"

**File:** `Modules/NfeBrasil/Listeners/EmitirNfceAoFinalizarVenda.php:60` aceita `partial`. Comentário linha 56-57 fala "à vista". `partial` em UltimatePOS = pagamento parcial (boleto pago + saldo a vencer). Para NFC-e modelo 65 isso é polêmico fiscalmente (NFC-e é varejo presencial à vista).

**Impacto smoke:** zero (Wagner cria venda paid full).

**Severidade:** baixa — questão fiscal/produto, não bug de runtime.

---

## Resumo executivo (TL;DR pra Wagner)

**Pode smokar?** Sim, em ~12 min, **mas precisa setar `ncm_default` antes** (UI ou tinker). Sem isso, primeira emissão falha com RuntimeException.

**Fluxo síncrono?** Sim — `QUEUE_CONNECTION=sync` no `.env.example` faz `EmitirNfceJob` rodar inline na request POST do `SellPosController`. Listener é `ShouldQueue` mas com queue=sync isso vira execução síncrona. Status atualiza no mesmo request → polling pega valor terminal já no primeiro tick.

**Risco de produção?** Zero — `ambiente=2` (homologação) + per-business gate `nfe_business_configs.auto_emission_enabled` protege biz=4 (ROTA LIVRE). Mesmo se flag global ON, sem opt-in per-business o listener é no-op.

**Próximo passo após smoke OK:** validar PDF DANFE + abrir cstat 100 XML + decidir cutover prod (`ambiente=1`).
