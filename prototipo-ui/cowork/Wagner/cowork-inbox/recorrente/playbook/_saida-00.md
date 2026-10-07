---
sessao: "00"
titulo: "Recibo — PUXAR as 6 Pages vivas de Cobrança Recorrente para o protótipo (uma rota por Index)"
autor: "[CL]"
data: 2026-10-07
base: origin/main 836619f64d
thread: 00-puxar-vivo.md
veredito: "entregue — 4 Index com vista própria (Assinaturas, Planos, Faturas, Configurações), Planos Create/Edit como drawer; as 5 rotas renderizadas sem erro no espelho servido; só cobranca-recorrente-page.jsx tocado no protótipo."
---

# _saída 00 · PUXAR o vivo de Cobrança Recorrente

> A ficha dá esta thread ao [CC]. Quem executou foi o [CL], a pedido do agente-pai. A subida ao
> Cowork não é feita por esta sessão: escrever tela no Claude Design exige opt-in do dono
> (ADR 0315), e quem sobe é a fila de merges. Até a subida, o check
> `espelho — mexeu depois de verificar` acusa o `cobranca-recorrente-page.jsx`. Isso é esperado.

## O que mudou na base da medida

Antes, só a vista Assinaturas existia. Planos, Faturas e Configurações eram um `<Placeholder>`
com texto. A A1 mede os 4 Index, então os 3 placeholders viraram tela.

A medida agora é reprodutível. O protótipo tinha 4 `new Date()`: "há N dias", a próxima cobrança
e o mapa de calor mudavam com o relógio. Todo "hoje" sai de `HOJE = 2026-06-01 12:00`, coerente
com o mock de jun/2026. A barra de período abre ancorada em `HOJE`.

A tabela rota → vista → Page fica em `window.RbRotas`, como o `RepRotas` do #8528. A vista sai em
`data-rb-vista` no `.cr-root`.

## Mapa rota ↔ Page

| rota | vista | Page Inertia |
|---|---|---|
| `recurring` | assinaturas | `RecurringBilling/Index` |
| `rb-assinaturas` | assinaturas | `RecurringBilling/Index` |
| `rb-planos` | planos | `RecurringBilling/Planos/Index` |
| `rb-planos` + botão "Novo plano" | drawer "Novo plano" | `RecurringBilling/Planos/Create` |
| `rb-planos` + botão "Editar" | drawer "Editar plano" | `RecurringBilling/Planos/Edit` |
| `rb-faturas` | faturas | `RecurringBilling/Faturas/Index` |
| `rb-config` | config | `RecurringBilling/Configuracoes/Index` |

Nenhuma rota nova no `app.jsx`. O roteador já mandava as 5 rotas para
`window.CobrancaRecorrentePage` com `view` fixa (`app.jsx:917-921`).

**Para [W] decidir:** Planos Create/Edit não têm rota própria. Uma rota `rb-plano-novo` /
`rb-plano-editar` exige mexer no `app.jsx`, fora do prefixo. Hoje são drawers abertos pelos botões.

## Diff por tela — o que o vivo tem e entrou

Fonte: leitura dos 6 `.tsx` em `resources/js/Pages/RecurringBilling/` e do
`ConfiguracoesController` no `main` `836619f64d`. Nenhum `.tsx` foi editado.

