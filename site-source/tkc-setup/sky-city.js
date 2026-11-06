(function(){
 'use strict';
 document.addEventListener('wpcf7mailsent',function(e){
  if(!e.target.closest('#share-your-sky'))return;
  // Pending photos are intentionally not appended to the public gallery.
  const response=e.target.querySelector('.wpcf7-response-output');
  if(response){response.setAttribute('tabindex','-1');response.focus({preventScroll:false});}
 });
})();
