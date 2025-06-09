@extends('install.layout', ['no_header' => 1])

@section('content')
<div class="container">
    <h2 class="text-center">{{ config('app.name') }} Installation <small>Step 1 of 3</small></h2>
    <div class="row justify-content-md-center">
        <div class="col-md-10">
            <hr/>
            @include('install.partials.nav', ['active' => 'install'])

                <div class="card">
                
                    <div class="card-header">
                        <h3 class="text-success">
                            Welcome to Installation!
                        </h3>
                    </div>

                    <div class="card-body">
                    
                        <p><strong class="text-danger">[IMPORTANT]</strong> Before you start installing make sure you have following information ready with you:</p>

                        <ol>
                            <li>
                                <b>Step-by-Step document</b> - <a href="https://ultimatefosters.com/docs/perfect-support-ticket-document-management-system/installation/" target="_blank">Documentation</a>
                            </li>
                            <li>
                                <b>Application Name</b> - Something short & Meaningful.
                            </li>
                            <li>
                                <b>Database informations:</b>
                                <ul>
                                    <li>Username</li>
                                    <li>Password</li>
                                    <li>Database Name</li>
                                    <li>Database Host</li>
                                </ul>
                            </li>
                            <li>
                                <b>Mail Configuration</b> - SMTP details (optional)
                            </li>
                            <li>
                                <b>Envato or Codecanyon Details:</b>
                                <ul>
                                    <li><b>Envato purchase code.</b> (<a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_blank">Where Is My Purchase Code?</a>)</li>
                                    <li>
                                        <b>Envato Username.</b> (Your envato username)
                                    </li>
                                </ul>
                            </li>
                        </ol>

                        @include('install.partials.i_service')

                        @include('install.partials.e_license')
                  
                        <a href="{{route('install.details')}}" class="btn btn-primary float-right">I Agree, Let's Go!</a>
                    </div>
                
                </div>
            </div>
    </div>
</div>
@endsection
