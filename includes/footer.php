    </main>
  </div>
</div>

<!-- Generic delete confirmation -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Please confirm</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="confirmText">Are you sure?</div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" id="confirmYes">Yes, delete</button></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Confirmation before destructive actions: <form class="js-confirm" data-message="...">
(function () {
  let pending = null;
  const modalEl = document.getElementById('confirmModal');
  const modal = new bootstrap.Modal(modalEl);
  document.addEventListener('submit', function (ev) {
    const f = ev.target;
    if (f.classList.contains('js-confirm')) {
      ev.preventDefault();
      pending = f;
      document.getElementById('confirmText').textContent = f.dataset.message || 'Are you sure?';
      modal.show();
    }
  });
  document.getElementById('confirmYes').addEventListener('click', function () {
    if (pending) { const f = pending; pending = null; f.submit(); }
  });
  // Bootstrap client-side validation
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
    });
  });
})();
</script>
<?= $extraScripts ?? '' ?>
</body>
</html>
