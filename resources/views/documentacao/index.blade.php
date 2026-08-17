@extends('documentacao.layout')

@section('titulo', 'Documentação do sistema')

@section('conteudo')
  <div class="col">
    <div class="stamp">Documentação do sistema</div>

    <div class="colophon">
      <span><b>Fonte</b> {{ $fonte }}</span>
      @if ($atualizadoEm)
        <span><b>Atualizado em</b> {{ $atualizadoEm }}</span>
      @endif
      <span><b>Renderizado</b> a cada acesso</span>
      @unless ($buscaDisponivel)
        <span><b>Busca</b> índice indisponível neste ambiente</span>
      @endunless
    </div>
  </div>

  <div class="com-trilho">
    {{-- Sumário DERIVADO dos títulos do próprio documento (comSumario no controller).
         Não existe lista escrita à mão: título novo entra sozinho, título removido sai
         sozinho. É a mesma razão de a página inteira ser renderizada em runtime. --}}
    @if (! empty($sumario))
      <nav class="trilho" aria-label="Sumário do documento">
        <p class="trilho-tit">Nesta página</p>
        <ol>
          @foreach ($sumario as $item)
            <li @class(['grupo' => $item['nivel'] === 2, 'sub' => $item['nivel'] === 4])>
              <a href="#{{ $item['id'] }}">
                @if ($item['codigo'])<span class="cod">{{ $item['codigo'] }}</span>@endif
                <span>{{ $item['rotulo'] }}</span>
              </a>
            </li>
          @endforeach
        </ol>
      </nav>
    @endif

    <div class="col">
      <div class="doc">{!! $html !!}</div>

      <footer>
        Esta página <strong>é</strong> o documento <code>{{ $fonte }}</code>, renderizado —
        não uma cópia dele. Alterou a fonte por PR? A página muda no próximo acesso.
        Não existe versão intermediária para ficar desatualizada.
        @if ($buscaDisponivel)
          Para o resto do acervo, use a busca acima — ela cobre {{ $escopoProsa }}.
        @endif
      </footer>
    </div>
  </div>
@endsection

@push('script')
<script>
  // Tabela larga rola sozinha — o corpo da página nunca rola de lado.
  document.querySelectorAll('.doc table').forEach(function (t) {
    if (t.parentElement.classList.contains('tabela-scroll')) return;
    var box = document.createElement('div');
    box.className = 'tabela-scroll';
    t.parentNode.insertBefore(box, t); box.appendChild(t);
  });

  // Marca no trilho a seção que está sendo lida. Sem JS o trilho continua um
  // sumário de links funcionando — o realce é conforto, não requisito.
  (function () {
    var trilho = document.querySelector('.trilho');
    if (!trilho) return;

    var links = Array.prototype.slice.call(trilho.querySelectorAll('a[href^="#"]'));
    var alvos = links
      .map(function (a) {
        return { link: a, el: document.getElementById(decodeURIComponent(a.hash.slice(1))) };
      })
      .filter(function (p) { return p.el; });

    if (!alvos.length) return;

    var atual = null;
    var agendado = false;

    // "Seção corrente" = o último título que já passou da linha de leitura. Medimos
    // getBoundingClientRect (o que o browser resolveu), nunca offsetTop — offsetParent
    // não é o container que rola.
    function marcar() {
      agendado = false;

      var escolhido = alvos[0];
      for (var i = 0; i < alvos.length; i++) {
        if (alvos[i].el.getBoundingClientRect().top <= 96) escolhido = alvos[i];
        else break;
      }

      // Fim da página: a última seção é a corrente, mesmo sem ter cruzado a linha.
      if (window.innerHeight + window.scrollY >= document.body.scrollHeight - 4) {
        escolhido = alvos[alvos.length - 1];
      }

      if (escolhido === atual) return;
      if (atual) atual.link.removeAttribute('aria-current');
      escolhido.link.setAttribute('aria-current', 'true');
      atual = escolhido;
    }

    function agendar() {
      if (agendado) return;
      agendado = true;
      window.requestAnimationFrame(marcar);
    }

    window.addEventListener('scroll', agendar, { passive: true });
    window.addEventListener('resize', agendar, { passive: true });
    marcar();
  })();
</script>
@endpush
