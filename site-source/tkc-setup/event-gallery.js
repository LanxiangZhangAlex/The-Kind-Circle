(function () {
  'use strict';
  let dialog, photo, caption, counter, original, previous, next;
  let items = [], index = 0, returnFocus, oldOverflow = '', touch = null;
  const et = document.documentElement.lang.startsWith('et');
  const text = et ? {title:'Tegevuse fotogalerii', close:'Sulge', prev:'Eelmine foto', next:'Järgmine foto', original:'Ava originaalpilt', error:'Pildi laadimine ebaõnnestus. Ava originaalpilt.'} : {title:'Activity photo gallery',close:'Close',prev:'Previous photo',next:'Next photo',original:'Open original image',error:'The image could not be loaded. Open the original image.'};
  function button(label) { const b=document.createElement('button');b.type='button';b.textContent=label;return b; }
  function create() {
    dialog=document.createElement('dialog');dialog.className='tkc-gallery-dialog';dialog.setAttribute('aria-label',text.title);
    const toolbar=document.createElement('div');toolbar.className='tkc-gallery-toolbar';
    original=document.createElement('a');original.target='_blank';original.rel='noopener';original.textContent=text.original;
    const close=button(text.close);close.addEventListener('click',()=>dialog.close());toolbar.append(original,close);
    const stage=document.createElement('div');stage.className='tkc-gallery-stage';photo=document.createElement('img');photo.addEventListener('error',()=>{caption.textContent=text.error;caption.hidden=false;});stage.append(photo);
    caption=document.createElement('p');caption.className='tkc-gallery-caption';
    const controls=document.createElement('div');controls.className='tkc-gallery-controls';previous=button('←');previous.setAttribute('aria-label',text.prev);next=button('→');next.setAttribute('aria-label',text.next);counter=document.createElement('span');counter.setAttribute('aria-live','polite');controls.append(previous,counter,next);
    previous.addEventListener('click',()=>show(index-1));next.addEventListener('click',()=>show(index+1));dialog.append(toolbar,stage,caption,controls);document.body.append(dialog);
    dialog.addEventListener('keydown',e=>{if(e.key==='ArrowLeft'){e.preventDefault();show(index-1);}if(e.key==='ArrowRight'){e.preventDefault();show(index+1);}});
    dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close();});
    dialog.addEventListener('close',()=>{document.body.style.overflow=oldOverflow;photo.removeAttribute('src');returnFocus?.focus({preventScroll:true});});
    stage.addEventListener('touchstart',e=>{touch=e.touches.length===1?{x:e.touches[0].clientX,y:e.touches[0].clientY}:null;},{passive:true});
    stage.addEventListener('touchend',e=>{if(!touch||!e.changedTouches.length)return;const dx=e.changedTouches[0].clientX-touch.x,dy=e.changedTouches[0].clientY-touch.y;touch=null;if(Math.abs(dx)>50&&Math.abs(dx)>Math.abs(dy))show(index+(dx<0?1:-1));},{passive:true});
  }
  function show(i) { index=(i+items.length)%items.length;const item=items[index];photo.alt=item.alt;photo.src=item.href;original.href=item.href;caption.textContent=item.caption;caption.hidden=!item.caption;counter.textContent=(index+1)+' / '+items.length;previous.hidden=next.hidden=items.length<2; }
  document.addEventListener('click',e=>{
    const link=e.target.closest('.tkc-event-gallery .tkc-gallery-link');if(!link||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||!window.HTMLDialogElement)return;
    const gallery=link.closest('[data-tkc-gallery]');if(!gallery)return;
    const links=[...gallery.querySelectorAll('.tkc-gallery-link')];items=links.map(a=>({href:a.href,alt:a.querySelector('img')?.alt||'',caption:a.closest('figure')?.querySelector('.tkcsky-caption')?.textContent||a.closest('figure')?.querySelector('figcaption')?.textContent||''}));
    if(!items.length)return;e.preventDefault();if(!dialog)create();returnFocus=link;oldOverflow=document.body.style.overflow;show(links.indexOf(link));dialog.showModal();document.body.style.overflow='hidden';dialog.querySelector('button').focus();
  });
})();
