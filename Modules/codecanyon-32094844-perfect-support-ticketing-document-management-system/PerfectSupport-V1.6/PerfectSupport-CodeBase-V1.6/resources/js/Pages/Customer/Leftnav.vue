<template>
	<div class="navbar-content scroll-div">
        <ul class="nav pcoded-inner-navbar">
            <li class="nav-item pcoded-menu-caption">
                <label>{{__('messages.navigation')}}</label>
            </li>
            
            <li class="nav-item">
                <inertia-link :href="route_ziggy('customer.tickets.index')" class="nav-link">
                    <span class="pcoded-micon" method="post">
                        <i class="fas fa-ticket-alt"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.tickets')}}
                    </span>
                </inertia-link>
            </li>
            
            <li class="nav-item" v-if="$page.is_public_ticket_enabled">
                <inertia-link :href="route_ziggy('customer.public-tickets')" class="nav-link" :data="getPublicTicketFilterParams()">
                    <span class="pcoded-micon" method="post">
                        <i class="fas fa-ticket-alt"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.public_tickets')}}
                    </span>
                </inertia-link>
            </li>
            
            <li class="nav-item">
                <a :href="route_ziggy('documentation-index')" class="nav-link" target="_blank">
                    <span class="pcoded-micon" method="get">
                        <i class="fab fa-autoprefixer"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.documentations')}}
                    </span>
                </a>
            </li>
        </ul>
    </div>
</template>
<script>
    export default {
        methods: {
            getPublicTicketFilterParams() {
                if (!_.isNull(sessionStorage.getItem('pTticketFilterParams'))) {
                    var ticketFilterParams =  JSON.parse(sessionStorage.getItem('pTticketFilterParams'));
                    return ticketFilterParams;
                }
                return null;
            }
        }
    }
</script>