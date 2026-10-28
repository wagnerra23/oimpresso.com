// fiscal-das-page.jsx — Apuração do DAS. Protótipo de 27/10/2026 (US-FISCAL-030, commit 2e878a2046), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["fiscal-das"] = `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apuração do DAS</title>
<!--
  fiscal-das.html — protótipo da tela Fiscal/Das (US-FISCAL-030, fatia 2).
  Fonte de design no git (ADR 0438). Linguagem visual do módulo Fiscal (fiscal-subpages.jsx:
  hero + cards fx-card + linhas fx-row). Tokens do DS (prototipo-ui/design-system/colors_and_type.css).
  Dados FICTÍCIOS (empresa de demonstração). A conta é a mesma do ApuracaoDasService:
  LC 123/2006 art. 18 §1º-A, Anexos I a V (redação LC 155/2016).
  Estados demonstrados pelo seletor "Cenário": normal · acima do sublimite · sem RBT12 · sem anexo.
  Fora desta tela (Non-Goals): transmitir PGDAS-D, lançar a pagar, escolher o anexo pelo usuário
  sem o contador, segregar receitas.
-->
<style>
:root{
  --font-sans:"IBM Plex Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
  --font-mono:"IBM Plex Mono",ui-monospace,Menlo,monospace;
  --bg:oklch(0.985 0.003 90);--bg-2:oklch(0.965 0.004 90);--surface:#fff;
  --border:oklch(0.90 0.004 90);--border-2:oklch(0.93 0.004 90);
  --text:oklch(0.22 0.01 80);--text-dim:oklch(0.50 0.01 80);--text-mute:oklch(0.555 0.01 80);
  --accent:oklch(0.55 0.15 295);--accent-soft:oklch(0.95 0.04 295);--accent-fg:#fff;
  --warn:oklch(0.56 0.12 70);--warn-soft:oklch(0.955 0.072 75);
  --radius:8px;--radius-sm:6px;
  --fs-1:10.5px;--fs-2:11.5px;--fs-3:12.5px;--fs-4:13.5px;--fs-5:15px;--fs-7:22px;--fs-8:28px;
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){
  --bg:oklch(0.26 0.006 240);--bg-2:oklch(0.23 0.006 240);--surface:oklch(0.30 0.008 240);
  --border:oklch(0.34 0.008 240);--border-2:oklch(0.31 0.008 240);
  --text:oklch(0.94 0.005 90);--text-dim:oklch(0.72 0.005 90);--text-mute:oklch(0.68 0.005 90);
  --accent-soft:oklch(0.34 0.06 295);--warn:oklch(0.82 0.16 75);--warn-soft:oklch(0.34 0.05 75);
}}
:root[data-theme="dark"]{
  --bg:oklch(0.26 0.006 240);--bg-2:oklch(0.23 0.006 240);--surface:oklch(0.30 0.008 240);
  --border:oklch(0.34 0.008 240);--border-2:oklch(0.31 0.008 240);
  --text:oklch(0.94 0.005 90);--text-dim:oklch(0.72 0.005 90);--text-mute:oklch(0.68 0.005 90);
  --accent-soft:oklch(0.34 0.06 295);--warn:oklch(0.82 0.16 75);--warn-soft:oklch(0.34 0.05 75);
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:var(--fs-4)/1.45 var(--font-sans)}
.fx-page{max-width:1180px;margin:0 auto;padding:20px 16px 48px}
.fx-hero{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:16px}
.fx-crumb{font-size:var(--fs-2);color:var(--text-dim);text-transform:uppercase;letter-spacing:.04em}
h1{font-size:var(--fs-7);margin:2px 0 0;font-weight:600}
.fx-sub{color:var(--text-dim);font-size:var(--fs-3);margin-top:2px}
.fx-filtros{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end}
label.fx-f{display:flex;flex-direction:column;gap:3px;font-size:var(--fs-2);color:var(--text-dim)}
select,input[type=month]{font:inherit;font-size:var(--fs-3);color:var(--text);background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);height:32px;padding:0 8px;min-width:150px}
.fx-selo{display:inline-flex;align-items:center;gap:6px;font-size:var(--fs-2);color:var(--text-dim);background:var(--bg-2);border:1px solid var(--border);border-radius:999px;padding:3px 10px}
.fx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
@media (max-width:900px){.fx-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.fx-kpi{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;min-width:0}
.fx-kpi-l{font-size:var(--fs-2);color:var(--text-dim)}
.fx-kpi-v{font:600 var(--fs-7)/1.2 var(--font-mono);margin-top:4px;overflow-wrap:anywhere}
.fx-kpi-s{font-size:var(--fs-2);color:var(--text-mute);margin-top:2px}
.fx-kpi.destaque{border-color:var(--accent);background:var(--accent-soft)}
.fx-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:14px}
@media (max-width:900px){.fx-grid{grid-template-columns:minmax(0,1fr)}}
.fx-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:14px;overflow:hidden}
.fx-card-h{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--border-2);font-size:var(--fs-5);font-weight:600}
.fx-card-b{padding:12px 14px}
.fx-row{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px dashed var(--border-2);font-size:var(--fs-3)}
.fx-row:last-child{border-bottom:0}
.fx-row-l{color:var(--text-dim)}
.fx-row-v{font-family:var(--font-mono);text-align:right}
.fx-conta{font-family:var(--font-mono);font-size:var(--fs-3);background:var(--bg-2);border-radius:var(--radius-sm);padding:10px 12px;line-height:1.7;overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:var(--fs-3)}
th{font-size:var(--fs-2);color:var(--text-dim);text-align:right;font-weight:500;padding:6px 8px;border-bottom:1px solid var(--border)}
th:first-child,td:first-child{text-align:left}
td{padding:6px 8px;text-align:right;font-family:var(--font-mono);border-bottom:1px solid var(--border-2)}
tr.atual td{background:var(--accent-soft);font-weight:600}
.fx-aviso{display:flex;gap:10px;background:var(--warn-soft);border:1px solid var(--warn);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:var(--fs-3)}
.fx-aviso b{display:block}
.fx-vazio{background:var(--surface);border:1px dashed var(--border);border-radius:var(--radius);padding:28px;text-align:center;color:var(--text-dim);margin-bottom:14px}
.fx-vazio b{display:block;color:var(--text);font-size:var(--fs-5);margin-bottom:4px}
ol.fx-perg{margin:0;padding-left:18px;font-size:var(--fs-3)}
ol.fx-perg li{padding:4px 0}
.fx-delta{font-family:var(--font-mono)}
.fx-nota{font-size:var(--fs-2);color:var(--text-mute);margin-top:8px}
.oculto{display:none!important}
</style>
</head>
<body>
<div class="fx-page" data-screen-label="Fiscal · Apuração do DAS">
  <header class="fx-hero">
    <div>
      <div class="fx-crumb">Fiscal · Simples Nacional</div>
      <h1>Apuração do DAS</h1>
      <div class="fx-sub">Prévia da guia do mês pelo anexo do contador. Não transmite o PGDAS-D.</div>
    </div>
    <div class="fx-filtros">
      <label class="fx-f">Competência<input type="month" id="comp" value="2026-10"></label>
      <label class="fx-f">Anexo (informado pelo contador)
        <select id="anexo">
          <option value="">Escolha o anexo</option>
          <option value="I" selected>Anexo I · Comércio</option>
          <option value="II">Anexo II · Indústria</option>
          <option value="III">Anexo III · Serviços</option>
          <option value="IV">Anexo IV · Serviços</option>
          <option value="V">Anexo V · Serviços</option>
        </select>
      </label>
      <label class="fx-f">Cenário (só no protótipo)
        <select id="cenario">
          <option value="normal">Normal</option>
          <option value="sublimite">Acima do sublimite</option>
          <option value="inicio">Sem RBT12 (início de atividade)</option>
        </select>
      </label>
    </div>
  </header>

  <div class="fx-vazio oculto" id="semAnexo">
    <b>Escolha o anexo que o contador usa</b>
    O sistema não escolhe o anexo. Para serviços, o Fator R (aba Serviços) indica III ou V.
  </div>

  <div id="corpo">
    <div class="fx-aviso oculto" id="avisoSub"><span>⚠</span><div><b>Receita dos 12 meses acima do sublimite (LC 123, art. 13-A)</b>ICMS e ISS saem do DAS e passam a ser pagos por fora. O valor abaixo ainda inclui essa parte: a partilha é decisão do contador.</div></div>
    <div class="fx-aviso oculto" id="avisoInicio"><span>⚠</span><div><b>Sem receita nos 12 meses anteriores</b>No início de atividade a lei usa a receita proporcional (art. 18 §2º). Confirme com o contador antes de usar um valor.</div></div>

    <section class="fx-kpis" aria-label="Resultado da apuração">
      <div class="fx-kpi"><div class="fx-kpi-l">Receita do mês</div><div class="fx-kpi-v" id="kReceita">—</div><div class="fx-kpi-s">vendas finalizadas − devoluções</div></div>
      <div class="fx-kpi"><div class="fx-kpi-l">RBT12</div><div class="fx-kpi-v" id="kRbt">—</div><div class="fx-kpi-s" id="kRbtJanela">12 meses anteriores</div></div>
      <div class="fx-kpi"><div class="fx-kpi-l">Alíquota efetiva</div><div class="fx-kpi-v" id="kEfetiva">—</div><div class="fx-kpi-s" id="kFaixa">faixa —</div></div>
      <div class="fx-kpi destaque"><div class="fx-kpi-l">DAS do mês (prévia)</div><div class="fx-kpi-v" id="kDas">—</div><div class="fx-kpi-s" id="kVenc">vence dia 20 do mês seguinte</div></div>
    </section>

    <div class="fx-grid">
      <div>
        <div class="fx-card">
          <div class="fx-card-h"><span>A conta</span><span class="fx-selo">LC 123/2006 · art. 18 §1º-A</span></div>
          <div class="fx-card-b">
            <div class="fx-conta" id="conta">—</div>
            <p class="fx-nota">O valor sai da conta inteira; a alíquota efetiva só aparece arredondada.</p>
          </div>
        </div>
        <div class="fx-card">
          <div class="fx-card-h"><span id="tabTitulo">Faixas do Anexo I</span></div>
          <div class="fx-card-b" style="padding:0">
            <table><thead><tr><th>Faixa · RBT12 até</th><th>Alíquota nominal</th><th>Parcela a deduzir</th></tr></thead><tbody id="faixas"></tbody></table>
          </div>
        </div>
      </div>
      <div>
        <div class="fx-card">
          <div class="fx-card-h"><span>Estimativa do Financeiro × apuração</span></div>
          <div class="fx-card-b">
            <div class="fx-row"><span class="fx-row-l">Hoje (≈6% do recebido)</span><span class="fx-row-v" id="cEst">—</span></div>
            <div class="fx-row"><span class="fx-row-l">Pela apuração</span><span class="fx-row-v" id="cApu">—</span></div>
            <div class="fx-row"><span class="fx-row-l">Diferença</span><span class="fx-row-v fx-delta" id="cDif">—</span></div>
            <p class="fx-nota">A estimativa usa o recebido no mês (caixa); a apuração usa as vendas do mês (competência). Por isso podem divergir.</p>
          </div>
        </div>
        <div class="fx-card">
          <div class="fx-card-h"><span>Confirme com o contador</span></div>
          <div class="fx-card-b">
            <ol class="fx-perg">
              <li>A empresa apura por competência ou por caixa?</li>
              <li>Há receita a segregar (ST retida, monofásico, exportação, outra atividade)?</li>
              <li>No início de atividade, qual receita proporcional usar?</li>
              <li>Acima do sublimite, qual parte sai do DAS?</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const ANEXOS={I:[[180000,400,0],[360000,730,5940],[720000,950,13860],[1800000,1070,22500],[3600000,1430,87300],[4800000,1900,378000]],II:[[180000,450,0],[360000,780,5940],[720000,1000,13860],[1800000,1120,22500],[3600000,1470,85500],[4800000,3000,720000]],III:[[180000,600,0],[360000,1120,9360],[720000,1350,17640],[1800000,1600,35640],[3600000,2100,125640],[4800000,3300,648000]],IV:[[180000,450,0],[360000,900,8100],[720000,1020,12420],[1800000,1400,39780],[3600000,2200,183780],[4800000,3300,828000]],V:[[180000,1550,0],[360000,1800,4500],[720000,1950,9900],[1800000,2050,17100],[3600000,2300,62100],[4800000,3050,540000]]};
