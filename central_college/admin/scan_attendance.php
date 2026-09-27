<?php
// admin/scan_attendance.php - QR Code Scanner (FIXED)
session_start();
if (!isset($_SESSION['principal_id']) && !isset($_SESSION['teacher_id'])) {
    header("Location: ../login.php");
    exit();
}
$user_name = $_SESSION['principal_name'] ?? $_SESSION['teacher_name'] ?? 'User';
$user_role = isset($_SESSION['principal_id']) ? 'Principal' : 'Teacher';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>📸 QR Attendance Scanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',system-ui,sans-serif; }
        body { background:#0b0f1a; color:#f1f5f9; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px; }
        .scanner-container { max-width:480px; width:100%; background:rgba(17,24,39,0.9); backdrop-filter:blur(10px); border-radius:24px; padding:28px 24px; box-shadow:0 20px 60px rgba(0,0,0,0.7); border:1px solid rgba(255,255,255,0.06); animation:fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .header { text-align:center; margin-bottom:20px; }
        .header h1 { font-size:1.6rem; font-weight:700; background:linear-gradient(135deg,#818cf8,#8b5cf6); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .header .sub { color:#94a3b8; font-size:0.85rem; margin-top:4px; }
        .user-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(99,102,241,0.15); padding:6px 16px; border-radius:50px; font-size:0.75rem; color:#a78bfa; border:1px solid rgba(99,102,241,0.2); margin-top:8px; }
        #reader { width:100%; border-radius:16px; overflow:hidden; background:#000; min-height:300px; position:relative; }
        #reader video { border-radius:16px; width:100%; height:auto; }
        #reader__scan_region { border:2px dashed rgba(99,102,241,0.4) !important; border-radius:16px !important; background:rgba(0,0,0,0.3) !important; }
        .status-box { margin-top:18px; padding:14px 18px; border-radius:12px; background:rgba(15,23,42,0.8); min-height:56px; display:flex; align-items:center; justify-content:center; gap:12px; font-weight:500; font-size:0.95rem; border:1px solid rgba(255,255,255,0.05); transition:all 0.3s; }
        .status-box.idle { color:#94a3b8; }
        .status-box.scanning { color:#60a5fa; border-color:rgba(96,165,250,0.3); }
        .status-box.success { color:#34d399; border-color:rgba(52,211,153,0.3); background:rgba(16,185,129,0.08); }
        .status-box.error { color:#f87171; border-color:rgba(248,113,113,0.3); background:rgba(239,68,68,0.08); }
        .btn-group { display:flex; gap:10px; margin-top:16px; flex-wrap:wrap; }
        .btn { flex:1; padding:12px 20px; border:none; border-radius:12px; font-weight:600; font-size:0.9rem; cursor:pointer; transition:all 0.3s; display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; min-width:60px; }
        .btn-primary { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; }
        .btn-secondary { background:rgba(255,255,255,0.06); color:#cbd5e1; border:1px solid rgba(255,255,255,0.08); }
        .btn-danger { background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.2); }
        .sound-toggle { margin-top:12px; display:flex; align-items:center; justify-content:center; gap:8px; font-size:0.75rem; color:#64748b; }
        .sound-toggle input[type="checkbox"] { accent-color:#6366f1; width:16px; height:16px; cursor:pointer; }
        @media (max-width:500px) { .scanner-container { padding:16px; } .header h1 { font-size:1.3rem; } #reader { min-height:220px; } .btn { font-size:0.8rem; padding:10px 14px; min-width:50px; } }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="header">
            <h1><i class="fa-solid fa-qrcode" style="margin-right:8px;"></i> QR Scanner</h1>
            <div class="sub">Point your camera at the student's QR code</div>
            <div class="user-badge"><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($user_name) ?> (<?= htmlspecialchars($user_role) ?>)</div>
        </div>
        <div id="reader"></div>
        <div id="status" class="status-box idle"><span class="icon"><i class="fa-solid fa-camera"></i></span><span>Waiting for scan...</span></div>
        <div class="btn-group">
            <button id="restartBtn" class="btn btn-secondary" onclick="restartScanner()"><i class="fa-solid fa-rotate"></i> Restart</button>
            <button id="flashBtn" class="btn btn-secondary" onclick="toggleFlash()"><i class="fa-solid fa-bolt"></i> Flash</button>
            <a href="dashboard.php" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Exit</a>
        </div>
        <div class="sound-toggle"><input type="checkbox" id="soundCheck" checked><label for="soundCheck"><i class="fa-solid fa-volume-high"></i> Sound alert</label></div>
    </div>

    <script>
        let html5QrCode = null, isScanning = false, flashOn = false, soundEnabled = true;
        const statusEl = document.getElementById('status');

        function playBeep(success = true) {
            if (!soundEnabled) return;
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator(), gain = audioCtx.createGain();
                osc.connect(gain); gain.connect(audioCtx.destination);
                osc.type = 'sine'; osc.frequency.value = success ? 800 : 400;
                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                osc.start(); osc.stop(audioCtx.currentTime + (success ? 0.3 : 0.5));
            } catch(e) {}
        }

        function setStatus(type, message, icon = null) {
            statusEl.className = 'status-box ' + type;
            const icons = { idle:'fa-solid fa-camera', scanning:'fa-solid fa-spinner fa-pulse', success:'fa-solid fa-check-circle', error:'fa-solid fa-times-circle' };
            const iconClass = icon || icons[type] || 'fa-solid fa-circle-info';
            statusEl.innerHTML = `<span class="icon"><i class="${iconClass}"></i></span><span>${message}</span>`;
        }

        // ============================================================
        // SCAN SUCCESS HANDLER (handles both JSON and ATTEND_ formats)
        // ============================================================
        function onScanSuccess(decodedText, decodedResult) {
            if (isScanning) return;
            isScanning = true;
            if (html5QrCode) html5QrCode.stop().catch(()=>{});
            setStatus('scanning', 'Processing QR code...', 'fa-solid fa-spinner fa-pulse');

            // Try JSON (Base64) format first
            try {
                const decodedString = atob(decodedText);
                const studentData = JSON.parse(decodedString);

                if (studentData.id && studentData.name) {
                    setStatus('scanning', 
                        `🎓 ${studentData.name} | 📚 ${studentData.class} | 🆔 ${studentData.reg_no}`, 
                        'fa-solid fa-user-check'
                    );
                    markAttendance(studentData.id);
                    return;
                }
            } catch (e) {
                // Not JSON — try ATTEND_ format
            }

            // Fallback: ATTEND_ prefix format
            if (decodedText && decodedText.startsWith("ATTEND_")) {
                try {
                    const encodedId = decodedText.replace("ATTEND_", "");
                    const studentId = atob(encodedId);
                    if (studentId && !isNaN(studentId) && studentId > 0) {
                        markAttendance(studentId);
                        return;
                    }
                } catch(e) {}
            }

            // Unknown format
            setStatus('error', 'Invalid QR Code! Please scan a valid student ID.');
            playBeep(false);
            setTimeout(()=>{ isScanning = false; restartScanner(); }, 2500);
        }

        // ============================================================
        // MARK ATTENDANCE via AJAX
        // ============================================================
        function markAttendance(studentId) {
            const csrf = '<?= $_SESSION['csrf_token'] ?>';
            fetch('mark_attendance_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'student_id=' + encodeURIComponent(studentId) + '&csrf_token=' + encodeURIComponent(csrf)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setStatus('success', data.message + ' ✅');
                    playBeep(true);
                } else {
                    setStatus('error', data.message || 'Failed to mark attendance.');
                    playBeep(false);
                }
            })
            .catch(err => {
                setStatus('error', 'Network error! Please check connection.');
                playBeep(false);
            })
            .finally(() => {
                setTimeout(() => { isScanning = false; restartScanner(); }, 3000);
            });
        }

        // ============================================================
        // START SCANNER
        // ============================================================
        function startScanner() {
            if (html5QrCode) {
                try { html5QrCode.clear(); } catch(e) {}
                html5QrCode = null;
            }

            setStatus('idle', 'Starting camera...');

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setStatus('error', 'Your browser does not support camera access.');
                return;
            }

            try {
                html5QrCode = new Html5Qrcode("reader");

                const config = {
                    fps: 15,
                    qrbox: { width: 240, height: 240 },
                    aspectRatio: 1.0,
                };

                html5QrCode.start(
                    { facingMode: "environment" },
                    config,
                    onScanSuccess,
                    (errorMessage) => {
                        // Ignore frequent scanner errors
                    }
                ).then(() => {
                    setStatus('scanning', 'Scanning... Point camera at QR code');
                }).catch(err => {
                    console.error('Scanner start error:', err);
                    setStatus('error', 'Camera not available. Trying front camera...');
                    setTimeout(() => {
                        if (html5QrCode) {
                            html5QrCode.start(
                                { facingMode: "user" },
                                config,
                                onScanSuccess,
                                (e) => {}
                            ).then(() => {
                                setStatus('scanning', 'Scanning... Point camera at QR code');
                            }).catch(e2 => {
                                setStatus('error', 'Camera not available. Please use a device with a camera.');
                            });
                        }
                    }, 1000);
                });
            } catch (e) {
                setStatus('error', 'Error starting camera: ' + e.message);
            }
        }

        // ============================================================
        // RESTART SCANNER
        // ============================================================
        function restartScanner() {
            isScanning = false;
            if (html5QrCode) {
                try { html5QrCode.clear(); } catch(e) {}
                html5QrCode = null;
            }
            setTimeout(startScanner, 300);
        }

        // ============================================================
        // FLASH TOGGLE
        // ============================================================
        function toggleFlash() {
            try {
                const video = document.querySelector('#reader video');
                if (video && video.srcObject) {
                    const track = video.srcObject.getVideoTracks()[0];
                    if (track && track.getCapabilities && track.getCapabilities().torch) {
                        flashOn = !flashOn;
                        track.applyConstraints({ advanced: [{ torch: flashOn }] });
                        document.getElementById('flashBtn').innerHTML = flashOn ? '<i class="fa-solid fa-bolt"></i> On' : '<i class="fa-solid fa-bolt"></i> Flash';
                    } else {
                        setStatus('error', 'Flash not available on this camera.');
                    }
                }
            } catch(e) {
                setStatus('error', 'Flash not supported.');
            }
        }

        // ============================================================
        // SOUND TOGGLE
        // ============================================================
        document.getElementById('soundCheck').addEventListener('change', function() {
            soundEnabled = this.checked;
        });

        // ============================================================
        // KEYBOARD SHORTCUTS
        // ============================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'r' || e.key === 'R') restartScanner();
            if (e.key === 'f' || e.key === 'F') toggleFlash();
            if (e.key === 'Escape') window.location.href = 'dashboard.php';
        });

        // ============================================================
        // INIT
        // ============================================================
        window.onload = function() {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ video: true })
                    .then(function(stream) {
                        stream.getTracks().forEach(track => track.stop());
                        startScanner();
                    })
                    .catch(function(err) {
                        setStatus('error', 'Camera permission denied! Please allow camera access.');
                        console.error('Camera error:', err);
                    });
            } else {
                setStatus('error', 'Camera not supported on this browser.');
            }
        };

        window.addEventListener('beforeunload', function() {
            if (html5QrCode) {
                try { html5QrCode.stop(); html5QrCode.clear(); } catch(e) {}
            }
        });

        console.log('📸 QR Attendance Scanner loaded');
    </script>
</body>
</html>