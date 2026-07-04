(function($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value || '').html();
	}

	function renderPreview(attachment) {
		var url = attachment.url || '';
		var type = attachment.type === 'video' ? 'video' : 'image';

		if (!url) {
			return '';
		}

		if (type === 'video') {
			return '<video controls src="' + escapeHtml(url) + '"></video><p>' + escapeHtml(attachment.filename || url) + '</p>';
		}

		return '<img src="' + escapeHtml(url) + '" alt="">';
	}

	$(function() {
		var $box = $('.dlh-hof-media-box');
		var frame = null;

		if (!$box.length || typeof wp === 'undefined' || !wp.media) {
			return;
		}

		$box.on('click', '.dlh-hof-media-choose', function(event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'Choose Hall of Fame media',
				button: {
					text: 'Use this media'
				},
				library: {
					type: ['image', 'video']
				},
				multiple: false
			});

			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				var mediaType = attachment.type === 'video' ? 'video' : 'image';

				$('#dlh_media_attachment_id').val(attachment.id || '');
				$('#dlh_media_type').val(mediaType);
				$('#dlh_media_url').val(attachment.url || '');
				$('.dlh-hof-media-preview').html(renderPreview(attachment));
			});

			frame.open();
		});

		$box.on('click', '.dlh-hof-media-clear', function(event) {
			event.preventDefault();
			$('#dlh_media_attachment_id').val('');
			$('#dlh_media_url').val('');
			$('.dlh-hof-media-preview').empty();
		});
	});
})(jQuery);
