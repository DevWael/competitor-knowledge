jQuery(document).ready(function ($) {
    var pollInterval = null;

    // Run Analysis with Progress Tracking
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
        $('#ck-progress-container').show();
        updateProgress(0, 'Starting analysis...', '');

        $.ajax({
            url: ck_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'ck_run_analysis',
                product_id: productId,
                nonce: nonce
            },
            success: function (response) {
                if (response.success && response.data.analysis_id) {
                    startProgressPolling(response.data.analysis_id, nonce, btn);
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    btn.prop('disabled', false).text(ck_vars.btn_text);
                    $('#ck-progress-container').hide();
                }
            },
            error: function() {
                alert('Error: Request failed.');
                btn.prop('disabled', false).text(ck_vars.btn_text);
                $('#ck-progress-container').hide();
            }
        });
    });

    // Progress Polling
    function startProgressPolling(analysisId, nonce, btn) {
        pollInterval = setInterval(function() {
            $.ajax({
                url: ck_vars.ajax_url,
                type: 'GET',
                data: {
                    action: 'ck_get_analysis_progress',
                    analysis_id: analysisId,
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success) {
                        var data = response.data;
                        updateProgress(data.percentage, data.step_label || 'Processing...', data.status);

                        if (data.completed) {
                            stopPolling();
                            updateProgress(100, 'Analysis complete!', 'completed');
                            setTimeout(function() { location.reload(); }, 1500);
                        } else if (data.failed) {
                            stopPolling();
                            updateProgress(0, 'Analysis failed: ' + (data.error || 'Unknown error'), 'failed');
                            btn.prop('disabled', false).text(ck_vars.btn_text);
                        }
                    }
                },
                error: function() {
                    // Continue polling on error
                }
            });
        }, 2000); // Poll every 2 seconds
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    function updateProgress(percent, label, status) {
        $('#ck-progress-bar').css('width', percent + '%');
        $('#ck-progress-percent').text(percent + '%');
        $('#ck-progress-label').text(label);
        
        if (status === 'completed') {
            $('#ck-progress-bar').css('background', 'linear-gradient(90deg, #4caf50, #8bc34a)');
        } else if (status === 'failed') {
            $('#ck-progress-bar').css('background', '#f44336');
        }
    }

    // View All Analyses Modal
    $('#ck-view-all-analyses').on('click', function (e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        var nonce = ck_vars.nonce;

        $.ajax({
            url: ck_vars.ajax_url,
            type: 'GET',
            data: {
                action: 'ck_get_product_analyses_modal',
                product_id: productId,
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    showModal(response.data.html);
                } else {
                    alert('Error: ' + (response.data || 'Failed to load analyses'));
                }
            }
        });
    });

    function showModal(html) {
        // Remove existing modal
        $('#ck-modal-overlay').remove();

        var modal = $('<div id="ck-modal-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:100000;display:flex;align-items:center;justify-content:center;">' +
            '<div id="ck-modal" style="background:#fff;padding:20px;border-radius:8px;max-width:800px;max-height:80vh;overflow:auto;position:relative;">' +
            '<button id="ck-modal-close" style="position:absolute;top:10px;right:10px;background:none;border:none;font-size:24px;cursor:pointer;">&times;</button>' +
            html +
            '</div></div>');

        $('body').append(modal);

        $('#ck-modal-close, #ck-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                $('#ck-modal-overlay').remove();
            }
        });
    }

    // Conditional Settings Fields
    function toggleProviderFields() {
        const provider = $('#ck-ai-provider').val();
        const searchProvider = $('#competitor_knowledge_options_search_provider, [name="competitor_knowledge_options[search_provider]"]').val();

        // Hide all provider-specific fields
        $('.ck-field-google, .ck-field-ollama, .ck-field-openrouter, .ck-field-zai').closest('tr').hide();
        $('.ck-field-tavily, .ck-field-brave').closest('tr').hide();

        // Show fields for selected AI provider
        if (provider === 'google') {
            $('.ck-field-google').closest('tr').show();
        } else if (provider === 'ollama') {
            $('.ck-field-ollama').closest('tr').show();
        } else if (provider === 'openrouter') {
            $('.ck-field-openrouter').closest('tr').show();
        } else if (provider === 'zai') {
            $('.ck-field-zai').closest('tr').show();
        }

        // Show fields for selected search provider
        if (searchProvider === 'tavily') {
            $('.ck-field-tavily').closest('tr').show();
        } else if (searchProvider === 'brave') {
            $('.ck-field-brave').closest('tr').show();
        }
    }

    // Initialize on settings page
    if ($('#ck-ai-provider').length) {
        toggleProviderFields();
        $('#ck-ai-provider, [name="competitor_knowledge_options[search_provider]"]').on('change', toggleProviderFields);
    }
});
