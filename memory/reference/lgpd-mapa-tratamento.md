# Mapa de Tratamento de Dados Pessoais — oimpresso

> **Base legal:** Art. 37 LGPD (Registro das operações de tratamento) + [Guia Orientativo ANPD Cookies 2024-2025](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-cookies-e-protecao-de-dados-pessoais.pdf).
> **Controlador:** oimpresso (CNPJ a registrar — Wagner Almeida). Para cada cliente PME assinante, o oimpresso atua como **operador** do controlador-cliente (relação dupla camada — ver §Operações).
> **Encarregado / DPO:** Wagner Almeida — `wagnerra@gmail.com`. Função acumulada (pequena empresa) até MRR comportar DPO dedicado.
> **Última revisão:** 2026-05-25 (criação inicial — gap G1 [ADR 0191](../decisions/0191-microsoft-clarity-session-replay-lgpd.md)).
> **Revisão obrigatória:** trimestral OU quando entrar novo subprocessador OU quando finalidade muda OU quando incidente Art. 48.

## Resumo executivo

oimpresso é SaaS B2B ERP gráfico multi-tenant (Laravel 13.6 + PHP 8.4). Trata dados pessoais em duas camadas:

1. **Operadores** (devs/operadores do cliente PME que acessam o sistema): usuários internos do tenant — nome, e-mail, telefone, log de acesso, comportamento de navegação.
2. **Titulares finais** (clientes das gráficas/oficinas/cobrança contratadas pelo tenant): cadastros CPF/CNPJ, endereço, telefone WhatsApp, histórico de vendas/recebíveis, mensagens trocadas.

Base legal predominante: **execução de contrato** ([Art. 7, V](https://www.gov.br/anpd/pt-br/canais_atendimento/agente-de-tratamento/lgpd-art-7-bases-legais)) + **legítimo interesse** (Art. 7, IX) com LIA documentada por operação. Tracking comportamental (session-replay Clarity) é exceção: exige **consentimento opt-in** (Art. 7, I) por força do [Guia ANPD Cookies](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-cookies-e-protecao-de-dados-pessoais.pdf).

Isolamento Tier 0 IRREVOGÁVEL via `business_id` global scope ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)) — toda query DB tem filtro `business_id`, nenhum tenant enxerga dados de outro.

## Categorias de dados tratados

| Categoria | Exemplos | Sensibilidade ANPD | Onde armazenado | Retenção canônica |
|---|---|---|---|---|
| Identificação pessoal | Nome, CPF, CNPJ, e-mail, telefone, endereço | Comum | MySQL Hostinger | 5 anos (Art. 1.179 CC + fiscal) |
| Cadastro fiscal | Inscrição estadual, IE/IM, regime tributário | Comum (público em parte) | MySQL Hostinger | 5 anos |
| Financeiro / cobrança | Boletos, parcelas, vencimentos, status pago | Comum | MySQL Hostinger + Asaas | 5 anos (audit fiscal) |
| Comunicação WhatsApp | Mensagens trocadas, mídia, número | Comum (alguns dados pessoais por conteúdo) | MySQL Hostinger (metadados) + daemon CT 100 (sessão Baileys) | 1825d (`mensagem` em [retention.php](../../Modules/Jana/Config/retention.php)) |
| Comportamento UX | Cliques, scroll, rage clicks, dead clicks, session replay com mask-all | Comum (potencial PII se mask falhar) | Microsoft Clarity (Azure EUA) | 30d gravações + 13m heatmaps (não-configurável free tier) |
| Conversas IA (Jana) | Histórico chat copiloto, fatos persistidos | Comum (titulares + operadores) | MySQL Hostinger + Meilisearch CT 100 | 730d conversa / 1825d mensagem ([retention.php](../../Modules/Jana/Config/retention.php)) |
| Tokens / credenciais cliente | API keys Asaas, tokens NFe, sessão Baileys | **Crítica** (não-PII mas regulamentada) | Vaultwarden self-hosted + DB criptografado | Enquanto contrato ativo |
| Audit trail | `mcp_audit_log`, `jana.audit.*` | Comum | MySQL Hostinger | 365d `mcp_audit_log` / append-only critical entries |

## Operações de tratamento (Art. 37)

