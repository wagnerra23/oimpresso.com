---
sessao: "00"
titulo: "Recibo — PUXAR as 7 Pages vivas de Cliente para o protótipo (tabela window.CliRotas)"
autor: "[CL]"
data: 2026-10-07
base: origin/main 836619f64d
thread: 00-puxar-vivo.md
veredito: "entregue — 6 rotas cli-* + clientes renderizadas sem erro no espelho servido, cada uma declarando data-page; o que o vivo tem e o protótipo não tinha entrou em Index, drawer, form, Import, Ledger e Map; Show segue sem rota (decisão [W])."
---

# _saída 00 · PUXAR o vivo de Cliente

> A ficha dá esta thread ao [CC]. Quem executou foi o [CL], a pedido do agente-pai. Este recibo
> sobe ao Cowork pelo canal `cowork-inbox/` depois do merge (a isenção de opt-in da ADR 0412 só
> vale para blob já igual ao `origin/main`). Os `.jsx`/`.css` não sobem daqui: escrever tela no
> Claude Design exige opt-in do dono (ADR 0315). Até a subida, o check
> `espelho — mexeu depois de verificar` acusa os arquivos tocados. Isso é esperado.

## Mapa rota ↔ Page

A tabela fica exportada em `window.CliRotas` (`clientes-page.jsx`, no fim). O roteador do
`app.jsx` (:814-820) já manda cada rota para a vista certa. Nenhuma rota nova foi registrada.
Cada vista agora declara `data-page` na raiz. O `design-diff-lote` recusa medir quando o valor
não bate com a tela pedida (`design-diff-lote.mjs`, em `renderDesign`).

| rota | Page Inertia | arquivo do protótipo | o que monta |
|---|---|---|---|
| `clientes` | `Cliente/Index` (+ `_drawer/`, `_components/`) | `clientes-page.jsx` + `cliente-drawer760.jsx` | lista no papel Clientes, sem filtro |
| `cli-novo` | `Cliente/Create` (+ `_form/`) | `cliente-form.jsx` (`modo="novo"`) | formulário em branco |
| `cli-editar` | `Cliente/Edit` (+ `_form/`) | `cliente-form.jsx` (`modo="editar"`) | formulário do 1º de `OS_CLIENTS` |
| `cli-import` | `Cliente/Import` | `cliente-import.jsx` | os 2 passos, sem arquivo |
| `cli-extrato` | `Cliente/Ledger` | `cliente-extrato.jsx` | extrato do 1º cliente, 3 meses até `FIN_TODAY` |
| `cli-mapa` | `Cliente/Map` | `cliente-mapa.jsx` | lista + mapa, nenhum cliente escolhido |
| `cli-grupos` | `Cliente/Grupos/Index` | `cliente-grupos.jsx` | thread 03, não tocado |
| — | `Cliente/Show` (+ `_show/`) | — | sem rota (ver abaixo) |

## Reprodutibilidade — o que foi fixado

- **Esqueleto de 520 ms (`clientes`).** A medida espera duas leituras iguais do DOM, com 200 ms
  entre elas (`design-diff-lote.mjs::esperarEstavel`). O esqueleto dura 520 ms e cabia duas
  leituras iguais nele, então a medida podia fotografar as 6 linhas falsas. Agora, quando a
  lista monta pela primeira vez **na rota com que a página abriu**, ela pula o esqueleto. Os
  scripts lazy carregam todos no boot (`oimpresso.com.html:175-181`), então a rota lida na
  carga do script é a rota de boot. Quem navega até `clientes` depois do boot continua vendo
  o esqueleto.
- **`cli-editar`.** Antes abria em branco, igual ao `cli-novo`. Agora abre com o cliente de
  `window.__CLI_EDITAR_ID` ou, sem ele, o 1º de `OS_CLIENTS` (nome, documento, celular,
  endereço principal). Nada navega para `cli-editar` hoje, então não há caminho interativo
  afetado.
- **Drawer, "cadastrado há …".** Usava `Date.now()`. Agora usa `FIN_TODAY`, o mesmo relógio da
  lista e do Financeiro.
- **`cli-extrato`.** Já usava `FIN_TODAY` e o cliente de `window.__CLI_EXTRATO_ID` (fallback
  `OS_CLIENTS[0]`). Nada mudou.
