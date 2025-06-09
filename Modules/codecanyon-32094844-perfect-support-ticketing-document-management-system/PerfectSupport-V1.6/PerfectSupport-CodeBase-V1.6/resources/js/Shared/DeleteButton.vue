<template>
    <button type="button" :class="classes" @click="click(data.id)">
        <i class="fas fa-trash-alt"></i>
    </button>
</template>
<script>
	export default {
	    props: {
	        data: {},
	        name: {},
	        click: {
	            type: Function,
	            default: () => {}
	        },
	        classes: {
	            type: Object
	        }
	    }
	}
</script>