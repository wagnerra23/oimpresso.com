<template>
	<layout :title="__('messages.documentations')">
		<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <div class="page-header-title">
			<h3 class="m-b-10">
				{{__('messages.documentations')}}
				<a :href="route_ziggy('documentation-index')"
					class="btn btn-square btn-sm btn-outline-primary"
					target="_blank">
					<i class="fas fa-external-link-alt"></i>
					{{__('messages.view_doc')}}
				</a>
				<inertia-link :href="route_ziggy('documentation.create')"
					class="btn btn-square btn-sm btn-outline-primary float-right"
					:data="{'doc_type': 'doc'}">
					{{__('messages.add_doc')}}
				</inertia-link>
			</h3>
		</div>
		<div class="page-wrapper mt-4">
			<div class="row">
				<template v-if="documentations.length" v-for="documentation in documentations">
					<div class="col-md-4">
						<div class="card code-table">
							<div class="card-header">
								<div class="d-flex align-items-center">
									<h4 class="mr-auto">
										<a :href="route_ziggy('view.documentation', {'slug': documentation.doc_slug , 'documentation': documentation.id})" target="_blank" class="text-dark">
											{{documentation.title}}
										</a>
									</h4>
									<a :href="route_ziggy('view.documentation', {'slug': documentation.doc_slug , 'documentation': documentation.id})" target="_blank" class="mr-2" :title="__('messages.view_doc_type', {'doc_type': documentation.doc_type})">
										<i class="fas fa-external-link-alt"></i>
									</a>
							      	<inertia-link :href="route_ziggy('documentation.edit', {'id': documentation.id})"
									class="mr-1" :title="__('messages.edit_doc_type', {'doc_type': documentation.doc_type})">
										<i class="fas fa-edit text-muted"></i>
									</inertia-link>
									<i class="far fa-trash-alt text-danger cursor-pointer"
									:title="__('messages.delete_doc_type', {'doc_type': documentation.doc_type})"
									@click="remove(documentation.id)"></i>
								</div>
								<p :title="__('messages.was_this_doc_helpful')">
									<small>{{__('messages.yes')}} : {{documentation.yes}}, {{__('messages.no')}} : {{documentation.no}}</small>
								</p>
							</div>
							<div class="card-body pr-3 pl-3">
								<template v-if="documentation.sections.length">
									<div class="accordion sortable" id="documentation">
										<template v-for="(section, index) in documentation.sections">
											<div class="card"
												:id="section.id" :sort="index">
										        <div class="card-header" :id="'heading_'+section.id">
										        	<div class="d-flex align-items-center">
											            <h5 class="mb-0">
											            	<a href="#!" data-toggle="collapse" :data-target="'#collapse_'+section.id"
											            		aria-expanded="false" :aria-controls="'collapse_'+section.id" class="collapsed">
											            		{{section.title}}
											            	</a>
											            </h5>
											            <a :href="route_ziggy('view.documentation.section', {'slug': section.doc_slug , 'documentation': section.id})" target="_blank" class="mr-2" :title="__('messages.view_doc_type', {'doc_type': section.doc_type})">
															<i class="fas fa-external-link-alt"></i>
														</a>
														<inertia-link :href="route_ziggy('documentation.edit', {'id': section.id})"
															class="mr-1" :title="__('messages.edit_doc_type', {'doc_type': section.doc_type})">
															<i class="fas fa-edit text-muted"></i>
														</inertia-link>
														<i class="far fa-trash-alt text-danger cursor-pointer mr-1"
															:title="__('messages.delete_doc_type', {'doc_type': section.doc_type})"
															@click="remove(section.id)"></i>
														<inertia-link :href="route_ziggy('documentation.create')"
															:data="{'doc_type': 'article', 'parent_id' : section.id}"
															:title="__('messages.add_doc_type', {'doc_type': 'article'})"
															class="ml-1 mr-2">
															<i class="fas fa-plus-circle"></i>
														</inertia-link>
														<i class="fas fa-bars handle ml-auto cursor-pointer" :title="__('messages.sort_order')"></i>
											        </div>
											        <p :title="__('messages.was_this_doc_helpful')">
														<small>{{__('messages.yes')}} : {{section.yes}}, {{__('messages.no')}} : {{section.no}}</small>
													</p>
										        </div>
										        <div :id="'collapse_'+section.id" class="collapse" :aria-labelledby="'heading_'+section.id" data-parent="#documentation">
										        	<template v-if="section.articles.length">
											            <div class="card-body pr-3 pl-3">
											            	<ul class="list-group sortable">
											            		<template v-for="(article, index) in section.articles">
																	<li class="list-group-item text-dark d-flex justify-content-between align-items-center"
																	:id="article.id" :sort="index">
																		<a :href="route_ziggy('view.section.article', {'slug': article.doc_slug , 'documentation': article.id})" target="_blank" class="text-dark">
																			{{article.title}}
																		</a>
																		<span class="badge">
																			<span class="badge badge-secondary mr-1" :title="__('messages.was_this_doc_helpful')">
																				{{__('messages.yes')}} : {{article.yes}}
																			</span>
																			<span class="badge badge-secondary mr-1" :title="__('messages.was_this_doc_helpful')">
																				{{__('messages.no')}} : {{article.no}}
																			</span>
																			<a :href="route_ziggy('view.section.article', {'slug': article.doc_slug , 'documentation': article.id})" target="_blank" class="mr-1" :title="__('messages.view_doc_type', {'doc_type': article.doc_type})">
																				<i class="fas fa-external-link-alt"></i>
																			</a>
																			<inertia-link :href="route_ziggy('documentation.edit', {'id': article.id})"
																				class="mr-1" :title="__('messages.edit_doc_type', {'doc_type': article.doc_type})">
																				<i class="fas fa-edit text-muted"></i>
																			</inertia-link>
																			<i class="far fa-trash-alt text-danger cursor-pointer mr-1"
																				:title="__('messages.delete_doc_type', {'doc_type': article.doc_type})"
																			@click="remove(article.id)"></i>
																			<i class="fas fa-bars handle ml-auto cursor-pointer" :title="__('messages.sort_order')"></i>
																		</span>
																	</li>
																</template>
															</ul>
											            </div>
											        </template>
										        </div>
										    </div>
										</template>
									</div>
								</template>
							</div>
							<div class="card-footer text-center text-muted">
							    <inertia-link :href="route_ziggy('documentation.create')"
									class="btn btn-square btn-sm btn-primary"
									:data="{'doc_type': 'section', 'parent_id' : documentation.id}">
									{{__('messages.add_section')}}
								</inertia-link>
							</div>
						</div>
					</div>
				</template>
			</div>
		</div>
	</layout>
</template>
<script>
	import Layout from '@/Shared/Layout';
	import Leftnav from '@/Pages/Elements/Leftnav';
	export default {
		components: {
			Layout,
			Leftnav,
		},
		props:['documentations'],
		created() {
			const self = this;
			$(function() {
				$(".sortable").sortable({
					handle: ".handle",
					update: function (event, ui) {
						let data = $(this).sortable("toArray");
						self.updateSortOrder(data);
					}
				});
			});
		},
		methods :{
			remove(id) {
				const self = this;
                if (confirm(self.__('messages.are_you_sure'))) {
                	self.$inertia.delete(self.route_ziggy('documentation.destroy', [id]).url())
	                .then(function(response){
	                    console.log(response);
	                });
                }
			},
			updateSortOrder(data) {
				const self = this;
				axios.post(self.route_ziggy('update.doc.sortOrder').url(), {
						ids: data
					})
					.then(function (response) {
						if(response.data.success) {
							toastr.success(response.data.msg);
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
</script>