(function(){
 'use strict';
 document.addEventListener('click',function(e){
  const choose=e.target.closest('.tkcq-cover-choose'),remove=e.target.closest('.tkcq-cover-remove');
  if(!choose&&!remove)return;e.preventDefault();const box=e.target.closest('.tkcq-cover-control'),input=box.querySelector('input[name="tkcq_cover_id"]'),preview=box.querySelector('.tkcq-cover-preview');
  if(remove){input.value='0';preview.src=tkcqCover.placeholder;return;}
  const frame=wp.media({title:'Choose cover / 选择封面图',button:{text:'Use as cover / 用作封面'},library:{type:'image'},multiple:false});
  frame.on('select',function(){const image=frame.state().get('selection').first().toJSON();input.value=image.id;preview.src=image.sizes?.medium?.url||image.url;});frame.open();
 });
})();
