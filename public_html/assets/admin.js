'use strict';
const picker=document.querySelector('#news-image');
const preview=document.querySelector('#image-preview');
let previewURL;
if(picker&&preview)picker.addEventListener('change',()=>{if(previewURL)URL.revokeObjectURL(previewURL);const file=picker.files[0];if(!file){preview.hidden=true;return;}if(file.size>5*1024*1024){picker.setCustomValidity('Rasm 5 MB dan katta bo‘lmasin.');picker.reportValidity();preview.hidden=true;return;}picker.setCustomValidity('');previewURL=URL.createObjectURL(file);preview.src=previewURL;preview.hidden=false;});
const editor=document.querySelector('#news-editor');
let dirty=false;
if(editor){editor.addEventListener('input',()=>{dirty=true;});editor.addEventListener('submit',()=>{dirty=false;});window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});}
document.querySelectorAll('[data-toggle-password]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById(button.dataset.togglePassword);if(input){input.type=input.type==='password'?'text':'password';button.textContent=input.type==='password'?'Parolni ko‘rsatish':'Parolni yashirish';}}));
