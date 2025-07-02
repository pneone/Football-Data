(function($) {
    'use strict';

    $(document).ready(function() {

        $('#footballForm').on('submit', function(e) {
            e.preventDefault();

            $('#sfd-loading').show();
            $('#sfd-content').hide();

            $.ajax({
                url: sfd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sfd_get_data',
                    type: $('#type').val(),
                    league: $('#league').val(),
                    date_from: $('#date_from').val(),
                    date_to: $('#date_to').val(),
                    nonce: sfd_ajax.nonce,
                },
                success: function(response) {
                    $('#sfd-loading').hide();

                    if (response.success) {
                        $('#sfd-content').html(response.data).show();
                    } else {
                        const message = response.data && response.data.message
                            ? response.data.message
                            : 'Error downloading data from the server';

                        $('#sfd-content').html('<p class="football-error">' + message + '</p>').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#sfd-loading').hide();

                    const responseText = xhr.responseText ? xhr.responseText : '';
                    const message = `AJAX ERROR: ${error}<br>Status: ${status}<br>${responseText}`;

                    $('#sfd-content').html('<p class="football-error">' + message + '</p>').show();

                    console.error('AJAX ERROR:', {
                        status: status,
                        error: error,
                        response: xhr
                    });
                }

            });
        });
    });

})(jQuery);