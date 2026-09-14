# Tarefas — charter (módulo Essentials · aba Essenciais)

- **Rota no protótipo:** `essenciais · ess-tarefa` (dentro de `oimpresso.com.html`, shell Cockpit V2)
- **Origem no main:** `Modules/Essentials/Resources/views/todo/{index,create,edit,show,view,comment,update_task_status_modal}.blade.php`
- **Build:** `essenciais-page.jsx` · `essenciais-extras.jsx` · `essenciais-data.jsx` · `essenciais-page.css`
- **Fase:** F1 (protótipo visual [CC]) — aguarda F1.5 [CD] e F3 [CL]

## Objetivo
Fazer a lista de afazeres do escritório caber numa tela: quem atribui vê tudo, quem executa vê o que é dele, e ninguém precisa perguntar "e a tarefa X?".

## Personas
Wagner (atribui, 1440px) · Larissa (executa no balcão, 1280px) · Eliana (financeiro, tarefas de fechamento)

## Seções (ordem na tela)
1. Toolbar: busca · atribuído a · prioridade · situação · Adicionar
2. Segunda linha: período de início · ordenação · densidade
3. Tabela: criado em · ID · tarefa · situação · início · fim · horas est. · atribuído por · atribuído a
4. BulkBar: alterar situação · concluir · excluir
5. Drawer de detalhe: situação · prazo · descrição · histórico · documentos · comentários
6. Tela cheia (todo/show): mesma tarefa com histórico e cartões laterais
7. Modal de situação (1 ou N tarefas)

## Estados cobertos
- com dados
- primeira vez (vazio ensina o que é atribuir)
- sem permissão de atribuir (vê só as próprias)
- demonstração (criar/excluir bloqueados)
- filtro sem resultado
- atrasada (fim < hoje)

## Permissões (Spatie, do main)
Mapeadas por papel no protótipo (`ESSENCIAIS.PERMS`): `essentials.assign_todos` · `add_todos` · `view_message` · `create_message` · `edit_essentials_settings`. O que o papel não pode aparece **bloqueado com motivo**, nunca escondido sem explicação.

## Fora de escopo (precisa decisão de [W])
- Vínculo tarefa ↔ OS/cliente (não existe no blade).
- Versionamento de documento (o main guarda media, não versiona).
- Canal de notificação de tarefa atribuída / memorando novo.
