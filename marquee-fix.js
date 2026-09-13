(()=>{
  const init=()=>{
    document.querySelectorAll('.worked-slider').forEach(slider=>{
      if(slider.dataset.marqueeClean==='1') return;

      slider.innerHTML=`
        <div class="worked-track" aria-label="Companies and servers">
          <div class="worked-slide"><a href="https://eywamc.com" target="_blank" rel="noopener noreferrer"><img src="/assets/workedwith/eywamc.png" alt="EywaMC" loading="eager" decoding="async"></a></div>
          <div class="worked-slide"><a href="https://h20.gg/" target="_blank" rel="noopener noreferrer"><img src="/assets/workedwith/h20esports.png" alt="H20 Esports" loading="eager" decoding="async"></a></div>
          <div class="worked-slide"><a href="https://dc.steampunksmp.com" target="_blank" rel="noopener noreferrer"><img src="/assets/workedwith/steampunksmp.png" alt="Steampunk SMP" loading="eager" decoding="async"></a></div>
          <div class="worked-slide"><a href="https://www.stichtingsuperhelden.nl/" target="_blank" rel="noopener noreferrer"><img src="/assets/workedwith/stichtingssuperhelden.png" alt="Stichting Superhelden" loading="eager" decoding="async"></a></div>
        </div>`;

      const track=slider.querySelector('.worked-track');
      const base=[...track.children];
      track.style.cssText='display:flex!important;align-items:center!important;width:max-content!important;gap:28px!important;will-change:transform!important;animation:none!important;transform:translate3d(0,0,0)!important;';
      base.forEach(slide=>slide.querySelector('img')?.setAttribute('draggable','false'));
      base.forEach(slide=>track.appendChild(slide.cloneNode(true)));

      let loopWidth=0,x=0,last=performance.now(),paused=false;
      const measure=()=>{
        const items=[...track.children].slice(0,base.length);
        const gap=parseFloat(getComputedStyle(track).gap)||0;
        loopWidth=items.reduce((sum,item)=>sum+item.getBoundingClientRect().width,0)+gap*base.length;
      };
      const apply=()=>track.style.transform=`translate3d(${x}px,0,0)`;
      const frame=now=>{
        const dt=Math.min(50,now-last)/1000; last=now;
        if(!paused&&loopWidth){x-=42*dt;if(x<=-loopWidth)x+=loopWidth;apply()}
        requestAnimationFrame(frame);
      };
      const canHover=()=>window.matchMedia('(hover:hover) and (pointer:fine)').matches;
      slider.addEventListener('mouseenter',()=>{if(canHover())paused=true});
      slider.addEventListener('mouseleave',()=>{if(canHover())paused=false});
      slider.addEventListener('touchstart',()=>paused=true,{passive:true});
      slider.addEventListener('touchend',()=>paused=false,{passive:true});
      window.addEventListener('resize',measure,{passive:true});
      slider.dataset.marqueeClean='1';
      measure();apply();requestAnimationFrame(frame);
    });
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
