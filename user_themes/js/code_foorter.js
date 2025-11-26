    const root=document.documentElement;
    const appThemeKey='mozart_theme';
    const navCollapsedKey='mozart_nav_collapsed';
    const btnTheme=document.getElementById('btnTheme');
    const btnNovo=document.getElementById('btnNovo');
    const modal=document.getElementById('modalNovo');

    function setTheme(v){
      document.body.setAttribute('data-theme', v);
      try{localStorage.setItem(appThemeKey,v)}catch(e){}
    }
    function toggleTheme(){
      const cur=document.body.getAttribute('data-theme')||'light';
      setTheme(cur==='light'?'dark':'light');
    }
    function toggleModal(show){ modal.style.display = show? 'flex':'none'; }

    // Sidebar collapse
    const btnCollapse = document.getElementById('btnCollapse');
    function setNavCollapsed(collapsed){
      document.body.classList.toggle('nav-collapsed', !!collapsed);
      try{ localStorage.setItem(navCollapsedKey, collapsed ? '1':'0'); }catch(e){}
    }

    // Dropdown Perfil
    const profileDropdown = document.getElementById('profileDropdown');
    const btnPerfil = document.getElementById('btnPerfil');
    function closeAllDropdowns(){ profileDropdown.classList.remove('open'); btnPerfil.setAttribute('aria-expanded','false'); }
    btnPerfil.addEventListener('click', (e)=>{
      e.stopPropagation();
      const isOpen=profileDropdown.classList.toggle('open');
      btnPerfil.setAttribute('aria-expanded', isOpen?'true':'false');
    });
    document.addEventListener('click', (e)=>{
      if(!profileDropdown.contains(e.target)) closeAllDropdowns();
    });
    document.addEventListener('keydown', (e)=>{
      if(e.key==='Escape') closeAllDropdowns();
    });

    // init
    (function(){
      setTheme(localStorage.getItem(appThemeKey)||'light');
      if((localStorage.getItem(navCollapsedKey)||'0')==='1') setNavCollapsed(true);
    })();

    btnTheme.addEventListener('click', toggleTheme);
    btnNovo.addEventListener('click', ()=> toggleModal(true));
    modal.addEventListener('click', (e)=>{ if(e.target===modal) toggleModal(false); });
    if(btnCollapse){
      btnCollapse.addEventListener('click', ()=>{
        const willCollapse = !document.body.classList.contains('nav-collapsed');
        setNavCollapsed(willCollapse);
      });
    }

    // (Preparado p/ Bootstrap futuro): se você quiser, basta injetar o CSS do Bootstrap aqui.
    // Mantive classes e estrutura neutras para evitar conflitos agora.