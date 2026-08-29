<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Local Test Server</title>
</head>
<body>
    <h1>Local Server3 is Running!</h1>
    <p>Live Time: <strong id="clock">Loading time...</strong></p>

    <script>
        function updateClock() {
            const now = new Date();
            // Formats time as HH:MM:SS AM/PM based on browser settings
            document.getElementById('clock').innerText = now.toLocaleTimeString();
        }

        // Run immediately on page load
        updateClock();

        // Update the clock every 1000 milliseconds (1 second)
        setInterval(updateClock, 1000);
    </script>
</body>
</html>