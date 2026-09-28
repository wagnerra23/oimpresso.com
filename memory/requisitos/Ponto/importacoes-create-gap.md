---
id: requisitos-ponto-importacoes-create-gap
tela: Ponto/Importacoes/Create (/ponto/importacoes/novo)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Importacoes/Create.tsx
gerado_em: 2026-09-28
charter: resources/js/Pages/Ponto/Importacoes/Create.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/24-gap-importacoes.md
---

# GAP-SPEC — Ponto/Importacoes/Create

> **Fonte do contrato:** charter `Importacoes/Create.charter.md` + protótipo `ponto-telas.jsx`,
> Card de upload **inline** no símbolo `Importacoes` (`:799-823`). Lado vivo medido em
> `Importacoes/Create.tsx` e `ImportacaoController.php` (`origin/main` e4289e688). O `.tsx` vivo
> tem **0** `data-contract`. Todas as 4 lacunas que a thread 24 apontava no protótipo **já
> estão resolvidas no vivo** — este gap é, na prática, a lista do que o protótipo precisa alcançar.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Tela própria ou Card inline | **Vivo à frente.** Vivo é página própria na rota `/ponto/importacoes/novo` (`Create.tsx`, breadcrumb em `:136-140`). Protótipo abre um Card dentro da lista (`:799-823`). | Nada no vivo. `D-PONTO-DETALHE` = ROTA PRÓPRIA ("in-page não é alternativa") — quem muda é o protótipo (R2); é a thread 28. |
| Tipo de arquivo | **Paridade.** Vivo `Create.tsx:81-89` (AFD / AFDT com o nome completo). Protótipo `:803-806`. | Nada — paridade. |
| Arquivo | **Vivo à frente.** Vivo restringe a `.txt` (`accept=".txt"`, `Create.tsx:96`) e mostra nome e tamanho do escolhido (`:99-103`). Protótipo: `input type="file"` sem `accept` (`:808`). | Nada — vivo à frente. |
| Bloco explicativo do fluxo | **Vivo à frente.** Vivo tem os 4 passos que o charter pede — SHA-256 → dedup → job assíncrono → acompanhamento (`Create.tsx:63-72`). Protótipo cita o job e o assíncrono, mas não o SHA-256 (`:811-813`). | Nada — vivo à frente. |
| Barra de progresso do upload | **Vivo à frente.** Vivo mostra `form.progress.percentage` (`Create.tsx:106-115`). Protótipo não tem (região a nascer lá; o DS tem `Progress`). | Nada — vivo à frente. |
| Enviar e destino após o envio | **Vivo à frente.** Vivo redireciona ao `Show` da importação criada (`ImportacaoController.php:92-94`, `route('ponto.importacoes.show', …)`), como o charter pede. Protótipo volta para a lista (`:816-820`). A geração de id por `Math.max(...)+1` do protótipo (`:817`) é mock — **não portar**. | Nada — vivo à frente. |
