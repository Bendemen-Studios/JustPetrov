(()=>{
  const start=()=>{
    document.querySelectorAll('.worked-slider').forEach(slider=>{
      const track=slider.querySelector('.worked-track');
      if(!track)return;
      let paused=false;
      let last=performance.now();
      let offset=0;
      let loopWidth=0;
      const measure=()=>{
        const children=[...track.children];
        if(children.length<2){loopWidth=track.scrollWidth;return}
        loopWidth=track.scrollWidth/2;
        if(loopWidth>0 && Math.abs(offset)>=loopWidth)offset%=loopWidth;
      };
      measure();
      const speed=42;
      const tick=(now)=>{
        const dt=Math.min(100,now-last)/1000;
        last=now;
        if(!paused){
          if(!loopWidth)measure();
          offset-=speed*dt;
          if(loopWidth>0 && -offset>=loopWidth)offset+=loopWidth;
          track.style.transform=`translate3d(${offset}px,0,0)`;
        }
        requestAnimationFrame(tick);
      };
      slider.addEventListener('mouseenter',()=>paused=true);
      slider.addEventListener('mouseleave',()=>paused=false);
      slider.addEventListener('touchstart',()=>paused=true,{passive:true});
      slider.addEventListener('touchend',()=>paused=false,{passive:true});
      window.addEventListener('resize',measure,{passive:true});
      requestAnimationFrame(tick);
    });
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});
  else start();
})();
