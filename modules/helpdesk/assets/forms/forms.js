/**
 * forms.js — Renderer simples de esquemas JSON (MVP)
 * Esquema esperado:
 * {
 *   "title": "Abertura de Chamado",
 *   "fields": [
 *      {"name":"assunto","label":"Assunto","type":"text","required":true,"placeholder":"..."},
 *      {"name":"categoria","label":"Categoria","type":"select","options":[["rede","Rede"],["impressora","Impressora"]]},
 *      {"name":"descricao","label":"Descrição","type":"textarea","rows":4,"required":true}
 *   ],
 *   "submit": {"label":"Enviar","action":"ticket:create"} // ou {action:"bpm:start", "process_key":"abc"}
 * }
 */
(function(global){
  function el(tag, attrs={}, children=[]){
    const e = document.createElement(tag);
    for(const k in attrs){
      if(k==='class') e.className = attrs[k];
      else if(k==='html') e.innerHTML = attrs[k];
      else e.setAttribute(k, attrs[k]);
    }
    (children||[]).forEach(c=> e.appendChild(typeof c==='string'? document.createTextNode(c) : c));
    return e;
  }

  function render(container, schema){
    container.innerHTML = '';
    if(!schema || !Array.isArray(schema.fields)){ container.textContent='Schema inválido.'; return; }
    if(schema.title){ container.appendChild(el('h4', {class:'mb-2'}, [schema.title])); }
    const form = el('form', {class:'moz-form'}, []);
    schema.fields.forEach(f=>{
      const g = el('div', {class:'mb-2'}, []);
      if(f.label) g.appendChild(el('label', {}, [f.label]));
      let input;
      if(f.type==='textarea'){
        input = el('textarea', {name:f.name, rows:f.rows||4, class:'form-control', placeholder:f.placeholder||''});
      } else if(f.type==='select'){
        input = el('select', {name:f.name, class:'form-control'});
        (f.options||[]).forEach(opt=>{
          const [val, txt] = Array.isArray(opt)? opt : [opt, opt];
          input.appendChild(el('option', {value:val}, [txt]));
        });
      } else {
        input = el('input', {name:f.name, type:f.type||'text', class:'form-control', placeholder:f.placeholder||''});
      }
      if(f.required) input.required = true;
      g.appendChild(input);
      if(f.help) g.appendChild(el('div', {class:'text-muted', html:f.help}));
      form.appendChild(g);
    });
    const btn = el('button', {type:'submit', class:'btn btn-primary'}, [ (schema.submit && schema.submit.label) || 'Enviar' ]);
    form.appendChild(btn);

    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      const fd = new FormData(form);
      const data = {}; for(const [k,v] of fd.entries()) data[k]=v;
      container.dispatchEvent(new CustomEvent('mozform:submit', {detail:{schema, data}}));
    });

    container.appendChild(form);
  }

  global.MozForms = { render };
})(window);
