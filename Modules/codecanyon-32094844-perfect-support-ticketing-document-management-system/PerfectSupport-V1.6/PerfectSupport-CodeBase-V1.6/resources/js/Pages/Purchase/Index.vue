<template>
	<layout :title="__('messages.purchases')">
        <template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
					<div class="page-header-title">
                        <h3 class="m-b-10">
                            {{__('messages.purchases')}}
                        </h3>
                    </div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div class="card code-table">
                        <div class="card-header">
                            <h5>
                                {{__('messages.all_purchases')}}
                            </h5>
                            <a :href="route_ziggy('customer.export.purchases')" class="btn float-right btn-sm btn-outline-primary">
                                <i class="far fa-file-excel"></i>
                                {{__('messages.export_to_excel')}}
                            </a>
                            <inertia-link :href="route_ziggy('create.purchase')" class="btn float-right btn-sm btn-info text-white">
                                <i class="fas fa-plus"></i>
                                {{__('messages.add_purchase')}}
                            </inertia-link>
                        </div>
                        <div class="card-block pb-0">
                            <data-table :columns="columns" :url="route_ziggy('purchase-data-table')" ref="purchaseDataTable">
                            </data-table>
                        </div>
                    </div>
                </div>
			</div>
		</div>
	</layout>
</template>
</template>
<script>
	import Layout from '@/Shared/Layout';
    import Leftnav from '@/Pages/Elements/Leftnav';
    import Pagination from '@/Shared/Pagination';
    import DeleteButton from '@/Shared/DeleteButton';
    import EditRedirectButton from '@/Shared/EditRedirectButton';
	export default {
		components: {
			Layout,
            Leftnav,
            Pagination
		},
		data: function () {
			const self = this;
			return {
				columns: [
                    {
                        label: self.__('messages.action'),
                        name: self.__('messages.edit'),
                        orderable: false,
                        classes: { 
                            'btn btn-icon' : true,
                            'btn-rounded btn-sm' : true,
                            'btn-primary' : true
                        },
                        width:1,
                        meta:{
                            url: 'edit.purchase',
                            icon: 'far fa-edit'
                        },
                        component: EditRedirectButton
                    },
                    {
                        label: '',
                        name: self.__('messages.action'),
                        orderable: false,
                        classes: { 
                            'btn btn-icon' : true,
                            'btn-rounded btn-sm' : true,
                            'btn-danger' : true
                        },
                        event: "click",
                        handler: self.deletePurchase,
                        width:1,
                        component: DeleteButton
                    },
                    {
                        label: self.__('messages.name'),
                        name: 'name',
                        columnName: 'users.name',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.email'),
                        name: 'email',
                        columnName: 'users.email',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.product'),
                        name: 'product',
                        columnName: 'products.name',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.license_key'),
                        name: 'product_license_key',
                        columnName: 'license_key',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.purchased_on'),
                        name: 'purchased_on',
                        orderable: true,
                    },
                    {
                        label: self.__('messages.source'),
                        columnName: 'sources.name',
                        name: 'source',
                        orderable: true,
                    }
                ]
			}
		},
        methods:{
            deletePurchase(id) {
                const self = this;
                if (confirm(self.__('messages.are_you_sure'))) {
                    axios.delete(self.route_ziggy('delete.purchase', [id]).url())
                    .then(function (response) {
                        if (response.data.success) {
                            toastr.success(response.data.msg);
                            self.$refs.purchaseDataTable.getData();
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