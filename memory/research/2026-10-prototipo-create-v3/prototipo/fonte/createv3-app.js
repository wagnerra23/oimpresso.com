/* Palco do artefato: monta window.VendaV3Create (âncora prototipo-ui/cowork/Wagner/venda-v3.jsx)
   sem o shell do app único. Só a barra do protótipo é deste arquivo. */
(function () {
  var h = React.createElement;
  var raiz = document.getElementById('app');

  if (!window.VendaV3Create || !window.OfficeImpressoPontoWR2DesignSystem_019dd0) {
    raiz.innerHTML = '<div class="cv3-falha">A tela não carregou: o React ou o design system não chegou da CDN. Recarregue a página.</div>';
    return;
  }

  function temaAtual() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function Seg(props) {
    return h('div', { className: 'cv3-seg', role: 'group', 'aria-label': props.rotulo },
      props.opcoes.map(function (o) {
        return h('button', {
          key: o[0], type: 'button', 'aria-pressed': props.valor === o[0] ? 'true' : 'false',
          onClick: function () { props.onTroca(o[0]); }
        }, o[1]);
      }));
  }

  function Palco() {
    var modoIni = 'create';
    try { if (localStorage.getItem('oimpresso.artefato.cv3.modo') === 'edit') modoIni = 'edit'; } catch (e) {}
    var m = React.useState(modoIni), modo = m[0], setModo = m[1];
    var t = React.useState(temaAtual()), tema = t[0], setTema = t[1];

    React.useEffect(function () {
      var f = function () { setTema(temaAtual()); };
      window.addEventListener('cv3-tema', f);
      return function () { window.removeEventListener('cv3-tema', f); };
    }, []);

    var trocaModo = function (v) {
      setModo(v);
      try { localStorage.setItem('oimpresso.artefato.cv3.modo', v); } catch (e) {}
    };
    var trocaTema = function (v) { window.__cv3Tema(v); setTema(v); };

    return h(React.Fragment, null,
      h('header', { className: 'cv3-bar' },
        h('div', { className: 'cv3-id' },
          h('span', { className: 'cv3-pill' }, 'Protótipo'),
          h('span', { className: 'cv3-rota' }, '/sells/create-v3'),
          h('span', { className: 'cv3-nota' }, 'Âncora venda-v3.jsx · dados de cena, nada é gravado')
        ),
        h('div', { className: 'cv3-ctrl' },
          h(Seg, { rotulo: 'Modo da tela', valor: modo, onTroca: trocaModo,
            opcoes: [['create', 'Nova venda'], ['edit', 'Editar venda']] }),
          h(Seg, { rotulo: 'Tema', valor: tema, onTroca: trocaTema,
            opcoes: [['light', 'Claro'], ['dark', 'Escuro']] })
        )
      ),
      h('main', { className: 'cv3-palco' },
        h(window.VendaV3Create, { key: modo, modo: modo })
      )
    );
  }

  ReactDOM.createRoot(raiz).render(h(Palco));
})();
