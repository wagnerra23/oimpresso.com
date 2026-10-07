---
sessao: "00"
titulo: "Recibo — PUXAR as 13 Pages vivas de Essentials para o protótipo (uma rota ess-* por Page)"
autor: "[CL]"
data: 2026-10-07
base: origin/main 836619f64d
thread: 00-puxar-vivo.md
veredito: "entregue 11 de 13 Pages em rota ess-* fixa (window.EssRotas, 12 rotas); Settings/Index e Holidays/Index ficam com hrm-config e hrm-feriados (moram em /hrm/*). 16 de 17 rotas renderizadas sem erro; a 17ª (`essenciais`) quebra no main também, por ordem de carga do shell. Só essenciais-page.jsx tocado."
---

# _saída 00 · PUXAR o vivo de Essenciais

> A ficha dá esta thread ao [CC]. Quem executou foi o [CL], a pedido do agente-pai. Nada subiu ao
> Cowork: escrever no Claude Design exige opt-in do dono (ADR 0315). Até a subida, o check
> `espelho — mexeu depois de verificar` acusa o `essenciais-page.jsx`. Isso é esperado.

## Mapa rota `ess-*` ↔ Page

As rotas ficam exportadas em `window.EssRotas`. Cada uma fixa a aba; as de formulário e de detalhe
abrem o painel lateral com um item fixo. Pela D1 ([W] 06/10), Create/Edit/Show de Conhecimento e
de Tarefas viram painel lateral (PT-02) da lista. No protótipo foi assim que encaixou: as quatro
Pages de Tarefas e as quatro de Conhecimento são a mesma aba, com ou sem o painel aberto.

| rota | Page Inertia | o que monta |
|---|---|---|
| `ess-tarefas` | `Essentials/Todo/Index` | aba Tarefas |
| `ess-tarefa-nova` | `Essentials/Todo/Create` | Tarefas + painel "Nova tarefa" |
| `ess-tarefa-editar` | `Essentials/Todo/Edit` | Tarefas + painel "Editar tarefa TAR2026/0012" |
| `ess-tarefa-ver` | `Essentials/Todo/Show` | Tarefas + painel da tarefa TAR2026/0012 |
| `ess-documentos` | `Essentials/Documents/Index` | aba Documentos |
| `ess-memorandos` | `Essentials/Documents/Index` (aba Memorandos da mesma Page) | aba Memorandos |
| `ess-lembretes` | `Essentials/Reminders/Index` | aba Lembretes (calendário) |
| `ess-mensagens` | `Essentials/Messages/Index` | aba Mensagens (mural) |
| `ess-kb` | `Essentials/Knowledge/Index` | aba Base de conhecimento |
| `ess-kb-novo` | `Essentials/Knowledge/Create` | Base + painel "Novo livro" |
| `ess-kb-editar` | `Essentials/Knowledge/Edit` | Base + painel "Editar seção" (seção 11) |
| `ess-kb-ver` | `Essentials/Knowledge/Show` | Base + painel da seção 11, "Receber arte do cliente" |
| `hrm-config` (já existia, fora do prefixo) | `Essentials/Settings/Index` | — |
| `hrm-feriados` (já existia, fora do prefixo) | `Essentials/Holidays/Index` | — |

Nenhuma rota nova foi registrada no `app.jsx`. O roteador já manda todo `ess-*` para
`window.EssenciaisPage` (`app.jsx:865`), e a vista é escolhida dentro do `essenciais-page.jsx`. O
corpo da página remonta a cada troca de rota (`key` inclui a rota). Por isso o painel da rota abre
em qualquer chegada, e não só na primeira.

**Reprodutível sem localStorage, com uma ressalva.** A rota não lê storage. Os simuladores de papel
e estado do HRM leem (`oimpresso.hrm.papel`, `oimpresso.hrm.estado`, `hrm-ui.jsx:238`). Em contexto
limpo, que é o da medição, valem `admin` e `normal`.

