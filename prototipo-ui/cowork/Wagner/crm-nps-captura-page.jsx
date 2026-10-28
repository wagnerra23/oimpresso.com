// crm-nps-captura-page.jsx — NPS pós-venda e captura de lead. Protótipo de 27/10/2026 (PR #10005), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["crm-nps"] = `<title>NPS e captura de lead</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<!--
  Protótipo gerado no git (ADR 0438 D1) para US-CRM-097 (NPS) e US-CRM-096 (captura de lead).
  Âncora no DS: prototipo-ui/design-system/colors_and_type.css (bloco .cockpit — tokens abaixo
  copiados de lá, mesmos nomes). Padrões de tela: A = página pública avulsa (sem shell, celular),
  B = PT-02 painel (KPIs em cima, lista embaixo), C = cartão do funil PT-05 (#9883) com a origem.
-->
<style>
/* Layout: três quadros empilhados, cada um com o padrão de tela que vai virar .tsx. */
:root{
  --font-sans:"IBM Plex Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
  --font-mono:"IBM Plex Mono",ui-monospace,"SF Mono",Menlo,monospace;
  --bg:oklch(0.985 0.003 90);--bg-2:oklch(0.965 0.004 90);--surface:#ffffff;
  --border:oklch(0.90 0.004 90);--border-2:oklch(0.93 0.004 90);
  --text:oklch(0.22 0.01 80);--text-dim:oklch(0.50 0.01 80);--text-mute:oklch(0.555 0.01 80);
  --accent:oklch(0.55 0.15 295);--accent-soft:oklch(0.95 0.04 295);--accent-fg:#ffffff;
  --pos:oklch(0.50 0.12 150);--pos-soft:oklch(0.95 0.075 150);
  --neg:oklch(0.55 0.18 25);--neg-soft:oklch(0.955 0.055 25);
  --warn:oklch(0.56 0.12 70);--warn-soft:oklch(0.955 0.072 75);
  --radius:8px;--radius-sm:6px;--radius-lg:12px;
  --shadow-soft:0 1px 2px rgba(0,0,0,.04);
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){
  --bg:oklch(0.26 0.006 240);--bg-2:oklch(0.23 0.006 240);--surface:oklch(0.30 0.008 240);
  --border:oklch(0.34 0.008 240);--border-2:oklch(0.31 0.008 240);
  --text:oklch(0.94 0.005 90);--text-dim:oklch(0.72 0.005 90);--text-mute:oklch(0.67 0.005 90);
  --accent-soft:oklch(0.33 0.09 295);--pos:oklch(0.76 0.18 150);--pos-soft:oklch(0.33 0.06 150);
  --neg:oklch(0.74 0.19 25);--neg-soft:oklch(0.33 0.07 25);--warn:oklch(0.82 0.16 75);--warn-soft:oklch(0.34 0.06 75);
  color-scheme:dark}}
:root[data-theme="dark"]{
  --bg:oklch(0.26 0.006 240);--bg-2:oklch(0.23 0.006 240);--surface:oklch(0.30 0.008 240);
  --border:oklch(0.34 0.008 240);--border-2:oklch(0.31 0.008 240);
  --text:oklch(0.94 0.005 90);--text-dim:oklch(0.72 0.005 90);--text-mute:oklch(0.67 0.005 90);
  --accent-soft:oklch(0.33 0.09 295);--pos:oklch(0.76 0.18 150);--pos-soft:oklch(0.33 0.06 150);
  --neg:oklch(0.74 0.19 25);--neg-soft:oklch(0.33 0.07 25);--warn:oklch(0.82 0.16 75);--warn-soft:oklch(0.34 0.06 75);
  color-scheme:dark}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:var(--font-sans);font-size:13px;line-height:1.45;
  padding-left:max(16px,env(safe-area-inset-left,0px));padding-right:max(16px,env(safe-area-inset-right,0px));padding-block:24px 48px}
