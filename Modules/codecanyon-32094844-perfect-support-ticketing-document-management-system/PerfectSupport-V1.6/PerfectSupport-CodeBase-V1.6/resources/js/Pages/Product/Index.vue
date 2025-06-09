<template>
	<layout :title="__('messages.products')">
        <template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
					<div class="page-header-title">
                        <h3 class="m-b-10">
                            {{__('messages.products')}}
                        </h3>
                    </div>
                    <!-- Add Product Modal -->
                    <div class="modal fade" id="addProduct">
                        <form v-on:submit.prevent="submitForm">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">
                                            {{__('messages.add_product')}}
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="pname">
                                                    {{__('messages.name')}}*
                                                </label>
                                                <input type="text" class="form-control" id="pname" :placeholder="__('messages.name')"
                                                    v-model="new_product.name" required
                                                >
                                            </div>

                                            <div class="form-group">
                                                <label for="description">
                                                    {{__('messages.description')}}
                                                </label>
                                                <textarea class="form-control" id="description" rows="3" v-model="new_product.description" :placeholder="__('messages.description')"></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label for="sources">
                                                    {{__('messages.sources')}}
                                                </label>
                                                <select class="form-control" id="sources" multiple v-model="new_product.sources">
                                                    <option v-for="source in sources" 
                                                        :value="source.id" v-text="source.name"></option>
                                                </select>
                                            </div>

                                            <div class="form-group" v-if="_.indexOf(new_product.sources, 1) != -1">
                                                <label for="sources">
                                                    Envato Product*
                                                </label>
                                                <select class="form-control" id="envato_product_id" v-model="new_product.envato_product_id" required>
                                                    <option v-for="(envato_product, index) in envato_products" 
                                                        :value="index" 
                                                        v-text="envato_product"></option>
                                                </select>
                                            </div>

                                            <div class="form-group" v-if="_.indexOf(new_product.sources, 2) != -1">
                                                <label for="woolicense_product_id">
                                                    {{__('messages.Woocommerce_licensing')}} Product Unique Id*
                                                </label>
                                                <input type="text" class="form-control" id="woolicense_product_id" v-model="new_product.woolicense_product_id" required
                                                >
                                            </div>

                                            <div class="form-group">
                                                <label for="support_agents">
                                                   {{__('messages.support_agents')}}*
                                                </label>
                                                <select class="form-control" id="support_agents" multiple v-model="new_product.support_agents" required>
                                                    <option v-for="(name, key) in agents" 
                                                        :value="key" v-text="name"></option>
                                                </select>
                                            </div>

                                            <!-- add department -->
                                            <div class="row mb-3">
                                                <div class="col-md-2 mt-auto mb-auto">
                                                    <h5>{{__('messages.departments')}}</h5>
                                                </div>
                                                <div class="col-md-10 pl-0">
                                                    <button type="button" class="btn btn-secondary btn-sm"
                                                        @click="addDepartmentForNewProduct()">
                                                        {{__('messages.add_department')}}
                                                    </button>
                                                </div>
                                                <div class="col-md-12">
                                                    <select class="form-control departments_dropdown" multiple
                                                        v-model="choosen_departments">
                                                        <option v-for="(department, key) in departments"
                                                            :value="department.id"
                                                            v-text="department.name">
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row mb-3"
                                                v-if="isAddingDepartmentForNewProduct">
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" id="department"
                                                        :placeholder="__('messages.department')"
                                                    v-model="new_department" required>
                                                    <small class="form-text text-danger"
                                                        v-if="error_msg" v-text="error_msg"></small>
                                                </div>
                                                <div class="col-md-1 mt-auto mb-auto">
                                                    <button type="button" class="btn btn-primary btn-sm"
                                                        @click="saveDepartmentForNewProduct()">
                                                        {{__('messages.save')}}
                                                    </button>
                                                </div>
                                                <div class="col-md-2 mt-auto mb-auto">
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        @click="closeAddDepartmentForNewProduct()">
                                                        {{__('messages.close')}}
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <div class="accordion" id="addProductAccordion">
                                                        <div class="card mb-3"
                                                            v-for="(department, key) in new_product.departments">
                                                            <div class="card-header" :id="`addProductAccordionHeading_${department.unique_id}`">
                                                                <h5 class="mb-0">
                                                                    <a href="#!" data-toggle="collapse" :data-target="`#addProductAccordionCollapse_${department.unique_id}`" aria-expanded="false" :aria-controls="`addProductAccordionCollapse_${department.unique_id}`" class="collapsed">
                                                                        {{department.name}}
                                                                    </a>
                                                                </h5>
                                                                <i class="far fa-trash-alt text-danger cursor-pointer float-right fa-lg"
                                                                    :title="__('messages.remove')"
                                                                    @click="removeDepartmentFromNewProduct(key)"></i>
                                                            </div>
                                                            <div :id="`addProductAccordionCollapse_${department.unique_id}`" class="card-body collapse" :aria-labelledby="`addProductAccordionHeading_${department.unique_id}`" data-parent="#addProductAccordion">
                                                                <div class="col-sm-12 mb-1">
                                                                    <div class="checkbox checkbox-fill d-inline">
                                                                        <input class="form-check-input" type="checkbox" v-model="department.show_related_public_ticket" :id="`rt_${department.unique_id}`">
                                                                        <label class="cr" :for="`rt_${department.unique_id}`">
                                                                            {{__('messages.show_related_public_ticket')}}
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-12 mb-1">
                                                                    <div class="form-group">
                                                                        <label :for="`pd_agent_${department.unique_id}`">
                                                                           {{__('messages.support_agents')}}
                                                                        </label>
                                                                        <select class="form-control" :id="`pd_agent_${department.unique_id}`" multiple v-model="department.agents"
                                                                        :data-uid="department.unique_id">
                                                                            <option v-for="(name, key) in agents" 
                                                                                :value="key" v-text="name"></option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label :for="`info_${department.unique_id}`">
                                                                           {{__('messages.info_instruction')}}
                                                                        </label>
                                                                        <textarea  class="form-control" rows="3"
                                                                            :id="`info_${department.unique_id}`"
                                                                            v-model="department.information"
                                                                            :data-uid="department.unique_id"></textarea>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- /add department -->
                                            <div class="checkbox checkbox-fill d-inline">
                                                <input type="checkbox" class="form-check-input" id="is_active" v-model="new_product.is_active">
                                                <label class="cr" for="is_active">
                                                    {{__('messages.active')}}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                            {{__('messages.close')}}
                                        </button>
                                        <loading-button :loading="submitting" class="btn btn-primary" type="submit">
                                            {{__('messages.submit')}}
                                        </loading-button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- / Add Product Modal-->
                    <!-- Edit Product Modal -->
                    <div class="modal fade" id="editProduct">
                        <form v-on:submit.prevent="updateProduct">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">
                                            {{__('messages.edit_product')}}
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="pname">
                                                    {{__('messages.name')}}*
                                                </label>
                                                <input type="text" class="form-control" id="pname" :placeholder="__('messages.name')"
                                                    v-model="edit_product.name" required
                                                >
                                            </div>

                                            <div class="form-group">
                                                <label for="description">
                                                    {{__('messages.description')}}
                                                </label>
                                                <textarea class="form-control" id="description" rows="3" v-model="edit_product.description" :placeholder="__('messages.description')"></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label for="sources">
                                                    {{__('messages.sources')}}
                                                </label>
                                                <select class="form-control" id="sources" multiple v-model="edit_product.sources">
                                                    <option v-for="source in sources" 
                                                        :value="source.id" v-text="source.name"></option>
                                                </select>
                                            </div>

                                            <div class="form-group" v-if="_.indexOf(edit_product.sources, 1) != -1">
                                                <label for="sources">
                                                    Envato Product*
                                                </label>
                                                <select class="form-control" id="envato_product_id" v-model="edit_product.envato_product_id" required>
                                                    <option v-for="(envato_product, index) in envato_products" 
                                                        :value="index" 
                                                        v-text="envato_product"></option>
                                                </select>
                                            </div>

                                            <div class="form-group" v-if="_.indexOf(edit_product.sources, 2) != -1">
                                                <label for="woolicense_product_id">
                                                    {{__('messages.Woocommerce_licensing')}} Unique Id*
                                                </label>
                                                <input type="text" class="form-control" id="woolicense_product_id" v-model="edit_product.woolicense_product_id" required
                                                >
                                            </div>

                                            <div class="form-group">
                                                <label for="support_agents">
                                                   {{__('messages.support_agents')}}*
                                                </label>
                                                <select class="form-control" id="support_agents" multiple v-model="edit_product.support_agents" required>
                                                    <option v-for="(name, key) in agents" 
                                                        :value="key" v-text="name"></option>
                                                </select>
                                            </div>
                                            <!-- edit department -->
                                            <div class="row mb-3">
                                                <div class="col-md-2 mt-auto mb-auto">
                                                    <h5>{{__('messages.departments')}}</h5>
                                                </div>
                                                <div class="col-md-10 pl-0">
                                                    <button type="button" class="btn btn-secondary btn-sm"
                                                        @click="addDepartmentForProduct()">
                                                        {{__('messages.add_department')}}
                                                    </button>
                                                </div>
                                                <div class="col-md-12">
                                                    <select class="form-control edit_departments_dropdown" multiple
                                                        v-model="choosen_departments">
                                                        <option v-for="(department, key) in departments"
                                                            :value="department.id"
                                                            v-text="department.name">
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row mb-3"
                                                v-if="isAddingDepartmentForProduct">
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" id="department"
                                                        :placeholder="__('messages.department')"
                                                    v-model="new_department" required>
                                                    <small class="form-text text-danger"
                                                        v-if="error_msg" v-text="error_msg"></small>
                                                </div>
                                                <div class="col-md-1 mt-auto mb-auto">
                                                    <button type="button" class="btn btn-primary btn-sm"
                                                        @click="saveDepartmentForProduct()">
                                                        {{__('messages.save')}}
                                                    </button>
                                                </div>
                                                <div class="col-md-2 mt-auto mb-auto">
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        @click="closeAddDepartmentForProduct()">
                                                        {{__('messages.close')}}
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <div class="accordion" id="editProductAccordion">
                                                        <div class="card mb-3"
                                                            v-for="(department, key) in edit_product.departments">
                                                            <div class="card-header" :id="`editProductAccordionHeading_${department.unique_id}`">
                                                                <h5 class="mb-0">
                                                                    <a href="#!" data-toggle="collapse" :data-target="`#editProductAccordionCollapse_${department.unique_id}`" aria-expanded="false" :aria-controls="`editProductAccordionCollapse_${department.unique_id}`" class="collapsed">
                                                                        {{department.name}}
                                                                    </a>
                                                                </h5>
                                                                <i class="far fa-trash-alt text-danger cursor-pointer float-right fa-lg"
                                                                    :title="__('messages.remove')"
                                                                    @click="removeExistingDepartmentFromEditProduct(key)"></i>
                                                            </div>
                                                            <div :id="`editProductAccordionCollapse_${department.unique_id}`" class="card-body collapse" :aria-labelledby="`editProductAccordionHeading_${department.unique_id}`" data-parent="#editProductAccordion">
                                                                <div class="col-sm-12 mb-1">
                                                                    <div class="checkbox checkbox-fill d-inline">
                                                                        <input class="form-check-input" type="checkbox" v-model="department.show_related_public_ticket" :id="`rt_${department.unique_id}`">
                                                                        <label class="cr" :for="`rt_${department.unique_id}`">
                                                                            {{__('messages.show_related_public_ticket')}}
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-12 mb-1">
                                                                    <div class="form-group">
                                                                        <label :for="`pde_agent_${department.unique_id}`">
                                                                           {{__('messages.support_agents')}}
                                                                        </label>
                                                                        <select class="form-control" :id="`pde_agent_${department.unique_id}`" multiple v-model="department.agents"
                                                                        :data-uid="department.unique_id">
                                                                            <option v-for="(name, key) in agents" 
                                                                                :value="key" v-text="name"></option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label :for="`info_${department.unique_id}`">
                                                                           {{__('messages.info_instruction')}}
                                                                        </label>
                                                                        <textarea  class="form-control" rows="3"
                                                                            :id="`info_${department.unique_id}`"
                                                                            v-model="department.information"
                                                                            :data-uid="department.unique_id"></textarea>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- new department -->
                                                        <div class="card mb-3"
                                                            v-for="(department, key) in edit_product.new_departments">
                                                            <div class="card-header" :id="`editProductAccordionHeading_${department.unique_id}`">
                                                                <h5 class="mb-0">
                                                                    <a href="#!" data-toggle="collapse" :data-target="`#editProductAccordionCollapse_${department.unique_id}`" aria-expanded="false" :aria-controls="`editProductAccordionCollapse_${department.unique_id}`" class="collapsed">
                                                                        {{department.name}}
                                                                    </a>
                                                                </h5>
                                                                <i class="far fa-trash-alt text-danger cursor-pointer float-right fa-lg"
                                                                    :title="__('messages.remove')"
                                                                    @click="removeNewDepartmentFromEditProduct(key)"></i>
                                                            </div>
                                                            <div :id="`editProductAccordionCollapse_${department.unique_id}`" class="card-body collapse" :aria-labelledby="`editProductAccordionHeading_${department.unique_id}`" data-parent="#editProductAccordion">
                                                                <div class="col-sm-12 mb-1">
                                                                    <div class="checkbox checkbox-fill d-inline">
                                                                        <input class="form-check-input" type="checkbox" v-model="department.show_related_public_ticket" :id="`rt_${department.unique_id}`">
                                                                        <label class="cr" :for="`rt_${department.unique_id}`">
                                                                            {{__('messages.show_related_public_ticket')}}
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-12 mb-1">
                                                                    <div class="form-group">
                                                                        <label :for="`new_pde_agent_${department.unique_id}`">
                                                                           {{__('messages.support_agents')}}
                                                                        </label>
                                                                        <select class="form-control" :id="`new_pde_agent_${department.unique_id}`" multiple v-model="department.agents"
                                                                        :data-uid="department.unique_id">
                                                                            <option v-for="(name, key) in agents" 
                                                                                :value="key" v-text="name"></option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label :for="`info_${department.unique_id}`">
                                                                           {{__('messages.info_instruction')}}
                                                                        </label>
                                                                        <textarea  class="form-control" rows="3"
                                                                            :id="`info_${department.unique_id}`"
                                                                            v-model="department.information"
                                                                            :data-uid="department.unique_id"></textarea>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- /new department -->
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- /edit department -->
                                            <div class="checkbox checkbox-fill d-inline">
                                                <input type="checkbox" class="form-check-input" id="is_Woocommerce_licensing_active" v-model="edit_product.is_active">
                                                <label class="cr" for="is_Woocommerce_licensing_active">
                                                    {{__('messages.active')}}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                            {{__('messages.close')}}
                                        </button>
                                        <loading-button :loading="submitting" class="btn btn-primary" type="submit">
                                            {{__('messages.update')}}
                                        </loading-button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- /Edit Product Modal -->
                    <div class="card code-table">
                        <div class="card-header">
                            <h5>
                                {{__('messages.all_products')}}
                            </h5>
                            <button type="button" class="btn btn-primary btn-sm float-right"
                                @click="AddProduct()">
                                <i class="fas fa-plus"></i>
                                {{__('messages.add_product')}}
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-1">
                                    <i class="fas fa-ellipsis-v"></i>
                                </div>
                                <div class="col-sm-2">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.name')}}
                                    </span>
                                </div>
                                <div class="col-sm-2">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.description')}}
                                    </span>
                                </div>
                                <div class="col-sm-2">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.sources')}}
                                    </span>
                                </div>
                                <div class="col-sm">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.departments')}}
                                    </span>
                                </div>
                                <div class="col-sm-2">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.support_agents')}}
                                    </span>
                                </div>
                                <div class="col-sm">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.added_at')}}
                                    </span>
                                </div>
                            </div>
                            <hr>
                            <template v-if="products.data.length" v-for="product in products.data">
                                <div class="row">
                                    <div class="col-sm-1">
                                        <span class="cursor-pointer" @click="editProduct(product.id)">
                                            <i class="far fa-edit text-info"></i>
                                        </span>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="m--font-bolder d-flex align-items-center">
                                            <div class="no-wrap d-inline-block max-width-250" :title="product.name">
                                                {{product.name}}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="m--font-bolder d-flex align-items-center">
                                            <div class="no-wrap d-inline-block max-width-250" :title="product.description">
                                                {{product.description}}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        {{listSources(product.sources)}}
                                    </div>
                                    <div class="col-sm">
                                        {{listDepartments(product.product_departments)}}
                                    </div>
                                    <div class="col-sm-2">
                                        {{listSupportAgents(product.support_agents)}}
                                    </div>
                                    <div class="col-sm">
                                        {{$commonFunction.formatDate(product.created_at)}}
                                    </div>
                                </div>
                                <hr>
                            </template>
                            <div class="row" v-if="products.data.length === 0">
                                <div class="col-sm">
                                    <div class="alert alert-info" role="alert">
                                        <h4 class="text-muted">
                                            {{__('messages.no_data_found')}}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <Pagination :pagination="products" :url="route_ziggy('products.index')">
                            </Pagination>
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
    import LoadingButton from '@/Shared/LoadingButton';
	export default {
		components: {
			Layout,
            Leftnav,
            Pagination,
            LoadingButton
		},
		props: {
    		products: Object,
            sources: Array,
            agents: Object
  		},
  		data: function () { 
		    return {
                new_product: {},
                submitting: false,
                envato_products: {},
                edit_product: {
                    'id':null,
                    'name': '',
                    'description': '',
                    'sources': [],
                    'envato_product_id': null,
                    'woolicense_product_id': null,
                    'support_agents': [],
                    'is_active': true,
                    'departments':[],
                    'new_departments':[]
                },
                isAddingDepartmentForNewProduct: false,
                new_department: '',
                isAddingDepartmentForProduct: false,
                error_msg:'',
                departments:[],
                choosen_departments: []
		    }
  		},
        beforeMount(){
            self = this;
            self.newProduct();
            self.sources.forEach(function(source) { 
                if((source.source_type == 'envato') && !_.isEmpty(source.source_other_info)){
                    self.envato_products = source.source_other_info['items'];
                }
            });
        },
		methods:{
            newProduct(){
                this.new_product = {
                    'name': '',
                    'description': '',
                    'sources': [],
                    'envato_product_id': null,
                    'woolicense_product_id': null,
                    'support_agents': [],
                    'is_active': true,
                    'departments':[]
                };
            },
            listSources(sources){
                var temp = [];
                _(sources).forEach(
                    function(source) {
                        if (!_.isEmpty(source)) {
                            temp.push(source.name);
                        }
                    }
                )

                return temp.join(', ');
            },
            listDepartments(productDepartments) {
                let temp = [];
                _(productDepartments).forEach(
                    function(product) {
                        if (!_.isEmpty(product.department)) {
                            temp.push(product.department.name);
                        }
                    }
                )
                return temp.join(', ');
            },
            listSupportAgents(supportAgents) {
                var temp = [];
                _(supportAgents).forEach(
                    function(supportAgent) {
                        temp.push(supportAgent.name);
                    }
                )

                return temp.join(', ');
            },
			submitForm(){
                self = this;
                self.submitting = true;
                self.$inertia.post(self.route_ziggy('products.store'), self.new_product)
                .then(function(){
                    self.submitting = false;
                    self.newProduct();
                    $('#addProduct').modal('hide');
                })
            },
            editProduct(id) {
                const self = this;
                self.departments = [];
                self.choosen_departments = [];
                axios.get(self.route_ziggy('products.edit', [id]).url())
                .then(function (response) {

                    self.departments = response.data.departments;
                    self.edit_product = response.data.product;
                    self.edit_product.id = response.data.product.id;
                    self.edit_product.sources = response.data.product_source;
                    self.edit_product.support_agents = response.data.agent_for_product;
                    self.edit_product.envato_product_id = response.data.envato_product_id;
                    self.edit_product.woolicense_product_id = response.data.woolicense_product_id;
                    self.edit_product.departments = response.data.product_departments;
                    self.edit_product.new_departments = [];

                    if (!_.isEmpty(tinymce.editors)) {
                        tinymce.remove();
                    }

                    $('#editProduct').modal('show');

                    $(".edit_departments_dropdown").select2({
                        dropdownParent: $("#editProduct .modal-content")
                    }).on('change', function(){
                        self.choosen_departments = $(this).val();
                        self.getNewDepartmentForEditProduct();
                    });

                    if (!_.isEmpty(self.edit_product.departments)) {
                        self.edit_product.departments.forEach(function(department) {
                            self.choosen_departments.push(department.department_id);
                            setTimeout(function(){

                                $(`#pde_agent_${department.unique_id}`).select2({
                                    dropdownParent: $("#editProduct .modal-content")
                                })
                                .on('change', function(){
                                    let unique_id = $(this).data("uid");
                                    let index = _.findIndex(self.edit_product.departments, {unique_id: unique_id});
                                    self.edit_product.departments[index].agents = $(this).val();
                                });

                                tinymce.init({
                                    selector: `textarea#info_${department.unique_id}`,
                                    setup: function (editor) {
                                        editor.on('init', function (e) {
                                            editor.setContent(department.information);
                                        });
                                    },
                                    init_instance_callback: function(editor) {
                                        editor.on('Change', function(e) {
                                            let uid = parseInt($(`#${editor.id}`).attr("data-uid"));
                                            let index = _.findIndex(self.edit_product.departments, {unique_id: uid});
                                            self.edit_product.departments[index].information = tinymce.get(editor.id).getContent();
                                        });
                                    }
                                });
                            }, 1000);
                        });
                    }
                })
                .catch(function (error) {
                    console.log(error);
                })
                .then(function () {
                    // always executed
                });
            },
            updateProduct() {
                const self = this;
                self.submitting = true;
                self.$inertia.put(self.route_ziggy('products.update', [self.edit_product.id]).url(), self.edit_product)
                .then(function(){
                    self.submitting = false;
                    $('#editProduct').modal('hide');
                })
            },
            AddProduct(){
                const self = this;

                self.departments = [];
                self.choosen_departments = [];
                self.newProduct();

                if (!_.isEmpty(tinymce.editors)) {
                    tinymce.remove();
                }
                axios.get(self.route_ziggy('products.create').url())
                .then(function (response) {
                    if (response.data.success) {

                        self.departments = response.data.departments;

                        $('#addProduct').modal('show');

                        $(".departments_dropdown").select2({
                            dropdownParent: $("#addProduct .modal-content")
                        })
                        .on('change', function(){
                            self.choosen_departments = $(this).val();
                            self.getDepartmentForProduct();
                        });
                    }
                })
                .catch(function (error) {
                    console.log(error);
                })
                .then(function () {
                    // always executed
                });
            },
            addDepartmentForNewProduct() {
                const self = this;
                self.new_department = '';
                self.isAddingDepartmentForNewProduct = !self.isAddingDepartmentForNewProduct;
            },
            saveDepartmentForNewProduct() {
                const self = this;
                self.error_msg = '';

                if (_.isEmpty(self.new_department)) {
                    self.error_msg = self.__('messages.department_is_required');
                    return false;
                }
                
                axios.post(self.route_ziggy('departments.store').url(), {
                    name: self.new_department
                })
                .then(function (response) {
                    if (response.data.success) {
                        toastr.success(response.data.msg);
                        self.departments.push(response.data.department);
                        self.closeAddDepartmentForNewProduct();
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
            },
            closeAddDepartmentForNewProduct() {
                const self = this;
                self.new_department = '';
                self.isAddingDepartmentForNewProduct = false;
            },
            getDepartmentForProduct(){
                const self = this;
                _.forEach(self.choosen_departments, function(department_id, key){
                    let department_index = _.findIndex(self.departments, {id: parseInt(department_id)});
                    let pd_index = _.findIndex(self.new_product.departments, {department_id: department_id});
                    if ((department_index != -1) && (pd_index == -1)) {
                        let unique_id = self.generateUniqueRandomNum();

                        self.new_product.departments.push({
                            'name' : self.departments[department_index].name,
                            'agents' : [],
                            'unique_id' : unique_id,
                            'department_id': department_id,
                            'information' : '',
                            'show_related_public_ticket' : false
                        });
                        setTimeout(function(){

                            $(`#pd_agent_${unique_id}`).select2({
                                dropdownParent: $('#addProduct .modal-content')
                            })
                            .on('change', function(){
                                let unique_id = $(this).data("uid");
                                let index = _.findIndex(self.new_product.departments, {unique_id: unique_id});
                                self.new_product.departments[index].agents = $(this).val();
                            });

                            tinymce.init({
                                selector: `textarea#info_${unique_id}`,
                                init_instance_callback: function(editor) {
                                    editor.on('Change', function(e) {
                                        let uid = parseInt($(`#${editor.id}`).attr("data-uid"));
                                        let index = _.findIndex(self.new_product.departments, {unique_id: uid});
                                        self.new_product.departments[index].information = tinymce.get(editor.id).getContent();
                                    });
                                }
                            });
                            
                        }, 1000);
                    }
                });
            },
            removeDepartmentFromNewProduct(index) {
                const self = this;
                if (confirm(self.__('messages.are_you_sure'))) {

                    let department_id = self.new_product.departments[index].department_id;
                    let department_index = self.choosen_departments.indexOf(department_id);

                    self.new_product.departments.splice(index, 1);
                    self.choosen_departments.splice(department_index, 1);
                }
            },
            addDepartmentForProduct(){
                const self = this;
                self.new_department = '';
                self.isAddingDepartmentForProduct = !self.isAddingDepartmentForProduct;
            },
            closeAddDepartmentForProduct() {
                const self = this;
                self.new_department = '';
                self.isAddingDepartmentForProduct = false;
            },
            saveDepartmentForProduct(){
                const self = this;
                self.error_msg = '';

                if (_.isEmpty(self.new_department)) {
                    self.error_msg = self.__('messages.department_is_required');
                    return false;
                }
                
                axios.post(self.route_ziggy('departments.store').url(), {
                    name: self.new_department
                })
                .then(function (response) {
                    if (response.data.success) {
                        toastr.success(response.data.msg);
                        self.departments.push(response.data.department);
                        self.closeAddDepartmentForProduct();
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
            },
            getNewDepartmentForEditProduct() {
                const self = this;
                _.forEach(self.choosen_departments, function(department_id, key){
                    let epd_index = _.findIndex(self.edit_product.departments, {department_id: parseInt(department_id)});
                    if (epd_index == -1) {
                        let pd_index = _.findIndex(self.edit_product.new_departments, {department_id: parseInt(department_id)});
                        if (pd_index == -1) {
                            let department_index = _.findIndex(self.departments, {id: parseInt(department_id)});
                            let unique_id = self.generateUniqueRandomNum();

                            self.edit_product.new_departments.push({
                                'name' : self.departments[department_index].name,
                                'agents' : [],
                                'unique_id' : unique_id,
                                'department_id': parseInt(department_id),
                                'information' : '',
                                'show_related_public_ticket' : false
                            });

                            setTimeout(function(){
                                
                                $(`#new_pde_agent_${unique_id}`).select2({
                                    dropdownParent: $('#editProduct .modal-content')
                                })
                                .on('change', function(){
                                    let unique_id = $(this).data("uid");
                                    let index = _.findIndex(self.edit_product.new_departments, {unique_id: unique_id});
                                    self.edit_product.new_departments[index].agents = $(this).val();
                                });

                                tinymce.init({
                                    selector: `textarea#info_${unique_id}`,
                                    init_instance_callback: function(editor) {
                                        editor.on('Change', function(e) {
                                            let uid = parseInt($(`#${editor.id}`).attr("data-uid"));
                                            let index = _.findIndex(self.edit_product.new_departments, {unique_id: uid});
                                            self.edit_product.new_departments[index].information = tinymce.get(editor.id).getContent();
                                        });
                                    }
                                });
                            }, 1000);
                        }
                    }
                });
            },
            removeNewDepartmentFromEditProduct(index) {
                const self = this;
                if (confirm(self.__('messages.are_you_sure'))) {
                    let department_id = self.edit_product.new_departments[index].department_id;
                    let department_index = self.choosen_departments.indexOf(department_id);

                    self.edit_product.new_departments.splice(index, 1);
                    self.choosen_departments.splice(department_index, 1);
                }
            },
            removeExistingDepartmentFromEditProduct(index){
                const self = this;
                if (confirm(self.__('messages.are_you_sure'))) {
                    let department_id = self.edit_product.departments[index].department_id;
                    let department_index = self.choosen_departments.indexOf(department_id);

                    self.edit_product.departments.splice(index, 1);
                    self.choosen_departments.splice(department_index, 1);
                }
            },
            generateUniqueRandomNum() {
                return Math.floor(1000 + Math.random() * 90000);
            }
		}
	}
</script>