@extends('install.layout', ['no_header' => 1])

@section('content')
<div class="container">
	<h2 class="text-center">{{ config('app.name') }}</h2>
    <div class="row">

        <div class="col-md-8 col-md-offset-2">
         	<h3 class="text-success">Great! <br/>Application succesfully installed.</h3>
         	<hr>
          	<p>All the application details is saved in <b>.env</b> file. You can change them anytime there.</p>

         	<p><b><a href="{{route('login')}}" target="_blank">Login here</a></b></p>
         	<p><b>Username</b>: superadmin@example.com</p>
         	<p><b>Password</b>: 12345678</p>
        </div>
    </div>
</div>
@endsection
