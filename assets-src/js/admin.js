jQuery(document).ready(function ($) {
    $('#ck-run-analysis').on('click', function (e) {
        e.preventDefault();

        var btn = $(this);
        var productId = btn.data('product-id');
        var nonce = ck_vars.nonce;

        if (!productId) {
            alert('Error: Product ID missing.');
            return;
        }

        btn.prop('disabled', true).text(ck_vars.running_text);

        $.ajax({
            url: ck_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'ck_run_analysis',
                product_id: productId,
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    btn.prop('disabled', false).text(ck_vars.btn_text);
                }
            },
            error: function () {
                alert('Request failed.');
                btn.prop('disabled', false).text(ck_vars.btn_text);
            }
        });
    });
});
