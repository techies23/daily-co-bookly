function showEvent(e) {
    console.log('callFrame event', e)
}

function setup() {
}

async function getRoom() {
    room = {url: decodeURIComponent(dailyco.domain_uri + 'hello')};

    kbs = 800;
    trackConstraints = null;

    updateRoomInfoDisplay();
    setInterval(updateNetworkInfoDisplay, 5000);
}

async function createFrame() {
    //
    // ask the daily-js library to create an iframe inside the
    // 'call-frame-container' div
    //
    callFrame = window.DailyIframe.createFrame(document.getElementById('call-frame-container'));

    callFrame.on('loading', (e) => {
        showEvent(e);
        buttonDisable('join-meeting');
    });

    callFrame.on('loaded', showEvent)
        .on('started-camera', showEvent)
        .on('camera-error', showEvent)
        .on('joining-meeting', showEvent)
        .on('input-event', showEvent)
        .on('error', showEvent);

    callFrame.on('joined-meeting', (e) => {
        showEvent(e);
        buttonEnable('leave-meeting', 'start-recording', 'bandwidth-buttons');
    });
    callFrame.on('left-meeting', (e) => {
        showEvent(e);
        buttonDisable('leave-meeting', 'start-recording', 'stop-recording',
            'bandwidth-buttons');
        buttonEnable('join-meeting');
    });

    callFrame.on('app-message', (e) => {
        showEvent(e);
        kbs = e.data.setVideoBandwidthCap || 800;
        trackConstraints = null;
        if (kbs <= 32) {
            trackConstraints = {width: 160, height: 90}
        } else if (kbs <= 128) {
            trackConstraints = {width: 320, height: 180}
        } else if (kbs <= 384) {
            trackConstraints = {width: 640, height: 360}
        }
        console.log('setting send bandwidth to', kbs,
            'kbs and applying camera track constraints',
            trackConstraints);
        callFrame.setBandwidth({kbs, trackConstraints});
        updateRoomInfoDisplay();
    });

    callFrame.on('participant-joined', updateParticipantInfoDisplay)
        .on('participant-updated', updateParticipantInfoDisplay)
        .on('participant-left', updateParticipantInfoDisplay);
}

async function createFrameAndRoom() {
    await getRoom();
    await createFrame();
    callFrame.join({url: room.url});
}

function updateRoomInfoDisplay() {
    let roomInfo = document.getElementById('meeting-room-info');
    roomInfo.innerHTML = `
    <div><b>Headroom Test Video Room</b>:</div>
    <div>
     <div>Displaying local camera below:</div>
     <div>Outgoing bandwidth soft cap: ${kbs} kb/s</div>
     <div>camera resolution constraints:
       ${trackConstraints ?
        trackConstraints.width + 'x' + trackConstraints.height :
        '1280x720'}
    </div>
  `;
    if (!window.expiresUpdate) {
        window.expiresUpdate = setInterval(() => {
            let exp = (room && room.config && room.config.exp);
            if (exp) {
                document.getElementById('expires-countdown').innerHTML = `
           room expires in 
             ${Math.floor((new Date(exp * 1000) - Date.now()) / 1000)}
           seconds
         `;
            }
        }, 1000);
    }
}

function updateParticipantInfoDisplay(e) {
    showEvent(e);
    // todo
}

async function updateNetworkInfoDisplay() {
    let infoEl = document.getElementById('network-info'),
        statsInfo = await callFrame.getNetworkStats();
    infoEl.innerHTML = `
    <div><b>network stats</b></div>
    <div>
      <div>
        video send:
        ${Math.floor(statsInfo.stats.latest.videoSendBitsPerSecond / 1000)} kb/s
      </div>
      <div>
        video recv:
        ${Math.floor(statsInfo.stats.latest.videoRecvBitsPerSecond / 1000)} kb/s
      <div>
        worst send packet loss:
        ${Math.floor(statsInfo.stats.worstVideoSendPacketLoss * 100)}%</div>
      <div>worst recv packet loss:
        ${Math.floor(statsInfo.stats.worstVideoRecvPacketLoss * 100)}%</div>
    </div>
  `;
}

//
// UI utility functions
//
function buttonEnable(...args) {
    args.forEach((id) => {
        let el = document.getElementById(id);
        if (el) {
            el.classList.remove('disabled');
        }
    });
}

function buttonDisable(...args) {
    args.forEach((id) => {
        let el = document.getElementById(id);
        if (el) {
            el.classList.add('disabled');
        }
    });
}

document.addEventListener("load", setup(), createFrameAndRoom());