(()=>{
  const init=()=>{
    document.querySelectorAll('.worked-slider').forEach(slider=>{
      if(slider.dataset.marqueeClean==='1') return;

      const sourceSlides=[...slider.querySelectorAll('.worked-slide')];
      if(!sourceSlides.length) return;

      const unique=[];
      const seen=new Set();
      sourceSlides.forEach(slide=>{
        const img=slide.querySelector('img');
        const key=(img?.getAttribute('src')||img?.currentSrc||img?.alt||'').split('?')[0];
        if(key && !seen.has(key)){
          seen.add(key);
          unique.push(slide.cloneNode(true));
        }
      });

      if(!unique.length) return;

      const track=document.createElement('div');
      track.className='worked-track';
      track.setAttribute('aria-label','Companies and servers');
      track.style.cssText='display:flex!important;align-items:center!important;width:max-content!important;gap:28px!important;will-change:transform!important;animation:none!important;transform:translate3d(0,0,0)!important;';

      const addSet=(hidden=false)=>unique.forEach(slide=>{
        const item=slide.cloneNode(true);
        if(hidden) item.setAttribute('aria-hidden','true');
        track.appendChild(item);
      });

      // Two identical sets make the end of one set visually identical to the start of the next.
      addSet(false);
      addSet(true);
      slider.replaceChildren(track);
      slider.dataset.marqueeClean='1';

      let loopWidth=0;
      let x=0;
      let last=performance.now();
      let paused=false;
      const speed=42;

      const measure=()=>{
        const items=[...track.children].slice(0,unique.length);
        const gap=parseFloat(getComputedStyle(track).gap)||0;
        loopWidth=items.reduce((sum,item)=>sum+item.getBoundingClientRect().width,0)+Math.max(0,items.length-1)*gap+gap;
        if(loopWidth>0){
          x=((x % loopWidth)+loopWidth)%loopWidth;
        }
      };

      const apply=()=>track.style.transform=`translate3d(${x}px,0,0)`;
      const frame=now=>{
        const dt=Math.min(50,now-last)/1000;
        last=now;
        if(!paused && loopWidth>0){
          x-=speed*dt;
          if(x<=-loopWidth) x+=loopWidth;
          apply();
        }
        requestAnimationFrame(frame);
      };

      const canHover=()=>window.matchMedia('(hover:hover) and (pointer:fine)').matches;
      slider.addEventListener('mouseenter',()=>{ if(canHover()) paused=true; });
      slider.addEventListener('mouseleave',()=>{ if(canHover()) paused=false; });
      slider.addEventListener('touchstart',()=>{paused=true},{passive:true});
      slider.addEventListener('touchend',()=>{paused=false},{passive:true});
      window.addEventListener('resize',measure,{passive:true});

      measure();
      apply();
      requestAnimationFrame(frame);
    });
  };

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
