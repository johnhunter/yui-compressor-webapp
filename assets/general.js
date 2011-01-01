/*
	Web implementation of the Yui compressor.
	author: J. Hunter / A. Smith
	Modified: 2010-07-25
*/


var filesUpload = $('#upload');

filesUpload.change(parseUpload);
if (filesUpload.val()) parseUpload();

function parseUpload () {
	
	var el = filesUpload,
		filenames = $('#filenames'),
		fileList = filesUpload.get(0).files || [];
	
	filenames.empty().sortable({
		axis: 'y' ,
		containment: 'parent',
		forcePlaceholderSize: true
	});
	
	$.each(fileList, function (i, v) {
		
		//	v.name, v.size, v.type,
		// TODO test mimetype is text/*
		
		v.kbSize = Number(v.size/1000).toFixed(2);
		$( "#fileRowTmpl" ).tmpl(v).appendTo(filenames);
		
	});
	
}




$(document).ready(function () {
	
	//multiUpload.init();
	

	$("#removefield").click(function (e){
		$("#files p.row:last").prev().remove();
		e.preventDefault();
		return(false);
	});
	
	$('#options-control').click(function (e) {
		this.blur();
		e.preventDefault();
		$(this).toggleClass('active');
		$('#options').slideToggle(200);
	});
	
});


var multiUpload = {
	errorMessages: {
		noUploadFile:	'You need to upload at least one file.', 
		wrongFileType:	'Supported file types are .js or .css only.',
		mixedFile:		'You can only compress one file type at a time, please upload either .js or .css files.',
		noOutputFile:	'Please provide an output file name.'
	},
	errorType: {},
	compressedSuffix: '-min',
	
	
	init: function () {
		var that = this,
			container = $('#files');
			
		
		that.currentIndex = 0;
		that.fileType = $('#name-suffix');
		that.fileName = $('#name');
		that.initialFilename = '';
		that.resetErrors();
		
		that.populate($('p.row:last', container), true);
		
		$('#addFileUpload').click(function (e) {
			var prev = $('p.row:last', container);
			container.append(prev.clone());
			var current = container.find('p.row:last');
			$('input', current).show();
			$('a.remove-field', current).hide();
			$('span.filename', current).remove();
			
			that.populate(current);
			
			e.preventDefault();
			this.blur();
		});
		
		$('#compress-button').click(function (e) {
			var inputs = $('#files input');
			
			if ($('#name').val() === '') that.errorType.noOutputFile = true;
			if (inputs.length === 0) that.errorType.noUploadFile = true;
			
			inputs.each(function () {
				that.processUploadFile($(this));
			});
			
			
			if (that.showErrors()) {
				e.preventDefault();
				return false;
			}
			
			return true;
		});
		
		$('form :text').bind('keypress', function (e) {
			// dont submit on enter
			if (e.keyCode == 13) { return false; }
		});
		
	},
	
	populate: function (row, initial) {
		var that = this,
			index = that.currentIndex;
			
		index++;
		$('span.index', row).html(index);
		
		$("input[type='file']", row)
			.val('')
			.change(function (e) {
				var input = $(this),
					filename = that.processUploadFile(input);
				
				// hide input and show filename
				input.hide().before('<span class="filename">'+ filename +'</span>');
				
				that.showErrors();
			});
		
		$('a.remove-field', row).click(function (e) {
			$(this).parents('p.row').remove();
			that.enableRemoveButtons();
			e.preventDefault();
		});
		
		if (!initial) {
			row.hide();
			row.slideDown(200);
		}

		that.enableRemoveButtons();
		
		that.currentIndex++;
	},
	
	getFilename: function (fullpath) {
		return fullpath.split(/[\\|\/]/).pop();
	},
	
	enableRemoveButtons: function () {
		var buttons = $('#files a.remove-field');
		if (buttons.length === 1) {
			buttons.hide();
		}
		else {
			buttons.show();
		}
	},
	
	processUploadFile: function (input) {
		//called on each upload click
		
		var that =  this,
			filename = that.getFilename(input.val()),
			errorType = { noUploadFile: false, wrongFileType: false, mixedFile: false, noOutputFile: false },
			errorMsg = '',
			ext = '',
			fileType = that.fileType.val();
			name = filename.split('.');
			
		
		ext = name.pop().toLowerCase();
		name = name.join('.');
		
		
		if (!(/js|css/.test(ext))) that.errorType.wrongFileType = true;
		if (that.fileName.val() === '') {
			that.fileName.val(name + that.compressedSuffix);
		}
		if (fileType !== '' && fileType !== ext) {
			that.errorType.mixedFile = true;
		}
		else {
			that.fileType.val(ext);
		}
		
		return filename;
	},
	
	resetErrors: function () {
		this.errorType = { noUploadFile: false, wrongFileType: false, mixedFile: false, noOutputFile: false };
	},
	
	showErrors: function () {
		var errorMsg = '';
		
		for (var key in this.errorType) {
			if (this.errorType[key]) errorMsg += this.errorMessages[key] + '\n\n';
		}
		this.resetErrors();
		
		if (errorMsg) {
			alert(errorMsg);
			return true;
		}
		return false;
	}
	
};