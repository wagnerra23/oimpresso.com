# BaseConhecimento — charter (módulo Essentials · aba Essenciais)

- **Rota no protótipo:** `ess-kb` (dentro de `oimpresso.com.html`, shell Cockpit V2)
- **Origem no main:** `Modules/Essentials/Resources/views/knowledge_base/{index,sidebar,show,create,edit}.blade.php`
- **Build:** `essenciais-page.jsx` · `essenciais-extras.jsx` · `essenciais-data.jsx` · `essenciais-page.css`
- **Fase:** F1 (protótipo visual [CC]) — aguarda F1.5 [CD] e F3 [CL]

## Objetivo
Escrever uma vez o que hoje se explica de novo a cada pessoa nova: como receber arte, como calibrar, o que é prazo.

## Personas
Iniciante (aprende o domínio) · Camila e Tiago (escrevem) · Larissa (consulta no balcão)

## Seções (ordem na tela)
1. Busca
2. Árvore: categoria → seção → artigo
3. Artigo: título, resumo, conteúdo rico e filhos como cartões
4. Ações de autoria (editar, adicionar seção, excluir)

## Estados cobertos
- com dados
- busca sem resultado
- sem permissão de editar
- demonstração

## Permissões (Spatie, do main)
Mapeadas por papel no protótipo (`ESSENCIAIS.PERMS`): `essentials.assign_todos` · `add_todos` · `view_message` · `create_message` · `edit_essentials_settings`. O que o papel não pode aparece **bloqueado com motivo**, nunca escondido sem explicação.

## Fora de escopo (precisa decisão de [W])
- Vínculo tarefa ↔ OS/cliente (não existe no blade).
- Versionamento de documento (o main guarda media, não versiona).
- Canal de notificação de tarefa atribuída / memorando novo.
