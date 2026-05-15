---
slug: rotalivre
business_id: 4
razao_social: LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME
cnpj: 73.306.573/0001-11
status: producao
vertical_principal: vestuario
sub_vertical: loja-vestuario-balcao-pdv
cnae: 4781-4/00
cidade_uf: Gravatal/SC
distancia_km_wagner: null
inicio_relacionamento: 2021-02-01
canary_inicio: null
faturamento_anual_brl: null
funcionarios_total: 4
champions_oimpresso:
  - slug: larissa
    role: dona-operadora-principal
decisor_principal: larissa
sistema_anterior: null
concorrentes_avaliados_pausados: []
pricing_mensal_brl: null
arquitetura_migracao: cutover-historico
perfil_legacy: null
timezone: America/Sao_Paulo
ultima_atualizacao: 2026-05-14
proxima_revisao: 2026-08-14
---

# ROTA LIVRE (business_id = 4)

Cliente piloto Modules/Vestuario · 17k+ vendas históricas · 99% do volume do sistema oimpresso novo. Loja de vestuário em Termas do Gravatal/SC, NÃO gráfica em SP. Larissa é dona/operadora principal.

## 1. Identificação

| Campo | Valor |
|---|---|
| Razão social | LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME |
| CNPJ | 73.306.573/0001-11 |
| Localização única | BL0001 "ROTA LIVRE" — Termas do Gravatal, Gravatal/SC, CEP 88735-0 |
| Telefone comercial | (48) 3626-4806 |
| Timezone cadastrado | `America/Sao_Paulo` (confirmado operacionalmente em 2026-04-24) |
| Cadastro original | 2021-02-01 |
| Primeira venda | 2021-05-13 |
| Volume | 17.251+ vendas (~99% do total do sistema) |
| Frequência | Venda ativa diariamente (operação real, não demo) |

## 2. Stakeholders

| Nome | Papel | user biz=4 | sistema usado | papel canary |
|---|---|---|---|---|
| **[Larissa](../funcionarios/rotalivre/larissa.md)** | **Dona/operadora principal** | id=10 `larissa-04` (`Admin#4`) | oimpresso (99% volume) | cliente piloto vivo |
| WR2 Sistemas (admin externo Wagner) | Suporte externo | id=9 `wr2.rotalivre` (`Admin#4`) | oimpresso (suporte) | suporte-tecnico |
| Vendas (operador balcão) | Caixa secundário | id=11 `rota.vendas-04` (`Vendas#4`) | oimpresso (caixa) | operador-balcao |
| Caixa (operador secundário) | Caixa | id=72 `caixa-04` (`Caixa#4`) | oimpresso (caixa) | operador-balcao |

Todos ativos, `permitted_locations='all'` ou location.4 explícita (confirmado 2026-04-24).

Decisor principal: **Larissa** (dona — decide tudo sozinha · sem cadeia hierárquica).

## 3. Saúde financeira

Snapshot dedicado não levantado — operação vestuário com ticket médio R$ 100-500 por venda balcão. Volume 17.251+ vendas implica receita significativa mas faturamento anual não computado.

Padrões observados:
- **Horário de pico:** 14h-17h SP (base nos `created_at` recentes)
- **Ticket médio observado:** R$ 100-500 (balcão de moda)
- **Cliente mais frequente:** "Cliente Balcão" (genérico) + nomes PF (Jociane, Edna, Guilherme, Eli, Lucio, etc.)
- **Esquema de fatura:** NFs com `2026/NNNN` e/ou `17NNN` convivem — dois schemes ativos

## 4. Sistema atual

Cliente **único em produção** no oimpresso novo. Não tem sistema legacy paralelo nem concorrente avaliado. Larissa migrou do nada (ou de planilha) direto pra oimpresso em 2021.

Pain-points operacionais:
- Monitor pequeno 1280px (telas com muitas colunas estouram layout)
- Sensibilidade a regressões visuais (decorou estado de bugs antigos)

## 5. Arquitetura migração

N/A — operação direta no oimpresso desde 2021-02-01 cadastro original. Sem dual-system. Sem cutover pendente.

## 6. Pricing + comercial

Pricing acordado não documentado neste perfil (Wagner gerencia diretamente).

Cliente paga continuamente desde 2021. Sem upsells ativos catalogados.

## 7. Sensibilidades operacionais — NÃO MEXER SEM AVISAR

1. **Horários das vendas:** Larissa **decorou** horários com o shift +3h do bug histórico de `format_date`. Qualquer correção visual de datetime = regressão percebida. Ver [ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md). Não reaplicar `Carbon::parse` no `format_date` sem comunicar antes.

2. **Transaction_date retroativo é normal:** Larissa frequentemente digita `transaction_date` com hora passada (até 17h de diferença do `created_at`), especialmente pra vendas de balcão registradas em lote no final do dia. Isso NÃO é bug — é fluxo dela. Diff `transaction_date` < `created_at` esperado. Não tentar "corrigir" por algoritmo.

