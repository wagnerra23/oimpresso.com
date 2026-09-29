---
id: requisitos-ponto-importacoes-create-gap
tela: Ponto/Importacoes/Create (/ponto/importacoes/novo)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Importacoes/Create.tsx
gerado_em: 2026-09-29
charter: resources/js/Pages/Ponto/Importacoes/Create.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/24-gap-importacoes.md
---

# GAP-SPEC — Ponto/Importacoes/Create

> **Fonte do contrato:** charter `Importacoes/Create.charter.md` + protótipo `ponto-telas.jsx`,
> Card de upload no ramo `nova` do símbolo `Importacoes` (`:823-854`) — até 2026-09-28 inline na
> lista, desde 2026-09-29 página própria (thread 28). Lado vivo medido em `Importacoes/Create.tsx` e
> `ImportacaoController.php` (`origin/main` e4289e688, 2026-09-28); citações de linha re-medidas em
> 2026-09-29. Em 2026-09-28 o `.tsx` vivo tinha **0** `data-contract`; em 2026-09-29 tem 1
> (`importacoes-upload-do-arquivo`, `Create.tsx:74`). Todas as 4 lacunas que a thread 24 apontava
> no protótipo **já estão resolvidas no vivo** — este gap é, na prática, a lista do que o protótipo
> precisa alcançar.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Tela própria ou Card inline | **Paridade desde 2026-09-29 (thread 28, handoff 43).** Vivo é página própria na rota `/ponto/importacoes/novo` (`Create.tsx`, breadcrumb em `:136-140`). Protótipo passou a abrir a mesma tela como endereço próprio (`sub === "novo"`, `ponto-telas.jsx:751-752`; página em `:823-854`). Fato datado: quando este gap foi medido, em 2026-09-28, o protótipo abria um Card dentro da lista. | Nada — paridade. `D-PONTO-DETALHE` = ROTA PRÓPRIA, cumprida no protótipo pela thread 28 (2026-09-29). |
| Tipo de arquivo | **Paridade.** Vivo `Create.tsx:81-88` (AFD / AFDT com o nome completo). Protótipo `ponto-telas.jsx:832-835`. | Nada — paridade. |
| Arquivo | **Vivo à frente.** Vivo restringe a `.txt` (`accept=".txt"`, `Create.tsx:96`) e mostra nome e tamanho do escolhido (`:99-103`). Protótipo: `input type="file"` sem `accept` (`ponto-telas.jsx:837`). | Nada — vivo à frente. |
| Bloco explicativo do fluxo | **Vivo à frente.** Vivo tem os 4 passos que o charter pede — SHA-256 → dedup → job assíncrono → acompanhamento (`Create.tsx:63-72`). Protótipo: a nota cita o job e o assíncrono, mas não o SHA-256 (`ponto-telas.jsx:840-842`); desde 2026-09-29 o subtítulo da página própria cita a rejeição de duplicado por hash SHA-256 (`:827`). | Nada — vivo à frente. |
| Barra de progresso do upload | **Vivo à frente.** Vivo mostra `form.progress.percentage` (`Create.tsx:106-115`). Protótipo não tem (região a nascer lá; o DS tem `Progress`). | Nada — vivo à frente. |
| Enviar e destino após o envio | **Vivo à frente.** Vivo redireciona ao `Show` da importação criada (`ImportacaoController.php:92-94`, `route('ponto.importacoes.show', …)`), como o charter pede. Protótipo volta para a lista (`ponto-telas.jsx:845-849`, `setNova(false)` em `:848`). A geração de id por `Math.max(...)+1` do protótipo (`:846`) é mock — **não portar**. | Nada — vivo à frente. |
