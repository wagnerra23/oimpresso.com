# PONTE [CL] — Financeiro · deltas da sessão 2026-06-29 (pós-merge)

> [W] "exportar as diferenças para code". [CC] propõe · [CL] faz diff contra `@main` e aplica o que faltar no bundle escopado `.fin-cowork` (`resources/css/fin-cowork.css` + `fin-output.css`) e nas Pages Inertia (`Unificado/Index.tsx` + componentes). NÃO commitado. Roxo 295 intocado.
> Espelhos Cowork (fetch por curl — URLs coladas por [CC] no envio): `financeiro-page.jsx` + `financeiro.css`.
> ⚠️ Vários destes JÁ existem em produção (comparei screenshot WR2). Onde já existir, **não duplicar** — só reconciliar look/comportamento. [CL] confirma no `@main` antes.

## Deltas a portar

**1. Filtro "Contas" = MÚLTIPLO ([W] "as contas são múltiplas").**
Dropdown de checkboxes (não select single). Botão mostra "Todas as contas" / "<conta>" (1) / "N contas" (>1) + "Limpar seleção". Marca várias contas bancárias; filtra a tabela por conta. Se produção tem single select, trocar pra multi. Classe `.fin-contas-filter`/`.fin-contas-pop`/`.fin-contas-opt` (ver `financeiro.css`).

**2. Filtro "Plano de contas" = ÁRVORE ([W] "o plano de contas é árvore?").**
Select hierárquico: optgroup nível-1 (`3 · Receitas` / `4 · Despesas`) › subconta como `<option disabled>` (`3.1 Serviços gráficos`…) › conta-folha indentada selecionável. Label default "Todo o plano de contas". Filtra por folha (= categoria no mock; em prod = `plano_conta_id` com fallback `categoria_id`, Onda 12.7).

**3. Colunas da tabela: Forma · Conta · Baixa** (entre Categoria e Status — paridade produção).
- **Forma**: meio de pagamento (Dinheiro/Boleto/PIX/Transferência). Em prod = campo real do título.
- **Conta**: conta bancária (Itaú PJ, Bradesco, Caixa…). Em prod = relação conta.
- **Baixa**: data de liquidação (`paid_at`/`data_baixa`) ou "—".
(Produção já tem as 3 — provável no-op; confirmar header e ordem.)

**4. CTA "Novo título"** (era "Novo lançamento"). Termo de domínio = título. Renomear botão + título do modal + aria.

**5. Refino premium ([W] "refino premium") — DirIcon + StatusBadge + chips de filtro:**
- `DirIcon` (seta in/out, col. Forma/dir): borda `1px solid color-mix(in oklch, <pos|neg> 22%, transparent)` + `box-shadow 0 1px 3px -1px color-mix(... 28%)`, stroke 2.
- `StatusBadge`: borda `1px solid color-mix(in oklch, <cor-status> 22%, transparent)` mantendo dot + fundo soft (cada status com cor base `c`).
- Chips de filtro de ciclo (`.fin-filter-cb`): bordas translúcidas via `color-mix` (22% off · 50% on) + sombra suave no `.on`; contador transparente na cor do estado (mantém pílula + box ✓).
Aplicar a mesma assinatura nos equivalentes do git pra manter paridade.

## NÃO fazer (decisões [W] desta sessão)
- **NÃO** adicionar faixas de aging (`< 30d / 30-60 / …`) na linha de filtros — [W] "isso eu não quero". (Se produção já tem, é decisão de produção; o protótipo optou por não ter.)
- **NÃO** mover os campos de data pra toolbar — [W] "deve ficar como estava as datas": datas ficam no **PeriodBar** (presets Dia/Semana/Mês/Ano/Tudo + **Personalizado** que revela dd/mm — dd/mm). 
- Roxo 295 intocado. Não editar `_generated-*.css` à mão (DTCG é fonte).

## Em aberto p/ [W]
- Conflito aging: produção TEM faixas de aging; protótipo (por pedido [W]) NÃO. [CL] confirmar com [W] se remove de produção ou mantém divergência.

git diff contra `@main` → aplicar só os deltas reais → PR → merge sob CI verde + OK [W]. Atualizar `SYNC_LOG.md`.