3. **Monitor pequeno:** opera em monitor ~1280px. Tela `/sells` com 21 colunas era inutilizável. Solução 2026-04-24: `columnDefs: { targets: [11,12,21,22,23], visible: false }` + locale pt-BR DataTables. Não adicionar colunas default sem pensar no espaço.

4. **Fluxo depende de location selecionada:** `/sells/create` só libera busca de produto depois de `default_location` preenchido. Role `Vendas#4` já tem `location.4` (corrigido em 2026-04-24); se alguém criar role custom sem isso, trava novamente.

5. **ROTA LIVRE = LOJA DE ROUPA em Gravatal/SC** (NÃO é gráfica em SP). Vocabulário: peça, par (sapato), variação cor+tamanho (grade). NÃO usa m³ nem m². Ver [dominios-verticais-oimpresso.md](../dominios-verticais-oimpresso.md).

## 8. Estado prod oimpresso

| Item | Estado |
|---|---|
| Cadastro biz=4 | ✅ ativo desde 2021-02-01 |
| 4 users ativos | ✅ |
| 17.251+ vendas | ✅ histórico completo |
| Operação diária | ✅ 14h-17h SP horário de pico |

Features ligadas: vendas balcão, NFCe, gestão estoque (grade cor+tamanho), DataTables locale pt-BR, columnDefs custom 1280px.

## 9. Histórico de marcos

### 2021-02-01 — Cadastro inicial
Cliente criado como business_id=4 no oimpresso. Wagner gerenciou onboarding.

### 2021-05-13 — Primeira venda
Operação real começa. Daí em diante venda diária ininterrupta.

### 2026-04-24 — Bateria de bugs em `/sells`
Wagner reportou queixas acumuladas da Larissa. Origem múltipla:

1. **Labels em inglês/espanhol/português com typos** → fix lang + DataTables locale pt-BR (commit `dcefd087`)
2. **21 colunas estourando tela 1280px** → columnDefs escondendo 5 colunas (mesmo commit)
3. **Timezone frontend não aplicando (`moment.tz.setDefault('')`)** → session('business_timezone') chave dedicada + middleware (commit `47c9e594`)
4. **`format_date` empurrando +3h** → corrigido (commit `10634ad2`), **revertido mesmo dia** (commit `e5c8c90d`) porque Larissa reportou "vendas antigas mudaram 3h". Ela decorou os horários errados. ADR 0066 formaliza preservação.
5. **Campo de busca de produto disabled em `/sells/create`** → dois bugs independentes:
   - role `Vendas#4` sem `location.4` (fix de dados, SSH direto)
   - shim `Form::` rendering `disabled=false` como ativo (commit `7fbfbdc7`)

Session notes em `memory/sessions/2026-04-24-*.md`.

### 2026-05-14 — Incidente cross-business import-estoque Martinho
3 importers (estoque + produtos + compras) tinham SELECT/UPDATE em `variation_location_details` SEM JOIN+WHERE business_id explícito. UPDATE batch Martinho contaminou 5 VLDs ROTA LIVRE (CARDIGAN M/G · JAQUETA P/M · BLUSA P/G — total 3 unidades). Recovery via backup `~/.cagefs/tmp/oimpresso-dump-20260513-195514.sql.gz` (13/maio 19:55 BRT) + 3 UPDATEs surgical. Larissa não viu nada. Mitigação aplicada: cinto-suspensório `INNER JOIN products + WHERE business_id` em 3 importers + `rowcount==0 → skip`. Detalhes: [feedback-importer-cross-business-bug.md](../feedback-importer-cross-business-bug.md).

## 10. Refs

- [ADR 0066 — format_date shift +3h preservado legacy clientes](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)
- [ADR 0093 — Multi-tenant isolation Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0121 — Modular especializado por vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [dominios-verticais-oimpresso.md](../dominios-verticais-oimpresso.md) — Vestuário CNAE 4781-4/00 vocabulário
- [feedback-importer-cross-business-bug.md](../feedback-importer-cross-business-bug.md) — incidente 2026-05-14
- Funcionários: [larissa](../funcionarios/rotalivre/larissa.md)
- Sessions: `memory/sessions/2026-04-24-*.md` (bateria de bugs)

## Dados úteis pra query

```sql
-- Vendas do ROTA LIVRE num período
SELECT id, invoice_no, transaction_date, created_at, final_total
FROM transactions WHERE business_id=4 AND type='sell'
  AND DATE(created_at) BETWEEN :start AND :end;

-- Users + roles
SELECT u.id, u.username, u.first_name, r.name as role
FROM users u
JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type='App\\User'
JOIN roles r ON r.id = mhr.role_id
WHERE u.business_id=4;

-- Permissões do role Vendas#4 (pra verificar se location ainda existe)
SELECT p.name FROM permissions p
JOIN model_has_permissions mhp ON mhp.permission_id=p.id
JOIN roles r ON r.id=mhp.model_id AND mhp.model_type='Spatie\\Permission\\Models\\Role'
WHERE r.name='Vendas#4';
```
