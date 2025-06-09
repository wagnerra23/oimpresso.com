<template>
 	<layout :title="__('messages.home')">
    	<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<h3>
			{{__('messages.welcome',{name: $page.auth.user.name})}}
		</h3>
  	</layout>
</template>

<script>
	import Layout from '@/Shared/Layout';
	import Leftnav from '@/Pages/Customer/Leftnav';

	export default {
		components: {
			Layout,
			Leftnav
		}
	}
</script>