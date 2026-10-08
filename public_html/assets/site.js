'use strict';
document.body.classList.add('js');
const toggle=document.querySelector('.menu-toggle');
const menu=document.querySelector('#main-nav');
if(toggle&&menu){
 const close=()=>{menu.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');};
 toggle.addEventListener('click',()=>{const open=menu.classList.toggle('is-open');toggle.setAttribute('aria-expanded',String(open));});
 menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',close));
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu.classList.contains('is-open')){close();toggle.focus();}});
}
const mapButton=document.getElementById('load-map');
if(mapButton)mapButton.addEventListener('click',()=>{const frame=document.getElementById('campus-map');if(!frame)return;frame.src=frame.dataset.src;frame.hidden=false;mapButton.closest('.map-placeholder').hidden=true;});
