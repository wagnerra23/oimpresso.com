<template>
	<div class="dropdown">
        <a class="dropdown-toggle" href="#" data-toggle="dropdown" @click="readNotifications" id="notification-dropdown-div">
            <i class="far fa-bell fa-lg"></i>
            <span class="badge badge-info" v-if="notifications_count">
            	{{notifications_count}}
            </span>
        </a>
        <div class="dropdown-menu dropdown-menu-right notification" id="notification-dropdown-menu">
            <div class="noti-head">
                <h6 class="d-inline-block m-b-0">
                    {{__('messages.notifications')}}
                </h6>
            </div>
            <!-- notification body -->
            <ul class="noti-body scroll-notification" v-if="!_.isEmpty(notifications)">
            	<li class="n-title" v-if="isNewNotificationAvailable(notifications)">
					<p class="m-b-0">
						{{__('messages.new')}}
					</p>
				</li>
            	<template v-for="notification in notifications">
            		<template v-if="_.isNull(notification.read_at)">
						<NotificationBody :notification="notification">
						</NotificationBody>
            		</template>
                </template>
                <li class="n-title" v-if="isOldNotificationAvailable(notifications)">
					<p class="m-b-0">
						{{__('messages.earlier')}}
					</p>
				</li>
            	<template v-for="notification in notifications">
            		<template v-if="!_.isNull(notification.read_at)">
						<NotificationBody :notification="notification">
						</NotificationBody>
            		</template>
                </template>
            </ul>
            <ul class="noti-body" v-if="_.isEmpty(notifications)">
            	<li class="notification">
            		<div class="media">
            			<div class="media-body">
	            			<p>
	            				{{__('messages.no_notification_found')}}
	            			</p>
	            		</div>
            		</div>
            	</li>
            </ul>
            <div class="noti-footer" v-if="!_.isNull(url)">
                <a href="#" @click="readNotifications">
                	{{__('messages.load_more')}}
                </a>
            </div>
            <!-- /notification body-footer -->
        </div>
    </div>
</template>
<script>
	import NotificationBody from '@/Shared/NotificationBody';
	export default {
		components: {
			NotificationBody
		},
		data() {
			return {
            	notifications_count: null,
            	notifications: [],
            	url: null
        	}
    	},
    	created() {
    		const self = this;
    		self.getNotificationsCount();
    		setInterval(() => {
	            self.getNotificationsCount();
	        }, APP.NOTIFICATION_REFRESH_TIME);
    	},
		methods: {
			getNotificationsCount() {
				const self = this;
				axios.get(this.route_ziggy('notifications.index'))
				.then(function (response) {
					self.notifications_count = response.data;
				})
				.catch(function (error) {
				    console.log(error);
				})
				.then(function () {
				    // always executed
				});
			},
			readNotifications() {
				const self = this;
				if (_.isNull(self.url)) {
	                self.url = self.route_ziggy('read-notifications');
	                self.notifications = [];
	            }
				axios.get(self.url)
				.then(function (response) {
					self.notifications = _.concat(self.notifications, response.data.data);
                    self.url = _.get(response, 'data.next_page_url', null);
                    $("#notification-dropdown-div").dropdown('show');
                    self.getNotificationsCount();
				})
				.catch(function (error) {
				    console.log(error);
				})
				.then(function () {
				    // always executed
				});
			},
			isNewNotificationAvailable(notifications) {
				return notifications.some(notification => _.isNull(notification.read_at));
			},
			isOldNotificationAvailable(notifications) {
				return notifications.some(notification => !_.isNull(notification.read_at));
			}
		}
	}
</script>
<style scoped>
	.scroll-notification {
		max-height: 350px;
    	overflow-y: scroll;
	}
</style>