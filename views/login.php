<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        try {
            $db = GymDatabaseConnector::getInstance();
            $user = $db->authenticateUser($username, $password);

            if ($user) {
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } catch (Exception $e) {
            $error = "Login failed: " . $e->getMessage();
        }
    }
}
?>
<!-- HTML form as above -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - He-Man Fitness Gym</title>
    <!-- Add Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Add Google Fonts for Anton and Inter -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter&display=swap">
    <style>
        /* Reset default margins and ensure full height */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
            background-color: rgba(49, 49, 49, 0.88);
        }

        .site-header {
            background-color: rgba(68, 68, 68, 1);
            padding: 15px 40px;
            display: flex;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.25);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 20;
        }

        .brand-name {
            font-family: 'Anton', sans-serif;
            font-size: 24px;
            color: #fff;
            font-weight: 400;
            margin: 0;
        }

        .main-content {
            flex: 1;
            padding: 80px 20px 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 50px);
        }

        .login-container {
            background-color: rgba(44, 44, 44, 1);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .logo-image {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
        }

        .login-heading {
            font-family: 'Anton', sans-serif;
            font-size: 48px;
            font-weight: 400;
            margin: 0 0 20px;
        }

        .text-red {
            color: rgba(226, 29, 29, 1);
        }

        .text-white {
            color: #fff;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .form-label {
            font-family: 'Arial', sans-serif;
            font-size: 16px;
            color: #fff;
            font-weight: 500;
            margin-bottom: 5px;
            width: 70%;
            text-align: left;
        }

        .form-input {
            width: 70%;
            padding: 10px;
            border-radius: 10px;
            background-color: rgba(68, 68, 68, 0.8);
            color: #fff;
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
            text-align: left;
            margin: 0 auto;
            display: block;
            border: none;
        }

        .form-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .login-button {
            background-color: rgba(226, 29, 29, 1);
            padding: 12px;
            border: none;
            border-radius: 20px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            width: 70%;
            margin: 0 auto;
        }

        .login-button:disabled {
            background-color: rgba(226, 29, 29, 0.6);
            cursor: not-allowed;
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .error-message {
            color: rgba(226, 29, 29, 1);
            font-size: 14px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <h1 class="brand-name">He-Man Fitness Gym</h1>
    </header>

    <main class="main-content">
        <div class="login-container">
            <img
                id="logo1"
                width="120"
                height="120"
                src="../img/logo2.jpg"
                class="logo-image"
                alt="He-Man Fitness Gym Logo"
            />
            <h2 class="login-heading">
                <span class="text-red">LOG</span><span class="text-white">IN</span>
            </h2>
            <form
                x-data="{
                    username: '',
                    password: '',
                    isHovered: false,
                    isLoading: false,
                    async submitForm() {
                        if (!this.username || !this.password) {
                            alert('Please enter both username and password');
                            return;
                        }
                        this.isLoading = true;
                        // PHP handles the actual submission, so we let the form submit naturally
                        document.querySelector('.login-form').submit();
                    }
                }"
                class="login-form"
                method="POST"
                @submit.prevent="submitForm"
            >
                <div class="form-group">
                    <label for="username" class="form-label">User</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        x-model="username"
                        class="form-input"
                        required
                        aria-required="true"
                        :disabled="isLoading"
                    />
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        x-model="password"
                        class="form-input"
                        required
                        aria-required="true"
                        :disabled="isLoading"
                    />
                </div>

                <button
                    type="submit"
                    class="login-button"
                    x-on:mouseenter="isHovered = true"
                    x-on:mouseleave="isHovered = false"
                    x-bind:style="{ transform: isHovered && !isLoading ? 'scale(1.02)' : 'scale(1)', background: isHovered && !isLoading ? '#ff2424' : '#E21D1D' }"
                    :disabled="isLoading"
                    :aria-busy="isLoading"
                >
                    <span x-show="!isLoading">Login</span>
                    <span
                        x-show="isLoading"
                        class="loading-spinner"
                        aria-hidden="true"
                    ></span>
                    <span x-show="isLoading" class="sr-only">Loading...</span>
                </button>
            </form>
            <?php if (isset($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
