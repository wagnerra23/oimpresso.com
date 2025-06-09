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
                        <i class="fas fa-user-plus auth-icon"></i>
                    </div>
                    <h3 class="mb-4">{{ __('Sign Up') }}</h3>
                    <form method="POST" action="register" @submit.prevent="submit">
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-user-tie"></i>
                                </span>
                            </div>
                            <input id="name" type="text" 
                                :class="['form-control', $page.errors.name ? 'is-invalid': '']"
                                :placeholder="__('Name')" 
                                v-model="form.name" required 
                                autocomplete="name" autofocus>
                            <span class="invalid-feedback" role="alert"
                                v-if="$page.errors.name">
                                <strong>{{ $page.errors.name[0] }}</strong>
                            </span>
                        </div>
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
                                autocomplete="email">
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
                                :class="['form-control', $page.errors.password ? 'is-invalid': '']" required v-model="form.password"
                                :placeholder="__('Password')">
                            <span class="invalid-feedback" role="alert"
                                v-if="$page.errors.password">
                                <strong>{{ $page.errors.password[0] }}</strong>
                            </span>
                        </div>
                        <div class="input-group mb-4">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                            <input id="password_confirmation" type="password" 
                                :class="['form-control', $page.errors.password ? 'is-invalid': '']" required v-model="form.password_confirmation"
                                :placeholder="__('Confirm Password')">
                            <span class="invalid-feedback" role="alert"
                                v-if="$page.errors.password">
                                <strong>{{ $page.errors.password[0] }}</strong>
                            </span>
                        </div>
                        <button class="btn btn-primary shadow-2 mb-4">
                            {{ __('Sign Up') }}
                        </button>
                        <p class="mb-0 text-muted">
                            Already have an account? 
                            <inertia-link :href="route_ziggy('login')">
                                {{ __('Login') }}
                            </inertia-link>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    export default{
        metaInfo: { title: 'Sign up' },
        props: {

        },
        data(){
            return {
                form: {
                    name:null,
                    email: null,
                    password: null,
                    password_confirmation:null
                },
            }
        },
        methods: {
            submit(){
                this.$inertia.post(this.route_ziggy('register.attempt'), {
                    name: this.form.name,
                    email: this.form.email,
                    password: this.form.password,
                    password_confirmation:this.form.password_confirmation
                }).then((response) => console.log(response));
            }
        }
    }
</script>