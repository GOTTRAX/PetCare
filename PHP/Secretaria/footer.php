<?php
// footer.php - Rodapé com scripts
?>
    </div><!-- Fechamento do .content -->
    
    <script>
        // Script para controle da sidebar
        document.addEventListener("DOMContentLoaded", function() {
            const toggleButton = document.getElementById("sidebar-toggle");
            const sidebar = document.getElementById("secretaria-sidebar");
            const content = document.querySelector(".content");
            
            // Toggle do menu mobile
            if (toggleButton && sidebar) {
                toggleButton.addEventListener("click", function() {
                    sidebar.classList.toggle("mobile-open");
                    
                    if (sidebar.classList.contains("mobile-open")) {
                        content.style.filter = "brightness(0.7)";
                        content.style.pointerEvents = "none";
                    } else {
                        content.style.filter = "brightness(1)";
                        content.style.pointerEvents = "auto";
                    }
                });
            }
            
            // Fechar menu ao clicar em um item (mobile)
            if (window.innerWidth <= 768) {
                const menuItems = document.querySelectorAll(".sidebar-menu a");
                menuItems.forEach(item => {
                    item.addEventListener("click", function() {
                        sidebar.classList.remove("mobile-open");
                        content.style.filter = "brightness(1)";
                        content.style.pointerEvents = "auto";
                    });
                });
            }
        });
        
        // Verificar redimensionamento da janela
        window.addEventListener("resize", function() {
            const sidebar = document.getElementById("secretaria-sidebar");
            const content = document.querySelector(".content");
            
            if (window.innerWidth > 768 && sidebar) {
                sidebar.classList.remove("mobile-open");
                content.style.filter = "brightness(1)";
                content.style.pointerEvents = "auto";
            }
        });
    </script>
</body>
</html>