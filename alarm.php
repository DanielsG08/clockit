<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alarm Clock</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 500px;
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }
        h1 {
            color: #333;
            margin: 0;
            font-size: 1.8rem;
        }
        .back-btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-btn:hover {
            background: #5568d3;
        }
        #currentTime {
            font-size: 2.5rem;
            margin-bottom: 30px;
            text-align: center;
            color: #333;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }
        .controls {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }
        input[type="time"] {
            padding: 12px;
            font-size: 1rem;
            border: 2px solid #667eea;
            border-radius: 5px;
            width: 100%;
            max-width: 200px;
        }
        .button-group {
            display: flex;
            gap: 10px;
            width: 100%;
            justify-content: center;
            flex-wrap: wrap;
        }
        button {
            padding: 10px 20px;
            font-size: 1rem;
            cursor: pointer;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        button:hover:not(:disabled) {
            background: #5568d3;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        #alarmStatus {
            text-align: center;
            color: #666;
            margin-top: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Alarm</h1>
            <a href="dashboard.php" class="back-btn">← Dashboard</a>
        </div>
        
        <div id="currentTime">--:--:--</div>
        <div class="controls">
            <input type="time" id="alarmTime">
            <div class="button-group">
                <button id="setAlarm">Set Alarm</button>
                <button id="clearAlarm" disabled>Clear Alarm</button>
            </div>
            <div id="alarmStatus"></div>
        </div>
    </div>
    <audio id="alarmSound" src="https://actions.google.com/sounds/v1/alarms/alarm_clock.ogg" preload="auto"></audio>

    <script>
        const currentTimeEl = document.getElementById('currentTime');
        const alarmTimeInput = document.getElementById('alarmTime');
        const setAlarmBtn = document.getElementById('setAlarm');
        const clearAlarmBtn = document.getElementById('clearAlarm');
        const alarmSound = document.getElementById('alarmSound');
        const alarmStatus = document.getElementById('alarmStatus');

        let alarmTime = null;
        let alarmTimeout = null;

        function updateCurrentTime() {
            const now = new Date();
            currentTimeEl.textContent = now.toLocaleTimeString();
            if (alarmTime) {
                checkAlarm(now);
            }
        }

        function checkAlarm(now) {
            const nowStr = now.toTimeString().slice(0,5);
            if (nowStr === alarmTime) {
                alarmSound.play().catch(err => console.log('Could not play sound:', err));
                alert('Alarm!');
                clearAlarm();
            }
        }

        function setAlarm() {
            alarmTime = alarmTimeInput.value;
            if (!alarmTime) {
                alert('Please select a time');
                return;
            }
            setAlarmBtn.disabled = true;
            clearAlarmBtn.disabled = false;
            alarmStatus.textContent = `Alarm set for ${alarmTime}`;
        }

        function clearAlarm() {
            alarmTime = null;
            setAlarmBtn.disabled = false;
            clearAlarmBtn.disabled = true;
            alarmStatus.textContent = '';
        }

        setInterval(updateCurrentTime, 1000);
        setAlarmBtn.addEventListener('click', setAlarm);
        clearAlarmBtn.addEventListener('click', clearAlarm);
        
        // Initial time display
        updateCurrentTime();
    </script>
</body>
</html>