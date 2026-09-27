<?php
// Professional Animated Index Page
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | System</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e293b, #2563eb);
            overflow: hidden;
            transition: opacity 0.7s ease; 
        }

        
        body::before, body::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            z-index: 0;
        }

        body::before { width: 500px; height: 500px; top: -150px; left: -150px; animation: float 6s ease-in-out infinite; }
        body::after { width: 400px; height: 400px; bottom: -120px; right: -120px; animation: float 8s ease-in-out infinite; }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(30px); }
        }

        .container {
            position: relative;
            z-index: 10;
            width: 90%;
            max-width: 420px;
            padding: 40px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
            color: white;
            animation: fadeIn 1.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h1 { font-size: 3rem; margin-bottom: 15px; letter-spacing: 2px; animation: glow 2s infinite alternate; }

        @keyframes glow {
            from { text-shadow: 0 0 10px #60a5fa; }
            to { text-shadow: 0 0 25px #ffffff; }
        }

        p { font-size: 1.1rem; margin-bottom: 30px; color: #e2e8f0; }

        button {
            padding: 14px 30px;
            border: none;
            border-radius: 50px;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
        }

        .small-text { margin-top: 25px; font-size: 0.8rem; color: #cbd5e1; }
    </style>
</head>
<body>

<div class="container">
    <h1>WELCOME</h1>
    <p>Professional Animated PHP System</p>
    <button onclick="goToLogin()">Enter System</button>
    <div class="small-text">Powered by PHP & Modern UI</div>
</div>

<script>
    function goToLogin(){
        document.body.style.opacity = "0";
        setTimeout(function(){
            window.location.href = "login.php"; 
        }, 700);
    }
</script>

</body>
</html>