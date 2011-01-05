/*
	Web implementation of the Yui compressor.
	author: J. Hunter
	Modified: 2011-01-05
	
	
*/



$(document).ready(function () {
	
	multiUpload.init();
	
});


/*
	Module multiUpload
	@author	John Hunter
*/
var multiUpload = function ($) {
	
	var fileList,
		fileCount,
		listContainer,
		fileExtn,
		uploadField,
		fileTypeField,
		fileNameField,
		reportPanel,
		allowedFileTypes = /js|css/i,
		defaultFileName = 'lib-min',
		detailsText = ['show details','hide details'],
		messages = {
			noSupport:			'Your browser does not support multiple file uploads! \n\nThis application requires: Firefox 3.6+, Chrome 9+, or Safari 5+', 
			noUploadFile: 		'You need to upload at least one file.', 
			wrongFileType: 		'Supported file types are .js or .css only.',
			mixedFile:			'You can only compress one file type at a time, please upload either .js or .css files.',
			confDeleteFile:		'Are you sure you want to remove this file?',
			confOverwriteFiles: 'Selecting new files for upload will overwrite your existing selection. Do you want to do that?'
		};
	
	
	function init () {
		uploadField = $('#upload');
		fileTypeField = $('#name-suffix');
		fileNameField = $('#name');
		listContainer = $('#filenames');
		reportPanel = $('#report');
		
		clearFileList();
		
		if (!('files' in uploadField.get(0))) {
			return error('noSupport', 'fatal');
		}
		
		listContainer.sortable({
			axis: 'y' ,
			containment: 'parent',
			forcePlaceholderSize: true,
			cursor: 'move',
			tolerance: 'pointer'
		});
		
		if (uploadField.val()) parseUpload();
		
		uploadField.
			change(parseUpload).
			click(function () {
				if (fileCount > 0 || reportPanel.length) {
					var isConfirmed = confirm(messages.confOverwriteFiles);
					if (isConfirmed) {
						clearResult();
						clearFileList();
					}
					return isConfirmed;
				}
			});
		
		
		$('form').submit(function (e) {
			if (fileCount == 0) return error('noUploadFile', 'fatal');
		}).find(':text').bind('keypress', function (e) {
			if (e.keyCode == 13) return false;
		});
		
		$('a.remove-field').live('click', function (e) {
			var row = $(this).parents('p');
			e.preventDefault();
			row.addClass('active');
			if (confirm(messages.confDeleteFile)) {
				row.swapClass('deleting','active').fadeOut(function () { row.remove(); });
				fileCount--;
			}
			else {
				row.removeClass('active');
			}
		});
		
		$('#options-control').click(function (e) {
			this.blur();
			e.preventDefault();
			$(this).toggleClass('active');
			$('#options').slideToggle(200);
		});

		$('dl', reportPanel).each(function (i) {
			var detail = $(this),
				id = 'detail-' + (i+1);
				control = $('<a href="#' + id + '">' + detailsText[0] + '</a>').click(function (e) {
					e.preventDefault();
					var el = $(this),
						c = 'expanded',
						isExp = el.hasClass(c);

					if (isExp) this.blur();
					el.text(detailsText[ isExp ? 0 : 1 ]);
					el.toggleClass(c);
					detail.slideToggle();
				});

			detail.hide().attr('id', id).prev().append(control);
		});
	}
	
	
	function clearFileList () {
		listContainer.empty();
		fileCount = 0;
		fileList = [];
		fileExtn = '';
		fileNameField.val('');
	}
	
	
	function clearResult () {
		reportPanel.remove();
		$('#compressed-file').remove();
	}
	

	function parseUpload () {
		
		fileList = uploadField.get(0).files || [];
		
		$.each(fileList, function (i, v) {
			//	from file api: v.name, v.size, v.type
			// NOTE: v.type is reported as text/* (Firefox) or application/x-javascript (Chrome)
			
			var name = v.name.split('.'),
				extn = name.pop().toLowerCase();
			
			
			if (fileList.length === 1) fileNameField.val(name.join('.') + '-min');
			
			if (!fileExtn) {
				if (!allowedFileTypes.test(extn)) {
					return error('wrongFileType', 'warning');// continue
				}
				fileExtn = extn;
			}
			else if (fileExtn !== extn) {
				return error('mixedFile', 'fatal');// break
			}

			v.kbSize = Number(v.size/1000).toFixed(2);
			
			$( "#fileRowTmpl" ).tmpl(v).appendTo(listContainer); // optimize!!
			fileCount++;
			fileTypeField.val(fileExtn);
		});
		
		if (!fileNameField.val()) {
			fileNameField.val(defaultFileName);
		}
		
	}
	
	
	function error (name, severity) {
		// TODO: write proper error reporting
		
		
		if (severity === 'fatal') {
			alert(messages[name] || name);
			return false;
		}
		else {
			alert(messages[name] || name);
		}
		
		return true;
	}
	
	
	return {
		init: init
	};
}(jQuery);




// --- move to lib.js ---------------------------------------------------------/
/*
	swapClass - a jQuery plugin
	@author	John Hunter
*/
(function ($) {
	
	$.fn.swapClass = function (add, remove) {
		return this.addClass(add).removeClass(remove);
	};
		
})(jQuery);
