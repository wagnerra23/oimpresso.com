<template>
 	<layout :title="__('messages.backups')">
    	<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <div class="page-wrapper">
        	<div class="row">
        		<div class="col-md-12">
                    <div class="page-header-title">
                        <h3 class="m-b-10">
                            {{__('messages.backups')}}
                        </h3>
                    </div>
                    <div class="card code-table">
                        <div class="card-header">
                            <h5 class="card-title">
                                {{__('messages.all_backups')}}
                            </h5>
                            <loading-button type="button" :loading="submitting" class="btn float-right btn-sm btn-info text-white">
                                <span @click="createBackup()">
                                    <i class="fas fa-plus"></i>
                                    {{__('messages.create_backup')}}
                                </span>
                            </loading-button>
                        </div>
                        <div class="card-block pb-0">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>
                                                {{__('messages.file')}}
                                            </th>
                                            <th>
                                                {{__('messages.date')}}
                                            </th>
                                            <th>
                                                {{__('messages.action')}}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="backup in backups">
                                            <tr>
                                                <td>
                                                    {{backup.file_name}}
                                                </td>
                                                <td>
                                                    {{backup.last_modified}}
                                                </td>
                                                <td>
                                                    <a :href="backup.download_link" class="btn btn-success btn-sm">
                                                        {{__('messages.download')}}
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        @click="removeBackup(backup.file_name)">
                                                        {{__('messages.delete')}}
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr v-if="backups.length === 0">
                                            <td colspan="3">
                                                <div class="alert alert-info" role="alert">
                                                    <h4 class="text-muted">
                                                        {{__('messages.no_data_found')}}
                                                    </h4>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <p>
                                        <h4>
                                            {{__('messages.auto_backup_command_instruction')}}
                                        </h4>
                                        <code>
                                            {{cron_job_command}}
                                        </code>
                                    </p>
                                </div>
                            </div>
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
    import LoadingButton from '@/Shared/LoadingButton';
	export default {
		components: {
			Layout,
			Leftnav,
            LoadingButton
		},
		props: ['backups', 'cron_job_command'],
        data: function () {
            return {
                submitting: false
            }
        },
        methods: {
            removeBackup(file_name) {
                const self = this;
                if (confirm(self.__('messages.are_you_sure'))) {
                    self.$inertia.visit(self.route_ziggy('backups.destroy', [file_name]), {
                        method: 'delete',
                        preserveState: true,
                        preserveScroll: true,
                    });
                }
            },
            createBackup() {
                const self = this;
                self.submitting = true;
                self.$inertia.visit(self.route_ziggy('backups.create'), {
                    method: 'get',
                    preserveState: true,
                    preserveScroll: true,
                }).then(function(response){
                    self.submitting = false;
                });
            }
        }
	}
</script>