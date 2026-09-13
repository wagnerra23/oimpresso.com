# Lembretes — charter (módulo Essentials · aba Essenciais)

- **Rota no protótipo:** `ess-lembretes` (dentro de `oimpresso.com.html`, shell Cockpit V2)
- **Origem no main:** `Modules/Essentials/Resources/views/reminder/{index,create,show}.blade.php`
- **Build:** `essenciais-page.jsx` · `essenciais-extras.jsx` · `essenciais-data.jsx` · `essenciais-page.css`
- **Fase:** F1 (protótipo visual [CC]) — aguarda F1.5 [CD] e F3 [CL]

## Objetivo
Um calendário que mostra junto o que a equipe marcou e o que os outros módulos cobram (vencimento, fechamento do ponto).

## Personas
Eliana (vencimentos) · Rafael (manutenção e instalação) · Wagner (reunião)

## Seções (ordem na tela)
1. Toolbar: mês anterior/atual/próximo · origem · Adicionar lembrete
2. Grade do mês com eventos por dia
3. Legenda de origem + dica de teclado
4. Drawer de detalhe (excluir se manual, abrir no módulo se vem de fora)
5. Formulário: nome · repetição · data · hora início · hora fim

## Estados cobertos
- com dados
- primeira vez
- filtro de origem
- origem externa (só leitura)
- demonstração

## Permissões (Spatie, do main)
Mapeadas por papel no protótipo (`ESSENCIAIS.PERMS`): `essentials.assign_todos` · `add_todos` · `view_message` · `create_message` · `edit_essentials_settings`. O que o papel não pode aparece **bloqueado com motivo**, nunca escondido sem explicação.

## Fora de escopo (precisa decisão de [W])
- Vínculo tarefa ↔ OS/cliente (não existe no blade).
- Versionamento de documento (o main guarda media, não versiona).
- Canal de notificação de tarefa atribuída / memorando novo.
