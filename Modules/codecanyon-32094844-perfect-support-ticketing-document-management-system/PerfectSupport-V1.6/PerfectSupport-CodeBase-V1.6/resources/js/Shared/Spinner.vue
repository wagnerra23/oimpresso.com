<template>
	<i class="fas fa-spinner fa-pulse fa-spin" v-if="spin"></i>
</template>
<script>
export default {
  	props: {
    	spin: Boolean,
  	}
}
</script>