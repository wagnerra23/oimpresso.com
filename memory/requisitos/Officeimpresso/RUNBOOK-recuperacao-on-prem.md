---
type: runbook
module: Officeimpresso
status: ativo
related:
  - memory/decisions/0115-recuperacao-cliente-gold-via-bundle-oimpresso.md
  - memory/decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md
  - memory/decisions/0216-deploy-webhook-rodar-composer-dump-autoload.md
  - memory/decisions/0062-separacao-runtime-hostinger-ct100.md
  - memory/requisitos/NfeBrasil/README.md
  - memory/requisitos/NfeBrasil/SPEC.md
created_at: 2026-05-09
last_updated: 2026-07-04
trigger:
  - "Cliente on-prem em versão antiga querendo NF-e 55 (caso Gold)"
  - "Cliente on-prem destinatário de NF-e que precisa manifestar no prazo SEFAZ (caso Gold real — ADR 0116)"
  - "Reativação de business dormente com instalação local"
  - "Trilha 1 do roadmap (49 dormentes)"
---

# Runbook — Recuperação cliente on-prem (oimpresso Laravel)

> **Caso primário:** Gold Comunicação Visual (sessão 2026-05-09, [ADR 0115](../../decisions/0115-recuperacao-cliente-gold-via-bundle-oimpresso.md) + pivot [ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).
> **Aplica-se a:** qualquer cliente que rode o oimpresso instalado on-prem em versão antiga e precise de NF-e via `Modules/NfeBrasil` — seja **emitindo** (Trilha A) ou **manifestando** sobre notas recebidas (Trilha B).
> **Reutilizável:** este runbook é o molde dos ~49 businesses dormentes (Trilha 1 do roadmap). O tronco (Fases 1–3) é idêntico pra todos; só as fases fiscais mudam por cliente.

## Qual trilha? (a Fase 1 decide — nunca pule)

Tronco comum (Fases 1–3: discovery + proposta + upgrade) + a trilha fiscal que o **discovery** escolhe:

| Se o cliente… | Trilha | Fases fiscais |
|---|---|---|
| **emite** NF-e 55 / NFC-e (vende B2B / varejo) | **A — Emissão** | Fase 4 + 5 + 6 |
| **recebe** NF-e e precisa manifestar no prazo (destinatário) | **B — Manifestação** ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)) | Fase 4-Manifestação |
| ambos | A + B | todas |

> ⚠️ **Lição do caso Gold (por que a Fase 1 é inegociável):** a demanda entrou como "emitir NF-e 55" (Trilha A), mas o discovery revelou que o dealbreaker real era **manifestar** notas recebidas (Trilha B). Ter assumido a trilha errada custaria o upgrade inteiro no escopo errado. Qualificar ANTES de propor.

## Pré-condições antes de iniciar

- [ ] ADR 0115 aceito (ou ADR equivalente pro cliente da vez)
- [ ] Cliente concorda com discovery técnico (acesso SSH ou tela compartilhada)
- [ ] Backup atualizado do servidor on-prem (snapshot VM ou dump MySQL + tarball app)
- [ ] Cert digital A1 disponível na empresa do cliente (.pfx + senha)
- [ ] IE habilitada SEFAZ pra ambiente de produção (se ainda em homologação, OK pra smoke)
- [ ] Ambiente Laravel mínimo: PHP 8.4, MySQL 8+, Composer 2.x

## Fase 1 — Discovery técnico (~2h)

**Objetivo:** identificar delta entre instalação atual do cliente e `main` do oimpresso.

Coletar e registrar em `memory/clientes/<biz-slug>/discovery-2026-MM-DD.md` (gitignored ou Vaultwarden se PII):

1. **Versão atual:**
   - `git log -1` se for repo
   - tag/commit no header do `composer.json` ou `package.json`
   - tela `/officeimpresso/licenca` (se existir naquela versão)
