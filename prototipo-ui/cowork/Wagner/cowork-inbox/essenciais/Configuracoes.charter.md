# Configuracoes — charter (módulo Essentials · aba Essenciais)

- **Rota no protótipo:** `ess-config` (dentro de `oimpresso.com.html`, shell Cockpit V2)
- **Origem no main:** `Modules/Essentials/Resources/views/settings/add.blade.php (EssentialsSettingsController)`
- **Build:** `essenciais-page.jsx` · `essenciais-extras.jsx` · `essenciais-data.jsx` · `essenciais-page.css`
- **Fase:** F1 (protótipo visual [CC]) — aguarda F1.5 [CD] e F3 [CL]

## Objetivo
Deixar num lugar o que é do escritório (prefixo de tarefa, upload, mural) sem misturar com o que é de RH.

## Personas
Wagner (admin)

## Seções (ordem na tela)
1. Card Tarefas: prefixo · quem atribui
2. Card Documentos e memorandos: limite · padrão de compartilhamento
3. Card Lembretes e mensagens: repetição padrão · localidade obrigatória · KB visível ao cliente
4. Salvar + nota dizendo que RH tem as suas

## Estados cobertos
- admin
- sem edit_essentials_settings (bloqueio com motivo)
- demonstração

## Permissões (Spatie, do main)
Mapeadas por papel no protótipo (`ESSENCIAIS.PERMS`): `essentials.assign_todos` · `add_todos` · `view_message` · `create_message` · `edit_essentials_settings`. O que o papel não pode aparece **bloqueado com motivo**, nunca escondido sem explicação.

## Fora de escopo (precisa decisão de [W])
- Vínculo tarefa ↔ OS/cliente (não existe no blade).
- Versionamento de documento (o main guarda media, não versiona).
- Canal de notificação de tarefa atribuída / memorando novo.
