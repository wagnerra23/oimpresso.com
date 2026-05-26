# Discovery — Reunião Martinho Caçambas (2026-05-13 10h)

> **Tipo:** registro pós-reunião · piloto qualificado #1 Modules/OficinaAuto
> **Cliente:** MARTINHO CAÇAMBAS LTDA · business_id=**164** em prod (Hostinger)
> **Hash legacy:** `Cliente_731814` ([01-perfil](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md))
> **Banco legacy:** `servidor-crm:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB` (alias `MartinhoServidor`)

## Decisão da reunião (Wagner confirmou)

✅ **Martinho aceitou — iniciar migração via importer Firebird.**

Próximo passo técnico: **US-OFICINA-002** (importer `EQUIPAMENTO_VEICULO` → `vehicles` Laravel, ~91 caçambas/caminhões esperados, PLACA 95.6%).

## Estado prod biz=164 hoje (validado via SSH 2026-05-13 ~15h45 BRT)

| Recurso | Estado |
|---|---|
| `business.id=164` | ✅ existe ("MARTINHO CAÇAMBAS LTDA") |
| `business.tax_number_1` | ❌ NULL — popular quando importer EMPRESA rodar |
| `vehicles` biz=164 | ✅ **91 rows importados 2026-05-13 13:31 BRT** (legacy_id 1..91 · placeholder `#EQ{codigo}` pros 4 sem placa) |
| `service_orders` biz=164 | ✅ **91 rows importados 2026-05-13 13:31 BRT** |
| Modules/OficinaAuto V0 | ✅ LIVE (US-OFICINA-001 PR #556 + Kanban Producao PRs #735→#740) |

> **Nota da sessão ~15h45:** ao retomar pra "continuar migrando Martinho", descobriu-se que importer já havia rodado em outra sessão do mesmo dia (13:31 BRT). Importer Python `scripts/legacy-migration/import-vehicles.py` foi criado nesta sessão e validado em dry-run (91 reads · 87 placas reais · 4 placeholders) — fica como ferramenta documentada/idempotente pros próximos clientes OfficeImpresso (Vargas, Extreme, Gold, etc).

## Arquitetura escolhida (Wagner aprovou 2026-05-13)

| Decisão | Caminho |
|---|---|
| Banco fonte | **Remoto** `MartinhoServidor` (LAN servidor-crm, requer Wagner local) |
| Idempotência | **`vehicles.legacy_id` direto** (sem tabela bridge — schema já tem coluna string 20 chars) |
| Stack importer | Python `firebird-driver` + `pymysql` (pattern validado `import-empresas.py` biz=1) |
| Mode `dry-run` | gera SQL preview em `scripts/legacy-migration/output/` antes de tocar prod |

## Vocabulário Martinho (CORRIGIDO 2026-05-26 pós-ADR 0194)

> **⚠️ Correção factual (Wagner 2026-05-26 + [ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) + errata endereço pós-smoke prod):** vocabulário original capturado neste discovery 2026-05-13 estava errado por leitura errada do Firebird. **Sub-vertical real do Martinho é 4 — mecânica pesada / autorizada caminhão basculante CNAE 4520** (**Tubarão/SC Humaitá de Cima**, NÃO Capivari de Baixo como inicialmente inferido via WebSearch), NÃO locação caçamba container CNAE 4581 como inferido.

### Vocabulário canon CORRETO (sub-vertical 4 mecânica pesada caminhão basculante)

- **peça hidráulica** — PTO (tomada de força), kit hidráulico, bomba, válvula, mangueira
- **peça mecânica pesada** — eixo, suspensão pesada, motor diesel, embreagem, freio pneumático
- **L** — óleo diesel/motor/hidráulico
- **kg** — graxa
- **hora-trabalho mecânico** — OS programada (não diária)
- **catálogo cross-ref** Scania/Volvo/MB/Ford
- **placa Mercosul** — caminhão de cliente (não frota Martinho)

### Vocabulário sub-vertical 3 hipotético (LOCAÇÃO CONTAINER — sem cliente real ancorado)

> Capacidade caçamba container = **m³** (volume 3D — 3m³ pequena reforma · 5m³ reforma grande · 7m³ obra média). **NÃO** dizer m² (área 2D — Comunicação Visual). Preservado em [dominios-verticais-oimpresso.md §"Sub-vertical 3"](../../../reference/dominios-verticais-oimpresso.md) caso futuro cliente real ancorar essa sub-vertical.

### Correção 2026-05-26 — origem do erro de leitura

- 96% PLACA Firebird = caminhões de clientes que entram pra peça/serviço (caçamba container estacionária NÃO tem placa)
- Faturamento R$ [redacted Tier 0]M+/mês compatível com mecânica pesada autorizada · não com locação container ticket R$ [redacted Tier 0]-500/diária
- WebSearch 2026-05-26 identificou entidade Martinho Caçambas (URL Listatudo seção Tubarão · perfil peça hidráulica Polli/munck/plataforma/basculante). Endereço cadastrado prod biz=164 (Location BL0001) confirmou **Tubarão SC Humaitá de Cima** (errata 2026-05-26 — anterior dizia Capivari de Baixo).
- Entidade do filho — pai (Martinho da Caçamba Tubarão SC) é transportadora resíduo separada, NÃO é cliente oimpresso
- Cadeia comercial: [Tork PTO (fábrica Capivari)](../../../research/clientes-prospect/tork-tomadas-forca/01-perfil.md) → Martinho (revenda + instala) → frota basculante terceiro

## Pendente Wagner complementar (quando puder)

- [ ] Opção comercial aceita: A (beta 30d) · B (faseada) · C (pacote completo) — ver [demo-script.md §fechamento](demo-script.md)
- [ ] Prazo combinado pra cutover (canary 7d? 30d?)
- [ ] Escopo combinado da fase 1 (só vehicles? incluir vendas históricas 44k? boletos pendentes?)
- [ ] Stakeholders Martinho (Martinho dono + filho/operador? quem opera diário?)

## Refs

- [ADR 0137](../../../decisions/0137-modules-oficinaauto-qualificada.md) — OficinaAuto qualificada · Martinho #1
- [ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE prod
- [handoff 2026-05-12 23h](../../../handoffs/2026-05-12-2300-massive-sells-session-revert-fix-martinho-prep.md) — prep da reunião
- [01-perfil Martinho](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- [03-financeiro Martinho](../../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md) — R$ [redacted Tier 0]M receita 12m · 76.7% inadimplência (a investigar inadimplencia-investigacao.md)
- [legacy-delphi-firebird](../../../reference/legacy-delphi-firebird.md) — credenciais SYSDBA/masterkey + DSN MartinhoServidor

## CHANGELOG

- **2026-05-13** — Criado pós-reunião 10h · Claude (worktree angry-liskov-ec22c0). Discovery completo Martinho biz=164 com vocabulário errado (caçamba avulsa locação m³).
- **2026-05-26** — Correção §Vocabulário pós-[ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md). Sub-vertical 4 mecânica pesada caminhão basculante CNAE 4520. Adicionada §"Correção 2026-05-26 — origem do erro de leitura" + cadeia comercial Tork PTO prospect.

---
**Criado:** 2026-05-13 pós-reunião 10h · Claude (worktree angry-liskov-ec22c0)
**Última atualização:** 2026-05-26 (correção domínio pós-ADR 0194 · worktree oficinaauto-demo)
