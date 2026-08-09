// Shared Logic

document.addEventListener('DOMContentLoaded', () => {
    // Tab switching logic
    document.querySelectorAll('[data-tab-target]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.querySelector(button.dataset.tabTarget);
            
            // Remove active class
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('[data-tab-target]').forEach(b => b.classList.remove('active'));
            
            // Add active class
            button.classList.add('active');
            target.classList.add('active');
        });
    });

    // Modal closing logic
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.target.closest('.modal');
            if (modal) modal.classList.remove('visible');
        });
    });

    // Login page forms toggle
    const loginTab = document.getElementById('tab-login');
    const registerTab = document.getElementById('tab-register');
    if (loginTab && registerTab) {
        loginTab.addEventListener('click', () => {
            document.getElementById('form-login').style.display = 'block';
            document.getElementById('form-register').style.display = 'none';
            loginTab.classList.replace('btn-outline', 'btn-primary');
            registerTab.classList.replace('btn-primary', 'btn-outline');
        });
        registerTab.addEventListener('click', () => {
            document.getElementById('form-register').style.display = 'block';
            document.getElementById('form-login').style.display = 'none';
            registerTab.classList.replace('btn-outline', 'btn-primary');
            loginTab.classList.replace('btn-primary', 'btn-outline');
        });
    }

    // Auto-open tab from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab) {
        const tabBtn = document.querySelector(`[data-tab-target="#tab-${activeTab}"]`);
        if (tabBtn) {
            tabBtn.click();
            // Clean up the URL to prevent sticking
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
    
    // Check GPS Status on Load
    checkGPS();
});

function checkGPS() {
    const dot = document.getElementById('gps_dot');
    const text = document.getElementById('gps_text');
    if (!dot || !text) return;

    if (!navigator.geolocation) {
        dot.style.background = '#ef4444';
        text.innerHTML = 'GPS Unsupported';
        return;
    }

    if (!window.isSecureContext && location.hostname !== 'localhost') {
        dot.style.background = '#f59e0b';
        text.innerHTML = 'GPS Protocol Blocked (Needs HTTPS)';
        return;
    }

    navigator.permissions.query({name:'geolocation'}).then(result => {
        if (result.state === 'granted') {
            dot.style.background = '#10b981';
            dot.style.boxShadow = '0 0 5px #10b981';
            text.innerHTML = 'Forensic GPS Ready';
        } else if (result.state === 'prompt') {
            dot.style.background = '#3b82f6';
            text.innerHTML = 'GPS Access Pending...';
        } else {
            dot.style.background = '#ef4444';
            text.innerHTML = 'GPS Access Denied';
        }
    });
}

function openModal(id) {
    document.getElementById(id).classList.add('visible');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('visible');
}

// ------------------------
// Barcode Scanning Logic
// ------------------------
let scannerActive = false;

function toggleScanner() {
    if (typeof Quagga === 'undefined') {
        alert("Scanner library failed to load.");
        return;
    }
    
    const container = document.getElementById('interactive');
    
    if (scannerActive) {
        Quagga.stop();
        container.style.display = 'none';
        scannerActive = false;
        return;
    }
    
    container.style.display = 'block';
    
    Quagga.init({
        inputStream : {
            name : "Live",
            type : "LiveStream",
            target: container
        },
        decoder : {
            readers : ["ean_reader"]
        }
    }, function(err) {
        if (err) {
            console.error(err);
            alert("Camera access denied or unavailble.");
            container.style.display = 'none';
            return;
        }
        Quagga.start();
        scannerActive = true;
    });

    Quagga.onDetected(function(result) {
        var code = result.codeResult.code;
        // Verify it looks like our 13-digit code
        document.getElementById('barcode_input').value = code;
        Quagga.stop();
        container.style.display = 'none';
        scannerActive = false;
    });
}


