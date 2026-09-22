---
sessao: "00"
titulo: Placar — contar entrega real (índice mínimo)
autor: "[CC]"
criado: 2026-09-22
base: wagnerra23/oimpresso.com@main 701f40c6ec66 (lido 2026-09-22 19:33 UTC)
---
# Placar — índice

**Por que existe:** o `placar-de-lista` diz `0 de 61`, e há entrega em produção sem recibo: `Essentials/Licencas/Index.tsx` (`EssentialsLeaveController.php:191`) e `Essentials/Tipos.tsx` (`EssentialsLeaveTypeController.php:73`) cumprem as threads HRM 02 e 03, e não existe `_saida` para nenhuma das duas. O instrumento mede papelada, não entrega. Resultado: o Code não sabe o que sobra, e o Cowork reescreve tela que já está pronta.

**2 threads. Abrir com `/onda placar --thread 01` (diretório é minúsculo).** A 02 conserta o próprio `/onda`, que hoje não acha índice nenhum no modo thread.

```json
{
  "modulo": "Placar",
  "sha": "701f40c6ec66",
  "gerado": "2026-09-22",
  "decisoes": [],
  "threads": [
    { "id": "01", "titulo": "Estado `sem recibo`: provas verdes sem _saida", "dono": "CL", "arquivo": "01-entregue-sem-recibo.md",
      "prefixo": ["scripts/qa/placar-indice.mjs", "scripts/qa/placar-indice.test.mjs"],
      "nao_toca": ["scripts/qa/placar.mjs", ".github/workflows/placar-de-lista.yml", "prototipo-ui/cowork/Wagner/cowork-inbox/"],
      "provas": [
        { "tipo": "contem", "path": "scripts/qa/placar-indice.mjs", "padrao": "'sem recibo'" },
        { "tipo": "contem", "path": "scripts/qa/placar-indice.test.mjs", "padrao": "sem recibo" }
      ] },
    { "id": "02", "titulo": "/onda modo thread: caminho quebrado ($1/$NN) e índice ausente vira PARAR", "dono": "CL", "arquivo": "02-onda-modo-thread.md",
      "prefixo": [".claude/commands/onda.md"],
      "nao_toca": ["scripts/qa/", "scripts/design-sync/pedido.mjs"],
      "provas": [
        { "tipo": "nao_contem", "path": ".claude/commands/onda.md", "padrao": "--thread $NN" },
        { "tipo": "contem", "path": ".claude/commands/onda.md", "padrao": "PARE" }
      ] }
  ]
}
```
