jQuery(document).ready(function($) {
    let selectedRoom = $('.room').first().data('room-id') || 0;
    let quantities = {};

    $('.room').on('click', function() {
        $('.room').removeClass('selected');
        $(this).addClass('selected');
        selectedRoom = $(this).data('room-id');
        loadItems();
        calculateTotal();
    });

    $(document).on('click', '.qty-minus', function() {
        let itemId = $(this).closest('.item-row').data('item-id');
        let qtyInput = $(`.qty-input[data-item="${itemId}"]`);
        let qty = parseInt(qtyInput.val()) - 1;
        qtyInput.val(Math.max(0, qty));
        quantities[itemId] = Math.max(0, qty);
        calculateTotal();
    });

    $(document).on('click', '.qty-plus', function() {
        let itemId = $(this).closest('.item-row').data('item-id');
        let qtyInput = $(`.qty-input[data-item="${itemId}"]`);
        let qty = parseInt(qtyInput.val()) + 1;
        qtyInput.val(qty);
        quantities[itemId] = qty;
        calculateTotal();
    });

    $(document).on('change', '.qty-input', function() {
        let itemId = $(this).data('item');
        let qty = parseInt($(this).val()) || 0;
        $(this).val(Math.max(0, qty));
        quantities[itemId] = qty;
        calculateTotal();
    });

    function loadItems() {
        quantities = {}; // Reset quantities on room change
        if (selectedRoom) {
            $.post(spaceEstimator.ajax_url, {
                action: 'get_items_by_room',
                room_id: selectedRoom,
                nonce: spaceEstimator.nonce
            }, function(response) {
                if (response.success) {
                    $('#items').html(response.data.html);
                    calculateTotal();
                }
            });
        }
    }

    function calculateTotal() {
        let roomSpaceText = $('.room.selected .room-space').text();
        let roomSpace = 0;
        if (roomSpaceText) {
            let match = roomSpaceText.match(/[\d.]+/);
            roomSpace = match ? parseFloat(match[0]) : 0;
        }
        let total = roomSpace;
        $('.item-row').each(function() {
            let itemId = $(this).data('item-id');
            let match = $(this).find('.item-name').text().match(/\(([\d.]+)/);
            let spaceValue = match ? parseFloat(match[1]) : 0;
            let qty = quantities[itemId] || 0;
            total += spaceValue * qty;
        });
        $('#total-space').text(total.toFixed(2));
    }

    calculateTotal();
});