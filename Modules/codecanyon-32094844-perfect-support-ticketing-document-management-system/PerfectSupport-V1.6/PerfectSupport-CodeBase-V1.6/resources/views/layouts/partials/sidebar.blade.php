<nav class="pcoded-navbar">
    <div class="navbar-wrapper">
        <div class="navbar-brand header-logo">
            <a href="{{ route('documentation-index') }}" class="b-brand">
                <div class="b-bg">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <span class="b-title">
                    {{ config('app.name', 'Laravel') }}
                </span>
            </a>
            <a class="mobile-menu" id="mobile-collapse" href="#!">
                <span></span>
            </a>
        </div>
        <div class="navbar-content scroll-div">
            <ul class="nav pcoded-inner-navbar">
                <li class="nav-item pcoded-menu-caption">
                    <label>
                        <a href="{{route('view.documentation', ['slug' => $documentation->doc_slug, 'documentation' => $documentation->id])}}" class="text-white">
                            {{ucfirst($documentation->title)}}
                        </a>
                    </label>
                </li>
                @if(count($documentation->sections) > 0)
                    @foreach($documentation->sections as $section)
                        <li class="nav-item pcoded-hasmenu @if(isset($current_sec_slug) && ($section->doc_slug == $current_sec_slug)) active pcoded-trigger @endif">
                            <a href="#!" class="nav-link">
                                <span class="pcoded-micon">
                                    <i class="fas fa-book-open"></i>
                                </span>
                                <span class="pcoded-mtext">
                                    {{ucfirst($section->title)}}
                                </span>
                            </a>
                            <ul class="pcoded-submenu">
                                <li @if(isset($active_id) && ($section->id == $active_id)) class="active" @endif>
                                    <a href="{{route('view.documentation.section', ['slug' => $section->doc_slug, 'documentation' => $section->id])}}" class="">
                                        Overview
                                    </a>
                                </li>
                                @foreach($section->articles as $article)
                                    <li @if(isset($active_id) && ($article->id == $active_id)) class="active" @endif>
                                        <a href="{{route('view.section.article', ['slug' => $article->doc_slug, 'documentation' => $article->id])}}">
                                            {{ucfirst($article->title)}}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                @endif
            </ul>
        </div>
    </div>
</nav>