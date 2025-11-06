<?php
/**
 * YalaGuard Camera System - Simple Connection with Video Recording
 */

// Start session
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user = [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'email' => $_SESSION['email'],
    'full_name' => $_SESSION['full_name'],
    'role' => $_SESSION['role'],
    'status' => $_SESSION['status']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>YalaGuard - Drone System</title>
    <link rel="stylesheet" href="../assets/css/style.css">

</head>
<body>
    <!-- Simple Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-logo">🐘 YalaGuard</a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="elephants.php">Elephants</a></li>
                <li><a href="geofencing.php">Geofencing</a></li>
                <li><a href="gps-collar-management.php">GPS Collars</a></li>
                <li><a href="camera.php" class="active">Camera</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Alert System</a>
                    <ul class="dropdown-menu">
                        <li><a href="manual-alerts.php">Manual Alert</a></li>
                        <li><a href="authority-management.php">Manage Authorities</a></li>
                        <li><a href="#" onclick="viewAlertHistory()">Alert History</a></li>
                    </ul>
                </li>
            </ul>
            <div class="navbar-user">
                <span class="user-info">Welcome, <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
                <a href="../api/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">🚁 Live Drone Feed with Video Recording</h1>
        
        <!-- Drone Connection Section -->
        <div class="camera-section">
            <h2>Connect to Your Drone</h2>
            <p>Enter your drone URL and click "Start Drone"</p>
            
            <input 
                type="text" 
                id="cameraUrl" 
                class="camera-input" 
                placeholder="http://192.168.1.5:8080/video"
                value="http://192.168.1.5:8080/video"
            >
            
            <div class="camera-controls">
                <button class="btn btn-primary btn-large" onclick="startCamera()">
                    ▶️ Start Drone
                </button>
                <button class="btn btn-success btn-large" onclick="testConnection()">
                    Test Connection
                </button>
                <button class="btn btn-danger btn-large" onclick="stopCamera()">
                    ⏹️ Stop Drone
                </button>
                <button class="btn btn-warning btn-large" onclick="fullscreen()">
                    Full Screen
                </button>
            </div>
            
            <!-- Recording Controls -->
            <div class="camera-controls" style="margin-top: 1rem;">
                <button class="btn btn-success btn-large" id="startRecordingBtn" onclick="startRecording()" style="display: none;">
                    🔴 Start Recording
                </button>
                <button class="btn btn-danger btn-large" id="stopRecordingBtn" onclick="stopRecording()" style="display: none;">
                    ⏹️ Stop Recording
                </button>
                <button class="btn btn-warning btn-large" id="downloadRecordingBtn" onclick="downloadRecording()" style="display: none;">
                    💾 Download Video
                </button>
            </div>
            
            <div id="recordingStatus" style="margin-top: 1rem; font-weight: 500; color: #dc3545;"></div>
            <div id="connectionStatus" style="margin-top: 1rem; font-weight: 500;"></div>
        </div>
        
        <!-- Live Feed Container -->
        <div class="camera-section">
            <h2>Live Drone Feed</h2>
            <div class="live-feed" id="liveFeed">
                <div class="feed-placeholder">
                    <i>📹</i>
                    <div>Enter drone URL above and click "Start Drone"</div>
                    <div style="font-size: 0.7rem; margin-top: 0.5rem; opacity: 0.7;">
                        Example: http://192.168.1.5:8080/video
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Troubleshooting Section -->
        <div class="camera-section">
            <div class="troubleshooting">
                <h4>🔧 Troubleshooting Connection Issues</h4>
                <ul>
                    <li><strong>Check IP Address:</strong> Make sure 192.168.1.5 is your drone's actual IP address</li>
                    <li><strong>Check Port:</strong> Verify port 8080 is correct for your drone</li>
                    <li><strong>Network Access:</strong> Ensure your computer and drone are on the same network</li>
                    <li><strong>Drone Power:</strong> Make sure your drone is turned on and connected</li>
                    <li><strong>Try Different URLs:</strong> 
                        <ul style="margin-top: 0.5rem; margin-bottom: 0;">
                            <li><code>http://192.168.1.5:8080/mjpeg</code></li>
                            <li><code>http://192.168.1.5:8080/stream</code></li>
                            <li><code>http://192.168.1.5:8080/live</code></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        let currentVideo = null;
        let isPlaying = false;
        let mediaRecorder = null;
        let recordedChunks = [];
        let isRecording = false;
        let recordingUrl = null;
        let recordingCanvas = null;
        let recordingContext = null;
        let recordingStream = null;

        function updateStatus(message, type) {
            const statusDiv = document.getElementById('connectionStatus');
            const statusClass = type === 'success' ? 'status-success' : 
                               type === 'error' ? 'status-error' : 'status-connecting';
            
            statusDiv.innerHTML = `<span class="status-indicator ${statusClass}"></span>${message}`;
        }

        function updateRecordingStatus(message, type) {
            const statusDiv = document.getElementById('recordingStatus');
            const statusClass = type === 'success' ? 'status-success' : 
                               type === 'error' ? 'status-error' : 'status-connecting';
            
            statusDiv.innerHTML = `<span class="status-indicator ${statusClass}"></span>${message}`;
        }

        function testConnection() {
            const url = document.getElementById('cameraUrl').value.trim();
            
            if (!url) {
                alert('Please enter a camera URL');
                return;
            }

            updateStatus('Testing connection...', 'connecting');
            
            // Create a test image to check if URL is accessible
            const testImg = new Image();
            testImg.onload = function() {
                updateStatus('Connection successful! Camera URL is accessible.', 'success');
            };
            testImg.onerror = function() {
                updateStatus('Connection failed. Check your camera URL and network.', 'error');
            };
            
            // Add timestamp to avoid caching
            testImg.src = url + (url.includes('?') ? '&' : '?') + 't=' + Date.now();
        }

        function startCamera() {
            const url = document.getElementById('cameraUrl').value.trim();
            
            if (!url) {
                alert('Please enter a drone URL');
                return;
            }

            if (isPlaying) {
                alert('Drone is already running!');
                return;
            }

            updateStatus('Connecting to drone...', 'connecting');
            
            const liveFeed = document.getElementById('liveFeed');
            
            // Clear previous content
            liveFeed.innerHTML = '';
            
            // Try different methods to display the camera feed
            tryMethod1(url, liveFeed);
        }

        function tryMethod1(url, liveFeed) {
            // Method 1: Try HTML5 video element
            updateStatus('Trying drone video stream...', 'connecting');
            
            const video = document.createElement('video');
            video.style.width = '100%';
            video.style.height = '100%';
            video.style.borderRadius = '10px';
            video.style.objectFit = 'cover';
            video.controls = true;
            video.autoplay = true;
            video.muted = true;
            video.crossOrigin = 'anonymous';
            
            // Handle video events
            video.onloadstart = function() {
                updateStatus('Loading video stream...', 'connecting');
                console.log('Loading video stream...');
            };
            
            video.oncanplay = function() {
                updateStatus('Drone connected successfully!', 'success');
                console.log('Video stream ready to play');
                isPlaying = true;
                currentVideo = video;
                
                // Show recording controls for video streams
                showRecordingControls();
            };
            
            video.onerror = function() {
                console.log('Video method failed, trying image method...');
                tryMethod2(url, liveFeed);
            };
            
            // Set video source
            video.src = url;
            liveFeed.appendChild(video);
            
            // Set a timeout to try next method if video doesn't load
            setTimeout(() => {
                if (!isPlaying) {
                    console.log('Video timeout, trying image method...');
                    tryMethod2(url, liveFeed);
                }
            }, 5000);
        }

        function tryMethod2(url, liveFeed) {
            // Method 2: Try as MJPEG stream using img element
            updateStatus('Trying drone MJPEG stream...', 'connecting');
            
            const img = document.createElement('img');
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.borderRadius = '10px';
            img.style.objectFit = 'cover';
            img.crossOrigin = 'anonymous';
            
            img.onload = function() {
                updateStatus('Drone connected successfully! (MJPEG)', 'success');
                console.log('MJPEG stream loaded');
                isPlaying = true;
                currentVideo = img;
                
                // Show recording controls for image streams
                showRecordingControls();
            };
            
            img.onerror = function() {
                console.log('MJPEG method failed, trying iframe method...');
                tryMethod3(url, liveFeed);
            };
            
            // Clear previous content and add image
            liveFeed.innerHTML = '';
            liveFeed.appendChild(img);
            
            // Add timestamp to avoid caching
            img.src = url + (url.includes('?') ? '&' : '?') + 't=' + Date.now();
            
            // Set a timeout to try next method if image doesn't load
            setTimeout(() => {
                if (!isPlaying) {
                    console.log('MJPEG timeout, trying iframe method...');
                    tryMethod3(url, liveFeed);
                }
            }, 5000);
        }

        function tryMethod3(url, liveFeed) {
            // Method 3: Try iframe for embedded streams
            updateStatus('Trying drone iframe method...', 'connecting');
            
            const iframe = document.createElement('iframe');
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '10px';
            iframe.src = url;
            iframe.allow = 'autoplay; fullscreen';
            
            iframe.onload = function() {
                updateStatus('Drone connected successfully! (iframe)', 'success');
                console.log('iframe stream loaded');
                isPlaying = true;
                currentVideo = iframe;
                
                // Show recording controls for iframe streams
                showRecordingControls();
            };
            
            iframe.onerror = function() {
                console.log('iframe method failed, showing error...');
                showConnectionError(url, liveFeed);
            };
            
            // Clear previous content and add iframe
            liveFeed.innerHTML = '';
            liveFeed.appendChild(iframe);
            
            // Set a timeout to show error if iframe doesn't load
            setTimeout(() => {
                if (!isPlaying) {
                    console.log('iframe timeout, showing error...');
                    showConnectionError(url, liveFeed);
                }
            }, 5000);
        }

        function showConnectionError(url, liveFeed) {
            updateStatus('Failed to connect to drone. Check troubleshooting below.', 'error');
            
            liveFeed.innerHTML = `
                <div class="feed-placeholder">
                    <i>❌</i>
                    <div>Failed to connect to drone</div>
                    <div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.7;">
                        URL: ${url}
                    </div>
                    <div style="font-size: 0.8rem; margin-top: 1rem; opacity: 0.6;">
                        The URL is accessible but the stream format is not supported
                    </div>
                    <div style="font-size: 0.8rem; margin-top: 0.5rem; opacity: 0.6;">
                        Try different URL formats in the troubleshooting section
                    </div>
                </div>
            `;
            isPlaying = false;
            hideRecordingControls();
        }

        function showRecordingControls() {
            document.getElementById('startRecordingBtn').style.display = 'inline-block';
            document.getElementById('stopRecordingBtn').style.display = 'none';
            document.getElementById('downloadRecordingBtn').style.display = 'none';
        }

        function hideRecordingControls() {
            document.getElementById('startRecordingBtn').style.display = 'none';
            document.getElementById('stopRecordingBtn').style.display = 'none';
            document.getElementById('downloadRecordingBtn').style.display = 'none';
        }

        function startRecording() {
            if (!isPlaying || !currentVideo) {
                alert('Please start the drone first');
                return;
            }

            if (isRecording) {
                alert('Recording is already in progress');
                return;
            }

            try {
                if (currentVideo.tagName === 'VIDEO') {
                    startVideoRecording();
                } else if (currentVideo.tagName === 'IMG') {
                    startImageStreamRecording();
                } else {
                    alert('Recording not supported for this stream type');
                }
            } catch (error) {
                console.error('Recording error:', error);
                alert('Recording failed: ' + error.message);
            }
        }

        function startVideoRecording() {
            const video = currentVideo;
            
            try {
                // Get the video stream
                let stream;
                
                if (video.srcObject) {
                    stream = video.srcObject;
                } else if (video.captureStream) {
                    stream = video.captureStream(30); // 30 FPS
                } else {
                    throw new Error('No stream available for recording');
                }
                
                // Create MediaRecorder
                let mediaRecorder;
                const mimeTypes = [
                    'video/webm;codecs=vp8',
                    'video/webm;codecs=vp9',
                    'video/webm',
                    'video/mp4',
                    'video/ogg'
                ];
                
                for (let mimeType of mimeTypes) {
                    if (MediaRecorder.isTypeSupported(mimeType)) {
                        try {
                            mediaRecorder = new MediaRecorder(stream, { 
                                mimeType: mimeType,
                                videoBitsPerSecond: 2500000 // 2.5 Mbps
                            });
                            console.log('Using MIME type:', mimeType);
                            break;
                        } catch (e) {
                            continue;
                        }
                    }
                }
                
                if (!mediaRecorder) {
                    throw new Error('No supported video format found');
                }
                
                // Store the media recorder
                window.currentMediaRecorder = mediaRecorder;
                
                recordedChunks = [];
                
                mediaRecorder.ondataavailable = function(event) {
                    if (event.data.size > 0) {
                        recordedChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = function() {
                    const blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType });
                    recordingUrl = URL.createObjectURL(blob);
                    
                    // Show download button
                    document.getElementById('downloadRecordingBtn').style.display = 'inline-block';
                    console.log('Video recording ready for download, size:', blob.size, 'bytes');
                };
                
                // Start recording
                mediaRecorder.start(1000); // Collect data every second
                isRecording = true;
                
                // Add recording overlay
                addRecordingOverlay();
                
                // Update UI
                document.getElementById('startRecordingBtn').style.display = 'none';
                document.getElementById('stopRecordingBtn').style.display = 'inline-block';
                
                updateRecordingStatus('🔴 Recording video...', 'error');
                console.log('Started video recording');
                
            } catch (error) {
                console.error('Video recording failed:', error);
                alert('Video recording failed: ' + error.message);
            }
        }

        function startImageStreamRecording() {
            // For image streams, create a canvas and record it as video
            isRecording = true;
            recordedChunks = [];
            
            // Create canvas for recording
            recordingCanvas = document.createElement('canvas');
            recordingContext = recordingCanvas.getContext('2d');
            const img = currentVideo;
            
            recordingCanvas.width = img.naturalWidth || img.width;
            recordingCanvas.height = img.naturalHeight || img.height;
            
            // Create stream from canvas
            recordingStream = recordingCanvas.captureStream(30); // 30 FPS
            
            // Create MediaRecorder for canvas stream
            let mediaRecorder;
            const mimeTypes = [
                'video/webm;codecs=vp8',
                'video/webm;codecs=vp9',
                'video/webm',
                'video/mp4',
                'video/ogg'
            ];
            
            for (let mimeType of mimeTypes) {
                if (MediaRecorder.isTypeSupported(mimeType)) {
                    try {
                        mediaRecorder = new MediaRecorder(recordingStream, { 
                            mimeType: mimeType,
                            videoBitsPerSecond: 2500000 // 2.5 Mbps
                        });
                        console.log('Using MIME type for image stream:', mimeType);
                        break;
                    } catch (e) {
                        continue;
                    }
                }
            }
            
            if (!mediaRecorder) {
                alert('No supported video format found');
                isRecording = false;
                return;
            }
            
            // Store the media recorder
            window.currentMediaRecorder = mediaRecorder;
            
            mediaRecorder.ondataavailable = function(event) {
                if (event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };
            
            mediaRecorder.onstop = function() {
                const blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType });
                recordingUrl = URL.createObjectURL(blob);
                
                // Show download button
                document.getElementById('downloadRecordingBtn').style.display = 'inline-block';
                console.log('Image stream video ready for download, size:', blob.size, 'bytes');
            };
            
            // Start recording
            mediaRecorder.start(1000);
            
            // Start drawing frames to canvas
            const drawInterval = setInterval(() => {
                if (!isRecording) {
                    clearInterval(drawInterval);
                    return;
                }
                
                // Draw current frame to canvas
                recordingContext.drawImage(img, 0, 0);
            }, 33); // 30 FPS
            
            // Store interval for cleanup
            window.drawInterval = drawInterval;
            
            // Add recording overlay
            addRecordingOverlay();
            
            // Update UI
            document.getElementById('startRecordingBtn').style.display = 'none';
            document.getElementById('stopRecordingBtn').style.display = 'inline-block';
            
            updateRecordingStatus('🔴 Recording video from image stream...', 'error');
            console.log('Started image stream video recording');
        }

        function addRecordingOverlay() {
            const liveFeed = document.getElementById('liveFeed');
            const overlay = document.createElement('div');
            overlay.className = 'recording-overlay';
            overlay.id = 'recordingOverlay';
            overlay.innerHTML = '🔴 REC';
            liveFeed.appendChild(overlay);
        }

        function removeRecordingOverlay() {
            const overlay = document.getElementById('recordingOverlay');
            if (overlay) {
                overlay.remove();
            }
        }

        function stopRecording() {
            if (!isRecording) {
                alert('No recording in progress');
                return;
            }

            isRecording = false;
            
            // Remove recording overlay
            removeRecordingOverlay();
            
            // Stop video recording
            if (window.currentMediaRecorder && window.currentMediaRecorder.state !== 'inactive') {
                window.currentMediaRecorder.stop();
                console.log('Stopped video recording');
            }
            
            // Clear draw interval if it exists
            if (window.drawInterval) {
                clearInterval(window.drawInterval);
                window.drawInterval = null;
            }
            
            // Update UI
            document.getElementById('startRecordingBtn').style.display = 'inline-block';
            document.getElementById('stopRecordingBtn').style.display = 'none';
            
            updateRecordingStatus('Recording stopped', 'success');
            console.log('Stopped recording');
        }

        function downloadRecording() {
            if (recordingUrl) {
                // Download video recording
                const a = document.createElement('a');
                a.href = recordingUrl;
                a.download = `yalaguard-video-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.webm`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                
                // Clean up
                URL.revokeObjectURL(recordingUrl);
                recordingUrl = null;
                document.getElementById('downloadRecordingBtn').style.display = 'none';
                
                console.log('Video recording downloaded');
            } else {
                alert('No recording available to download');
            }
        }

        function stopCamera() {
            if (isRecording) {
                stopRecording();
            }
            
            if (currentVideo) {
                if (currentVideo.tagName === 'VIDEO') {
                    currentVideo.pause();
                    currentVideo.src = '';
                }
                currentVideo = null;
            }
            
            isPlaying = false;
            updateStatus('Drone stopped', 'error');
            updateRecordingStatus('', '');
            
            const liveFeed = document.getElementById('liveFeed');
            liveFeed.innerHTML = `
                <div class="feed-placeholder">
                    <i>🚁</i>
                    <div>Drone stopped</div>
                    <div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.7;">
                        Click "Start Drone" to resume
                    </div>
                </div>
            `;
            
            hideRecordingControls();
            console.log('Stopped drone feed');
        }

        function fullscreen() {
            if (currentVideo && isPlaying) {
                if (currentVideo.requestFullscreen) {
                    currentVideo.requestFullscreen();
                } else if (currentVideo.webkitRequestFullscreen) {
                    currentVideo.webkitRequestFullscreen();
                } else if (currentVideo.msRequestFullscreen) {
                    currentVideo.msRequestFullscreen();
                }
            } else {
                alert('Please start the drone first');
            }
        }

        // Handle Enter key in URL input
        document.getElementById('cameraUrl').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                startCamera();
            }
        });
    </script>
</body>
</html>
