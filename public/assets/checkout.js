$(function () {
    const form = $('#checkout-form');
    const button = form.find('button[type="submit"]');
    const message = $('#checkout-message');

    button.prop('disabled', false);

    form.on('submit', function (event) {
        event.preventDefault();

        button.prop('disabled', true);
        message.text('Checking your details 1s');

        $.ajax({
            url: 'api/create-payment.php',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json' })

        .done(function (response) {
            message.text(response.message);
                if (response.result_url) {
                    $('#result-link')
                        .attr('href', response.result_url)
                        .prop('hidden', false);
                }
            if (response.payment_link) {
                $('#payment-frame')
                    .attr('src', response.payment_link)
                    .prop('hidden', false);
            }
        })
            
        .fail(function (xhr) {
            const response = xhr.responseJSON;

            message.text(
                response?.errors
                ? Object.values(response.errors).join(' ')
                : response?.message || 'Unable to process the request.'
            );
              })
        .always(function () {button.prop('disabled', false); });
    });
});