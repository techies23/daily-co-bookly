jQuery(function ($) {

    var DailyCoBookly = {

        init: function () {
            this.cacheDOM();
            this.loadHandlers();
            this.eventListeners();
        },

        cacheDOM: function () {
            this.dtable = $('.datatable-render');
            this.dpicker = $('.datepicker-render');

            this.wpUserId = $('.daily-co-admin-wp-user-flush-consent');
            this.flushbtn = $('.daily-co-admin-flush-btn');
        },

        loadHandlers: function () {
            if ($(this.dtable).length > 0) {
                $(this.dtable).DataTable();
            }

            if ($(this.dpicker).length > 0) {
                $(this.dpicker).datetimepicker({
                    minDate: '-1',
                    mask: true,
                    step: 30
                });
            }
        },

        eventListeners: function () {
            $('.delete-room').on('click', this.deleteRoom.bind(this));
            this.flushbtn.on('click', this.flushCache.bind(this));
            this.wpUserId.on('change', this.addToFlushBtn.bind(this));
        },

        deleteRoom: function (e) {
            e.preventDefault();
            var con = confirm("Are you sure you want to delete this ?");
            var room_name = $(e.currentTarget).data('room');
            if (con) {
                $(e.currentTarget).html('Loading...');
                $.post(daily.ajax_uri, {name: room_name, action: 'delete_room'}).done(function (response) {
                    if (response.deleted) {
                        $(".updated").show().html('<p>Room ' + room_name + ' Deleted Successfully !</p>');
                        location.reload();
                    } else if (response.error) {
                        $(".error").show().html('<p>' + response.info + '</p>');
                    }

                    $(e.currentTarget).html('Delete');
                });
            }

            return;
        },

        addToFlushBtn: function (e) {
            e.preventDefault();
            var user_id = $(e.currentTarget).val();
            $('.daily-co-admin-flush-btn').attr('data-user', user_id);
        },

        flushCache: function (e) {
            e.preventDefault();
            var user_id = $(e.currentTarget).data('user');
            var therapist_id = $(e.currentTarget).data('therapist');
            var con = confirm("Are you sure you want to flush consent for this user ?");
            if (con) {
                $('.bookly-flush-cache-tbl-body').html('<tr><td colspan="4">Flushing.. Please wait...</td></tr>');
                $.post(ajaxurl, {action: 'admin_flush_consent', user: user_id, therapist: therapist_id}).done(function (response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert(response.data);
                        location.reload();
                    }
                });
            }
        }

    };

    DailyCoBookly.init();
});