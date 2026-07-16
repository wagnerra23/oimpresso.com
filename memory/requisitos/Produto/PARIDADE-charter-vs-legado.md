---
titulo: "Paridade — Charters React × Cadastro de Produto legado (mapa de gaps do cutover)"
tipo: paridade
module: Produto
status: ativo
owner: wagner
gerado: 2026-07-13
fontes:
  - resources/js/Pages/Produto/Create.charter.md (+ 7 charters irmãos)
  - memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md
  - memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md
  - memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md
observacao: "Cruzamento contrato-vivo × comportamento legado. Base de decisão do cutover — o que falta virar charter/US antes de aposentar o Delphi."
---

# Paridade — Charters React × Cadastro de Produto legado

> **Pergunta que este doc responde:** o `Create.charter.md` (e irmãos) é o contrato de paridade
> suficiente pra migrar o cadastro de produto do Office Comercial (Delphi) pro oimpresso? **Não.**
> O charter de Create cobre ~10-15% da tela legada (é um *Wave 2 draft* do formulário básico).
> Este doc mapeia, item a item, o que **já está no charter**, o que mora em **telas-irmãs**, e o
> que **não tem casa em nenhum charter** — que é o backlog real do cutover.
>
> **Insumos:** `Create.charter.md` (`status: draft`, Tier A, charter_version 1) + a lista
> anti-regressão de ~140 itens (`ANTI-REGRESSAO-cadastro-produto-legacy.md` + `-variacao-legado.md`).

---

## 0. Resumo executivo

| Métrica | Valor |
|---|---|
| Itens anti-regressão catalogados | **~140** (`AR-PROD-001..187`) |
| Cobertos pelo `Create.charter.md` | **~15** (formulário básico) |
| Mapeáveis a telas-irmãs (charter existe, cobertura parcial) | ~25 |
| **Sem casa em nenhum charter atual** | **~100** ⚠️ |
| Colisão direta charter Non-Goal × legado | **2 áreas inteiras** (Composição, Variação) 🔴 |

**Leitura:** o `Create.charter` é honesto sobre seu escopo mínimo, mas **não é o contrato de paridade**
da migração. O cadastro legado é uma tela-mãe de 8 abas + Composição + Variação; a arquitetura nova
espalha isso em 8 páginas — e a maioria das funções de valor/fiscal/produção ainda **não foi contratada**.

---

## 1. O que o `Create.charter.md` COBRE ✅ (~15 itens)

| Charter (Goals) | Item anti-regressão |
|---|---|
| 8 campos sempre visíveis: name · sku · type · unit · category · brand · tax · alert_quantity | AR-PROD-001, 002, 005, 011, 014, 053 |
| Avançado: barcode_type · sub_category · sub_units · weight · description · enable_sr_no · expiry · racks · custom_fields 1-20 | AR-PROD-010, 042, 004 (parcial) |
| Card "Estoque" + "Localizações" (opening stock) | AR-PROD-050, 051, 055 (parcial) |
| SKU server-side + duplicate `?d=N` + multi-tenant scope | AR-PROD-002, 007 (dup), 010 |
| Defaults type=single · enable_stock=true · tax_type=exclusive | AR-PROD-001 |

> O charter também acerta os invariantes Tier 0 (business_id, sem sessionStorage, SKU server-side) —
> alinhado com o SDD §3.

---

## 2. Legado que mora em TELAS-IRMÃS (charter existe, cobertura parcial) 🟡

Na arquitetura nova, abas do legado viram páginas separadas. Precisam do seu próprio cruzamento:

| Aba legada | Itens | Página nova / charter | Estado |
|---|---|---|---|
| Estoque › Histórico de Movimento (kardex) | AR-PROD-060..065 | `StockHistory.charter.md` | 🔴 grade 47 "fachada" (`movements` undefined) — G-01 do SDD |
| Custos e Tabelas de Preços | AR-PROD-090..109 | `SellingPrices.charter.md` / `Unificado` | 🟡 multiplicador oco (G-02); Formação de Preço ausente |
| Estoque › Fornecedor | AR-PROD-070..075 | `Unificado` (insumos) | ❌ `fornecedor => null` (C18 do SDD) |
| Estoque › Compras | AR-PROD-080..084 | `Unificado` / `Show` | ❌ sem cobertura |
| Estoque › Geral + saldo por local | AR-PROD-050..057, 144..145 | `StockHistory` / `Unificado` | 🟡 parcial |

---

## 3. Legado SEM CASA em nenhum charter ⚠️ (o backlog real do cutover · ~100 itens)

Nenhuma página/charter atual contempla estas áreas — cada uma precisa virar charter + US:

