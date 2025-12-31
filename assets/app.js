const copyButtons = document.querySelectorAll('button.copy');
copyButtons.forEach((btn) => {
  btn.addEventListener('click', async () => {
    const value = btn.dataset.copy;
    try {
      await navigator.clipboard.writeText(value);
      btn.textContent = 'Copied';
      setTimeout(() => (btn.textContent = 'Copy'), 1500);
    } catch (err) {
      console.error('Copy failed', err);
      btn.textContent = 'Try manually';
    }
  });
});

const hashLinks = document.querySelectorAll('a[href^="#"]');
hashLinks.forEach((link) => {
  link.addEventListener('click', (event) => {
    const targetId = link.getAttribute('href').slice(1);
    const target = document.getElementById(targetId);
    if (target) {
      event.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

const editButtons = document.querySelectorAll('.edit-toggle');
editButtons.forEach((btn) => {
  btn.addEventListener('click', () => {
    const targetId = btn.dataset.target;
    const row = document.getElementById(targetId);
    if (row) {
      row.classList.toggle('open');
      const parentRow = row.previousElementSibling;
      const parentHidden = parentRow && parentRow.style.display === 'none';
      row.style.display = row.classList.contains('open') && !parentHidden ? 'block' : 'none';
    }
  });
});

const searchInput = document.getElementById('link-search');
if (searchInput) {
  const rows = document.querySelectorAll('.table__row');
  searchInput.addEventListener('input', () => {
    const term = searchInput.value.toLowerCase();
    rows.forEach((row) => {
      const text = row.dataset.search?.toLowerCase() || '';
      const visible = text.includes(term);
      row.style.display = visible ? 'grid' : 'none';
      const editRow = row.nextElementSibling;
      if (editRow && editRow.classList.contains('edit-row')) {
        editRow.style.display = visible && editRow.classList.contains('open') ? 'block' : 'none';
      }
    });
  });
}
