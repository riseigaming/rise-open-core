(() => {
 const header = document.querySelector('.globalTopShell');
 if (header) { const measure = () => document.documentElement.style.setProperty('--journey-header-height', header.getBoundingClientRect().height+'px'); measure(); new ResizeObserver(measure).observe(header); }
 const groups = [...document.querySelectorAll('.journeyTopGroup')];
 groups.forEach(group => group.addEventListener('toggle', () => { if (group.open) groups.forEach(other => { if (other !== group) other.open = false; }); }));
 document.addEventListener('click', event => groups.forEach(group => { if (!group.contains(event.target)) group.open = false; }));
 document.addEventListener('keydown', event => { if (event.key === 'Escape') groups.forEach(group => { if(group.open){group.open=false;group.querySelector('summary').focus();} }); });
 const showProviders = () => { if (location.hash === '#catalog-providers') { const section = document.getElementById('catalog-providers'); if (section) section.open = true; } };
 showProviders(); window.addEventListener('hashchange', showProviders);
})();
