<template>
 	<layout :title="__('messages.home')">
    	<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <h3 class="m-b-20">
        	{{__('messages.welcome',{name: $page.auth.user.name})}},
        </h3>
        <div class="page-wrapper">
        	<div class="row">
        		<!-- total tickets -->
				<div class="col-md-6 col-xl-4">
					<div class="card card-customer">
						<div class="card-block">
							<div class="row align-items-center justify-content-center">
								<div class="col">
									<h2 class="mb-2 f-w-300">
										{{total_tickets}}
									</h2>
									<h5 class="text-muted mb-0">
										{{__('messages.total_tickets')}}
									</h5>
								</div>
								<div class="col-auto">
									<i class="fas fa-ticket-alt f-30 text-white theme-bg"></i>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- /total tickets -->
				<!-- by status -->
				<template v-if="tickets_by_status.length" v-for="ticket in tickets_by_status">
					<div class="col-md-6 col-xl-4">
						<div class="card card-customer">
							<div class="card-block">
								<div class="row align-items-center justify-content-center">
									<div class="col">
										<h2 class="mb-2 f-w-300">
											{{ticket.total_tickets}}
										</h2>
										<h5 class="text-muted mb-0">
											{{__('messages.'+ticket.status)}}
										</h5>
									</div>
									<div class="col-auto">
										<i class="fas fa-ticket-alt f-30 text-white theme-bg"></i>
									</div>
								</div>
							</div>
						</div>
					</div>
				</template>
				<!-- /by status -->
        	</div>
        	<!-- total tickets by agents -->
        	<div class="row" v-if="_.includes(['admin'], $page.auth.user.role)">
        		<div class="col-md-12">
					<div class="card code-table">
						<div class="card-header">
							<h5>{{__('messages.tickets_by_agents')}}</h5>
						</div>
						<div class="card-block pb-0">
							<div class="table-responsive">
								<table class="table table-striped">
									<thead>
										<tr>
											<th>
												{{__('messages.support_agent')}}
											</th>
											<th>
												{{__('messages.new')}}
											</th>
											<th>
												{{__('messages.waiting')}}
											</th>
											<th>
												{{__('messages.pending')}}
											</th>
											<th>
												{{__('messages.closed')}}
											</th>
											<th>
												{{__('messages.open')}}
											</th>
										</tr>
									</thead>
									<tbody>
										<template v-for="agent in tickets_by_agents">
											<tr>
												<td>
													{{agent.name}}
												</td>
												<td>
													{{!_.isEmpty(agent.ticket.new) ? agent.ticket.new : 0}}
												</td>
												<td>
													{{!_.isEmpty(agent.ticket.waiting) ? agent.ticket.waiting : 0}}
												</td>
												<td>
													{{!_.isEmpty(agent.ticket.pending) ? agent.ticket.pending : 0}}
												</td>
												<td>
													{{!_.isEmpty(agent.ticket.closed) ? agent.ticket.closed : 0}}
												</td>
												<td>
													{{!_.isEmpty(agent.ticket.open) ? agent.ticket.open : 0}}
												</td>
											</tr>
										</template>
									</tbody>
								</table>
							</div>
						</div>
					</div>
        		</div>
        	</div>
        	<!-- /total tickets by agents -->
        	<!-- total tickets by producta -->
        	<div class="row" v-if="_.includes(['admin'], $page.auth.user.role)">
        		<div class="col-md-12">
					<div class="card code-table">
						<div class="card-header">
							<h5>{{__('messages.tickets_by_products')}}</h5>
						</div>
						<div class="card-block pb-0">
							<div class="table-responsive">
								<table class="table table-striped">
									<thead>
										<tr>
											<th>
												{{__('messages.product')}}
											</th>
											<th>
												{{__('messages.new')}}
											</th>
											<th>
												{{__('messages.waiting')}}
											</th>
											<th>
												{{__('messages.pending')}}
											</th>
											<th>
												{{__('messages.closed')}}
											</th>
											<th>
												{{__('messages.open')}}
											</th>
										</tr>
									</thead>
									<tbody>
										<template v-for="product in tickets_by_products">
											<tr>
												<td>
													{{product.name}}
												</td>
												<td>
													{{product.new}}
												</td>
												<td>
													{{product.waiting}}
												</td>
												<td>
													{{product.pending}}
												</td>
												<td>
													{{product.closed}}
												</td>
												<td>
													{{product.open}}
												</td>
											</tr>
										</template>
									</tbody>
								</table>
							</div>
						</div>
					</div>
        		</div>
        	</div>
        	<!-- /total tickets by products -->
        </div>
  	</layout>
</template>

<script>
	import Layout from '@/Shared/Layout';
	import Leftnav from '@/Pages/Elements/Leftnav';

	export default {
		components: {
			Layout,
			Leftnav
		},
		props: [
			'total_tickets', 'tickets_by_status',
			'tickets_by_agents', 'tickets_by_products'
		],
		mounted: function () {
		  this.$nextTick(function () {
		    console.log("hhere");
		  })
		}
	}
</script>