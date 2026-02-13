<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gerenciamento GKT">
    <title>GKT Artes Marciais - Login</title>

    <link rel="shortcut icon" href="favicon.png" type="image/x-icon">

    <!--font awesome-->
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@300;400;500;600;700&family=Saira+Condensed:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!--style-->
    <link rel="stylesheet" href="ASSETS/CSS/loginStyle.css">
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-container">
        <div class="bg-pattern"></div>
        
        <!-- Combat Shapes -->
        <div class="combat-shape shape-1"></div>
        <div class="combat-shape shape-2"></div>
        <div class="combat-shape shape-3"></div>

        <!-- Particles -->
        <div class="particle particle-1"></div>
        <div class="particle particle-2"></div>
        <div class="particle particle-3"></div>
    </div>

    <!-- Main Login Container -->
    <div class="login-container">
        <div class="login-wrapper">
            <!-- Left Side - Branding -->
            <div class="brand-side">
                <div class="combat-silhouette"></div>
                
                <div class="brand-content">
                    <div class="logo-large">GKT</div>
                    <div class="logo-subtitle">Artes Marciais</div>
                    <div class="brand-divider"></div>
                    <div class="brand-tagline">
                        Sistema de Gerenciamento
                    </div>
                </div>

                <div class="brand-features">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <span>Gestão completa de alunos</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <span>Relatórios e análises</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <span>Segurança e privacidade</span>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="form-side">
                <div class="form-header">
                    <h1 class="form-title">Bem-vindo</h1>
                    <p class="form-subtitle">Entre com suas credenciais para acessar o sistema</p>
                </div>

                <form action="BACKEND/CONTROLLER/actions.php" class="login-form" id="loginForm" method="POST">
                    <div class="form-group">
                        <label for="inputUser" class="form-label">Usuário ou Email</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <input 
                                id="inputUser"
                                name="inputUser"
                                type="text" 
                                class="form-input" 
                                placeholder="Digite seu usuário ou email"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="inputUserPass" class="form-label">Senha</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <input 
                                type="password" 
                                class="form-input" 
                                id="inputUserPass"
                                name="inputUserPass"
                                placeholder="Digite sua senha"
                                required
                            >

                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="alternar entre mostrar ou não a senha">
                                <svg id="eyeIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        Entrar
                    </button>
                </form>

            </div>
        </div>
    </div>

    <script src="ASSETS/JS/login.js"></script>

    <script>
        
    </script>
</body>
</html>