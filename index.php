<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BrewTopia - POS Inventory System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="landing-container">
        <div class="landing-content">
            <div class="landing-inner">
                <div class="hero-left">
                    <div class="landing-logo">
                        <img src="assets/img/9bar-pos-logo2.png" alt="9Bars Logo">
                    </div>

                    <h1 class="landing-title">
                        9BARS<br>
                        COFFEE
                    </h1>

                    <p class="landing-subtitle">
                        Find the best drink to accompany your days
                    </p>

                    <a href="login.php" class="btn-landing">
                        Get Started
                    </a>

                    <div class="landing-footer">
                        <p>POSINVENTORY<br>Copyright \u00a9 2025</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const logo = document.querySelector('.landing-logo');
            const title = document.querySelector('.landing-title');
            
            // Animate elements on load
            setTimeout(() => {
                logo.style.transform = 'scale(1.1)';
                logo.style.transition = 'transform 0.5s ease';
                
                setTimeout(() => {
                    logo.style.transform = 'scale(1)';
                }, 500);
            }, 500);
            
            // Add hover effects
            const button = document.querySelector('.btn-landing');
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>