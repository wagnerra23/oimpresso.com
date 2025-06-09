<template>
	<div>
		<li class="notification pt-1 pb-1"
			v-if="(notification.type == 'App\\Notifications\\Customer\\NewTicketAdded') && !_.isNull(notification.ticket)">
			<inertia-link :href="route_ziggy('customer.tickets.show', [notification.ticket.id])">
			    <div class="media">
			        <img class="img-radius" :src="'https://ui-avatars.com/api/'+notification.ticket.user.name">
			        <div class="media-body">
			            <p>
			                <strong>
			                	{{notification.ticket.user.name}}
			                </strong>
			                <span class="n-time text-muted">
			                    <i class="far fa-clock m-r-10"></i>
			                    {{
			                    	$commonFunction.timeFromNow(notification.created_at)
			                    }}
			                </span>
			            </p>
			            <p v-html="__('messages.new_ticket_added_notif',{ticket_ref: notification.ticket.ticket_ref})">
			            </p>
			        </div>
			    </div>
			</inertia-link>
		</li>
		<li class="notification pt-1 pb-1"
			v-if="(notification.type == 'App\\Notifications\\SupportAgent\\NewTicketAssigned') && !_.isNull(notification.ticket)">
			<inertia-link :href="route_ziggy('customer.tickets.show', [notification.ticket.id])">
			    <div class="media">
			        <img class="img-radius" :src="'https://ui-avatars.com/api/'+notification.assigned.name">
			        <div class="media-body">
			            <p>
			                <strong>
			                	{{notification.assigned.name}}
			                </strong>
			                <span class="n-time text-muted">
			                    <i class="far fa-clock m-r-10"></i>
			                    {{
			                    	$commonFunction.timeFromNow(notification.created_at)
			                    }}
			                </span>
			            </p>
			            <p v-html="__('messages.support_agent_assigned_notif',{ticket_ref: notification.ticket.ticket_ref})">
			            </p>
			        </div>
			    </div>
			</inertia-link>
		</li>
		<li class="notification pt-1 pb-1"
			v-if="(notification.type == 'App\\Notifications\\Customer\\AgentRepliedToTicket') && !_.isNull(notification.comment)">
			<inertia-link :href="route_ziggy('customer.tickets.show', [notification.comment.ticket.id])">
			    <div class="media">
			        <img class="img-radius" :src="'https://ui-avatars.com/api/'+notification.comment.user.name">
			        <div class="media-body">
			            <p>
			                <strong>
			                	{{notification.comment.user.name}}
			                </strong>
			                <span class="n-time text-muted">
			                    <i class="far fa-clock m-r-10"></i>
			                    {{
			                    	$commonFunction.timeFromNow(notification.created_at)
			                    }}
			                </span>
			            </p>
			            <p v-html="__('messages.agent_replied_ticket_notif',{name: notification.comment.user.name, ticket_ref: notification.comment.ticket.ticket_ref})"></p>
			        </div>
			    </div>
			</inertia-link>
		</li>
		<li class="notification pt-1 pb-1"
			v-if="(notification.type == 'App\\Notifications\\SupportAgent\\CustomerRepliedToTicket') && !_.isNull(notification.ticket)">
			<inertia-link :href="route_ziggy('customer.tickets.show', [notification.ticket.id])">
			    <div class="media">
			        <img class="img-radius" :src="'https://ui-avatars.com/api/'+notification.ticket.user.name">
			        <div class="media-body">
			            <p>
			                <strong>{{notification.ticket.user.name}}</strong>
			                <span class="n-time text-muted">
			                    <i class="far fa-clock m-r-10"></i>
			                    {{
			                    	$commonFunction.timeFromNow(notification.created_at)
			                    }}
			                </span>
			            </p>
			            <p v-html="__('messages.customer_replied_ticket_notif',{name: notification.ticket.user.name, ticket_ref: notification.ticket.ticket_ref})"></p>
			        </div>
			    </div>
			</inertia-link>
		</li>
		<li class="notification pt-1 pb-1"
			v-if="(notification.type == 'App\\Notifications\\SupportAgent\\OtherAgentsRepliedToTicket') && !_.isNull(notification.comment) && !_.isNull(notification.comment.ticket) && !_.isNull(notification.comment.user)">
			<inertia-link :href="route_ziggy('customer.tickets.show', [notification.comment.ticket.id])">
			    <div class="media">
			        <img class="img-radius" :src="'https://ui-avatars.com/api/'+notification.comment.user.name">
			        <div class="media-body">
			            <p>
			                <strong>{{notification.comment.user.name}}</strong>
			                <span class="n-time text-muted">
			                    <i class="far fa-clock m-r-10"></i>
			                    {{
			                    	$commonFunction.timeFromNow(notification.created_at)
			                    }}
			                </span>
			            </p>
			            <p v-html="__('messages.other_agents_replied_to_ticket_notif',{name: notification.comment.user.name, ticket_ref: notification.comment.ticket.ticket_ref})"></p>
			        </div>
			    </div>
			</inertia-link>
		</li>
	</div>
</template>
<script>
	export default {
		props: ['notification']
	}
</script>