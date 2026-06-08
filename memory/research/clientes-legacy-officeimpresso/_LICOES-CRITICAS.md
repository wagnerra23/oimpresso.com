---
title: Lições críticas — anti-bug consolidado para próximas sessões OfficeImpresso legacy
status: live
date: 2026-05-11
audience: time interno (Wagner / Felipe / Maiara / Eliana / Luiz) + IA-pair
purpose: prevenir erros recorrentes detectados em 12 PRs da sessão 2026-05-11 (Sells Grade + OficinaAuto qualificação)
escopo: pasta clientes-legacy-officeimpresso/ + skills officeimpresso-* + Modules/OficinaAuto
---

# Lições críticas — não repetir

> 8 erros cometidos e corrigidos nesta sessão. Documento serve como "anti-bug index" pra próximas análises de cliente legacy. Cada lição tem causa raiz + fix + path canônico onde encontrar verdade.

## 🚨 Erros de classificação de cliente

### 1. Vargas NÃO é gráfica nem gráfica+frota — é **oficina de recapagem de caçamba de caminhão**

| Detalhe | Valor |
|---------|-------|
| Cliente hash | `Cliente_874398` |
| Vertical real | Oficina especializada (CNAE 2212-9/00 recapagem) |
| Evidência | 1.064 veículos em `EQUIPAMENTO_VEICULO` · PLACA 80% · **PLACA2 20%** + **CHASSI2 8%** = cavalo+reboque (semi-reboque tem placa+chassi próprios) |
| Erro v1 | Classifiquei como "gráfica de SP" |
| Erro v2 | "gráfica + frota multi-vertical" |
| Wagner corrigiu | "empresa GRANDE de recapagem de caçamba de caminhão" |
| Path canônico | [02-vargas-recapagem/01-perfil.md](02-vargas-recapagem/01-perfil.md) |

**Regra:** **NUNCA classificar vertical via heatmap sem Wagner aprovar** — humano sabe negócio real do cliente que dados sozinhos enganam.

### 2. Gold NÃO é "gráfica genérica" — é **comunicação visual** sob demanda

| Detalhe | Valor |
|---------|-------|
| Cliente hash | `Cliente_09FEB1` |
| Vertical real | Comunicação visual (CNAE 7320-5/00 ou 1813-0/01) |
| Evidência | 29k vendas "EM PRODUÇÃO" via `VENDA.SITUACAO` inline + zero PCP industrial + zero veículos = comvis personalizada |
| Erro inicial | "gráfica com funil produção textual" genérica |
| Wagner corrigiu | "comunicação visual" |
| Path canônico | [04-gold-comvis/01-perfil.md](04-gold-comvis/01-perfil.md) |

**Regra:** PCP por centro de trabalho (`VENDA_PRODUTO_CENTRO_TRABALHO`) ≠ status produção textual (`VENDA.SITUACAO`). Industrial usa PCP, comvis usa status.

## 🚨 Erros de mapping Delphi → Laravel

### 3. "Dt. Prometido" no UI Delphi é `PROJETO_DT_FIM`, **NÃO** `DT_PROMETIDO`

| Detalhe | Valor |
|---------|-------|
| Coluna SQL real | `VENDA.PROJETO_DT_FIM` |
| Coluna assumida (errada) | `VENDA.DT_PROMETIDO` (não existe) |
| Fonte autoritativa | [Controller.Venda.Venda.pas:94](.../Controller/Controller.Venda.Venda.pas) — `InitializeDatasNaConsulta` |
| Re-probe correto | Extreme 91.4% · Gold 6.2% · Vargas 0.4% · Martinho 0% · WR2 8% |
| Erro v1/v2/v3 | Heatmap buscou `DT_PROMETIDO` que não existe → resultado "ausente" em 4 de 5 bancos |
| Path canônico | [_MAPPING/TELA-LISTA-VENDAS.md §5](_MAPPING/TELA-LISTA-VENDAS.md) |

**Regra:** **Source-first sempre primário** pra mapping Delphi → Laravel. Probing-first sem código-fonte real engana (skill `officeimpresso-source-analysis`).

### 4. Extreme (não Gold) é o cliente paradigmático de "prazo prometido"

| Cliente | PROJETO_DT_FIM % | Interpretação |
|---------|-----------------:|---------------|
| **Extreme** | **91.4%** | 🎯 gráfica industrial controla prazo formalmente |
| Gold | 6.2% | comvis NÃO controla prazo estruturalmente |
| Vargas | 0.4% | recapagem (entrega rápida, sem prazo formal) |
| Martinho | 0.0% | mecânica pesada caminhão basculante (sub-vertical 4 · CNAE 4520 · correção ADR 0194 — pré-correção dizia "caçamba locação") |
| WR2 | 8.0% | toy |

**Regra:** Inverter v1/v2/v3 que diziam Gold = "cliente comvis com prazo". É Extreme.

### 5. `P.PLACA` em `VENDA` é **FK integer** pra `EQUIPAMENTO_VEICULO.CODIGO`, NÃO string da placa

