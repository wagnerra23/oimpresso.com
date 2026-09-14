---
thread: "01"
modulo: shell-usermenu
dono: "[CL]"
prefixo: resources/js/Components/cockpit/Sidebar.tsx
base: remedir antes de escrever (li 6fc8b8fac31d em 2026-09-10)
---
# 01 · "Aparência" promete cascata e não abre

## Problema
No menu do usuário, o item **Aparência** tem a seta `›` — contrato visual de "abre algo" — e **nenhum handler**. O usuário clica e nada acontece. O estado de tema existe e funciona: o shell já escreve `data-theme` no `<html>`; o que falta é o caminho de UI até ele.

## O que fazer
1. Transformar o item numa cascata (mesmo padrão da que já existe no arquivo — reusar, não inventar um segundo jeito de cascata).
2. Duas opções, nesta ordem e com esta copy: **Escuro** ("Padrão do balcão") · **Claro** ("Escritório, luz alta"). Dark é o padrão do projeto ([W] 2026-06-03).
3. Marcar a escolhida (✓) e mostrar o valor atual no trigger, como o **Modo de trabalho** já faz.
4. **Um dono só pro estado:** dirigir o mesmo estado de tema que o shell já persiste. Não criar chave de `localStorage` nova nem um `useState` paralelo.

## Por que essa última linha está aqui
Deste lado eu **errei exatamente isso**: portei o Modo de trabalho com `localStorage` próprio enquanto o shell já tinha o estado — dois donos num eixo, e a UI mentia sobre quem manda. Corrigi passando a dirigir o estado existente. Não repita.

## Prova
- `npm run lint && npx tsc --noEmit` → exit 0.
- Runtime: abre, escolhe, `data-theme` muda, **persiste no reload**.
- **Controle positivo:** recarregar com `light` guardado volta em `light` (senão você ligou a UI e não o estado).

## Parar se
- Aparecer necessidade de mexer em `resources/css/**` → **pare**: tema é token, e isso é outra onda com outro dono.
- O charter do `AppShellV2` declarar cascata de tema em outro lugar → siga o charter e reporte a divergência.