### Op-01 — Operação principal SaaS ERP (vendas, CRM, financeiro, fiscal, RH)
- **Finalidade:** prover ao cliente PME (controlador) sistema ERP para operar vendas, contatos, NFe, cobrança recorrente, RH e WhatsApp business.
- **Base legal:** execução de contrato (Art. 7, V) — contrato de licenciamento SaaS oimpresso × cliente PME.
- **Papel oimpresso:** **operador** (cliente PME é controlador dos dados dos titulares finais que cadastra).
- **Titulares afetados:** clientes finais das gráficas/oficinas (titulares) + operadores do cliente PME (usuários sistema).
- **Categorias:** identificação pessoal + cadastro fiscal + financeiro + comunicação WhatsApp + conversas IA.
- **Subprocessadores:** Hostinger Cloud Startup (hospedagem app + MySQL prod) — Brasil; Proxmox/CT 100 on-premise oimpresso (daemon Baileys, Centrifugo, Meilisearch, Jaeger) — Brasil; GitHub (código + Actions CI/CD) — EUA.
- **Transferência internacional:** GitHub (EUA) — código-fonte não inclui dados pessoais de produção; Actions roda contra fixtures locais. SCC GitHub/Microsoft aplica automático.
- **Retenção:** ver `Modules/Jana/Config/retention.php` por entidade (730-1825 dias) + obrigação fiscal 5 anos (Art. 173 CTN).
- **Medidas de segurança:** multi-tenant `business_id` global scope ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)); TLS 1.3 em todas conexões; criptografia at-rest credenciais via `EncryptedCredentialCast`; MFA superadmin; audit append-only via `mcp_audit_log` e `JanaAuditService`.
- **Compartilhamento com 3os:** somente subprocessadores listados. Nenhum compartilhamento marketing/publicidade.

