/**
 * WhatsApp Bridge - Device Management JavaScript
 * File: admin/devices/assets/devices.js
 */

// Global variables
let qrScanner = null;
let qrStream = null;

// Enhanced clipboard functionality
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () {
            showToast('Copied to clipboard!', 'success');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Copied to clipboard!', 'success');
    }
}

// Toast notification system
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// QR Tab switching functionality
function switchQRTab(tab) {
    const showQRTab = document.getElementById('showQRTab');
    const scanQRTab = document.getElementById('scanQRTab');
    const showQRContent = document.getElementById('showQRContent');
    const scanQRContent = document.getElementById('scanQRContent');

    if (tab === 'show') {
        // Activate Show QR tab
        showQRTab.classList.add('active');
        scanQRTab.classList.remove('active');

        // Show/hide content
        showQRContent.classList.add('active');
        scanQRContent.classList.remove('active');
    } else if (tab === 'scan') {
        // Activate Scan QR tab
        scanQRTab.classList.add('active');
        showQRTab.classList.remove('active');

        // Show/hide content
        scanQRContent.classList.add('active');
        showQRContent.classList.remove('active');

        // Stop any existing scan when switching tabs
        stopQRScan();
    }
}

// Show QR Code modal
function showQR(sessionId, apiKey) {
    document.getElementById('qrModal').style.display = 'block';

    // Set default tab to "Show QR"
    switchQRTab('show');

    document.getElementById('qrContent').innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Loading QR code...</p>
        </div>
    `;

    // Fetch QR code with enhanced error handling
    fetch(`../../api/auth.php?action=qr`, {
        method: 'GET',
        headers: {
            'X-API-Key': apiKey,
            'Content-Type': 'application/json'
        }
    })
        .then(response => {
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response. Please check if Node.js API is running.');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data && data.data.qr_code) {
                document.getElementById('qrContent').innerHTML = `
                    <div style="text-align: center;">
                        <img src="data:image/png;base64,${data.data.qr_code}" 
                             alt="QR Code" 
                             style="max-width: 300px; width: 100%; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 1rem;">
                        <p style="margin: 1rem 0; color: #333;">
                            <strong>📱 Scan this QR code with WhatsApp</strong>
                        </p>
                        <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                            Open WhatsApp → Settings → Linked Devices → Link a Device
                        </p>
                        <button class="btn btn-primary" onclick="refreshQR('${sessionId}', '${apiKey}')">
                            🔄 Refresh QR
                        </button>
                    </div>
                `;
            } else {
                document.getElementById('qrContent').innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
                        <p style="color: #e74c3c; font-weight: 500;">QR code not available</p>
                        <p style="font-size: 0.9rem; color: #666; margin: 1rem 0;">
                            ${data.error || 'Please try connecting the device first or check if Node.js API is running.'}
                        </p>
                        <button class="btn btn-primary" onclick="refreshQR('${sessionId}', '${apiKey}')">
                            🔄 Retry
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('QR Code Error:', error);
            document.getElementById('qrContent').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                    <p style="color: #e74c3c; font-weight: 500;">Error loading QR code</p>
                    <p style="font-size: 0.9rem; color: #666; margin: 1rem 0;">
                        ${error.message}
                    </p>
                    <button class="btn btn-primary" onclick="refreshQR('${sessionId}', '${apiKey}')">
                        🔄 Retry
                    </button>
                </div>
            `;
        });
}

// Refresh QR Code
function refreshQR(sessionId, apiKey) {
    showQR(sessionId, apiKey);
}

// Close QR Modal
function closeQR() {
    stopQRScan();
    document.getElementById('qrModal').style.display = 'none';
}

// QR Scanner functions
function startQRScan() {
    const video = document.getElementById('qrVideo');
    const canvas = document.getElementById('qrCanvas');
    const context = canvas.getContext('2d');
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');

    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'environment' // Use back camera if available
        }
    })
        .then(function (stream) {
            qrStream = stream;
            video.srcObject = stream;
            video.play();

            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';

            // Start scanning
            qrScanner = setInterval(function () {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.height = video.videoHeight;
                    canvas.width = video.videoWidth;
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);

                    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);

                    // Use jsQR library if available
                    if (typeof jsQR !== 'undefined') {
                        const code = jsQR(imageData.data, imageData.width, imageData.height);
                        if (code) {
                            document.getElementById('scannedText').value = code.data;
                            document.getElementById('scanResult').style.display = 'block';
                            stopQRScan();

                            // Vibrate if supported
                            if (navigator.vibrate) {
                                navigator.vibrate(200);
                            }

                            showToast('QR Code scanned successfully!', 'success');
                        }
                    }
                }
            }, 500);
        })
        .catch(function (err) {
            console.error('Camera access error:', err);
            showToast('Cannot access camera: ' + err.message, 'error');
        });
}

