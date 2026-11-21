$(function () {
    const element = document.getElementById('payment-modal');
    const modal = bootstrap.Modal.getOrCreateInstance(element);
    let request = null;
    let version = 0;

    function renderFields(target, values) {
        const list = $(target).empty();

        Object.entries(values).forEach(function ([label, value]) {
            $('<dt>').text(label).appendTo(list);
            $('<dd>')
                .text(value == null ? 'Not available' : String(value))
                .appendTo(list);
        });
    }

    $('[data-view-payment]').on('click', function () {
        const id = String($(this).attr('data-view-payment'));
        const current = ++version;

        if (request) {
            request.abort();
        }

        $('#payment-modal-title').text('Payment details - ' + id);
        $('#details-message').text('Loading payment details');
        $('#gateway-message').text('');
        $('#local-details, #gateway-details').empty();
        modal.show(this);

        request = $.ajax({
            url: 'api/payment-details.php',
            method: 'GET',
            data: { id: id },
            dataType: 'json',
            timeout: 35000,
        })
            .done(function (response) {
                if (current !== version || String(response.local.id) !== id) {
                    return;
                }

                const local = response.local;
                $('#details-message').text('');
                renderFields('#local-details', {
                    Reference: local.payment_code,
                    'Gateway payment ID': local.gateway_payment_id,
                    Customer: local.customer_name,
                    Email: local.customer_email,
                    'Mobile (+973)': local.customer_mobile,
                    Amount: local.amount + ' ' + local.currency,
                    'Payment status': local.status,
                    'Transaction ID': local.transaction_id,
                    'Transaction status': local.transaction_status,
                    'Transaction time (gateway)': local.transaction_date,
                    'Response code': local.response_code,
                    Response: local.response_message,
                    'Created (UTC)': local.created_at,
                    'Updated (UTC)': local.updated_at,
                });

                const gateway = response.gateway;
                if (!gateway.available) {
                    $('#gateway-message').text(gateway.message);
                    return;
                }

                const payment = gateway.payment;
                const transaction = gateway.transaction;
                renderFields('#gateway-details', {
                    Reference: payment.code,
                    'Gateway payment ID': payment.id,
                    Amount: payment.amount + ' ' + payment.currency,
                    'Payment status': payment.status,
                    'Transaction ID': transaction.transaction_id,
                    'Transaction status': transaction.transaction_status,
                    'Transaction time (gateway)': transaction.transaction_date,
                    'Response code': transaction.response_code,
                    Response: transaction.response_message,
                    'Fetched (UTC)': gateway.fetched_at,
                });
            })
            .fail(function (xhr, status) {
                if (current !== version || status === 'abort') {
                    return;
                }

                $('#details-message').text(
                    xhr.responseJSON?.message ||
                        'Unable to load payment details. Please try again.',
                );
            })
            .always(function () {
                if (current === version) {
                    request = null;
                }
            });
    });

    element.addEventListener('hide.bs.modal', function () {
        ++version;
        if (request) {
            request.abort();
            request = null;
        }
    });
});
