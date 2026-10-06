---
sessao: "02"
titulo: D4: install em fila
dono: "[W]"
base: 2fe69ddc0280
---
# 02 · D4: install em fila

**Bloqueada até [W] decidir D4.** Pergunta que destrava: há worker de fila rodando em produção? Sem worker, o job deixa a tela "instalando" pra sempre.

Se sim → [CL] parte de `../repo/app/Jobs/InstalarModuloJob.php` (lock por módulo, `tries=1`, estado em cache `instalando|ok|erro`). Lido no turno: o job **não existe** no `main` @2fe69ddc0280.

## Prova
No JSON do `00-INDICE.md` — o placar confere. Recibo: `_saida-02.md`, de quem executar.
