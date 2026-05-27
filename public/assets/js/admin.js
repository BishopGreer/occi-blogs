// OCCI Blogs Admin JS

// Auto-dismiss alerts after 5 seconds
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 5000);
});

// Confirm dangerous actions (data-confirm attribute)
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});
