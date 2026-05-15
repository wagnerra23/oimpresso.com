# Proposta: Coleta sistemática de perfis Cliente + Funcionário como Conhecimento Tier A

**Status:** proposed (Wagner aprovou conceito 2026-05-14 noite · executando F1+F2+F3)
**Origem:** Wagner reagiu ao perfil Martinho atualizado: *"isso tem que ter regra eu nem preciso avisar que tem que ser assim, colete de cada cliente e cada funcionário. isso é ouro"*
**Alinhamento ADR 0105:** sinal Wagner = repetir manualmente o que deveria ser automático é dor catalogada · automatizar via skill Tier A.
**Princípios duros aplicados:** ADR 0094 §1 Context as a product · §3 Charter > Spec · §5 SoC brutal · §7 Transparência.

---

## 1) Contexto

### Problema (estado atual antes desta ADR)

Conhecimento sobre clientes e seus funcionários está disperso e inconsistente:

- `memory/reference/cliente-rotalivre.md` — perfil único · estrutura ad-hoc
- `memory/reference/cliente-martinho.md` — criado 14/maio · estrutura DIFERENTE de ROTA LIVRE
- `memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md` — perfil legacy histórico · estrutura DIFERENTE de ambos
- `memory/reference/clientes-ativos.md` — lista sem detalhe
- Funcionários (Larissa · Jair · Kamila · Lara · Dani · Rodrigo · Eduardo) **não têm perfil individual**
- Sem trigger automático — Wagner precisa LEMBRAR de pedir registro toda vez (dor catalogada 14/maio)

### O que se perde

- Sensibilidades operacionais (Larissa decorou shift +3h `format_date` · Lara não-técnica · Dani persona financeiro) ficam só na cabeça do Wagner
- Preferências UX (monitor 1280px Larissa · co-design presencial Lara) viram tribal knowledge
- Histórico de marcos (Jair endossou 14/maio · Highsoft pausado) some sem registro estruturado
- Cross-link cliente↔funcionário↔módulo↔decisão fica fragmentado
- Felipe/Maiara/Eliana[E] não enxergam contexto sem perguntar Wagner

### O que se ganha com pattern

- Onboarding novo dev: lê `memory/reference/clientes/<slug>.md` + `funcionarios/<cliente>/` e tem 80% do contexto
- Cliente novo Vargas/Gold/Extreme entra com template preenchido em ~30min (não 2h de research disperso)
- Champions canary identificados explicitamente (não em conversa WhatsApp perdida)
- Audit trail: quando Jair endossou? Em que sessão? Vinculado a qual decisão?
- LGPD-clean por design (PII real em Vaultwarden · git só perfil operacional)

---

## 2) Decisão proposta

Adotar **Perfis Cliente/Funcionário como Conhecimento Tier A** com 4 componentes obrigatórios:

1. **Estrutura pasta canônica** `memory/reference/{clientes,funcionarios}/<slug>.md`
2. **Frontmatter + sections padronizadas** (template mandatório)
3. **Skill Tier A `cliente-funcionario-collector`** auto-trigger sem Wagner pedir
4. **Governança LGPD** PII real Vaultwarden · git operacional só

---

## 3) Estrutura pasta canônica

```
memory/reference/
├── clientes/                              ← NOVO (consolidado)
│   ├── _INDEX.md                          (lista navegável · ordenada por status)
│   ├── _TEMPLATE.md                       (skeleton mandatório · ler antes de criar)
│   ├── rotalivre.md                       (move de cliente-rotalivre.md · biz=4)
│   ├── martinho-cacambas.md               (move de cliente-martinho.md · biz=164)
│   ├── wagner-wr2.md                      (próximo · biz=1 — caso especial dono)
│   └── <slug>.md                          (cada cliente que entra no funil qualificado+)
│
└── funcionarios/                          ← NOVO
    ├── _INDEX.md
    ├── _TEMPLATE.md
    ├── rotalivre/
    │   └── larissa.md                     (dona/operadora ROTA LIVRE)
    └── martinho-cacambas/
        ├── jair.md                        (dono majoritário · endossou)
        ├── martinho.md                    (sócio · dá nome empresa)
        ├── kamila.md                      (filha · operação Delphi · pausou Highsoft)
        ├── lara.md                        (filha · estoque · champion oimpresso)
        ├── dani.md                        (financeiro · champion oimpresso)
        ├── rodrigo.md                     (vendedor)
        └── eduardo.md                     (vendedor)
```