### Settings e Holidays — de qual roteiro (a pergunta do índice §4)

Os dois são do roteiro **hrm**:

- o vivo serve as duas em `/hrm/settings` e `/hrm/holiday` (`Modules/Essentials/Routes/web.php:67,94`);
- o menu RH lista os dois como ghost do HRM, "Feriados" `/hrm/holiday` e "Configurações" `/hrm/settings`
  (`DataController.php:370` e `:372`);
- o breadcrumb do `Settings/Index.tsx` é `HRM › Configurações`;
- o protótipo já tem as duas vistas: `hrm-feriados` (`hrm-page.jsx:349`) e `hrm-config`. O `Config`
  de `hrm-extras.jsx:264-276` tem os mesmos campos do vivo: os três prefixos, as instruções de
  afastamento e a meta de vendas sem imposto.

Criar uma rota `ess-*` para elas seria uma segunda vista da mesma Page. A aba `ess-config` do
protótipo **não** é a `Settings/Index` viva. Ela está listada em "só-protótipo".

## Diff por tela — o que o vivo tem e entrou

Fonte: leitura dos 13 `.tsx` em `resources/js/Pages/Essentials/{Todo,Documents,Messages,Reminders,Knowledge,Settings,Holidays}/`
no `main` `836619f64d`. Os `Inertia::render` de `Modules/Essentials` foram conferidos: nenhuma Page
fica fora do índice além de `Licencas`, `Tipos`, `Metas` e `Painel`, que são do roteiro hrm. Nenhum
`.tsx` foi editado.