| Page | entrou no protótipo |
|---|---|
| `RecurringBilling/Index` | Título "Cobrança recorrente" e a linha "N ATIVAS · MRR · CHURN %". KPIs com os rótulos do vivo: "Churn este mês" ("N cancelamento(s)"), "Próxima cobrança" (quando + "valor · N cobranças"), "Retentado falhos" ("requer ação"). Coluna de filtros: "Mostrar só favoritos (n)", Status (Todas, Em dia, Retentando, Falharam, Pausadas, Canceladas, com contagem), Próxima cobrança (Qualquer data, Hoje, Amanhã, Esta semana, Próx. 30 dias, Personalizado com De/Até), resumo por plano (preço e nº de ativas), "MRR filtrado" com "N ativ. de M". Busca "Buscar (/) — cliente, CNPJ, OS". Vazio "Nada por aqui." / "Nenhuma assinatura com este filtro + busca." No drawer: botão "PDF", "OS recente", e-mail no contato, "Reenviar" só quando há nota, "Anotar internamente…" + "Anotar", botão "Editar". Drawer "Editar cobrança" (Valor, Ciclo, Forma Boleto/Pix/Cartão, "Salvar alterações"). Drawer "Nova assinatura" (Cliente com busca, Plano "Sem plano (avulso)", Valor, Ciclo, Próxima cobrança, Gateway Banco Inter/Asaas, Forma, Descrição, "Criar assinatura"). |
| `Planos/Index` | Vista nova. Título "Planos · cobrança recorrente", linha "N PLANOS · N ATIVOS · MRR potencial", botão "Novo plano". KPIs "Ticket médio · MRR potencial" (destaque, "MRR total"), "Total planos" ("N inativos"), "Total ativos" ("disponíveis pra novas assinaturas"), "Plano top vendido" ("N assin. · valor"). Distribuição por ciclo (Mensal, Trimestral, Semestral, Anual, Customizado). Busca "Buscar (/) — nome ou slug" + "Buscar". Tabela Plano (nome, slug, descrição curta) · Ciclo · Valor · Assinaturas · Fiscal · Status · Ações (Editar, Excluir). Rodapé "N planos no total · Página 1 de 1". Vazio "Nenhum plano cadastrado." + "Criar primeiro plano". Confirmação de exclusão com aviso quando há assinaturas ativas. Mock: os 4 planos do `SUBS` + 1 inativo. |
| `Planos/Create` · `Edit` | Drawer `PlanoForm`. Identificação (Nome do plano *, Slug — no Edit, aviso ao mudar, Descrição curta, Descrição completa). Cobrança (Valor *, Ciclo * com "Customizado (dias)" → Ciclo (dias) 1–365, Trial (dias) 0–90, "Plano ativo (disponível pra novas assinaturas)"). Emissão fiscal (Tipo; CFOP se NFe; Código de serviço se NFS-e). Rodapé "Cancelar (Esc)" e "Salvar plano (⌘↵)"; no Edit, "Salvar alterações" e cabeçalho "{nome} · #id". |
| `Faturas/Index` | Vista nova. Título "Faturas · cobrança recorrente", linha "N FATURAS · ATRASADAS N". KPIs "Pago este mês" (destaque), "Pendente", "Atrasado" ("N faturas vencidas"), "Total de faturas" ("histórico completo"). Filtros de status (Todas, Pagas, Pendentes, Atrasadas, Canceladas), gateway (Todos gateways, Inter, C6, Asaas), período (Qualquer período, Mês atual, Próximo mês, Apenas atrasadas), busca "Buscar (/) — cliente, CNPJ, número da fatura" e contador "x / N". Tabela Número · Cliente (CNPJ) · Plano ("avulsa") · Valor · Vencimento (data + "há Nd/em Nd") · Status · Gateway · Ações ("Cancelar" ou "—"). Diálogo "Cancelar fatura" (motivo opcional, "Voltar", "Confirmar cancelamento"). Vazio "Nenhuma fatura encontrada." Mock: 11 faturas coerentes com o `SUBS`. |
| `Configuracoes/Index` | Vista nova, 4 cards em 2 colunas. "Gateways de boleto/pix" (Banco Inter PJ · Produção ativo, Asaas sandbox, "Adicionar gateway"). "Régua de dunning (cobrança)" (1ª retentativa +3d, 2ª +7d, 3ª final +15d). "NFe-de-boleto-pago automática" (chave "Desativada", selo "Em breve"). "Webhooks" (Asaas e Banco Inter PJ: POST, URL, "Copiar"/"Copiado!", link "docs", "Autenticação"). URL de exemplo com a empresa 1. |

## Só no protótipo — [W] decide (nada foi apagado)

- Detalhe da assinatura em **drawer**; o vivo usa coluna fixa à direita.
- **Barra de período** (`FinPeriodBar`) ao lado dos presets de data do vivo. As duas ficaram.
- KPI **"A recuperar"**: virou dica do card "Retentado falhos" ("requer ação · valor a recuperar").
- **Ticket médio** na dica do MRR.
- Favorito local (no vivo é persistido), `OiEtapaPainel`, aba **"✦ IA"** (o vivo tem o JanaPanel
  dentro do detalhe), **"Nota pinada"**, CNPJ no bloco de dados, **LTV acumulado**.
