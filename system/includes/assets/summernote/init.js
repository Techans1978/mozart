document.addEventListener('DOMContentLoaded', async function(){
  await SummernoteLoader.loadAll();
  $('textarea.js-editor-intro').summernote({ height:180, lang:'pt-BR' });
  $('textarea.js-editor').summernote({ height:420, lang:'pt-BR' });
});