| Page | entrou no protótipo |
|---|---|
| todas de Tarefas | Rótulos de situação e prioridade do vivo (`ToDo::getTaskStatus/getTaskPriorities`, lang pt): **Novo · Em progresso · Em espera · Terminado** e **Baixo · Médio · Alto · Urgente**. Antes eram Nova/Em andamento/Concluída e Baixa/Média/Alta. A troca é feita em `essenciais-page.jsx`, por cima de `E.ST_TAR`/`E.PRIO`. O lugar certo é `essenciais-data.jsx` (ver "O que precisa subir"). |
| `Todo/Index` | Coluna **Prioridade** (badge; antes ia no subtítulo da tarefa). Coluna **Ações** com Editar, Ver e Excluir. Botão **"Limpar tudo"** quando há filtro. Dica `/ buscar · n novo`. O vazio passa a decidir por "tem filtro?", como no vivo, e não pelo modo "Primeira vez". Ganhou as ações "Limpar busca e filtros" e "Adicionar". Excluir agora pede confirmação: "Remover tarefa?", com a frase do vivo "… será excluída definitivamente, junto com seus comentários e anexos. Essa ação não pode ser desfeita." Vale para a linha, para o lote e para o painel. |
| `Todo/Create` | Título **"Nova tarefa"** e subtítulo "Após criar, você pode adicionar comentários e anexos na tela de detalhe.". Seção **"Atribuir a \*"** com "{n} usuário(s) selecionado(s). Se nenhum for escolhido, a tarefa fica atribuída só a você.", e o comportamento vai junto: sem ninguém escolhido, a tarefa fica atribuída a quem cria. Rótulos "Status" e **"Previsão de término"**. Placeholders "Ex: Enviar relatório mensal ao financeiro", "Ex: 4" e "Detalhe o que precisa ser feito, critérios de aceite, contexto…". **Saiu** o subtítulo técnico "essentials/todo · campos do todo/create.blade" e a nota "No main o anexo sobe por dropzone…". |
| `Todo/Edit` | Título **"Editar tarefa {ID}"**. Contador "{n} usuário(s) selecionado(s)." sem a regra do vazio, e sem placeholders, como no vivo. |
| `Todo/Show` | Subtítulo "{ID} · Criada em {data}". Seção **Dados** com Início, Previsão de término, Horas estimadas, Criada por, Atribuída a e Descrição, e o botão **"Docs compartilhados"**. **Comentários**: campo "Escreva um comentário sobre essa tarefa…", botão **Enviar**, vazio "Ainda não há comentários nesta tarefa." e **Remover** no comentário próprio. **Anexos (n)**, novo no painel: "Anexar arquivos" (múltiplos), "Até 10 MB por arquivo. PDF, imagens, planilhas, documentos e arquivos compactados.", "Descrição (opcional)" com placeholder "Ex: Relatório final do mês", botão **"Enviar anexos"** e vazio "Nenhum anexo enviado ainda.". **Atividades (n)** substitui "Histórico de situação" no título. O conteúdo ainda é o `X.Historico`. |
| `Documents/Index` | Subtítulos do vivo: "Arquivos compartilhados com a equipe." e "Avisos e memorandos internos em texto.". Na linha, **"Compartilhado por {nome}"** quando o item não é seu. **Compartilhar e Excluir só para o item próprio** (`is_mine`). Excluir confirma: "Remover arquivo?" / "Remover memo?" e "\"{nome}\" será apagado junto com seus compartilhamentos.". O formulário ganha cabeçalho "Enviar arquivo", o texto "Arquivos ficam disponíveis para você…", o rótulo "Arquivo \*" e "Descrição (opcional)". O memorando passa a "Novo memo" / "Aviso em texto compartilhado com a equipe.", com **"Corpo \*"** obrigatório e o botão **"Criar"**. O vazio ganhou a ação "Adicionar". O memorando aberto mostra "{data} · por {nome}". |
| `Messages/Index` | Subtítulo do vivo, sem o "— é o que o blade entrega.". Vazios "Ainda não há mensagens. Seja o primeiro a escrever." e 'Nenhuma mensagem desta localidade. Escolha "Todas as localidades" pra ver o mural inteiro.'. A mensagem própria aparece como **"Você"**, com **Remover**, que confirma com "Remover mensagem?" e "Essa ação não pode ser desfeita.". No compositor, o seletor começa em **"Todas as lojas"**, e **Enter envia** (Shift+Enter quebra linha). O protótipo usava Ctrl+Enter. Sem permissão, a frase é "Você não tem permissão para ver mensagens.". |
| `Reminders/Index` | **"Voltar pra hoje"** quando o mês muda, "{n} lembrete(s) cadastrado(s)" e o vazio "Nenhum lembrete cadastrado. Use Adicionar lembrete — ele aparece no dia marcado, e repete conforme a recorrência escolhida.". O formulário passa a **"Novo lembrete" / "Editar lembrete"**, com Nome \*, Data \*, Hora \*, Fim (opcional) e Repetição, e o botão **Salvar**. O detalhe ganha **Editar** e Excluir com confirmação "Remover lembrete?" / "\"{nome}\" será removido permanentemente.". Saiu "Evento" da lista de dados, porque o nome já é o título. |
| `Knowledge/Index` | A árvore e a leitura seguem as do protótipo (`X.BaseConhecimento`). As rotas abrem o painel do vivo por cima. A copy da Index viva está em "fora do prefixo". |
| `Knowledge/Create` | Painel "Novo livro / Nova seção / Novo artigo" (nível 1, 2, 3), "Dentro de **{pai}**", "Título \*", "Conteúdo (HTML permitido)" e "Texto do livro/seção/artigo…". No livro, **Compartilhar com**: "Público (todos do business)" ou "Apenas usuários selecionados", e neste caso "Usuários com acesso" e "{n} usuário(s) selecionado(s).". Botões Cancelar e Salvar. |
| `Knowledge/Edit` | O mesmo painel, com título "Editar {livro\|seção\|artigo}". |
| `Knowledge/Show` | Painel com o título, o tipo (Livro/Seção/Artigo), "Compartilhado: Público" no livro, a seção **Conteúdo** com o vazio "(Sem conteúdo — clique em Editar para adicionar.)" e a navegação do livro (seções e contagem de artigos). Botões **Editar** e **"Adicionar seção" / "Adicionar artigo"**. |
| `Settings/Index` | Nada. É do roteiro hrm, e `hrm-config` já tem os campos do vivo. |
| `Holidays/Index` | Nada. É do roteiro hrm (`hrm-feriados`). |