```sql
-- WRONG (assumido v1/v2):
SELECT PLACA FROM VENDA WHERE PLACA = 'RXT9I46';

-- RIGHT (real):
SELECT EV.PLACA FROM VENDA P
LEFT JOIN EQUIPAMENTO_VEICULO EV ON (EV.CODIGO = P.PLACA)
WHERE EV.PLACA = 'RXT9I46';
```

**Regra:** Sempre verificar JOIN antes de tratar como string. Coluna chamada `PLACA` pode ser FK.

## 🚨 Erros de uso de dados

### 6. Martinho 76,7% inadimplência é **lixo histórico 2015-19**, não cliente que não paga

| Sinal | Valor | Implicação |
|-------|------:|-----------|
| 83% do vencido > 365 dias | média 2.564 dias (~7 anos) | inadimplência **fóssil** |
| Recente (<60d) | 4,9% (R$ [redacted Tier 0]k) | inadimplência **real** |
| Taxa baixa 12m | 84,7% | operação corrente saudável |
| Taxa boleto 12m | 74% | cliente cobra com boleto |
| R$ [redacted Tier 0]M em `INATIVO CANCELADA` | 12× maior que vencido ativo | manutenção manual caótica |

**Regra:** Inadimplência alta em cliente Delphi legacy ≠ inadimplência real. Investigar idade + cancelamentos manuais primeiro.

**Path canônico:** [05-martinho-cacambas/04-inadimplencia-investigacao.md](05-martinho-cacambas/04-inadimplencia-investigacao.md)

**Implicação produto:** Modules/OficinaAuto V1 ROI principal = **cleanup tools** (importer com regra "write-off candidate" + tela "Revisão pendências legadas" + Conciliação VENDA↔FINANCEIRO), NÃO dunning automático.

## 🚨 Achados de schema crítico

### 7. `CONFIGURACOES_GRID.GRID` é **BLOB DFM DevExpress** serializado, NÃO JSON estruturado

| Detalhe | Valor |
|---------|-------|
| Tabela | `CONFIGURACOES_GRID` (8 colunas — universal nos 5 bancos) |
| Campo crítico | `GRID` BLOB ~12-16KB |
| Conteúdo real | DFM ASCII com `TcxGridDBColumn`, `Visible: True/False`, `GroupIndex`, `SortOrder` |
| Discovery necessário | Parser ASCII regex (ver `scripts/probe_configuracoes_grid_blob.py`) |
| Path canônico | [_MAPPING/CONFIGURACOES-GRID.md](_MAPPING/CONFIGURACOES-GRID.md) |

**Padrões descobertos:**
- 42 colunas declaradas em VENDA grid · **13-18 visíveis avg** por user (clientes filtram 60-70% por default)
- # grids salvos por cliente = **proxy company size** (Vargas 548 · Martinho 690 · WR2 253)
- Sort persistido = 0% em todos clientes → low impact pra V1 Grade Avançada
- Agrupamento usado em 2/5 clientes → US-SELL-019 **condicional**, não universal

