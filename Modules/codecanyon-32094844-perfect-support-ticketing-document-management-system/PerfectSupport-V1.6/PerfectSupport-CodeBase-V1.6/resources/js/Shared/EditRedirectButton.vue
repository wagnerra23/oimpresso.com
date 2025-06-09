<template>
    <inertia-link :href="route_ziggy(meta.url, [data.id])" :class="classes">
        <i class="far fa-edit text-white"></i>
    </inertia-link>
</template>
<script>
	export default {
	    props: {
	        data: {},
	        name: {},
	        classes: {
	            type: Object
	        },
	        meta:{
	        	type: Object
	        }
	    }
	}
</script>