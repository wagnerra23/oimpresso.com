# Gap map — Cliente/Index vs mockups Cowork (Fase 1 aplicar-prototipo · READ-ONLY)

> Gerado 2026-06-30. Mapeamento de gap entre a **tela viva** e **dois mockups** do bundle Cowork importado pra staging.
> NÃO é aplicação — é diagnóstico barato (read-only) pra decidir o que adotar / o que está stale.

## Arquivos comparados

| Papel | Caminho | Linhas |
|---|---|---|
| **Tela viva** | `resources/js/Pages/Cliente/Index.tsx` (+ `_drawer/*`, `_components/*`) | 2683 |
| Mockup A | `_cowork-handoff-staging/.../project/clientes-page.jsx` (lista/cadastro multi-papel) | 1118 |
| Mockup B | `_cowork-handoff-staging/.../project/crm-page.jsx` (funil/kanban CRM) | 243 |

> ⚠️ Os mockups são **demo data-driven em `window.*`** (mock client-side, sem backend). A tela viva é Inertia/React server-side com `business_id` Tier 0 scope, defer, PII mascarado server-side e drawer 760 de 6 abas + 3 chips. Em quase tudo a tela viva está **muito à frente**.

---

## VEREDITO RÁPIDO

| Mockup | Veredito | Resumo |
|---|---|---|
| **A — clientes-page.jsx** | **MOCKUP-STALE** (com 2 gaps reais menores em ADOTAR-PARCIAL) | A tela viva supera o mockup em todas as partes estruturais. Stale: counters nas tabs, KPI strip dentro do header, drawer cadastral completo. Gaps reais só na perspectiva *Fornecedor/Funcionário/Representante* (vocabulário por papel) e em micro-UX de endereço. |
| **B — crm-page.jsx** | **FORA DE ESCOPO desta tela** (não-gap pra Cliente; pertence a `Modules/Crm`) | É um funil kanban de *deals/oportunidades* — outra entidade, outro módulo. A tela Cliente não deve absorver isso. Já existe `Modules/Crm` vivo com `Wave27DealPipelineTest` + `FunnelStrip.tsx` no Financeiro/Cobranca. |

---

## MOCKUP A — clientes-page.jsx · gap por parte

### A1 · Header / identidade
- **Vivo-à-frente (mockup stale):** PageHeader canon v3.x (ADR 0189/0190) — H1 22px, subnav inline md+ com 6 tabs (`all/customer/supplier/employee/representative/other`), primary roxo universal `oklch(0.55 0.15 295)`, fallback mobile 2ª linha, dropdown ⋮ (Importar/Exportar CSV/Grupos). O mockup tem header `os-page-h` simples (h1+p, botão Importar+CTA) e 5 tabs **com counter visível na tab**.
- **Gap real (mockup tem, vivo não):** o mockup mostra **counter numérico dentro de cada tab** (`cli-moduletopnav-n`). A tela viva **computa** `tab_counts` server-side mas **removeu o badge** da tab (decisão Wagner 2026-05-25: "duplicava o KPI strip"). → **não é gap, é decisão explícita.** Anti-regressão: não re-adicionar.
- **POR QUÊ:** mockup é versão anterior do mesmo design; o vivo é o canon evoluído.
- **Esforço/risco:** — (nada a fazer)

### A2 · KPIs
- **Vivo-à-frente:** `KpiStripClickable` 5 cards-filtro (ativos/comSaldo/vips/sem90/novos), counts **reais server-side** (`kpis.*` scoped, Onda 3), clicáveis aplicam filtro, com `<Deferred>` + skeleton. O mockup tem `cli-kpihero` 6 cards **estáticos derivados client-side** de mock (`KpiHero`), incluindo "Faturamento R$ +12% vs ontem" (número decorativo sem prova).
- **Gap real:** o mockup tem um 6º card **"Faturamento hoje"** que a tela viva não tem. _pendente_ avaliar se faz sentido — exige fonte server real (não o mock "+12%"). Baixa prioridade; o KPI strip vivo é proposital com 5.
- **POR QUÊ:** vivo recusou números sem prova (regra "claim sem evidência"); faturamento-do-dia precisa de query dedicada.
- **Esforço/risco:** P / baixo se houver query pronta; senão M. **Não adotar o mock "+12%"** (viola regra de valor sem 2 caminhos).

