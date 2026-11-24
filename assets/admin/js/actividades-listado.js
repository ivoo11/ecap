(()=>{
 const root=document.querySelector('.actividades-page');if(!root)return;
 const forms=[...root.querySelectorAll('.actividad-state-form')];
 forms.forEach(form=>{const select=form.querySelector('select'),actions=form.querySelector('.actividad-save'),initial=select.value;actions.hidden=true;
  select.addEventListener('change',()=>{actions.hidden=select.value===initial;});
  form.addEventListener('reset',()=>{actions.hidden=true;});
  form.addEventListener('submit',()=>{form.dataset.submitting='true';});
 });
 window.addEventListener('beforeunload',event=>{if(forms.some(f=>!f.querySelector('.actividad-save').hidden && !f.dataset.submitting)){event.preventDefault();event.returnValue='';}});
 const tools=root.querySelector('.actividades-tools'),search=root.querySelector('#actividad-buscar'),rows=[...root.querySelectorAll('[data-actividad]')];
 if(!rows.length)return;tools.hidden=false;let filter='todas';
 const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es').trim();
 const update=()=>{let count=0;const query=normalize(search.value);rows.forEach(row=>{row.hidden=!((filter==='todas'||row.dataset.status===filter)&&normalize(row.dataset.search).includes(query));if(!row.hidden)count++;});root.querySelector('.actividades-empty').hidden=count!==0;root.querySelector('#actividades-resultados').textContent=count+' actividades';};
 root.querySelectorAll('[data-filter]').forEach(button=>button.addEventListener('click',()=>{filter=button.dataset.filter;root.querySelectorAll('[data-filter]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));update();}));search.addEventListener('input',update);
})();