### Op-02 — Análise comportamental Microsoft Clarity (session replay + heatmap + Smart Alerts)
- **Finalidade:** detectar rage clicks, dead clicks, quick backs e padrões de fricção em telas autenticadas para priorização objetiva de melhorias UX (substituir intuição por sinal real). Base operacional da ADR 0105 ("cliente como sinal").
- **Base legal:** **consentimento opt-in explícito** (Art. 7, I) via `ConsentBanner` Linear/Notion-style; cookie `oimpresso_consent_v1` armazena escolha; sem `analytics_accepted=true` o snippet NÃO carrega.
- **Papel oimpresso:** controlador (decide finalidade) + Microsoft Corporation é operador/subprocessador.
- **Titulares afetados:** operadores do cliente PME que consentirem (superadmin e `user_oimpresso` são bloqueados por guard server-side — não poluem dataset).
- **Categorias:** session replay (DOM + cliques + scroll) com **mask-all default** em inputs; URL acessada; user-agent; IP truncado pela Microsoft; custom tags `business_id` + `user_type` + `module`.
- **Subprocessador:** Microsoft Corporation (Microsoft Clarity) — entidade contratante Microsoft Ireland Operations Limited (MIOL).
- **Transferência internacional:** EUA (Azure) — base [Microsoft Products DPA](https://www.microsoft.com/licensing/docs/view/Microsoft-Products-and-Services-Data-Protection-Addendum-DPA) + Cláusulas Padrão Contratuais (SCC) MIOL ↔ EUA. ANPD ainda não publicou lista de países adequados (Art. 33 §II), operação cai em cláusulas contratuais padrão.
- **Retenção:** 30 dias para gravações de sessão; 13 meses para heatmaps agregados (defaults Microsoft, não-configuráveis no tier gratuito).
- **Medidas de segurança:**
  - `mask-all` default em todos inputs ([`clarity.blade.php`](../../resources/views/layouts/partials/clarity.blade.php)) — campo novo nasce mascarado; unmask explícito só em label de verbo (não-PII);
  - 5 guards server-side em [`HandleInertiaRequests:193`](../../app/Http/Middleware/HandleInertiaRequests.php): `CLARITY_ENABLED=true` + `project_id` configurado + usuário autenticado + `! in_array(user_type, ['superadmin','user_oimpresso'])` + consent banner aceito;
  - default OFF em produção (`CLARITY_ENABLED=false` em [`config/services.php:148`](../../config/services.php)) — Wagner ativa manual após smoke;
  - **gap G2 — route-blocklist explícito** pendente: hoje confia em mask-all para `/sells/*`, `/contatos/*`, `/financeiro/*`, `/fiscal/*`. CNIL draft fev/2026 vai exigir bloqueio de rota — implementar antes da publicação;
  - multi-tenant: `clarity('set','business_id', $bizId)` server-side, filtro nativo Clarity garante visualização isolada por tenant.
- **Compartilhamento com 3os:** Microsoft (subprocessador) — sem outros 3os; sem publicidade nem revenda.
- **Direitos do titular:** opt-out reversível via banner cookie (limpa `oimpresso_consent_v1`); **gap G3 — deleção individual de sessão pendente** — hoje feita manual no dashboard Clarity (admin filtra `business_id=X` + delete). Próxima iteração: estender [`LgpdEsquecerTitularTool`](../../Modules/Jana/Mcp/Tools/LgpdEsquecerTitularTool.php) com instrução de runbook ou integração Clarity Data Export API.
- **Sinais regulatórios monitorados:** CNIL França abriu draft dez/2024 mirando Clarity/Hotjar (consulta pública aberta fev/2026); ANPD Brasil simétrica via [Guia Cookies](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-cookies-e-protecao-de-dados-pessoais.pdf) + [Lei 15.352/2026](https://www.conjur.com.br/2026-abr-10/da-norma-a-fiscalizacao-como-a-anpd-aplica-alguns-dos-principios-da-lgpd/) (poder fiscalizatório real ANPD).

### Op-03 — Comunicação WhatsApp Business (atendimento + cobrança via Baileys)
- **Finalidade:** atender clientes finais do tenant via WhatsApp, enviar boletos/lembretes de cobrança, integrar inbox unificado.
- **Base legal:** execução de contrato (Art. 7, V — tenant é controlador) + legítimo interesse (Art. 7, IX — cobrança).
- **Titulares:** clientes finais do tenant (números de telefone, conteúdo de mensagens, mídia).
- **Subprocessadores:** Proxmox/CT 100 on-premise oimpresso (daemon Baileys + Fastify TS — local Brasil, ver [`whatsapp-daemon-ct100.md`](whatsapp-daemon-ct100.md)); Meta/WhatsApp (servidores WhatsApp — destino final mensagens).
- **Transferência internacional:** Meta opera servidores globalmente — usuário final aceitou Termos WhatsApp ao instalar app (controlador secundário). DPA Meta Business via futuro acesso direto Tech Provider ([`meta-whatsapp-tech-provider.md`](meta-whatsapp-tech-provider.md)).
- **Retenção:** 1825 dias para `mensagem` ([retention.php](../../Modules/Jana/Config/retention.php)) + purge automático via `php artisan jana:retention-purge`.
- **Medidas de segurança:** sessão Baileys criptografada at-rest CT 100; HMAC + nonce em webhooks; backpressure inbox queue; OTel tracing Jaeger.

### Op-04 — Assistente IA Jana (copiloto LLM)
- **Finalidade:** copiloto contextual no ERP — responde dúvidas, sugere ações, persiste fatos relevantes.
- **Base legal:** execução de contrato (Art. 7, V).
- **Titulares:** operadores do cliente PME (mensagens conversadas com IA).
- **Categorias:** prompt + resposta + memória de fatos.
- **Subprocessadores:** OpenAI (EUA — provider default `config/ai.php`); Anthropic (EUA — chamadas Claude via API); Google Gemini (EUA — imagens); Cohere (EUA — reranking); Groq (EUA — inferência rápida quando habilitada). DPA padrão de cada provider aplica automático.
- **Transferência internacional:** EUA — todos providers via cláusulas contratuais padrão. Custo IA tracked via OTel (princípio 2 Constituição v2 + [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4).
- **Retenção:** ver `Modules/Jana/Config/retention.php` — `conversa` 730d, `mensagem` 1825d, `cache_semantico` 90d, `memoria_fato` 1825d.
- **Medidas de segurança:** `PiiRedactor` aplica scrubbing em payloads de telemetria; isolamento `business_id` em todas queries Jana; cache semântico evita reenviar prompts duplicados (custo + privacidade).
- **Direitos do titular:** [`DsrService::esquecerTitular`](../../Modules/Jana/Services/Lgpd/DsrService.php) anonimiza CPF/CNPJ em `jana_mensagens`, `jana_memoria_facts`, `jana_cache_semantico`, `jana_conversas` — exposto via tool MCP `lgpd-esquecer-titular` e command `jana:retention-purge`.

### Op-05 — Cobrança e gateway de pagamento
- **Finalidade:** emissão de boletos, PIX, cartão recorrente para clientes finais do tenant.
- **Base legal:** execução de contrato (Art. 7, V).
- **Titulares:** clientes finais do tenant que pagam ao tenant (CPF/CNPJ, dados bancários PIX, parcelas).
- **Subprocessador:** Asaas (CNPJ 19.540.550/0001-21) — fintech brasileira, **sem transferência internacional**; credenciais por business em `rb_boleto_credentials` com `EncryptedCredentialCast`.
- **Retenção:** 5 anos (audit fiscal — Art. 173 CTN + Art. 195 CF).
- **Medidas de segurança:** credenciais Asaas criptografadas at-rest; flags de segurança (`config/services.php` bloco `asaas`); refund jobs assíncronos auditáveis.

### Op-06 — Documentos fiscais (NFe)
- **Finalidade:** emissão de NFe/NFCe/NFSe para clientes finais do tenant.
- **Base legal:** cumprimento de obrigação legal (Art. 7, II) + execução de contrato.
- **Titulares:** clientes finais do tenant (CPF/CNPJ, endereço, itens da nota).
- **Subprocessadores:** SEFAZ estaduais (governo BR — destinatário legal); certificado digital A1 do cliente (armazenado criptografado).
- **Transferência internacional:** nenhuma.
- **Retenção:** 5 anos legal (Art. 173 CTN).

### Op-07 — E-mail transacional
- **Finalidade:** recuperação de senha, notificações de cobrança, alertas operacionais.
- **Base legal:** execução de contrato.
- **Subprocessador:** Mailgun (Sinch) — EUA, quando `MAIL_MAILER=mailgun`; SMTP genérico configurável por cliente.
- **Transferência internacional:** EUA — DPA Mailgun + SCC aplica automático.
- **Retenção:** logs Mailgun 30d default.

## Subprocessadores (lista canônica)

| Empresa | Finalidade | País | Base de transferência | DPA | Última verificação |
|---|---|---|---|---|---|
| Hostinger International Ltd | Hospedagem app + MySQL produção | Brasil (datacenter SP) | N/A (nacional) | [Hostinger DPA](https://www.hostinger.com.br/dpa) | 2026-05-25 |
| Proxmox / CT 100 (on-premise oimpresso) | Daemon Baileys, Centrifugo, Meilisearch, Jaeger, Vaultwarden | Brasil (sede oimpresso) | N/A (controlador opera) | N/A | 2026-05-25 |
| Microsoft Corporation (Clarity) | Session replay + heatmap | EUA (Azure) | Microsoft Products DPA + SCC MIOL↔EUA | [Link DPA](https://www.microsoft.com/licensing/docs/view/Microsoft-Products-and-Services-Data-Protection-Addendum-DPA) | 2026-05-25 (Op-02) — 🟡 gap G6 documentar aceite em Vaultwarden |
| OpenAI L.L.C. | LLM provider default Jana | EUA | DPA OpenAI + SCC | [Link](https://openai.com/policies/data-processing-addendum/) | 🟡 a confirmar aceite formal |
| Anthropic PBC | LLM provider Claude | EUA | DPA Anthropic + SCC | [Link](https://www.anthropic.com/legal/dpa) | 🟡 a confirmar aceite formal |
| Google LLC (Gemini) | LLM imagens | EUA | DPA Google Cloud + SCC | [Link](https://cloud.google.com/terms/data-processing-addendum) | 🟡 a confirmar aceite formal |
| Cohere Inc. | LLM reranking | Canadá / EUA | DPA Cohere | [Link](https://cohere.com/dpa) | 🟡 a confirmar aceite formal |
| Groq Inc. | LLM inferência rápida | EUA | DPA Groq | 🟡 a confirmar | 🟡 a confirmar |
| GitHub Inc. (Microsoft) | Repositório código + Actions CI/CD | EUA | Microsoft Products DPA + SCC | [Link](https://github.com/customer-terms/github-data-protection-agreement) | 🟡 a confirmar aceite formal |
| Asaas (Sólides Tecnologia S.A.) | Gateway pagamento BR | Brasil | N/A (nacional) | Termos Asaas | 2026-05-25 |
| Mailgun (Sinch) | E-mail transacional (quando configurado) | EUA | Mailgun DPA + SCC | [Link](https://www.mailgun.com/legal/dpa/) | 🟡 a confirmar aceite formal |
| Meta Platforms (WhatsApp) | Servidores WhatsApp final | Global | Termos WhatsApp aceitos pelo titular final | [Link](https://www.whatsapp.com/legal/business-policy/) | 🟡 Tech Provider direto pendente |

## Direitos do titular (Art. 18 LGPD)

**Como exercer:**
- E-mail: `lgpd@oimpresso.com.br` (🟡 a configurar como alias DPO) ou `wagnerra@gmail.com`.
- Tool técnica interna: `php artisan jana:lgpd:esquecer-titular --cpf=XXX --business=N` ou tool MCP [`lgpd-esquecer-titular`](../../Modules/Jana/Mcp/Tools/LgpdEsquecerTitularTool.php).
- Pedido manual em texto livre via canal oficial — Wagner como Encarregado responde.

**Direitos garantidos:**
- Acesso (Art. 18, II) — exportação JSON via DSR
- Correção (III) — edição via UI sistema
- Eliminação (VI) — `DsrService::esquecerTitular` em modo `anonymize` (default) ou `hard`
- Portabilidade (V) — JSON estruturado de cadastros + histórico
- Oposição (§ VI) — opt-out cookie Clarity + bloqueio comunicação marketing
- Informação sobre compartilhamento (VII) — este documento responde

**Prazo legal:** 15 dias úteis (Art. 19, §3º).

**Audit trail:** cada exercício de direito gera entrada `lgpd.dsr.esquecimento.batch` em `jana.audit` com `audit_trail_id` UUID estável (append-only, NUNCA purgado pelo retention job).

## Incidente de segurança (Art. 48)

**Detecção:**
- Health-check diário `php artisan jana:health-check` 06:00 BRT — 5 checks SQL (multi_tenant_isolation, brief_uptime, custo_brain_b, pii_leak, profile_distiller_drift).
- Alertas `storage/logs/laravel.log` entries `ALERT`.
- Sentry / OTel exceptions tracked.

**Contenção:**
- Isolar tenant afetado (revogar tokens, forçar logout, ativar maintenance mode módulo).
- Snapshot Hostinger antes de mitigação destrutiva.
- Rollback PR via `gh pr revert` quando incidente origem código.

**Comunicação:**
- **ANPD:** 2 dias úteis após confirmação de incidente material (Art. 48 §1º) via canal oficial ANPD.
- **Titular afetado:** comunicação direta com escopo, dados expostos, medidas tomadas, contato Encarregado.
- **Cliente PME (controlador)** quando oimpresso for operador: notificar antes de comunicar titular final.

**Registro:** abrir entry em `memory/sessions/YYYY-MM-DD-incidente-NNN.md` + audit trail `lgpd.incidente` + ADR de remediação se causa estrutural.

## Histórico de revisões

| Data | Alteração | Autor |
|---|---|---|
| 2026-05-25 | Criação inicial. Catalogadas Op-01 a Op-07 + 12 subprocessadores. Op-02 Clarity registrada como subprocessador EUA com SCC (gap G1 [ADR 0191](../decisions/0191-microsoft-clarity-session-replay-lgpd.md)). Gaps remanescentes G2-G6 identificados em [`memory/sessions/2026-05-25-arte-clarity-lgpd-decision.md`](../sessions/2026-05-25-arte-clarity-lgpd-decision.md). | Wagner |

---

## Anexos

- [Dossier estado-da-arte Clarity LGPD 2026-05-25](../sessions/2026-05-25-arte-clarity-lgpd-decision.md) — fonte do gap G1
- [ADR 0191](../decisions/0191-microsoft-clarity-session-replay-lgpd.md) — decisão técnica Clarity
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 IRREVOGÁVEL
- [retention.php canônico](../../Modules/Jana/Config/retention.php) — TTL por entidade
- [DsrService](../../Modules/Jana/Services/Lgpd/DsrService.php) — implementação Art. 18 §VI
- [Guia ANPD Cookies (PDF)](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-cookies-e-protecao-de-dados-pessoais.pdf)
- [Microsoft Products DPA](https://www.microsoft.com/licensing/docs/view/Microsoft-Products-and-Services-Data-Protection-Addendum-DPA)
- [CNIL session replay draft 2024](https://ppc.land/frances-cnil-puts-session-replay-tools-under-the-privacy-microscope/)
