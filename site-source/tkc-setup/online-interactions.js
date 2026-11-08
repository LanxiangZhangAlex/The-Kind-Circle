(function () {
  'use strict';
  const seen = new WeakSet();
  function setup(box) {
    if (seen.has(box)) return; seen.add(box);
    const et = box.dataset.lang === 'et';
    const text = et ? {error:'Toiming ebaõnnestus. Proovi uuesti.', empty:'Kommentaare veel pole.', like:'Märgi postitus meeldivaks', unlike:'Eemalda meeldimine', loading:'Laadimine…'} : {error:'Something went wrong. Please try again.', empty:'No comments yet.', like:'Like this submission', unlike:'Remove your like', loading:'Loading…'};
    const heart=box.querySelector('.tkci-like'), status=box.querySelector('.tkci-status'), details=box.querySelector('.tkci-discussion'), replies=box.querySelector('.tkci-replies'), more=box.querySelector('.tkci-more'), form=box.querySelector('form');
    let offset=0, loaded=false, loading=false;
    async function request(operation, extra={}) {
      const body=new URLSearchParams({action:'tkci_public',nonce:box.dataset.nonce,kind:box.dataset.kind,entry:box.dataset.entry,lang:box.dataset.lang,operation,...extra});
      const response=await fetch(box.dataset.endpoint,{method:'POST',body,credentials:'same-origin',cache:'no-store'});
      let json; try {json=await response.json();}catch(_){throw new Error(text.error);}
      if (!response.ok || !json.success) throw new Error(json.data?.message || text.error);
      return json.data;
    }
    function update(data) {
      if (Number.isFinite(data.count)) box.querySelector('.tkci-like-count').textContent=data.count;
      if (typeof data.liked==='boolean') {heart.setAttribute('aria-pressed',String(data.liked));heart.setAttribute('aria-label',data.liked?text.unlike:text.like);}
      if (Number.isFinite(data.comments)) box.querySelector('.tkci-comment-count').textContent=data.comments;
    }
    async function load(reset=false) {
      if(loading)return; loading=true;more.disabled=true;
      if(reset){offset=0;replies.replaceChildren();}
      try {
        const data=await request('comments',{offset:String(offset)});update(data);
        for (const row of data.replies) {
          const article=document.createElement('article');article.className='tkci-reply';
          const head=document.createElement('div'),name=document.createElement('strong'),date=document.createElement('span'),body=document.createElement('p');
          name.textContent=row.nickname;date.textContent=row.date;head.append(name,date);body.textContent=row.comment;article.append(head,body);replies.append(article);
        }
        if(reset && !data.replies.length){const p=document.createElement('p');p.className='tkci-empty';p.textContent=text.empty;replies.append(p);}
        offset=data.next;more.hidden=!data.more;loaded=true;
      }catch(e){status.textContent=e.message;}finally{loading=false;more.disabled=false;}
    }
    request('state').then(data=>{update(data);heart.disabled=false;}).catch(e=>{status.textContent=e.message;});
    heart.addEventListener('click',async()=>{
      heart.disabled=true;status.textContent='';
      try{update(await request('like',{liked:heart.getAttribute('aria-pressed')==='true'?'0':'1'}));}
      catch(e){status.textContent=e.message;}finally{heart.disabled=false;}
    });
    box.querySelector('.tkci-comment-toggle').addEventListener('click',e=>{e.preventDefault();details.open=!details.open;if(details.open)details.scrollIntoView({behavior:'smooth',block:'nearest'});});
    details.addEventListener('toggle',()=>{if(details.open&&!loaded)load(true);});
    more.addEventListener('click',()=>load());
    form.addEventListener('submit',async e=>{
      e.preventDefault();if(!form.reportValidity())return;const submit=form.querySelector('.tkci-submit');submit.disabled=true;status.textContent=text.loading;
      try {
        const data=await request('reply',{nickname:form.elements.nickname.value,comment:form.elements.comment.value,website:form.elements.website.value});
        update(data);form.elements.comment.value='';status.textContent=data.message;await load(true);
      }catch(e){status.textContent=e.message;}finally{submit.disabled=false;}
    });
  }
  function scan(){document.querySelectorAll('.tkci-interactions').forEach(setup);}
  scan();
  // City Surprise replaces its thread after a new check-in without reloading the form.
  new MutationObserver(records=>{if(records.some(r=>[...r.addedNodes].some(n=>n.nodeType===1&&(n.matches('.tkci-interactions')||n.querySelector('.tkci-interactions')))))scan();}).observe(document.body,{childList:true,subtree:true});
})();
