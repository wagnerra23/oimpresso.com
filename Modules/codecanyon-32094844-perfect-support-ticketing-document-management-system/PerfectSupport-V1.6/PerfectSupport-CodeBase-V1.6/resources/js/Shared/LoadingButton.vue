<template>
	<button :disabled="loading">
		<spinner :spin="loading"></spinner>
		<slot />
	</button>
</template>
<script>
	import Spinner from './Spinner';
	export default {
		components: {
			Spinner
		},
	  	props: {
	    	loading: Boolean
	  	}
	}
</script>