<template>
    <span v-text="__('messages.'+data.role)"></span>
</template>
<script>
	export default {
	    props: {
	        data: {}
	    }
	}
</script>