2. **Banco:**
   - Versão MySQL/MariaDB
   - Tamanho do dump
   - Tabelas core já existentes (`business`, `users`, `transactions`, `products`)
   - Tem `nfe_*` tables? (se sim, módulo NfeBrasil parcialmente lá)
3. **Cert/Fiscal:**
   - Cert A1 .pfx local? Storage path?
   - IE registrada na tabela `business` campo `tax_number_1`?
   - Regime tributário (Simples/Lucro Presumido/Lucro Real)?
   - CSC SEFAZ-SP (Código Segurança Contribuinte) disponível?
4. **Infra:**
   - Servidor: VM, container, bare-metal?
   - SO: Linux distro/versão
   - Web server: nginx/Apache + PHP-FPM
   - Conectividade SEFAZ outbound (firewall corporativo bloqueia HTTPS pra `nfe.fazenda.sp.gov.br`?)
5. **Operadora persona:**
   - Quem opera o caixa hoje (equivalente Larissa do ROTA LIVRE)
   - Volume estimado de NF-e/mês
   - Já emitia NF-e em outro sistema antes? Qual?

**Saída:** documento de discovery + decisão GO/NO-GO da Fase 2.

## Fase 2 — Proposta comercial (~3h)

Ver template em [PROPOSTA-COMERCIAL-vs-mubsys.md](PROPOSTA-COMERCIAL-vs-mubsys.md) (a ser criado na próxima US).

Pontos-chave:
- Ancorar diferenciais em [memory/comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md](../../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)
- Pricing on-prem: **TBD por Wagner** (one-time + manutenção anual %)
- Pricing SaaS Hostinger alternativo: R$ [redacted Tier 0]/mês Plano Enterprise NfeBrasil ([README NfeBrasil](../NfeBrasil/README.md))
- Cláusula de cert A1 (cliente fornece + responsabilidade rotação)
- SLA suporte (horário comercial, response time)

## Fase 3 — Upgrade plataforma on-prem (~4-6h)

**Risco:** ALTO. Cliente está em produção. Janela de manutenção obrigatória.

1. **Backup completo** (DB + app + storage)
2. **Diff de migrations** entre versão atual e `main`
3. **Estratégia:**
   - Se delta < 6 meses: `git pull` + `composer install` (sem `--no-dev`) + `php artisan migrate` + `npm ci && npm run build`
   - Se delta > 6 meses: clonar `main` em paralelo, importar dump, validar, cutover DNS/Apache
4. **Hot points (pegadinhas reais catalogadas — cada uma já derrubou prod uma vez):**
   - PHP 7.x → 8.4 pode quebrar packages antigos (ver diff de versão 3.7→6.7 do Officeimpresso)
   - `composer install --no-dev` quebra Faker em prod → **NUNCA usar `--no-dev`**
   - **Pós `git pull`/`reset`, SEMPRE `composer dump-autoload`.** Sem isso, classmap fica stale → **500 em toda rota** com `laravel.log` mudo (incidente 2026-06-18). É a falha nº 1 de deploy interrompido. Ver [ADR 0216](../../decisions/0216-deploy-webhook-rodar-composer-dump-autoload.md).
   - **`composer install` pode abortar por `ext-sodium` ausente no CLI** do servidor (puxado transitivamente por Passport/`lcobucci/jwt`), mesmo com o toggle web ligado. Fix: `--ignore-platform-req=ext-sodium` (o runtime usa RSA; sodium nunca é chamado). Incidente 2026-06-18.
   - **`git checkout`/`pull` aborta se houver untracked colidindo** com arquivos versionados (vivido no staging 2026-07-04: 3 testes untracked). Backup primeiro, então `git clean -fd <path-escopado>` — nunca `clean` na raiz cego.
   - **Se editar arquivo no servidor via Windows/PowerShell:** `Set-Content -Encoding utf8` grava **UTF-8 com BOM** → o BOM antes de `<?php` faz o PHP cuspir HTML e a app crashar. Use `utf8NoBOM` / `[IO.File]::WriteAllText` sem BOM. (Preferir sempre deploy via git, não edição direta — [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md).)
   - `Modules/NfeBrasil` exige `Modules/Officeimpresso` ativo (licença)
   - Triggers MySQL imutabilidade (Portaria 671/2021) — preservar na migration

