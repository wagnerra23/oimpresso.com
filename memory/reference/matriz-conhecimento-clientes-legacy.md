---
name: Matriz de conhecimento — clientes legacy OfficeImpresso (WR Comercial Delphi) × oimpresso (Laravel)
description: Cruza 50 bancos Firebird registrados HKCU × 56 businesses oimpresso prod × status migração × vertical real × sinal qualificado. Atualizar quando cliente novo for analisado OU status migração mudar. Calibrada com Martinho biz=164 migrado 2026-05-13.
type: reference
---

# Matriz de conhecimento — universo legacy → oimpresso

> **Função:** ponto único de verdade pra "onde está cada cliente da carteira Wagner (WR Sistemas 26 anos) no caminho legacy → oimpresso". Usada pelo agent [migracao-officeimpresso](../../.claude/agents/migracao-officeimpresso.md) pra escolher próximo cliente + por Wagner pra priorizar comercial.

## Universos cruzados

| Universo | Tamanho | Fonte |
|---|---|---|
| Bancos Firebird Delphi | **50 bancos** | `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos` (Windows Wagner) — ver [legacy-delphi-firebird.md](legacy-delphi-firebird.md#bancos-registrados-50-entradas-2026-05-09) |
| Businesses oimpresso prod | **56 businesses** | `business` table Hostinger — ver [clientes-ativos.md](clientes-ativos.md) |
| Businesses oimpresso COM vendas | **7 de 56** | ROTA LIVRE 99% volume; só biz=4 é real | 
| Clientes com perfil analisado | **5 de 50** | `memory/research/clientes-legacy-officeimpresso/0[1-5]-*/01-perfil.md` |

## Tier A — 5 clientes COM perfil completo (priorizados)

> **`VERSAO_BANCO`** coletada 2026-05-13 via `SELECT VALOR FROM CONFIGURACOES WHERE CONFIG='VERSAO_BANCO'` em cada banco. Range na carteira: **v1404 (Martinho)** → **v1474 (Zoom)** = 70 versões de drift · 65 tabelas de diferença (377 → 442). Usar pelo agent [`migracao-firebird-versoes`](../../.claude/agents/migracao-firebird-versoes.md) pra adapter automático.

| Hash | Slug research | Banco FB (alias) | **`VERSAO_BANCO`** | Tabelas FB | Vehicles FB | Vendas FB | Vertical real | biz oimpresso | Status migração | Próximo passo Wagner |
|---|---|---|---:|---:|---:|---:|---|---:|---|---|
| **`Cliente_xx`** (WR2 Wagner) | [01-wr-sistemas](../research/clientes-legacy-officeimpresso/01-wr-sistemas/01-perfil.md) | `ServidorWR2` (servidor-crm:Banco) | **1468** | 442 | 102 | 1.866 | dev/demo Wagner | **biz=1** | ✅ **migrado parcial 2026-05-11** (4 contacts + 19 accounts via [import-empresas.py](../../scripts/legacy-migration/import-empresas.py) + [import-contas-bancarias.py](../../scripts/legacy-migration/import-contas-bancarias.py)) | Estabilizar como ground truth pra próximos importers |
| **`Cliente_874398`** (Vargas) | [02-vargas-recapagem](../research/clientes-legacy-officeimpresso/02-vargas-recapagem/01-perfil.md) | `Vargas` (servidor-crm) | **1468** | 401 | 1.064 | 3.981 | recapagem **caçamba de caminhão** | **a confirmar** | 🟡 **dry-run pendente** — depende US-OFICINA-007 | Confirmar biz_id; spawn agent `migracao-firebird-versoes` |
| **`Cliente_6928E8`** (Extreme) | [03-extreme-grafica](../research/clientes-legacy-officeimpresso/03-extreme-grafica/01-perfil.md) | `Extreme` (servidor-crm) | **1472** | 401 | 0 | **85.575** | gráfica industrial PCP | **a confirmar** | ⏸️ **aguardando Modules/ComunicacaoVisual maduro** | Validar PCP gap; v1472 = drift baixo vs v1474 canônica |
| **`Cliente_09FEB1`** (Gold) | [04-gold-comvis](../research/clientes-legacy-officeimpresso/04-gold-comvis/01-perfil.md) | `Gold` (servidor-crm) | **1466** | 416 | 0 | **55.715** | comunicação visual (m²) | **a confirmar** | ⏸️ **trilha Gold dormente** (US-NFE-043..048 backlog) | Reativar quando Modules/ComunicacaoVisual V1 LIVE |
| **`Cliente_731814`** (Martinho) | [05-martinho-cacambas](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md) | `MartinhoServidor` (servidor-crm:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB) | **1404** ⚠️ + antigo | 377 | 91 | **46.065** | locação **caçamba avulsa** (m³) | **biz=164** | ✅ **vehicles+SO done 2026-05-13 13:31** (91 + 91 rows · placeholder `#EQ{codigo}`) · 🟡 **vendas+financeiro PENDENTE** | Spawn `migracao-firebird-versoes` p/ terminar Fase 4+5 (adapter forte v1404) |

## Tier B+ — Sampled VERSAO_BANCO (4 candidatos ComVis pendentes)

| Alias | VERSAO_BANCO | Tabelas | Vendas FB | Status candidatura |
|---|---:|---:|---:|---|
| Zoom | **1474** ⚠️ + novo (canônica) | 400 | 52.390 | candidato ComVis saudável |
| Fixar | **1421** | 377 | 4.584 | candidato ComVis saudável |
| Mhundo | **1429** | 383 | 18.327 | candidato ComVis saudável |
| Produart | TBD | TBD | TBD | candidato ComVis saudável (pendente sample) |

## Tier B — Resto registry HKCU (45 bancos sem perfil analisado)

Bancos Delphi registrados sem perfil em `memory/research/clientes-legacy-officeimpresso/`. Maioria provavelmente dormente / não usa Delphi ativamente. Priorizar análise APENAS se cliente pagar plano + reportar problema ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

| Alias HKCU | Path (servidor-crm:) | Sinal? | Vertical inferida | Ação |
|---|---|---|---|---|
| Art Laser | `D:\DadosClientes\Art Laser\Dados\BANCO.FDB` | — | gráfica/laser? | dormente |
| ASULBRAT | `D:\DadosClientes\Assulbrat\Dados\BANCO - ASULBRAT.FDB` | — | a determinar | dormente |
| Bangalo | `D:\DadosClientes\Bangalo Servidor\Dados\BANCO.FDB` | — | a determinar | dormente |
| Camargo | `D:\DadosClientes\Sabor Brasil\Dados\BANCO.FDB` | — | restaurante (Sabor Brasil)? | dormente |
| Casagrande | `D:\DadosClientes\Mecânica Casagrande\Dados\BANCO.FDB` | — | mecânica automotiva | candidato OficinaAuto (sub-vertical 4520) |
| CiaDosMoveis | `D:\DadosClientes\Cia dos Moveis\Dados\BANCO.FDB` | — | móveis | dormente |
| CiaSul | `D:\DadosClientes\Ciasul\Dados\BANCO.FDB` | — | a determinar | dormente |
| CopyLanLocal | `D:\DadosClientes\Copylan\Dados\BANCO.FDB` | — | copy/gráfica | dormente |
| CubaInox | `D:\DadosClientes\CubaInox\Dados\BANCO.FDB` | — | metalúrgica inox | dormente |
| CyberStudio | `D:\DadosClientes\CyberStudio\Dados\BANCO.FDB` | — | a determinar | dormente |
| Destak | `D:\DadosClientes\Destak\Dados\BANCO.FDB` | — | a determinar | dormente |
| Display Parana | `D:\DadosClientes\Display Parana\Dados\BANCO.FDB` | — | comunicação visual | candidato ComVis |
| DMB | `D:\DadosClientes\DMB\Dados\BANCO.FDB` | — | a determinar | dormente |
| ECopias | `D:\DadosClientes\ECOPIAS\Dados\BANCO.FDB` | — | copy | dormente |
| Estilo | `D:\DadosClientes\ESTILO\Dados\BANCO.FDB` | — | a determinar | dormente |
| Fixar | `D:\DadosClientes\Fixar\Dados\BANCO.FDB` | — | comunicação visual? | candidato ComVis (cited handoff 6 saudáveis) |
| Fluxo | `D:\DadosClientes\FLUXO\Dados\BANCO.FDB` | — | a determinar | dormente |
| Golbal | `D:\DadosClientes\Global Pneus\Dados\BANCO.FDB` | — | pneus/oficina | candidato OficinaAuto (recapagem ou loja) |
| GoldenPrint | `D:\DadosClientes\Golden Print\Dados\BANCO.FDB` | — | gráfica/print | dormente |
| GPSinalizacao | `D:\DadosClientes\GPSinalizacao\Dados\BANCO.FDB` | — | sinalização viária | candidato ComVis |
| GSX | `D:\DadosClientes\GPSinalizacao\Dados\BANCO - GSX.FDB` | — | sinalização (subset GPSinalizacao) | dormente |
| Guia Decor | `D:\DadosClientes\Guia Decor\Dados\BANCO.FDB` | — | decoração | dormente |
| HexiPrint | `D:\DadosClientes\HexiPrint\Dados\BANCO.FDB` | — | gráfica | dormente |
| Lebrinha | `D:\DadosClientes\Mecanica Lebrinha\Dados\BANCO.FDB` | — | mecânica automotiva | candidato OficinaAuto (duplicado com "Mecanica Lebrinha") |
| Max | `D:\DadosClientes\Max Comunicação\Dados\BANCO.FDB` | — | comunicação visual | candidato ComVis |
| Medeiros Produtos Limpeza | `D:\DadosClientes\Medeiros Produtos Limpeza\Dados\BANCO.FDB` | — | distribuição limpeza | dormente |
| Metalurgica SF | `D:\DadosClientes\Metalurgica SF\BANCO.FDB` | — | metalúrgica | dormente |
| Mhundo | `D:\DadosClientes\Mhundo\Dados\BANCO.FDB` | — | a determinar | candidato handoff (6 saudáveis) |
| Midia e CIA | `D:\DadosClientes\MIDIA E CIA\Dados\BANCO.FDB` | — | mídia/gráfica | dormente |
| Midia OFF | `D:\DadosClientes\MIDIA OFF\Dados\BANCO.FDB` | — | mídia | dormente |
| MilLetras | `D:\DadosClientes\MIL LETRAS\Dados\BANCO.FDB` | — | comunicação visual (letras) | candidato ComVis |
| MoveisSul | `D:\DadosClientes\Movesul\Dados\BANCO.FDB` | — | móveis | dormente |
| Multimage | `D:\DadosClientes\Multimage\Dados\BANCO.FDB` | — | gráfica/mídia | dormente |
| NewPrintFoz | `D:\DadosClientes\NewPrintFoz\Dados\BANCO.FDB` | — | gráfica Foz Iguaçu | dormente |
| Personalise | `D:\DadosClientes\Personalize\Dados\BANCO.FDB` | — | personalização | dormente |
| Produart | `D:\DadosClientes\Produart\Dados\BANCO.FDB` | — | a determinar | candidato handoff (6 saudáveis) |
| RG Comunicacao | `D:\DadosClientes\RG Comunicação\Dados\BANCO.FDB` | — | comunicação visual | candidato ComVis |
| Safety | `D:\DadosClientes\Safety\Dados\BANCO.FDB` | — | a determinar (segurança?) | dormente |
| SCMola | `D:\DadosClientes\SCMolas\Dados\BANCO.FDB` | — | molas/autopeças | candidato OficinaAuto (autopeças/molas) |
| Studium Vinil | `D:\DadosClientes\Studium Vinil\Dados\BANCO.FDB` | — | comunicação visual (vinil) | candidato ComVis |
| TechPress | `D:\DadosClientes\Techpress\Dados\BANCO.FDB` | — | gráfica (TechPress legacy) | dormente |
| TechPressLocal | `D:\DadosClientes\Techpress\BANCO.FDB` (local) | — | gráfica TechPress (subset) | dormente |
| Vargas Acessorios | `D:\DadosClientes\Jardel acessorios\Dados\BANCO.FDB` | — | acessórios (subset Vargas?) | dormente |
| Wow | `D:\DadosClientes\WOWComunicacao\Dados\BANCO.FDB` | — | comunicação visual | candidato ComVis |
| Zoom | `D:\DadosClientes\Zoom\Dados\BANCO.FDB` | — | a determinar | candidato handoff (6 saudáveis) |

## Tier C — Candidatos OficeImpresso saudáveis citados em handoffs

Handoff [2026-05-12-2300](../handoffs/2026-05-12-2300-massive-sells-session-revert-fix-martinho-prep.md) cita 7 candidatos saudáveis pra ComVis:

> Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart

Já analisados: Vargas (✅ Tier A) · Extreme (✅) · Gold (✅). Falta analisar: **Zoom, Fixar, Mhundo, Produart** (4 candidatos pra ativar quando ComVis V1 LIVE).

## Sinais qualificados pra migração ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

Cliente vira "Tier A" (vai pro topo prioridade) quando:
- (a) Cliente paga plano oimpresso E reportou problema concreto OU
- (b) Métrica detecta drift (latência > 5s, erros > 1%, churn risk) OU
- (c) Wagner sinaliza "feedback comercial direto" (call, email, WhatsApp)

Sem sinal → cliente fica em Tier B/C (não migrar especulativo).

## Próximos passos imediatos

| # | Ação | Owner | Prazo |
|---|---|---|---|
| 1 | Wagner valida discovery-martinho.md complementando reunião 10h (opção comercial aceita, prazo, escopo combinado) | Wagner | hoje |
| 2 | Investigar quem rodou importer 13:31 (cc-search MCP self-host? sessão claude.ai cloud?) | Wagner + Claude | esta sessão |
| 3 | Migrar Vargas (próximo OficeImpresso Tier A — recapagem 1.064 veículos multi-placa) | agent `migracao-officeimpresso` | TBD (Wagner sign-off) |
| 4 | Ativar 4 candidatos ComVis (Zoom, Fixar, Mhundo, Produart) — depende Modules/ComunicacaoVisual V1 LIVE | a definir | TBD |
| 5 | Receita 12m via [officeimpresso-financial-snapshot](../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md) pra Vargas/Extreme/Gold (já tem pra Martinho) | Wagner (operador) | TBD |

## Refs canon

- [Pattern canônico migração](migracao-officeimpresso-pattern.md) — receita técnica 4 fases
- [Agent `migracao-officeimpresso`](../../.claude/agents/migracao-officeimpresso.md) — orquestrador por cliente
- [legacy-delphi-firebird](legacy-delphi-firebird.md) — DSNs, credenciais, registry
- [clientes-ativos](clientes-ativos.md) — 56 businesses oimpresso prod
- [dominios-verticais-oimpresso](dominios-verticais-oimpresso.md) — Vestuario/ComVis/OficinaAuto/Repair
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — sinal qualificado
- [ADR 0121](../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — modular por vertical
- [ADR 0137](../decisions/0137-modules-oficinaauto-qualificada.md) — OficinaAuto qualificada

## Como atualizar essa matriz

1. Cliente novo analisado em `memory/research/clientes-legacy-officeimpresso/NN-<slug>/01-perfil.md` → adicionar linha em Tier A
2. Status migração mudou (qualificado → dry-run → migrado parcial → migrado completo) → atualizar coluna "Status migração"
3. Banco novo aparece em HKCU (cliente WR2 instalou Delphi) → adicionar em Tier B
4. Após sessão de migração → atualizar "Próximo passo Wagner" + "Status migração" do cliente impactado

---

**Última atualização:** 2026-05-13 ~16h BRT · sessão `angry-liskov-ec22c0` · pós-descoberta migração Martinho (Wagner) já feita 13:31 BRT por agente anterior (créditos esgotados).