## Defeitos do vivo — não entraram

- `Knowledge/Create|Edit` escreve **"Novo seção"** e **"Novo artigo"**: `labelFor()` mais `toLowerCase()`
  dá erro de concordância. No protótipo ficou "Nova seção" / "Novo artigo".
- O status **"Terminado"** vem de `restaurant.completed` (`lang/pt/restaurant.php:76`), uma chave de
  outro módulo. **Entrou**, porque é o que o vivo mostra, mas fica para [W]: é rótulo de verdade ou
  defeito de tradução?
- O `Documents/Index` vivo diz "Compartilhe via ícone ⤴ após o upload". O protótipo não tem ícone
  ali, então ficou "via Compartilhar". É uma adaptação, não uma cópia literal.

## Ficou fora, e por quê

Tudo abaixo está **fora do prefixo** da thread (`essenciais-page.jsx`):

- **`essenciais-extras.jsx`**
  - `BaseConhecimento` usa "categoria" e o vivo usa **livro → seção → artigo**. Também faltam o
    "Novo livro" do topo, o subtítulo "Organize manuais, procedimentos e artigos em livros → seções
    → artigos.", o vazio "Nenhum livro cadastrado ainda." com "Criar primeiro livro", o "Nada
    encontrado na base / Nenhum livro, seção ou artigo com "{termo}". Tente outra palavra." com
    "Limpar busca", e o confirmar "Remover item?" ("… será removido junto com seus filhos (seções e
    artigos). Ação não pode ser desfeita."). Os botões Editar/Adicionar seção/Excluir de lá não abrem
    o painel novo.
  - O `ModalStatus` usa "Alterar situação" e o vivo usa **"Atualizar status" / "Novo status" /
    "Selecione o status"**.
  - O vazio do `Historico` não usa o "Nenhuma atividade registrada para essa tarefa." do vivo.
  - O `Compartilhar` usa usuário **ou** função, e o vivo usa **usuários e "Papéis (roles)"** juntos,
    com o texto "Escolha usuários ou papéis que terão acesso ao arquivo/memo.".
- **`essenciais-data.jsx`**: os rótulos de `ST_TAR`/`PRIO` foram trocados aqui por cima. O valor
  canônico tem de ir para lá.