### Convenção de slugs

- **Cliente:** `<slug-kebab-curto>` — `rotalivre` (não `rota-livre`) · `martinho-cacambas` · `vargas-recapagem` · `gold-comunicacao`
- **Funcionário:** `<first-name-lowercase>` único por cliente — `larissa` · `jair` · `kamila` · `lara` · `dani`
- Conflito de homônimos: `lara2`, `lara-financeiro` etc (raro · documentar caso a caso)

---

## 4) Frontmatter padronizado

### Cliente

```yaml
---
slug: martinho-cacambas
business_id: 164
razao_social: MARTINHO CAÇAMBAS LTDA
status: piloto-ativo            # prospect | qualificado | piloto-ativo | producao | churned | feature-wish
vertical_principal: oficina-auto-locacao-cacamba
sub_vertical: caçamba-avulsa-entulho-obra
cnae: 4581-4/00
cidade_uf: <CIDADE>/SC
distancia_km_wagner: 20
inicio_relacionamento: 2026-05-13
canary_inicio: 2026-05-19
faturamento_anual_brl: 6281171.55
funcionarios_total: 20
champions_oimpresso:
  - slug: lara
    role: estoque
  - slug: dani
    role: financeiro
decisor_principal: jair
sistema_anterior: Office Comercial Delphi (WR Sistemas legacy)
concorrentes_avaliados_pausados:
  - Highsoft
pricing_mensal_brl: 830
arquitetura_migracao: dual-sync-delphi-master-oimpresso-viewer
perfil_legacy: ../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md
ultima_atualizacao: 2026-05-14
proxima_revisao: 2026-05-19
---
```

### Funcionário

```yaml
---
slug: lara
cliente_slug: martinho-cacambas
first_name: Lara                  # nome curto · sem sobrenome em git
relacao: filha do Jair (dono majoritário)
role_operacional: responsável estoque
user_id_oimpresso: null           # criar pré-canary
papel_canary: champion-oimpresso
acesso_sistemas:
  - sistema: Office Comercial Delphi (WR Sistemas legacy)
    role: operadora-estoque
  - sistema: oimpresso
    role: planned (Admin#164 biz=164)
preferencias_ux:
  - persona_nao_tecnica
  - monitor_1280px (assumido por similaridade ROTA LIVRE)
sensibilidades:
  - pede_co_design_presencial
  - prefere_ver_wagner_desenvolvendo
pii_vault_ref: vault://martinho-cacambas/lara         # CPF/email/telefone real
ultima_atualizacao: 2026-05-14
---
```

---

## 5) Sections padronizadas

### Cliente (10 seções obrigatórias)

1. **Identificação** — razão social · CNPJ · localização · timezone · cadastro
2. **Stakeholders** — decisor principal + champions + operadores (cross-link funcionários/)
3. **Saúde financeira** — snapshot atualizado (receita 12m · despesa · inadimplência · ticket)
4. **Sistema atual** — Delphi/Highsoft/SaaS concorrente · pain-points
5. **Arquitetura migração** — dual-sync / canary / cutover · cronograma
6. **Pricing + comercial** — mensal · upsells · contratos · trial
7. **Sensibilidades operacionais — NÃO MEXER SEM AVISAR** (caixa-preta crítica)
8. **Estado prod oimpresso** — business_id · rows importadas · features ligadas · features escondidas (sidebar config)
9. **Histórico de marcos** — datado · cross-link sessions/ + handoffs/
10. **Refs** — funcionários/ · ADRs · sessions · research/ legacy · RUNBOOKs

### Funcionário (6 seções obrigatórias)

