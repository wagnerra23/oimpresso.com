/*
 * CONGELA O RELÓGIO DO NAVEGADOR nas capturas do gate visual (visual-regression).
 * ---------------------------------------------------------------------------------
 * POR QUE EXISTE
 *
 * As suítes de baseline já congelavam o relógio do PHP com
 * `\Carbon\Carbon::setTestNow('2026-06-11 12:00:00')` (PixelBaselineTest:78,
 * IsolatedStatesBaselineTest, FinanceiroFlowBaselineTest) — o comentário lá diz
 * "pra matar flakiness de datas". Mas isso congela UM dos dois relógios: telas que
 * chamam `new Date()` no NAVEGADOR seguiam lendo o relógio real da máquina, no
 * instante do render. Exemplos medidos em 2026-08-14:
 *
 *   - resources/js/Pages/Jana/components/JanaAreaHeader.tsx:80
 *       new Date().toLocaleTimeString('pt-BR', {hour,minute})  → "Atualizado 17:17"
 *   - resources/js/Pages/Jana/_components/JanaCockpit.tsx:356
 *       new Date().toLocaleDateString('pt-BR', {day,month,year}) → "14 de agosto de 2026"
 *   - resources/js/Pages/Jana/_components/JanaCockpit.tsx:107
 *       new Date().getHours()  → alterna a saudação (Bom dia/Boa tarde/Boa noite)
 *
 * Consequência: a baseline dessas telas drifa POR MINUTO, não por release. O gate
 * required ficava impossível de manter — e o diff virava falso-vermelho.
 *
 * COMO FUNCIONA
 *
 * Substitui o construtor global `Date` por um Proxy que:
 *   - `new Date()`      (sem argumento) → SEMPRE o instante congelado;
 *   - `new Date(x, …)`  (com argumento) → comportamento REAL, intocado. É o que
 *     mantém funcionando o parse de timestamp vindo do servidor (ex.:
 *     Pages/Jana/Chat.tsx:132 formata a hora de CADA mensagem a partir do dado);
 *   - `Date.now()`      → o instante congelado;
 *   - `Date()`          (chamado como função) → string do instante congelado;
 *   - `Date.parse` / `Date.UTC` / `Date.prototype` → intocados (o Proxy repassa).
 *
 * O Proxy preserva `Date.prototype`, então `x instanceof Date` continua verdadeiro
 * e qualquer lib que estenda Date segue funcionando (o trap `construct` repassa o
 * `newTarget`).
 *
 * ONDE É INJETADO
 *
 * `resources/views/layouts/inertia.blade.php`, dentro do <head> e ANTES do
 * `@vite([... 'resources/js/app.tsx'])` — precisa rodar antes do bundle da app,
 * senão o React já renderizou com o relógio vivo. É lido de ESTE arquivo por
 * `file_get_contents` justamente pra não existir uma segunda cópia do shim: o
 * bite-test (scripts/tests/visreg-clock-bite.mjs) carrega o MESMO arquivo, então
 * o que é provado é o que roda.
 *
 * GUARDA
 *
 * Só é injetado quando `config('visreg.freeze_clock')` tem valor (env
 * `VISREG_FREEZE_CLOCK`, setada só no .env do job visual-regression) E o ambiente
 * não é produção — mesma defesa-em-profundidade do VisregStateMiddleware.
 *
 * RESÍDUO HONESTO (não coberto de propósito)
 *
 *   - `performance.now()` segue vivo: mede duração, não é exibido.
 *   - `new Intl.DateTimeFormat().format()` SEM argumento usa o relógio da engine,
 *     não o nosso `Date.now`. Não há ocorrência dessa forma no código hoje; se
 *     aparecer, o sintoma volta e este comentário é o ponteiro.
 *
 * @see config/visreg.php                        (a flag)
 * @see resources/views/layouts/inertia.blade.php (a injeção)
 * @see scripts/tests/visreg-clock-bite.mjs       (o bite-test: morde e libera)
 * @see tests/Browser/Support/VisregThreshold.php (o comparador do gate)
 */
(function () {
  var at = window.__VISREG_FREEZE_AT__;

  // Sem instante válido = no-op absoluto. Nunca "chuta" um horário.
  if (typeof at !== 'number' || !isFinite(at)) {
    return;
  }

  var RealDate = Date;

  function nowCongelado() {
    return at;
  }

  window.Date = new Proxy(RealDate, {
    construct: function (Alvo, args, newTarget) {
      // Sem argumento = "agora" → congelado. Com argumento = parse real, intocado.
      var argsFinais = args.length === 0 ? [at] : args;

      return Reflect.construct(Alvo, argsFinais, newTarget === undefined ? Alvo : newTarget);
    },

    apply: function () {
      // `Date()` como função devolve string, por spec — nunca um objeto.
      return new RealDate(at).toString();
    },

    get: function (Alvo, prop) {
      if (prop === 'now') {
        return nowCongelado;
      }

      // Repassa o resto (prototype, parse, UTC, length, name…). Usar Reflect.get
      // no ALVO preserva os invariantes de Proxy pras props não-configuráveis.
      return Reflect.get(Alvo, prop);
    },
  });
})();
