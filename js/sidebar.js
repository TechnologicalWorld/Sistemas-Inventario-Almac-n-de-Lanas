// sidebar.js - Control completo del sidebar

class SidebarManager {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.mainContent = document.getElementById('mainContent');
        this.overlay = document.getElementById('sidebarOverlay');
        this.collapseBtn = document.getElementById('sidebarCollapse');
        this.mobileToggle = document.getElementById('mobileToggle');
        this.navLinks = document.querySelectorAll('.nav-link');
        
        this.isMobile = window.innerWidth < 769;
        this.isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        this.init();
        this.attachEvents();
    }
    
    init() {
        // Aplicar estado inicial solo en desktop
        if (!this.isMobile && this.isCollapsed) {
            this.sidebar.classList.add('collapsed');
        }
        
        // Restaurar posición de scroll
        const scrollPos = sessionStorage.getItem('sidebarScroll');
        if (scrollPos) {
            const nav = this.sidebar.querySelector('.sidebar-nav');
            if (nav) nav.scrollTop = parseInt(scrollPos);
        }
    }
    
    attachEvents() {
        // Toggle collapse (desktop)
        if (this.collapseBtn) {
            this.collapseBtn.addEventListener('click', () => this.toggleCollapse());
        }
        
        // Toggle mobile
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => this.openMobile());
        }
        
        // Cerrar con overlay
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeMobile());
        }
        
        // Cerrar al hacer clic en enlaces (móvil)
        this.navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (this.isMobile) {
                    this.closeMobile();
                }
                this.saveScrollPosition();
            });
        });
        
        // Resize handler
        window.addEventListener('resize', () => this.handleResize());
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Prevenir cierre al hacer clic dentro del sidebar
        this.sidebar.addEventListener('click', (e) => e.stopPropagation());
    }
    
    toggleCollapse() {
        if (!this.isMobile) {
            this.sidebar.classList.toggle('collapsed');
            this.isCollapsed = this.sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', this.isCollapsed);
        }
    }
    
    openMobile() {
        this.sidebar.classList.add('active');
        this.overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    closeMobile() {
        this.sidebar.classList.remove('active');
        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth < 769;
        
        if (wasMobile !== this.isMobile) {
            if (this.isMobile) {
                // Cambió a móvil
                this.sidebar.classList.remove('collapsed');
                this.closeMobile();
            } else {
                // Cambió a desktop
                this.closeMobile();
                if (this.isCollapsed) {
                    this.sidebar.classList.add('collapsed');
                }
            }
        }
    }
    
    handleKeyboard(e) {
        // ESC para cerrar en móvil
        if (e.key === 'Escape' && this.isMobile) {
            this.closeMobile();
        }
        
        // Ctrl + B para toggle en desktop
        if (e.ctrlKey && e.key === 'b' && !this.isMobile) {
            e.preventDefault();
            this.toggleCollapse();
        }
    }
    
    saveScrollPosition() {
        const nav = this.sidebar.querySelector('.sidebar-nav');
        if (nav) {
            sessionStorage.setItem('sidebarScroll', nav.scrollTop);
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    new SidebarManager();
});