.wrap{max-width:1180px;margin:0 auto;display:flex;flex-direction:column;gap:40px}
.quadro-tit{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.quadro-tit h2{font-size:15px;font-weight:600;margin:0;text-wrap:balance}
.quadro-tit span{font-size:12px;color:var(--text-mute)}
.tag{font-family:var(--font-mono);font-size:11px;color:var(--text-dim);background:var(--bg-2);border:1px solid var(--border-2);border-radius:4px;padding:1px 6px}
h1{font-size:20px;font-weight:600;margin:0 0 4px}
.sub{color:var(--text-dim);margin:0;max-width:65ch}

/* A — página pública */
.telefone{max-width:380px;margin:0 auto;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
.tel-topo{padding:18px 20px 14px;border-bottom:1px solid var(--border-2)}
.tel-topo b{display:block;font-size:12px;color:var(--text-mute);font-weight:500;letter-spacing:.04em;text-transform:uppercase}
.tel-corpo{padding:20px;display:flex;flex-direction:column;gap:16px}
.pergunta{font-size:17px;font-weight:600;margin:0;text-wrap:balance}
.escala{display:grid;grid-template-columns:repeat(6,1fr);gap:6px}
.escala button{font:600 15px var(--font-sans);height:44px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--surface);color:var(--text);cursor:pointer;font-variant-numeric:tabular-nums}
.escala button:hover{border-color:var(--accent)}
.escala button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.escala button[aria-pressed="true"]{background:var(--accent);border-color:var(--accent);color:var(--accent-fg)}
.extremos{display:flex;justify-content:space-between;font-size:11px;color:var(--text-mute)}
label{font-size:12px;font-weight:500;color:var(--text-dim);display:flex;flex-direction:column;gap:6px}
textarea{font:13px var(--font-sans);border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 10px;min-height:72px;resize:vertical;background:var(--surface);color:var(--text)}
.btn{font:600 13px var(--font-sans);height:40px;border-radius:var(--radius-sm);border:1px solid var(--accent);background:var(--accent);color:var(--accent-fg);cursor:pointer;padding:0 16px}
.btn:disabled{opacity:.45;cursor:not-allowed}
.btn-ghost{background:transparent;color:var(--text);border-color:var(--border)}
.obrigado{text-align:center;padding:28px 20px}
.obrigado .nota-grande{font:600 40px var(--font-sans);color:var(--accent);font-variant-numeric:tabular-nums}
.rodape-tel{padding:12px 20px;border-top:1px solid var(--border-2);font-size:11px;color:var(--text-mute)}

/* B — painel */
.painel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg)}
.ph{display:flex;gap:12px;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;padding:16px 18px;border-bottom:1px solid var(--border-2)}
.filtros{display:flex;gap:6px;flex-wrap:wrap}
.chip-f{font:500 12px var(--font-sans);border:1px solid var(--border);background:var(--surface);color:var(--text-dim);border-radius:999px;padding:4px 10px;cursor:pointer}
.chip-f[aria-pressed="true"]{background:var(--accent-soft);color:var(--accent);border-color:var(--accent-soft)}
.kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;border-bottom:1px solid var(--border-2)}
.kpi{padding:14px 18px;border-right:1px solid var(--border-2);min-width:0}
.kpi:last-child{border-right:0}
.kpi .rot{font-size:11px;color:var(--text-mute);letter-spacing:.04em;text-transform:uppercase}
.kpi .val{font:600 24px var(--font-sans);font-variant-numeric:tabular-nums;margin-top:2px}
.kpi .det{font-size:12px;color:var(--text-dim)}
.barra{display:flex;height:10px;border-radius:999px;overflow:hidden;margin-top:8px;background:var(--bg-2)}
.barra i{display:block}
.legenda{display:flex;gap:12px;flex-wrap:wrap;font-size:11px;color:var(--text-dim);margin-top:6px}
.legenda b{display:inline-block;width:8px;height:8px;border-radius:2px;margin-right:4px;vertical-align:middle}
.tabela-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:640px}
th{font-size:11px;font-weight:500;color:var(--text-mute);text-align:left;padding:8px 18px;border-bottom:1px solid var(--border-2);letter-spacing:.03em;text-transform:uppercase}
td{padding:9px 18px;border-bottom:1px solid var(--border-2);vertical-align:top}
tr:last-child td{border-bottom:0}
.nota{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:22px;border-radius:4px;font:600 12px var(--font-sans);font-variant-numeric:tabular-nums}
.n-p{background:var(--pos-soft);color:var(--pos)}.n-m{background:var(--warn-soft);color:var(--warn)}.n-d{background:var(--neg-soft);color:var(--neg)}
.origem{font-family:var(--font-mono);font-size:12px;color:var(--text-dim)}
.coment{color:var(--text);max-width:42ch}
.coment.vazio{color:var(--text-mute)}
.mute{color:var(--text-mute);font-size:12px;font-variant-numeric:tabular-nums}
.aviso{padding:10px 18px;font-size:12px;color:var(--text-dim);background:var(--bg-2);border-top:1px solid var(--border-2);border-radius:0 0 var(--radius-lg) var(--radius-lg)}