## Fase 4 — Configuração NfeBrasil (~2-3h)

Ordem de execução em UI `/nfe-brasil/configuracao`:

1. Upload cert A1 .pfx (+ senha) → `nfe_certificados`
2. Configurar regime tributário em `nfe_business_configs` (tabela já existe — migration `2026_05_06_010001`)
3. Aplicar template SP industrial gráfico:
   - `industria-grafica-presumido-sp.php` ou
   - `industria-grafica-simples-sp.php`
   - dependendo do regime
4. Setar `NFEBRASIL_AUTO_EMISSION_NFCE` se Gold tem caixa (NFC-e auto)
5. Validar CSC + ambiente homologação primeiro (`NFE_AMBIENTE=2`)

## Fase 5 — Smoke fiscal homologação SEFAZ-SP (~2h)

Análogo ao [runbook biz=1 SEFAZ-SC](../../auto/runbook_smoke_sefaz_biz1.md), mas pra SP:

1. Criar venda em `/sells/create` com produto NCM válido
2. Verificar `/nfe-brasil/transactions/{tx}/status`
3. Confirmar `cstat == 100` (Autorizado uso NF-e)
4. Validar XML salvo em storage
5. Validar DANFE PDF gerado
6. Cancelar a NF-e teste (até 24h NFC-e / 168h NF-e)

## Fase 6 — Treinamento + cutover prod (~3h)

1. Treinamento operadora Gold (sessão 1h via Meet/Zoom)
2. Documento PDF "Como emitir NF-e no oimpresso" (1 página)
3. Cutover: trocar `NFE_AMBIENTE=2` (homol) → `1` (prod)
4. Acompanhamento 7 dias (verificar rejeições no `/nfe-brasil/monitor`)

## Cláusulas de proibição (Tier 0)

- ⛔ **Cert A1 cliente NUNCA** vem pra Hostinger/CT 100. Fica no servidor dele
- ⛔ **`composer install --no-dev` NUNCA** em prod (auto-mem `reference_composer_install_obrigatorio_pos_deploy.md`)
- ⛔ **PII real Gold** (CNPJ/IE/cert path) **NUNCA** em PR/commit/log público
- ⛔ **Não interromper Cycle 03** (smoke ROTA LIVRE NFC-e SEFAZ-SC)

## Pós-recuperação

- [ ] Apender ao [_INDEX comparativos](../../comparativos/_INDEX.md): "Caso Gold — recuperado em 2026-MM-DD"
- [ ] Atualizar [auto-mem `reference_clientes_ativos.md`] (Wagner faz, não Claude — proibição auto-mem)
- [ ] ADR retro: o que funcionou, o que custou mais
- [ ] Repetir runbook pros próximos dormentes

---

## Reutilização pros 49 dormentes (Trilha 1)

O runbook é o **molde**; cada cliente gera só os artefatos por-cliente. O que é genérico vs por-cliente:

| Genérico (este runbook — não re-escrever) | Por-cliente (gerar a cada recuperação) |
|---|---|
| As 6 fases + trilhas A/B + pegadinhas de upgrade | `discovery-<data>.md` (Fase 1) — versão, banco, cert, trilha |
| Cláusulas Tier 0 (cert, `--no-dev`, PII) | Proposta comercial (pricing definido por Wagner por cliente) |
| Template proposta vs Mubsys | Janela de manutenção + cutover acordados com o cliente |

**Ao recuperar o próximo dormente:** (1) copie o checklist da Fase 1, (2) rode discovery → escolha a trilha, (3) siga o tronco, (4) **aprenda em "Aprendizado pós-Gold"** — o que mordeu num cliente evita retrabalho no seguinte. O runbook melhora a cada recuperação; não nasce dois.

