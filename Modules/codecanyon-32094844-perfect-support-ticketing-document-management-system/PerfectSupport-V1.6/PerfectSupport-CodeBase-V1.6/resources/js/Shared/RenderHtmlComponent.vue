<template>
    <div v-html="data.body"></div>
</template>
<script>
	export default {
	    props: {
	        data: {},
	    }
	}
</script>