### A3 · Filtros / busca
- **Vivo-à-frente:** 6 `FilterDropdown` (Status/Tipo/UF/Tags multi/Sem-compra/Saldo) + busca **server-side** (`router.reload q=`, debounce 300ms, LIKE name/tax/mobile/contact/fantasia scoped) + `ActiveChip` removíveis + "limpar tudo" + contagem "X de Y". O mockup filtra **em memória sobre mock**, sem server, e tem busca client-only.
- **Gap real:** nenhum estrutural. Mockup tem ícone `/` + `⌘K` hint no input — a tela viva já tem **isso e mais** (Command Palette ⌘K, cheat-sheet `?`, J/K nav, Enter abrir).
- **Esforço/risco:** — (vivo à frente)

### A4 · Tabela / linhas
- **Vivo-à-frente:** colunas Avatar HSL-hash · Cliente+sub (fantasia/telefone, supressão de duplicata) · TipoPill · Documento mascarado · Cidade/UF · FrescorPill · SaldoCell (vermelho devedor) · OS · TagChips (+N overflow) · Star localStorage · ActionsMenu (Ver/Editar/Extrato/Excluir gated). Sort **server-side** em 4 colunas, paginação server, VIP badge. O mockup tem colunas equivalentes mas tudo **derivado de hash do mock** (`deriveCli` inventa tipo/UF/cidade/saldo/tags/frescor) — não é dado real.
- **Gap real:** o kebab do mockup tem **"Nova OS"** e **"Gerenciar tags"** como ações de linha. A tela viva não tem essas duas no ActionsMenu. Possível ADOTAR-PARCIAL se houver demanda (Nova OS depende de OficinaAuto/Repair; Gerenciar tags depende de UI de tags). _pendente_ confirmar rota.
- **POR QUÊ:** atalhos de produtividade; vivo priorizou Ver/Editar/Extrato/Excluir.
- **Esforço/risco:** P (link) a M (se precisar fluxo); risco baixo. Gated por módulo (Nova OS).

### A5 · Drawer de detalhe
- **Vivo-à-frente (massivamente):** `ClienteSheet` 760px, 6 abas cadastrais (Identificação/Contato/Endereço/Comercial/Classificação/Operações) com **autosave on blur** + 3 chips header (Placas gated OficinaAuto/anexos/IA) + sub-abas de Operações (Extrato/Vendas/Pagamentos/Documentos/Pessoas/Auditoria) + CNPJ lookup BrasilAPI + "Falar com Copiloto". O mockup tem um drawer read-only de **uma tela só** (KPIs OS + Frescor&Saldo + Contato + Endereços + Histórico de OS).
- **Gap real (micro-UX de endereço — ADOTAR-PARCIAL candidato):** o `CliEnderecoSection` do mockup tem 2 detalhes que valem olhar contra o `EnderecoTab`/`EnderecosEntregaList` vivos:
  1. **Botão "Copiar endereço"** (clipboard formatado) com feedback ✓ 1.6s.
  2. **Toggle "Usar p/ entrega" / badge "Entrega padrão"** por endereço.
  - _pendente_ verificar se `_drawer/EnderecosEntregaList.tsx` já cobre (existe no projeto). Se não cobrir, é gap real pequeno.
- **POR QUÊ:** conveniência operacional (copiar pra colar em romaneio; marcar endereço de entrega default).
- **Esforço/risco:** P cada; risco baixo (UI local, sem tocar valor/estoque).

### A6 · Perspectivas Fornecedor / Funcionário / Representante (o gap real mais relevante do mockup A)
- **Gap real (mockup tem, vivo NÃO — como tela dedicada):** o mockup A tem **4 views completas com vocabulário próprio por papel**:
  - **Fornecedor:** KPIs Críticos/Lead-time médio/A-pagar; colunas Categoria/Lead/Frequência/A-pagar/Vencimento/Pedidos-abertos; kebab "Lançar contas a pagar"/"Histórico de cotações".
  - **Funcionário:** KPIs Comercial/Produção/Férias/Aniversários; colunas Cargo/Setor/Vínculo/Admissão/Turno/Acesso/Status/Aniversário; kebab "Folha de pagamento"/"Registrar férias".
  - **Representante:** KPIs Carteira/Vendas-mês/Top-performer/A-pagar-comissão; colunas Região/Comissão%/Carteira/Vendas-mês/A-pagar; kebab "Lançar comissão"/"Ver carteira".