---

**Status do runbook:** `ativo-v1` — refinado 2026-07-04 (trilhas A/B explícitas + pegadinhas reais de upgrade + molde de reutilização). Detalhamento fino de cada fase segue em US-NFE-NNN conforme o 1º caso real roda (ADR 0115 §Plano de execução). As lições do Gold real voltam pra cá (seção "Aprendizado pós-Gold").

---

## Fase 4-Manifestação — Manifestação do Destinatário (caso Gold) · ADR 0116

> Adicionada em 2026-05-09 após pivot ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).
> Aplica-se quando cliente é **destinatário de NF-e** (recebe mercadoria) e precisa manifestar via SEFAZ.
> Caso primário: Gold Comunicação Visual (recebe NF-e de fornecedores de placas; caminhão próprio busca a carga).

### O que é manifestação do destinatário

4 eventos da NT 2014.002:

| Evento | tpEvento | Significado | Prazo SEFAZ |
|---|---|---|---|
| Ciência da Operação | 210210 | "Vi a nota" | 10 dias (recomendado) |
| **Confirmação da Operação** | **210200** | **"Recebi a mercadoria"** — libera transporte | **180 dias** (NT 2014.002) |
| Desconhecimento da Operação | 210220 | "Não conheço — uso indevido CNPJ" | 10 dias |
| Operação não Realizada | 210240 | "Não recebi — dispensa pagamento" | 180 dias (justificativa ≥15 chars) |

Em paralelo: **Distribuição DFe** (`sefazDistDFe($lastNSU)`) baixa XMLs automáticos via NSU do ambiente nacional SEFAZ.

### Pré-requisitos específicos

- [ ] Cert A1 válido (mesmo cert usado na emissão; assina eventos também)
- [ ] CNPJ do destinatário no cadastro `business`
- [ ] IE habilitada (algumas SEFAZ exigem pra `sefazDistDFe`)
- [ ] Conectividade outbound pra `https://www1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx` (ambiente nacional, **não** SEFAZ-UF)

### Passos (US-NFE-049..053)

1. **US-NFE-049** — Migrar legado `app/Manifesto.php` → `Modules/NfeBrasil/Models/NfeDfeRecebido.php` + migrations `nfe_dfe_recebidos` + `nfe_dfe_itens`
2. **US-NFE-050** — `ManifestacaoService::cienciar/confirmar/desconhecer/naoRealizada` envolvendo `sped-nfe::Tools::sefazManifesta`
3. **US-NFE-051** — `DistribuicaoDfeService` + Job `BuscarDfesRecebidosJob` (roda 06:00 BRT, throttle 5min/business)
4. **US-NFE-052** — UI `Pages/NfeBrasil/Manifestacao/Index.tsx` com countdown prazo + bulk Confirmar
5. **US-NFE-053** — Smoke homologação SEFAZ-SP eventos 210/220

### Aprendizado pós-Gold

> Apender aqui aprendizados reais durante e depois US-NFE-053. Padrão a aplicar nos próximos 49 dormentes (Trilha 1).

### Cláusulas adicionais

- ⛔ **NSU é cursor irreversível.** SEFAZ não retorna NSU já consultado. Se DB perde `last_nsu`, perde XMLs. Backup antes de qualquer migration que toca `nfe_dfe_nsu_state`
- ⛔ **Job manifestação NÃO emite NF-e.** Confusão comum entre dev e cliente — UI deixa claro: "Manifestação ≠ Emissão"
- ⛔ **Confirmação 220 é assinada pela cert do destinatário.** Não usar cert do fornecedor mesmo que disponível
- ✅ **Bulk Confirmar é o assassino de Mubsys.** Operadora bate 50 notas em 1 clique → Mubsys/concorrentes não têm. Material de proposta comercial.
