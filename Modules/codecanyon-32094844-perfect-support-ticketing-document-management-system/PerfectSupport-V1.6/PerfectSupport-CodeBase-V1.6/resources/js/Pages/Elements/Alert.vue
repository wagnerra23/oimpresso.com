<template>
    <div 
        :class="['col-md-12', 'alert', 'alert-dismissible', 'fade', 'show', 'alert-' + type]" 
        role="alert" v-if="!_.isEmpty(content)">
            {{content}}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" v-if="is_dismissible"><span aria-hidden="true">×</span></button>
    </div>
</template>

<script>
    export default {
        props: {
            content: String,
            type: String,
            is_dismissible: Boolean
        }
    }
</script>
