<?php
/**
 * SIDEBAR COMPLETA
 * Arquivo: sidebar.php
 */
function renderSidebar() {
    // Obter variáveis de página ativa do header
    global $home_active, $usuarios_active, $animais_active, $perfil_active;
    global $calendario_active, $equipe_active, $relatorios_active, $configuracoes_active;
    
    // HTML da sidebar
    echo '
    <!-- Menu Lateral -->
    <div id="secretaria-sidebar">
        <div class="sidebar-logo-container">
            <div class="sidebar-logo">
                <i class="fas fa-school"></i>
            </div>
            <div class="sidebar-logo-text">PetCare</div>
        </div>
        <ul class="sidebar-menu">
            <li class="' . $home_active . '">
                <a href="sec_home.php">
                    <i class="fas fa-home"></i>
                    <span class="sidebar-menu-text">Home</span>
                </a>
            </li>
            <li class="' . $usuarios_active . '">
                <a href="CrudUsu.php">
                    <i class="fas fa-users"></i>
                    <span class="sidebar-menu-text">Usuários</span>
                </a>
            </li>
            <li class="' . $animais_active . '">
                <a href="CrudAnimal.php">
                    <i class="fas fa-paw"></i>
                    <span class="sidebar-menu-text">Animais</span>
                </a>
            </li>
            <li class="' . $perfil_active . '">
                <a href="perfil.php">
                    <i class="fas fa-user"></i>
                    <span class="sidebar-menu-text">Perfil</span>
                </a>
            </li>
            <li class="' . $calendario_active . '">
                <a href="calendario.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="sidebar-menu-text">Calendário</span>
                </a>
            </li>
            <li class="' . $equipe_active . '">
                <a href="equipe.php">
                    <i class="fas fa-file-alt"></i>
                    <span class="sidebar-menu-text">Equipe</span>
                </a>
            </li>
            
            <li class="' . $configuracoes_active . '">
                <a href="config.php">
                    <i class="fas fa-cog"></i>
                    <span class="sidebar-menu-text">Configurações</span>
                </a>
            </li>
            <li>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="sidebar-menu-text">Sair</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Botão Mobile -->
    <button id="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>';
}
?>