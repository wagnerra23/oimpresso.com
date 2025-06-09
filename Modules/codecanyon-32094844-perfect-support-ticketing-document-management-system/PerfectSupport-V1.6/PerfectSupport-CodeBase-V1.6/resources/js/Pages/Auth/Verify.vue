<template>
	<div class="auth-wrapper">
		<div class="auth-content subscribe">
			<div class="col-md-12">
				<div class="card">
					<div class="row no-gutters">
						<div class="col-md-4 col-lg-6 d-none d-md-flex d-lg-flex theme-bg align-items-center justify-content-center">
							<i class="fas fa-user-check fa-10x text-white"></i>
						</div>
						<div class="col-md-8 col-lg-6">
							<div class="card-body text-center">
								<div class="row justify-content-center">
									<div class="col-sm-10">
										<div class="mb-4">
											<i class="far fa-envelope auth-icon"></i>
										</div>
										<h4 class="mb-4">
											{{ __('Verify Your Email Address') }}
										</h4>
										<p>
											{{ __('Before proceeding, please check your email for a verification link.') }}
	                    					{{ __('If you did not receive the email') }},
	                    				</p>
										<form class="d-inline" method="POST" @submit.prevent="submit">
                        					<button type="submit" class="btn btn-link p-0 m-0 align-baseline">
                        						{{ __('click here to request another') }}
                        					</button>.
                    					</form>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
	export default{
		metaInfo: { title: 'Email verification' },
		methods: {
			submit(){
                this.sending = true;
				this.$inertia.post(this.route_ziggy('verification.resend')).then(() => this.sending = false)
			}
		}
	}
</script>