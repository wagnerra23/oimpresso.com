# /modulos — Patches propostos (colar 1× ou aplicar via Code)

> [CC] 2026-08-19. Cada patch tem **sintoma → causa → mudança → prova**. Ordem = MOD-O2/O3 das ondas.
> Não commitei nada: as tools de GitHub daqui são read-only.

---

## P1 — install que falha deixa a linha mentindo "Ativo" (UC-MOD-13) · `must`

**Sintoma:** migrate quebra, o toast mostra o erro, mas a linha continua **Ativo** e o módulo fica meio-instalado.
**Causa:** `ModuleManagerService::install()` chama `setActive($name, true)` **antes** do `module:migrate` (necessário: nWidart só registra módulo habilitado) e o `catch` só devolve a mensagem.
**Mudança** (`app/Services/ModuleManagerService.php`):

```diff
         } catch (Throwable $e) {
+            // O ativar acontece ANTES do migrate (nWidart só registra módulo habilitado).
+            // Se o migrate falhou, o estado tem de voltar — senão a tela /modulos afirma
+            // "Ativo" para um módulo com schema incompleto (UC-MOD-13).
+            $this->setActive($name, false);
+
             return [
                 'success' => false,
                 'output'  => $e->getMessage(),
                 'install_output' => null,
             ];
         }
```

**Prova:** `UC-MOD-13` em `tests/Feature/Modules/ModuleManagementTest.php`.
**Nota:** migrations que já rodaram antes da exceção **não** são revertidas — a mensagem do toast deve dizer isso ("migrations parcialmente aplicadas; rode novamente após corrigir").

---

## P2 — status "Com erro" é inalcançável na prática · `should`

**Sintoma:** o KPI "Com erro" e o badge existem, mas nunca acendem: 32 módulos, 0 erros, sempre.
**Causa:** `error` só é preenchido quando a **leitura** do `module.json` lança `Throwable`. `module.json` inválido (JSON malformado ⇒ `json_decode` devolve null, sem exceção), provider ausente ou migration pendente não produzem erro.
**Mudança** (`list()`, dentro do `try`):

```diff
                 $moduleJson = [];
                 if (File::exists($moduleJsonPath)) {
-                    $moduleJson = json_decode(File::get($moduleJsonPath), true) ?? [];
+                    $decoded = json_decode(File::get($moduleJsonPath), true);
+                    if (! is_array($decoded)) {
+                        $error = 'module.json inválido: '.json_last_error_msg();
+                    } else {
+                        $moduleJson = $decoded;
+                        if (empty($moduleJson['providers'])) {
+                            $error = 'module.json sem providers[] — o módulo não é carregado.';
+                        }
+                    }
+                } else {
+                    $error = 'module.json ausente.';
                 }
```

e trocar `'error' => null` por `'error' => $error` no array de retorno do caminho felizes.
**Prova:** unit com fixture `Modules/__ErrFixture__/module.json` malformado (mesmo padrão do `DetectDriftCommandTest`) ⇒ linha com `error` preenchido e status `errored`. Alinhado com `tests/Feature/Audit/ModuleScaffoldingTest.php`, que já exige `providers[]` — a tela passa a mostrar o que o audit já reprova.

---

## P3 — `has_datacontroller` chega na prop e a tela ignora · `should`

**Sintoma:** módulo instalado e ativo que **não aparece no menu** — sintoma clássico (documentado em `.claude/skills/criar-modulo/SKILL.md`), invisível na tela.
**Causa:** `DataController` é quem monta o item de sidebar; a prop existe, o `Index.tsx` não usa.
**Mudança** (`resources/js/Pages/Modules/Index.tsx`, célula do nome):

```diff
                           <div className="text-[11px] text-muted-foreground/70 leading-tight mt-0.5">
                             {m.alias} · v{m.version}
+                            {m.active && ! m.has_datacontroller && (
+                              <span className="ml-2 text-warning-fg" title="Sem DataController — não monta item na sidebar">
+                                sem menu
+                              </span>
+                            )}
                           </div>
```

**Prova:** contrato de tela (`modulos.tabela`) + UC novo "módulo ativo sem DataController é sinalizado".

---

## P4 — versão sempre `v0.0` (decisão D1) · `must` decidir

**Sintoma:** 32 linhas dizendo `v0.0`.
**Causa:** nenhum `module.json` declara `version`; o Service faz fallback `'0.0'`.
**Mudança recomendada** (opção (a) — versão **instalada**, que é o que o operador quer saber):

```diff
+                $installedVersion = \App\Models\System::getProperty(strtolower($name).'_version');
+
                 $modules[] = [
                     'name'               => $name,
-                    'version'            => (string) ($moduleJson['version'] ?? '0.0'),
+                    'version'            => (string) ($moduleJson['version'] ?? $installedVersion ?? '—'),
```

**Cuidado (bug adjacente conhecido):** `isModuleInstalled()` busca `strtolower($name).'_version'`, então alias kebab (`oficina-auto`, `comunicacao-visual`) grava chave diferente da consultada — a própria skill `criar-modulo` documenta o sintoma "Instalar perpétuo". Vale um teste que trave a convenção para os 32 antes de exibir a versão instalada.

---

## P5 — duas leis de autorização (decisão D2) · `must` decidir

**Sintoma:** `/modulos` autoriza por `session('is_admin')`/`Admin#<biz>`; o item de menu (`AdminSidebarMenu.php:809`) e o legado `Install/ModulesController` autorizam por permissão `manage_modules`. Dá para ver o item no menu e tomar 403 na tela.
**Mudança** (`ModuleManagementController::__construct`), se [W] unificar:

```diff
-            $isAdmin = (bool) $request->session()->get('is_admin', false);
-            if (!$isAdmin && method_exists($request->user(), 'hasRole')) {
-                $businessId = $request->session()->get('business.id');
-                $isAdmin = $request->user()->hasRole('Admin#' . $businessId);
-            }
-            abort_unless($isAdmin, 403, 'Acesso restrito a administradores.');
+            abort_unless($request->user()->can('manage_modules'), 403, 'Acesso restrito a administradores.');
```

**Prova:** UC-MOD-02/04 reescritos na porta nova (mesma intenção, outra chave).

---

## P6 — `install` dentro do request web (decisão D4) · `should`

**Sintoma:** módulo com 24 migrations pode estourar `max_execution_time`; a UI não sabe o que aconteceu.
**Mudança sugerida:** `InstalarModuloJob` (fila) + coluna de estado transitório na prop (`installing`) + botão desabilitado enquanto roda + lock por nome (`Cache::lock('modulo:install:'.$name)`) para não haver dois installs concorrentes. Precisa confirmar que há fila configurada em produção (não verifiquei).

---

## P7 — documentação que manda o operador na tela morta · `must` (barato)

6 arquivos citam `/manage-modules` como caminho vivo. Trocar por `/modulos` (mantendo a menção histórica onde explica a substituição):

- `.claude/skills/cockpit-runbook/TEMPLATE.md:42`
- `.claude/skills/criar-modulo/SKILL.md:26,46,74,151,252`
- `.claude/skills/migrar-modulo/SKILL.md:144,226`
- `.claude/skills/sidebar-menu-arch/SKILL.md:222`

---

## P8 — chaves órfãs no `modules_statuses.json` · `should`

Accounting (false), CustomDashboard, Ecommerce, FieldForce, Hms, InboxReport: chave sem pasta. Duas saídas — remover do arquivo (recomendo, é ruído de merge) ou a tela passar a listar como "no registro, sem código". Se remover: um PR mecânico + UC-MOD-06 já protege o comportamento.
