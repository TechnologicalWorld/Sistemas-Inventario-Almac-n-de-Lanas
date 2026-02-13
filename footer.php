<?php

?>
            </div><!-- page-content -->
        </div><!-- content-container -->
        
        <!-- Footer -->
        <footer class="footer">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <span class="text-muted">
                        <i class="fas fa-copyright"></i> <?php echo date('Y'); ?> 
                        <?php echo EMPRESA_NOMBRE; ?> - <?php echo SISTEMA_NOMBRE; ?>
                    </span>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <span class="text-muted small">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                        <span class="mx-2">|</span>
                        <i class="fas fa-shield-alt"></i> <?php echo ucfirst($_SESSION['usuario_rol']); ?>
                    </span>
                </div>
            </div>
        </footer>
    </main>

    <!-- Modal de carga -->
    <div class="modal fade" id="loadingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-5">
                    <div class="spinner-border text-success" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-4 mb-0 fw-bold">Procesando...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/sidebar.js"></script>
    
    <?php if (basename($_SERVER['PHP_SELF']) == 'ventas.php'): ?>
    <script src="js/ventas.js"></script>
    <?php endif; ?>
    
    <script>
    // Funciones globales
    function mostrarLoading() {
        new bootstrap.Modal(document.getElementById('loadingModal')).show();
    }
    
    function ocultarLoading() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
        if (modal) modal.hide();
    }
    
    // Auto-hide alerts
    $(document).ready(function() {
        setTimeout(() => $('.alert').fadeOut('slow'), 5000);
        
        // Tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
    </script>
</body>
</html>