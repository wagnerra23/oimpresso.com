<template>
	<div class="auth-wrapper">
        <div class="auth-content">
            <div class="auth-bg">
                <span class="r"></span>
                <span class="r s"></span>
                <span class="r s"></span>
                <span class="r"></span>
            </div>
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="feather icon-mail auth-icon"></i>
                    </div>
                    <h3 class="mb-4">
                    	{{__('Reset Password')}}
                    </h3>
                    <div class="alert alert-success" role="alert" v-if="$page.status.length">
                        <b>{{$page.status}}</b>
                    </div>
                    <form method="POST" action="login" @submit.prevent="submit">
	                    <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-at"></i>
                                </span>
                            </div>
	                        <input id="email" type="email" 
                                :class="['form-control', $page.errors.email ? 'is-invalid': '']"
                                :placeholder="__('E-Mail Address')" 
                                v-model="form.email" required 
                                autocomplete="email" autofocus>
                            <span class="invalid-feedback" role="alert"
                                v-if="$page.errors.email">
                                <strong>{{ $page.errors.email[0] }}</strong>
                            </span>
	                    </div>
	                    <button class="btn btn-primary mb-4 shadow-2">
	                    	{{__('Send Password Reset Link')}}
	                    </button>
	                </form>
                    <p class="mb-0 text-muted">
                    	{{__('Don’t have an account?')}}
                    	<inertia-link :href="route_ziggy('register')">
                    		{{ __('Signup') }}
                    	</inertia-link>
                    </p>
                    <p class="mb-0 text-muted">
                        Already have an account? 
                        <inertia-link :href="route_ziggy('login')">
                            {{ __('Login') }}
                        </inertia-link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
	export default{
		data(){
			return {
				sending: false,
				form: {
					email: null,
				},
			}
		},
		methods: {
			submit(){
                this.sending = true;
				this.$inertia.post(this.route_ziggy('password.email'), {
			    	email: this.form.email
			    }).then(() => this.sending = false)
			}
		}
	}
</script>