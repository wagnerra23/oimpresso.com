<template>
	<layout :title="__('messages.add_doc_type', {'doc_type': doc_type})">
		<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
			        <div class="card">
			        	<div class="card-header">
			        		<h5 v-html="__('messages.add_doc_on', {'doc_type': doc_type, 'doc' : parent_doc.title})"
			        			v-if="parent_doc">
                            </h5>
                            <h5 v-else>
                            	{{__('messages.add_doc_type', {'doc_type': doc_type})}}
                            </h5>
			        	</div>
			        	<form method="POST" @submit.prevent="submitForm">
			        		<div class="card-body">
			        			<div class="row">
			        				<div class="col-md-12">
			        					<div class="form-group">
                                            <label for="title">
                                                {{__('messages.title')}}
                                            </label>
                                            <input type="text" :class="['form-control', $page.errors.title ? 'is-invalid': '']" id="title" :placeholder="__('messages.title')" name="title" v-model="doc.title" required>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.title">
                                                <strong>
                                                	{{ $page.errors.title[0] }}
                                                </strong>
                                            </span>
                                        </div>
			        				</div>
			        			</div>
			        			<div class="row">
				        			<div class="col-md-12">
				        				<div class="form-group">
				        					<label for="content">
				        						{{__('messages.content')}}
				        					</label>
				        					<textarea class="form-control" v-model="doc.content" name="content" rows="3" id="content"></textarea>
				        				</div>
				        			</div>
				        		</div>
			        			<div class="row mt-3">
			        				<div class="col-md-12">
			        					<loading-button :loading="submitting" class="btn btn-success float-right" type="submit">
		                                   {{__('messages.save')}}
		                                </loading-button>	
			        				</div>
			        			</div>
			        		</div>
			        	</form>
			        </div>
			    </div>
			</div>
		</div>
	</layout>
</template>
<script>
	import Layout from '@/Shared/Layout';
	import Leftnav from '@/Pages/Elements/Leftnav';
	import LoadingButton from '@/Shared/LoadingButton';
	export default {
		components: {
			Layout,
			Leftnav,
			LoadingButton
		},
		props: ['doc_type', 'parent_id', 'parent_doc'],
		data: function () {
  			return {
  				doc: {
                    title: '',
                    content:''
                },
                submitting: false
  			}
  		},
  		created: function () {
  			const self = this;
  			let upload_url = self.route_ziggy('doc.img.upload').url();
		    $(function() {
		    	//if editor exist destory & re-initialize it
		    	if (!_.isNull(tinymce.get('content'))) {
  					tinymce.remove("textarea#content");
				}
				//initialize editor
				tinymce.init({
				    selector: 'textarea#content',
				    height: 500,
					theme: 'silver',
					plugins: [
					'paste link autolink lists hr anchor pagebreak codesample code image'
					],
					toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify |' +
					' bullist numlist outdent indent | link image | forecolor backcolor | codesample code',
					menubar: 'edit insert',
					images_upload_url: upload_url,
					document_base_url: APP.APP_URL+'/',
					relative_urls: true,
					/* enable title field in the Image dialog*/
					image_title: true,
					/* enable automatic uploads of images represented by blob or data URIs*/
					automatic_uploads: true,
					/*
					URL of our upload handler (for more details check: https://www.tiny.cloud/docs/configure/file-image-upload/#images_upload_url)
					images_upload_url: 'postAcceptor.php',
					here we add custom filepicker only to Image dialog
					*/
					file_picker_types: 'image',
					/* and here's our custom image picker*/
					file_picker_callback: function (cb, value, meta) {
					var input = document.createElement('input');
					input.setAttribute('type', 'file');
					input.setAttribute('accept', 'image/*');

					/*
					  Note: In modern browsers input[type="file"] is functional without
					  even adding it to the DOM, but that might not be the case in some older
					  or quirky browsers like IE, so you might want to add it to the DOM
					  just in case, and visually hide it. And do not forget do remove it
					  once you do not need it anymore.
					*/

					input.onchange = function () {
					  var file = this.files[0];

					  var reader = new FileReader();
					  reader.onload = function () {
					    /*
					      Note: Now we need to register the blob in TinyMCEs image blob
					      registry. In the next release this part hopefully won't be
					      necessary, as we are looking to handle it internally.
					    */
					    var id = 'blobid' + (new Date()).getTime();
					    var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
					    var base64 = reader.result.split(',')[1];
					    var blobInfo = blobCache.create(id, file, base64);
					    blobCache.add(blobInfo);

					    /* call the callback and populate the Title field with the file name */
					    cb(blobInfo.blobUri(), { title: file.name });
					  };
					  reader.readAsDataURL(file);
					};

					input.click();
					},
				});
		    });
		},
  		methods:{
			submitForm(){
                self = this;

                let doc_data = _.pick(self.doc, ['title']);
				doc_data.content = tinymce.get("content").getContent();
				doc_data.doc_type = self.doc_type;
				doc_data.parent_id = self.parent_id;
				
                self.submitting = true;
                self.$inertia.post(self.route_ziggy('documentation.store').url(), doc_data)
                .then(function(response){
                    self.submitting = false;
                });
            },
		}
	}
</script>