function stopQRScan() {
    const video = document.getElementById('qrVideo');
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');

    if (qrScanner) {
        clearInterval(qrScanner);
        qrScanner = null;
    }

    if (qrStream) {
        qrStream.getTracks().forEach(track => track.stop());
        qrStream = null;
    }

    video.srcObject = null;
    startBtn.style.display = 'inline-block';
    stopBtn.style.display = 'none';
}

function copyScannedText() {
    const textarea = document.getElementById('scannedText');
    copyToClipboard(textarea.value);
}

function clearScanResult() {
    document.getElementById('scannedText').value = '';
    document.getElementById('scanResult').style.display = 'none';
}

function sendQRToWhatsApp() {
    const scannedText = document.getElementById('scannedText').value;
    if (scannedText) {
        // For now, just copy to clipboard
        copyToClipboard(scannedText);
        showToast('QR content copied! You can now paste it in WhatsApp.', 'success');
    }
}

// API Examples Modal
function showApiExamples(apiKey, deviceName) {
    document.getElementById('apiModal').style.display = 'block';

    const baseUrl = window.location.origin + window.location.pathname.replace('/admin/devices/index.php', '');

    document.getElementById('apiContent').innerHTML = `
        <div class="api-examples">
            <div style="background: #e3f2fd; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem;">
                <strong>Device:</strong> ${deviceName}<br>
                <strong>API Key:</strong> 
                <code style="background: white; padding: 0.25rem 0.5rem; border-radius: 3px; margin: 0 0.5rem;">${apiKey}</code>
                <button onclick="copyToClipboard('${apiKey}')" class="btn btn-small btn-secondary">Copy Key</button>
            </div>
            
            <h4 style="color: #333; margin: 1.5rem 0 0.5rem;">1. Send Text Message</h4>
            <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_text",
    "to": "628123456789",
    "text": "Hello from ${deviceName}!"
  }'</code></pre>
            
            <h4 style="color: #333; margin: 1.5rem 0 0.5rem;">2. Send to Multiple Numbers</h4>
            <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_text",
    "to": ["628123456789", "628987654321"],
    "text": "Broadcast message from ${deviceName}"
  }'</code></pre>
            
            <h4 style="color: #333; margin: 1.5rem 0 0.5rem;">3. Send Image with Caption</h4>
            <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_image",
    "to": "628123456789",
    "image": "https://example.com/image.jpg",
    "caption": "Check out this image!"
  }'</code></pre>
            
            <h4 style="color: #333; margin: 1.5rem 0 0.5rem;">4. Check Device Status</h4>
            <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem;"><code>curl -X GET ${baseUrl}/api/status \\
  -H "X-API-Key: ${apiKey}"</code></pre>
            
            <h4 style="color: #333; margin: 1.5rem 0 0.5rem;">5. Send Document/File</h4>
            <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_document",
    "to": "628123456789",
    "document": "https://example.com/document.pdf",
    "filename": "document.pdf"
  }'</code></pre>

            <h4 style="color: #333; margin: 1.5rem 0 0.5rem;">6. Response Format</h4>
            <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem;"><code>{
  "success": true,
  "message": "Message sent successfully",
  "data": {
    "message_id": "msg_12345",
    "to": "628123456789",
    "timestamp": "2024-01-01T12:00:00Z"
  }
}</code></pre>

            <div style="background: #fff3cd; padding: 1rem; border-radius: 4px; margin-top: 1.5rem; border-left: 4px solid #ffc107;">
                <strong>📝 Important Notes:</strong>
                <ul style="margin: 0.5rem 0 0 1rem;">
                    <li>All phone numbers must include country code (e.g., 628123456789 for Indonesia)</li>
                    <li>Group IDs end with @g.us (e.g., 120363123456789012@g.us)</li>
                    <li>Device must be connected to send messages</li>
                    <li>Check API documentation for more endpoints and parameters</li>
                </ul>
            </div>
        </div>
    `;
}

function closeApi() {
    document.getElementById('apiModal').style.display = 'none';
}

// Edit Device Modal
function editDevice(deviceId, webhookUrl, note) {
    document.getElementById('edit_device_id').value = deviceId;
    document.getElementById('edit_webhook_url').value = webhookUrl;
    document.getElementById('edit_note').value = note;
    document.getElementById('editModal').style.display = 'block';
}

function closeEdit() {
    document.getElementById('editModal').style.display = 'none';
}

// Form submission handling
function handleFormSubmit(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;

    // Disable button and show loading state
    submitButton.disabled = true;
    submitButton.textContent = 'Processing...';

    // Re-enable button after 10 seconds to prevent permanent lock
    setTimeout(() => {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }, 10000);

    return true;
}

// CSRF Token management
function refreshCsrfToken() {
    fetch('index.php?action=refresh_csrf', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.csrf_token) {
                // Update all CSRF token inputs
                document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                    input.value = data.csrf_token;
                });
                showToast('Security token refreshed', 'success');
            }
        })
        .catch(error => {
            console.error('Failed to refresh CSRF token:', error);
        });
}