- **`hrm-ui.jsx`**: a `Paginacao` mostra "{p} de {n}" e o vivo mostra "Página X de Y · N item(s)".
- **Títulos h1 das Pages** ("Tarefas (To-Do)" / "Organize e acompanhe tarefas atribuídas a você ou à
  equipe.", "Lembretes" / "Avisos pessoais com data e repetição (cada usuário vê só os seus).", "Base
  de conhecimento"). No protótipo a vista vive sob o cabeçalho "Essenciais" com abas. Não entraram,
  como no precedente do Repair, porque o título é de outra thread.
- **Defeito do shell (`oimpresso.com.html:422`)**: a rota `essenciais`, aberta direto, **não monta**.
  `FAMILIAS.essenciais = /essenciais/` joga `essenciais-*.jsx` para a frente de `hrm-ui.jsx`, e o
  `essenciais-page.jsx` lê `window.HrmUI` ainda indefinido ("Cannot destructure property 'Badge' of
  'U' as it is undefined"). Já quebrava no `main`: medi servindo o blob de `HEAD`, com 2 pageerrors
  iguais. As rotas `ess-*` não sofrem disso, porque `ess` não está em `FAMILIAS`. É mais um motivo
  para medir pela `ess-tarefas`, e não pela `essenciais`.

## Só-protótipo — não apaguei, fica para [W]

- Aba **`ess-config`** (`X.Config`): quem atribui tarefa, tamanho máximo, compartilhar memorando
  por, repetição padrão, mensagem exige localidade, base visível ao cliente. O vivo **não tem**
  nenhum desses campos (a `Settings/Index` viva tem prefixos, instruções de afastamento e meta de
  vendas).
- Rota **`ess-tarefa`** (tarefa em tela cheia, `X.TarefaPage`). Com a D1 o alvo é o painel, então a
  tela cheia vira duplicata da `ess-tarefa-ver`.
- Em Tarefas: **densidade** Confortável/Compacto, **seleção em lote** (Alterar situação, Concluir,
  Excluir) e **destaque "Atrasada"**. O vivo tem o "Atrasada", os outros dois não.
- Em Lembretes: **Origem** (Lembrete/Financeiro/Ponto), com o filtro e a legenda, e "Abrir no
  módulo". O vivo é pessoal ("cada usuário vê só os seus") e não tem origem.
- Em Mensagens: **lida / nova · marcar lida** e **"Marcar tudo como lido"**.
- Em Documentos: a coluna "Compartilhado com (n)" no painel. O botão Baixar existe no vivo.
- Em Tarefas, a validação "O fim não pode ser antes do início". O vivo não tem.
- **Saiu** do protótipo a validação "Escolha pelo menos um responsável", porque contradizia o vivo:
  sem responsável, a tarefa é de quem cria.

## Provas

- **Render.** Espelho servido por `servirEspelho` (de `scripts/design/design-diff-lote.mjs`) na
  porta **5579**, com Playwright. Viewport 1280×900, contexto limpo, rota posta em
  `localStorage['oimpresso.route']`. Espera `__oiLazyDone` e depois o DOM estável.
  - 17 rotas medidas: as 12 de `EssRotas` mais `essenciais`, `ess-tarefa`, `ess-config`,
    `hrm-config` e `hrm-feriados`.
  - **16 montaram com 0 erro de página e 0 erro de console.** Montaram `.ess-page` com o
    `data-screen-label` da aba certa; `hrm-config` e `hrm-feriados` montaram "HRM · Configurações" e
    "HRM · Feriados".
  - Os painéis abriram com os títulos esperados: "Nova tarefa", "Editar tarefa TAR2026/0012",
    "Refazer prova do painel de fachada — cliente pediu Pantone 2925 C", "Novo livro", "Editar
    seção" e "Receber arte do cliente".
  - Os cabeçalhos da tabela de `ess-tarefas` saíram assim: Criado em · ID da tarefa · Tarefa ·
    Situação · **Prioridade** · Início · Fim · Horas est. · Atribuído por · Atribuído a · **Ações**.
  - `window.EssRotas` tem **12** chaves.
  - A 17ª, `essenciais`, deu **2 pageerrors**. É o mesmo resultado com o `essenciais-page.jsx` de
    `HEAD` injetado via `route.fulfill`, com `EssRotas` nulo, o que confirma que o blob servido era o
    antigo. O defeito é pré-existente (ver "Ficou fora").
- **ds-guard.** `node scripts/design/ds-guard.mjs prototipo-ui/cowork/Wagner/essenciais-page.jsx` →
  `limpo`, rc 0. **Ressalva:** a própria saída diz "(ignorado: nao e css/html)". O guard não lê
  `.jsx`, então o "limpo" não mede este arquivo.
- **Integridade do arquivo.** 0 bytes de controle fora de TAB/LF/CR, 0 CR e 0 barra invertida. O
  `HEAD` também tinha 0 barras, então nenhuma colapsou. Diff `git diff --numstat`: **+290 −87**.

## O que precisa subir ao Cowork

- `prototipo-ui/cowork/Wagner/essenciais-page.jsx`
- `prototipo-ui/cowork/Wagner/cowork-inbox/essenciais/playbook/_saida-00.md`
- Pendente, fora desta thread: `essenciais-data.jsx` (rótulos `ST_TAR`/`PRIO`) e
  `essenciais-extras.jsx` (copy do vivo em BaseConhecimento, ModalStatus, Historico e Compartilhar,
  listada acima).
