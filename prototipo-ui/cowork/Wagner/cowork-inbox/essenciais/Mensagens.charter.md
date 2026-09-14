# Mensagens — charter (módulo Essentials · aba Essenciais)

- **Rota no protótipo:** `ess-mensagens` (dentro de `oimpresso.com.html`, shell Cockpit V2)
- **Origem no main:** `Modules/Essentials/Resources/views/messages/{index,message_div,recent_messages}.blade.php`
- **Build:** `essenciais-page.jsx` · `essenciais-extras.jsx` · `essenciais-data.jsx` · `essenciais-page.css`
- **Fase:** F1 (protótipo visual [CC]) — aguarda F1.5 [CD] e F3 [CL]

## Objetivo
Mural interno por localidade: o aviso que hoje vive em grupo de WhatsApp e some.

## Personas
toda a equipe · Wagner define a regra, Filial Norte responde

## Seções (ordem na tela)
1. Cabeçalho: filtro de localidade + marcar tudo como lido
2. Mural em ordem cronológica com autor, localidade e hora
3. Compositor: texto + localidade + Enviar

## Estados cobertos
- com dados
- não lidas
- filtrado por localidade
- sem permissão de ver (view_message)
- demonstração

## Permissões (Spatie, do main)
Mapeadas por papel no protótipo (`ESSENCIAIS.PERMS`): `essentials.assign_todos` · `add_todos` · `view_message` · `create_message` · `edit_essentials_settings`. O que o papel não pode aparece **bloqueado com motivo**, nunca escondido sem explicação.

## Fora de escopo (precisa decisão de [W])
- Vínculo tarefa ↔ OS/cliente (não existe no blade).
- Versionamento de documento (o main guarda media, não versiona).
- Canal de notificação de tarefa atribuída / memorando novo.
