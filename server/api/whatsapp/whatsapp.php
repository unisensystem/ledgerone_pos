<?php
include_once '../../client/includes/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Integration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="whatsapp.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fab fa-whatsapp"></i>
                <h1>WhatsApp Integration</h1>
            </div>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=Admin+User&background=0D8ABC&color=fff" alt="Admin User">
                <span>Admin User</span>
            </div>
        </header>

        <aside class="sidebar">
            <ul class="nav-links">
                <li><a href="#" class="active"><i class="fas fa-sliders-h"></i> Integration</a></li>
                <li><a href="#"><i class="fas fa-list"></i> Forms</a></li>
                <li><a href="#"><i class="fas fa-history"></i> Message Log</a></li>
                <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="#"><i class="fas fa-question-circle"></i> Help</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <!-- Integration Card -->
            <div class="card">
                <div class="card-header">
                    <h2>WhatsApp Integration</h2>
                    <label class="toggle-switch">
                        <input type="checkbox" id="integrationToggle" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="card-body">
                    <div id="integrationContent">
                        <div class="toggle-container">
                            <span class="toggle-label">Enable WhatsApp Integration</span>
                        </div>

                        <div class="qr-section">
                            <div class="qr-code">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <p class="qr-info">Scan with WhatsApp to link your account</p>
                            <p>QR expires in: <strong id="countdown">59</strong>s</p>
                            <button class="btn btn-secondary" id="refreshQR" style="margin-top: 10px;">
                                <i class="fas fa-sync-alt"></i> Refresh QR
                            </button>
                            
                            <div class="qr-instructions">
                                <h4>Linking Instructions:</h4>
                                <ol>
                                    <li>Open WhatsApp on your phone</li>
                                    <li>Go to Settings → Linked Devices</li>
                                    <li>Tap "Link a Device" and scan the code</li>
                                </ol>
                            </div>
                        </div>

                        <div class="form-selection">
                            <h3>Select Forms for Notifications</h3>
                            
                            <div class="select-all">
                                <input type="checkbox" id="selectAllForms">
                                <label for="selectAllForms">Select All Forms</label>
                            </div>
                            
                            <div class="form-list">
                                <div class="form-item">
                                    <input type="checkbox" id="form1" checked>
                                    <label for="form1">Contact Form</label>
                                </div>
                                <div class="form-item">
                                    <input type="checkbox" id="form2" checked>
                                    <label for="form2">Registration Form</label>
                                </div>
                                <div class="form-item">
                                    <input type="checkbox" id="form3">
                                    <label for="form3">Support Request</label>
                                </div>
                                <div class="form-item">
                                    <input type="checkbox" id="form4">
                                    <label for="form4">Feedback Form</label>
                                </div>
                            </div>
                        </div>

                        <div class="status-section">
                            <div class="status-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="status-text">
                                <h4>Integration Active</h4>
                                <p>WhatsApp is connected. Notifications enabled for selected forms.</p>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button class="btn btn-primary" id="saveSettings">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                            <button class="btn btn-secondary" id="testIntegration">
                                <i class="fas fa-paper-plane"></i> Test Integration
                            </button>
                            <button class="btn btn-danger" id="disconnect">
                                <i class="fas fa-unlink"></i> Disconnect
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="card">
                <div class="card-header">
                    <h2>Integration Statistics</h2>
                </div>
                <div class="card-body">
                    <div class="stats-container">
                        <div class="stat-card">
                            <i class="fas fa-paper-plane"></i>
                            <h3>1,247</h3>
                            <p>Messages Sent</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-check-circle"></i>
                            <h3>1,189</h3>
                            <p>Delivered</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-eye"></i>
                            <h3>976</h3>
                            <p>Read</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-comment"></i>
                            <h3>342</h3>
                            <p>Replies</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="whatsapp.js"></script>
</body>
</html>