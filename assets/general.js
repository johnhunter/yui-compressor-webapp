/*
	Web implementation of the Yui compressor.
	author: J. Hunter
	Modified: 2011-01-05
	
*/

window.userWarning = window.userWarning || [];



$(document).ready(function () {
	
	frameDialog.init('#result-frame');
	multiUpload.init();
	
});


// used by both client and server
function showWarning (msg) {
	var m = msg || userWarning.join('\n');
	if (!msg) userWarning = [];
	
	alert(m);
}



/*
	Module multiUpload - multifile upload and compression.
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
		allowedFileTypes = /js|css/i,
		defaultFileName = 'lib-min',
		detailsText = ['show details','hide details'],
		submitButton,
		messages = {
			noSupport:			'Your browser does not support multiple file uploads! \n\nThis application requires: Firefox 3.6+, Chrome 9+, or Safari 5+', 
			noUploadFile: 		'You need to upload at least one file to compress.', 
			wrongFileType: 		'Supported file types are .js or .css only.',
			mixedFile:			'You can only compress one file type at a time, please upload either .js or .css files.',
			confDeleteFile:		'Are you sure you want to remove this file?',
			confOverwriteFiles: 'Selecting new files for upload will overwrite your existing selection. Do you want to do that?',
			confLeavePage:      'You will loose your existing file selection and order.'
		};
	
	
	function init () {
		
		uploadField = $('#upload');
		fileTypeField = $('#name-suffix');
		fileNameField = $('#name');
		listContainer = $('#filenames');
		submitButton = $('#compress-button');
		$('#fileRowTmpl').template('fileRowTemplate');
		
		clearFileList();
		
		if (uploadField.length && !('files' in uploadField.get(0))) {
			return error('noSupport', 'fatal');
		}
		
		listContainer.sortable({
			axis: 'y' ,
			containment: 'parent',
			forcePlaceholderSize: true,
			cursor: 'move',
			tolerance: 'pointer'
		});
		
		uploadField.
			change(parseUpload).
			click(function () {
				var isConfirmed = true;
				if (fileCount > 0) {
					isConfirmed = confirm(messages.confOverwriteFiles);
					if (isConfirmed) clearFileList();
				}
				return isConfirmed;
			});
		
		$('form').
			submit(function (e) {
				if (fileCount == 0) return error('noUploadFile', 'fatal');
				submitButton.addClass('waiting');
				return true;
			}).
			find(':text').bind('keypress', function (e) {
				if (e.keyCode == 13) return false;
			});
		
		
			$("table").delegate("td", "hover", function(){
				$(this).toggleClass("hover");
			});
		
		listContainer.delegate('a.remove-field', 'click', function (e) {
			var row = $(this).parents('p');
			e.preventDefault();
			row.addClass('active');
			if (confirm(messages.confDeleteFile)) {
				row.swapClass('deleting','active').fadeOut(function () { row.remove(); });
				fileCount--;
				if (fileCount === 0) clearFileList();
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
		
		window.onbeforeunload = function () {
			if (fileCount > 0) {
				return messages.confLeavePage;
			}
		};
	}
	
	function clearFileList () {
		listContainer.empty();
		fileCount = 0;
		fileList = [];
		fileExtn = '';
		fileNameField.val('');
		fileTypeField.val('');
		
		uploadField.val('');// clear upload so that onchange will fire.
	}
	
	function processResult (body) {
		var reportPanel = $('#report', body);
		
		submitButton.removeClass('waiting');
		
		if (reportPanel.length) {
			
			$('dl', reportPanel).each(function (i) {
				var detail = $(this),
					control,
					id = 'detail-' + (i+1);
					
				control = $('<a href="#' + id + '">' + detailsText[0] + '</a>').click(function (e) {
					e.preventDefault();
					var el = $(this),
						c = 'expanded',
						isExp = el.hasClass(c);

					if (isExp) this.blur();
					el.text(detailsText[ isExp ? 0 : 1 ]);
					el.toggleClass(c);
					detail.toggle();
					frameDialog.resize();
				});

				detail.hide().attr('id', id).prev().append(control);
			});
			
			return;
		}
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
			
			$.tmpl('fileRowTemplate', v).appendTo(listContainer);
			fileCount++;
			fileTypeField.val(fileExtn);
			
			return true;
		});
		
		if (!fileNameField.val()) {
			fileNameField.val(defaultFileName);
		}
		
	}
	
	function error (name, severity) {
		// TODO: write proper error reporting
		
		
		if (severity === 'fatal') {
			showWarning(messages[name] || name);
			return false;
		}
		else {
			showWarning(messages[name] || name);
		}
		
		return true;
	}
	
	return {
		init: init,
		processResult: processResult
	};
}(jQuery);



/*
	Module frameDialog - loads content in a frameset
	@author	John Hunter
	created	2011-01-06
*/
var frameDialog = function ($) {
	
	var frame,
		frameBody,
		title = document.title;
	
	function init (iframeSelector, titleSep) {
		titleSep = titleSep || ' > ';
		frame = $(iframeSelector).hide();
		
		
		frame.load(function () {
			frameBody = $('body', frame.contents());
			
			var frameTitle = $('title', frameBody.parent()).html();
			if (frameTitle) document.title = title + titleSep + frameTitle;
			
			$('a.close', frameBody).click(close);
			
			multiUpload.processResult(frameBody);
			
			frame.show();
			resize();
			
		});
	}
	
	function resize () {
		frame.height(frameBody.outerHeight(true));
	}
	
	function close (e) {
		if (e) e.preventDefault();
		document.title = title;
		frame.hide();
	}
	
	return {
		init: init,
		resize: resize
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