- **`cli-import`.** O `Math.random` só roda no progresso, depois do clique. Nada mudou.
- **Storage — NÃO ignorado, ao contrário da proposta.** As medidas abrem contexto novo e só
  gravam `oimpresso.route` (`design-diff-lote.mjs:704`, `render-proto-baseline.mjs:430`), então
  `oimpresso.clientes.favorites`, `.status`, `.creditos`, `oimpresso.outros.papeis` e
  `oimpresso.clientes.grupos.v2` já começam vazios na medida. Ignorar o storage apagaria os
  favoritos de quem recarrega a página. `window.__PESSOAS_ROLE_INITIAL` e
  `window.__CLI_GRUPO_INICIAL` só existem depois de um clique na tela de Grupos.
- **Visão Funcionários (`new Date()`, linha 1517).** Não foi mexida: não é a vista da rota.

## Diff por tela — o que o vivo tem e entrou

Fonte: leitura dos `.tsx` em `resources/js/Pages/Cliente/` no `main` `836619f64d`. Nenhum
`.tsx` foi editado.

| Page | entrou no protótipo |
|---|---|
| `Index` | Filtro Status com as opções de OS do vivo: "Ativo (com OS aberta)", "Atrasado", "Sem OS" (Index.tsx:1010-1012). As de cadastro ficaram, e o "Ativo" delas virou "Cadastro ativo" para não repetir o rótulo. KPI "Clientes ativos" passou a contar e filtrar quem tem OS aberta, com o subtítulo "com OS aberta" (KpiStripClickable.tsx:139-140). Saldo "Em débito"/"Sem saldo" (Index.tsx:357-358). "Sem compra há" com "6 meses" e "1 ano" (Index.tsx:348-354). "Limpar" no topo do filtro simples e "Limpar tudo" no múltiplo, só com algo marcado (Index.tsx:1536-1552). Atalho "Esc · fechar modal ou cancelar foco" na lista de atalhos, e o Esc tira o foco da busca (Index.tsx:2585). Seção de endereços com o título "Endereços de entrega / comerciais" e o vazio "Nenhum endereço cadastrado ainda." (EnderecosEntregaList.tsx). |
| `_drawer` | Identificação: "Contato principal", "Cargo do contato" e, só para PF, "Data de nascimento" (input date). Contato: "Telefone principal", "Telefone alternativo", "Telefone 3" (recados), "E-mail comercial" (vendedor), "E-mail NF-e" (contador). Classificação: "VIP" com "prioridade na agenda de produção", e "Bloqueio comercial" com "Bloquear cobrança/venda". Cabeçalho: "Falar com a Jana →" como botão primário. Chips no formato do vivo: "N placas", "IA", "N anexos", com os títulos do vivo. |
| `Create` · `Edit` | Títulos "Novo cliente" / "Editar cliente". Botões "Salvar cliente" / "Salvar alterações". Volta "← Voltar para clientes" / "← Voltar para detalhe" (Create.tsx:53, Edit.tsx:80). Endereço ganhou "Endereço de entrega" (ClienteForm.tsx:207). Financeiro ganhou "Saldo inicial" (ClienteForm.tsx:223). O grupo virou "Grupo de clientes" com "— Nenhum —" (ClienteForm.tsx:240, :249). |
| `Import` | "← Voltar para clientes", "Passo 1 — Baixe o template", "Baixar template", "Passo 2 — Envie o arquivo", "Arquivos aceitos: .xlsx, .csv. Tamanho máximo: 10 MB.", "Clique ou arraste o arquivo aqui" / ".xlsx ou .csv até 10 MB" / "Clique pra escolher outro", "Enviando… N%", botão "Cancelar" (Import.tsx:161-256). |
| `Ledger` | Título "Extrato — {nome}" com o documento embaixo ("Documento não informado" sem ele). "← Voltar para detalhe". KPIs "Total débitos", "Total créditos", "Saldo atual". Filtros "Data inicial" / "Data final". Formatos "Padrão", "Resumido", "Detalhado por linha". Vazio "Nenhum lançamento no período selecionado." (Ledger.tsx:108-208). |
| `Map` | "← Voltar para clientes". Subtítulo "N clientes com posição registrada de M total." No painel do cliente: "Ver detalhes →", "Abrir no mapa completo →" e "• celular" (Map.tsx:90-212). |
| `Show` | Nada (sem rota). |

## Correções à leitura anterior