| Área legada | Itens | Por que importa |
|---|---|---|
| **Aba Fiscal** (NCM · CEST · origem · grupo imposto · PAF-ECF IAT/IPPT · pesos) | AR-PROD-124..130 | o charter só tem o campo `tax`; NF-e depende disso |
| **Formação de Preço** (markup composto · rendimento última compra · dimensões Larg/Comp/Espessura · valor mínimo · flags pode comprar/vender/movimenta estoque) | AR-PROD-090..103 | é o motor de custo/margem — Tier 0 valor |
| **Preço Especial por cliente** (valor original ± %acréscimo/%desconto) | AR-PROD-111..116 | preço vinculado a cliente |
| **Anexo** (visibilidade cadastro/venda/produção · caminho de rede) | AR-PROD-117..123 | charter diz "1 imagem só"; é a "arte anexada" (F4 do SDD) |
| **Atividade** (histórico de alterações do cadastro) | AR-PROD-131..134 | auditoria append-only |
| **Ícones de estoque do cabeçalho** (ajuste manual E/S · saldo por local) | AR-PROD-012, 140..145 | movimento de estoque manual |
| **Excluir** (soft-delete → inativo + filtro) | AR-PROD-022 | Create é create-only; falta o ciclo de exclusão |
| **Dados Adicionais** (Plano de Contas · Marca) | AR-PROD-040..041 | vínculo contábil |

---

## 4. Colisão direta charter Non-Goal × legado 🔴 (maior risco de paridade)

O `Create.charter` **declara Non-Goal** exatamente as duas features mais ricas — e mais valiosas pras
verticais comunicação visual/oficina — que o legado já tem por completo:

| Charter Non-Goal (adiado p/ "Wave 3") | Legado equivalente (anti-regressão) |
|---|---|
| ❌ "Variation builder dinâmico inline (variable — Wave 3)" | **Aba Variação inteira** — AR-PROD-170..187 (grade tam×cor · preço por quantidade com filho vinculado · tipo de cálculo Até/Acima de · % desconto ou acréscimo · Modelo de Grade reutilizável) |
| ❌ "Combo composition picker inline (combo — Wave 3)" | **Aba Composição inteira** — AR-PROD-150..168 (BOM multi-nível `ORDEM_ARVORE` · 11 fórmulas ÁREA QUADRADA/PERÍMETRO/ILHÓS/FOLHAS-CHAPA/BARRAS · planilha embutida · Produzir · Diferença no Valor) |

> ⚠️ São **as fórmulas de m²/perímetro/ilhós** (comunicação visual) e a **grade tam×cor** (vestuário/
> oficina) — o núcleo do diferencial vertical do oimpresso (SDD §1.0). O único charter existente as
> empurra pra "Wave 3" sem contrato. Também colide com o dicionário de domínio, que diz
> `products.type ∈ {single, variable, modifier}` — **`combo`/kit não existe** ("não inventar",
> `memory/dominio/estoque.md`): a composição legada tem que virar `ProductBom` + motor de fórmula,
> **não** `type=combo`.

---

## 5. Recomendação — roadmap de charters pro cutover

Ordem sugerida (cada um vira charter Tier A + US no SPEC + casos.md ancorado nos `AR-PROD-*`):

1. **Formação de Preço** (AR-PROD-090..103) — Tier 0 valor; destrava custo/margem correto. Pré-req de tudo que emite NF/vende.
2. **Aba Fiscal** (AR-PROD-124..130) — sem NCM/CEST/origem não emite NF-e.
3. **Composição/BOM + fórmulas** (AR-PROD-150..168) — a perna de comunicação visual (CV-01/CV-03 do SDD); a mais rica e a que o legado já resolvia.
4. **Variação/Grade** (AR-PROD-170..187) — grade tam×cor + preço por quantidade.
5. **Kardex real** (AR-PROD-060..065) — fecha a fachada `StockHistory` (G-01).
6. **Fornecedor + Compras + Preço Especial + Anexo + Atividade + Excluir** (AR-PROD-070..084, 111..134, 022) — completam a paridade.

**Gate de cada item:** US no SPEC → casos.md com UC-IDs ancorados nos `AR-PROD-*` → Pest failing-first
(`[V0]` onde toca valor) → charter draft→live com smoke biz=1 → (se V0) dupla-confirmação + aprovação.

---

## 6. Referências

- Charters: `resources/js/Pages/Produto/*.charter.md` (8, todas `draft`)
- Anti-regressão: [ANTI-REGRESSAO-cadastro-produto-legacy.md](ANTI-REGRESSAO-cadastro-produto-legacy.md) · [ANTI-REGRESSAO-cadastro-produto-variacao-legado.md](ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md)
- SDD: [SDD-tela-cadastro-produto-v1.0.md](SDD-tela-cadastro-produto-v1.0.md)
- Manual legado: `memory/dominios/wr-comercial/modulos/estoque/tabelas/PRODUTO.md` (+ satélites)
- SPEC/gaps: [SPEC.md](SPEC.md) (US-PROD-020..026) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (61/100)

---

**Histórico:** 2026-07-13 — Cruzamento criado a partir do `Create.charter.md` × lista anti-regressão
(~140 itens). Conclusão: charter atual cobre ~15%; Composição e Variação (núcleo das verticais) estão
como Non-Goal adiado. [CC]
