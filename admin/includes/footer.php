  </div><!-- /.admin-content -->
</main><!-- /.admin-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(url, name) {
  if (confirm(`Delete "${name}"? This cannot be undone.`)) {
    window.location.href = url;
  }
}
</script>
<?= $adminScripts ?? '' ?>
</body>
</html>