- **Rótulos curtos "Fornec." / "Equipe" / "Repr."** existem no `SLOT2_TABS` (Index.tsx:220-227),
  mas o vivo não os mostra: `contactGhosts` só passa `label` (Index.tsx:821-827). Não entraram.
- **"Inscrição municipal"** não está no drawer vivo, mas está no card Fiscal do `Show`
  (Show.tsx:445). Fica no drawer do protótipo e vai para a decisão do Show.
- **Painel "Contexto do cadastro" do form** não é só do protótipo: o vivo tem o `ClienteRail`
  (pré-visualização + "Prontidão fiscal"). O protótipo já espelhava os checks.

## Só no protótipo — [W] decide, nada foi apagado

- **Index:** coluna "Última OS"; filtro "Grupo"; saldo "Com crédito a favor"; opções de status
  de cadastro (Cadastro ativo/Inativo/Bloqueado) no filtro Status; "Lançar crédito" e
  "Desativar/Ativar cadastro" no menu da linha; "Mapa de clientes" no ⋮ do topo; KPI escuro
  "Faturamento" com "+12% vs ontem"; visões por papel com colunas próprias (Fornecedor,
  Funcionário, Representante, Outros).
- **Drawer:** "Inscrição municipal"; "Como é conhecido" (PF; no vivo o nome fantasia é só PJ);
  WhatsApp como campo próprio; chip "Risco" (o vivo tem o `RiscoClienteCard` no Show); chip de
  placas sem a condição do OficinaAuto (o vivo só mostra com o módulo ligado); selo de status
  do cadastro no cabeçalho (o vivo mostra "Sem OS"/"Ativo", que é status de OS).
- **Drawer › Endereço:** o vivo tem o endereço principal como formulário (CEP, Número,
  Endereço, Complemento, Bairro, Cidade, UF, com autosave) e, abaixo, a lista de endereços
  extras. O protótipo mostra o principal como um cartão da lista. Não fundi.
- **Form:** endereço quebrado em Logradouro/Número/Complemento/Bairro; "Site"; "Prazo padrão",
  "Forma de pagamento preferida", "Mensagem para a venda" no Financeiro.
- **Import:** banner de resultado parcial, "Baixar as linhas com erro", "Tirar o arquivo",
  aceite de `.xls` no texto do drop (o vivo aceita `.xls` no input, mas o texto diz
  ".xlsx, .csv").
- **Ledger:** "Enviar por e-mail"; coluna Local na tabela; linha "Total do período"; modo
  resumido por mês; linhas de item no detalhado; filtro aplicado ao vivo (o vivo tem botão
  "Aplicar" que vai ao servidor).
- **Map:** item sem posição clicável com mensagem explicando o CEP. O vivo desabilita o item.
  Mantive o protótipo: a mensagem ensina o que fazer, e o desabilitado não diz nada.

### Show — rota própria ou fundir no drawer?

O que só o `Show` tem, e o drawer não: página cheia com 9 abas (Extrato, Vendas, Pagamentos,
"Documentos & Notas", Atividades, Pessoas, Veículos só com OficinaAuto, Assinaturas, Pontos;
Show.tsx:124-134). A aba **Atividades** não existe nas 8 sub-abas de Operações do drawer.
4 KPIs (Total vendido, A receber, Total comprado, Saldo abertura; Show.tsx:198-206). Card
Contato (Celular, Fixo, E-mail, Endereço). Card Fiscal (CPF/CNPJ, Nome fantasia, IE, IM,
Indicador IE, Regime, SUFRAMA; Show.tsx:436-456). `RiscoClienteCard`. `ActionsMenu` com
"adicionar desconto" (`AddDiscountModal`). Criar `cli-show` exige mexer no `app.jsx`, fora do
prefixo desta thread.

## Ficou fora, e por quê

- **"1 pendência" no rodapé do drawer.** No vivo é placeholder fixo (Index.tsx:2196, comentário
  "Wave G calcula contagem real"). O protótipo copiou o defeito em `cliente-drawer760.jsx`. Não
  removi sem decisão de [W]. O "Salvar" do mesmo rodapé também é placeholder no vivo.
- **Busca rápida da página (⌘K) com "Novo cliente", "Importar clientes", "Limpar filtros desta
  página"** (Index.tsx:2398-2400). No protótipo o ⌘K é a busca global do shell (`app.jsx`,
  fora do prefixo). Uma segunda paleta na página brigaria pela mesma tecla.
