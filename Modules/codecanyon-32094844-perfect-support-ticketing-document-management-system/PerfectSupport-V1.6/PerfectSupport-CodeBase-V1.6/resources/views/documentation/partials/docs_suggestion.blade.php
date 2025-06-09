@if(count($documentations) > 0)
	<ul class="list-group">
		@foreach($documentations as $documentation)
			<li class="list-group-item">
				@php
					if ($documentation->doc_type == 'doc') {
						$url = route('view.documentation', ['slug' => \Str::slug($documentation->title, '-'), 'documentation' => $documentation->id]);
					} elseif ($documentation->doc_type == 'section') {
						$url = route('view.documentation.section', ['slug' => \Str::slug($documentation->title, '-'), 'documentation' => $documentation->id]);
					} elseif ($documentation->doc_type == 'article') {
						$url = route('view.section.article', ['slug' => \Str::slug($documentation->title, '-'), 'documentation' => $documentation->id]);
					}
				@endphp
				<a href="{{$url}}">
					<i class="fas fa-external-link-square-alt mr-2"></i>
					{{ucfirst($documentation->title)}}
				</a>
			</li>
		@endforeach
	</ul>
@else
	@if(!empty($search_params))
		<h4 class="text-center text-white">
			{{__('messages.we_didnot_find_search_result', ['search_params' => $search_params])}}
			<a href="{{ route('login') }}" target="_blank" class="btn btn-outline-light btn-sm">
				<i class="fas fa-unlock-alt auth-icon"></i>{{__('messages.login_to_open_a_ticket')}}
			</a>
		</h4>
	@endif
@endif