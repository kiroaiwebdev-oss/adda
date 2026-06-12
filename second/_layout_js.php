<?php
/** Shared JS bottom partial for manager panel pages. */
?>
<script>
(function(){
  const t = document.querySelector('[data-theme-toggle]');
  const r = document.documentElement;
  const stored = localStorage.getItem('mgr-theme');
  let d = stored || (matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light');
  r.setAttribute('data-theme', d);
  if(t) t.addEventListener('click', () => {
    d = d === 'dark' ? 'light' : 'dark';
    r.setAttribute('data-theme', d);
    localStorage.setItem('mgr-theme', d);
  });
})();

const _menuBtn = document.getElementById('menuBtn');
const _sidebar = document.getElementById('sidebar');
if(_menuBtn && _sidebar){
  _menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    _sidebar.classList.toggle('open');
  });
  document.addEventListener('click', (e) => {
    if(!_sidebar.contains(e.target) && !_menuBtn.contains(e.target))
      _sidebar.classList.remove('open');
  });
}
</script>
