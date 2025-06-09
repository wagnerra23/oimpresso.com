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
                    <h5 class="mb-4">
                        {{__('Reset Password')}}
                    </h5>
                    <form method="POST" action="login" @submit.prevent="submit">
                        <input type="hidden" name="token" :value="token">
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
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                            <input type="password" :class="['form-control', $page.errors.password ? 'is-invalid': '']"
                                placeholder="Password"
                                autocomplete="new-password"
                                v-model="form.password"
                                required>
                            <span class="invalid-feedback" role="alert"
                                v-if="$page.errors.password">
                                <strong>{{ $page.errors.password[0] }}</strong>
                            </span>
                        </div>                  
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                            <input type="password" class="form-control"
                                placeholder="Confirm Password"
                                v-model="form.password_confirmation"
                                required autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary shadow-2 mb-4">
                            {{__('Reset Password')}}
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
        props:['token', 'email'],
		data(){
			return {
				sending: false,
				form: {
					email: null,
                    password: null,
                    password_confirmation: null
				},
			}
		},
        created() {
            this.form.email = this.email;
        },
		methods: {
			submit(){
                this.sending = true;
				this.$inertia.post(this.route_ziggy('password.update'), {
			    	email: this.form.email,
                    password: this.form.password,
                    password_confirmation: this.form.password_confirmation,
                    token:this.token
			    }).then(() => this.sending = false)
			}
		}
	}
</script>