/* C — funil */
.colunas{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.coluna{background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius);padding:10px;display:flex;flex-direction:column;gap:8px;min-width:0}
.coluna h3{font-size:12px;font-weight:600;margin:0;display:flex;justify-content:space-between;color:var(--text-dim)}
.cartao{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px;display:flex;flex-direction:column;gap:6px;box-shadow:var(--shadow-soft)}
.cartao .t{font-weight:600}
.cartao .l{display:flex;justify-content:space-between;gap:8px;font-size:12px;color:var(--text-dim);flex-wrap:wrap}
.chip-o{display:inline-flex;gap:4px;align-items:center;font-size:11px;border-radius:4px;padding:1px 6px;background:var(--accent-soft);color:var(--accent);font-weight:500}
.chip-o.manual{background:var(--bg-2);color:var(--text-mute)}
pre{font:12px/1.5 var(--font-mono);background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius);padding:12px;overflow-x:auto;margin:0;color:var(--text)}
.duas{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:16px;align-items:start}
@media (max-width:760px){.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.kpi:nth-child(2){border-right:0}.kpi:nth-child(-n+2){border-bottom:1px solid var(--border-2)}.colunas,.duas{grid-template-columns:1fr}}
@media (prefers-reduced-motion:no-preference){.escala button{transition:background .12s,border-color .12s}}
</style>

