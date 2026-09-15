# /modulos — Ondas para o Code (F3)

> [CC] 2026-08-19 · destino [CL] · aprovação de escopo [W]
> Trio F1: `cowork-inbox/MODULOS-F1-2026-08-19.md` · build: `prototipo-ui/cowork/modulos/`
> Numeração `MOD-O*` para não colidir com as "Wave N" de backend do repo.
> **A tela já existe em React** (`Pages/Modules/Index.tsx`) — estas ondas são acabamento, prova e governança, não tradução de Blade. Nenhuma onda de arquitetura.

---

## MOD-O0 — 4 decisões [W] antes de codar (bloqueante)

D1 versão sempre `v0.0` · D2 RBAC (`is_admin`/`Admin#biz` vs permissão `manage_modules`) · D3 drawer PT-02 entra? · D4 migration dentro do request web.
Detalhe e recomendação em cada uma: §6 do trio F1. **Saída:** as 4 respondidas em `Index.charter.md` (que sai de `draft`).

---

## MOD-O1 — Prova mínima (o que destrava o `ciclo-completo`)

**Entra:** `resources/js/Pages/Modules/Index.casos.md` com os 14 UC do trio (o arquivo **não existe** — é o motivo direto da reprovação do gate); `tests/Feature/Modules/ModuleManagementTest.php` com os 8 testes de HTTP; `ModuleManagerServiceTest` com os 3 de unidade; `Index.charter.md` de `draft` → `live` com os acréscimos A1–A4 e as respostas do MOD-O0.
**Sai:** `prototipo-readiness` marca a tela ✅ (trio completo) e `ciclo-completo` passa de 1/6 para verde nas caixas de charter/casos.
**Não entra:** nenhuma mudança de UI.

---

## MOD-O2 — Verdade da tela (o que hoje ela informa errado)

1. **Versão** conforme D1 — parar de escrever `v0.0` em 32 linhas.
2. **`has_datacontroller`** vem na prop e a tela ignora: mostrar como "monta item na sidebar" (é a causa clássica de "módulo instalado que não aparece no menu" — vale um marcador, não uma coluna).
3. **Install que falha continua ativo** (o `setActive(true)` roda antes do migrate): ou reverter a flag no `catch`, ou exibir status `errored` derivado — a linha não pode dizer "Ativo" depois de um migrate quebrado.
4. **`error` nunca é preenchido** no caminho felizes/infelizes do `list()` (só em `Throwable` na leitura) — o status "Com erro" é hoje inalcançável na prática. Definir a fonte: `module.json` inválido, provider ausente, migration pendente (`module:status`).
**Sai:** cada célula da tela mapeada a um fato do sistema; KPI "Com erro" deixa de ser decorativo.

---

## MOD-O3 — Segurança e robustez da ação

1. RBAC conforme D2, com teste dos dois caminhos.
2. `install` conforme D4 (fila + estado "instalando" com desabilitação do botão, ou lock + limite declarado).
3. Confirmação destrutiva **no servidor também**: hoje a barreira é o `confirm()` do browser; `uninstall` de módulo em uso por negócios ativos merece checagem (quantos negócios têm o pacote com o módulo) e a contagem no aviso.
4. Auditoria: `toggle/install/uninstall` gravando em activity_log (quem, quando, de→para) — módulo é estado global do app e hoje só o git do JSON conta a história.
**Sai:** nenhuma ação global sem autor e sem rastro.

---

## MOD-O4 — DS vivo no lugar das peças caseiras

`KpiCard` (4 cards) · `Switch` (já é o de `Components/ui`, manter) · `StatusBadge` com **kind novo `modulo`** (ativo/inativo/com erro/não registrado) · `EmptyState variant="no-results"` com motivo + ação · `Toast` · `Drawer`+`DrawerSection` se D3 = sim.
Ficam como estão de propósito: tabela (espelha `Cliente/Index`), `FilterDropdown` e kebab (o padrão é do shell inteiro; trocar só aqui criaria dois padrões — isso é onda transversal).
**Sai:** zero cor crua, zero primitivo caseiro novo nesta tela.

---

## MOD-O5 — Contrato de tela + CI

`prototipo-ui/contrato/modulos.contract.json` (ADR 0286) com as âncoras e a copy literal do §4 do trio; `data-contract` nos 8 pontos; `contrato:check` no PR. Smoke visual 1280/1440 (pendência do charter) + prova de que a tabela não estoura com a sidebar aberta.
**Sai:** a copy e a ordem das seções travadas no CI.

---

## MOD-O6 — Limpeza do legado

Remover do app o caminho `/manage-modules` (rotas + `Install/ModulesController` na parte que só servia a view AdminLTE), mantendo o redirect do `LegacyMenuAdapter`; conferir que nenhum runbook/skill manda o operador em `/manage-modules` (hoje 6 skills mandam: `cockpit-runbook`, `criar-modulo`, `migrar-modulo`, `sidebar-menu-arch`).
**Sai:** uma porta só para gerenciar módulos, e a documentação apontando pra ela.

---

## O que mais precisa (além das ondas)

1. **Atualizar as 6 skills** que instruem "login superadmin → `/manage-modules` → Install": a URL viva é `/modulos`. É documentação que hoje leva o operador a uma tela quebrada.
2. **Alinhar `moduleSystemKey`**: `isModuleInstalled()` busca `strtolower(nome)_version`, então alias kebab (`oficina-auto`, `comunicacao-visual`) instala gravando chave diferente da consultada — a `criar-modulo/SKILL.md` já documenta o sintoma ("Instalar perpétuo"). Vale um teste que trave a convenção para os 32.
3. **Chaves órfãs no `modules_statuses.json`** (Accounting, CustomDashboard, Ecommerce, FieldForce, Hms, InboxReport): módulos sem pasta. Decidir se saem do arquivo ou se a tela precisa dizer "no JSON, sem código".
4. **`version` nos `module.json`** (se D1 = b) — 32 arquivos, PR mecânico.
5. **Tela irmã sem F1:** `/superadmin/usuarios` (`Usuario360Controller`) segue sem desenho — registrada no sync anterior, continua aberta.
6. **Não verifiquei** (fora do que li neste turno): se existe fila configurada para jobs longos em produção, e se `activity_log` já cobre ações app-wide. As ondas O3 assumem que sim; confirmar antes de estimar.

---

## Ponte pro `main`

Nada aqui está commitado — vive no projeto Cowork. Caminho: cola zero-toque de `cowork-inbox/MODULOS-F1-2026-08-19.md` + este arquivo, ou Issue com o form `cowork-intake`. Export do build já em `prototipo-ui/cowork/modulos/` (só jsx/css, conforme o guard `cowork-ssot-guard.mjs`).
