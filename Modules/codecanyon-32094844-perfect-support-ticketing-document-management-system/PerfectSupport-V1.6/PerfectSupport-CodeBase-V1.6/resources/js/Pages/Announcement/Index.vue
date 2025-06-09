<template>
	<layout :title="__('messages.announcements')">
        <template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
					<div class="page-header-title">
                        <h3 class="m-b-10">
                            {{__('messages.announcements')}}
                        </h3>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                {{__('messages.all_announcements')}}
                            </h5>
                            <inertia-link :href="route_ziggy('announcements.create')" class="btn float-right btn-sm btn-info text-white">
                                <i class="fas fa-plus"></i>
                                {{__('messages.new_announcement')}}
                            </inertia-link>
                        </div>
                        <div class="card-block pb-0">
                            <data-table :columns="columns" :url="route_ziggy('announcements-data-table')" ref="announcementsDataTable" order-by="start_datetime" order-dir="desc">
                            </data-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </layout>
</template>
<script>
	import Layout from '@/Shared/Layout';
    import Leftnav from '@/Pages/Elements/Leftnav';
    import DeleteButton from '@/Shared/DeleteButton';
    import Translate from '@/Shared/Translate';
    import RenderHtml from '@/Shared/RenderHtmlComponent';
    export default {
		components: {
			Layout,
            Leftnav,
            DeleteButton,
            Translate
		},
        data() {
            const self = this;
            return {
                columns: [
                    {
                        label: self.__('messages.action'),
                        name: self.__('messages.action'),
                        orderable: false,
                        classes: { 
                            'btn btn-icon' : true,
                            'btn-rounded btn-sm' : true,
                            'btn-danger' : true
                        },
                        event: "click",
                        handler: self.deleteAnnouncement,
                        width:1,
                        component: DeleteButton
                    },
                    {
                        label: self.__('messages.start_datetime'),
                        name: 'start_datetime',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.end_datetime'),
                        name: 'end_datetime',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.role'),
                        name: 'role',
                        orderable: true,
                        component: Translate,
                    },
                    {
                        label: self.__('messages.product'),
                        name: 'product',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.announcement'),
                        name: 'body',
                        orderable: true,
                        component: RenderHtml
                    }
                ]
            }
        },
        methods: {
            deleteAnnouncement(id) {
                const self = this;
                if (confirm(self.__('messages.are_you_sure'))) {
                    axios.delete(self.route_ziggy('announcements.destroy', [id]).url())
                    .then(function (response) {
                        if (response.data.success) {
                            toastr.success(response.data.msg);
                            self.$refs.announcementsDataTable.getData();
                        } else {
                            toastr.error(response.data.msg);
                        }
                    })
                    .catch(function (error) {
                        console.log(error);
                    })
                    .then(function () {
                        // always executed
                    });
                }
            }
        }
	}
</script>