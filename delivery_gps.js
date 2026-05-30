/**
 * delivery_gps.js  (v2 — with Force GPS support)
 * ─────────────────────────────────────────────────
 * Include on the delivery person's dashboard page.
 *
 * Required globals (set before this script):
 *   DP_PHONE    — delivery person's PhoneNumber
 *   DP_PASSWORD — their password
 *
 * Features:
 *  - Sends GPS location to update_location.php every 20 seconds
 *  - Polls check_gps_request.php every 5 seconds
 *  - Shows a MANDATORY FULLSCREEN MODAL if admin forces GPS
 *  - Modal cannot be dismissed — only GPS allow button works
 *  - Once GPS is activated, clears the force flag on server
 */

(function () {
  const SEND_INTERVAL_MS  = 20000; // send location every 20s
  const POLL_INTERVAL_MS  = 5000;  // check for force request every 5s
  const UPDATE_ENDPOINT   = "update_location.php";
  const CHECK_ENDPOINT    = "check_gps_request.php";

  let lastLat = null, lastLng = null;
  let gpsModalVisible = false;
  let watchId = null;
  let gpsActivatedThisSession = false; // ← prevents re-show after activation

  // ── Build the mandatory GPS modal (injected once into DOM) ────────────────
  function buildModal() {
    if (document.getElementById('gps-force-modal')) return;

    const modal = document.createElement('div');
    modal.id = 'gps-force-modal';
    modal.style.cssText = `
      position:fixed;inset:0;z-index:99999;
      background:rgba(0,0,0,.85);backdrop-filter:blur(6px);
      display:none;align-items:center;justify-content:center;
      font-family:Inter,sans-serif;
    `;

    modal.innerHTML = `
      <style>
        @keyframes gps-pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
        @keyframes gps-ring{0%{transform:scale(1);opacity:.7}100%{transform:scale(2);opacity:0}}
        #gps-force-modal .gps-icon-wrap{position:relative;width:80px;height:80px;margin:0 auto 20px;}
        #gps-force-modal .gps-icon-bg{
          width:80px;height:80px;border-radius:50%;background:#ba1a1a;
          display:flex;align-items:center;justify-content:center;
          animation:gps-pulse 1.5s ease-in-out infinite;position:relative;z-index:2;
        }
        #gps-force-modal .gps-ring{
          position:absolute;inset:0;border-radius:50%;border:3px solid #ba1a1a;
          animation:gps-ring 1.5s ease-out infinite;
        }
        #gps-force-modal .gps-ring2{animation-delay:.5s;}
        #gps-force-modal .activate-btn{
          width:100%;padding:16px;border-radius:14px;border:none;cursor:pointer;
          background:linear-gradient(135deg,#005ea4,#0077ce);
          color:white;font-size:16px;font-weight:800;letter-spacing:.03em;
          display:flex;align-items:center;justify-content:center;gap:10px;
          transition:transform .15s,box-shadow .15s;
          box-shadow:0 4px 20px rgba(0,94,164,.5);
        }
        #gps-force-modal .activate-btn:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(0,94,164,.6);}
        #gps-force-modal .activate-btn:active{transform:scale(.97);}
        #gps-force-modal .status-line{
          margin-top:14px;font-size:12px;font-weight:700;color:#aaa;text-align:center;min-height:18px;
        }
      </style>

      <div style="background:white;border-radius:24px;padding:36px 32px;max-width:380px;width:90%;
                  text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5);">

        <!-- Pulsing GPS icon -->
        <div class="gps-icon-wrap">
          <div class="gps-ring"></div>
          <div class="gps-ring gps-ring2"></div>
          <div class="gps-icon-bg">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="white">
              <path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"/>
            </svg>
          </div>
        </div>

        <!-- Title -->
        <h2 style="font-size:20px;font-weight:800;color:#191c1d;margin:0 0 8px;font-family:Manrope,sans-serif;">
          GPS Activation Required
        </h2>
        <p id="gps-modal-subtitle" style="font-size:13px;color:#707783;margin:0 0 24px;line-height:1.5;">
          Your manager has requested your live location.<br/>
          You must enable GPS to continue working.
        </p>

        <!-- Warning box -->
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:12px 14px;margin-bottom:24px;text-align:left;">
          <p style="margin:0;font-size:12px;font-weight:700;color:#856404;">
            ⚠️ This window cannot be closed until GPS is activated.
          </p>
        </div>

        <!-- Activate button -->
        <button class="activate-btn" id="gps-activate-btn" onclick="activateGPS()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
            <path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06z"/>
          </svg>
          Enable GPS Now
        </button>

        <p class="status-line" id="gps-modal-status"></p>

        <!-- Small note -->
        <p style="margin-top:16px;font-size:11px;color:#aaa;">
          Your location is only shared while you are on duty.<br/>
          It is used for order tracking purposes only.
        </p>
      </div>
    `;

    // Block all attempts to dismiss (Escape key, back button)
    modal.addEventListener('click', e => e.stopPropagation());
    document.addEventListener('keydown', e => {
      if (gpsModalVisible && (e.key === 'Escape' || e.key === 'Backspace')) e.preventDefault();
    });

    document.body.appendChild(modal);
  }

  function showModal(adminName) {
    buildModal();
    gpsModalVisible = true;
    const modal = document.getElementById('gps-force-modal');
    modal.style.display = 'flex';
    if (adminName) {
      document.getElementById('gps-modal-subtitle').innerHTML =
        `<strong>${adminName}</strong> has requested your live location.<br/>You must enable GPS to continue.`;
    }
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
  }

  function hideModal() {
    const modal = document.getElementById('gps-force-modal');
    if (modal) {
      modal.style.display = 'none';
      modal.style.visibility = 'hidden';
      modal.style.opacity = '0';
      modal.style.pointerEvents = 'none';
      // Remove from DOM entirely after fade
      setTimeout(() => { if (modal.parentNode) modal.parentNode.removeChild(modal); }, 500);
    }
    gpsModalVisible = false;
    document.body.style.overflow = '';
  }

  // ── GPS activation handler (called by modal button) ───────────────────────
  window.activateGPS = function () {
    const btn    = document.getElementById('gps-activate-btn');
    const status = document.getElementById('gps-modal-status');

    if (!('geolocation' in navigator)) {
      status.textContent = '❌ GPS not supported on this device.';
      status.style.color = '#ba1a1a';
      return;
    }

    btn.textContent = '⏳ Requesting GPS permission…';
    btn.disabled = true;
    status.textContent = 'Please click "Allow" in the browser prompt above.';
    status.style.color = '#707783';

    navigator.geolocation.getCurrentPosition(
      pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;

        // ── Close the modal IMMEDIATELY — GPS coords obtained ──────────────
        // We don't wait for the server. The location will be sent regardless.
        gpsActivatedThisSession = true;
        lastLat = lat; lastLng = lng;
        updateStatusBadge(true, lat, lng);

        status.textContent = '✅ GPS activated!';
        status.style.color = '#186a22';
        btn.textContent = '✅ GPS Active';

        setTimeout(() => hideModal(), 800);

        // Send location + clear force flag (fire and forget)
        fetch(UPDATE_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            phone: DP_PHONE, password: DP_PASSWORD,
            lat, lng, status: 1, clear_force: true,
          }),
        })
        .then(r => r.json())
        .catch(() => {
          // Even if server fails, GPS is activated — retry will pick it up
        });

        startWatching(); // keep GPS active continuously
      },
      err => {
        btn.disabled = false;
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06z"/></svg> Enable GPS Now';

        if (err.code === 1) {
          status.innerHTML = '❌ Permission denied.<br/>Go to browser settings → Allow location for this site.';
        } else {
          status.textContent = '❌ ' + err.message;
        }
        status.style.color = '#ba1a1a';
      },
      { enableHighAccuracy: true, timeout: 15000 }
    );
  };

  // ── Poll server for force request every 5s ────────────────────────────────
  function pollForceRequest() {
    // Never re-show the modal if GPS was already activated in this session
    if (gpsActivatedThisSession) return;

    fetch(CHECK_ENDPOINT)
      .then(r => r.json())
      .then(d => {
        if (d.forced && !gpsModalVisible) showModal(d.admin);
      })
      .catch(() => {});
  }

  // ── Regular GPS sending ───────────────────────────────────────────────────
  function sendLocation(lat, lng) {
    if (lat === lastLat && lng === lastLng) return;
    lastLat = lat; lastLng = lng;
    fetch(UPDATE_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: DP_PHONE, password: DP_PASSWORD, lat, lng, status: 1 }),
    })
    .then(r => r.json())
    .then(d => { if (d.success) updateStatusBadge(true, lat, lng); })
    .catch(() => {});
  }

  function startWatching() {
    if (!('geolocation' in navigator) || watchId) return;
    const opts = { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 };
    watchId = navigator.geolocation.watchPosition(
      pos => sendLocation(pos.coords.latitude, pos.coords.longitude),
      () => {},
      opts
    );
    setInterval(() => {
      navigator.geolocation.getCurrentPosition(
        pos => sendLocation(pos.coords.latitude, pos.coords.longitude),
        () => {}, opts
      );
    }, SEND_INTERVAL_MS);
  }

  function updateStatusBadge(online, lat, lng) {
    const badge = document.getElementById('gps-status-badge');
    if (!badge) return;
    badge.innerHTML = online
      ? `<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#186a22;">
           <span style="width:8px;height:8px;border-radius:50%;background:#186a22;
             animation:pulse-dot 2s ease-in-out infinite;display:inline-block;"></span>
           GPS Active · ${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}
         </span>`
      : `<span style="font-size:12px;font-weight:700;color:#aaa;">📍 GPS Inactive</span>`;
  }

  function goOffline() {
    navigator.sendBeacon(UPDATE_ENDPOINT,
      JSON.stringify({ phone: DP_PHONE, password: DP_PASSWORD, lat: lastLat||0, lng: lastLng||0, status: 0 })
    );
  }

  // ── Boot ──────────────────────────────────────────────────────────────────
  window.addEventListener('beforeunload', goOffline);
  setInterval(pollForceRequest, POLL_INTERVAL_MS);
  pollForceRequest(); // check immediately on page load
  startWatching();    // also start normal GPS sending

})();