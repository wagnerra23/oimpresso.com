---
sessao: "11"
titulo: Ferramentas — permissão, auditoria e autor
dono: "[CL]"
base: ff43e08461d9
origem: _saida-02 §Perguntas
---
# 11 · Ferramentas — permissão, auditoria e autor

[W] 07/10: permissão própria (declarar em `DataController::user_permissions`) nas rotas `grant`, `revoke` e `tools/{name}/execute`; auditoria filtrada por `business_id`; `triggered_by` = usuário logado, nunca `'wagner'` fixo. Tier 0: teste com tenant 98×99.
