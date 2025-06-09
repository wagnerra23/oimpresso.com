@inject(\'request\', \'Illuminate\\Http\\Request\')
<!-- Main Header -->

<div
    <div class="navbar bg-base-100 h-16 border-solid border-b-2 border-base-300/30 no-print">
    <div class="w-full">
        <div class="flex lg:items-center !w-full">
            <div class="flex items-start lg:w-auto flex-1">
                <button type="button" class="navbar-start lg:small-view-button xl:w-40 lg:hidden inline-flex text-sm text-base-content transition-all duration-200">
                    <span class="sr-only">
                        Sidebar Menu
                    </span>
                    <svg aria-hidden="true" class="size-9" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 6l16 0" />
                        <path d="M4 12l16 0" />
                        <path d="M4 18l16 0" />
                    </svg>
                </button>

                <button type="button"
                    class="side-bar-collapse hidden lg:inline-flex items-center btn-sm btn btn-ghost text-secondary">
                    <span class="sr-only">
                        Collapse Sidebar
                    </span>
                    <svg aria-hidden="true" class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" />
                        <path d="M15 4v16" />
                        <path d="M10 10l-2 2l2 2" />
                    </svg>
                </button>

                {{-- Showing active package for SaaS Superadmin --}}
                @if(Module::has(\'Superadmin\'))
                    @includeIf(\'superadmin::layouts.partials.active_subscription\')
                @endif

                {{-- When using superadmin, this button is used to switch users --}}
                @if(!empty(session(\'previous_user_id\')) && !empty(session(\'previous_username\')))
                    <a href="{{route(\'sign-in-as-user\', session(\'previous_user_id\'))}}" class="dropdown"><i class="fas fa-undo"></i> @lang(\'lang_v1.back_to_username\', [\'username\' => session(\'previous_username\')] )</a>
                @endif

            </div>

            <div class="menu menu-horizontal justify-end gap-3">
                @if (Module::has(\'Essentials\'))
                    @includeIf(\'essentials::layouts.partials.header_part\')
                @endif
                <details class="dropdown relative inline-block text-left">
                    <summary
                        class="inline-flex transition-all btn btn-sm btn-ghost text-secondary gap-1">
                        <svg aria-hidden="true" class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                            <path d="M9 12h6" />
                            <path d="M12 9v6" />
                        </svg>
                    </summary>
                    <ul class="menu dropdown-content z-[1] w-48 absolute left-0 z-10 mt-2 origin-top-right bg-base-100 rounded-lg shadow-lg focus:outline-none"
                        role="menu" tabindex="-1">
                        <div class="p-2" role="none">
                            <a href="{{ route(\'calendar\') }}"
                                class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 rounded-lg hover:text-gray-900 hover:bg-gray-100"
                                role="menuitem" tabindex="-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-calendar"
                                    width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <rect x="4" y="5" width="16" height="16" rx="2" />
                                    <line x1="16" y1="3" x2="16" y2="7" />
                                    <line x1="8" y1="3" x2="8" y2="7" />
                                    <line x1="4" y1="11" x2="20" y2="11" />
                                    <line x1="11" y1="15" x2="12" y2="15" />
                                    <line x1="12" y1="15" x2="12" y2="18" />
                                </svg>
                                @lang(\'lang_v1.calendar\')
                            </a>
                            @if (Module::has(\'Essentials\'))
                                <a href="#"
                                    data-href="{{ action([\\Modules\\Essentials\\Http\\Controllers\\ToDoController::class, \'create\']) }}"
                                    data-container="#task_modal"
                                    class="btn-modal flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 rounded-lg hover:text-gray-900 hover:bg-gray-100"
                                    role="menuitem" tabindex="-1">
                                    <svg aria-hidden="true" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M3 3m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                                        <path d="M9 12l2 2l4 -4" />
                                    </svg>
                                    @lang(\'essentials::lang.add_to_do\')
                                </a>
                            @endif
                            @if (auth()->user()->hasRole(\'Admin#\' . auth()->user()->business_id))
                                <a href="#" id="start_tour"
                                    class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 rounded-lg hover:text-gray-900 hover:bg-gray-100"
                                    role="menuitem" tabindex="-1">
                                    <svg aria-hidden="true" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                        <path d="M12 17l0 .01" />
                                        <path d="M12 13.5a1.5 1.5 0 0 1 1 -1.5a2.6 2.6 0 1 0 -3 -4" />
                                    </svg>
                                    Application Tour
                                </a>
                            @endif
                        </div>
                    </ul>

                </details>


                {{-- data-toggle="popover" remove this for on hover show --}}
                <div class="dropdown dropdown-bottom">
                    <button id="btnCalculator" title="@lang(\'lang_v1.calculator\')" 
                        type="button" data-trigger="click" data-html="true" data-placement="bottom" 
                        class="btn btn-sm btn-ghost text-secondary">
                        <span class="sr-only" aria-hidden="true">
                            Calculator
                        </span>
                        <svg aria-hidden="true" class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 3m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" />
                            <path d="M8 7m0 1a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v1a1 1 0 0 1 -1 1h-6a1 1 0 0 1 -1 -1z" />
                            <path d="M8 14l0 .01" />
                            <path d="M12 14l0 .01" />
                            <path d="M16 14l0 .01" />
                            <path d="M8 17l0 .01" />
                            <path d="M12 17l0 .01" />
                            <path d="M16 17l0 .01" />
                        </svg>
                    </button>
                    <div class="dropdown-content bg-base-100 shadow rounded-box p-4 w-64">
                        @include(\'layouts.partials.calculator\')
                    </div>
                </div>

                @if (in_array(\'pos_sale\', $enabled_modules))
                    @can(\'sell.create\')
                        <a href="{{ action([\\App\\Http\\Controllers\\SellPosController::class, \'create\']) }}"
                            class="btn btn-sm font-normal btn-ghost text-secondary">
                            <svg aria-hidden="true" class="size-5 hidden md:block" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                <path d="M14 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                <path d="M4 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                <path d="M14 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                            </svg>
                            @lang(\'sale.pos_sale\')
                        </a>
                    @endcan
                @endif
                @if (Module::has(\'Repair\'))
                    @includeIf(\'repair::layouts.partials.header\')
                @endif
                @can(\'profit_loss_report.view\')
                    <button type="button" type="button" id="view_todays_profit" title="{{ __(\'home.todays_profit\') }}"
                        data-toggle="tooltip" data-placement="bottom"
                        class="hidden sm:inline-flex btn btn-sm btn-ghost text-secondary">
                        <span class="sr-only">
                            Today\'s Profit
                        </span>
                        <svg aria-hidden="true" class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                            <path d="M3 6m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                            <path d="M18 12l.01 0" />
                            <path d="M6 12l.01 0" />
                        </svg>
                    </button>
                @endcan

                <button type="button"
                    class="hidden lg:inline-flex btn btn-sm font-normal btn-ghost text-secondary">
                    {{ @format_date(\'now\') }}
                </button>

                @include(\'layouts.partials.header-notifications\')

                {{-- Botão para abrir o Chat Dify --}}
                <button id="open-dify-chat" type="button" title="Assistente IA"
                    class="btn btn-sm btn-ghost text-secondary">
                    <span class="sr-only">Abrir Chat IA</span>
                    {{-- Ícone de Chat (exemplo) --}}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </button>

                {{-- User Profile Dropdown --}}
                <details class="dropdown relative inline-block text-left">
                    <summary data-toggle="popover"
                        class="btn btn-sm btn-ghost text-secondary">
                        <span class="hidden md:block">{{ Auth::User()->first_name }} {{ Auth::User()->last_name }}</span>

                        <svg  xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="size-5"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" /></svg>
                    </summary>

                    <ul class="p-2 w-48 absolute right-0 z-10 mt-2 origin-top-right bg-base-100 rounded-lg shadow-lg border focus:outline-none"
                        role="menu" tabindex="-1">
                        <div class="px-4 pt-3 pb-1" role="none">
                            <p class="text-sm" role="none">
                                Signed in as
                            </p>
                            <p class="text-sm font-medium text-gray-900 truncate" role="none">
                                {{ Auth::User()->first_name }} {{ Auth::User()->last_name }}
                            </p>
                        </div>

                        <li>
                            <a href="{{ action([\\App\\Http\\Controllers\\UserController::class, \'getProfile\']) }}"
                                class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 rounded-lg hover:text-gray-900 hover:bg-gray-100"
                                role="menuitem" tabindex="-1">
                                <svg aria-hidden="true" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                    <path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                                    <path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" />
                                </svg>
                                @lang(\'lang_v1.profile\')
                            </a>
                        </li>
                        <li>
                        <div class="dropdown dropdown-end block">
                            <button tabindex="0" role="button" class="btn-sm btn-ghost text-secondary flex items-center">
                               Selecionar Tema
                                <svg width="12px" height="12px" class="h-2 w-2 fill-current opacity-60 ml-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2048 2048">
                                    <path d="M1799 349l242 241-1017 1017L7 590l242-241 775 775 775-775z"></path>
                                </svg>
                            </button>
                            <div tabindex="0" class="dropdown-content z-[1] p-2 shadow-2xl bg-base-300 rounded-box w-52">
                                <div class="grid grid-cols-1 gap-3 p-3" data-choose-theme>
                                    <button class="outline-base-content overflow-hidden rounded-lg text-left" data-set-theme="" data-act-class="[&_svg]:visible">
                                        <div data-theme="light" class="bg-base-100 text-base-content w-full cursor-pointer font-sans">
                                            <div class="grid grid-cols-5 grid-rows-3">
                                                <div class="col-span-5 row-span-3 row-start-1 flex gap-1 py-3 px-4 items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 invisible">
                                                        <path d="M20.285 2l-11.285 11.567-5.286-5.011-3.714 3.716 9 8.728 15-15.285z"></path>
                                                    </svg>
                                                    <div class="flex-grow text-sm">Default</div>
                                                    <div class="flex flex-shrink-0 flex-wrap gap-1 h-full">
                                                        <div class="bg-primary w-2 rounded"></div>
                                                        <div class="bg-secondary w-2 rounded"></div>
                                                        <div class="bg-accent w-2 rounded"></div>
                                                        <div class="bg-neutral w-2 rounded"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </button>
                                    @foreach(config(\'constants.themes\') as $theme)
                                        <button class="outline-base-content overflow-hidden rounded-lg text-left" data-set-theme="{{ $theme }}" data-act-class="[&_svg]:visible">
                                            <div data-theme="{{ $theme }}" class="bg-base-100 text-base-content w-full cursor-pointer font-sans">
                                                <div class="grid grid-cols-5 grid-rows-3">
                                                    <div class="col-span-5 row-span-3 row-start-1 flex gap-1 py-3 px-4 items-center">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 invisible">
                                                            <path d="M20.285 2l-11.285 11.567-5.286-5.011-3.714 3.716 9 8.728 15-15.285z"></path>
                                                        </svg>
                                                        <div class="flex-grow text-sm">{{ ucfirst($theme) }}</div>
                                                        <div class="flex flex-shrink-0 flex-wrap gap-1 h-full">
                                                            <div class="bg-primary w-2 rounded"></div>
                                                            <div class="bg-secondary w-2 rounded"></div>
                                                            <div class="bg-accent w-2 rounded"></div>
                                                            <div class="bg-neutral w-2 rounded"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        </li>
                        <li>
                            <a href="{{ route(\'logout\') }}"
                                onclick="event.preventDefault(); document.getElementById(\'logout-form\').submit();"
                                class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 rounded-lg hover:text-gray-900 hover:bg-gray-100"
                                role="menuitem" tabindex="-1">
                                <svg aria-hidden="true" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
                                    <path d="M9 12h12l-3 -3" />
                                    <path d="M18 15l3 -3" />
                                </svg>
                                @lang(\'lang_v1.logout\')
                            </a>
                            <form id="logout-form" action="{{ route(\'logout\') }}" method="POST" style="display: none;">
                                {{ csrf_field() }}
                            </form>
                        </li>
                    </ul>
                </details>
            </div>
        </div>
    </div>
</div>

