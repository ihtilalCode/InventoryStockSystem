<?php
// includes/footer.php
?>
    </div> <!-- .row -->
</div> <!-- .container-fluid -->

<!-- Bootstrap JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.menu-toggle');
    if (!toggle) return;

    toggle.addEventListener('click', function () {
        document.body.classList.toggle('sidebar-collapsed');
        const expanded = !document.body.classList.contains('sidebar-collapsed');
        toggle.setAttribute('aria-expanded', String(expanded));
    });
});
</script>
</body>
</html>