<div class="wrap">
  <header>
    <h1>NPS pós-venda e captura de lead</h1>
    <p class="sub">Protótipo das telas de US-CRM-097 e US-CRM-096. Dados de exemplo da empresa fictícia "Gráfica Exemplo". Os três quadros viram três telas.</p>
  </header>

  <section aria-labelledby="qa">
    <div class="quadro-tit"><h2 id="qa">A · Resposta do cliente</h2><span class="tag">GET /nps/{token}</span><span>página pública, sem login, aberta pelo link do WhatsApp</span></div>
    <div class="telefone">
      <div class="tel-topo"><b>Gráfica Exemplo</b>Pedido entregue em 26/10</div>
      <form class="tel-corpo" id="form-nps">
        <p class="pergunta">De 0 a 10, quanto você recomendaria a Gráfica Exemplo a um amigo ou colega?</p>
        <div>
          <div class="escala" role="group" aria-label="Nota de 0 a 10" id="escala"></div>
          <div class="extremos" style="margin-top:6px"><span>0 · nada provável</span><span>10 · muito provável</span></div>
        </div>
        <label for="comentario">O que pesou na sua nota? (opcional)
          <textarea id="comentario" maxlength="1000" placeholder="Conte em poucas palavras"></textarea>
        </label>
        <button class="btn" id="enviar" type="submit" disabled>Enviar resposta</button>
      </form>
      <div class="obrigado" id="obrigado" hidden>
        <div class="nota-grande" id="nota-dada">9</div>
        <p style="font-weight:600;margin:6px 0 4px">Obrigado pela resposta</p>
        <p class="mute" style="margin:0">Sua nota foi registrada. Pode fechar esta página.</p>
      </div>
      <div class="rodape-tel">Link vale para uma resposta. Já respondeu? Mostramos a nota que você deu.</div>
    </div>
  </section>

  <section aria-labelledby="qb">
    <div class="quadro-tit"><h2 id="qb">B · Painel NPS</h2><span class="tag">/crm/nps</span><span>PT-02 · permissão crm.nps.ver</span></div>
    <div class="painel">
      <div class="ph">
        <div><div style="font-size:16px;font-weight:600">NPS</div><div class="mute">Clientes que receberam a pesquisa depois da venda ou da OS entregue</div></div>
        <div class="filtros" role="group" aria-label="Período">
          <button class="chip-f" aria-pressed="false" type="button">7 dias</button>
          <button class="chip-f" aria-pressed="true" type="button">30 dias</button>
          <button class="chip-f" aria-pressed="false" type="button">90 dias</button>
        </div>
      </div>
      <div class="kpis">
        <div class="kpi"><div class="rot">NPS</div><div class="val">+42</div><div class="det">de −100 a +100</div></div>
        <div class="kpi"><div class="rot">Respostas</div><div class="val">38</div><div class="det">de 61 enviadas · 62%</div></div>
        <div class="kpi" style="grid-column:span 2">
          <div class="rot">Distribuição</div>
          <div class="barra" aria-hidden="true"><i style="width:57.9%;background:var(--pos)"></i><i style="width:26.3%;background:var(--warn)"></i><i style="width:15.8%;background:var(--neg)"></i></div>
          <div class="legenda"><span><b style="background:var(--pos)"></b>22 promotores (9-10)</span><span><b style="background:var(--warn)"></b>10 neutros (7-8)</span><span><b style="background:var(--neg)"></b>6 detratores (0-6)</span></div>
        </div>
      </div>
      <div class="tabela-wrap">
        <table>
          <thead><tr><th>Nota</th><th>Cliente</th><th>Origem</th><th>Comentário</th><th>Respondida</th></tr></thead>
          <tbody>
            <tr><td><span class="nota n-d">3</span></td><td>Papelaria Ponto Azul</td><td class="origem">OS 0412</td><td class="coment">O banner chegou dois dias depois do combinado.</td><td class="mute">27/10 09:14</td></tr>
            <tr><td><span class="nota n-p">10</span></td><td>Clínica Bem Estar</td><td class="origem">Venda 1987</td><td class="coment">Atendimento rápido e a arte ficou ótima.</td><td class="mute">26/10 18:02</td></tr>
            <tr><td><span class="nota n-m">8</span></td><td>Auto Peças Sul</td><td class="origem">Venda 1979</td><td class="coment vazio">sem comentário</td><td class="mute">26/10 11:40</td></tr>
            <tr><td><span class="nota n-p">9</span></td><td>Escola Pequeno Mundo</td><td class="origem">OS 0408</td><td class="coment">Gostei de receber a foto da instalação.</td><td class="mute">25/10 16:27</td></tr>
          </tbody>
        </table>
      </div>
      <div class="aviso">Detratores aparecem primeiro. Cada cliente recebe no máximo uma pesquisa a cada 90 dias.</div>
    </div>
  </section>

  <section aria-labelledby="qc">
    <div class="quadro-tit"><h2 id="qc">C · Lead do site no funil</h2><span class="tag">/crm/funil</span><span>cartão do funil (#9883) com a origem</span></div>
    <div class="duas">
      <div class="colunas">
        <div class="coluna"><h3>Lead <span>3</span></h3>
          <div class="cartao"><div class="t">Lead: Marina Costa (Site)</div><div class="l"><span>Marina Costa</span><span>R$ 0,00</span></div><div><span class="chip-o">Formulário · google / cpc · outubro</span></div></div>
          <div class="cartao"><div class="t">Lead: Rafael Lima (Site)</div><div class="l"><span>Rafael Lima</span><span>R$ 0,00</span></div><div><span class="chip-o">Formulário · instagram / social</span></div></div>
          <div class="cartao"><div class="t">Fachada ACM loja centro</div><div class="l"><span>Ótica Visão</span><span>R$ 4.800,00</span></div><div><span class="chip-o manual">Manual</span></div></div>
        </div>
        <div class="coluna"><h3>Qualificação <span>1</span></h3>
          <div class="cartao"><div class="t">Adesivos frota 12 carros</div><div class="l"><span>Transportes Rio</span><span>R$ 3.200,00</span></div><div><span class="chip-o">Formulário · google / organic</span></div></div>
        </div>
        <div class="coluna"><h3>Proposta <span>0</span></h3><p class="mute" style="margin:4px 2px">Nenhum negócio nesta etapa</p></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;min-width:0">
        <div style="font-weight:600">Formulário no site da empresa</div>
        <p class="mute" style="margin:0">A empresa cola isto no site. Os campos utm_* vêm da URL da página; o campo website fica escondido e é a armadilha contra robôs.</p>
<pre>&lt;form method="post"
  action="https://oimpresso.com/api/crm/captura/<span style="color:var(--accent)">{chave}</span>"&gt;
  &lt;input name="nome" required&gt;
  &lt;input name="email" type="email"&gt;
  &lt;input name="telefone"&gt;
  &lt;textarea name="mensagem"&gt;&lt;/textarea&gt;
  &lt;input name="utm_source" type="hidden"&gt;
  &lt;input name="utm_medium" type="hidden"&gt;
  &lt;input name="utm_campaign" type="hidden"&gt;
  &lt;input name="website" hidden tabindex="-1"&gt;
  &lt;button&gt;Quero um orçamento&lt;/button&gt;
&lt;/form&gt;</pre>
      </div>
    </div>
  </section>
</div>

<script>
(function(){
  var escala=document.getElementById('escala'),enviar=document.getElementById('enviar'),escolhida=null;
  for(var n=0;n<=10;n++){(function(v){
    var b=document.createElement('button');b.type='button';b.textContent=v;b.id='nota-'+v;b.setAttribute('aria-pressed','false');
    b.addEventListener('click',function(){escolhida=v;Array.prototype.forEach.call(escala.children,function(x){x.setAttribute('aria-pressed',String(x===b))});enviar.disabled=false});
    escala.appendChild(b);
  })(n)}
  document.getElementById('form-nps').addEventListener('submit',function(e){
    e.preventDefault();if(escolhida===null)return;
    document.getElementById('nota-dada').textContent=escolhida;
    this.hidden=true;document.getElementById('obrigado').hidden=false;
  });
  document.querySelectorAll('.filtros .chip-f').forEach(function(c,_,todos){c.addEventListener('click',function(){todos.forEach(function(x){x.setAttribute('aria-pressed',String(x===c))})})});
})();
</script>
`;
