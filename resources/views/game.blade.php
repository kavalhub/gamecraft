<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Крафт-Игра - Вход</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        h1 { color: #333; margin-bottom: 10px; text-align: center; }
        .subtitle { color: #666; text-align: center; margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        input {
            width: 100%; padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px; font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus { outline: none; border-color: #667eea; }
        button {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none;
            border-radius: 8px; font-size: 16px;
            font-weight: 600; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .message {
            padding: 12px; border-radius: 8px;
            margin-bottom: 20px; display: none;
        }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.show { display: block; }

        .login-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .login-section h3 { color: #555; margin-bottom: 15px; font-size: 16px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🎮 Крафт-Игра</h1>
    <p class="subtitle">Создай своего героя и начни крафтить!</p>

    <div id="message" class="message"></div>

    <form id="registerForm">
        <div class="form-group">
            <label for="username">Имя героя</label>
            <input type="text" id="username" name="username"
                   placeholder="Введи имя (минимум 3 символа)"
                   required minlength="3" maxlength="50">
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password"
                   placeholder="Введи пароль (минимум 4 символа)"
                   required minlength="4">
        </div>

        <button type="submit">Создать героя</button>
    </form>

    <div class="login-section">
        <h3>Уже есть герой?</h3>
        <form id="loginForm">
            <div class="form-group">
                <label for="loginUsername">Имя героя</label>
                <input type="text" id="loginUsername" name="username" required>
            </div>
            <button type="submit" style="background:linear-gradient(135deg,#10b981,#059669)">Войти</button>
        </form>
    </div>
</div>

<script>
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    const messageDiv = document.getElementById('message');

    function showMessage(text, type) {
        messageDiv.textContent = text;
        messageDiv.className = `message ${type} show`;
        setTimeout(() => messageDiv.classList.remove('show'), 5000);
    }

    function goToGame(userId, username) {
        localStorage.setItem('userId', userId);
        localStorage.setItem('username', username);
        showMessage(`✅ Добро пожаловать, ${username}! Переходим в игру...`, 'success');
        setTimeout(() => {
            window.location.href = '/play';
        }, 1500);
    }

    // Регистрация
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        try {
            const response = await fetch('/api/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ username, password })
            });
            const data = await response.json();

            if (response.ok && data.user_id) {
                goToGame(data.user_id, data.username);
            } else {
                const errorMsg = data.error || data.message || 'Неизвестная ошибка';
                showMessage(`❌ Ошибка: ${errorMsg}`, 'error');
            }
        } catch (error) {
            showMessage(`❌ Ошибка соединения: ${error.message}`, 'error');
        }
    });

    // Вход (для существующих пользователей)
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('loginUsername').value.trim();

        try {
            const response = await fetch(`/api/login?username=${encodeURIComponent(username)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.user_id) {
                goToGame(data.user_id, data.username);
            } else {
                showMessage('❌ Пользователь не найден', 'error');
            }
        } catch (error) {
            showMessage(`❌ Ошибка: ${error.message}`, 'error');
        }
    });

    // Если уже авторизован — сразу в игру
    const savedUserId = localStorage.getItem('userId');
    const savedUsername = localStorage.getItem('username');
    if (savedUserId && savedUsername) {
        // Можно автоматически перенаправить, но дадим выбрать
    }
</script>
</body>
</html>
