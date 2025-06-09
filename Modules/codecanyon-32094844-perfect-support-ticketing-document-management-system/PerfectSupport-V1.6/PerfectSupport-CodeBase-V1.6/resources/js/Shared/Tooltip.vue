<template>
	<span data-toggle="tooltip" data-html="true" :title="title">
		<slot/>
	</span>
</template>
<script>
	export default {
		props: {
    		title: String,
  		}
	}
</script>