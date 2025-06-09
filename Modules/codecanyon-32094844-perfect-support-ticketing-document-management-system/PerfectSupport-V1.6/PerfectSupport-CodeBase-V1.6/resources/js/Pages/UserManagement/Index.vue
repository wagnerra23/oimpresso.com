<template>
	<layout :title="__('messages.user_management')">
        <template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
                    <div class="page-header-title">
                        <h3 class="m-b-10">
                            {{__('messages.users')}}
                        </h3>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                {{__('messages.all_users')}}
                            </h5>
                            <a :href="route_ziggy('export.users')" class="btn float-right btn-sm btn-outline-primary">
                                <i class="far fa-file-excel"></i>
                                {{__('messages.export_to_excel')}}
                            </a>
                            <inertia-link :href="route_ziggy('user-management.create')" class="btn float-right btn-sm btn-info text-white">
                                <i class="fas fa-plus"></i>
                                {{__('messages.new_user')}}
                            </inertia-link>
                        </div>
                        <div class="card-block pb-0">
                            <data-table :columns="columns" :url="route_ziggy('users-data-table')" ref="usersDataTable">
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
    import Pagination from '@/Shared/Pagination';
    import Translate from '@/Shared/Translate';
    import FormatDate from '@/Shared/FormatDate';
    import DeleteButton from '@/Shared/DeleteButton';
    import EditRedirectButton from '@/Shared/EditRedirectButton';
	export default {
		components: {
			Layout,
            Leftnav,
            Pagination,
            Translate,
            FormatDate,
            DeleteButton,
            EditRedirectButton
		},
        data() {
            const self = this;
            return {
                columns: [
                    {
                        label: self.__('messages.name'),
                        name: 'name',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.email'),
                        name: 'email',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.role'),
                        name: 'role',
                        orderable: true,
                        component: Translate,
                    },
                    {
                        label: self.__('messages.all_purchases'),
                        name: 'all_purchases',
                        orderable: false,
                    },
                    {
                        label: self.__('messages.not_purchases'),
                        name: 'not_purchases',
                        orderable: false,
                    },
                    {
                        label: self.__('messages.added_at'),
                        name: 'created_at',
                        orderable: true,
                        component: FormatDate,
                    },
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
                        handler: self.deleteUser,
                        width:1,
                        component: DeleteButton
                    },
                    {
                        label: '',
                        name: self.__('messages.edit'),
                        orderable: false,
                        classes: { 
                            'btn btn-icon' : true,
                            'btn-rounded btn-sm' : true,
                            'btn-primary' : true
                        },
                        width:1,
                        meta:{
                            url: 'user-management.edit',
                            icon: 'far fa-edit'
                        },
                        component: EditRedirectButton
                    },
                ]
            }
        },
		methods:{
            deleteUser(id) {
                const self = this;
                if (confirm(self.__('messages.are_you_sure'))) {
                    axios.delete(self.route_ziggy('user-management.destroy', [id]).url())
                    .then(function (response) {
                        if (response.data.success) {
                            toastr.success(response.data.msg);
                            self.$refs.usersDataTable.getData();
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