1. **Papel atual** — role operacional · cargo · responsabilidade primária
2. **Acesso a sistemas** — Delphi/oimpresso/etc · user_id por sistema · role
3. **Preferências UX** — monitor · persona (técnica/não) · atalhos · idioma
4. **Sensibilidades** — pain-points reportados · gostos · estilos de comunicação
5. **Histórico de interações** — datado · reuniões · feedbacks · decisões
6. **Refs** — cliente_slug · sessões onde aparece · ADRs envolvidas

---

## 6) Skill Tier A `cliente-funcionario-collector`

Skill auto-trigger always-on que detecta menções a cliente/funcionário e força registro.

### Triggers (matchers de description)

Skill ativa quando Claude detecta na conversa OU em Edit/Write paths:

| Trigger | Ação |
|---|---|
| Menção `business_id=N` onde N ≠ {1 Wagner, 4 ROTA LIVRE, 99 sandbox} | Verificar `clientes/<slug>.md` existe · senão criar stub + perguntar Wagner |
| Menção nome próprio + role operacional (regex: capitalized + verbo "cuida"/"opera"/"vende"/"compra"/"trabalha em") | Verificar `funcionarios/<cliente>/<slug>.md` · senão criar stub |
| Decisão arquitetural envolvendo cliente (ADR new · proposal new) | Atualizar histórico no perfil cliente + adicionar ref |
| Incidente/marco datável (ex "Jair endossou" · "Larissa reclamou") | Adicionar entrada `## Histórico` cliente + funcionário envolvido |
| Status mudança detectada (qualificado→piloto-ativo · piloto→producao) | Update frontmatter + histórico cliente |
| Wagner usa palavra-trigger "salve no perfil" / "anota no cliente X" / "ouro" | Force update imediato + perguntar confirmação só se ambíguo |

### Anti-patterns proibidos

- ❌ Criar perfil de cliente prospect SEM signal real (LGPD + ruído · ver ADR 0105)
- ❌ Duplicar info entre `_INDEX.md` e `<slug>.md` (single source of truth)
- ❌ Inflar perfil com features wish (não realizadas) — só fatos
- ❌ PII real (CPF · email pessoal · telefone) em git canônico — vai pra Vaultwarden
- ❌ Esquecer cross-link com perfil legacy `research/clientes-legacy-officeimpresso/`

---

## 7) Governança LGPD

### Em git canônico (visível ao time + MCP server)

✅ Permitido:
- Razão social (público registro)
- CNPJ (público registro)
- Endereço comercial (público registro)
- `first_name` funcionário (nome curto sem sobrenome)
- Role operacional
- Preferências UX agregadas
- Sensibilidades (sem citar diretamente nome real)

### Em Vaultwarden (`vault.oimpresso.com`)

🔐 Obrigatório:
- CPF / RG funcionário
- Email pessoal (não corporativo)
- Telefone pessoal (não comercial)
- WhatsApp pessoal
- Endereço residencial
- Senhas iniciais oimpresso (até reset pelo próprio)

### Cross-link

```yaml
pii_vault_ref: vault://<cliente-slug>/<funcionario-slug>
```

Skill `cliente-funcionario-collector` valida que `pii_vault_ref` está populado quando funcionário criado · alerta se ausente.

---

## 8) `_INDEX.md` navegável

### `clientes/_INDEX.md`

```markdown
# Índice de Clientes (memory/reference/clientes/)

## 🟢 Piloto ativo
| Slug | Razão social | biz | Vertical | Champions | Início canary |
|---|---|---:|---|---|---|
| [martinho-cacambas](martinho-cacambas.md) | MARTINHO CAÇAMBAS LTDA | 164 | OficinaAuto | lara · dani | 2026-05-19 |

## ✅ Produção
| Slug | Razão social | biz | Vertical | Champions | Volume |
|---|---|---:|---|---|---|
| [rotalivre](rotalivre.md) | LARISSA COMÉRCIO ARTIGOS VESTUÁRIO LTDA-ME | 4 | Vestuário | larissa | 17k+ vendas |

## ⏸️ Qualificado (aguardando sinal)
| Slug | Razão social | biz | Vertical | Sinal |
|---|---|---:|---|---|
| [vargas-recapagem](vargas-recapagem.md) | (PENDENTE Wagner) | TBD | OficinaAuto recapagem | 1.064 veículos multi-placa |

## 🔒 Backlog feature-wish
(zero por ora — ADR 0105 sinal qualificado obrigatório)

## ❌ Churned
(zero)
```

