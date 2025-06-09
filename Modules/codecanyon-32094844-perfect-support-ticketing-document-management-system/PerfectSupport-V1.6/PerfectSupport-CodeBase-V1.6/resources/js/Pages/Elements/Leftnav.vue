<template>
	<div class="navbar-content scroll-div">
        <ul class="nav pcoded-inner-navbar">
            <li class="nav-item pcoded-menu-caption">
                <label>{{__('messages.navigation')}}</label>
            </li>

            <li class="nav-item">
                <inertia-link :href="route_ziggy('home')" class="nav-link">
                    <span class="pcoded-micon" method="post">
                        <i class="fas fa-home"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.home')}}
                    </span>
                </inertia-link>
            </li>

            <li class="nav-item">
                <inertia-link :href="route_ziggy('tickets.index')" class="nav-link" :data="getTicketFilterParams()">
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
            <li class="nav-item pcoded-hasmenu"
                v-if="_.includes(['admin'], $page.auth.user.role)">
                <a href="#!" class="nav-link">
                    <span class="pcoded-micon">
                        <i class="fas fa-file-alt"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.reports')}}
                    </span>
                </a>
                <ul class="pcoded-submenu">
                    <li>
                        <inertia-link :href="route_ziggy('reports.comments')"
                            :data="getCommentsFilterParams()">
                            {{__('messages.comments')}}
                        </inertia-link>
                    </li>
                    <li>
                        <inertia-link :href="route_ziggy('reports.completed.tickets')"
                            :data="getCompletedTicketFilterParams()">
                            {{__('messages.completed')}}
                        </inertia-link>
                    </li>
                </ul>
            </li>
            <li class="nav-item" v-if="_.includes(['admin'], $page.auth.user.role)">
                <inertia-link :href="route_ziggy('canned-responses.index')" class="nav-link">
                    <span class="pcoded-micon" method="post">
                        <i class="fas fa-reply-all"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.canned_response')}}
                    </span>
                </inertia-link>
            </li>

            <li class="nav-item">
                <inertia-link :href="route_ziggy('get.validate.license')" class="nav-link">
                    <span class="pcoded-micon" method="get">
                        <i class="far fa-check-circle"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.license_checker')}}
                    </span>
                </inertia-link>
            </li>

            <li class="nav-item" v-if="_.includes(['admin'], $page.auth.user.role)">
                <inertia-link :href="route_ziggy('users-purchase-list')" class="nav-link">
                    <span class="pcoded-micon" method="post">
                        <i class="fas fa-store"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.purchases')}}
                    </span>
                </inertia-link>
            </li>
            
            <li class="nav-item" v-if="_.includes(['admin'], $page.auth.user.role)">
                <inertia-link :href="route_ziggy('announcements.index')" class="nav-link">
                    <span class="pcoded-micon" method="get">
                        <i class="fas fa-bullhorn"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.announcements')}}
                    </span>
                </inertia-link>
            </li>
            
            <li class="nav-item">
                <inertia-link :href="route_ziggy('documentation.index')" class="nav-link">
                    <span class="pcoded-micon" method="get">
                        <i class="fab fa-autoprefixer"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.documentations')}}
                    </span>
                </inertia-link>
            </li>

            <li class="nav-item" v-if="_.includes(['admin'], $page.auth.user.role)">
                <inertia-link :href="route_ziggy('user-management.index')" class="nav-link">
                    <span class="pcoded-micon" method="get">
                        <i class="fas fa-users-cog"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.user_management')}}
                    </span>
                </inertia-link>
            </li>
            
            <li class="nav-item" v-if="_.includes(['admin'], $page.auth.user.role)">
                <inertia-link :href="route_ziggy('backups.index')" class="nav-link">
                    <span class="pcoded-micon" method="get">
                        <i class="fa fas fa-hdd"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.backups')}}
                    </span>
                </inertia-link>
            </li>
            <li class="nav-item pcoded-hasmenu" v-if="_.includes(['admin'], $page.auth.user.role)">
                <a href="#!" class="nav-link">
                    <span class="pcoded-micon">
                        <i class="fas fa-cogs"></i>
                    </span>
                    <span class="pcoded-mtext">
                        {{__('messages.settings')}}
                    </span>
                </a>
                <ul class="pcoded-submenu">
                    <li>
                        <inertia-link :href="route_ziggy('settings.index')">
                            {{__('messages.settings')}}
                        </inertia-link>
                    </li>
                    <li>
                        <inertia-link :href="route_ziggy('sources.index')">
                            {{__('messages.sources')}}
                        </inertia-link>
                    </li>
                    <li>
                        <inertia-link :href="route_ziggy('products.index')">
                            {{__('messages.products')}}
                        </inertia-link>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</template>
<script>
    export default {
        methods: {
            getTicketFilterParams() {
                if (!_.isNull(sessionStorage.getItem('ticketFilterParams'))) {
                    var ticketFilterParams =  JSON.parse(sessionStorage.getItem('ticketFilterParams'));
                    return ticketFilterParams;
                }
                return null;
            },
            getPublicTicketFilterParams() {
                if (!_.isNull(sessionStorage.getItem('pTticketFilterParams'))) {
                    var ticketFilterParams =  JSON.parse(sessionStorage.getItem('pTticketFilterParams'));
                    return ticketFilterParams;
                }
                return null;
            },
            getCompletedTicketFilterParams() {
                if (!_.isNull(sessionStorage.getItem('closedTicketFilterParams'))) {
                    var ticketFilterParams =  JSON.parse(sessionStorage.getItem('closedTicketFilterParams'));
                    return ticketFilterParams;
                }
                return null;
            },
            getCommentsFilterParams() {
                if (!_.isNull(sessionStorage.getItem('commentsFilterParams'))) {
                    var commentsFilterParams =  JSON.parse(sessionStorage.getItem('commentsFilterParams'));
                    return commentsFilterParams;
                }
                return null;
            }
        }
    }
</script>