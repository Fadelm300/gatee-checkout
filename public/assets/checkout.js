$(function () {
    const form = $('#checkout-form');
    const button = form.find('button[type="submit"]');
    const message = $('#checkout-message');

    button.prop('disabled', false);

    form.on('submit', function (event) {
        event.preventDefault();

        button.prop('disabled', true);
        message.text('Checking your details...');

        $.ajax({
            url: 'api/create-payment.php',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json' })

        .done(function (response) {
            message.text(response.message); })
            
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