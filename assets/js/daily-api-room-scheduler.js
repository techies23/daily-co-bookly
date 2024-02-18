let token = dailyco.token;
let domain = dailyco.domain_uri;
let room = dailyco.room;
let join = room.url + '?t=' + token;

function showEvent(e) {
    var el = document.getElementById('daily-co-error-output');
    if (e.action === "error") {
        var img = dailyco.plugin_path + 'assets/images/warning.png';
        el.innerHTML = '<img src="' + img + '" alt="Warning">';
        el.innerHTML += '<p>' + e.errorMsg + '</p>';
    }

    // console.log('video call event -->', e);
}

function leftEvent(e) {
    var el = document.getElementById('daily-co-output-room');

    if (e.action === "left-meeting") {
        jQuery('#daily-co-expiry-countdown').remove();
    }
}

function updateRoomInfoDisplay() {
    let ending_time_dialog = false;
    if (!window.expiresUpdate) {
        document.getElementById("daily-co-expiry-countdown").style.display = "block";
        let exp = (room && room.config && room.config.exp);
        if (exp) {
            let timestamp = exp;

            let minute = 60,
                hour = 60 * 60,
                day = 60 * 60 * 24;

            window.expiresUpdate = setInterval(function () {
                let currentTime = new Date().getTime() / 1000;
                let timeRemaining = timestamp - currentTime;
                let dayFloor = Math.floor(timeRemaining / day);
                let hourFloor = Math.floor((timeRemaining - dayFloor * day) / hour);
                let minuteFloor = Math.floor((timeRemaining - dayFloor * day - hourFloor * hour) / minute);
                let secondFloor = Math.floor((timeRemaining - dayFloor * day - hourFloor * hour - minuteFloor * minute));
                let countdownCompleted = "Session has Completed !";

                if (hourFloor === 0 && minuteFloor <= 4) {
                    jQuery('#daily-co-expiry-countdown').css({
                        'background': 'red',
                        'color': '#fff'
                    });

                    if (!ending_time_dialog) {
                        alert('Please start concluding your session as the room will close in five minutes.');
                        ending_time_dialog = true;
                    }
                }

                if (secondFloor <= 0 && minuteFloor <= 0 && hourFloor <= 0) {
                    clearInterval(window.expiresUpdate);
                    document.getElementById('daily-co-expiry-countdown').innerHTML = countdownCompleted;
                } else {
                    if (timestamp > currentTime) {
                        if (dayFloor > 0) {
                            document.getElementById('daily-co-expiry-countdown').innerHTML = 'This room is going to expire in ' + dayFloor + " days " + hourFloor + " hours " + minuteFloor + " minutes " + secondFloor + " seconds ";
                        } else if (hourFloor > 0) {
                            document.getElementById('daily-co-expiry-countdown').innerHTML = 'This room is going to expire in ' + hourFloor + " hours " + minuteFloor + " minutes " + secondFloor + " seconds ";
                        } else if (minuteFloor > 0) {
                            document.getElementById('daily-co-expiry-countdown').innerHTML = 'This room is going to expire in ' + minuteFloor + " minutes " + secondFloor + " seconds ";
                        } else {
                            document.getElementById('daily-co-expiry-countdown').innerHTML = 'This room is going to expire in ' + secondFloor + " seconds ";
                        }
                    }
                }
            }, 1000);
        }
    }
}

async function createFrame() {
    callFrame = window.DailyIframe.createFrame({
        showLeaveButton: true,
        iframeStyle: {
            position: 'relative',
            top: 0,
            left: 0,
            width: '100%',
            height: '100%'
        }
    });

    callFrame.on('loading', showEvent)
        .on('loaded', showEvent)
        .on('started-camera', showEvent)
        .on('camera-error', showEvent)
        .on('joining-meeting', showEvent)
        .on('left-meeting', leftEvent)
        .on('participant-joined', showEvent)
        .on('participant-updated', showEvent)
        .on('participant-left', showEvent)
        .on('recording-started', showEvent)
        .on('recording-stopped', showEvent)
        .on('recording-stats', showEvent)
        .on('recording-error', showEvent)
        .on('recording-upload-completed', showEvent)
        .on('input-event', showEvent)
        .on('error', showEvent);

    callFrame.on('joining-meeting', (e) => {
        showEvent(e);
        updateRoomInfoDisplay();
    });
}

async function createFrameAndRoom() {
    await createFrame();
    callFrame.join({url: join});
}


document.addEventListener("load", createFrameAndRoom());