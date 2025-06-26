jQuery(document).ready(function($) {
    $('#give-kkiapay-button').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $feedback = $('#give-kkiapay-feedback');
        var amount = $('#give-amount').val();
        
        $button.prop('disabled', true).text(give_kkiapay_vars.loading);
        $feedback.removeClass('success error').hide();
        
        if (typeof KkiapayWidget === 'undefined') {
            showError(give_kkiapay_vars.error);
            return;
        }
        
        KkiapayWidget.init({
            amount: parseInt(amount),
            key: give_kkiapay_vars.public_key,
            callback: function(response) {
                if (response && response.transactionId) {
                    processPayment(response.transactionId);
                } else {
                    showError(give_kkiapay_vars.error);
                }
            },
            theme: "purple",
            position: "center",
            sandbox: give_kkiapay_vars.sandbox === 'true'
        });
    });
    
    function processPayment(transactionId) {
        var $form = $('#give-form-' + give_kkiapay_vars.form_id);
        
        $('<input>').attr({
            type: 'hidden',
            name: 'kkiapay_transaction_id',
            value: transactionId
        }).appendTo($form);
        
        $form.submit();
    }
    
    function showError(message) {
        var $feedback = $('#give-kkiapay-feedback');
        $feedback.addClass('error').text(message).show();
        $('#give-kkiapay-button').prop('disabled', false).text(give_kkiapay_vars.button_text);
    }
});