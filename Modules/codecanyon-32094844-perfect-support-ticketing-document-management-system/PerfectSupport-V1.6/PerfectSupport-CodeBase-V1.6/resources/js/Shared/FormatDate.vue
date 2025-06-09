<template>
    <span>
    	{{$commonFunction.formatDate(data.created_at)}}
    </span>
</template>
<script>
	export default {
	    props: {
	        data: {}
	    }
	}
</script>