- **"Tax number (legado UPOS)".** Jargão de desenvolvimento. O protótipo segue com "Tax number"
  e a ajuda "Campo legado do UPOS".
- **"Dados do lead"** (DadosLeadSection) vem do LeadController do Crm, fora desta tela.
- **"Extensão PHP Zip indisponível"** (Import.tsx:98-105) e "Dedup… chegam em breve": estado
  de servidor e promessa, não tela.
- **Sidebar e rotas novas** (`data.jsx`, `app.jsx`): fora do prefixo.

## Provas

1. **Render** no espelho servido por `servirEspelho` (`design-diff-lote.mjs`), 1280×900, um
   contexto novo por rota, `oimpresso.route` gravado antes do load, OSM bloqueado. Script
   playwright no scratchpad da sessão, fora do repo.

   | rota | erros de página | erros de console | `data-page` | h1 | esqueleto no boot |
   |---|---|---|---|---|---|
   | `clientes` | 0 | 0 | `Cliente/Index` | Clientes | 0 linhas |
   | `cli-novo` | 0 | 0 | `Cliente/Create` | Novo cliente | — |
   | `cli-editar` | 0 | 0 | `Cliente/Edit` | Editar cliente | — |
   | `cli-import` | 0 | 0 | `Cliente/Import` | Importar clientes | — |
   | `cli-extrato` | 0 | 0 | `Cliente/Ledger` | Extrato — Acme Comércio Ltda | — |
   | `cli-mapa` | 0 | 0 | `Cliente/Map` | Mapa de clientes | — |
   | `cli-grupos` | 0 | 0 | — (thread 03) | Grupos de cliente | — |

   `window.CliRotas` com 7 rotas. Drawer do 1º cliente: chips `2 placas · Risco · IA · 0 anexos`,
   botões `Imprimir ficha · Falar com a Jana →`. Filtro Status: `Todos · Ativo (com OS aberta) 13 ·
   Atrasado 0 · Sem OS 2 · Cadastro ativo 13 · Inativo 1 · Bloqueado 1`.
2. **Controle do esqueleto.** Boot em `cli-import`, depois `__go('clientes')`: 6 linhas de
   esqueleto aos 80 ms, 0 aos ~1 s. O caminho interativo segue igual.
3. **Controle de reprodutibilidade.** Dois renders de `clientes` em contextos novos: o
   `innerText` da página saiu igual (2443 caracteres). `cli-editar` abriu com
   "Acme Comércio Ltda" e "12.345.678/0001-90".
4. `node scripts/design/ds-guard.mjs <cada um dos 8 arquivos tocados>` → `limpo` nos 8.
5. `node scripts/design/protocolo.config.mjs --selftest` → OK.
6. `memory/requisitos/Cliente/clientes.map.json` regenerado com
   `node scripts/design/gerar-map.mjs memory/requisitos/Cliente/clientes-gap.md --atualizar`:
   7 partes preservadas, `prototipo_sha` `sha256:88c274fec65d` → `sha256:75ed75ab0ddf`. Nenhum
   outro `*.map.json` cita os arquivos tocados (`grep` em `memory/`).
   `node scripts/governance/requisitos-status.mjs Cliente --check` → em dia, nada a regravar.
7. **Baselines não regravadas por decisão da fila.** `node scripts/design/render-proto-baseline.mjs
   --check` no `origin/main` `836619f64d`, antes de editar: **12 drifts em 9 baselines** (Compras,
   Financeiro conciliacao/dre/fluxo/impostos/unificado, KB, Sells, TeamMcp/forja-cockpit), as 9
   que existem. O drift pré-existente de 12 em 9 e o efeito deste jsx ficam para o PR único de
   regravação, depois dos 6 PUXAR.

## O que precisa subir ao Cowork

- `prototipo-ui/cowork/Wagner/clientes-page.jsx` e `clientes-page.css`
- `prototipo-ui/cowork/Wagner/cliente-drawer760.jsx`
- `prototipo-ui/cowork/Wagner/cliente-form.jsx`
- `prototipo-ui/cowork/Wagner/cliente-import.jsx`
- `prototipo-ui/cowork/Wagner/cliente-extrato.jsx`
- `prototipo-ui/cowork/Wagner/cliente-mapa.jsx` e `cliente-telas.css`
- `prototipo-ui/cowork/Wagner/cowork-inbox/cliente/playbook/_saida-00.md`