**Regra:** US-SELL-027 schema discovery precisa parser DFM (estimate 6h → 10h pós-PR #545).

### 8. Delphi já tem **bridge pro oimpresso.com novo** + **Kanban industrial pronto**

| Componente Delphi | Descrição |
|-------------------|-----------|
| `Controller.OImpresso.pas` | Tela "API OImpresso.com" |
| `Controller.Pessoas.OImpresso.pas` | Sync de Pessoas |
| `Classe.Mestre.OImpresso.pas` | Bridge base |
| Métodos: `SincronizarContatos/Vendas/Financeiro/Produto/Tudo` | POST API oimpresso.com |
| Tabela `OIMPRESSO` + `OIMPRESSO_LOG` | Estado e audit trail de sync |
| `Controller.Producao.Kanban.pas` | **Kanban industrial built-in** com 8 agrupadores + drag-drop + colunas colapsáveis |
| Tabela `WR_KANBAN(CHAVE, COLUNA, ORDEM, COLUNA_FECHADA)` | Persiste estado UI Kanban |

**Implicação estratégica:**
- Migração OfficeImpresso → oimpresso.com **não precisa ser cutover Big Bang**. Modelo Asaas-like viável: cliente continua Delphi + ganha cloud em paralelo via sync bridge.
- Kanban do Delphi serve de **pré-arte** pra Modules/OficinaAuto (Kanban OS) e Modules/ComunicacaoVisual (Kanban produção).

**Path canônico:** [.claude/skills/officeimpresso-source-analysis/SKILL.md](../../../.claude/skills/officeimpresso-source-analysis/SKILL.md) §"Bridge Delphi ↔ oimpresso.com"

---

## 🛠️ Lições operacionais (paralelização de agents)

### O que funcionou
- 4 agents em **worktree isolada** podem rodar simultaneamente sem conflito real
- Cada agent fez 1 PR autocontido com commit msg coerente
- Total wallclock ~30 min com 4 agents vs ~3-4h sequencial

### O que falhou
- Agent C teve fallback pro **working tree principal** e detectou conflito com Agent B (working tree compartilhado por engano) — stashou e recuperou-se manualmente
- Worktree isolada **não garante 100% isolamento** quando agents tocam mesma região conceitual (mesma branch base alterada por outro durante execução)
- PR de scaffold (#556) Pest não rodou em worktree (vendor não compartilhado via junction PSR-4)

### Heurística para próxima sessão

| Critério | Recomendação |
|----------|--------------|
| Cap agents simultâneos | **3-4** (sweet spot prático) |
| Escopos | **Textuais disjuntos** (diretórios diferentes; mesmo módulo OK se arquivos diferentes) |
| ❌ Não paralelizar | 2+ agents potencialmente editam mesmo arquivo/feature |
| Pest em worktree | ⚠️ Pode não rodar (vendor não compartilhado) — escrever testes mas validar local |
| Conflito de branch | Sempre criar branch fresca de `origin/main` no agente (não a partir de branch local que pode estar atrasada) |
| Cherry-pick fallback | Se PR der conflito, cherry-pick em branch fresca > tentar resolver merge |

### ⚠️ Aviso da sessão paralela 2026-05-11 18:30

Handoff irmão [2026-05-11-1830-paralelizacao-omnichannel-frustrada.md](../../handoffs/2026-05-11-1830-paralelizacao-omnichannel-frustrada.md) reporta: **subagents spawned de worktree filha morrem** (não dá pra Agent dentro de worktree spawn outro Agent dentro de worktree). Spawn agents só do main worktree.

---

## 📋 Drift conhecido (follow-ups documentados)

### SPEC OficinaAuto — US-OFICINA-NNN em tabela markdown vs header

[ADR 0134](../../decisions/0134-tasks-create-respeita-spec-placeholders.md) parser MCP indexa tasks via regex `### US-XX-NNN ·` (header) ou `- US-XX-NNN —` (bullet). [SPEC OficinaAuto](../../requisitos/OficinaAuto/SPEC.md) tem US-OFICINA-001..004 em **tabela markdown** (linha 25-28: `| **US-OFICINA-001** | Scaffold... |`) que parser não indexa.

**Resultado:** `mcp__Oimpresso_MCP___Wagner__tasks-list module:OficinaAuto` retorna só US-AUTO-NNN antigos (anexo histórico §3 do SPEC), não US-OFICINA-NNN ativos.

**Fix proposto (próxima sessão):** converter linhas 25-28 do SPEC OficinaAuto pra formato:
```markdown
### US-OFICINA-001 · Scaffold módulo V0 (8 peças + Vehicle + ServiceOrder + Pest + Inertia Pages) — DONE PR #556

> owner: — · priority: p0 · status: done · ...

### US-OFICINA-002 · Importer Firebird EQUIPAMENTO_VEICULO → vehicles Laravel (piloto Martinho) — P0
...
```

**Trabalho:** ~15 min · Owner: — (próximo dev pegar) · Estimate ROI: alto (descobertabilidade no MCP)

### Migration naming — `vehicles` vs `oficina_auto_vehicles`

Agent E (PR #556) seguiu literal do ADR 0137 (`vehicles`/`service_orders` sem prefixo). Divergente da convenção `comvis_*` em Modules/ComunicacaoVisual. **Decidir antes de US-OFICINA-002 (importer)** — rename via migration depois é caro.

---

## 📚 Referência rápida — onde encontrar verdade

| Pergunta | Path canônico |
|----------|---------------|
| Vertical real de cada cliente | [perfis em NN-slug/01-perfil.md](.) |
| SQL exato de tela Delphi | [_MAPPING/TELA-*.md](_MAPPING/) (5 telas mapeadas) |
| Schema CONFIGURACOES_GRID + BLOB DFM | [_MAPPING/CONFIGURACOES-GRID.md](_MAPPING/CONFIGURACOES-GRID.md) |
| Metodologia análise (3 camadas) | [_COMO-ANALISAR.md](_COMO-ANALISAR.md) |
| Skill source-first | [.claude/skills/officeimpresso-source-analysis/SKILL.md](../../../.claude/skills/officeimpresso-source-analysis/SKILL.md) |
| Análise financeira por cliente | `NN-slug/03-financeiro-*.md` + [_ANALISE-FINANCEIRA-CROSS-CLIENTE.md](_ANALISE-FINANCEIRA-CROSS-CLIENTE.md) |
| Inadimplência Martinho (caso adversarial) | [05-martinho-cacambas/04-inadimplencia-investigacao.md](05-martinho-cacambas/04-inadimplencia-investigacao.md) |
| ADR mãe modular vertical | [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) |
| ADR OficinaAuto qualificada | [ADR 0137](../../decisions/0137-modules-oficinaauto-qualificada.md) |
| ADR Sells Grade Avançada | [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md) |

---

**Última atualização:** 2026-05-11 fim de sessão — consolidação anti-bug pós-12 PRs mergeados. Próxima atualização: quando próxima sessão detectar erro recorrente novo OU quando fix do drift US-OFICINA-NNN no SPEC for aplicado.