- **Vivo hoje:** a tela viva tem as **tabs** (`supplier/employee/representative/other`) e troca H1/CTA/contagem por papel, mas reusa **a MESMA tabela e os MESMOS KPIs de cliente** pra todos os papéis — não há colunas/KPIs/kebab especializados por papel.
- **POR QUÊ importa:** fornecedor não tem "Frescor/Saldo-devedor/OS"; tem lead-time e a-pagar. Funcionário tem vínculo/admissão. A tabela genérica de cliente é semanticamente errada nesses papéis.
- **Esforço/risco:** **G** (4 perspectivas × backend real de cada domínio: fornecedor↔compras/AP, funcionário↔RH, representante↔comissões). Risco médio-alto: muitos desses dados (comissão, folha, lead-time) **não existem como modelo** hoje (_pendente_ confirmar). É feature de produto, não polish visual. **Candidato a ADR feature-wish + sinal qualificado**, não a aplicação direta de protótipo.

### A7 · View "Todos" (diretório consolidado)
- **Gap real (menor):** o mockup tem `AllView` — diretório minimal com campos comuns (Nome/Tipo/Documento/Contato) + KPIs por tipo clicáveis que trocam de aba + hint "visão consolidada". A tela viva tem a tab `all` (ROLE_TITLE "Contatos") mas, de novo, usa a tabela cliente completa.
- **Esforço/risco:** P-M; risco baixo. Só relevante se A6 for adotado (faz par com ele).

---

## MOCKUP B — crm-page.jsx · veredito

- **NÃO é gap da tela Cliente.** É um **funil comercial kanban** de *deals* (lead→qualificado→proposta→negociação→ganho) com drag-drop entre colunas, drawer de oportunidade (valor, fluxo de estágio clicável, ações Ligar/WhatsApp/Orçamento, timeline), stats de pipeline/conversão/ciclo-médio, "Novo lead". Entidade = **oportunidade**, não cadastro de cliente.
- **Já existe no projeto vivo (não duplicar):** `Modules/Crm/` (module.json, CrmUtil, **Wave27DealPipelineTest**, MultiTenantIsolationTest) + `resources/js/Pages/Financeiro/Cobranca/_components/FunnelStrip.tsx`. O CRM/funil é território de `Modules/Crm`, não de `Pages/Cliente`.
- **Ação correta:** se for aplicar, é na **fila do módulo Crm** (skill aplicar-prototipo aponta o charter/`page` alvo desse bundle pra Crm, não pra Cliente). Para a tela Cliente: **descartar como gap.**
- **Esforço/risco:** N/A nesta tela.

---

## Síntese de ações (só o que é gap real DESTA tela)

| # | Item | Parte | Esforço | Risco | Recomendação |
|---|---|---|---|---|---|
| 1 | Perspectivas Fornecedor/Funcionário/Representante com colunas+KPIs+kebab próprios | A6 | **G** | médio-alto | ADR feature-wish + sinal qualificado (dados de domínio podem não existir) — NÃO aplicação direta |
| 2 | Copiar-endereço + toggle "entrega padrão" no drawer | A5 | P | baixo | ADOTAR-PARCIAL se `EnderecosEntregaList.tsx` ainda não cobrir (verificar 1º) |
| 3 | Ações de linha "Nova OS" / "Gerenciar tags" no kebab | A4 | P-M | baixo | ADOTAR-PARCIAL condicional (gated módulo / UI de tags) |
| 4 | KPI "Faturamento hoje" (6º card) | A2 | P-M | baixo | _pendente_ — só com fonte server real; NUNCA o mock "+12%" |
| — | View "Todos" diretório minimal | A7 | P-M | baixo | só faz par com #1 |

**Anti-regressões a respeitar (mockup A é stale nestes pontos — NÃO copiar):**
- Counter dentro da tab (removido de propósito 2026-05-25).
- KPI strip dentro do header / KPIs derivados client-side da página.
- Busca/sort/filtro client-side em memória (o vivo é server-side scoped).
- Dados `deriveCli` por hash (tipo/UF/saldo/tags inventados) — o vivo usa dado real.

> Multi-tenant `business_id` Tier 0 intocável em qualquer adoção. PII só mascarado server-side (como já está). Nenhum dos gaps acima toca cálculo de valor/estoque diretamente — mas #1 (a-pagar/comissão/saldo fornecedor) cairia na Regra Mestre de valor se implementado.