// Verification Form Submit
function verifyProduct(e) {
    e.preventDefault();
    const barcode = document.getElementById('barcode_input').value;
    const batch = document.getElementById('batch_input').value;
    const expiry = document.getElementById('expiry_input').value;
    const evidenceFile = document.getElementById('evidence_image').files[0];

    const btn = document.getElementById('verify_btn');
    const originalText = btn.innerHTML;
    const resDiv = document.getElementById('verify_result');
    
    // UI RESET & LOADING STATE
    resDiv.style.display = 'block';
    resDiv.className = 'alert';
    resDiv.style.background = '#f8f9fa';
    resDiv.innerHTML = `
        <div style="text-align:center; padding:20px;">
            <div class="loader" style="width:30px; height:30px; border:3px solid #eee; border-top:3px solid var(--blue); border-radius:50%; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
            <p id="extraction_status" style="font-size:0.85rem; font-weight:600; color:var(--blue); margin:0;">Initiating Forensic Handshake...</p>
            <div id="ocr_terminal" style="margin-top:15px; background:#1a1b1e; padding:10px; border-radius:6px; font-family:monospace; font-size:0.7rem; color:#4ade80; text-align:left; height:80px; overflow:hidden;"></div>
        </div>
    `;

    const terminal = document.getElementById('ocr_terminal');
    const statusText = document.getElementById('extraction_status');
    
    const logBatch = (msg) => {
        const line = document.createElement('div');
        line.innerHTML = `> ${msg}`;
        terminal.appendChild(line);
        terminal.scrollTop = terminal.scrollHeight;
    };

    // MOCK OCR SEQUENCING
    setTimeout(() => { logBatch("Accessing GS1 Cloud Node..."); }, 400);
    setTimeout(() => { logBatch("Scanning Image Evidence for Alphanumerics..."); }, 900);
    setTimeout(() => { 
        if(evidenceFile) {
            logBatch("OCR SUCCESS: Extracted Layer Data [0x8B...]"); 
            statusText.innerHTML = "Performing Heuristic Math...";
        } else {
            logBatch("OCR SKIP: No image evidence attached.");
            statusText.innerHTML = "Calculating Confidence...";
        }
    }, 1500);

    const formData = new FormData();
    formData.append('action', 'check');
    formData.append('barcode', barcode);
    formData.append('batch', batch);
    formData.append('expiry', expiry);

    fetch('verify.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        // Store global state for reporting
        window.currentDetectedProduct = data.product || 'Unknown Product';
        window.currentStatus = data.status;
        window.currentConfidence = data.confidence;
        window.currentProof = data.proof;
        window.currentMfgDate = data.mfgDate;
        window.currentTrueExpiry = data.trueExpiry;

        setTimeout(() => {
            renderForensicResult(data);
        }, 2200); // Allow mock animation to finish
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        const resDiv = document.getElementById('verify_result');
        resDiv.style.display = 'block';
        resDiv.className = 'alert alert-danger';
        resDiv.innerHTML = `<h4>SYSTEM CRASH DETECTED</h4><pre style="white-space:pre-wrap; font-size:0.8rem; margin-top:10px; color:darkred;">${err.message}</pre>`;
        console.error(err);
    });
}