// Sync all devices status
function syncAllDevices() {
    const button = event.target;
    const originalText = button.textContent;

    button.disabled = true;
    button.textContent = '🔄 Syncing...';

    fetch('../../api/sync_devices.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('All devices synced successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Sync failed: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showToast('Sync error: ' + error.message, 'error');
        })
        .finally(() => {
            button.disabled = false;
            button.textContent = originalText;
        });
}

// Modal event handlers
function setupModalHandlers() {
    // Close modals when clicking outside
    window.onclick = function (event) {
        const qrModal = document.getElementById('qrModal');
        const editModal = document.getElementById('editModal');
        const apiModal = document.getElementById('apiModal');

        if (event.target == qrModal) {
            closeQR();
        }
        if (event.target == editModal) {
            closeEdit();
        }
        if (event.target == apiModal) {
            closeApi();
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        // ESC to close modals
        if (e.key === 'Escape') {
            closeQR();
            closeEdit();
            closeApi();
        }
    });
}

// Network status handlers
function setupNetworkHandlers() {
    window.addEventListener('online', function () {
        showToast('Connection restored', 'success');
    });

    window.addEventListener('offline', function () {
        showToast('Connection lost - some features may not work', 'error');
    });
}

// Auto-refresh functionality
function setupAutoRefresh() {
    // Auto-refresh CSRF token every 30 minutes
    setInterval(refreshCsrfToken, 30 * 60 * 1000);

    // Auto-refresh page every 5 minutes to update device status
    setInterval(function () {
        // Only refresh if no modals are open
        const modals = document.querySelectorAll('.modal');
        const isModalOpen = Array.from(modals).some(modal =>
            window.getComputedStyle(modal).display === 'block'
        );

        if (!isModalOpen) {
            location.reload();
        }
    }, 5 * 60 * 1000);
}

// Form validation
function setupFormValidation() {
    // Real-time validation for device name
    const deviceNameInput = document.getElementById('device_name');
    if (deviceNameInput) {
        deviceNameInput.addEventListener('input', function () {
            const value = this.value.trim();
            const errorElement = this.parentElement.querySelector('.form-error');

            // Remove existing error
            if (errorElement) {
                errorElement.remove();
            }

            // Validate length
            if (value.length > 0 && value.length < 3) {
                const error = document.createElement('small');
                error.className = 'form-error';
                error.style.color = '#dc3545';
                error.textContent = 'Device name must be at least 3 characters long';
                this.parentElement.appendChild(error);
            }
        });
    }

    // Validate webhook URL
    const webhookInputs = document.querySelectorAll('input[name="webhook_url"]');
    webhookInputs.forEach(input => {
        input.addEventListener('blur', function () {
            const value = this.value.trim();
            const errorElement = this.parentElement.querySelector('.form-error');

            // Remove existing error
            if (errorElement) {
                errorElement.remove();
            }

            // Validate URL if not empty
            if (value && !isValidUrl(value)) {
                const error = document.createElement('small');
                error.className = 'form-error';
                error.style.color = '#dc3545';
                error.textContent = 'Please enter a valid URL';
                this.parentElement.appendChild(error);
            }
        });
    });
}

// URL validation helper
function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// Device status checker
function checkDeviceStatus() {
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        const status = badge.textContent.toLowerCase().trim();

        // Add animation for connecting status
        if (status === 'connecting') {
            badge.style.animation = 'pulse 1.5s infinite';
        }
    });
}

// Add CSS animation for pulse effect
function addPulseAnimation() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}

// Page initialization
function initializePage() {
    console.log('Initializing WhatsApp Bridge Device Management...');

    // Setup form handlers
    document.querySelectorAll('form[method="POST"]').forEach(form => {
        form.addEventListener('submit', function (e) {
            return handleFormSubmit(this);
        });
    });

    // Setup modal handlers
    setupModalHandlers();

    // Setup network handlers
    setupNetworkHandlers();

    // Setup auto-refresh
    setupAutoRefresh();

    // Setup form validation
    setupFormValidation();

    // Check device status animations
    checkDeviceStatus();

    // Add pulse animation
    addPulseAnimation();

    // Add tooltips for truncated text
    document.querySelectorAll('[title]').forEach(element => {
        element.style.cursor = 'help';
    });

    // Add smooth scrolling
    document.documentElement.style.scrollBehavior = 'smooth';

    // Show loading complete
    showToast('Device management loaded successfully', 'success');

    console.log('WhatsApp Bridge Device Management initialized successfully!');
}

// Page loading handler
window.addEventListener('beforeunload', function () {
    // Stop any active QR scanner
    stopQRScan();

    // Add loading overlay
    document.body.style.opacity = '0.7';
    document.body.style.pointerEvents = 'none';
});

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    // Small delay to ensure all elements are rendered
    setTimeout(initializePage, 100);
});

// Export functions for testing (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        copyToClipboard,
        showToast,
        switchQRTab,
        refreshQR,
        isValidUrl
    };
}