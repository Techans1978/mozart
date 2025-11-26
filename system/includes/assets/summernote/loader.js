(function(w){
  function loadScript(src){return new Promise((res,rej)=>{var s=document.createElement('script');s.src=src;s.onload=res;s.onerror=rej;document.head.appendChild(s);});}
  function loadStyle(h){return new Promise((res,rej)=>{var l=document.createElement('link');l.rel='stylesheet';l.href=h;l.onload=res;l.onerror=rej;document.head.appendChild(l);});}

  w.SummernoteLoader = {
    base: '<?= BASE_URL ?>/system/includes/assets/summernote',
    async loadAll(){
      const b=this.base;
      // BS3 build
      await loadStyle(b+'/summernote.css');
      await loadScript(b+'/summernote.min.js');
      await loadScript(b+'/lang/summernote-pt-BR.min.js');

      // (se quiser depois: carregar plugins terceiros aqui)
      // await loadScript(b+'/plugin/emoji/summernote-emoji.js'); etc.
    }
  };
})(window);
