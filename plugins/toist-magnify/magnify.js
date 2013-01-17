function quickEditPost(){
	var $ = jQuery;
	var _edit = inlineEditPost.edit;
	inlineEditPost.edit = function(id){
		var args = [].slice.call(arguments);
		_edit.apply(this,args);
		
		if(typeof(id) == 'object'){
			id = this.getId(id);
		}
		
		if(this.type == 'post'){
			var 
				editRow = $('#edit-'+id),
				postRow = $('#post-'+id),
				status = $('.column-tomag_status',postRow).text(),
				checked = false;
				
				if(status == 'Included') checked = true;
				
			//set the value in the quick editor
			if(checked == true){
				$(':input[name=tomag_include]',editRow).attr('checked','checked');
			}
		}
	}
}

if(inlineEditPost){
	quickEditPost();
} else{
	jQuery(quickEditPost);
}
