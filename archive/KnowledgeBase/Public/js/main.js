$(document).ready(function(){
	$('#kb-customer-logout-link').click(function(e) {
		$('#customer-logout-form').submit();
		e.preventDefault();
	});
	$('#kb-logout-link').click(function(e) {
		$('#logout-form').submit();
		e.preventDefault();
	});
	$('#kb-category-nav-toggle').click(function(e) {
		$('#kb-category-nav').toggleClass('hidden-xs')
		e.preventDefault();
	});
});
