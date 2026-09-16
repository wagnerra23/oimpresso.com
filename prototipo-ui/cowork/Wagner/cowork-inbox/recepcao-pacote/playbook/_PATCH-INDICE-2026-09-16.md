# PATCH do índice · recepcao-pacote · 2026-09-16

> A pasta no `main` pode estar à frente da cópia local (foi o caso do Ponto e do Patrimônio). Então isto é **patch**, não índice reescrito: aplique os 3 objetos abaixo no `00-INDICE.md` que estiver no `main`.

## 1) `threads[]` — acrescentar

```json
{
  "id": "02",
  "titulo": "Pacote v2 regerado (2026-09-16): aplicar snapshot c8a0709 e descartar o lote de 07/09",
  "dono": "CL",
  "arquivo": "02-pacote-2026-09-16-regerado.md",
  "prefixo": ["sync", "prototipo-ui/cowork/Wagner", "prototipo-ui/design-system", "scripts/design-sync/state"],
  "nao_toca": ["resources/js/**", "memory/**", "governance/**"],
  "depende_threads": [],
  "depende_decisoes": [],
  "nota_provas": "prova e EXECUCAO do applier com exit code, nao 'o arquivo existe'",
  "provas": [
    { "tipo": "execucao", "cmd": "node scripts/design-sync/aplicar-payload.mjs sync/payload.part*.json --dry --require-complete-shell", "recibo": "_saida-02.md", "exige": "BUNDLE v2 VALIDADO · id c8a070942fa6cfb79615cc482dcf63155b9d08a455c7d7cec521af32a268c768 · modo snapshot · 281 arquivo(s)" },
    { "tipo": "execucao", "cmd": "node scripts/design-sync/aplicar-payload.mjs sync/payload.part*.json --require-complete-shell", "exige": "PROMOVIDO ATOMICAMENTE e state/active-bundle.json com bundleId c8a0709…" },
    { "tipo": "execucao", "cmd": "caso de sanidade: rodar o dry-run com UMA parte de fora", "exige": "recusa por 'lote incompleto' — validador que aceita 43 de 44 nao e validador" }
  ]
}
```

## 2) `§2` (linhas de estado) — acrescentar

- **2026-09-16 · pacote REGERADO**: snapshot `c8a0709…` · 281 arquivos · 7.416.482 B · 286 chunks · **44 partes** · `missing: []` · auto-auditoria 0 erro.
- **2026-09-16 · lote de 07/09 CONDENADO e apagado deste lado**: 31 de 43 partes acima do cap (maior 308.280 B) + manifesto `snapshot` com `baseBundleId` + `changes` incoerente ⇒ era inaplicável, não "defasado".

## 3) Tabela de threads — acrescentar linha

| **02** | aplicar o pacote regerado de 16/09 | `sync` · `prototipo-ui/**` | **CABE** · sem decisão pendente |
