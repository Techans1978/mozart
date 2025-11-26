(function() {
  var KEY = 'startmin.sidebarCollapsed';

  function setCollapsed(collapsed) {
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    var btn = document.getElementById('sidebarToggle');
    if (btn) btn.setAttribute('aria-expanded', String(!collapsed));
    try { localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch(e) {}
  }

  // restaurar estado salvo
  try {
    var saved = localStorage.getItem(KEY);
    if (saved === '1') setCollapsed(true);
  } catch(e) {}

  document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('sidebarToggle');
    if (!btn) return;
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      setCollapsed(!document.body.classList.contains('sidebar-collapsed'));
    });
  });
})();

document.addEventListener('DOMContentLoaded', function () {
  var items = document.querySelectorAll('#side-menu > li > a');

  [].forEach.call(items, function(a){
    // pega texto "visível" que não está em elementos (nós de texto)
    var textNodes = [];
    a.childNodes.forEach(function(n){
      if (n.nodeType === 3 && n.nodeValue.trim()) textNodes.push(n);
    });
    if (!a.querySelector('.menu-text')) {
      var label = textNodes.map(function(n){ return n.nodeValue.trim(); }).join(' ');
      // remove nós de texto soltos
      textNodes.forEach(function(n){ a.removeChild(n); });
      // cria o span para o texto
      var span = document.createElement('span');
      span.className = 'menu-text';
      span.textContent = label;
      a.appendChild(document.createTextNode(' '));
      a.appendChild(span);
      // tooltip via title (funciona mesmo sem Bootstrap JS)
      if (!a.getAttribute('title')) a.setAttribute('title', label);
      a.setAttribute('aria-label', label);
    }
  });

  // se usar Bootstrap, ative os tooltips bonitos (opcional)
  if (window.jQuery && jQuery.fn.tooltip) {
    jQuery('#side-menu > li > a[title]').tooltip({ container: 'body', placement: 'right' });
  }
});

document.addEventListener('DOMContentLoaded', function () {
  // marca li que têm submenu
  document.querySelectorAll('#side-menu li').forEach(function(li){
    if (li.querySelector(':scope > .nav-second-level, :scope > .nav-third-level')) {
      li.classList.add('has-submenu');
      var a = li.querySelector(':scope > a');
      if (a && !a.getAttribute('aria-haspopup')) {
        a.setAttribute('aria-haspopup', 'true');
        a.setAttribute('aria-expanded', 'false');
      }
    }
  });

  // Teclado: abre flyout com seta direita no colapsado
  document.querySelectorAll('#side-menu > li.has-submenu > a').forEach(function(a){
    a.addEventListener('keydown', function(e){
      if (document.body.classList.contains('sidebar-collapsed') && (e.key === 'ArrowRight' || e.key === 'Enter')) {
        e.preventDefault();
        var submenu = a.parentElement.querySelector(':scope > .nav-second-level');
        if (submenu) {
          submenu.style.display = 'block';
          a.setAttribute('aria-expanded', 'true');
          submenu.querySelector('a')?.focus();
        }
      }
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
  // 1) Garante <span class="menu-text"> e "title" nos links do 1º nível
  document.querySelectorAll('#side-menu > li > a').forEach(function(a){
    // cria span para o texto se não existir
    if (!a.querySelector('.menu-text')) {
      var txt = Array.from(a.childNodes)
        .filter(n => n.nodeType === 3 && n.nodeValue.trim())
        .map(n => n.nodeValue.trim()).join(' ');
      if (txt) {
        Array.from(a.childNodes).forEach(n => { if (n.nodeType === 3) n.remove(); });
        var span = document.createElement('span');
        span.className = 'menu-text';
        span.textContent = txt;
        a.appendChild(document.createTextNode(' '));
        a.appendChild(span);
      }
      if (!a.getAttribute('title') && txt) a.setAttribute('title', txt);
      if (txt) a.setAttribute('aria-label', txt);
    }
    // 2) passa o título para o flyout (data-title)
    var li = a.parentElement;
    var submenu = li && li.querySelector(':scope > .nav-second-level');
    var label = (a.querySelector('.menu-text')?.textContent || a.textContent || '').trim();
    if (submenu && label) submenu.setAttribute('data-title', label);
  });

  // 3) marca quem tem submenu (2º e 3º níveis)
  document.querySelectorAll('#side-menu li').forEach(function(li){
    if (li.querySelector(':scope > .nav-second-level, :scope > .nav-third-level')) {
      li.classList.add('has-submenu');
      var a = li.querySelector(':scope > a');
      if (a) { a.setAttribute('aria-haspopup','true'); a.setAttribute('aria-expanded','false'); }
    }
  });

  // 4) Mantém o flyout dentro da tela (ajusta top dinamicamente)
  function clampFlyoutPosition() {
    if (!document.body.classList.contains('sidebar-collapsed')) return;
    document.querySelectorAll('#side-menu > li > .nav-second-level').forEach(function(ul){
      if (getComputedStyle(ul).display === 'none') return;
      var r = ul.getBoundingClientRect();
      var overflowBottom = r.bottom - window.innerHeight;
      var overflowTop = -r.top;

      ul.style.transform = '';
      if (overflowBottom > 0) {
        ul.style.transform = 'translateY(' + (-overflowBottom - 8) + 'px)';
      } else if (overflowTop > 0) {
        ul.style.transform = 'translateY(' + (overflowTop + 8) + 'px)';
      }
    });
  }

  // Recalcula ao mover o mouse nos itens do 1º nível
  document.querySelectorAll('#side-menu > li').forEach(function(li){
    li.addEventListener('mouseenter', clampFlyoutPosition);
  });
  window.addEventListener('resize', clampFlyoutPosition);
});

document.addEventListener('DOMContentLoaded', function () {
  // marca itens do 2º nível que têm 3º nível
  document.querySelectorAll('#side-menu .nav-second-level > li').forEach(function(li){
    if (li.querySelector(':scope > .nav-third-level')) {
      li.classList.add('has-submenu');
      var a = li.querySelector(':scope > a');
      if (a) { a.setAttribute('aria-haspopup','true'); a.setAttribute('aria-expanded','false'); }
    }
  });

  // evita que o 3º nível saia da viewport (ajuste vertical)
  function clampThirdLevel() {
    if (!document.body.classList.contains('sidebar-collapsed')) return;
    document.querySelectorAll('#side-menu .nav-second-level > li.has-submenu > .nav-third-level')
      .forEach(function(ul){
        if (getComputedStyle(ul).display === 'none') return;
        ul.style.transform = '';
        var r = ul.getBoundingClientRect();
        var overflowBottom = r.bottom - window.innerHeight;
        var overflowTop = -r.top;
        if (overflowBottom > 0) {
          ul.style.transform = 'translateY(' + (-overflowBottom - 8) + 'px)';
        } else if (overflowTop > 0) {
          ul.style.transform = 'translateY(' + (overflowTop + 8) + 'px)';
        }
      });
  }

  // recalcula quando abre o 2º nível e quando passa o mouse no item do 2º
  document.querySelectorAll('#side-menu > li').forEach(function(li){
    li.addEventListener('mouseenter', clampThirdLevel);
  });
  document.querySelectorAll('#side-menu .nav-second-level > li.has-submenu').forEach(function(li){
    li.addEventListener('mouseenter', clampThirdLevel);
  });
  window.addEventListener('resize', clampThirdLevel);
});