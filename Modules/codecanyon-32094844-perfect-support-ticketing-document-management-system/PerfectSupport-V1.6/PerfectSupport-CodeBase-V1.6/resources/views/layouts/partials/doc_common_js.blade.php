<script type="text/javascript">
	$(document).ready(function() {

		$(document).on('change', '#search', function () {
			if($('#gs_tti50').length > 0) {
				$('#gs_tti50').find('input').val($('#search').val());
				$('.gsc-search-button').find('button').trigger("click");
			}
			searchDocs();
		});

		function searchDocs () {
			var search_params = $("input#search").val();
			$.ajax({
	            method: 'GET',
	            url: 'docs-search',
	            dataType: 'json',
	            data: {'search_params' : search_params},
	            success: function(response) {
	            	if (response.success) {
	            		$("div#search_suggestions").html(response.docs);
	            	}
	            }
	        });
		}
		searchDocs();
	});
</script>