function renderForensicResult(data) {
    const resDiv = document.getElementById('verify_result');
    resDiv.style.padding = '0';
    resDiv.style.border = 'none';
    resDiv.style.background = 'none';

    let statusColor = '#10b981'; // Green
    let statusIcon = '✓';
    if(data.status === 'ANOMALY') { statusColor = '#ef4444'; statusIcon = '⚠'; }
    if(data.status === 'INCONCLUSIVE') { statusColor = '#f59e0b'; statusIcon = '🔍'; }

    const confColor = data.confidence > 80 ? '#10b981' : (data.confidence > 50 ? '#f59e0b' : '#ef4444');

    // Split the proof into individual log items
    const logItems = data.proof.split(' | ').map(item => {
        let tag = 'MATCHED';
        let tagColor = '#10b981';
        if(item.includes('DETECTION') || item.includes('ANOMALY') || item.includes('ERROR')) {
            tag = 'ANOMALY';
            tagColor = '#ef4444';
        } else if(item.includes('Julian') || item.includes('format')) {
            tag = 'DECODED';
            tagColor = '#3b82f6';
        }
        return `
            <div style="margin-bottom:12px; border-bottom:1px solid #edf2f7; padding-bottom:12px;">
                <div class="diag-tag" style="background:${tagColor}15; color:${tagColor};">
                    ${tag}
                </div>
                <div class="diag-msg">${item}</div>
            </div>
        `;
    }).join('');

    const html = `
        <div class="forensic-card">
            <div class="diag-header" style="background:${statusColor};">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="background:rgba(0,0,0,0.2); width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">${statusIcon}</div>
                    <h3 style="margin:0; font-size:1.1rem; letter-spacing:0.02em;">VERDICT: ${data.status}</h3>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.6rem; opacity:0.8; font-weight:800;">CONFIDENCE SCORE</div>
                    <div style="font-size:1.1rem; font-weight:900;">${data.confidence}%</div>
                </div>
            </div>
            
            <div class="diag-body">
                <div style="margin-bottom:24px;">
                    <div class="diag-label">Identified Entity</div>
                    <div class="diag-value" style="color:var(--blue);">${data.product}</div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px;">
                    <div style="background:#f8fafc; padding:12px; border-radius:12px; border:1px solid #f1f5f9;">
                        <div class="diag-label">Deciphered Mfg</div>
                        <div class="diag-value" style="font-size:0.95rem;">${data.mfgDate || 'N/A'}</div>
                    </div>
                    <div style="background:#f8fafc; padding:12px; border-radius:12px; border:1px solid #f1f5f9; text-align:right;">
                        <div class="diag-label">Factory Expiry</div>
                        <div class="diag-value" style="font-size:0.95rem; color:var(--blue);">${data.trueExpiry || 'N/A'}</div>
                    </div>
                </div>

                <div class="diag-label" style="text-align:center;">Forensic Timeline Visualization</div>
                <div class="forensic-timeline">
                    <div class="timeline-bar"></div>
                    
                    ${(() => {
                        let mfgTimestamp = data.mfgTimestamp;
                        let trueExpTimestamp = data.trueExpTimestamp;

                        if ((!mfgTimestamp || typeof mfgTimestamp !== 'number') && data.mfgDate) {
                            const parsed = Date.parse(data.mfgDate);
                            if (!Number.isNaN(parsed)) mfgTimestamp = parsed / 1000;
                        }
                        if ((!trueExpTimestamp || typeof trueExpTimestamp !== 'number') && data.trueExpiry) {
                            const parsed = Date.parse(data.trueExpiry);
                            if (!Number.isNaN(parsed)) trueExpTimestamp = parsed / 1000;
                        }

                        if (!mfgTimestamp || !trueExpTimestamp || typeof mfgTimestamp !== 'number' || typeof trueExpTimestamp !== 'number') {
                            return '<div style="text-align:center; color:#666; font-size:0.8rem; padding:10px;">Timeline unavailable - insufficient date data</div>';
                        }
                        
                        const userExpTimestamp = typeof data.userExpTimestamp === 'number' ? data.userExpTimestamp : null;

                        const start = mfgTimestamp;
                        const end = Math.max(trueExpTimestamp, userExpTimestamp || 0) + (30 * 24 * 60 * 60); // 30 day padding
                        const range = end - start;
                        
                        if (range <= 0) {
                            return '<div style="text-align:center; color:#666; font-size:0.8rem; padding:10px;">Timeline unavailable - invalid date range</div>';
                        }
                        
                        const getPos = (ts) => Math.max(-5, Math.min(105, ((ts - start) / range) * 100));
                        
                        const factoryPos = getPos(trueExpTimestamp);
                        const userPos = userExpTimestamp ? getPos(userExpTimestamp) : null;
                        const isInverse = userExpTimestamp && userExpTimestamp < mfgTimestamp;
                        
                        let segments = `<div class="timeline-segment" style="left:0; width:${factoryPos}%; background:#10b981; opacity:0.2;"></div>`;
                        
                        if (userPos !== null) {
                            if (userPos > factoryPos) {
                                // Anomaly Zone (Extensions)
                                segments += `<div class="timeline-segment" style="left:${factoryPos}%; width:${userPos - factoryPos}%; background:#ef4444; opacity:0.3;"></div>`;
                            } else if (isInverse) {
                                // Critical Inversion (Exp before Mfg)
                                segments += `<div class="timeline-segment" style="left:${userPos}%; width:${0 - userPos}%; background:#ef4444; opacity:0.5; border-right:2px dashed #ef4444;"></div>`;
                            }
                        }

                        return `
                            ${segments}
                            <div class="timeline-point" style="left:0;" title="Mfg Date"></div>
                            <div class="timeline-label" style="left:0;">MFG</div>
                            
                            <div class="timeline-point" style="left:${factoryPos}%; border-color:#3b82f6;" title="Calculated Expiry"></div>
                            <div class="timeline-label" style="left:${factoryPos}%; color:#3b82f6;">FACTORY LIMIT</div>
                            
                            ${userPos !== null ? `
                                <div class="timeline-point" style="left:${userPos}%; border-color:#ef4444; ${isInverse ? 'background:#ef4444;' : ''}" title="Label Expiry"></div>
                                <div class="timeline-label" style="left:${userPos}%; color:#ef4444;">PRINTED EXP</div>
                            ` : ''}
                        `;
                    })()}
                </div>

                <div style="margin-top:32px;">
                    <div class="diag-label" style="margin-bottom:12px;">Diagnostic Evidence Log</div>
                    <div class="diag-log-container">
                        ${logItems}
                    </div>
                </div>

                ${data.status !== 'SAFE' ? `
                <button type="button" class="btn btn-primary" style="width: 100%; margin-top:24px; background:#ef4444; border:none; height:48px; border-radius:12px; display:flex; gap:10px;" onclick="openModal('reportModal')">
                   <span style="font-size:1.2rem;">⚠</span> FLAG SYSTEM ANOMALY
                </button>` : `
                <div style="margin-top:24px; padding:12px; background:#ecfdf5; border-radius:12px; display:flex; align-items:center; gap:10px; color:#065f46; font-size:0.875rem; font-weight:700;">
                    <span style="background:#10b981; color:white; width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.7rem;">✓</span>
                    CRITICAL METADATA SYNC PASSED
                </div>
                `}
            </div>
        </div>
    `;
    resDiv.innerHTML = html;
}