const CEN={normal:{rbt:500000,mes:50000,recebido:46000},sublimite:{rbt:3800000,mes:330000,recebido:300000},inicio:{rbt:0,mes:20000,recebido:18000}};
const brl=v=>v.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const pct=(v,c=2)=>(v*100).toLocaleString('pt-BR',{minimumFractionDigits:c,maximumFractionDigits:c})+'%';
const $=id=>document.getElementById(id);
function calc(anexo,rbt,mes){
  if(rbt<=0)return{semRbt:true};
  if(rbt>4800000)return{fora:true};
  const t=ANEXOS[anexo];
  for(let i=0;i<t.length;i++){const[l,a,pd]=t[i];if(rbt<=l){const ef=(rbt*a/10000-pd)/rbt;return{faixa:i+1,aliq:a/10000,pd,ef,das:Math.round(mes*ef*100)/100,sub:rbt>3600000};}}
}
function render(){
  const anexo=$('anexo').value,c=CEN[$('cenario').value],comp=$('comp').value;
  $('semAnexo').classList.toggle('oculto',!!anexo);$('corpo').classList.toggle('oculto',!anexo);
  if(!anexo)return;
  const[y,m]=comp.split('-').map(Number);
  const ini=new Date(y,m-13,1),fim=new Date(y,m-1,0);
  const d=x=>x.toLocaleDateString('pt-BR',{month:'short',year:'numeric'});
  $('kRbtJanela').textContent=d(ini)+' a '+d(fim);
  $('kVenc').textContent='vence 20/'+String(m%12+1).padStart(2,'0')+'/'+(m===12?y+1:y);
  const r=calc(anexo,c.rbt,c.mes);
  $('kReceita').textContent=brl(c.mes);$('kRbt').textContent=c.rbt?brl(c.rbt):'—';
  $('avisoSub').classList.toggle('oculto',!r.sub);$('avisoInicio').classList.toggle('oculto',!r.semRbt);
  $('tabTitulo').textContent='Faixas do Anexo '+anexo;
  $('faixas').innerHTML=ANEXOS[anexo].map(([l,a,pd],i)=>\`<tr class="\${r.faixa===i+1?'atual':''}"><td>\${i+1}ª · \${brl(l)}</td><td>\${pct(a/10000)}</td><td>\${brl(pd)}</td></tr>\`).join('');
  const est=Math.round(c.recebido*0.06*100)/100;$('cEst').textContent=brl(est);
  if(r.semRbt){$('kEfetiva').textContent='—';$('kFaixa').textContent='sem RBT12';$('kDas').textContent='—';$('conta').textContent='Sem RBT12 não há alíquota efetiva: aguarde o contador.';$('cApu').textContent='—';$('cDif').textContent='—';return;}
  $('kEfetiva').textContent=pct(r.ef,3);$('kFaixa').textContent=r.faixa+'ª faixa · nominal '+pct(r.aliq);
  $('kDas').textContent=brl(r.das);
  $('conta').innerHTML=\`(RBT12 × Aliq − PD) ÷ RBT12<br>(\${brl(c.rbt)} × \${pct(r.aliq)} − \${brl(r.pd)}) ÷ \${brl(c.rbt)} = \${pct(r.ef,4)}<br>DAS = \${brl(c.mes)} × \${pct(r.ef,4)} = <b>\${brl(r.das)}</b>\`;
  $('cApu').textContent=brl(r.das);const dif=r.das-est;$('cDif').textContent=(dif>=0?'+':'−')+brl(Math.abs(dif));
}
['anexo','cenario','comp'].forEach(id=>$(id).addEventListener('change',render));render();
</script>
</body>
</html>
`;
