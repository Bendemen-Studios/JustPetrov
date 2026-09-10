(()=>{
  const init=()=>{
    document.querySelectorAll('.worked-slider').forEach(slider=>{
      if(slider.dataset.marqueeClean==='1') return;
      const oldTrack=slider.querySelector('.worked-track');
      if(!oldTrack) return;

      const unique=[];
      const seen=new Set();
      oldTrack.querySelectorAll('.worked-slide').forEach(slide=>{
        const img=slide.querySelector('img');
        const key=img?.currentSrc || img?.src || img?.alt || '';
        if(key && !seen.has(key)){
          seen.add(key);
          unique.push(slide.cloneNode(true));
        }
      });
      if(unique.length<2) return;

      const track=document.createElement('div');
      track.className='worked-track';
      track.setAttribute('aria-label','Companies and servers');
      track.style.cssText='position:absolute!important;left:0!important;top:50%!important;transform:translateY(-50%)!important;display:flex!important;align-items:center!important;width:max-content!important;gap:28px!important;animation:none!important;will-change:left;';

      const addSet=hidden=>unique.forEach(slide=>{
        const item=slide.cloneNode(true);
        if(hidden) item.setAttribute('aria-hidden','true');
        track.appendChild(item);
      });
      addSet(false);
      addSet(true);
      while(track.scrollWidth < slider.clientWidth * 2) addSet(true);

      slider.replaceChildren(track);
      slider.dataset.marqueeClean='1';

      let loopWidth=0;
      const measure=()=>{
        const firstCount=unique.length;
        const items=[...track.children].slice(0,firstCount);
        const gap=parseFloat(getComputedStyle(track).gap)||0;
        loopWidth=items.reduce((sum,item)=>sum+item.getBoundingClientRect().width,0)+Math.max(0,firstCount-1)*gap+gap;
      };
      measure();

      let x=0;
      let last=performance.now();
      let paused=false;
      const speed=42;
      const frame=now=>{
        const dt=Math.min(50,now-last)/1000;
        last=now;
        if(!paused && loopWidth>0){
          x-=speed*dt;
          if(x<=-loopWidth) x+=loopWidth;
          track.style.setProperty('left',`${x}px`,'important');
        }
        requestAnimationFrame(frame);
      };

      slider.addEventListener('mouseenter',()=>paused=true);
      slider.addEventListener('mouseleave',()=>paused=false);
      slider.addEventListener('touchstart',()=>paused=true,{passive:true});
      slider.addEventListener('touchend',()=>paused=false,{passive:true});
      window.addEventListener('resize',measure,{passive:true});
      requestAnimationFrame(frame);
    });
  };

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
