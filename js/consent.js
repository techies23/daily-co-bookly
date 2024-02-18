var canvas = document.getElementById('headroom-consent-signature-pad');

// Adjust canvas coordinate space taking into account pixel ratio,
// to make it look crisp on mobile devices.
// This also causes canvas to be cleared.
function resizeCanvas() {
    // When zoomed out to less than 100%, for some very strange reason,
    // some browsers report devicePixelRatio as less than 1
    // and only part of the canvas is cleared then.
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext("2d").scale(ratio, ratio);
}

window.onresize = resizeCanvas;
resizeCanvas();

var signaturePad = new SignaturePad(canvas, {
    backgroundColor: 'rgb(255, 255, 255)',
});

document.getElementById('headroom-clear-pad').addEventListener('click', function (e) {
    e.preventDefault();
    signaturePad.clear();
});

jQuery(function ($) {

    var headroom_consent_form = {

        init: function () {
            this.cacheDOM();
            this.eventHandlers();
        },
        cacheDOM: function () {
            this.consentForm = $('#headroom-consent-form');
            this.dateOfBirth = $("#headroom-consent-dob");

            if (this.dateOfBirth.length > 0) {
                var minDate = new Date();
                minDate.setFullYear(minDate.getFullYear() - 13);
                this.dateOfBirth.datepicker({
                    maxDate: minDate,
                    changeMonth: true,
                    changeYear: true,
                    yearRange: "1920:+nn",
                    dateFormat: 'dd/mm/yy'
                }).mask('99/99/9999');
            }
        },
        eventHandlers: function () {
            this.consentForm.on('submit', this.submitConsent.bind(this));
        },
        submitConsent: function (e) {
            e.preventDefault();
            var name = $('#headroom-consent-name');
            var email = $('#headroom-consent-email');
            var required_field_txt = 'This field is required';
            if (signaturePad.isEmpty()) {
                $('.headroom-consent-signature-pad').css('border-color', 'red');
                if (!$('.sig-error').length) {
                    $('.headroom-consent-signature-wrapper').after('<p class="sig-error" style="color:red;margin:5px 0;">' + required_field_txt + '</p>');
                }
            } else {
                $('.headroom-consent-signature-pad').css('border-color', '#CCC');
                $('.sig-error').remove();
            }

            if (name.val() === "") {
                if (!$('.name-error').length) {
                    name.css('border-color', 'red').focus().after('<p class="name-error" style="color:red;margin:5px 0;">' + required_field_txt + '</p>');
                }
            } else {
                name.css('border-color', '#CCC');
                $('.name-error').remove();
            }

            if (email.val() === "") {
                if (!$('.email-error').length) {
                    email.css('border-color', 'red').focus().after('<p class="email-error" style="color:red;margin:5px 0;">' + required_field_txt + '</p>');
                }
            } else {
                email.css('border-color', '#CCC');
                $('.email-error').remove();
            }

            if (this.dateOfBirth.val() === "") {
                if (!$('.dob-error').length) {
                    this.dateOfBirth.css('border-color', 'red').focus().after('<p class="dob-error" style="color:red;margin:5px 0;">' + required_field_txt + '</p>');
                }
            } else if (!this.isValidDate(this.dateOfBirth.val())) {
                if (!$('.dob-error').length) {
                    this.dateOfBirth.css('border-color', 'red').focus().after('<p class="dob-error" style="color:red;margin:5px 0;">Please enter a valid date.</p>');
                }
            } else {
                this.dateOfBirth.css('border-color', '#CCC');
                $('.dob-error').remove();
            }

            if (signaturePad.isEmpty() || name.val() === "" || email.val() === "" || this.dateOfBirth.val() === "" || !this.isValidDate(this.dateOfBirth.val())) {
                return false;
            }

            var image = signaturePad.toDataURL('image/png');
            $(e.currentTarget).find('.consent-submit-btn').html('Submitting.. Please wait..');
            var data = $(e.currentTarget).serialize() + '&action=save_consent_form&img=' + image;
            $.post(BooklyL10n.ajaxurl, data).done(function (response) {
                if (response.success) {
                    $('.headroom-consent-warpper').html(response.data.msg);
                    window.location.href = response.data.redirect;
                } else {
                    $('.headroom-consent-warpper').html(response.data);
                }
            });
        },
        isValidDate: function (date) {
            var matches = /^(\d+)[-\/](\d+)[-\/](\d+)$/.exec(date);
            if (matches == null) return false;
            var d = matches[1];
            var m = matches[2];
            var y = matches[3];
            if (y > 2100 || y < 1900) return false;
            var composedDate = new Date(y + '/' + m + '/' + d);
            return composedDate.getDate() == d && composedDate.getMonth() + 1 == m && composedDate.getFullYear() == y;
        }
    };

    headroom_consent_form.init();
});
