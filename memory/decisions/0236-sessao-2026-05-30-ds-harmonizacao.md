# ADR 0236 — Harmonização DS sem perder qualidade + caminho v4.2

- **Data:** 2026-05-30
- **Sessão:** Cowork [CC] ↔ Wagner
- **Status:** aceito (D-01..D-04, D-07) · proposto (D-05, D-06)
- **Supersede:** nada. Estende ADR 0039 (cockpit) e 0114 (cowork loop).
- **Renumeração:** proposto como `0200` no Cowork; renumerado p/ **0236** (próximo nº livre) por colisão com a ADR 0200 já existente no git (*contacts* canon sync bidirecional). Regra ADR 0028 (numeração monotônica). Companheira: ADR 0237 (era `0201`).

## Contexto
Wagner queria aplicar o novo DS aos protótipos mas com **receio de perder a qualidade**
das telas atuais (cada uma "dedicada ao tema"). Auditamos as telas-núcleo e definimos
um padrão de harmonização que preserva a assinatura de cada tela.

## Decisões

### D-01 — DS é piso, não teto
O DS padroniza só o esqueleto compartilhado (componentes, densidade, foco, atalhos).
A assinatura temática de cada tela é **preservada**, não achatada. Harmonizar ≠ uniformizar.

### D-02 — Identidade via `--accent` escopado
Cada tela expressa sua cor por um bloco de token escopado por cima do DS
(`.vendas-scope { --accent: oklch(0.45 0.11 155) }`), nunca por fork de componente
nem hex cru. Os componentes DS herdam `--accent` e se pintam sozinhos.
Registry: Vendas 155 verde · Financeiro 295 roxo · Compras navy · Clientes 262 indigo · CRM 220 azul.

### D-03 — Cadastro grande = página inteira (PT-03)
Cadastros grandes (Vendas, Compras, Cliente/Contato) usam **página inteira** com
nav de seções + form multi-coluna + rail de apoio + savebar. Drawer fica só para
**detalhe / quick-view**. Justificativa: o rail de apoio (preview, prontidão NF-e,
dedup) não cabe em drawer de 420px. Revisa a regra "drawer apenas" do charter, que
valia para detalhe, não para cadastro.

### D-04 — Escopo DS 4.2
Promove ao DS, de forma aditiva (sem redeclarar), o que apareceu em 2+ telas:
`.kpi-cockpit/.kpi-hero`, `.fiscal-badge`, `.sla-pill`, `.readiness`, `.formpage` (PT-03),
`.shortcut-bar`. O que já existe (`.fsm-stepper`, `.viz-*`, `.cmdk`) é documentado, não duplicado.

### D-05 — Cor crua = erro (proposto)
Nenhum hex/oklch fora de `tokens.css`. Drift de cor vira lint, não revisão manual.
Garante que qualidade não regride. (Aguarda confirmação de adoção.)

### D-06 — Protótipo = produção (proposto · norte estratégico)
Cowork e app Inertia/React importam o **mesmo** `tokens.css` + `design-system.css`.
O handoff [CC]→[CL] deixa de ser retradução (onde a qualidade vaza) e vira importação.

### D-07 — Proibições charter (reafirmadas)
Sem CTA WhatsApp cliente-facing · sem modal full-screen p/ detalhe · sem inglês em UI
cliente-facing · sem emoji · sem rounded-xl+ · sem cor fora de token.

## Consequências
- Próximo: replicar molde PT-03 nos 3 cadastros; migrar Compras hex→token; corrigir
  colisão KPI do Financeiro <1100px.
- Memória: criado `STATUS.md` (espinha) + `Painel Cowork - Estado Atual.html`.
- Pendente Wagner: aprovar spec v4.2 → gerar ponte pro Code; executar limpeza canon/archive.
