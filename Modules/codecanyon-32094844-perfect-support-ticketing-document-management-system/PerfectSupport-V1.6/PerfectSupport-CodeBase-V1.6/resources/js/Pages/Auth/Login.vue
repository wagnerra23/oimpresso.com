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
                        <i class="fas fa-unlock-alt auth-icon"></i>
                    </div>
                    <h3 class="mb-4">{{ __('Login') }}</h3>
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
                        <div class="input-group mb-4">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                            <input id="password" type="password" 
                                class="form-control" required autocomplete="current-password" v-model="form.password"
                                :placeholder="__('Password')">
                            <span class="invalid-feedback" role="alert"
                                v-if="$page.errors.password">
                                <strong>{{ $page.errors.password[0] }}</strong>
                            </span>
                        </div>
                        <div class="form-group text-left">
                            <div class="checkbox checkbox-fill d-inline">
                                <input type="checkbox" id="checkbox-fill-a1" checked="" v-model="form.remember">
                                <label for="checkbox-fill-a1" class="cr"> {{ __('Remember Me') }}</label>
                            </div>
                        </div>
                        <button class="btn btn-primary shadow-2 mb-4">
                            {{ __('Login') }}
                        </button>
                        <a :href="route_ziggy('documentation-index')" target="_blank" rel="noopener" class="btn btn-outline-primary shadow-2 mb-4">
                            {{ __('messages.view_doc') }}
                        </a>
                        <p class="mb-2 text-muted">
                            Forgot password?
                            <inertia-link :href="route_ziggy('password.request')">
                                {{__('Reset')}}
                            </inertia-link>
                        </p>
                        <p class="mb-0 text-muted">
                            Don’t have an account?
                            <inertia-link :href="route_ziggy('register')">
                                {{ __('Signup') }}
                            </inertia-link>
                        </p>
                    </form>
                </div>

                <table v-if="$commonFunction.isDemo()" class="table text-center">
                    <tr>
                        <td colspan="3">Demo credentials</td>
                    </tr>
                    <tr>
                        <td>Admin</td>
                        <td>superadmin@example.com</td>
                        <td>12345678</td>
                    </tr>
                    <tr>
                        <td>Agent</td>
                        <td>agent@example.com</td>
                        <td>12345678</td>
                    </tr>
                    <tr>
                        <td>Customer</td>
                        <td>customer@example.com</td>
                        <td>12345678</td>
                    </tr>
                </table>

                </div>
            </div>
        </div>
    </div>
</div>
</template>
<script>
	export default{
		metaInfo: { title: 'Login' },
		data(){
			return {
				sending: false,
				form: {
					email: null,
	        		password: null,
	        		remember: null,
				},
			}
		},
        watch : {
            '$page.errors': function (errors) {
                console.error(errors);
            }
        },
		methods: {
			submit(){
                this.sending = true;
				this.$inertia.post(this.route_ziggy('login.attempt'), {
			    	email: this.form.email,
			    	password: this.form.password,
			    	remember: this.form.remember,
			    }).then(() => this.sending = false)
			}
		}
	}
</script>