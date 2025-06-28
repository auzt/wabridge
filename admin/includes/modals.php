<!-- QR Code Modal -->
<div id="qrModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeQR()">&times;</span>
        <h3>QR Code Scanner</h3>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button id="showQRTab" class="tab-button active" onclick="switchQRTab('show')">
                Show QR
            </button>
            <button id="scanQRTab" class="tab-button" onclick="switchQRTab('scan')">
                Scan QR
            </button>
        </div>

        <!-- Show QR Content -->
        <div id="showQRContent" class="qr-content active">
            <div id="qrContent">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading QR code...</p>
                </div>
            </div>
        </div>

        <!-- Scan QR Content -->
        <div id="scanQRContent" class="qr-content">
            <div class="scanner-container">
                <video id="qrVideo" width="300" height="300" autoplay></video>
                <canvas id="qrCanvas" style="display: none;"></canvas>

                <div class="scanner-controls">
                    <button id="startScanBtn" onclick="startQRScan()" class="btn btn-success">
                        Start Camera
                    </button>
                    <button id="stopScanBtn" onclick="stopQRScan()" class="btn btn-danger" style="display: none;">
                        Stop Camera
                    </button>
                </div>

                <div id="scanResult" class="scan-result" style="display: none;">
                    <strong>Scanned QR Code:</strong>
                    <textarea id="scannedText" rows="6" readonly></textarea>
                    <div class="result-actions">
                        <button onclick="sendQRToWhatsApp()" class="btn btn-success">
                            Send to WhatsApp
                        </button>
                        <button onclick="copyScannedText()" class="btn btn-secondary">
                            Copy Text
                        </button>
                        <button onclick="clearScanResult()" class="btn btn-warning">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="scanner-info">
                    <p><i class="icon-info"></i> Point your camera at a QR code to scan it</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Device Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEdit()">&times;</span>
        <h3>Edit Device</h3>
        <form method="POST" action="" onsubmit="return handleFormSubmit(this)">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="form_action" value="update">
            <input type="hidden" id="edit_device_id" name="device_id" value="">

            <div style="padding: 1.5rem;">
                <div class="form-group">
                    <label for="edit_webhook_url">Webhook URL</label>
                    <input type="url" id="edit_webhook_url" name="webhook_url"
                        placeholder="https://yourapp.com/webhook"
                        title="Enter a valid webhook URL (optional)">
                    <small class="form-help">Leave empty to remove webhook URL</small>
                </div>

                <div class="form-group">
                    <label for="edit_note">Note</label>
                    <textarea id="edit_note" name="note" rows="3"
                        placeholder="Add a note about this device (optional)"
                        maxlength="500"></textarea>
                    <small class="form-help">Maximum 500 characters</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update Device</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEdit()">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- API Examples Modal -->
<div id="apiModal" class="modal">
    <div class="modal-content modal-large">
        <span class="close" onclick="closeApi()">&times;</span>
        <h3>API Usage Examples</h3>
        <div id="apiContent" style="padding: 1.5rem; max-height: 70vh; overflow-y: auto;">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>