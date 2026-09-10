(()=>{
  const start=()=>{
    document.querySelectorAll('.worked-slider').forEach(slider=>{
      if(slider.dataset.scrollMarquee==='true')return;
      const track=slider.querySelector('.worked-track');
      if(!track)return;
      slider.dataset.scrollMarquee='true';
      track.style.animation='none';
      track.style.transform='none';
      track.style.animationPlayState='paused';
      let paused=false;
      let last=performance.now();
      const speed=0.045;
      const step=now=>{
        const dt=Math.min(100,now-last);
        last=now;
        if(!paused&&slider.scrollWidth>slider.clientWidth){
          slider.scrollLeft+=speed*dt;
          const half=track.scrollWidth/2;
          if(half>0&&slider.scrollLeft>=half)slider.scrollLeft-=half;
        }
        requestAnimationFrame(step);
      };
      slider.addEventListener('mouseenter',()=>paused=true);
      slider.addEventListener('mouseleave',()=>paused=false);
      slider.addEventListener('touchstart',()=>paused=true,{passive:true});
      slider.addEventListener('touchend',()=>paused=false,{passive:true});
      requestAnimationFrame(step);
    });
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});
  else start();
})();
