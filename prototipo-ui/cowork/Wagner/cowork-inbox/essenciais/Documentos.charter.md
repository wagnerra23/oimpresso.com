# Documentos — charter (módulo Essentials · aba Essenciais)

- **Rota no protótipo:** `ess-documentos` (dentro de `oimpresso.com.html`, shell Cockpit V2)
- **Origem no main:** `Modules/Essentials/Resources/views/document/{index,show}.blade.php · document_share/edit.blade.php`
- **Build:** `essenciais-page.jsx` · `essenciais-extras.jsx` · `essenciais-data.jsx` · `essenciais-page.css`
- **Fase:** F1 (protótipo visual [CC]) — aguarda F1.5 [CD] e F3 [CL]

## Objetivo
Ser o lugar onde contrato, apólice, tabela de preço e perfil de cor são achados sem pedir no WhatsApp.

## Personas
Eliana (sobe contrato e apólice) · Larissa (procura tabela de preço) · Tiago (procura perfil ICC)

## Seções (ordem na tela)
1. Cabeçalho do card: título + Adicionar
2. Formulário de upload: arquivo (tipos e limite ditos) + descrição
3. Tabela: nome · descrição · data do upload · ações (baixar/compartilhar/excluir)
4. Drawer: descrição + com quem está compartilhado
5. Drawer de compartilhamento: por usuário ou por função

## Estados cobertos
- com dados
- primeira vez
- tipo de arquivo recusado
- sem permissão de compartilhar
- demonstração

## Permissões (Spatie, do main)
Mapeadas por papel no protótipo (`ESSENCIAIS.PERMS`): `essentials.assign_todos` · `add_todos` · `view_message` · `create_message` · `edit_essentials_settings`. O que o papel não pode aparece **bloqueado com motivo**, nunca escondido sem explicação.

## Fora de escopo (precisa decisão de [W])
- Vínculo tarefa ↔ OS/cliente (não existe no blade).
- Versionamento de documento (o main guarda media, não versiona).
- Canal de notificação de tarefa atribuída / memorando novo.
