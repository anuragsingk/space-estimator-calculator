jQuery(document).ready(function($) {
    $('.upload-image').on('click', function(e) {
        e.preventDefault();
        let button = $(this);
        let uploader = wp.media({
            title: 'Select Image',
            button: { text: 'Use this image' },
            multiple: false
        }).on('select', function() {
            let attachment = uploader.state().get('selection').first().toJSON();
            $('#image_id').val(attachment.id);
            $('.image-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" style="max-width:100px;" />');
            $('.remove-image').show();
        }).open();
    });

    $('.remove-image').on('click', function(e) {
        e.preventDefault();
        $('#image_id').val('');
        $('.image-preview').empty();
        $(this).hide();
    });

    $('.delete-item, .delete-room').on('click', function(e) {
        if (!confirm('Are you sure?')) return false;
        let postId = $(this).data('id');
        let isRoom = $(this).hasClass('delete-room');
        $.post(spaceEstimatorAdmin.ajax_url, {
            action: isRoom ? 'delete_room' : 'delete_item',
            post_id: postId,
            nonce: spaceEstimatorAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data);
            }
        });
    });
});