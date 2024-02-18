jQuery(function ($) {

    var DailyCoBookly = {

        init: function () {
            this.cacheDOM();
            this.eventListeners();
            this.keepSelectedTabActive();
        },

        cacheDOM: function () {
            this.sendInvoice = $('#headroom-send-invoice-btn');
            this.icdInvoiceField = $('.icd-code-input');
            this.tariffInvoiceField = $('.tarfiff-code-input');
        },

        keepSelectedTabActive: function () {
            $('.wpsm_nav-tabs a[data-toggle="tab"]').on('show.bs.tab', function (e) {
                localStorage.setItem('activeTab', $(e.target).attr('href'));
            });
            var activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                $('.wpsm_nav-tabs a[href="' + activeTab + '"]').tab('show');
            }
        },

        eventListeners: function () {
            $('.headroom-send-invoice-btn').on('click', this.sendTheInvoice.bind(this));
        },

        sendTheInvoice: function (e) {
            e.preventDefault();

            var con = confirm("Are you sure you want to send the invoice to this user ?");
            var data = $(e.currentTarget).data();
            var icd = '.icd-code-input-' + data.aptid;
            var tarriff = '.tarfiff-code-input-' + data.aptid;
            var price = '.manual-price-field-' + data.aptid;
            var icd_code = $(icd).val();
            var tariff_code = $(tarriff).val();
            var manual_price = $(price).val();
            if (con) {
                $(e.currentTarget).html('Please wait...');
                $.post(daily.ajax_uri, {
                    data: data,
                    icd: icd_code,
                    tariff_code: tariff_code,
                    manual_price: manual_price,
                    action: 'send_invoice',
                    security: daily._nonce
                }).done(function (response) {
                    $(e.currentTarget).html('Sent. Please Wait..');
                    location.reload();
                });
            }

            return;
        }
    };

    DailyCoBookly.init();
});