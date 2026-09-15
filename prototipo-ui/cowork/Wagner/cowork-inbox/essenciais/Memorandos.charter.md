# Memorandos — charter (módulo Essentials · aba Essenciais)

- **Rota no protótipo:** `ess-memorandos` (dentro de `oimpresso.com.html`, shell Cockpit V2)
- **Origem no main:** `Modules/Essentials/Resources/views/memos/index.blade.php (document?type=memos)`
- **Build:** `essenciais-page.jsx` · `essenciais-extras.jsx` · `essenciais-data.jsx` · `essenciais-page.css`
- **Fase:** F1 (protótipo visual [CC]) — aguarda F1.5 [CD] e F3 [CL]

## Objetivo
Guardar o recado que precisa ficar: regra de balcão, plantão de feriado, prazo do mês.

## Personas
Wagner (publica) · equipe inteira (lê)

## Seções (ordem na tela)
1. Cabeçalho + Adicionar
2. Formulário: título + descrição
3. Tabela: título · descrição · data de criação · ações
4. Drawer: texto + compartilhamento

## Estados cobertos
- com dados
- primeira vez
- demonstração
- sem permissão de publicar

## Permissões (Spatie, do main)
Mapeadas por papel no protótipo (`ESSENCIAIS.PERMS`): `essentials.assign_todos` · `add_todos` · `view_message` · `create_message` · `edit_essentials_settings`. O que o papel não pode aparece **bloqueado com motivo**, nunca escondido sem explicação.

## Fora de escopo (precisa decisão de [W])
- Vínculo tarefa ↔ OS/cliente (não existe no blade).
- Versionamento de documento (o main guarda media, não versiona).
- Canal de notificação de tarefa atribuída / memorando novo.
