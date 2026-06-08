# Estado-da-arte — Microsoft Clarity sob LGPD/GDPR (decisão GO/NO-GO/GO-CONDICIONAL)

**Data:** 2026-05-25 · **Agent:** `estado-da-arte` · **Sessão:** `frosty-greider-83ab2f`
**Pergunta:** Posso instalar Microsoft Clarity gratuitamente no oimpresso (SaaS B2B Laravel+Inertia com PII visível em telas) sem violar LGPD? Quais salvaguardas técnicas + contratuais antes de mergear?
**TL;DR:** **GO-CONDICIONAL → JÁ EXECUTADO HOJE.** ADR 0191 aceita + PRs #1473 (consent banner) e #1480 (Clarity) merged na mesma sessão. Dossier serve agora como **auditoria pós-merge** confirmando que o estado-da-arte 2026 foi respeitado. **3 gaps menores remanescentes** (route-blocklist explícito, retenção contratual <30d, registro Art. 37 LGPD).

---

## 1. Estado-da-arte 2026 (pesquisa limpa)

| Player | Como resolve session-replay sob privacidade 2026 | Por que é referência |
|---|---|---|
| **Microsoft Clarity** ([docs](https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-masking), [privacy](https://clarity.microsoft.com/privacy)) | Mask-all default em inputs (não custom). 3 modos (Strict/Balanced/Relaxed) + API `data-clarity-mask/unmask`. Out/2025 enforcement: exige `consent` signal em EEA/UK/CH ([CookieHub](https://www.cookiehub.com/blog/dont-lose-your-clarity-data)). Retenção: 30d gravações + 13m heatmaps. DPA via [Microsoft Products DPA](https://www.microsoft.com/licensing/docs/view/Microsoft-Products-and-Services-Data-Protection-Addendum-DPA) (MIOL Ireland + SCCs). Free unlimited. |
| **Hotjar** (Contentsquare Group jul/2025) | `data-hj-suppress`, `data-hj-allow`. SOC2 + GDPR/CCPA/LGPD declarados ([cookie-script](https://cookie-script.com/guides/microsoft-clarity-session-replay-gdpr)). Tier free pequeno; pago escala rápido. |
| **Smartlook** | Mask via `data-private` + auto-redact. LGPD/GDPR/CCPA declared. Pago. |
| **PostHog (self-hosted)** | Open-source completo; dado fica no servidor do cliente. EU hosting cloud opcional. **Sem deploys self-hosted novos suportados em 2025** ([PostHog blog](https://posthog.com/blog/best-open-source-session-replay-tools)) — migration path para PostHog Cloud. |
| **OpenReplay (self-hosted)** | OSS verdadeiro, SOC2 Type 2 + GDPR, masking server-side. **Melhor opção self-hosted ativa em 2026.** Custo: ~4-8h setup + ops contínuo. |

**Sinais regulatórios duros 2024-2026 (relevantes ao Brasil):**

- **CNIL França** abriu draft de recomendação dez/2024 mirando Clarity + Hotjar nominalmente. Exige: (a) consent prévio explícito, (b) masking estruturado, (c) limites de retenção, (d) deleção individual de sessão, (e) joint-controller contratual ([ppc.land](https://ppc.land/frances-cnil-puts-session-replay-tools-under-the-privacy-microscope/), [Fox Rothschild](https://dataprivacy.foxrothschild.com/2025/04/articles/european-union/gdpr/session-replay-enforcement-but-in-french/)). Consulta pública aberta fev/2026.
- **ANPD Brasil** publicou *Guia Orientativo Cookies* out/2022, atualizado jan/2025 ([gov.br](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-cookies-e-protecao-de-dados-pessoais.pdf)): legítimo interesse OK pra analytics anônimo de audiência; **consentimento obrigatório pra cookies não-necessários e tracking comportamental** (session replay cai aqui). Lei 15.352/2026 deu poder fiscalizatório real à ANPD ([Conjur](https://www.conjur.com.br/2026-abr-10/da-norma-a-fiscalizacao-como-a-anpd-aplica-alguns-dos-principios-da-lgpd/)).
- **Microsoft Clarity** atualizou consent-mode out/2025 — sem signal `consent=granted`, dado não é processado em mercados EEA/UK/CH ([CookieHub](https://www.cookiehub.com/blog/dont-lose-your-clarity-data)). Brasil ainda não é mercado restrito pelo Clarity, mas a tendência é simétrica.
- **Microsoft answer oficial** ([Q&A 5652908](https://learn.microsoft.com/en-us/answers/questions/5652908/use-microsoft-clarity-for-a-customer-portal-with-s)): Clarity em portal com PII é **possível** desde que (1) admin configure content blocking explícito, (2) consent prévio EU, (3) review jurídico, (4) avaliar se 30d de retenção é compatível com sensibilidade.

---

## 2. Comparativo estado-da-arte × oimpresso

| Dimensão | Estado-da-arte 2026 | Oimpresso hoje (pós-PRs #1473 #1480) | Distância |
|---|---|---|---|
| **Base legal LGPD** | Consent opt-in explícito pra session replay (não-essencial) | Cookie `oimpresso_consent_v1` + banner não-modal Linear/Notion-style; `analytics_accepted=false` default; snippet só carrega após opt-in ([`ConsentBanner.tsx`](D:/oimpresso.com/resources/js/Components/shared/ConsentBanner.tsx)) | **Curta** — já compliant |
| **Mask default** | Mask-all default + unmask seletivo (LGPD-safe) | `clarity('set','mask_strategy','all')` no [`clarity.blade.php:43`](D:/oimpresso.com/resources/views/layouts/partials/clarity.blade.php); `data-clarity-unmask` só em `AppShellV2` topbar + `PageHeaderPrimary` (label de verbo, não PII) | **Curta** — pattern correto |
| **Multi-tenant isolation** | Custom tag por tenant; nunca confiar em client | `clarity('set','business_id', auth()->user()->business_id)` server-side via Blade. Dashboard nativo filtra por tag. ADR 0093 preservada | **Curta** — pattern canônico |
| **Filtro de operadores internos** | Excluir admins/QA do dataset | Guard `! in_array(user_type, ['superadmin','user_oimpresso'])` no [`HandleInertiaRequests:193`](D:/oimpresso.com/app/Http/Middleware/HandleInertiaRequests.php) + Blade guard duplicado | **Curta** |
| **Default OFF em prod** | Feature flag rollout gradual | `CLARITY_ENABLED=false` default ([config/services.php:148](D:/oimpresso.com/config/services.php)); Wagner ativa manual após criar projeto | **Curta** |
| **Route blocklist (telas com PII)** | CNIL draft exige "structured masking framework" → bloqueio explícito de telas críticas (CPF/CNPJ/valor) | **AUSENTE.** Snippet carrega em TODA tela autenticada Inertia + Blade. Confia 100% em mask-all default. `/sells/*`, `/contatos/*`, `/financeiro/*`, `/fiscal/*` (NFe) NÃO têm gate de rota | **Média** — defesa em profundidade falha |
| **Retenção contratual** | Configurar mínimo viável (CNIL: "retention limits") | 30d default Microsoft (não configurável free tier). Sessões "favoritadas" sobem pra 13m | **Média** — aceito por enquanto, mas precisa estar no Registro Art. 37 |
| **DPA / SCC / transferência internacional** | DPA específico assinado + SCC Microsoft Ireland↔EUA documentado | Microsoft Products DPA aplica automaticamente (não exige assinatura separada). MIOL+SCC pra UE; pra **Brasil, ANPD ainda não emitiu lista de países adequados** → operação cai em Art. 33 §II (cláusulas contratuais padrão) | **Média** — válido legalmente, mas não documentado no oimpresso |
| **Registro de Operações (Art. 37 LGPD)** | Mapa de tratamento de dados público/auditável | **AUSENTE.** Não há `memory/reference/lgpd-mapa-tratamento.md` listando Clarity como subprocessador, finalidade, base legal, retenção, transferência internacional | **Média** — gap formal, exigível em fiscalização ANPD |
| **Deleção individual de sessão** (CNIL draft) | UI/API pra excluir sessão específica a pedido do titular | Clarity oferece via dashboard nativo (admin manual). Oimpresso **não tem fluxo formal** — DSR esquecer-titular ([`DsrService.php`](D:/oimpresso.com/Modules/Jana/Services/Lgpd/DsrService.php)) cobre dados internos mas não toca Clarity | **Longa** — gap process, não código |
| **Snippet em layouts públicos** (login/landing) | Não carregar pre-auth | Incluído só em `inertia.blade.php` + `app.blade.php` (autenticados). `auth2.blade.php` e `home.blade.php` ficam fora | **Curta** |

**Onde oimpresso supera mediana de mercado:** mask-all default + guard chain de 5 condições server-side (env+project_id+auth+!internal+consent). Maioria de implementações Clarity em SaaS BR confia em mask Balanced + banner cookie genérico. Oimpresso fez o "inverter ônus" (qualquer form novo nasce mascarado).

**Onde está abaixo:** route-blocklist explícito + Registro Art. 37 + processo de deleção individual.

---

## 3. Gaps remanescentes (impacto × esforço IA-pair)

| Gap | Impacto | Esforço | Pré-req? |
|---|---|---|---|
| **G1.** Adicionar Clarity ao Registro de Operações Art. 37 LGPD (`memory/reference/lgpd-mapa-tratamento.md` novo) — listar finalidade, base legal (consent), retenção (30d/13m), subprocessador (Microsoft Ireland + SCC EUA), titulares afetados, mitigações | **Alto** (exigível em fiscalização ANPD; baixo custo de inclusão) | ~20-30min IA-pair | Não |
| **G2.** Route-blocklist explícito pra telas com PII concentrada (`/sells/*`, `/contatos/*`, `/financeiro/boletos/*`, `/fiscal/*`) — adicionar config `services.clarity.excluded_routes` + check no `HandleInertiaRequests::clarityShare()` e no partial Blade | **Médio-alto** (defesa em profundidade; mask-all já cobre, mas CNIL draft fev/2026 vai exigir formalmente) | ~45min IA-pair (config + middleware check + test) | Não |
| **G3.** Documentar processo de deleção de sessão Clarity em pedido DSR — estender `Modules/Jana/Mcp/Tools/LgpdEsquecerTitularTool.php` com instrução manual ("após anonimização DB, ir em clarity.microsoft.com → filter business_id=X → delete sessions") OU integrar Clarity Data Export API | **Médio** (LGPD Art. 18 §VI exige <15 dias; hoje só cobre DB interno) | ~30min documentação manual / ~2-3h integração API | G1 (precisa estar no mapa primeiro) |
| **G4.** Validar empiricamente que mask-all está mascarando CPF/CNPJ em telas reais (smoke browser MCP staging biz=4) — abrir 1 sessão em `/contatos/X/edit`, conferir no dashboard Clarity que campo CPF aparece como `▫▫▫.▫▫▫.▫▫▫-▫▫` | **Alto** (verifica que o pattern funciona na prática; só código sem smoke é fé) | ~15min smoke manual após Wagner habilitar `CLARITY_ENABLED=true` em prod | Wagner habilitar Clarity primeiro |
| **G5.** Mask explícito via `data-clarity-mask="True"` em formulários com PII visível como reforço (Crm/Cliente, Sells/CustomerSearch, Compras/Fornecedor, RH/Funcionario, Fiscal/NFe) — defense-in-depth sobre mask-all global | **Baixo-médio** (mask-all já cobre inputs por default Microsoft; reforço cobre texto rendered fora de input, ex: spans com `{cliente.cpf}`) | ~1-2h IA-pair (5+ telas × 1-2 atributos cada) | Não |
| **G6.** Aceite de DPA Microsoft documentado em Vaultwarden (link + screenshot + data) — não exige assinatura mas exige prova de aceitação | **Baixo** (DPA aplica automaticamente; documentação é defesa em auditoria) | ~10min Wagner manual | Não |

---

## Recomendação final

**GO-CONDICIONAL — implementação canon já aceita e merged.** Estado-da-arte 2026 (Clarity + consent banner + mask-all + multi-tenant tag + filtro interno + default off) foi atingido na mesma sessão `frosty-greider-83ab2f` via [ADR 0191](D:/oimpresso.com/memory/decisions/0191-microsoft-clarity-session-replay-lgpd.md) + PR #1473 + PR #1480.

**Comece por G1 — Registro Art. 37 LGPD.** Alto-impacto, baixo-esforço (~20-30min), zero pré-req, e é a única peça **legalmente exigível** que ainda falta. G2 (route-blocklist) é a 2ª prioridade e fecha a defesa em profundidade antes da CNIL/ANPD endurecerem fev-jun/2026.

**Próxima ação hoje:** criar `memory/reference/lgpd-mapa-tratamento.md` (template ANPD: agente de tratamento, finalidade, base legal, dados tratados, titulares, compartilhamento/subprocessadores, retenção, transferência internacional, medidas de segurança) com Clarity como primeira entrada. Depois, ativar `CLARITY_ENABLED=true` em prod Hostinger + smoke G4 com biz=4 Larissa em 7d.

**Plano alternativo (NO-GO hipotético):** se Larissa reportar invasão de privacidade ou ANPD endurecer regulação, rollback é trivial — `CLARITY_ENABLED=false` em `.env` (zero-impacto) + manter consent banner (serve a outras features). Migração pra OpenReplay self-hosted seria fallback (~4-8h setup + ops contínuo CT 100; ADR 0062 separação runtime preservada).