- Atalhos **"/"** e **"Esc"**; "N" abre Nova assinatura / Novo plano.

## Ficou fora, e por quê

- **Texto de desenvolvimento do vivo** não entrou como estava. Reescrevi em linguagem de usuário o
  que vale: régua de dunning (sem "past_due → fail" e "detail drawer"), NFe automática (sem
  "Ref. US-RB-044", `fiscal_type = nfe` e `editavel_em`), autenticação do Inter (sem
  `config_json.webhook_secret`), "Adicionar gateway" sem o selo "em breve aqui", e a confirmação
  de exclusão sem "(422)". A nota `POST /financeiro/rb-invoices/{id}/cancelar (US-RB-042)` saiu.
- **"Nova fatura — em breve"**: botão desabilitado no vivo, stub. Não entrou.
- **3 sparklines** com série fixa no código: não entraram.
- **Overlays globais** (Tour, CheatSheet, CmdPalette, Modo apresentação, Troubleshooter). O
  "Diagnosticar" continua como botão no drawer.
- **"Voltar"** e **"atalhos ?"** do cabeçalho; paginação real e atalhos J/K/B/N.
- **Sidebar** (`data.jsx`) e **CSS**: fora do prefixo. Nenhuma classe nova; tabelas, formulários e
  filtros usam as classes `cr-*` existentes e estilo inline com tokens `var(--…)`.
- **Status "reembolsada"** das faturas: o mock não tem caso.

## Provas

- **Render** das 5 rotas no espelho servido por `servirEspelho` (`scripts/design/design-diff-lote.mjs`),
  viewport 1280×900, tema escuro, `localStorage["oimpresso.route"]` gravado antes do load. As 5
  montaram `.cr-root` com **0 erro de página e 0 erro de console**, cada uma na vista esperada
  (`data-rb-vista`: assinaturas, assinaturas, planos, faturas, config), 0 placeholder e
  `RbRotas` com 5 chaves. Interações conferidas: linha → drawer "Padaria Pão Quente" → "Editar
  cobrança" → Esc fecha → "Nova assinatura"; "Novo plano"; "Editar plano" com "Sinalização Mensal
  · #11"; "Excluir plano …?"; "Cancelar fatura"; "Copiar" → "Copiado!".
  - Achado no caminho: o 1º Esc não fechava o "Editar cobrança". O listener do drawer era
    re-registrado a cada render, e o re-render disparado pelo listener da lista tirava ele da fila
    do mesmo evento. Corrigido com `onClose` em ref e registro único.
- `node scripts/design/ds-guard.mjs prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx` → `limpo`.
- `node scripts/design/protocolo.config.mjs --selftest` → OK.
- **Maps:** os 4 `memory/requisitos/RecurringBilling/cobranca-recorrente*.map.json` ficaram STALE
  pelo jsx novo. Regerados com `gerar-map.mjs <gap.md> --atualizar`: 9 + 11 + 12 + 8 partes
  preservadas, 0 nova; muda só `prototipo_sha` (c1c82438c9fa → 89bc8593a0ea) e `gerado_em`.
  `design-code-map-check` sem STALE nos 4. Os ranges de linha do protótipo e a
  `_nota_mapeamento` ("esta aba é só o `<Placeholder>`") ficaram velhos: o gerador preserva o
  preenchido. Re-ancorar é trabalho do dono do map.
- `requisitos-status.mjs RecurringBilling --check` → em dia.
- **Baselines não regravadas por decisão da fila; drift pré-existente de 12 em 9 + o efeito
  deste jsx ficam para o PR único.** Medido antes de editar: `render-proto-baseline --check` no
  `origin/main` `836619f64d` = 12 drifts em 9 baselines (Compras, Financeiro
  conciliação/dre/fluxo/impostos/unificado, KB, Sells, TeamMcp/forja-cockpit). Nenhuma baseline
  ancora no `cobranca-recorrente-page.jsx`.

## O que precisa subir ao Cowork

- `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx`
- `prototipo-ui/cowork/Wagner/cowork-inbox/recorrente/playbook/_saida-00.md`
