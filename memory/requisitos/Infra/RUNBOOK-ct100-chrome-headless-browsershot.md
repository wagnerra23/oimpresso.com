# RUNBOOK — CT 100 Chrome headless pra Browsershot (PDF Transcript Sells)

> **Tipo:** receita operacional "como instalar/ativar Chrome headless no CT 100"
> **Validado:** pendente Wagner executar (criado 2026-05-18 pós-merge PR #1050)
> **Pré-leitura obrigatória:** [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) · [`memory/proibicoes.md` §Ambiente](../../proibicoes.md) · `RUNBOOK-acesso-ct100.md`

---

## 1. Contexto

### Por que separação Hostinger ≠ CT 100 (ADR 0062 — IRREVOGÁVEL)

- **Hostinger** é shared hosting LiteSpeed — proibido instalar Chrome headless, daemons, octane, mcp (CLAUDE.md §Proibições + ADR 0062). Tentativas anteriores crasharam o app inteiro.
- **CT 100 Proxmox** é container dedicado com root, sudo, apt-get — runtime correto pra Chrome headless, Centrifugo, FrankenPHP, Meilisearch.
- Browsershot precisa **Chromium + Node + puppeteer-core** instalados no SO. Não é uma lib PHP pura — é wrapper que faz IPC com Chrome.

### Por que esse RUNBOOK existe

PR #1050 ([SellTranscriptPdfController](../../../app/Http/Controllers/SellTranscriptPdfController.php)) já entregou:
- Rota `GET /sells/{sale}/transcript.pdf` + Blade A4 template
- Detecção `class_exists(\Spatie\Browsershot\Browsershot::class)` → fallback estruturado 503 graceful
- Frontend probe HEAD esconde botão "Baixar PDF" quando endpoint devolve 503
- Pest verde cobrindo fallback + multi-tenant cross-business

**Estado atual em prod (Hostinger biz=1):** rota responde 503 `reason=browsershot_not_installed` — comportamento esperado, frontend degrada pro `window.print()` do modal Transcript HTML existente.

**Estado após executar este RUNBOOK (CT 100):** rota responde 200 `Content-Type: application/pdf; Content-Disposition: attachment` — download PDF real server-side gerado por Chromium headless.

### Cenário antes/depois

| Aspecto | Antes (hoje) | Depois (pós-RUNBOOK no CT 100) |
|---|---|---|
| Rota `/sells/{id}/transcript.pdf` | HTTP 503 graceful | HTTP 200 PDF binary |
| Botão "Baixar PDF" no SaleSheet | Oculto (probe HEAD detecta 503) | Visível → download forçado |
| Hostinger biz=1 | Mantém 503 SEMPRE | Mantém 503 SEMPRE (ADR 0062 irrevogável) |
| CT 100 oimpresso-mcp container | Sem Browsershot | Com Browsershot operacional |

---

## 2. Pré-requisitos

- [x] Acesso SSH Tailscale `tailscale ssh root@ct100-mcp` (ver `RUNBOOK-acesso-ct100.md`)
- [x] Disco livre ≥1.5GB no container (Chrome + libs ~600MB; node_modules ~400MB)
- [x] Permissão `root` (default user do CT 100)
- [x] PR #1050 já mergeado em `main` (controller + Blade + fallback)
- [x] Backup snapshot Proxmox antes de mexer (regra ouro infra empresa)

---

## 3. Procedimento (passo a passo)

### Passo 1 — SSH CT 100 + entrar no container

```bash
tailscale ssh root@ct100-mcp
# Pode pedir auth URL no primeiro acesso da sessão — Wagner abre + aprova
docker exec -it oimpresso-mcp bash
```

Working dir do container: `/var/www/html` (Laravel root, mesmo layout do Hostinger).

### Passo 2 — Instalar Chromium + libs sistema (Debian/Ubuntu)

```bash
apt-get update
apt-get install -y \
    chromium chromium-driver \
    libgbm1 libxkbcommon0 libgtk-3-0 \
    libnss3 libatk-bridge2.0-0 libdrm2 libxcomposite1 libxdamage1 \
    libxrandr2 libxss1 libasound2 \
    fonts-noto-color-emoji fonts-liberation
chromium --version  # esperado >= 120
which chromium      # esperado: /usr/bin/chromium
```

### Passo 3 — Instalar Node 20 LTS + puppeteer

```bash
node --version 2>/dev/null  # esperado >= 18; se ausente ou antigo:
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs
npm install -g puppeteer
# puppeteer baixa Chromium próprio em ~/.cache/puppeteer — coexiste com /usr/bin/chromium
```

### Passo 4 — Composer require Browsershot no Laravel

```bash
cd /var/www/html  # ou path canônico do oimpresso-mcp Laravel
composer require spatie/browsershot
# Browsershot é dependência PHP pura; Chrome + Node são pré-reqs do SO acima
```

### Passo 5 — Validar via tinker (smoke unit)

```bash
php artisan tinker --execute='
echo class_exists(\Spatie\Browsershot\Browsershot::class) ? "Class OK\n" : "FAIL\n";
$pdf = (new \Spatie\Browsershot\Browsershot)
    ->setHtml("<h1>Teste oimpresso CT 100</h1>")
    ->noSandbox()
    ->pdf();
echo $pdf ? "PDF gerado (".strlen($pdf)." bytes)\n" : "PDF FAIL\n";
'
```

Saída esperada:
```
Class OK
PDF gerado (12450 bytes)
```

### Passo 6 — Smoke real prod (curl com cookie autenticado)

```bash
# Pré: obter cookie de sessão fazendo login via curl ou copiar de Brave/Chrome devtools
COOKIE="laravel_session=eyJpdiI6...; XSRF-TOKEN=..."

curl -sv -o /tmp/test.pdf \
  -H "Cookie: $COOKIE" \
  "https://oimpresso.com/sells/1/transcript.pdf" 2>&1 | grep -E '^< (HTTP|Content)'

file /tmp/test.pdf
```

Saída esperada literal:
```
< HTTP/2 200
< Content-Type: application/pdf
< Content-Disposition: attachment; filename="venda-XXXX.pdf"
/tmp/test.pdf: PDF document, version 1.4, ...
```

⚠️ **Sem evidência curl literal acima → NÃO declarar "PDF funcionando em prod"** (memory/proibicoes.md §Claim sem evidência).

---

## 4. Troubleshooting

| Sintoma | Causa provável | Fix |
|---|---|---|
| `Failed to launch browser` | libs faltando | `apt-get install -y libnss3 libatk-bridge2.0-0` |
| `Permission denied: chromium` | binário sem cap | `setcap cap_net_bind_service=+ep $(which chromium)` |
| `Error: Could not find expected browser` | puppeteer não encontrou Chromium | `PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium` no `.env` |
| 503 persiste pós-install | classe não carregou | `composer dump-autoload` + reiniciar php-fpm/Octane (CT 100) |
| PDF gerado em branco/zerado | Sandbox bloqueia em container | adicionar `->noSandbox()` no controller (já não está — adicionar se necessário) |
| `EACCES /root/.cache/puppeteer` | npm global rodado como root sem cache writable | `mkdir -p /root/.cache/puppeteer && chmod -R 755 /root/.cache/puppeteer` |

---

## 5. Validação pós-deploy (checklist final)

- [ ] `chromium --headless --dump-dom https://oimpresso.com/login | head -c 200` retorna HTML válido
- [ ] `php artisan tinker --execute='echo class_exists(\Spatie\Browsershot\Browsershot::class) ? "OK" : "FAIL";'` retorna `OK`
- [ ] Pest CI verde — `php artisan test --filter=SellsTranscriptPdfTest` (já cobre fallback + tenancy; sobreviver a re-run)
- [ ] Smoke prod curl literal acima retorna `HTTP/2 200` + `Content-Type: application/pdf` + `Content-Disposition: attachment`
- [ ] PDF aberto em Adobe Reader / Brave / Chrome → renderiza header empresa + linhas venda + totais sem erro
- [ ] Hostinger biz=1 (`https://oimpresso.com/sells/1/transcript.pdf`) CONTINUA respondendo 503 (ADR 0062 — Chrome NÃO instalado lá)
- [ ] Frontend SaleSheet → botão "Baixar PDF" aparece (probe HEAD detecta 200) e download dispara

⚠️ **Hostinger DEVE continuar 503.** Se PDF começar a gerar em Hostinger, alguém violou ADR 0062 instalando Chrome lá — abrir incidente.

---

## 6. Rollback

Se Browsershot causar instabilidade no CT 100 (memory leak, zumbis Chromium, latência):

```bash
# Dentro do container oimpresso-mcp
cd /var/www/html
composer remove spatie/browsershot
composer dump-autoload

# UI automaticamente esconde botão (probe HEAD volta a detectar 503)
# Não precisa rebuild front

# Limpeza opcional do SO (libera ~600MB):
apt-get remove --purge -y chromium chromium-driver
apt-get autoremove -y
npm uninstall -g puppeteer
rm -rf /root/.cache/puppeteer
```

Frontend volta ao `window.print()` do modal HTML existente — degradação graceful documentada no controller.

---

## 7. Custo / Performance (estimativa CT 100)

| Métrica | Valor estimado |
|---|---|
| Disco | ~600MB Chromium + libs + ~400MB node_modules puppeteer |
| RAM cold-start | ~100MB Chromium boot |
| RAM warm (durante render) | ~50MB pico por request |
| Latência render A4 simples (1 venda 5-10 linhas) | cold ~800-1500ms; warm ~250-400ms |
| Rate sustained | ~150 PDF/min (1 worker síncrono CT 100) |
| Cache | nenhum nesta iteração — sempre re-render |

Se volume > 150 PDF/min sustained ou latência cold incomodar UX, próxima iteração:
- Browsershot pool warm via daemon
- Queue async + S3 + email link
- Cache por `sale.updated_at`

Fora do escopo desta iteração (PR #1050 + este RUNBOOK).

---

## 8. Refs

- **PR #1050** — `SellTranscriptPdfController` + Blade A4 + fallback 503 + Pest
- **ADR 0062** — [Separação runtime Hostinger ≠ CT 100](../../decisions/0062-separacao-runtime-hostinger-ct100.md)
- **ADR 0093** — [Multi-tenant Tier 0 irrevogável](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- **Skill** — `runtime-rules-hostinger-ct100` (Tier B auto-trigger)
- **RUNBOOK acesso CT 100** — [RUNBOOK-acesso-ct100.md](RUNBOOK-acesso-ct100.md)
- **Smoke Cowork pareada** — [`memory/requisitos/Sells/RUNBOOK-smoke-cowork.md`](../Sells/RUNBOOK-smoke-cowork.md) — 5 checks (manifest + tenancy)
- **Spatie Browsershot** — https://spatie.be/docs/browsershot
- **Controller** — [`app/Http/Controllers/SellTranscriptPdfController.php`](../../../app/Http/Controllers/SellTranscriptPdfController.php)
- **Pest cobertura** — `tests/Feature/Sells/SellsTranscriptPdfTest.php`

---

**Última atualização:** 2026-05-18 — RUNBOOK criado pós-merge PR #1050. Pendente execução real Wagner no CT 100 + atualizar seção "Validado" no header com data + hash commit.
