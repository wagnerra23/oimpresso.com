---
sessao: "11"
titulo: Menu Configurações e access_printers
dono: "[CL]"
base: 50e23057f1c2
origem: _saida-04 §3
---
# 11 · Menu Configurações e access_printers

A condição externa do dropdown de Configurações no `AdminSidebarMenu` não lista `access_printers`: quem só tem essa permissão não vê o grupo (nem na Blade, nem nas abas do `ConfiguracoesSubNav`). Incluir a permissão na condição; teste com usuário só-`access_printers` → o grupo aparece com a aba Impressoras e nenhuma outra.
