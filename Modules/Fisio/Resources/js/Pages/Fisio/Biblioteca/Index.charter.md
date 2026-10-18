---
id: modules-fisio-pages-fisio-biblioteca-index-charter
page: /fisio/biblioteca
component: Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.tsx
related_prototype: "n/a (o canvas Prescreve Pro desenha a Biblioteca, mas não está no espelho do repo; a tela segue o DS e o padrão de grade do HANDOFF-F1)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/biblioteca
  rota_nome: fisio.biblioteca.index
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/biblioteca (DRAFT · biblioteca de exercícios)

> Nasce na US-FISIO-004 (ADR 0437 §4). Casos: [`Index.casos.md`](Index.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar ao fisioterapeuta os exercícios que ele pode prescrever — a biblioteca global do oimpresso e os exercícios próprios da clínica — e deixá-lo cadastrar os da clínica.

## Goals — Features (faz)

- Grade com os exercícios globais (só leitura) e os próprios da empresa da sessão — UC-FBI-01.
- Cadastro e edição de exercício próprio, gravado com a empresa da sessão (exige `fisio.editar`) — UC-FBI-02, UC-FBI-03.
- Filtro por região e busca por texto no nome e nos sinônimos (busca na F1 aceita por [W] em 2026-10-18, ADR 0437 §14 nº 3) — UC-FBI-04.
- Foto (JPG/PNG/WebP) ou vídeo MP4 curto no exercício próprio, mostrada no card e no formulário de edição, guardada pelo backbone `Modules/Arquivos` sob a empresa da sessão e servida só por rota que confere a empresa — UC-FBI-05. Tipo, tamanho e a declaração de que não mostra paciente validados no servidor — UC-FBI-06. Remover tira só a mídia da clínica dona — UC-FBI-07.

## Non-Goals — Features (NÃO faz)

- ❌ Editar exercício da biblioteca global: é de todas as clínicas (UC-FBI-03).
- ❌ Sugerir exercício para paciente: quem escolhe é o profissional (ADR 0437 §5).
- ❌ Mídia em exercício da biblioteca global (403) e foto/vídeo de paciente (ADR 0437 §4 e §7; HANDOFF-F1) — UC-FBI-05.

## Refs

- ADR 0437 · `memory/requisitos/Fisio/HANDOFF-F1.md` (T-04, T-06) · `etapas/01-biblioteca.md` §3.9 · UI-0013
