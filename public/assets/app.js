(() => {
  const root = document.documentElement;
  const saved = localStorage.getItem('homa-theme');
  const preferred = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  root.dataset.theme = saved || preferred;

  document.addEventListener('click', async (event) => {
    const themeButton = event.target.closest('[data-theme-toggle]');
    if (themeButton) {
      root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
      localStorage.setItem('homa-theme', root.dataset.theme);
    }
    if (event.target.closest('[data-sidebar]')) document.getElementById('sidebar')?.classList.toggle('open');
    const copyButton = event.target.closest('[data-copy]');
    if (copyButton) {
      const input = document.querySelector(copyButton.dataset.copy);
      if (!input) return;
      await navigator.clipboard.writeText(input.value);
      const old = copyButton.textContent; copyButton.textContent = 'کپی شد';
      setTimeout(() => copyButton.textContent = old, 1400);
    }
  });
})();
