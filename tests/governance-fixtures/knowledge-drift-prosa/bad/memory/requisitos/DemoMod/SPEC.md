# SPEC — DemoMod (fixture RUIM: prosa NAO desarma o token)

Por ~7 semanas a mensagem de FAIL do `--check` prometia um escape valve — marcar
o caminho como planejado/inexistente — que NUNCA existiu no codigo (nasceu com a
catraca, commit cab11a2caa / PR #2591, 2026-06-12; removido em #5053).

Implementar esse escape seria presence-gate sobre TEXTO, familia banida em
proibicoes.md secao 5 (2026-07-09). Logo o contrato a defender e "prosa ao lado
NUNCA solta o token" — nao "o marcador funciona".

ATENCAO A QUEM EDITAR: o token de ghost abaixo aparece UMA vez SO, e sempre
acompanhado do marcador. Se voce mencionar o mesmo nome em outro lugar deste
arquivo sem o marcador, a fixture fica vermelha por esse outro token e para de
morder o escape — foi exatamente esse o defeito da 1a versao dela (2026-07-30).

Drift proposital: vide Modules/GhostNovo/Foo.php (planejado — nao existe)
