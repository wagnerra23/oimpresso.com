<!--
  Página de manutenção (503) — servida durante a janela de deploy.

  POR QUE ELA É HTML PURO, SEM NENHUMA DIRETIVA BLADE:
  o deploy chama `php artisan down --render="errors::503"`, e esse comando renderiza
  este arquivo ANTES de derrubar o site, guardando o HTML pronto em
  storage/framework/maintenance.php. Duas consequências:

    1. O comando de manutenção termina em `|| true` (deploy.yml). Se esta view
       falhasse ao renderizar, o erro seria mascarado e o site seguiria NO AR
       durante o `composer install` — muito pior que a janela de 503. Sem
       diretiva e sem variável, a renderização não tem como falhar.

    2. Em runtime este HTML é ecoado por PHP puro, antes do autoload do Composer.
       Nada aqui pode depender do framework, de rota, de asset buildado ou de
       fonte externa. Tudo é inline e self-contained de propósito.

  NÃO escreva diretiva nem interpolação Blade aqui — nem dentro de comentário
  HTML, porque o compilador do Blade não distingue os dois. Se precisar de dado
  dinâmico, ele entra por JavaScript no cliente, nunca por Blade.
-->
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Atualizando o sistema — oimpresso</title>
<style>
  html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    background: #EEF1F4;
    color: #16191E;
    font-family: "IBM Plex Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  .tela {
    min-height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    box-sizing: border-box;
  }
  .cartao {
    background: #FFFFFF;
    border: 1px solid #D6DBE1;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(22,25,30,.05), 0 12px 32px -18px rgba(22,25,30,.28);
    max-width: 460px;
    width: 100%;
    padding: 36px 34px;
    box-sizing: border-box;
  }
  .selo {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #00655C;
    margin: 0 0 14px;
  }
  h1 {
    font-size: 25px;
    line-height: 1.2;
    font-weight: 600;
    letter-spacing: -.02em;
    margin: 0 0 14px;
  }
  p {
    font-size: 15px;
    line-height: 1.6;
    color: #4E5865;
    margin: 0 0 12px;
  }
  .destaque {
    background: #E8F2F0;
    border: 1px solid #B9D9D3;
    border-radius: 6px;
    padding: 13px 15px;
    margin: 18px 0 0;
  }
  .destaque p {
    margin: 0;
    color: #14322E;
    font-size: 14.5px;
  }
  .destaque strong { color: #0B241F; }
  .rodape {
    margin: 22px 0 0;
    padding: 15px 0 0;
    border-top: 1px solid #E4E8EC;
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .estado {
    font-size: 13.5px;
    color: #4E5865;
    margin: 0;
  }
  .relogio {
    font-family: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
    font-size: 13px;
    color: #7A838F;
    font-variant-numeric: tabular-nums;
    margin: 0;
  }
  .ponto {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #00655C;
    margin-right: 7px;
    vertical-align: baseline;
  }
</style>
</head>
<body>
  <div class="tela">
    <div class="cartao">
      <p class="selo">oimpresso</p>
      <h1>Estamos atualizando o sistema</h1>
      <p>Isso costuma levar cerca de um minuto. A página volta sozinha assim que terminar &mdash; você não precisa fazer nada.</p>

      <div class="destaque">
        <p><strong>O que você digitou está salvo.</strong> Não feche esta aba: ao voltar, o sistema oferece o rascunho da venda para você continuar de onde parou.</p>
      </div>

      <div class="rodape">
        <p class="estado"><span class="ponto"></span><span id="estado">Reconectando&hellip;</span></p>
        <p class="relogio" id="relogio">0:00</p>
      </div>
    </div>
  </div>

<script>
(function () {
  var inicio = Date.now();
  var elRelogio = document.getElementById('relogio');
  var elEstado = document.getElementById('estado');
  var tentativas = 0;
  var voltou = false;

  function pintaRelogio() {
    if (voltou) return;
    var s = Math.floor((Date.now() - inicio) / 1000);
    var m = Math.floor(s / 60);
    var r = s % 60;
    elRelogio.textContent = m + ':' + (r < 10 ? '0' : '') + r;

    // Passou bem do tempo típico: troca o texto para não parecer travado.
    if (s > 180) {
      elEstado.textContent = 'Ainda atualizando — pode avisar o suporte';
    }
  }
  setInterval(pintaRelogio, 1000);

  // Sonda: HEAD na própria URL. Enquanto o site estiver em manutenção o
  // servidor responde 503; qualquer outra coisa significa que voltou.
  // Jitter evita que todas as abas recarreguem no mesmo instante.
  function sonda() {
    if (voltou) return;
    tentativas++;
    fetch(window.location.href, { method: 'HEAD', cache: 'no-store' })
      .then(function (resposta) {
        if (resposta.status !== 503) {
          voltou = true;
          elEstado.textContent = 'Voltou! Carregando…';
          window.location.reload();
          return;
        }
        agenda();
      })
      .catch(function () {
        // Rede oscilando durante o deploy é esperado — segue tentando.
        agenda();
      });
  }

  function agenda() {
    if (voltou) return;
    // 4s nas primeiras tentativas, afrouxando até 15s, sempre com jitter.
    var base = tentativas < 10 ? 4000 : (tentativas < 25 ? 8000 : 15000);
    setTimeout(sonda, base + Math.floor(Math.random() * 1500));
  }

  agenda();
})();
</script>
</body>
</html>