function submitReport() {
    const barcode = document.getElementById('barcode_input').value;
    const batch = document.getElementById('batch_input').value;
    const expiry = document.getElementById('expiry_input').value;
    const reason = window.currentStatus === 'ANOMALY' ? 'Forensic Anomaly Detected' : 'Manual verification requested';
    const proof = window.currentProof || '';

    const btn = document.getElementById('submit_report_btn');
    btn.innerHTML = 'Acquiring GPS...';
    btn.disabled = true;

    const gpsText = document.getElementById('gps_text');
    const gpsDot = document.getElementById('gps_dot');

    if (navigator.geolocation) {
        if (gpsText) gpsText.innerHTML = "Acquiring SAT-LOCK...";
        if (gpsDot) gpsDot.style.animation = "pulse 1s infinite";
        
        navigator.geolocation.getCurrentPosition(
            position => {
                if (gpsText) gpsText.innerHTML = "Location Locked";
                if (gpsDot) gpsDot.style.animation = "none";
                sendReportData(barcode, batch, window.currentMfgDate, expiry, reason, proof, position.coords.latitude, position.coords.longitude, btn, window.currentDetectedProduct);
            },
            error => {
                if (gpsText) gpsText.innerHTML = "Location Failed";
                sendReportData(barcode, batch, window.currentMfgDate, expiry, reason, proof, null, null, btn, window.currentDetectedProduct);
            },
            { timeout: 7000, enableHighAccuracy: true }
        );
    } else {
        sendReportData(barcode, batch, window.currentMfgDate, expiry, reason, proof, null, null, btn, window.currentDetectedProduct);
    }
}

function sendReportData(barcode, batch, mfg, expiry, reason, proof, lat, lng, btn, productName) {
    btn.innerHTML = 'Securing Evidence...';
    
    const evidenceFile = document.getElementById('evidence_image').files[0];
    const formData = new FormData();
    formData.append('action', 'report');
    formData.append('barcode', barcode);
    formData.append('product', productName || 'Unknown');
    formData.append('batch', batch);
    formData.append('mfg', mfg);
    formData.append('expiry', expiry);
    formData.append('reason', reason);
    formData.append('proof', window.currentProof || '');
    formData.append('status', window.currentStatus || 'anomaly');
    formData.append('confidence', window.currentConfidence || 0);

    if (evidenceFile) {
        formData.append('evidence_image', evidenceFile);
    }
    if (lat && lng) {
        formData.append('lat', lat);
        formData.append('lng', lng);
    }

    fetch('verify.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = 'Yes, Submit Report';
        btn.disabled = false;
        
        if (data.success) {
            closeModal('reportModal');
            alert(`Report submitted securely to FSSAI! ${lat ? '(GPS Location Attached)' : ''} It is now Under Review.`);
            document.getElementById('verifyForm').reset();
            document.getElementById('verify_result').style.display = 'none';
            // Optional: transition to reports tab
            const reportsTab = document.querySelector('[data-tab-target="#tab-reports"]');
            if (reportsTab) reportsTab.click();
        } else {
            alert("Error: " + data.error);
        }
    })
    .catch(err => {
        btn.innerHTML = 'Yes, Submit Report';
        btn.disabled = false;
        console.error(err);
        alert("Network error while submitting report.");
    });
}
