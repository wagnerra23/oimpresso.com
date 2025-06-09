<div class="row doc_search_div">
	<div class="col-md-12 mt-2">
		@if (Route::has('login'))
			<div class="auth-div">
				@auth
					@php
						$user = Auth::user();
						if(in_array($user->role, ['admin', 'support_agent'])){
							$home = action('HomeController@index');
						} elseif ($user->role == 'customer') {
							$home =  action('Customer\TicketController@index');
						}
					@endphp
					<a class="btn btn-success btn-sm" href="{{$home}}">
						<i class="fas fa-home"></i>Home
					</a>
				@else
					<a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">
						<i class="fas fa-unlock-alt auth-icon"></i>Login
					</a>
					@if (Route::has('register'))
						<a href="{{ route('register') }}" class="btn btn-info btn-sm">
							<i class="fas fa-user-plus auth-icon"></i>Sign Up
						</a>
					@endif
				@endauth
			</div>
		@endif
	</div>
	<div class="col-md-12 search-header text-center">
		<div class="text-white">
			Documentations
		</div>
	</div>
	<div class="col-md-8 offset-md-2">
		<input type="text" name="search" id="search" class="form-control form-control-lg" placeholder="{{__('messages.search')}}" autofocus>
	</div>
	@if(!empty($__gcse_html))
		<div class="col-md-12 mt-2">
			{!!$__gcse_html!!}  
		</div>
	@endif
	<div class="col-md-12">
		<div id="search_suggestions" class="mt-4"></div>
	</div>
</div>