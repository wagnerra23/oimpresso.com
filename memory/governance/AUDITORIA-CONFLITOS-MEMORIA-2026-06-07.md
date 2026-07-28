---
id: governance-auditoria-conflitos-memoria-2026-06-07
---

# Auditoria de Conflitos da Memória — 2026-06-07

> Disparada por Wagner: "revise todas as memórias conflitantes, mesmo que consuma tokens — isso é um erro que pode custar caro. O que eu tenho que me preocupar antes de iniciar errado?"
> Varredura do corpus canônico (265 ADRs · 114 reference · raiz memory/ · auto-mem legada) contra `main` @ 561ff8be3.
> Princípio: sessions/handoffs são snapshots históricos (envelhecem por design) — auditados só a camada AUTORITATIVA.

---

## 🔴 P0 — INCIDENTE DE SEGURANÇA (agir antes de qualquer outra coisa)

**10 arquivos em `memory/claude/` contêm SEGREDOS EM CLARO commitados no git** (visíveis ao time via MCP). O canon novo (`memory/reference/`) já redactou tudo pra ponteiro Vaultwarden — mas o legado nunca foi apagado.

| Arquivo | Tipo de segredo (valor NÃO reproduzido aqui) |
|---|---|
| `reference_proxmox_credenciais.md` | senha root Proxmox · token API Proxmox · senha root CT 100 · Reverb KEY/SECRET · Meilisearch master key · Portainer admin · senha Vaultwarden |
| `reference_vaultwarden_credenciais.md` | ADMIN_TOKEN do cofre + senha conta Wagner |
| `reference_hostinger_dns_api.md` | token API DNS Hostinger (produção) |
| `reference_proxmox_acesso_2026_04_29.md` | senha CT 100 + Proxmox root |
| `reference_central_voip_issabel.md` / `reference_central_voip_inventario.md` | senha PBX admin |
| `reference_painel_kinghost.md` | senha painel KingHost |
| `reference_hostinger_ssh_credenciais.md` | host:port:user SSH prod |
| `reference_router_empresa_dhcp.md` | mapa rede + credenciais pendentes |
| `reference_ssh_hardening_ct100_2026_04_30.md` | IP Tailscale + chaves SSH |

**Ação (per [feedback-nunca-publicar-credenciais]):** tratar como COMPROMETIDOS. (1) ROTACIONAR os segredos nos sistemas; (2) apagar os arquivos do HEAD; (3) avaliar purge de histórico (git-filter-repo) — decisão Wagner pois exige force-push. Rotação é o fix real; apagar arquivo é higiene.

---

## 🟠 P1 — Duas camadas de memória legada que CONTRADIZEM o canon (fonte de "começar errado")

### (a) `memory/claude/` — auto-mem legada (ADR 0061 mandou migrar, nunca limpou) — 75 arquivos
- **42 DUP-STALE** (cópia velha do que já existe em `memory/reference/`)
- **16 OBSOLETO** (roadmaps/estados de abril/2026 que contradizem o atual — ex: `project_roadmap_milestones.md` trata Laravel 13 como "alvo futuro" quando já é a stack)
- **10 CREDENCIAL** (ver P0)
- **7 ÚNICO-NÃO-MIGRADO** (info técnica válida sem equivalente no canon — migrar ANTES de apagar): `reference_db_schema.md`, `reference_audit_modulos_datacontroller.md`, `reference_datatables_locale.md`, `reference_diff_3_7_vs_6_7_officeimpresso.md`, `reference_wp_ajuda_fix.md`, `feedback_topnav_i18n_pattern.md`, `project_session_business_model.md`

### (b) `memory/NN-*.md` raiz — estrutura pré-Constituição v2
| Arquivo | Problema (CONTRADIZ canon atual) |
|---|---|
| `02-technical-stack.md` | stack IA errada: "Vizra ADK + OpenAI futuro" — descartado (ADR 0035/0048 = `laravel/ai`) |
| `05-preferences.md` | "Decida, não pergunte" — contradiz `wagner-request-refiner` (regra-mestre UI v2: pergunta antes em pedido vago) |
| `04-conventions.md` | "Laravel 10" + branch `develop` (nunca existiu; canon é `main`-protected) |
| `00/01/03-*` | persona/arquitetura era-PontoWr2 (auto-marcados STALE) |
| `07-roadmap.md` | tasks em markdown — ADR 0070 proíbe (usar MCP) |

**Seguros pra arquivar:** 00,01,03,07, OPUS-MISSION-BRIEF, COMPARATIVO_TELAS_BLADE_VS_REACT, REQUISITOS_FUNCIONAIS_PONTO, COMO_PEDIR_NOVA_TELA_OU_MODULO.
**Contradizem ativamente (prioridade):** 02, 05, 04.
**Manter (canon vivo):** why/what/how-oimpresso, proibicoes, regras-time, NORTE-ROI, INDEX, INDEX_TEMATICO, LICOES_CC, migrations, officeimpresso-spec, how-bridge-cloud-local, _INDEX-SECRETS.

## 🟡 P2 — `memory/reference/` com fatos stale (contradição interna)
| Fato | Stale diz | Verdade canônica |
|---|---|---|
| Onde roda o MCP server | `sandbox-hostnames.md`: "Hostinger subdomain" | **CT 100** (ADR 0062 proíbe MCP no Hostinger) — PERIGOSO |
| WhatsApp | `whatsapp-daemon-ct100.md`: "Baileys 6.7.18 LIVE" | Baileys SAIU (ADR 0202, 2026-05-27) — arquivo inteiro stale |
| Broadcaster CT 100 | `infra-proxmox-ct100.md` frontmatter + `infra-rede-empresa.md`: "Reverb" | **Centrifugo** (ADR 0058) |

## 🟡 P3 — Colisões de número de ADR (renumerar é PROIBIDO por ADR 0180/0094 — corrigir é só registrar/referenciar por slug)
- 13 números colididos; a maioria já registrada em `_INDEX-LIFECYCLE.md`.
- **0236 e 0246 NÃO estão no registro** → `AdrNumberCollisionTest` falharia. Registrar.
- **3 slugs-fantasma de ADR 0180** referenciados em 5 docs mas não existem no disco (links quebrados): `0180-pageheader-canon-3-zonas`, `0180-sidebar-v3-href-direto-ghosts-pageheader`, `0180-pageheader-canon-v3-href-direto-sem-dropdown`.
- **1 referência errada**: `.claude/agents/screen-qa-specialist.md:32` linka `0101-sistema-charter...` mas o texto (biz=1) é do `0101-tests-business-id-1-nunca-cliente`.
- Convenção a reforçar (ADR 0180): **sempre citar ADR por slug, nunca só por número**.

---

## Plano de remediação proposto (Wagner decide ordem/escopo)
1. **P0 segurança** — rotacionar + purgar credenciais (decisão Wagner; envolve force-push se purge de histórico).
2. **P1(a)** — migrar os 7 ÚNICOS → apagar `memory/claude/` inteiro.
3. **P1(b)** — apagar/arquivar os NN-* fósseis; corrigir 02/04/05 ou arquivá-los.
4. **P2** — corrigir 3 fatos stale em reference/ (1 commit).
5. **P3** — registrar 0236/0246; consertar 4 links de ADR.
6. **Prevenção** — a colisão de ADR e a auto-mem stale voltam sozinhas; reforçar gate `AdrNumberCollisionTest` + um gate que proíba secrets-em-claro em `memory/`.
