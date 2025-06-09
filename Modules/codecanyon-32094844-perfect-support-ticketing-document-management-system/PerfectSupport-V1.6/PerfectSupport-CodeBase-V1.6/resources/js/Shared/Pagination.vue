<template>
  	<div class="mt-6 float-right">
	    <ul class="pagination">
		    <li class="page-item">
		    	<inertia-link class="page-link" :href="url + '?page='+ decreasePage(pagination.current_page)"
					:data="_.pickBy(urlProps)" :class="pagination.current_page == 1 ? 'disable-event' : ''">
					<i class="fas fa-chevron-left"></i>
			      	{{__('messages.prev_page')}}
			    </inertia-link>
		    </li>
		    <li class="page-item" v-for="page in pagesNumber" :class="{'active': page == pagination.current_page}">
        		<inertia-link class="page-link" :href="url + '?page='+ page" :data="_.pickBy(urlProps)">
			      	{{ page }}
			    </inertia-link>
        	</li>
		    <li class="page-item">
		    	<inertia-link class="page-link" :href="url + '?page='+ increasePage(pagination.current_page)"
					:data="_.pickBy(urlProps)" :class="pagination.current_page == pagination.last_page ? 'disable-event' : ''">
			      	{{__('messages.next_page')}}
			      	<i class="fas fa-chevron-right"></i>
			    </inertia-link>
		    </li>
		</ul>
  	</div>
</template>

<script>
	export default {
		props: ['urlProps', 'pagination', 'url'],
	  	computed: {
      		pagesNumber() {
				if (!this.pagination.to) {
					return [];
				}
				
				let from = 1;
				let to = this.pagination.last_page;
				let pagesArray = [];

				for (let page = from; page <= to; page++) {
					pagesArray.push(page);
				}
				return pagesArray;
			}
	    },
	    methods :{
	    	increasePage(page) {
				return page + 1;
			},
			decreasePage(page) {
				return page - 1;	
			}
	    }
	}
</script>
<style scoped>
	.disable-event {
		pointer-events: none !important;
	}
</style>