### `funcionarios/_INDEX.md`

```markdown
# Índice de Funcionários (memory/reference/funcionarios/)

## Por cliente

### martinho-cacambas (biz=164)
| Slug | Nome | Role | Papel canary | Sistema atual |
|---|---|---|---|---|
| [jair](martinho-cacambas/jair.md) | Jair | Dono majoritário | Decisor (endossou 14/maio) | Delphi |
| [kamila](martinho-cacambas/kamila.md) | Kamila | Filha · operação | Pausou Highsoft | Delphi |
| [lara](martinho-cacambas/lara.md) | Lara | Filha · estoque | Champion oimpresso | Delphi → oimpresso 19/maio |
| [dani](martinho-cacambas/dani.md) | Dani / DANIELLI | Financeiro | Champion oimpresso | Delphi → oimpresso 19/maio |
| [rodrigo](martinho-cacambas/rodrigo.md) | Rodrigo da Silva | Vendedor | Continua Delphi | Delphi |
| [eduardo](martinho-cacambas/eduardo.md) | Eduardo | Vendedor | Continua Delphi | Delphi |

### rotalivre (biz=4)
| Slug | Nome | Role | Papel | Sistema atual |
|---|---|---|---|---|
| [larissa](rotalivre/larissa.md) | Larissa Fernandes | Dona/operadora | Cliente piloto vivo | oimpresso (99% volume) |
```

---

## 9) Triggers de revisão proativa

Skill `cliente-funcionario-collector` agenda revisões automáticas:

| Trigger | Frequência | Ação |
|---|---|---|
| Cliente status=piloto-ativo | 7 dias | Recordar Wagner pra atualizar histórico canary |
| Cliente status=qualificado | 30 dias | Recordar pra mover pra piloto-ativo ou descartar (ADR 0105) |
| Funcionário sem `user_id_oimpresso` E cliente status=piloto-ativo | 1 dia | Alertar Wagner criar user |
| Cliente sem `proxima_revisao` setada | imediato | Forçar Wagner setar data |
| Funcionário sem `pii_vault_ref` E cliente !=Wagner | imediato | Alertar criar Vaultwarden entry |

---

## 10) Migration plan (F2)

### Move + reorganiza

| De | Para |
|---|---|
| `memory/reference/cliente-rotalivre.md` | `memory/reference/clientes/rotalivre.md` |
| `memory/reference/cliente-martinho.md` | `memory/reference/clientes/martinho-cacambas.md` |
| (NOVO) | `memory/reference/clientes/_INDEX.md` |
| (NOVO) | `memory/reference/clientes/_TEMPLATE.md` |
| (NOVO) | `memory/reference/funcionarios/_INDEX.md` |
| (NOVO) | `memory/reference/funcionarios/_TEMPLATE.md` |
| (NOVO) | `memory/reference/funcionarios/rotalivre/larissa.md` |
| (NOVO) | `memory/reference/funcionarios/martinho-cacambas/{jair,martinho,kamila,lara,dani,rodrigo,eduardo}.md` |

### Compatibilidade backward

- Manter stub em `memory/reference/cliente-rotalivre.md` e `cliente-martinho.md` redirecionando ao novo path (1 linha · "MOVED: ver `clientes/<slug>.md`") por 90 dias
- Após 90 dias sem alerta de quebra, deletar stubs
- `dominios-verticais-oimpresso.md` já cross-linkado · não precisa mexer

### Não-objetivo F2

- NÃO mover `memory/research/clientes-legacy-officeimpresso/<N>-<cliente>/` — é histórico research diferente · cross-link via frontmatter
- NÃO criar perfil cliente prospect sem signal (ADR 0105)
- NÃO inflar com features wish

---

## 11) Riscos e mitigação

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Skill trigger falso-positivo (Claude cria perfil pra cliente que não é cliente) | Média | Baixo (ruído filesystem) | Validar via Wagner antes de commit · stub pode ser deletado fácil |
| PII real vazada em git por engano | Baixa (skill bloqueia · matcher PII) | Alto (LGPD) | Skill valida regex CPF/email/telefone antes de salvar · alerta Wagner |
| Drift entre `clientes/<slug>.md` e `clientes-ativos.md` legacy | Média | Baixo | Pest test consistência · ou deletar legacy quando _INDEX maduro |
| Perfis ficam stale (cliente mudou status mas perfil não atualizou) | Alta | Médio | Revisão proativa (§9) · Wagner WhatsApp reminder |
| Funcionário muda de cliente (raro · troca emprego) | Baixa | Baixo | Histórico append-only · novo perfil em cliente novo · cross-link |
| Tamanho índice cresce >50 clientes | Baixa (5 anos) | Baixo | Paginar `_INDEX` por status quando >30 |

---

## 12) Métricas de sucesso (90 dias pós-aprovação)

| Métrica | Meta | Crítico |
|---|---:|---|
| % clientes piloto+ com perfil completo | 100% | <80% = skill falhou |
| % funcionários champion com perfil | 100% | <90% = skill falhou |
| Tempo onboarding novo dev pra entender cliente X | ≤10min ler perfil | >30min = sections insuficientes |
| Wagner pedidos manuais "salve no perfil" | 0/semana | >2/semana = skill não auto-triggered |
| PII em git canônico | 0 | qualquer = bloqueio commit + alerta |
| Drift `_INDEX` vs perfis | 0 | >0 = Pest fail |

---

## 13) Plano de execução

| Fase | Esforço | Quem | Entrega |
|---|---|---|---|
| **F1 ADR proposal** | 30min | Claude (este doc · 2026-05-14 noite) | ✅ proposta escrita |
| **F2 Migração + criação perfis** | ~2h | Agent BG · noite 14/maio | Pasta `clientes/` + `funcionarios/` populadas (2 clientes + 8 funcionários) |
| **F3 Skill Tier A** | ~1h | Agent BG · noite 14/maio | `.claude/skills/cliente-funcionario-collector/SKILL.md` |
| **F4 ADR canon accepted** | Wagner segunda | Wagner | Move proposal → `0144-perfis-cliente-funcionario.md` |
| **F5 Skill upgrade Tier A** | ~30min | Após F4 | Adicionar à matriz Tier A `memory/sprints/s3-constituicao/03-skills-audit.md` |

---

## 14) Quando reabrir / descontinuar

- 90 dias sem uso (Wagner não pediu nada via skill) → reavaliar se trigger funciona
- Cliente NOVO entra mas perfil não foi auto-criado → bug na skill · fixar
- Felipe/Eliana[E] reclamam "não acho info do cliente X" → perfil incompleto · iterar sections
- Vault Vaultwarden mostrar overhead alto → reavaliar boundary git ↔ vault

---

## 15) ADRs e Skills relacionadas

- [ADR 0061](../0061-conhecimento-canonico-git-mcp-zero-automem.md) — Conhecimento canônico git + MCP zero auto-mem
- [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípios §1 Context as a product · §3 Charter > Spec)
- [ADR 0095](../0095-skills-tiers-convencao-interna.md) — Skills tiers (esta vira Tier A)
- [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado (não criar perfil de wish)
- [ADR 0131](../0131-tiering-memoria-canonico-local-segredo.md) — Tiering memória (segredo Vaultwarden · canônico git · local pessoal)
- Skill `brief-first` Tier A — pattern auto-trigger SessionStart referência
- Skill `multi-tenant-patterns` Tier A — pattern Tier 0 referência

---

**Criado:** 2026-05-14 noite
**Status:** proposed (aguarda Wagner aprovação como ADR 0144 segunda)
**Autor:** Wagner + Claude (worktree `naughty-euclid-2ab744`)
**Versão:** 1.0 draft inicial
